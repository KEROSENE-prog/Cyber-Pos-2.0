# Matriz de Pruebas Funcionales (Happy Path)
## Sistema CyberPOS 2.0 — Laneros Gamer (Barquisimeto)

* **Institucion:** Instituto Universitario Jesus Obrero (IUJO)
* **Elaborado por:** Luis Colmenarez (C.I. V-30.178.822)
* **Rama:** `feature/implantacion-respaldo-docs`
* **Entorno de prueba:** XAMPP 8.2 en Windows (PHP 8.2.12 + MariaDB) / Navegador Google Chrome
* **Fecha:** Noviembre 2026

---

## 1. Casos de Prueba Funcionales (Happy Path)

| ID | Módulo / Ruta | Objetivo del Test | Datos de Entrada (Input) | Resultado Esperado | Estatus | Severidad |
|:---|:---|:---|:---|:---|:---:|:---:|
| **TC-01** | `Punto de Venta (POS)`<br>`ventas.html`<br>Endpoint: `POST /ventas` | Registrar una venta de mostrador con stock disponible, calcular IVA (16%), total en USD y Bs (tasa BCV), descontar el stock en la base de datos y mostrar el ticket. | • Productos a comprar:<br>&nbsp;&nbsp;- 2 Monster Energy ($2.50 c/u)<br>&nbsp;&nbsp;- 1 Doritos Mega Queso ($1.80)<br>• Subtotal: $6.80<br>• IVA (16%): $1.09<br>• Total: $7.89<br>• Tasa BCV: 54.20 Bs/USD<br>• Cliente: `"Mostrador Público"` | La API responde 200 `{success: true, venta_id: X, total: 7.89}`.<br>• El stock de Monster baja de 24 a 22.<br>• El stock de Doritos baja de 26 a 25.<br>• Se guarda la venta en `sesiones_uso` con `tipo_registro='venta_snack'`.<br>• Se abre el ticket en pantalla con el total de $7.89 y su equivalente en Bs. (Bs. 427.64). | **PASÓ** | **Crítica** |
| **TC-02** | `Monitoreo de Sala`<br>`dashboard.html`<br>Endpoint: `POST /equipos/1/tiempo` | Asignar e iniciar tiempo de juego en una maquina libre (`PC 01`), activando el cronometro y el brillo verde neon. | • Maquina: ID 1 (`PC 01 - Ryzen 7 RTX 4070`)<br>• Estado inicial: `inactiva`<br>• Cliente: `"Carlos Mendoza"`<br>• Tiempo: 60 minutos (1 hora)<br>• Tarifa: $1.50/hr | La API responde 200 `{success: true}`.<br>• La tarjeta de la PC 01 se pone en verde con brillo neon (`neon-on`).<br>• El reloj arranca en `01:00:00` en cuenta regresiva.<br>• En la base de datos la PC 01 pasa a `estado='activa'`, `tiempo_limite=3600`, `cliente='Carlos Mendoza'`.<br>• El contador de PCs activas sube a 1. | **PASÓ** | **Crítica** |
| **TC-03** | `Recarga Cliente a Caja`<br>`dashboard_cliente.html` → `dashboard.html`<br>Endpoints:<br>`POST /solicitudes`<br>`POST /solicitudes/{id}/confirmar` | Pedir 30 minutos extras desde la pantalla del cliente y aprobar la recarga desde la caja, sumando el tiempo a la estacion. | • Desde la PC del cliente (`PC 01`, ID 4):<br>&nbsp;&nbsp;- Pide: 30 minutos ($0.75 / Bs. 40.65)<br>• Desde la caja (Admin):<br>&nbsp;&nbsp;- Clic en boton `Aprobar`<br>&nbsp;&nbsp;- Envia: `{ quien: "admin" }` | La solicitud se guarda como `pendiente` en `solicitudes_tiempo`.<br>• Aparece la alerta en caja en el panel de notificaciones con badge rojo.<br>• Al darle aprobar, cambia a `completada` (`confirmado_admin=1`).<br>• Se le suman 1800 segundos (30 min) a la PC 01 en la base de datos.<br>• En la pantalla del cliente se suma el tiempo al instante sin reiniciar. | **PASÓ** | **Alta** |
| **TC-04** | `Pedidos de Comida`<br>`dashboard_cliente.html` → `dashboard.html`<br>Endpoints:<br>`POST /pedidos`<br>`PUT /pedidos/{id}` | Hacer un pedido de comida desde la maquina del cliente (delivery al puesto) y marcarlo como entregado en caja. | • Desde el cliente (`PC 01`):<br>&nbsp;&nbsp;- 1 Sandwich Tostado Gamer ($3.00)<br>&nbsp;&nbsp;- 1 Coca-Cola 500ml ($1.75)<br>&nbsp;&nbsp;- Total orden: $4.75<br>• Desde la caja:<br>&nbsp;&nbsp;- Clic en `Despachar`<br>&nbsp;&nbsp;- Envia: `{ estado: "completado" }` | El pedido se guarda en `pedidos_snacks` como `pendiente`.<br>• Llega la notificacion al mostrador indicando el numero de PC y los articulos.<br>• Al despacharlo, cambia a `completado` en la base de datos.<br>• Se limpia la notificacion de pendientes en caja. | **PASÓ** | **Media** |
| **TC-05** | `Tasa del Dólar BCV`<br>`configuracion.html`<br>Endpoint: `PUT /config` | Actualizar la tasa oficial del BCV desde la API (o a mano) y comprobar que todos los precios en bolivares cambien al momento. | • Tasa anterior: 36.85 Bs/USD<br>• Nueva tasa guardada: 54.20 Bs/USD<br>• Origen: Boton `Actualizar desde API BCV` o manual | La API responde 200 `{success: true}`.<br>• La fila id=1 de `configuracion` se actualiza con `tasa_bcv=54.20`.<br>• En ventas.html los productos muestran el precio en bolivares actualizado (ej. $2.50 pasa a mostrar Bs. 135.50).<br>• En el dashboard los ingresos del dia en Bs se recalculan de una vez. | **PASÓ** | **Crítica** |

