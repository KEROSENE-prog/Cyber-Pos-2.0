# Guía de Instalación, Configuración y Respaldo
## CyberPOS 2.0 — Laneros Gamer (Barquisimeto)

* **Institución:** Instituto Universitario Jesús Obrero (IUJO)
* **Proyecto:** CyberPOS 2.0 — Sistema de Punto de Venta y Control de Tiempo
* **Rama:** `feature/implantacion-respaldo-docs`
* **Dirigido a:** Administrador del cyber y soporte técnico

---

## 1. Arquitectura y Stack Tecnológico

CyberPOS 2.0 es el sistema de control de tiempo y punto de venta del cyber Laneros Gamer. Permite monitorear las computadoras y consolas en tiempo real, facturar productos de la tienda y hacer conversiones automáticas en dólares y bolívares según la tasa del BCV.

```
+-----------------------------------------------------------------------+
|                    CAPA DE PRESENTACIÓN (CLIENTE)                     |
|  HTML5 Semántico + CSS3 Puro (Grid/Flexbox) + Vanilla JavaScript ES6+ |
|  Vistas: login.html | dashboard.html | ventas.html | crud.html        |
+-----------------------------------------------------------------------+
                                   | (HTTP / Fetch API / JSON)
                                   v
+-----------------------------------------------------------------------+
|                    CAPA DE SERVICIOS (BACKEND PHP)                    |
|  Front Controller: api/index.php (?r=<ruta>)                          |
|  Patrón Micro-MVC: app/core/ (Router, Controller, Model, Database)    |
|  Controladores: Auth, Equipo, Producto, Venta, Pedido, Solicitud, etc.|
|  Seguridad: Tokens de 48 chars hex en cabecera X-Auth-Token           |
|  Zona Horaria: America/Caracas (UTC -4)                               |
+-----------------------------------------------------------------------+
                                   | (PDO MySQL / utf8mb4)
                                   v
+-----------------------------------------------------------------------+
|                    CAPA DE DATOS (MYSQL / MARIADB)                    |
|  Esquema Relacional: cyberpos (7 tablas normalizadas en InnoDB)       |
|  - usuarios (Roles: administrador, operador, cliente | Hashing Bcrypt)|
|  - equipos (PCs Ryzen/RTX y Consolas PS5 | Tarifas horarias en USD)   |
|  - productos (Snacks, bebidas, comidas calientes y servicios cyber)   |
|  - sesiones_uso (Auditoría transaccional de alquileres y ventas)      |
|  - solicitudes_tiempo (Recargas interactivas cliente-caja)           |
|  - pedidos_snacks (Despacho de consumos a estación en JSON)          |
|  - configuracion (Singleton id=1 | Tasa cambiaria oficial BCV)        |
+-----------------------------------------------------------------------+
```

---

## 2. Requisitos Previos de Software y Servidor Local

### 2.1. Software Obligatorio
| Componente | Versión Mínima | Recomendada | Propósito |
|---|---|---|---|
| **Sistema Operativo** | Windows 10 (64-bit) / Ubuntu 20.04 LTS | Windows 11 Pro / Ubuntu 22.04 LTS | Servidor de caja o servidor dedicado en red local LAN |
| **Pila de Servidor Web** | XAMPP 8.0.x | **XAMPP 8.2.12** | Apache, MariaDB/MySQL y PHP pre-empaquetados |
| **PHP Engine** | PHP 8.0 | **PHP 8.2+** | Backend API con tipado estricto y funciones PDO |
| **Motor de Base de Datos** | MySQL 8.0 o MariaDB 10.4 | **MariaDB 10.4.32+** | Almacenamiento transaccional InnoDB |
| **Control de Versiones** | Git 2.30+ | **Git 2.40+** | Control de cambios y sincronización |
| **Navegador Web** | Chrome 100+ / Firefox 100+ | **Google Chrome / Edge Actualizados** | Interfaz de usuario, Web Storage y soporte CSS Grid |

### 2.2. Extensiones PHP Requeridas
Verifique que las siguientes extensiones estén activas en su archivo `php.ini` (activas por defecto en XAMPP):
* `pdo_mysql` (Conector de persistencia para MySQL/MariaDB)
* `mbstring` (Soporte para cadenas UTF-8 multibyte)
* `json` (Serialización y deserialización de pedidos y payloads)
* `curl` (Consulta a servicios externos / API cambiaria)
* `openssl` (Generación de bytes aleatorios criptográficos para tokens)

