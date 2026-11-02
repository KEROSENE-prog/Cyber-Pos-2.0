# Manual de Usuario del Sistema
## CyberPOS 2.0 — Laneros Gamer (Barquisimeto)

* **Institución:** Instituto Universitario Jesús Obrero (IUJO)
* **Proyecto:** CyberPOS 2.0 — Sistema de Punto de Venta y Control de Tiempo
* **Elaborado por:** Equipo de Desarrollo IUJO
* **Dirigido a:** Administrador del cyber, personal de caja y clientes

---

## 1. Introducción y Roles en el Cyber

CyberPOS 2.0 permite controlar el tiempo de juego de las computadoras y consolas PS5 en tiempo real, junto con un punto de venta (POS) para cobrar snacks, bebidas y comidas tanto en dólares como en bolívares a la tasa oficial del BCV.

### Roles del sistema:
* **Administrador (`administrador`):** Tiene acceso a todo: mapa de máquinas, apertura y cierre de equipos, punto de venta, inventario, cambio de tasa BCV, tema visual y respaldos de la base de datos.
* **Cajero / Operador (`administrador` / `operador`):** Se encarga de cobrar en el POS, recargar minutos de juego a los clientes y despachar pedidos de comida a los puestos.
* **Cliente Gamer (`cliente`):** Puede ver desde su pantalla cuánto tiempo le queda de juego, pedir recargas de minutos a la caja y ordenar comida o bebidas desde el carrito.

---

## 2. Inicio y Cierre de Sesión

### 2.1. Entrar al sistema (`login.html`)
La pantalla de login funciona tanto en computadoras como en teléfonos móviles.

> 📸 [INSERTAR CAPTURA AQUÍ: Pantalla de Login con selector de pestañas Cliente y Administrador]

#### Pasos para entrar:
1. Abrir en el navegador: `http://127.0.0.1:8000/login.html` (o la IP del servidor local).
2. **Elegir la pestaña según tu rol:**
   * **Cliente:** Para los jugadores que están en las máquinas.
   * **Administrador:** Para el personal de caja o supervisores.
3. Escribir el correo (ej. `admin@laneros.com` o `cliente@laneros.com`).
4. Escribir la contraseña (ej. `admin123` o `cliente123`).
5. Dar clic en el botón verde **Ingresar al Sistema**.
6. **Redirección:**
   * El personal entra al panel de **Monitoreo** (`dashboard.html`).
   * El cliente entra a su pantalla de **Mi Tiempo** (`dashboard_cliente.html`).

### 2.2. Registro de cuentas nuevas (`registro.html`)
Si un cliente nuevo llega a la sala:
1. Dar clic en **¿No tienes cuenta? Regístrate aquí** en la parte inferior del login.
2. Llenar los datos: Nombre completo, correo, contraseña (mínimo 6 caracteres) y confirmar la contraseña.
3. Dar clic en **Crear Cuenta**. El usuario queda guardado y entra de una vez.

### 2.3. Cerrar sesión
Al terminar el turno de trabajo o de juego:
1. Dar clic en la opción **🚪 Salir** en el menú de la izquierda (o el botón salir en la vista cliente).
2. El sistema borra el token guardado en el navegador y regresa a la pantalla de login.

> 📸 [INSERTAR CAPTURA AQUÍ: Menú lateral destacando el botón Salir y confirmación de cierre de sesión]

---

## 3. Monitoreo y Control de Tiempo de las Máquinas (`dashboard.html`)

Es la pantalla principal que mantiene el cajero abierta durante el día.

> 📸 [INSERTAR CAPTURA AQUÍ: Vista panorámica del Mapa de Sala con KPIs superiores y cuadrícula de PCs y Consolas]

### 3.1. Métricas del día (KPIs)
En la parte de arriba se ven 4 tarjetas informativas:
* **💵 Ingresos del Día:** El dinero recaudado hoy en dólares y su equivalente en bolívares según la tasa del BCV.
* **🖥️ PCs Activas:** Cuántas computadoras están alquiladas en ese momento.
* **🎮 Consolas Activas:** Cuántas PlayStation 5 están en uso.
* **🔔 Pendientes:** Notificaciones de pedidos o recargas que los clientes mandan desde sus puestos.

### 3.2. Panel de notificaciones en vivo
Muestra los avisos que llegan en tiempo real:
* **Pedidos de Snacks:** Indica qué puesto pidió comida, los productos y el monto a cobrar. Tiene botón verde para marcar como despachado.
* **Solicitudes de Tiempo:** Aparece cuando un cliente pide más minutos desde su PC.

