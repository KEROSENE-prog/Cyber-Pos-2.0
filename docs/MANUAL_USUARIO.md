# Manual de Usuario y Guía Operativa de Sala
## Sistema CyberPOS 2.0 — Laneros Gamer (Barquisimeto, Venezuela)

* **Institución:** Instituto Universitario Jesús Obrero (IUJO)
* **Proyecto:** CyberPOS 2.0 — Sistema de Punto de Venta y Control de Tiempo
* **Versión del Manual:** 2.0 (Auditoría Técnica)
* **Público Objetivo:** Administradores de Sala, Cajeros, Operadores de Turno y Clientes Jugadores.

---

## 1. Introducción y Roles de Usuario

El sistema **CyberPOS 2.0** unifica en una sola interfaz web el control de tiempo de juego en computadoras y consolas con un punto de venta (POS) multimoneda (Dólares estadounidenses y Bolívares según la tasa del Banco Central de Venezuela - BCV).

### Matriz de Roles y Permisos en Sala
* **Administrador (`administrador`):** Acceso total al Mapa de Sala, apertura y cierre de equipos, Punto de Venta (POS), alta/baja de productos en inventario, configuración de la tasa BCV, tema visual y copias de seguridad de la base de datos.
* **Cajero / Operador (`administrador` / `operador`):** Gestión de cobros en POS, recargas de tiempo de juego, despacho de órdenes de cafetería y snacks a las estaciones, y corte de tiempo de clientes.
* **Cliente Gamer (`cliente`):** Vista exclusiva de su estación asignada, cronómetro de tiempo restante en vivo, solicitud remota de recarga de minutos a caja y carrito de compras para entrega directa en su PC o consola.

---

## 2. Módulo de Autenticación y Control de Sesión

### 2.1. Inicio de Sesión (`login.html`)
El acceso al sistema está unificado en una sola pantalla adaptada para móviles y terminales táctiles de punto de venta.

> 📸 [INSERTAR CAPTURA AQUÍ: Pantalla de Login con selector de pestañas Cliente y Administrador]

#### Pasos para Iniciar Sesión:
1. Abra el navegador en la URL asignada a la sala (ej. `http://127.0.0.1:8000/login.html`).
2. **Seleccione la pestaña de su rol:**
   * **Cliente:** Para jugadores que desean consultar el estado de su estación.
   * **Administrador:** Para el personal de caja, supervisores y administradores.