Para verificar desde terminal:
```bash
php -m | grep -E "pdo_mysql|mbstring|json|curl|openssl"
# En PowerShell:
php -m | Select-String "pdo_mysql|mbstring|json|curl|openssl"
```

### 2.3. Especificaciones de Hardware y Respaldo Eléctrico (Sede Cyber)
* **Procesador:** Intel Core i3 / AMD Ryzen 3 (Quad-Core @ 3.0 GHz) o superior.
* **Memoria RAM:** Mínimo 8 GB (16 GB recomendado si el servidor aloja simultáneamente la estación de caja).
* **Almacenamiento:** Unidad de Estado Sólido (SSD NVMe o SATA) con al menos 15 GB disponibles.
* **Alimentación Ininterrumpida (UPS):** **OBLIGATORIO.** Un UPS interactivo de mínimo **800 VA / 480 W** conectado a la PC servidor y al switch/router principal de la sala. Esto garantiza entre 15 y 25 minutos de autonomía ante apagones o fluctuaciones de voltaje (típicas en la región centro-occidental de Venezuela), permitiendo que el personal realice un cierre de caja ordenado y un respaldo de emergencia.

---

## 3. Guía de Instalación y Puesta en Marcha

### Paso 1: Clonación del Repositorio y Selección de Rama
Abra su terminal (PowerShell, CMD o Bash) y clone el repositorio en el directorio `htdocs` de su instalación de XAMPP:

```bash
# Navegar al directorio htdocs
cd C:\xampp\htdocs

# Clonar el proyecto (si no está clonado)
git clone https://github.com/KEROSENE-prog/Cyber-Pos-2.0.git CYBER\Cyber-Pos-2.0

# Ingresar a la carpeta de trabajo
cd C:\xampp\htdocs\CYBER\Cyber-Pos-2.0

# Cambiar a la rama de trabajo de implantación y respaldo
git checkout feature/implantacion-respaldo-docs
```

> **IMPORTANTE:** La ruta absoluta de instalación en Windows no debe contener espacios en blanco al final de ningún nombre de directorio para evitar inconsistencias en el parser de PHP.

---

### Paso 2: Creación y Configuración del Archivo de Entorno (`.env`)
Copie la plantilla de configuración `.env.example` para generar su archivo `.env` local:

**En Windows (PowerShell / CMD):**
```powershell
Copy-Item .env.example .env
# o en CMD:
copy .env.example .env
```

**En Linux / macOS:**
```bash
cp .env.example .env
```

Abra el archivo `.env` con su editor de preferencia y valide los valores correspondientes al entorno de la sala:
```ini
# Configuración del Entorno
APP_NAME="CyberPOS - Laneros Gamer"
APP_ENV=local
APP_PORT=8000
APP_TIMEZONE=America/Caracas

# Conexión a Base de Datos (Parámetros locales de XAMPP)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=cyberpos
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Seguridad y Sesión
TOKEN_TTL=604800

# Integración Tasa Cambiaria Oficial
BCV_API_URL=https://ve.dolarapi.com/v1/dolares/oficial
BCV_FALLBACK_RATE=54.20
```

---

### Paso 3: Inicialización de la Base de Datos (DDL y Semillas)
Asegúrese de que el servicio **MySQL / MariaDB** esté iniciado en el panel de control de XAMPP.

#### Opción A: Ejecutar mediante el script automatizado (Recomendada)
* **En Windows (Doble Clic o Terminal):**
  Ejecute el script en [`scripts/backup_restore.bat`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/scripts/backup_restore.bat) o por PowerShell:
  ```powershell
  .\scripts\backup_restore.ps1 -Action schema
  .\scripts\backup_restore.ps1 -Action seeds
  ```
* **En Linux / macOS / Git Bash:**
  ```bash
  chmod +x scripts/backup_restore.sh
  ./scripts/backup_restore.sh schema
  ./scripts/backup_restore.sh seeds
  ```

#### Opción B: Ejecución manual directa mediante cliente MySQL CLI
```bash
# 1. Crear base de datos e importar estructura formal (DDL)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cyberpos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root cyberpos < database/schema.sql

# 2. Poblar datos iniciales y usuarios de prueba (Seeds)
C:\xampp\mysql\bin\mysql.exe -u root cyberpos < database/seeds.sql
```

#### Opción C: Mediante phpMyAdmin
1. Abra su navegador en `http://localhost/phpmyadmin/`.
2. Haga clic en la pestaña superior **Importar**.
3. Seleccione el archivo `database/schema.sql` y presione **Continuar**.
4. Repita el paso anterior seleccionando el archivo `database/seeds.sql`.

