-- Base de datos para CyberPOS (Laneros Gamer)
-- Instituto Universitario Jesus Obrero (IUJO)

CREATE DATABASE IF NOT EXISTS `cyberpos`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cyberpos`;

-- Desactivamos temporalmente las llaves foraneas para limpiar las tablas
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- Limpieza de tablas viejas si existen
DROP TABLE IF EXISTS `pedidos_snacks`;
DROP TABLE IF EXISTS `solicitudes_tiempo`;
DROP TABLE IF EXISTS `sesiones_uso`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `equipos`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `configuracion`;

-- Tabla de usuarios (administradores, operadores y clientes)
CREATE TABLE `usuarios` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo`  VARCHAR(120) NOT NULL,
  `email`            VARCHAR(150) NOT NULL,
  `password`         VARCHAR(255) NOT NULL,
  `rol`              ENUM('administrador', 'operador', 'cliente') NOT NULL DEFAULT 'cliente',
  `avatar_url`       TEXT NULL,
  `token`            VARCHAR(64) NULL,
  `token_expira`     DATETIME NULL,
  `fecha_creacion`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `uk_usuarios_email` UNIQUE (`email`),
  INDEX `idx_usuarios_token` (`token`),
  INDEX `idx_usuarios_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equipos del cyber (PCs y Consolas) con control de tiempo
CREATE TABLE `equipos` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_equipo`       VARCHAR(100) NOT NULL,
  `numero`              VARCHAR(10) NULL,
  `tipo`                ENUM('pc', 'consola') NOT NULL DEFAULT 'pc',
  `estado`              ENUM('inactiva', 'activa', 'finalizada') NOT NULL DEFAULT 'inactiva',
  `tiempo_transcurrido` INT NOT NULL DEFAULT 0,
  `tiempo_limite`       INT NOT NULL DEFAULT 0,
  `tarifa_por_hora`     DECIMAL(10,2) NOT NULL DEFAULT 1.50,
  `cliente`             VARCHAR(120) NULL,
  `fecha_inicio`        DATETIME NULL,
  `notificado`          TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_equipos_estado` (`estado`),
  INDEX `idx_equipos_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Productos de la tienda (snacks, bebidas y comidas)
CREATE TABLE `productos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`          VARCHAR(150) NOT NULL,
  `precio`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock`           INT NOT NULL DEFAULT 0,
  `url_imagen`      TEXT NULL,
  `categoria`       VARCHAR(60) NOT NULL DEFAULT 'otro',
  `origen`          ENUM('snacks', 'personalizados') NOT NULL DEFAULT 'snacks',
  `fecha_creacion`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_productos_origen` (`origen`),
  INDEX `idx_productos_categoria` (`categoria`),
  INDEX `idx_productos_stock` (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de sesiones de juego y ventas directas de mostrador
CREATE TABLE `sesiones_uso` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`           INT NULL,
  `equipo_nombre`       VARCHAR(100) NULL,
  `cliente`             VARCHAR(120) NULL,
  `segundos_alquilados` INT NOT NULL DEFAULT 0,
  `monto_cobrado`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tipo_registro`       ENUM('alquiler_tiempo', 'venta_snack') NOT NULL DEFAULT 'venta_snack',
  `detalle`             TEXT NULL,
  `fecha_registro`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sesiones_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_sesiones_fecha` (`fecha_registro`),
  INDEX `idx_sesiones_tipo` (`tipo_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de recarga de tiempo enviadas desde las estaciones de los clientes
CREATE TABLE `solicitudes_tiempo` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`          INT NULL,
  `cliente_id`         INT NULL,
  `cliente_nombre`     VARCHAR(120) NULL,
  `minutos`            INT NOT NULL DEFAULT 0,
  `monto`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado`             ENUM('pendiente', 'esperando_cliente', 'esperando_admin', 'completada', 'cancelada')
                        NOT NULL DEFAULT 'pendiente',
  `confirmado_admin`   TINYINT(1) NOT NULL DEFAULT 0,
  `confirmado_cliente` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_solicitudes_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitudes_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_solicitudes_estado` (`estado`),
  INDEX `idx_solicitudes_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos de snacks y comida hechos desde las computadoras
CREATE TABLE `pedidos_snacks` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`      INT NULL,
  `equipo_nombre`  VARCHAR(100) NULL,
  `cliente_nombre` VARCHAR(120) NULL,
  `items`          JSON NULL,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado`         ENUM('pendiente', 'completado', 'cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pedidos_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_pedidos_estado` (`estado`),
  INDEX `idx_pedidos_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuracion general (tasa del dolar bcv, precios de PCs y consolas)
CREATE TABLE `configuracion` (
  `id`                        INT NOT NULL PRIMARY KEY,
  `tasa_bcv`                  DECIMAL(10,2) NOT NULL DEFAULT 36.85,
  `tasa_manual`               TINYINT(1) NOT NULL DEFAULT 0,
  `nombre_cyber`              VARCHAR(150) NOT NULL DEFAULT 'CyberPOS Laneros Gamer',
  `precio_hora_pc`            DECIMAL(10,2) NOT NULL DEFAULT 1.50,
  `precio_hora_consola`       DECIMAL(10,2) NOT NULL DEFAULT 2.00,
  `ultima_actualizacion_tasa` DATETIME NULL,
  `theme_color`               VARCHAR(20) NOT NULL DEFAULT '#35c029',
  `avatar_url`                TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reactivamos las llaves foraneas
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET SQL_MODE=@OLD_SQL_MODE;
