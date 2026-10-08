# Instituto Universitario Jesús Obrero (IUJO)
## CyberPOS - Sistema de Monitoreo y Punto de Venta para Cyber Gaming

Sistema integral de interfaz web UI/UX adaptado a la identidad visual de **Laneros Gamer**. Combina un módulo de Punto de Venta (POS) para la gestión e inventario de productos (snacks y bebidas) con un sistema centralizado para el monitoreo en tiempo real del tiempo de uso de computadoras y consolas de videojuegos.


##  Tecnologías Utilizadas
* **HTML5 Semántico:** Maquetación modular basada en estándares HTML5 con (`<main>`, `<section>`, `<article>`, `<aside>`, `<header>`, `<nav>`, `<fieldset>`, `<dialog>`).
* **CSS3 Puro:** Diseño "Mobile-First", arquitectura con variables nativas en `:root`, CSS Grid, Flexbox y animaciones de transición sin necesidad de frameworks externos.
* **Vanilla JavaScript (ES6+):** Lógica ligera para el control interactivo de modales nativos, menú hamburguesa en dispositivos móviles, pestañas de roles y notificaciones temporizadas (Toasts).
* **PHP 8 (MVC propio):** Backend en `api/` y `app/` (modelos, controladores, rutas y núcleo) que expone una API JSON.
* **MySQL / MariaDB (PDO):** Persistencia de datos. Esquema y datos iniciales en `database/cyberpos.sql`.


##  Cómo ejecutar el sistema

> Importante: la ruta del proyecto **no debe contener espacios finales** en ningún nombre de carpeta (Windows y PHP fallan al abrir esas rutas).

1. **Crear la base de datos** (MySQL/MariaDB):
   ```bash
   mysql -u root -p < database/cyberpos.sql
   ```
2. **Configurar la conexión** en `app/config.php` (host, usuario, contraseña, `dbname=cyberpos`).
3. **Levantar el servidor PHP** desde la raíz del proyecto:
   ```bash
   php -S 127.0.0.1:8000
   ```
4. Abrir en el navegador: `http://127.0.0.1:8000/login.html`

**Credenciales de prueba:**
* Administrador: `admin@laneros.com` / `admin123`
* Cliente: `cliente@laneros.com` / `cliente123`


##  Identidad Visual y Sistema de Diseño
El prototipo está construido siguiendo una guía de estilo basada en variables CSS nativas:

* **Paleta de Colores (Modo Oscuro / Gaming):**
  * `Obsidian Black (#0a0a0a)`: Fondo principal y contenedores base.
  * `Laneros Green (#35c029)`: Acento primario, botones, resaltado de horas y estados activos.
  * `Laneros Hover (#2da323)` y `Laneros Active (#227d1a)`: Respuestas visuales para interacción y clic táctil.
  * `Laneros Dark (#144a0f)`: Sombras y matices de profundidad.
  * `Success (#16a34a)` y `Danger (#991b1b)`: Confirmaciones, alertas y errores de validación.
* **Tipografías:**
  * **Títulos y Relojes:** `Outfit` (700 / 800) para un acabado geométrico, tecnológico y legible.
  * **Cuerpo y Formularios:** `Inter` (400 / 600) para máxima claridad en tablas, etiquetas e inventario.


##  Vistas del Sistema

1. **Monitoreo de Equipos (`dashboard.html`):**
   * Panel de 4 tarjetas KPI en la parte superior (Ingresos del día, PCs activas, Consolas activas y Pedidos pendientes)[cite: 13].
   * Cuadrícula responsiva de tarjetas (`<article>`) que muestra cada equipo, estado (Activo/Inactivo), contador de tiempo restante (`<time>`) y botón para sumar horas.