> 📸 [INSERTAR CAPTURA AQUÍ: Panel de Notificaciones con una solicitud de tiempo pendiente y pedido de snacks]

### 3.3. Asignar tiempo a un equipo

> 📸 [INSERTAR CAPTURA AQUÍ: Modal Añadir Tiempo mostrando el selector de presets (30m, 1h, 1.5h, 2h) y cliente]

1. En la cuadrícula, buscar una máquina libre en gris (ej. `PC 01 - Ryzen 7 RTX 4070`).
2. Dar clic en **Iniciar Sesión** o **+ Añadir Tiempo**.
3. En la ventana que se abre:
   * **Nombre del Cliente:** Escribir el nombre o apodo (ej. `Carlos`).
   * **Botones rápidos:** Escoger entre **30 min**, **1 hora**, **1.5 horas** o **2 horas**.
   * **O personalizar:** Escribir los minutos exactos si el cliente pide otra cantidad.
4. Dar clic en **Confirmar**.
5. La máquina se enciende con un **brillo verde neón**, el reloj arranca la cuenta regresiva y la barra de tiempo empieza a llenarse.

### 3.4. Avisos cuando se va a acabar el tiempo
* **Alerta de 5 minutos:** Cuando quedan 5 minutos o menos, la tarjeta de la PC parpadea en **rojo neón** para avisar al cajero que pregunte al cliente si desea recargar.
* **Tiempo agotado:** Al llegar a cero, el equipo pasa a **Finalizada** y se genera una notificación para cobrar el tiempo o liberar la máquina.

### 3.5. Agregar una máquina nueva
1. Clic en el botón **+ Nuevo Equipo** arriba a la derecha.
2. Llenar: Nombre (ej. `PC 07` o `Consola 05`), Tipo (`PC` o `Consola`) y Tarifa por hora en dólares.
3. Clic en **Agregar Equipo** y aparecerá de inmediato en la cuadrícula.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal de creación de nuevo equipo en la sala con selector de tipo y tarifa]

---

## 4. Punto de Venta (POS) y Cobro de Snacks (`ventas.html`)

Módulo para cobrar productos de la tienda, calcular impuestos y emitir recibos.

> 📸 [INSERTAR CAPTURA AQUÍ: Pantalla de Ventas POS con catálogo de productos y carrito lateral de compra]

### 4.1. Cómo cobrar una venta
1. Ir a **💰 Ventas** en el menú de la izquierda.
2. **Buscar productos:** Escribir en el buscador (ej. `Monster`, `Doritos`, `Hamburguesa`).
3. **Agregar al carrito:** Hacer clic en la tarjeta del producto. Con cada clic se suma 1 unidad.
4. **En el carrito de la derecha:** Se pueden ajustar las cantidades con los botones de más (+) y menos (-).
5. **Revisar los totales:**
   * **Subtotal ($):** Total neto de los productos.
   * **IVA (16%):** Impuesto calculado automáticamente.
   * **TOTAL ($):** Monto final en dólares.
   * **Total en Bs.:** Conversión automática en bolívares según la tasa del BCV.

> 📸 [INSERTAR CAPTURA AQUÍ: Detalle del resumen de venta en el carrito mostrando desglose de IVA y total en Bs]

### 4.2. Imprimir el ticket de venta
1. Clic en el botón verde **✅ Finalizar Venta**.
2. El sistema descuenta el stock de los productos vendidos, guarda el registro de la venta en la base de datos y abre el ticket en pantalla.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal del Ticket de Venta con desglose de productos y botón Imprimir]

3. Clic en **🖨️ Imprimir** si se desea imprimir el recibo en la impresora térmica de caja, o clic en **+ Nueva Venta** para seguir atendiendo.

---

## 5. Control de Inventario y Tasa del Dólar BCV

### 5.1. Inventario de productos (`crud.html`)
Módulo exclusivo del Administrador para ver y ajustar el stock.

> 📸 [INSERTAR CAPTURA AQUÍ: Módulo de Inventario con secciones de Catálogo Estándar y Catálogo Personalizado]