3. Ingrese su **Correo Electrónico** (ej. `admin@laneros.com` o `cliente@laneros.com`).
4. Ingrese su **Contraseña**.
5. Haga clic en el botón verde **Ingresar al Sistema**.
6. **Redirección automática:**
   * Si ingresó como Administrador/Cajero, será redirigido a [`dashboard.html`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/dashboard.html).
   * Si ingresó como Cliente, será redirigido a [`dashboard_cliente.html`](file:///c:/xampp/htdocs/CYBER/Cyber-Pos-2.0/dashboard_cliente.html).

### 2.2. Registro de Nuevos Clientes (`registro.html`)
Para registrar a un nuevo usuario en la base de datos de Laneros Gamer:
1. En la parte inferior de la pantalla de acceso, haga clic en **¿No tienes cuenta? Regístrate aquí**.
2. Complete los campos: Nombre Completo, Correo Electrónico, Contraseña (mínimo 6 caracteres) y Confirmación de Contraseña.
3. Presione el botón **Crear Cuenta**. El sistema generará el registro con contraseña cifrada (Bcrypt) y lo iniciará automáticamente.

### 2.3. Cierre de Sesión Seguro
Para finalizar su turno y proteger los datos de caja:
1. En el menú lateral izquierdo (*Sidebar*), haga clic en la opción **🚪 Salir** (o botón superior en la vista cliente).
2. El sistema destruirá el token de acceso en el navegador (`localStorage`) e invalidará la sesión en la base de datos, redirigiendo de inmediato al login.

> 📸 [INSERTAR CAPTURA AQUÍ: Menú lateral destacando el botón Salir y confirmación de cierre de sesión]

---

## 3. Mapa de Sala y Monitoreo de Equipos (`dashboard.html`)

Esta pantalla es el centro de control operativo del cyber, diseñada para verse en la pantalla principal del mostrador de caja.

> 📸 [INSERTAR CAPTURA AQUÍ: Vista panorámica del Mapa de Sala con KPIs superiores y cuadrícula de PCs y Consolas]

### 3.1. Indicadores Clave de Desempeño (KPIs)
En la parte superior se visualizan 4 tarjetas métricas actualizadas en tiempo real:
* **💵 Ingresos del Día:** Monto total facturado en la jornada en dólares ($) y su conversión automática a Bolívares (Bs.) según la tasa BCV.
* **🖥️ PCs Activas:** Cantidad de computadoras de juego que se encuentran actualmente alquiladas.
* **🎮 Consolas Activas:** Número de PlayStation 5 en uso en las salas VIP o Arena.
* **🔔 Pendientes:** Contador de solicitudes de tiempo y pedidos de snacks que requieren atención inmediata en caja.

### 3.2. Panel de Notificaciones y Solicitudes en Vivo
Ubicado debajo de los KPIs, este panel lista todas las alertas en tiempo real:
* **Pedidos de Snacks:** Indica el equipo, cliente y monto total de la orden de comida solicitada desde la estación. Botón verde para despachar/completar la orden.
* **Solicitudes de Recarga de Tiempo:** Avisos cuando un jugador pide minutos adicionales desde su asiento.

> 📸 [INSERTAR CAPTURA AQUÍ: Panel de Notificaciones con una solicitud de tiempo pendiente y pedido de snacks]

### 3.3. Asignación e Inicio de Tiempo en un Equipo

> 📸 [INSERTAR CAPTURA AQUÍ: Modal Añadir Tiempo mostrando el selector de presets (30m, 1h, 1.5h, 2h) y cliente]

1. En la cuadrícula de equipos, ubique una estación en estado **Inactiva** (ej. `PC 01 - Ryzen 7 RTX 4070`).
2. Haga clic en el botón verde **Iniciar Sesión** o **+ Añadir Tiempo**.
3. Se abrirá la ventana modal `Añadir Tiempo`:
   * **Nombre del Cliente:** Ingrese el alias o nombre del jugador (ej. `Kelvin`).
   * **Selección Rápida:** Elija uno de los botones preconfigurados (**30 min**, **1 hora**, **1.5 horas**, **2 horas**).
   * **Minutos Personalizados:** O ingrese manualmente el número exacto de minutos contratados.
4. Presione el botón **Confirmar**.
5. **Efecto en el sistema:**
   * La tarjeta del equipo pasará inmediatamente a estado **Activa**, encendiendo su halo de luz **Verde Neón**.
   * Se inicia la cuenta regresiva visible tanto para el operador como para el cliente.
   * La barra de progreso de tiempo comenzará a llenarse.

### 3.4. Alertas de Tiempo por Vencer y Finalizado
* **Alerta Preventiva (≤ 5 minutos):** La tarjeta del equipo y la barra de tiempo cambian a **Rojo Neón Pulsante**, alertando al operador para consultar al cliente si desea renovar.
* **Tiempo Agotado:** El equipo pasa a estado **Finalizada**, emitiendo una notificación sonora/visual en caja para liberar el equipo o cobrar tiempo excedente.

### 3.5. Alta de Nuevas Estaciones en Sala
1. En el encabezado de la cuadrícula, haga clic en el botón **+ Nuevo Equipo**.
2. Complete el formulario modal:
   * **Nombre del Equipo:** Ej. `PC 07 - Ryzen 7` o `PlayStation 5 - VIP 03`.
   * **Tipo:** Seleccione `PC` o `Consola`.
   * **Tarifa por Hora ($):** Tarifa base de cobro (ej. `$1.50` para PC, `$2.50` para PS5).
3. Presione **Agregar Equipo**. La estación quedará disponible de inmediato en la sala.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal de creación de nuevo equipo en la sala con selector de tipo y tarifa]

---

## 4. Operación del Punto de Venta (POS) y Facturación (`ventas.html`)

El módulo de Punto de Venta está optimizado para procesar compras rápidas de snacks, bebidas, comida caliente y servicios de cyber.