2. **Inventario de Snacks (`crud.html`):**
   * Catálogo tipo POS con tarjetas de productos, fotos corporativas, precio y stock disponible.
   * Barra de búsqueda rápida por nombre y filtro por categorías (`<select>`).
   * Modal unificado (`<dialog>`) nativo para "Crear / Editar" productos.
   * Modal secundario para confirmación antes de eliminar registros.

3. **Autenticación (`login.html` / `registro.html`):**
   * Pantallas centradas mediante Flexbox sobre fondo inmersivo eSports con gradiente lineal.
   * Selector de pestañas (*Tabs*) para cambiar dinámicamente entre el rol de **Cliente** y **Administrador**.


## 🚀 Mejoras y Correcciones Implementadas

### 1. Corrección de zona horaria y expiración de tiempo
* Se detectó un desfase de **6 horas** entre PHP (`Europe/Berlin`) y MySQL (`America/Caracas`) que provocaba que las sesiones de tiempo expiraran al instante.
* **Solución:** se fijó `date_default_timezone_set('America/Caracas')` en `api/index.php` y `SET time_zone` en `app/core/Database.php`, de modo que PHP y la base de datos trabajan con la hora local del sistema.

### 2. Autenticación y manejo de sesión
* Manejo centralizado del **error 401**: al expirar el token, `js/api.js` limpia la sesión (`Auth.clearSession()`) y redirige automáticamente a `login.html`.
* Corrección de una **recursión infinita** en `js/dashboard.js` (se eliminaron los *wrappers* sobre las funciones expuestas en `window`).

### 3. Monitoreo de equipos en tiempo real
* Se corrigió el **parpadeo/reconstrucción** de las tarjetas: la nueva función `actualizarGridEnVivo()` actualiza en sitio el contador, la barra y el color, y solo vuelve a renderizar cuando cambia el estado del equipo (o su cliente, tarifa, hora límite o nombre).
* Corrección de **layout**: las tarjetas de equipos sin tiempo ya **no se estiran** para igualar a la tarjeta activa (`align-items: start` en `styles.css`); cada tarjeta conserva su tamaño.

### 4. Rediseño visual con identidad neón
* Contador de tiempo del cliente (`dashboard_cliente.html`) con borde y brillo neón, y estado de alerta en rojo cuando queda poco tiempo.
* Barra de progreso del cliente con brillo neón (verde normal, rojo en tiempo crítico).
* Tarjetas de equipos en Monitoreo con **halo neón** (verde para equipos activos, rojo para equipos finalizados o con ≤ 5 minutos).
* Selector de rol de `login.html` y `registro.html` rediseñado con un "globo" neón en la pestaña activa; se eliminaron los emojis del formulario de acceso.

### 5. Notificaciones de tiempo agotado
* Aviso **"Tiempo Agotado"** con el nombre del equipo (para el cliente, con indicación de acercarse a caja).
* Los estados previos de los equipos se persisten en `localStorage` para que la alerta no se pierda al navegar entre secciones.

### 6. Configuración del sistema
* Se trasladó la gestión de **Base de Datos** desde el menú lateral hacia **Configuración**, con tarjeta de estadísticas (usuarios, equipos, snacks, sesiones) y acciones de copia de seguridad, restauración y restablecimiento de fábrica.

### 7. Historial de Ventas
* Nueva sección **"🧾 Historial de Ventas"** en Configuración con **fecha, hora, productos vendidos y total**, además de un resumen de ventas y monto recaudado.
* Para poder registrar el detalle, se agregó la columna `detalle` a la tabla `sesiones_uso`, se actualizó el modelo `SesionUso` (`registrarVenta()` con ítems y `historialVentas()`) y el controlador `VentaController` (guarda el detalle y expone `GET /ventas`).


## 👥 Integrantes del Equipo
* **Luis Colmenarez** — C.I. V-30.178.822
* **Kelvin Nieto** — C.I. V-31.596.682
* **Jhonny Barrios** — C.I. V-30.621.847
* **Axel Dorante** — C.I. V-32.023.436