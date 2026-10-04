<?php

namespace App\Support;

use ZipArchive;
use RuntimeException;

class SimpleXlsx
{
    public static function download(array $headers, array $rows, string $filename, string $sheetName = 'Regiones')
    {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . uniqid('xlsx_', true) . '.xlsx';

        self::write($path, $headers, $rows, $sheetName);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public static function write(string $path, array $headers, array $rows = [], string $sheetName = 'Regiones'): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo crear la carpeta para el archivo Excel: ' . $directory);
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('La carpeta no tiene permisos de escritura para crear el archivo Excel: ' . $directory);
        }

        $zip = new ZipArchive();
        $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel. Código ZipArchive: ' . (string) $result . '. Ruta: ' . $path);
        }

        $shared = [];
        $sharedMap = [];
        $getShared = function ($value) use (&$shared, &$sharedMap) {
            $value = (string) $value;
            if (!array_key_exists($value, $sharedMap)) {
                $sharedMap[$value] = count($shared);
                $shared[] = $value;
            }
            return $sharedMap[$value];
        };

        $allRows = array_merge([$headers], $rows);
        $sheetRows = [];
        foreach ($allRows as $r => $row) {
            $cells = [];
            $col = 0;
            foreach ($row as $value) {
                $idx = $getShared($value);
                $ref = self::columnName($col + 1) . ($r + 1);
                $cells[] = '<c r="' . $ref . '" t="s"><v>' . $idx . '</v></c>';
                $col++;
            }
            $sheetRows[] = '<row r="' . ($r + 1) . '">' . implode('', $cells) . '</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>';

        $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared)
            . '" uniqueCount="' . count($shared) . '">';
        foreach ($shared as $value) {
            $sst .= '<si><t xml:space="preserve">' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></si>';
        }
        $sst .= '</sst>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $sheetName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', trim($sheetName)) ?: 'Hoja1';
        $sheetName = mb_substr($sheetName, 0, 31);
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/sharedStrings.xml', $sst);
        $zip->close();
    }

    public static function read(string $path): array
    {
        /*
         * Lee XLSX directamente desde el paquete ZIP.
         *
         * Se evita XPath/SimpleXML para las celdas porque los archivos
         * generados por Excel pueden declarar varios namespaces (r, mc,
         * x14ac, xr, etc.). El lector solo necesita el XML de la hoja y
         * sharedStrings, por lo que trabajar sobre el XML permite aceptar
         * archivos de Excel/LibreOffice sin depender del alias del namespace.
         */
        $zip = new ZipArchive();

        if (!is_file($path)) {
            throw new RuntimeException('No se encontró el archivo Excel.');
        }

        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo no es un Excel XLSX válido.');
        }

        try {
            // 1. Resolver la primera hoja disponible.
            $sheetPath = self::findFirstWorksheet($zip);

            if ($sheetPath === null) {
                throw new RuntimeException('El Excel no contiene ninguna hoja de cálculo.');
            }

            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false || trim($sheetXml) === '') {
                throw new RuntimeException('No se pudo leer la hoja principal del Excel.');
            }

            // 2. Cargar sharedStrings si existe.
            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

            if ($sharedXml !== false && trim($sharedXml) !== '') {
                $shared = self::parseSharedStrings($sharedXml);
            }

            // 3. Leer las filas y celdas de la hoja.
            $rows = self::parseWorksheet($sheetXml, $shared);

            if ($rows === []) {
                return [];
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * Obtiene la primera hoja de cálculo del XLSX.
     * Normalmente es xl/worksheets/sheet1.xml, pero no se asume ese nombre.
     */
    private static function findFirstWorksheet(ZipArchive $zip): ?string
    {
        $preferred = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($preferred) !== false) {
            return $preferred;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';

            if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Lee sharedStrings.xml, incluyendo:
     * <si><t>Texto</t></si>
     * y texto enriquecido:
     * <si><r><t>Parte 1</t></r><r><t>Parte 2</t></r></si>
     */
    private static function parseSharedStrings(string $xml): array
    {
        $shared = [];

        if (!preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $xml, $items)) {
            return $shared;
        }

        foreach ($items[1] as $item) {
            $text = '';

            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $item, $texts)) {
                foreach ($texts[1] as $part) {
                    $text .= self::decodeXmlText($part);
                }
            }

            $shared[] = $text;
        }

        return $shared;
    }

    /**
     * Lee sheetData/row/c sin importar los namespaces declarados en el XLSX.
     */
    private static function parseWorksheet(string $xml, array $shared): array
    {
        $rows = [];

        if (!preg_match('/<sheetData\b[^>]*>(.*?)<\/sheetData>/is', $xml, $sheetMatch)) {
            return [];
        }

        $sheetData = $sheetMatch[1];

        if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/is', $sheetData, $rowMatches)) {
            return [];
        }

        foreach ($rowMatches[1] as $rowXml) {
            $values = [];

            /*
             * Una celda XLSX normal es:
             * <c r="A1" t="s"><v>9</v></c>
             *
             * También pueden existir celdas vacías autocerradas:
             * <c r="C1" .../>
             */
            $cellPattern = '/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/is';

            if (!preg_match_all($cellPattern, $rowXml, $cellMatches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($cellMatches as $cellMatch) {
                $attributes = $cellMatch[1] ?? '';
                $content = $cellMatch[2] ?? '';

                $ref = self::xmlAttribute($attributes, 'r');
                if ($ref === '') {
                    continue;
                }

                if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $refMatch)) {
                    continue;
                }

                $columnIndex = self::columnIndex(strtoupper($refMatch[1]));
                $type = strtolower(self::xmlAttribute($attributes, 't'));
                $value = '';

                if ($content !== '') {
                    if ($type === 's') {
                        $v = self::firstXmlValue($content, 'v');
                        $sharedIndex = ($v === '' ? -1 : (int) $v);
                        $value = $shared[$sharedIndex] ?? '';
                    } elseif ($type === 'inlineStr') {
                        // <is><t>...</t></is>, incluyendo rich text <r><t>...</t></r>.
                        $value = self::allXmlTextNodes($content);
                    } else {
                        // Números, fechas seriales, booleanos, fórmulas con resultado, etc.
                        $value = self::firstXmlValue($content, 'v');

                        if ($value === '' && $type === 'str') {
                            $value = self::allXmlTextNodes($content);
                        }
                    }
                }

                $values[$columnIndex] = $value;
            }

            if ($values === []) {
                continue;
            }

            $max = max(array_keys($values));
            $normalized = [];

            for ($i = 1; $i <= $max; $i++) {
                $normalized[] = $values[$i] ?? '';
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    private static function xmlAttribute(string $attributes, string $name): string
    {
        $pattern = '/(?:^|\s)' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/is';

        if (preg_match($pattern, $attributes, $match)) {
            return self::decodeXmlText($match[2]);
        }

        return '';
    }

    private static function firstXmlValue(string $content, string $tag): string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($tag, '/') . '>/is', $content, $match)) {
            return self::decodeXmlText($match[1]);
        }

        return '';
    }

    private static function allXmlTextNodes(string $content): string
    {
        if (!preg_match_all('/<t\b[^>]*>(.*?)<\/t>/is', $content, $matches)) {
            return '';
        }

        $text = '';
        foreach ($matches[1] as $part) {
            $text .= self::decodeXmlText($part);
        }

        return $text;
    }

    private static function decodeXmlText(string $value): string
    {
        // Excel XML usa entidades XML normales (&amp;, &lt;, &#...;, etc.).
        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $mod = ($number - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    private static function columnIndex(string $name): int
    {
        $n = 0;
        foreach (str_split($name) as $char) $n = $n * 26 + (ord($char) - 64);
        return $n;
    }
}