> 📸 [INSERTAR CAPTURA AQUÍ: Pantalla de Ventas POS con catálogo de productos y carrito lateral de compra]

### 4.1. Proceso de Venta Mostrador Paso a Paso
1. Ingrese a la sección **💰 Ventas** desde el menú lateral.
2. **Búsqueda de Productos:** Utilice el campo de búsqueda rápida `Buscar producto...` para filtrar al instante por nombre (ej. `Monster`, `Doritos`, `Hamburguesa`).
3. **Agregar al Carrito:** Haga clic sobre la tarjeta del producto deseado. Cada clic incrementará la cantidad en el carrito lateral derecho.
4. **Modificación de Cantidades:** En el carrito puede aumentar (+), disminuir (-) o eliminar productos según el pedido del cliente.
5. **Revisión de Totales Multimoneda:**
   * **Subtotal ($):** Suma neta de los productos en USD.
   * **IVA (16%):** Impuesto al valor agregado calculado automáticamente.
   * **TOTAL ($):** Monto definitivo a cobrar en dólares.
   * **Equivalente en Bs.:** Conversión oficial en tiempo real calculada con la tasa BCV del día (ej. `Bs. 285.50`).

> 📸 [INSERTAR CAPTURA AQUÍ: Detalle del resumen de venta en el carrito mostrando desglose de IVA y total en Bs]

### 4.2. Finalización y Emisión del Ticket Fiscal
1. Una vez cargados los productos, presione el botón verde **✅ Finalizar Venta**.
2. El sistema registrará la transacción en la tabla `sesiones_uso`, descontará de inmediato el stock en la base de datos y abrirá el **Ticket de Venta**:

> 📸 [INSERTAR CAPTURA AQUÍ: Modal del Ticket de Venta con desglose de productos y botón Imprimir]

3. **Acciones del Ticket:**
   * Presione **🖨️ Imprimir** para enviar el comprobante a la impresora térmica de recibos (80mm o 58mm).
   * Presione **+ Nueva Venta** para limpiar el módulo y atender al siguiente cliente.

---

## 5. Gestión de Inventario y Tasa Cambiaria BCV

### 5.1. Control de Inventario (`crud.html`)
Módulo exclusivo para Administradores para abastecer y mantener el stock de la sala.

> 📸 [INSERTAR CAPTURA AQUÍ: Módulo de Inventario con secciones de Catálogo Estándar y Catálogo Personalizado]

#### Funcionalidades del Inventario:
* **Catálogo Estándar (Snacks y Bebidas):** Filtre por categorías (`Bebidas`, `Comida`) o busque por texto.
* **Catálogo Personalizado:** Artículos especiales de cafetería (hamburguesas, sandwiches) o servicios de cyber (impresiones, alquiler de controles).
* **Agregar Producto (`+ Nuevo`):**
  1. Haga clic en el botón **+ Nuevo**.
  2. Complete: Nombre del artículo, Precio de venta en USD, Stock disponible, Categoría y URL de la imagen.
  3. Presione **Guardar Producto**.
* **Edición y Eliminación:** Cada tarjeta dispone de botones para ajustar el precio/stock o dar de baja el producto si queda descontinuado.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal de Creación / Edición de Producto con campos de precio y stock]

---

### 5.2. Actualización de la Tasa BCV (`configuracion.html`)
La tasa del Banco Central de Venezuela es el núcleo del cálculo multimoneda de todo el sistema.

> 📸 [INSERTAR CAPTURA AQUÍ: Sección de Configuración de la Tasa BCV con display de tasa activa y botón de sincronización]

#### Modalidades de Actualización:
* **Modo Automático (Sincronización API):**
  1. Ingrese a **⚙️ Configuración**.
  2. Ubique la tarjeta **💱 Tasa BCV (Bolívares)**.
  3. Presione el botón **🔄 Actualizar desde API BCV**.
  4. El sistema consultará el endpoint oficial (`dolarapi.com`), actualizará el valor en la base de datos y refrescará inmediatamente todos los precios en Bolívares en el POS y vista de clientes.
