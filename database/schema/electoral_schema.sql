-- ============================================================
-- SISTEMA ELECTORAL
-- ESQUEMA DEFINITIVO MySQL 8.x
-- Preparado para implementación con Laravel
-- Versión: 2.0
-- Fecha: 2026-09-18
--
-- IMPORTANTE:
-- 1) Este archivo es para instalación limpia.
-- 2) Para producción, usar las migraciones versionadas de Laravel.
-- 3) No contiene un usuario administrador con contraseña fija.
-- 4) Las reglas de negocio complejas (ámbito y validación de actas)
--    deben reforzarse en servicios/policies de Laravel y dentro de transacciones.
--    de Laravel y dentro de transacciones.
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistema_electoral
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sistema_electoral;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notificacion;
DROP TABLE IF EXISTS configuracion_sistema;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS digitacion_acta;
DROP TABLE IF EXISTS personero_mesa;
DROP TABLE IF EXISTS personero_local;
DROP TABLE IF EXISTS personero_distrito;
DROP TABLE IF EXISTS personero_provincia;
DROP TABLE IF EXISTS personero_regional;
DROP TABLE IF EXISTS usuario_ambito;
DROP TABLE IF EXISTS rol_permiso;
DROP TABLE IF EXISTS usuario_rol;
DROP TABLE IF EXISTS permiso;
DROP TABLE IF EXISTS opcion_modulo;
DROP TABLE IF EXISTS modulo;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS persona;
DROP TABLE IF EXISTS partido;
DROP TABLE IF EXISTS mesa;
DROP TABLE IF EXISTS local;
DROP TABLE IF EXISTS distrito;
DROP TABLE IF EXISTS provincia;
DROP TABLE IF EXISTS region;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. ÁMBITO
-- ============================================================

CREATE TABLE region (
    id_region BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_region_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE provincia (
    id_provincia BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_region BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provincia_region_nombre (id_region, nombre),
    CONSTRAINT fk_provincia_region
        FOREIGN KEY (id_region) REFERENCES region(id_region)
) ENGINE=InnoDB;

CREATE TABLE distrito (
    id_distrito BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_provincia BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_distrito_provincia_nombre (id_provincia, nombre),
    CONSTRAINT fk_distrito_provincia
        FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia)
) ENGINE=InnoDB;

CREATE TABLE local (
    id_local BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_distrito BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    referencia VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_local_distrito_nombre (id_distrito, nombre),
    CONSTRAINT fk_local_distrito
        FOREIGN KEY (id_distrito) REFERENCES distrito(id_distrito)
) ENGINE=InnoDB;

CREATE TABLE mesa (
    id_mesa BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_local BIGINT UNSIGNED NOT NULL,
    numero_mesa VARCHAR(20) NOT NULL,
    total_electores INT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mesa_local_numero (id_local, numero_mesa),
    CONSTRAINT fk_mesa_local
        FOREIGN KEY (id_local) REFERENCES local(id_local),
    CONSTRAINT chk_mesa_electores CHECK (total_electores > 0),
    CONSTRAINT chk_mesa_numero CHECK (numero_mesa <> '')
) ENGINE=InnoDB;

-- ============================================================
-- 2. PERSONAS Y USUARIOS
-- ============================================================

CREATE TABLE persona (
    id_persona BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(20) NOT NULL,
    nombres VARCHAR(150) NOT NULL,
    apellido_paterno VARCHAR(100) NOT NULL,
    apellido_materno VARCHAR(100) NULL,
    celular VARCHAR(30) NULL,
    correo VARCHAR(150) NULL,
    id_region BIGINT UNSIGNED NULL,
    id_provincia BIGINT UNSIGNED NULL,
    id_distrito BIGINT UNSIGNED NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_persona_dni (dni),
    CONSTRAINT fk_persona_region
        FOREIGN KEY (id_region) REFERENCES region(id_region),
    CONSTRAINT fk_persona_provincia
        FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia),
    CONSTRAINT fk_persona_distrito
        FOREIGN KEY (id_distrito) REFERENCES distrito(id_distrito)
) ENGINE=InnoDB;