---

### Paso 4: Puesta en Marcha del Servidor Web

#### Modalidad 1: Servidor Embebido PHP (Desarrollo y Pruebas Rápidas)
Desde la raíz del proyecto (`C:\xampp\htdocs\CYBER\Cyber-Pos-2.0`):
```bash
# Utilizando el binario PHP de XAMPP:
C:\xampp\php\php.exe -S 127.0.0.1:8000
```
Abra el navegador en: **`http://127.0.0.1:8000/login.html`**

#### Modalidad 2: Servidor Apache Integrado de XAMPP (Producción de Sala)
1. Abra el **XAMPP Control Panel**.
2. Haga clic en **Start** en el módulo **Apache** y en el módulo **MySQL**.
3. Abra su navegador en: **`http://localhost/CYBER/Cyber-Pos-2.0/login.html`**

---

### Paso 5: Credenciales de Prueba Validadas en la BD
El script de semillas (`database/seeds.sql`) provee las siguientes cuentas listas para auditoría:

| Rol de Sistema | Nombre Completo | Correo Electrónico | Contraseña Plano | Pestaña en Login | Vista Inicial de Redirección |
|---|---|---|---|---|---|
| **Administrador** | Administrador Laneros | `admin@laneros.com` | `admin123` | Administrador | `dashboard.html` (KPIs, mapa sala, reportes) |
| **Cajero / Turno** | Cajero de Turno POS | `cajero@laneros.com` | `cajero123` | Administrador | `dashboard.html` / `ventas.html` (POS) |
| **Operador** | Carlos Mendoza | `operador@laneros.com` | `operador123` | Administrador | Preparado para módulo Operador |
| **Cliente Demo** | Cliente Gamer Demo | `cliente@laneros.com` | `cliente123` | Cliente | `dashboard_cliente.html` (Alquiler y snacks) |
| **Cliente Regular** | Kelvin Nieto | `kelvin@laneros.com` | `admin123` | Cliente | `dashboard_cliente.html` (Alquiler y snacks) |

---

## 4. Plan de Respaldo, Contingencia y Recuperación (Disaster Recovery Plan)

### 4.1. Política y Frecuencia de Respaldos
Para proteger los ingresos registrados, el control de inventario de snacks y el balance de tiempo de los jugadores frente a fallas técnicas o eléctricas:

1. **Respaldo Diario (Cierre de Caja - Turno Noche):**
   * **Responsable:** Operador o Cajero encargado del cierre.
   * **Horario:** Justo al finalizar la jornada comercial (ej. 10:00 PM).
   * **Acción:** Ejecutar la opción 1 del gestor de respaldos (`cyberpos_backup_YYYY-MM-DD_HH-MM-SS.sql`).
2. **Respaldo Semanal Fuera de Sitio (Off-site / 3-2-1):**
   * **Responsable:** Administrador general / Gerente del cyber.
   * **Horario:** Cada domingo al cierre de semana.
   * **Acción:** Copiar los archivos de la carpeta `backups/` a una unidad flash USB externa o disco portátil que no permanezca conectado a la PC, y sincronizar una copia en almacenamiento seguro en la nube (Google Drive o OneDrive).
3. **Respaldo Previo a Mantenimiento:**
   * **Responsable:** Personal de soporte TI.
   * **Acción:** Obligatorio antes de aplicar actualizaciones de código (`git pull`), migraciones de base de datos o mantenimiento del servidor físico.

---

### 4.2. Ejecución Práctica del Script de Respaldo

Los scripts residen en la carpeta `scripts/` y son compatibles tanto con Windows como con entornos Linux.

#### En Windows (Interactivo mediante Menú):
Haga doble clic sobre [`scripts/backup_restore.bat`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/scripts/backup_restore.bat) o ejecute en CMD:
```cmd
cd C:\xampp\htdocs\CYBER\Cyber-Pos-2.0\scripts
backup_restore.bat
```
Seleccione la **Opción [1]**. El script generará automáticamente un dump consistente en:
`backups/cyberpos_backup_YYYY-MM-DD_HH-MM-SS.sql`

#### En Windows (Automatizado mediante PowerShell):
```powershell
# Generar respaldo silencioso en un comando:
powershell -ExecutionPolicy Bypass -File .\scripts\backup_restore.ps1 -Action backup
```

#### En Linux / macOS / Bash:
```bash
./scripts/backup_restore.sh backup
```