* **Modo Manual:**
  1. Active la casilla de verificación **Ingresar tasa manualmente**.
  2. Escriba el valor deseado en el campo `Tasa Bs/USD` (ej. `55.00`).
  3. Presione el botón **💾 Guardar Tasa**.

---

### 5.3. Personalización del Tema y Perfil
* **Color de la Sala:** Seleccione cualquiera de los presets de color neón (Verde Laneros, Morado Cyberpunk, Azul Eléctrico, Naranja Gaming) o defina un color hexadecimal personalizado para adaptar la interfaz a los eventos del cyber.
* **Perfil:** Modifique el nombre comercial del establecimiento, actualice el avatar del administrador o cambie la clave de acceso de la cuenta.

> 📸 [INSERTAR CAPTURA AQUÍ: Selector de temas de color neón y formulario de perfil de administrador]

---

## 6. Portal de la Estación del Cliente (`dashboard_cliente.html`)

Esta vista se ejecuta en el navegador de cada computadora de juego o teléfono del cliente conectado a la red WiFi del cyber.

> 📸 [INSERTAR CAPTURA AQUÍ: Portal del Cliente con cronómetro neón en tiempo real y catálogo de snacks]

### 6.1. Visualización de Tiempo de Juego
* **Cronómetro Neón Central:** Muestra horas, minutos y segundos exactos restantes (`01:25:40`).
* **Barra de Progreso:** Indica el porcentaje de tiempo consumido.
* Al restar menos de 5 minutos, el cronómetro cambia automáticamente a tono **Rojo de Advertencia** y muestra un mensaje solicitando recargar para no perder su partida.

### 6.2. Solicitud de Recarga de Tiempo a Caja
Si el cliente desea seguir jugando sin levantarse de su silla:
1. Haga clic en el botón verde **⏱ Solicitar Más Tiempo**.
2. En la ventana emergente, elija una opción rápida (**15 min**, **30 min**, **45 min**, **1 hora**) o escriba los minutos deseados.
3. El sistema le calculará al instante el monto a pagar en dólares y bolívares.
4. Presione **📤 Enviar Solicitud a Caja**.
5. En pantalla aparecerá la tarjeta de estado: `Solicitud pendiente: esperando aprobación de caja...`.
6. Cuando el cajero valide el pago, el tiempo se acreditará automáticamente en su pantalla sin reiniciar su sesión.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal del Cliente seleccionando minutos de recarga con cálculo automático de costo]

### 6.3. Pedidos de Comida y Bebidas al Asiento (Delivery Interno)
1. En la parte inferior de la pantalla, explore el catálogo de **Snacks Disponibles**.
2. Haga clic en **Agregar** en los productos que desee consumir mientras juega.
3. En la tarjeta lateral **🛒 Carrito de Snacks**, revise el total a pagar.
4. Presione el botón **📦 Pedir a mi lugar**.
5. La orden llegará directamente a la pantalla de caja con la indicación del número de PC para que el operador lleve los productos a su estación.

> 📸 [INSERTAR CAPTURA AQUÍ: Carrito de Snacks del cliente con botón Pedir a mi lugar activo]

---

## 7. Preguntas Frecuentes y Solución de Problemas Comunes

| Situación | Causa Probable | Solución Operativa |
|---|---|---|
| **"El rol seleccionado no coincide con tu cuenta"** | Se intentó ingresar por la pestaña incorrecta en login. | Seleccione la pestaña **Administrador** si su cuenta es de personal, o **Cliente** si es jugador. |
| **Los precios en Bolívares no se actualizan** | La tasa manual está tildada o no hay conexión a internet para la API. | Ingrese a `Configuración -> Tasa BCV`, verifique la conexión y presione `Actualizar desde API BCV` o escriba la tasa del día manualmente. |
| **El cronómetro de una PC se detuvo** | Se agotó el tiempo contratado o la sesión fue pausada en caja. | Solicite recarga en caja o verifique en el Mapa de Sala si la estación figura en estado `Finalizada`. |
| **El carrito de ventas no permite finalizar** | No se han seleccionado artículos o el stock es cero. | Verifique que haya productos agregados al carrito y que el stock no esté agotado en el Inventario. |