---

## 2. Evidencias a Capturar (Pantalla y Base de Datos)

Para adjuntar en el informe del proyecto, estas son las capturas de pantalla y consultas SQL que debemos tomar de cada prueba:

### Evidencia 1 (Para TC-01: Venta en POS y Descuento de Stock)
* **En Pantalla:**
  1. Captura de `ventas.html` con los 2 Monster y el Doritos en el carrito, mostrando el Subtotal ($6.80), IVA ($1.09) y Total ($7.89 / Bs. 427.64).
  2. Captura del modal con el **Ticket de Venta** generado en pantalla con la fecha y el boton de imprimir.
* **En Base de Datos (consola MySQL o phpMyAdmin):**
  ```sql
  -- Vemos que la venta se guardo en sesiones_uso
  SELECT id, equipo_nombre, cliente, monto_cobrado, tipo_registro, detalle, fecha_registro
  FROM sesiones_uso
  ORDER BY id DESC LIMIT 1;

  -- Verificamos que se desconto el stock (Monster en 22 y Doritos en 25)
  SELECT id, nombre, precio, stock
  FROM productos
  WHERE id IN (1, 7);
  ```

---

### Evidencia 2 (Para TC-02: Inicio de Tiempo en PC 01)
* **En Pantalla:**
  1. Captura del modal en `dashboard.html` asignando 1 hora al cliente Carlos Mendoza.
  2. Captura de la tarjeta de la `PC 01` en verde neon con el reloj corriendo en cuenta regresiva.
* **En Base de Datos:**
  ```sql
  -- Revisamos que la PC 01 quedo activa con sus 3600 segundos (1 hora)
  SELECT id, nombre_equipo, tipo, estado, tiempo_transcurrido, tiempo_limite, tarifa_por_hora, cliente, fecha_inicio
  FROM equipos
  WHERE id = 1;
  ```

---

### Evidencia 3 (Para TC-03: Recarga de Minutos Aprobada en Caja)
* **En Pantalla:**
  1. Captura de la vista del cliente (`dashboard_cliente.html`) con el aviso azul de "Solicitud pendiente: +30 min".
  2. Captura del panel de caja (`dashboard.html`) mostrando la solicitud lista para aprobar.
* **En Base de Datos:**
  ```sql
  -- Vemos que la solicitud cambio a completada
  SELECT id, equipo_id, cliente_nombre, minutos, monto, estado, confirmado_admin, fecha
  FROM solicitudes_tiempo
  ORDER BY id DESC LIMIT 1;

  -- Comprobamos que el tiempo limite de la PC 01 subio de 3600 a 5400 segundos
  SELECT id, nombre_equipo, estado, tiempo_limite, cliente
  FROM equipos
  WHERE id = 1;
  ```

---

### Evidencia 4 (Para TC-04: Pedido de Comida Despachado)
* **En Pantalla:**
  1. Captura de la vista cliente con el sandwich y el refresco en el carrito y el boton "Pedir a mi lugar".
  2. Captura del panel de caja mostrando la comanda de la PC 01.
* **En Base de Datos:**
  ```sql
  -- Revisamos el pedido guardado con su lista de productos en JSON y estado completado
  SELECT id, equipo_nombre, cliente_nombre, items, total, estado, fecha
  FROM pedidos_snacks
  ORDER BY id DESC LIMIT 1;
  ```

---

### Evidencia 5 (Para TC-05: Tasa BCV Actualizada y Precios en Bs)
* **En Pantalla:**
  1. Captura de `configuracion.html` mostrando la tasa del dolar guardada en Bs. 54.20 con su fecha de actualizacion.
  2. Captura de `ventas.html` mostrando los productos con su precio en bolivares calculado con la nueva tasa.
* **En Base de Datos:**
  ```sql
  -- Verificamos la tasa guardada en la tabla de configuracion
  SELECT id, tasa_bcv, tasa_manual, nombre_cyber, precio_hora_pc, precio_hora_consola, ultima_actualizacion_tasa
  FROM configuracion
  WHERE id = 1;
  ```