CREATE TABLE usuario (
    id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    usuario VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    intentos_fallidos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    password_changed_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_persona (id_persona),
    UNIQUE KEY uq_usuario_login (usuario),
    CONSTRAINT fk_usuario_persona
        FOREIGN KEY (id_persona) REFERENCES persona(id_persona)
) ENGINE=InnoDB;

-- ============================================================
-- 3. ROLES Y PERMISOS
-- ============================================================

CREATE TABLE modulo (
    id_modulo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_modulo_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE opcion_modulo (
    id_opcion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_modulo BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_opcion_modulo_nombre (id_modulo, nombre),
    CONSTRAINT fk_opcion_modulo
        FOREIGN KEY (id_modulo) REFERENCES modulo(id_modulo)
) ENGINE=InnoDB;

CREATE TABLE rol (
    id_rol BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rol_nombre (nombre)
) ENGINE=InnoDB;

-- Un permiso pertenece a una opción concreta.
-- Si en el futuro se requiere permiso a nivel de módulo,
-- se crea una opción especial, por ejemplo "GENERAL".
CREATE TABLE permiso (
    id_permiso BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_modulo BIGINT UNSIGNED NOT NULL,
    id_opcion BIGINT UNSIGNED NOT NULL,
    accion VARCHAR(50) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_permiso (id_opcion, accion),
    CONSTRAINT fk_permiso_modulo
        FOREIGN KEY (id_modulo) REFERENCES modulo(id_modulo),
    CONSTRAINT fk_permiso_opcion
        FOREIGN KEY (id_opcion) REFERENCES opcion_modulo(id_opcion)
) ENGINE=InnoDB;

CREATE TABLE usuario_rol (
    id_usuario BIGINT UNSIGNED NOT NULL,
    id_rol BIGINT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_rol),
    CONSTRAINT fk_usuario_rol_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_usuario_rol_rol
        FOREIGN KEY (id_rol) REFERENCES rol(id_rol)
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
    id_rol BIGINT UNSIGNED NOT NULL,
    id_permiso BIGINT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rol_permiso_rol
        FOREIGN KEY (id_rol) REFERENCES rol(id_rol),
    CONSTRAINT fk_rol_permiso_permiso
        FOREIGN KEY (id_permiso) REFERENCES permiso(id_permiso)
) ENGINE=InnoDB;

-- Ámbito explícito de acceso.
-- La aplicación debe garantizar que solo se llene el nivel
-- correspondiente a "nivel" y que la cadena territorial sea válida.
CREATE TABLE usuario_ambito (
    id_usuario_ambito BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario BIGINT UNSIGNED NOT NULL,
    nivel ENUM('REGION','PROVINCIA','DISTRITO','LOCAL') NOT NULL,
    id_region BIGINT UNSIGNED NULL,
    id_provincia BIGINT UNSIGNED NULL,
    id_distrito BIGINT UNSIGNED NULL,
    id_local BIGINT UNSIGNED NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_ambito_nivel_ids
        (id_usuario, nivel, id_region, id_provincia, id_distrito, id_local),
    KEY idx_ua_usuario (id_usuario),
    KEY idx_ua_region (id_region),
    KEY idx_ua_provincia (id_provincia),
    KEY idx_ua_distrito (id_distrito),
    KEY idx_ua_local (id_local),
    CONSTRAINT fk_ua_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_ua_region
        FOREIGN KEY (id_region) REFERENCES region(id_region),
    CONSTRAINT fk_ua_provincia
        FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia),
    CONSTRAINT fk_ua_distrito
        FOREIGN KEY (id_distrito) REFERENCES distrito(id_distrito),
    CONSTRAINT fk_ua_local
        FOREIGN KEY (id_local) REFERENCES local(id_local)
) ENGINE=InnoDB;

