-- Datos iniciales y de prueba para CyberPOS (Laneros Gamer)
-- Instituto Universitario Jesus Obrero (IUJO)

USE `cyberpos`;

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

-- Limpiamos las tablas antes de insertar los datos
TRUNCATE TABLE `pedidos_snacks`;
TRUNCATE TABLE `solicitudes_tiempo`;
TRUNCATE TABLE `sesiones_uso`;
TRUNCATE TABLE `productos`;
TRUNCATE TABLE `equipos`;
TRUNCATE TABLE `usuarios`;
TRUNCATE TABLE `configuracion`;

-- -----------------------------------------------------------------------------
-- 1. Usuarios de prueba
-- Cuentas listas para entrar al sistema con sus roles:
--
-- Admin:        admin@laneros.com   | Clave: admin123    (rol: administrador)
-- Operador:     operador@laneros.com| Clave: operador123 (rol: operador)
-- Cajero Turno: cajero@laneros.com  | Clave: cajero123   (rol: administrador)
-- Cliente Demo: cliente@laneros.com | Clave: cliente123  (rol: cliente)
-- Kelvin Gamer: kelvin@laneros.com  | Clave: admin123    (rol: cliente)
--
-- Los hashes fueron creados con password_hash(PASSWORD_BCRYPT) de PHP
-- -----------------------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `nombre_completo`, `email`, `password`, `rol`, `avatar_url`, `token`, `token_expira`, `fecha_creacion`) VALUES
(1, 'Administrador Laneros', 'admin@laneros.com', '$2y$10$EK32893gyQJDV1lUtMyedOPHy30.AdKPZH3dk1M5mx4s3bZ0sTRQ.', 'administrador', '', NULL, NULL, '2026-10-01 10:00:00'),
(2, 'Carlos Mendoza (Operador)', 'operador@laneros.com', '$2y$10$X3obB6j4BGQl1p4yifCUkeHwTtxlk3QsVRd8RXviFktK1/Psm8TCW', 'operador', '', NULL, NULL, '2026-10-01 10:00:00'),
(3, 'Cajero de Turno POS', 'cajero@laneros.com', '$2y$10$ZvPACxt3vER00nRbDNU0SuhFMj.x8pHWibY0qmcvItwBZQEeDlrK2', 'administrador', '', NULL, NULL, '2026-10-01 10:00:00'),
(4, 'Cliente Gamer Demo', 'cliente@laneros.com', '$2y$10$xR/ncVAi7Y197PU4r7dDYuuZxkPILz49EDKCYdS1jNh1xW8VRRoSW', 'cliente', '', NULL, NULL, '2026-10-01 10:00:00'),
(5, 'Kelvin Nieto', 'kelvin@laneros.com', '$2y$10$EK32893gyQJDV1lUtMyedOPHy30.AdKPZH3dk1M5mx4s3bZ0sTRQ.', 'cliente', '', NULL, NULL, '2026-10-01 10:00:00');

