# Instituto Universitario Jesús Obrero (IUJO)
## CyberPOS 2.0 — Sistema de Monitoreo y Punto de Venta para Cyber Gaming
### Establecimiento: Laneros Gamer (Barquisimeto, Venezuela)

> **Guía de Instalación, Pruebas y Auditoría**  
> **Rama de Trabajo:** `feature/implantacion-respaldo-docs`  
> **Servidor Local:** XAMPP 8.2 (PHP 8.2 + MariaDB)

---

## 1. Arquitectura del Sistema

CyberPOS 2.0 es el sistema web para Laneros Gamer que combina el monitoreo en tiempo real de las computadoras y consolas PS5 con un punto de venta (POS) multimoneda (dólares y bolívares calculados con la tasa oficial del BCV).

```
[ Cliente Web / POS ] <---> [ API Front Controller (api/index.php) ] <---> [ Base de Datos MySQL ]
  Vanilla JS + HTML5         Micro-MVC Nativo PHP 8 (Core + Modelos)         7 Tablas (InnoDB / utf8mb4)
```

* **Backend:** Arquitectura Micro-MVC nativa en PHP 8 (`app/core/`, `app/controllers/`, `app/models/`) exponiendo endpoints RESTful con respuestas JSON uniformes (`{success: true, data: ...}`).
* **Persistencia y Base de Datos:** Motor MariaDB/MySQL con 7 tablas normalizadas en InnoDB (`usuarios`, `equipos`, `productos`, `sesiones_uso`, `solicitudes_tiempo`, `pedidos_snacks`, `configuracion`), claves foráneas `ON DELETE SET NULL / ON UPDATE CASCADE`, índices de aceleración y codificación `utf8mb4_unicode_ci`.
* **Seguridad y Sesión:** Autenticación por tokens criptográficos aleatorios de 48 caracteres hexadecimales generados en backend con TTL de 7 días (`token_ttl: 604800`) transportados en la cabecera `X-Auth-Token`. Contraseñas protegidas exclusivamente mediante `password_hash($pass, PASSWORD_BCRYPT)`.
* **Sincronización Cambiaria y Horaria:** Zona horaria sincronizada a `America/Caracas` (UTC -4) en PHP y MySQL (`SET time_zone`), y conversión multimoneda automática USD/VES conectada a la API oficial del Banco Central de Venezuela (BCV).

---

## 2. Pasos Rápidos de Arranque (Quick Start)

### Paso 1: Configurar Variables de Entorno
Copie la plantilla de configuración en la raíz del proyecto para generar su archivo `.env`:
```powershell
# En Windows (PowerShell):
Copy-Item .env.example .env
# En Windows (CMD):
copy .env.example .env
# En Linux / macOS:
cp .env.example .env
```

### Paso 2: Crear la Base de Datos e Importar Estructura (DDL)
Asegúrese de que MySQL esté corriendo en XAMPP y ejecute:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cyberpos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root cyberpos < database/schema.sql
```

### Paso 3: Cargar Datos Semilla de Prueba (Seeds)
Pueble los usuarios activos, estaciones de juego, catálogo de productos y tasa inicial:
```cmd
C:\xampp\mysql\bin\mysql.exe -u root cyberpos < database/seeds.sql
```
*(O ejecute la opción [3] y [4] desde el script interactivo `scripts/backup_restore.bat`).*

### Paso 4: Levantar el Servidor Web
Ejecute el servidor de desarrollo PHP desde la raíz del proyecto:
```cmd
C:\xampp\php\php.exe -S 127.0.0.1:8000
```

### Paso 5: Abrir en el Navegador
Acceda a la URL principal: **[http://127.0.0.1:8000/login.html](http://127.0.0.1:8000/login.html)**

---

## 3. Tabla de Credenciales Activas de Prueba (Auditoría QA)

El archivo `database/seeds.sql` inicializa las siguientes cuentas con contraseñas en Bcrypt validadas:

| Rol de Sistema | Nombre Completo | Correo Electrónico | Contraseña en Plano | Pestaña en Login | Vista de Acceso / Permisos |
|---|---|---|---|---|---|
| **Administrador** | Administrador Laneros | `admin@laneros.com` | `admin123` | Administrador | `dashboard.html` (Control total de sala, POS, configuración, auditoría) |
| **Cajero de Turno** | Cajero de Turno POS | `cajero@laneros.com` | `cajero123` | Administrador | `ventas.html` (Facturación de mostrador y despacho de pedidos) |
| **Operador** | Carlos Mendoza | `operador@laneros.com` | `operador123` | Administrador | Módulo de operación de sala y monitoreo |
| **Cliente Demo** | Cliente Gamer Demo | `cliente@laneros.com` | `cliente123` | Cliente | `dashboard_cliente.html` (Cronómetro, recargas y carrito) |
| **Cliente Regular** | Kelvin Nieto | `kelvin@laneros.com` | `admin123` | Cliente | `dashboard_cliente.html` (Estación de juego asignada) |

---

## 4. Batería de Pruebas Funcionales (Instrucciones de Ejecución)

Para auditar y certificar el correcto funcionamiento del sistema, siga esta secuencia de verificación:

### Prueba 1: Autenticación y Control de Roles
1. Ingrese a `http://127.0.0.1:8000/login.html`.
2. Seleccione la pestaña **Administrador**, ingrese `admin@laneros.com` / `admin123` y verifique la redirección exitosa al panel de Monitoreo (`dashboard.html`).
3. Cierre sesión desde el menú lateral (`Salir`).
4. Seleccione la pestaña **Cliente**, ingrese `cliente@laneros.com` / `cliente123` y verifique la redirección a la vista de cliente (`dashboard_cliente.html`).