-- ============================================================
-- 4. PARTIDOS POLÍTICOS Y CONCEPTOS ESPECIALES
-- ============================================================

CREATE TABLE partido (
    id_partido BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    tipo ENUM('POLITICO','ESPECIAL') NOT NULL DEFAULT 'POLITICO',
    logo VARCHAR(500) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partido_nombre (nombre)
) ENGINE=InnoDB;

-- ============================================================
-- 5. PERSONEROS
-- ============================================================

CREATE TABLE personero_regional (
    id_personero_regional BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    id_usuario BIGINT UNSIGNED NULL,
    id_region BIGINT UNSIGNED NOT NULL,
    condicion ENUM(
        'TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'
    ) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_region_condicion (id_region, condicion),
    KEY idx_pr_persona (id_persona),
    KEY idx_pr_usuario (id_usuario),
    CONSTRAINT fk_pr_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_pr_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_pr_region FOREIGN KEY (id_region) REFERENCES region(id_region)
) ENGINE=InnoDB;

CREATE TABLE personero_provincia (
    id_personero_provincia BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    id_usuario BIGINT UNSIGNED NULL,
    id_provincia BIGINT UNSIGNED NOT NULL,
    condicion ENUM(
        'TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'
    ) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pp_provincia_condicion (id_provincia, condicion),
    KEY idx_pp_persona (id_persona),
    KEY idx_pp_usuario (id_usuario),
    CONSTRAINT fk_pp_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_pp_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_pp_provincia FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia)
) ENGINE=InnoDB;

CREATE TABLE personero_distrito (
    id_personero_distrito BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    id_usuario BIGINT UNSIGNED NULL,
    id_distrito BIGINT UNSIGNED NOT NULL,
    condicion ENUM(
        'TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'
    ) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pd_distrito_condicion (id_distrito, condicion),
    KEY idx_pd_persona (id_persona),
    KEY idx_pd_usuario (id_usuario),
    CONSTRAINT fk_pd_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_pd_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_pd_distrito FOREIGN KEY (id_distrito) REFERENCES distrito(id_distrito)
) ENGINE=InnoDB;

CREATE TABLE personero_local (
    id_personero_local BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    id_usuario BIGINT UNSIGNED NULL,
    id_local BIGINT UNSIGNED NOT NULL,
    condicion ENUM(
        'TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'
    ) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pl_local_condicion (id_local, condicion),
    KEY idx_pl_persona (id_persona),
    KEY idx_pl_usuario (id_usuario),
    CONSTRAINT fk_pl_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_pl_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_pl_local FOREIGN KEY (id_local) REFERENCES local(id_local)
) ENGINE=InnoDB;

CREATE TABLE personero_mesa (
    id_personero_mesa BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_persona BIGINT UNSIGNED NOT NULL,
    id_mesa BIGINT UNSIGNED NOT NULL,
    condicion ENUM(
        'TITULAR','PRIMER_SUPLENTE','SEGUNDO_SUPLENTE','TERCER_SUPLENTE'
    ) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pm_mesa_condicion (id_mesa, condicion),
    KEY idx_pm_persona (id_persona),
    CONSTRAINT fk_pm_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_pm_mesa FOREIGN KEY (id_mesa) REFERENCES mesa(id_mesa)
) ENGINE=InnoDB;

-- ============================================================
-- 7. DIGITACIÓN DE ACTAS
-- ============================================================

