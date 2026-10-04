<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Conversión centralizada de imágenes a WebP.
 * Requiere la extensión GD con soporte WebP en PHP.
 */
class ImageWebp
{
    public static function fromUploadedFile(UploadedFile $file, string $destination, string $basename, int $quality = 86): string
    {
        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('No se pudo leer la imagen seleccionada.');
        }

        return self::fromBytes($bytes, $destination, $basename, $quality);
    }

    public static function fromBytes(string $bytes, string $destination, string $basename, int $quality = 86): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new RuntimeException('PHP GD con soporte WebP no está habilitado. Active la extensión GD en PHP para guardar imágenes en WebP.');
        }

        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            throw new RuntimeException('El archivo no contiene una imagen válida.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = 1600;

        if ($width > $maxDimension || $height > $maxDimension) {
            $scale = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            self::prepareCanvas($resized);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        } else {
            // Normalizar a truecolor para que el resultado sea consistente.
            $canvas = imagecreatetruecolor($width, $height);
            self::prepareCanvas($canvas);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $canvas;
        }

        if (!is_dir($destination) && !@mkdir($destination, 0775, true) && !is_dir($destination)) {
            imagedestroy($image);
            throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
        }
        if (!is_writable($destination)) {
            imagedestroy($image);
            throw new RuntimeException('La carpeta de imágenes no permite escritura.');
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $basename) ?: 'imagen';
        $filename = trim($safe, '-') . '_' . bin2hex(random_bytes(5)) . '.webp';
        $path = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $ok = @imagewebp($image, $path, max(60, min(95, $quality)));
        imagedestroy($image);

        if (!$ok || !is_file($path)) {
            @unlink($path);
            throw new RuntimeException('No se pudo convertir y guardar la imagen en WebP.');
        }

        return $filename;
    }

    private static function prepareCanvas($canvas): void
    {
        // WebP admite transparencia; se conserva para PNG/WebP transparentes.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
        imagealphablending($canvas, true);
    }
}