-- -----------------------------------------------------------------------------
-- 2. Maquinas del cyber (6 computadoras y 4 consolas PS5)
-- Precios: $1.50 la hora de PC y $2.00 - $2.50 la hora de consola
-- -----------------------------------------------------------------------------
INSERT INTO `equipos` (`id`, `nombre_equipo`, `numero`, `tipo`, `estado`, `tiempo_transcurrido`, `tiempo_limite`, `tarifa_por_hora`, `cliente`, `fecha_inicio`, `notificado`, `fecha_creacion`) VALUES
(1,  'PC 01 - Ryzen 7 RTX 4070', '01', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(2,  'PC 02 - Ryzen 7 RTX 4070', '02', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(3,  'PC 03 - Ryzen 5 RTX 3060', '03', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(4,  'PC 04 - Ryzen 5 RTX 3060', '04', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(5,  'PC 05 - Core i5 RTX 3060', '05', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(6,  'PC 06 - Core i5 RTX 3060', '06', 'pc',      'inactiva', 0, 0, 1.50, '', NULL, 0, '2026-10-01 08:00:00'),
(7,  'PlayStation 5 - VIP 01',   '07', 'consola', 'inactiva', 0, 0, 2.50, '', NULL, 0, '2026-10-01 08:00:00'),
(8,  'PlayStation 5 - VIP 02',   '08', 'consola', 'inactiva', 0, 0, 2.50, '', NULL, 0, '2026-10-01 08:00:00'),
(9,  'PlayStation 5 - Arena 03', '09', 'consola', 'inactiva', 0, 0, 2.00, '', NULL, 0, '2026-10-01 08:00:00'),
(10, 'PlayStation 5 - Arena 04', '10', 'consola', 'inactiva', 0, 0, 2.00, '', NULL, 0, '2026-10-01 08:00:00');

-- -----------------------------------------------------------------------------
-- 3. Productos en inventario (bebidas, chucherias, comida rapida y servicios)
-- Todos con inventario disponible para hacer pruebas de compras en el POS
-- -----------------------------------------------------------------------------
INSERT INTO `productos` (`id`, `nombre`, `precio`, `stock`, `url_imagen`, `categoria`, `origen`, `fecha_creacion`) VALUES
-- Bebidas
(1,  'Monster Energy Ultra White (473ml)',     2.50, 24, 'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),
(2,  'Red Bull Energy Drink (250ml)',          2.75, 20, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),
(3,  'Coca-Cola Sabor Original (500ml)',       1.75, 35, 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),
(4,  'Pepsi Black Zero Azúcar (500ml)',        1.50, 28, 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),
(5,  'Agua Mineral Minalba (600ml)',           1.00, 50, 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),
(6,  'Jugo Yukery Naranja (250ml)',            1.20, 25, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=500&q=80', 'bebidas',   'snacks', '2026-10-01 10:00:00'),

-- Snacks y chucherias
(7,  'Doritos Mega Queso (120g)',              1.80, 26, 'https://images.unsplash.com/photo-1600952841320-db92ec4047ca?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(8,  'Papas Lays Clásicas (115g)',             1.50, 22, 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(9,  'Cheetos Torciditos Horneados (110g)',    1.60, 18, 'https://images.unsplash.com/photo-1585238342024-78d387f4a707?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(10, 'Ruffles Sabor a Queso (115g)',           1.75, 20, 'https://images.unsplash.com/photo-1527960471264-932f39eb5846?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(11, 'Chocolate Snickers Bar (52g)',           1.00, 40, 'https://images.unsplash.com/photo-1570145820259-b5b80c5c8bd6?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(12, 'Galletas Oreo Original (117g)',          1.25, 35, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),
(13, 'Pirulin Lata Tradicional (155g)',        3.50, 15, 'https://images.unsplash.com/photo-1587132137056-bfbf0166836e?w=500&q=80', 'comida',    'snacks', '2026-10-01 10:00:00'),

-- Comida preparada
(14, 'Sandwich Tostado Jamón y Queso Gamer',   3.00, 16, 'https://images.unsplash.com/photo-1528735602780-2552fd46c7af?w=500&q=80', 'comida',    'personalizados', '2026-10-01 10:00:00'),
(15, 'Hamburguesa Laneros Doble Carne',        4.50, 14, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&q=80', 'comida',    'personalizados', '2026-10-01 10:00:00'),

-- Servicios de cyber
(16, 'Servicio Impresión B/N (Hoja Carta)',    0.20, 500, 'https://images.unsplash.com/photo-1589330694653-dad6d3240a91?w=500&q=80', 'servicios', 'personalizados', '2026-10-01 10:00:00'),
(17, 'Servicio Impresión Color (Hoja Carta)',  0.60, 300, 'https://images.unsplash.com/photo-1562654501-a0ccc0fc3fb1?w=500&q=80', 'servicios', 'personalizados', '2026-10-01 10:00:00'),
(18, 'Alquiler Control DualSense PS5 Extra/hr',1.50, 15, 'https://images.unsplash.com/photo-1606318801954-d46d46d3360a?w=500&q=80', 'servicios', 'personalizados', '2026-10-01 10:00:00');

-- -----------------------------------------------------------------------------
-- 4. Configuracion del negocio (fila id = 1)
-- Tasa inicial del dolar y nombres del local
-- -----------------------------------------------------------------------------
INSERT INTO `configuracion` (
  `id`,
  `tasa_bcv`,
  `tasa_manual`,
  `nombre_cyber`,
  `precio_hora_pc`,
  `precio_hora_consola`,
  `ultima_actualizacion_tasa`,
  `theme_color`,
  `avatar_url`
) VALUES (
  1,
  54.20,                                      -- Tasa oficial referencial en Bs
  0,                                          -- Sincronizacion automatica activa
  'CyberPOS Laneros Gamer - Barquisimeto',
  1.50,
  2.50,
  NOW(),
  '#35c029',
  ''
);

-- -----------------------------------------------------------------------------
-- 5. Historial de sesiones y ventas para que el panel no arranque vacio
-- -----------------------------------------------------------------------------
INSERT INTO `sesiones_uso` (`id`, `equipo_id`, `equipo_nombre`, `cliente`, `segundos_alquilados`, `monto_cobrado`, `tipo_registro`, `detalle`, `fecha_registro`) VALUES
(1, 1,    'PC 01 - Ryzen 7 RTX 4070', 'Kelvin Nieto',       7200, 3.00,  'alquiler_tiempo', NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(2, 3,    'PC 03 - Ryzen 5 RTX 3060', 'Carlos Mendoza',     3600, 1.50,  'alquiler_tiempo', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 7,    'PlayStation 5 - VIP 01',   'Cliente Gamer Demo', 7200, 5.00,  'alquiler_tiempo', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, NULL, 'Venta Directa',            'Mostrador Público',  0,    5.00,  'venta_snack',    '[{"id":1,"nombre":"Monster Energy Ultra White (473ml)","cantidad":2,"precio":2.50}]', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(5, NULL, 'Venta Directa',            'Mostrador Público',  0,    4.50,  'venta_snack',    '[{"id":7,"nombre":"Doritos Mega Queso (120g)","cantidad":1,"precio":1.80},{"id":2,"nombre":"Red Bull Energy Drink (250ml)","cantidad":1,"precio":2.75}]', DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- -----------------------------------------------------------------------------
-- 6. Pedidos de prueba desde las maquinas
-- -----------------------------------------------------------------------------
INSERT INTO `pedidos_snacks` (`id`, `equipo_id`, `equipo_nombre`, `cliente_nombre`, `items`, `total`, `estado`, `fecha`) VALUES
(1, 1, 'PC 01 - Ryzen 7 RTX 4070', 'Kelvin Nieto',       '[{"id":1,"nombre":"Monster Energy Ultra White (473ml)","precio":2.50,"cantidad":1},{"id":7,"nombre":"Doritos Mega Queso (120g)","precio":1.80,"cantidad":1}]', 4.30, 'completado', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 7, 'PlayStation 5 - VIP 01',   'Cliente Gamer Demo', '[{"id":14,"nombre":"Sandwich Tostado Jamón y Queso Gamer","precio":3.00,"cantidad":1},{"id":3,"nombre":"Coca-Cola Sabor Original (500ml)","precio":1.75,"cantidad":1}]', 4.75, 'pendiente', DATE_SUB(NOW(), INTERVAL 20 MINUTE));

-- -----------------------------------------------------------------------------
-- 7. Solicitudes de recarga de tiempo de prueba
-- -----------------------------------------------------------------------------
INSERT INTO `solicitudes_tiempo` (`id`, `equipo_id`, `cliente_id`, `cliente_nombre`, `minutos`, `monto`, `estado`, `confirmado_admin`, `confirmado_cliente`, `fecha`) VALUES
(1, 1, 5, 'Kelvin Nieto',       60, 1.50, 'completada', 1, 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 7, 4, 'Cliente Gamer Demo', 60, 2.50, 'pendiente',  0, 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE));

-- Restauramos las verificaciones
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