CREATE TABLE digitacion_acta (
    id_digitacion_acta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_mesa BIGINT UNSIGNED NOT NULL,
    numero_acta VARCHAR(20) NOT NULL,
    tipo_acta TINYINT UNSIGNED NOT NULL COMMENT '1=REGIONAL, 2=MUNICIPAL',
    id_partido BIGINT UNSIGNED NOT NULL,

    campo_1 INT UNSIGNED NOT NULL DEFAULT 0,
    campo_2 INT UNSIGNED NOT NULL DEFAULT 0,

    estado_acta ENUM('CONSISTENTE','OBSERVADA') NOT NULL DEFAULT 'OBSERVADA',

    suma_campo_1 INT UNSIGNED NOT NULL DEFAULT 0,
    suma_campo_2 INT UNSIGNED NOT NULL DEFAULT 0,
    detalle_observacion JSON NULL,

    id_usuario_registro BIGINT UNSIGNED NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario_modificacion BIGINT UNSIGNED NULL,
    fecha_modificacion DATETIME NULL,

    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_digitacion_mesa_tipo_partido (id_mesa, tipo_acta, id_partido),
    KEY idx_digitacion_mesa_tipo (id_mesa, tipo_acta),
    KEY idx_digitacion_partido (id_partido),
    KEY idx_digitacion_usuario_registro (id_usuario_registro),
    KEY idx_digitacion_estado (estado_acta),

    CONSTRAINT chk_tipo_acta CHECK (tipo_acta IN (1,2)),
    CONSTRAINT chk_numero_acta_no_vacio CHECK (numero_acta <> ''),

    CONSTRAINT fk_digitacion_mesa
        FOREIGN KEY (id_mesa) REFERENCES mesa(id_mesa),
    CONSTRAINT fk_digitacion_partido
        FOREIGN KEY (id_partido) REFERENCES partido(id_partido),
    CONSTRAINT fk_digitacion_usuario_registro
        FOREIGN KEY (id_usuario_registro) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_digitacion_usuario_modificacion
        FOREIGN KEY (id_usuario_modificacion) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

-- ============================================================
-- 8. AUDITORÍA
-- ============================================================

CREATE TABLE auditoria (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario BIGINT UNSIGNED NULL,
    modulo VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(100) NULL,
    id_registro BIGINT UNSIGNED NULL,
    accion VARCHAR(50) NOT NULL,
    valor_anterior JSON NULL,
    valor_nuevo JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_auditoria_usuario (id_usuario),
    KEY idx_auditoria_tabla_registro (tabla_afectada, id_registro),
    KEY idx_auditoria_fecha (created_at),
    KEY idx_auditoria_accion (accion),

    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

-- ============================================================
-- 9. CONFIGURACIÓN GENERAL
-- ============================================================

CREATE TABLE configuracion_sistema (
    id_configuracion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    tipo_valor ENUM('STRING','INTEGER','DECIMAL','BOOLEAN','JSON','DATE','DATETIME') NOT NULL DEFAULT 'STRING',
    descripcion VARCHAR(255) NULL,
    es_critica TINYINT(1) NOT NULL DEFAULT 0,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_configuracion_clave (clave)
) ENGINE=InnoDB;

-- ============================================================
-- 10. NOTIFICACIONES
-- ============================================================

CREATE TABLE notificacion (
    id_notificacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario BIGINT UNSIGNED NOT NULL,
    tipo ENUM('SUCCESS','INFO','WARNING','ERROR') NOT NULL DEFAULT 'INFO',
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    modulo VARCHAR(100) NULL,
    referencia_tipo VARCHAR(100) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha_lectura DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_notificacion_usuario_leida (id_usuario, leida),
    KEY idx_notificacion_fecha (created_at),

    CONSTRAINT fk_notificacion_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

-- ============================================================
-- 11. DATOS BASE
-- ============================================================

INSERT INTO partido (nombre, tipo, estado) VALUES
('VOTOS BLANCOS', 'ESPECIAL', 1),
('VOTOS NULOS', 'ESPECIAL', 1),
('VOTOS IMPUGNADOS', 'ESPECIAL', 1),
('TOTAL DE VOTOS EMITIDOS', 'ESPECIAL', 1);

INSERT INTO rol (nombre, estado) VALUES
('Administrador', 1),
('Digitador', 1),
('Personero Regional', 1),
('Personero Provincial', 1),
('Personero Distrital', 1),
('Personero de Local', 1);

INSERT INTO modulo (nombre, estado) VALUES
('Ámbito', 1),
('Personas', 1),
('Usuarios', 1),
('Roles y Permisos', 1),
('Locales', 1),
('Mesas', 1),
('Partidos Políticos', 1),
('Personeros', 1),
('Digitar Acta', 1),
('Reporte', 1);

-- Opciones base.
-- "GENERAL" permite representar permisos a nivel del módulo
-- sin usar NULL en la clave única de permisos.
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'GENERAL', 1 FROM modulo;

-- Opciones funcionales principales.
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Registro', 1 FROM modulo
WHERE nombre IN ('Ámbito','Personas','Usuarios','Roles y Permisos','Locales','Mesas','Partidos Políticos');

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Ver', 1 FROM modulo
WHERE nombre IN ('Ámbito','Personas','Usuarios','Roles y Permisos','Locales','Mesas','Partidos Políticos','Digitar Acta','Reporte');

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Importar Excel', 1 FROM modulo
WHERE nombre IN ('Ámbito','Personas','Usuarios','Locales','Mesas','Partidos Políticos','Personeros');

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Exportar Excel', 1 FROM modulo
WHERE nombre IN ('Ámbito','Personas','Usuarios','Locales','Mesas','Partidos Políticos','Personeros','Reporte');

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Personero Regional', 1 FROM modulo WHERE nombre='Personeros';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Personero Provincial', 1 FROM modulo WHERE nombre='Personeros';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Personero Distrital', 1 FROM modulo WHERE nombre='Personeros';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Personero de Local', 1 FROM modulo WHERE nombre='Personeros';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Personero de Mesa', 1 FROM modulo WHERE nombre='Personeros';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Avance', 1 FROM modulo WHERE nombre='Personeros';

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Digitar', 1 FROM modulo WHERE nombre='Digitar Acta';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Ver Acta', 1 FROM modulo WHERE nombre='Digitar Acta';

INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Resultados', 1 FROM modulo WHERE nombre='Reporte';
INSERT INTO opcion_modulo (id_modulo, nombre, estado)
SELECT id_modulo, 'Seguimiento', 1 FROM modulo WHERE nombre='Reporte';

-- Parámetros iniciales.
INSERT INTO configuracion_sistema
(clave, valor, tipo_valor, descripcion, es_critica, estado) VALUES
('nombre_sistema', 'Sistema de Gestión, Digitación y Resultados Electorales', 'STRING', 'Nombre del sistema', 1, 1),
('institucion', '', 'STRING', 'Institución propietaria del sistema', 1, 1),
('periodo_electoral', '', 'STRING', 'Periodo electoral', 1, 1),
('zona_horaria', 'America/Lima', 'STRING', 'Zona horaria del sistema', 1, 1),
('formato_fecha', 'DD/MM/YYYY', 'STRING', 'Formato de presentación de fecha', 0, 1),
('formato_fecha_hora', 'DD/MM/YYYY HH:MM:SS', 'STRING', 'Formato de presentación de fecha y hora', 0, 1),
('max_intentos_login', '5', 'INTEGER', 'Máximo de intentos fallidos antes de bloqueo', 1, 1),
('minutos_bloqueo_login', '15', 'INTEGER', 'Duración del bloqueo por intentos fallidos', 1, 1),
('dias_expiracion_password', '0', 'INTEGER', '0 = sin expiración automática', 1, 1),
('max_tamano_archivo_mb', '10', 'INTEGER', 'Tamaño máximo permitido para archivos', 0, 1),
('decimales_resultados', '2', 'INTEGER', 'Cantidad de decimales en porcentajes/resultados', 0, 1);

-- ============================================================
-- FIN
-- ============================================================