### Prueba 2: Venta en Punto de Venta (POS) y Descuento de Stock
1. Con la sesión de Administrador/Cajero abierta, ingrese a **💰 Ventas** (`ventas.html`).
2. Agregue 1 unidad de `Monster Energy Ultra White` ($2.50) y 1 de `Doritos Mega Queso` ($1.80).
3. Verifique el cálculo automático: Subtotal ($4.30), IVA 16% ($0.69) y Total ($4.99), además de la conversión a Bolívares (ej. `Bs. 270.46`).
4. Presione **✅ Finalizar Venta** y verifique la aparición del modal con el **Ticket Fiscal** listo para imprimir.
5. Ingrese a `crud.html` y constate que el stock de ambos artículos se descontó en 1 unidad en la base de datos.

### Prueba 3: Monitoreo y Asignación de Tiempo a Equipos
1. En **🖥️ Monitoreo** (`dashboard.html`), ubique una estación inactiva (ej. `PC 01`).
2. Presione **+ Añadir Tiempo**, seleccione el preset de **30 min** para el cliente `Kelvin` y confirme.
3. Compruebe que la tarjeta enciende su **halo Verde Neón**, el contador inicia la cuenta regresiva y el KPI de PCs Activas se incrementa.

### Prueba 4: Flujo Interactivo de Recarga y Pedidos desde Estación Cliente
1. Abra una ventana de incógnito en `dashboard_cliente.html` con la cuenta de `cliente@laneros.com`.
2. Presione **⏱ Solicitar Más Tiempo**, seleccione **30 min** y envíe la solicitud a caja.
3. En la pantalla del Administrador (`dashboard.html`), verifique que la solicitud aparece inmediatamente en el **Panel de Notificaciones** con opción de aprobar.
4. En el catálogo inferior de la vista cliente, agregue un snack al carrito y presione **📦 Pedir a mi lugar**. Verifique la llegada de la comanda al mostrador de caja.

### Prueba 5: Actualización de Tasa Oficial BCV
1. Ingrese a **⚙️ Configuración** (`configuracion.html`).
2. En la tarjeta de **Tasa BCV**, presione el botón **🔄 Actualizar desde API BCV**.
3. Verifique que la tasa se actualiza con el valor oficial del Banco Central de Venezuela y que los montos en Bolívares en el POS y en la vista de clientes reflejan el nuevo valor al instante.

---

## 5. Documentación y Scripts del Repositorio

* 📘 [**Guía Técnica de Instalación y Plan de Contingencia TI**](docs/GUIA_INSTALACION_TI.md): Requisitos de hardware, soporte ante apagones con UPS, comandos de despliegue y protocolos de recuperación de desastres.
* 📗 [**Manual de Usuario Operativo**](docs/MANUAL_USUARIO.md): Manual paso a paso para el personal de caja y clientes, con indicadores visuales para capturas de pantalla `> 📸 [INSERTAR CAPTURA AQUÍ: ...]`.
* 🛠️ [**Script de Respaldo Windows (Batch)**](scripts/backup_restore.bat): Gestor interactivo para generar backups con fecha y hora, restaurar copias de seguridad o inicializar la base de datos.
* ⚙️ [**Script de Respaldo PowerShell**](scripts/backup_restore.ps1): Script CLI automatizable para tareas programadas de respaldo.
* 🐧 [**Script de Respaldo Linux (Bash)**](scripts/backup_restore.sh): Utilidad de respaldo para entornos Unix / Docker / Git Bash.
* 🗄️ [**Esquema DDL Limpio (schema.sql)**](database/schema.sql): Estructura formal de las 7 tablas con claves foráneas e integridad referencial.
* 🌱 [**Datos Semilla de Prueba (seeds.sql)**](database/seeds.sql): Cuentas de usuario con hash Bcrypt, 10 estaciones de juego y 18 productos.
* 📄 [**Plantilla de Variables de Entorno (.env.example)**](.env.example): Definición documentada de parámetros de conexión y seguridad.

---

## 6. Identidad Visual y Sistema de Diseño
El sistema utiliza una guía de estilo gaming construida sobre variables nativas CSS:
* **Paleta de Colores (Modo Oscuro / Cyber Gaming):**
  * `Obsidian Black (#0a0a0a)`: Fondo principal y paneles de control.
  * `Laneros Green (#35c029)`: Acento primario, botones de acción, resaltado de cronómetros y halos neón activos.
  * `Neon Danger (#ef4444)`: Alerta preventiva de tiempo crítico (≤ 5 min) y tiempo agotado.
  * `Card Surface (#141419)`: Superficie de tarjetas y modales nativos.
* **Tipografías:**
  * **Relojes y Títulos:** `Outfit` (700 / 800) para un acabado geométrico y legible en pantallas de sala.
  * **Tablas e Inventario:** `Inter` (400 / 600) para máxima nitidez en datos numéricos y formularios.

---

## 👥 Integrantes del Equipo
* **Luis Colmenarez** — C.I. V-30.178.822
* **Kelvin Nieto** — C.I. V-31.596.682
* **Jhonny Barrios** — C.I. V-30.621.847
* **Axel Dorante** — C.I. V-32.023.436