* **Catálogo Estándar:** Snacks comerciales y bebidas (Monster, Lays, Doritos, refrescos).
* **Catálogo Personalizado:** Comidas del cyber (hamburguesas, sandwiches) y servicios (impresiones, alquiler de mandos).
* **Crear nuevo producto:** Clic en **+ Nuevo**, escribir nombre, precio en dólares, unidades en stock, categoría y URL de la foto, y guardar.
* **Editar o borrar:** Cada producto tiene sus botones para cambiar precio, stock o eliminarlo si ya no se vende.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal de Creación / Edición de Producto con campos de precio y stock]

---

### 5.2. Actualizar la tasa del dólar BCV (`configuracion.html`)
Es fundamental para que las conversiones a bolívares en el sistema se mantengan al día.

> 📸 [INSERTAR CAPTURA AQUÍ: Sección de Configuración de la Tasa BCV con display de tasa activa y botón de sincronización]

* **Actualización con un clic (desde la API):**
  1. Ir a **⚙️ Configuración**.
  2. En la tarjeta de **Tasa BCV**, hacer clic en el botón **🔄 Actualizar desde API BCV**.
  3. El sistema busca la tasa oficial del día en internet y actualiza todos los precios en bolívares de inmediato.
* **Si no hay internet (Manual):**
  1. Marcar la casilla **Ingresar tasa manualmente**.
  2. Escribir la tasa del día en el campo de texto (ej. `54.50`).
  3. Dar clic en **💾 Guardar Tasa**.

---

### 5.3. Cambiar el color del tema
En la tarjeta de **Tema y Personalización**, se puede elegir entre los colores predeterminados (verde laneros, morado, azul o naranja) o elegir un color personalizado para cambiar el estilo visual de la aplicación.

> 📸 [INSERTAR CAPTURA AQUÍ: Selector de temas de color neón y formulario de perfil de administrador]

---

## 6. Pantalla del Cliente en su Estación (`dashboard_cliente.html`)

Esta vista la tiene el jugador abierta en su computadora mientras juega.

> 📸 [INSERTAR CAPTURA AQUÍ: Portal del Cliente con cronómetro neón en tiempo real y catálogo de snacks]

### 6.1. Ver el tiempo restante
* El reloj grande en el centro muestra en verde neón cuántas horas y minutos le quedan (`01:15:30`).
* La barra de progreso va mostrando el tiempo consumido.
* Cuando quedan menos de 5 minutos, el reloj cambia a rojo para avisarle que está por vencer su sesión.

### 6.2. Pedir más tiempo sin levantarse de la silla
1. Clic en el botón **⏱ Solicitar Más Tiempo**.
2. Elegir entre **15 min**, **30 min**, **45 min** o **1 hora** (o escribir los minutos deseados).
3. La pantalla le calcula el costo en dólares y bolívares.
4. Clic en **📤 Enviar Solicitud a Caja**.
5. Aparecerá el aviso de que la solicitud fue enviada y está en espera.
6. En cuanto el cajero le da aprobar en caja, el reloj del cliente suma los minutos automáticamente.

> 📸 [INSERTAR CAPTURA AQUÍ: Modal del Cliente seleccionando minutos de recarga con cálculo automático de costo]

### 6.3. Pedir comida a la PC (Delivery interno)
1. En la parte de abajo de la pantalla, el cliente puede ver los snacks y bebidas disponibles con sus fotos y precios.
2. Clic en **Agregar** en los productos que quiera comer.
3. En el carrito de la derecha, revisa su total y le da clic a **📦 Pedir a mi lugar**.
4. La orden le llega de inmediato a la pantalla del cajero con el número de su PC para que le lleven los productos a su puesto.

> 📸 [INSERTAR CAPTURA AQUÍ: Carrito de Snacks del cliente con botón Pedir a mi lugar activo]

---

## 7. Preguntas Frecuentes

| Duda | Motivo | Qué hacer |
|---|---|---|
| **Sale error de rol al iniciar sesión** | Se intentó entrar por la pestaña equivocada. | Si eres cliente, usa la pestaña Cliente; si eres cajero/admin, usa Administrador. |
| **Los precios en bolívares no cuadran con el día** | La tasa quedó desactualizada o está en modo manual. | Entrar a Configuración, verificar internet y darle a `Actualizar desde API BCV` o ponerla a mano. |
| **El reloj de la PC se puso en rojo** | Quedan menos de 5 minutos de tiempo. | El jugador debe solicitar recarga o el operador debe preguntar si renovará. |
| **El botón de finalizar venta está apagado en el POS** | No hay productos en el carrito. | Seleccionar al menos un producto del catálogo para poder cobrar. |