> **Detalle Técnico del Respaldo:** El script utiliza el comando `mysqldump` con los flags `--single-transaction --routines --triggers --default-character-set=utf8mb4`, garantizando copias atómicas y consistentes sin bloquear las operaciones de venta en curso.

---

### 4.3. Protocolo de Contingencia ante Fallos Críticos

#### Escenario A: Caída Eléctrica Abrupta (Apagón) y Falla de Arranque de MySQL/MariaDB
**Síntoma:** El panel de XAMPP muestra `Error: MySQL shutdown unexpectedly` o la API responde con `Error 2002: No se pudo conectar a la base de datos`.

**Procedimiento de Recuperación:**
1. Verifique en el Administrador de Tareas que no existan procesos `mysqld.exe` huérfanos congelados. Si existen, finalícelos.
2. Inicie MySQL desde el XAMPP Control Panel.
3. Si el motor arranca pero las tablas presentan advertencias de inconsistencia, ejecute la herramienta de verificación y reparación en línea de comandos:
   ```cmd
   C:\xampp\mysql\bin\mysqlcheck.exe -u root --auto-repair --check --databases cyberpos
   ```
4. Si MariaDB se rehúsa a iniciar por corrupción severa de logs transaccionales (`ib_logfile0`), configure en `C:\xampp\mysql\bin\my.ini` la directiva temporal:
   ```ini
   [mysqld]
   innodb_force_recovery = 1
   ```
   Inicie el motor, ejecute de inmediato un volcado con `backup_restore.bat`, elimine los archivos de log dañados, restablezca `innodb_force_recovery = 0` y restaure el volcado.

---

#### Escenario B: Corrupción de Datos, Eliminación Accidental o Error Operativo en Caja
**Síntoma:** Se eliminó por error el inventario o una sesión de cobro, o la base de datos presenta registros inconsistentes.

**Procedimiento de Restauración Inmediata:**
1. Notifique al personal que detenga momentáneamente la facturación en pantalla.
2. Abra [`scripts/backup_restore.bat`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/scripts/backup_restore.bat) (o `backup_restore.ps1`).
3. Seleccione la **Opción [2] (Restaurar Base de Datos)**.
4. El sistema listará todos los respaldos disponibles ordenados cronológicamente:
   ```text
   cyberpos_backup_2026-11-01_15-22-45.sql
   cyberpos_backup_2026-10-31_22-00-10.sql
   ```
5. Escriba el nombre del archivo deseado y confirme con `S`.
6. La base de datos será reescrita exactamente al estado del último punto de restauración válido.

---

#### Escenario C: Restauración de Fábrica (Puesta a Punto Limpia para Nueva Temporada)
Si se desea purgar todas las pruebas y dejar el sistema en su estado óptimo de inicio:
1. Ejecute la **Opción [3]** del script (`schema.sql`) para regenerar la estructura vacía con sus índices y claves foráneas.
2. Ejecute la **Opción [4]** del script (`seeds.sql`) para reinsertar las estaciones estándar, catálogo de productos y usuarios autorizados.

---

### 4.4. Lista de Verificación Post-Recuperación (Post-Restore Checklist)

Tras cualquier procedimiento de contingencia o restauración, el especialista técnico DEBE validar estos 5 puntos antes de reabrir el sistema al público:

- [ ] **1. Servicio de Base de Datos Activo:** MariaDB se encuentra en estado `Running` en el puerto 3306.
- [ ] **2. Integridad de Tablas:** Ejecutar la consulta rápida de recuento para constatar existencia de datos:
  ```sql
  SELECT 'usuarios' AS tabla, COUNT(*) AS registros FROM usuarios UNION ALL
  SELECT 'equipos', COUNT(*) FROM equipos UNION ALL
  SELECT 'productos', COUNT(*) FROM productos UNION ALL
  SELECT 'configuracion', COUNT(*) FROM configuracion;
  ```
  *(Debe haber al menos 5 usuarios, 10 equipos, 18 productos y 1 fila de configuración).*
- [ ] **3. Autenticación de Caja:** Iniciar sesión con `admin@laneros.com` en `http://127.0.0.1:8000/login.html` y confirmar que se redirige exitosamente al Dashboard sin errores de token.
- [ ] **4. Normalización de Cronómetros:** Verificar que ninguna estación en el Mapa de Sala muestre tiempos transcurridos anómalos o negativos.
- [ ] **5. Facturación Multimoneda:** Ingresar a [`ventas.html`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/ventas.html), agregar un producto al carrito y verificar que el subtotal y total calculen tanto en dólares como en bolívares según la tasa BCV activa.

