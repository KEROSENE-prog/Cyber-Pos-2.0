# MATRIZ DE CASOS DE PRUEBA — Chaos Testing & Límites
## Rama: `feature/validaciones-chaos` · Módulos: Inventario, POS, Equipos, Solicitudes, Snacks, Configuración
**Autor:** Axel · **Proyecto:** CyberPOS / CYBER MASTER · **Fecha:** 2026-11-01

> [!IMPORTANT]
> Esta matriz cubre **TC-06 al TC-13** (8 casos). Se integra con los casos TC-01..TC-05 del equipo (rama `feature/implantacion-respaldo-docs`). Columnas exactas según la especificación del proyecto.

---

## Tabla de Casos de Prueba

| ID | Módulo / Ruta | Objetivo del Test | Datos de Entrada (Input) | Resultado Esperado | Estatus | Severidad |
|---|---|---|---|---|---|---|
| **TC-06** | Inventario / `POST /api/snacks` · `POST /api/personalizados` · `PUT /api/snacks/{id}` | Rechazar precio negativo o cero al crear o editar un producto en inventario | `{ "nombre": "Papas", "precio": -15.00, "stock": 10 }` | HTTP **400** · `{"success":false,"error":"Precio inválido: debe ser mayor a 0."}` · Frontend muestra Toast rojo con el mensaje. Sin inserción en BD. | ✅ Implementado | 🔴 **CRÍTICA** |
| **TC-07** | Inventario, Equipos, Configuración / todos los endpoints `POST`/`PUT` operativos | Rechazar campos de texto obligatorios que estén vacíos o sean sólo espacios en blanco (`"   "`) | `{ "nombre": "   ", "precio": 5.00 }` _(nombre vacío con espacios)_ | HTTP **400** · `{"success":false,"error":"El nombre del producto es obligatorio..."}` · Backend aplica `.trim()` antes de validar. Toast de error en frontend. | ✅ Implementado | 🟠 **ALTA** |
| **TC-08** | Equipos / `POST /api/equipos/{id}/tiempo` · Solicitudes / `POST /api/solicitudes` · Configuración / `PUT /api/config` | Rechazar minutos de alquiler ≤ 0 o > 1440, y tasa BCV ≤ 0 o no numérica | Minutos: `{ "minutos": 0 }` · Minutos excesivos: `{ "minutos": 2000 }` · Tasa: `{ "tasa_bcv": -5 }` | HTTP **400** en cada caso · Mensajes específicos: `"Minutos de alquiler inválidos: debe ser mayor a 0."` / `"Minutos excesivos: el tiempo máximo permitido es 1440 minutos."` / `"Tasa BCV inválida: debe ser mayor a 0."` · Toast descriptivo en frontend. | ✅ Implementado | 🟠 **ALTA** |
| **TC-09** | Inventario, Equipos, Dashboard / `POST /api/snacks` · `POST /api/equipos` · renderizado en DOM | Neutralizar payload XSS inyectado en nombre de producto o cliente | `{ "nombre": "<script>alert('XSS')</script>", "precio": 1.50, "stock": 5 }` | Backend: `strip_tags()` almacena en BD `alert('XSS')` sin etiquetas. Frontend: `escapeHtml()` convierte `<` `>` en entidades HTML. El script **jamás se ejecuta** en el navegador. Toast y tarjetas muestran texto literal inofensivo. | ✅ Implementado | 🔴 **CRÍTICA** |
| **TC-10** | Inventario / `GET /api/snacks?busqueda=...` · Equipos / `GET /api/equipos` · Ventas / `POST /api/ventas` | Inmunidad ante intento de SQL Injection en buscador de catálogo o filtros de equipos | Parámetro `busqueda`: `' OR '1'='1 --` · Campo nombre: `Robert'); DROP TABLE productos; --` | La consulta usa PDO Prepared Statement con `ATTR_EMULATE_PREPARES=false`. El payload se trata como literal de texto. No hay fuga de datos, no se eliminan tablas. Respuesta normal: array vacío o resultados legítimos. | ✅ Implementado | 🔴 **CRÍTICA** |
| **TC-11** | POS / `POST /api/ventas` · Snacks / `POST /api/pedidos` | Bloquear y hacer ROLLBACK completo al intentar vender una cantidad superior al stock disponible en BD | Producto `"Coca Cola"` con `stock=2` en BD. Payload: `{ "items": [{ "id": 5, "cantidad": 10 }] }` | HTTP **400** · `{"success":false,"error":"Stock insuficiente para 'Coca Cola': disponible 2, solicitado 10. Ningún producto fue descontado."}` · BD permanece intacta (`stock=2`). **ROLLBACK** ejecutado. Ningún otro ítem del carrito fue descontado. | ✅ Implementado | 🔴 **CRÍTICA** |
| **TC-12** | Equipos / `POST /api/equipos` · `PUT /api/equipos/{id}` | Prevenir la creación o renombrado de un equipo con un `nombre_equipo` ya registrado en BD | Equipo `"PC 01"` ya existe en BD. Payload: `{ "nombre_equipo": "PC 01", "tipo": "pc" }` | HTTP **400** · `{"success":false,"error":"Ya existe un equipo registrado con el nombre \"PC 01\". Usa un nombre diferente."}` · No se inserta duplicado. BD mantiene integridad. Toast rojo en el modal de administración. | ✅ Implementado | 🟠 **ALTA** |
| **TC-13** | Equipos / `POST /api/equipos/{id}/tiempo` · Solicitudes / `PUT /api/solicitudes/{id}/admin` | Verificar integridad atómica al asignar tiempo a un equipo: el UPDATE en `equipos` y el INSERT en `sesiones_uso` ocurren en la misma transacción o se revierten juntos | Aprobar solicitud de tiempo para equipo `"PC 03"`: `{ "accion": "iniciar", "minutos": 60, "cliente": "Juan" }` | Si la operación es exitosa: `equipos.estado="activa"`, `equipos.tiempo_limite=3600s`, `equipos.cliente="Juan"` **y** una nueva fila en `sesiones_uso` con `segundos_alquilados=3600`, `monto_cobrado=1.50` — todo en el mismo commit de TX. Si falla cualquier paso intermedio: ROLLBACK total, equipo permanece en estado anterior, sin fila huérfana en `sesiones_uso`. | ✅ Implementado | 🔴 **CRÍTICA** |

---

## Guía de Capturas de Pantalla por Caso

> [!NOTE]
> Para cada caso, toma la captura **antes** (input enviado) y **después** (respuesta del sistema). Usa las DevTools de Chrome (F12 → Network) para inspeccionar la petición y respuesta HTTP exacta.

### TC-06 — Precio Negativo en Inventario
1. Abre **Administrador → Inventario → Agregar Producto**.
2. Ingresa `Precio: -15.00` y cualquier nombre válido.
3. **Captura A:** El formulario con el valor `-15.00` visible.
4. Haz clic en "Guardar".
5. **Captura B:** El Toast rojo con el mensaje de error (`"Precio inválido: debe ser mayor a 0"`).
6. **Captura C:** DevTools → Network → la petición `POST /api/snacks` con status `400` y el JSON de respuesta.

### TC-07 — Campo de Texto con Solo Espacios
1. En el modal de agregar producto o equipo, escribe **sólo espacios** en el campo Nombre.
2. **Captura A:** El campo aparentemente relleno (con espacios), formulario listo para enviar.
3. Envía el formulario.
4. **Captura B:** Toast rojo indicando nombre obligatorio.
5. **Captura C:** DevTools → Network → request `POST` con `status 400` y body de respuesta con el error.

### TC-08 — Minutos Inválidos y Tasa BCV ≤ 0
1. **Flujo A (minutos=0):** Abre el modal de asignar tiempo a un equipo → ingresa `0` en minutos → envía → **Captura:** Toast rojo + Network 400.
2. **Flujo B (minutos=2000):** Ingresa `2000` minutos → envía → **Captura:** Toast rojo + Network 400.
3. **Flujo C (tasa=-5):** Abre Configuración → Tasa BCV → ingresa `-5` → guarda → **Captura:** Toast rojo + Network 400.

### TC-09 — Payload XSS en Nombre de Producto
1. En el formulario de Inventario → Nombre, pega exactamente: `<script>alert('XSS')</script>`
2. **Captura A:** El campo con el payload visible antes de enviar.
3. Guarda el producto.
4. **Captura B:** La tarjeta del producto en la vista del catálogo mostrando el texto literal **sin que se abra ningún alert**.
5. **Captura C:** DevTools → Network → la respuesta `200` y el campo `nombre` en el JSON, ya sanitizado (`alert('XSS')`).

### TC-10 — SQL Injection en Buscador
1. Abre la vista de **Inventario** o el buscador de productos.
2. En el campo de búsqueda, escribe: `' OR '1'='1 --`
3. **Captura A:** El buscador con el payload visible.
4. **Captura B:** Los resultados — debe mostrar resultados normales (filtrado real) o lista vacía, **nunca** todos los registros ni error de BD.
5. **Captura C:** DevTools → Network → la petición `GET /api/snacks?busqueda=...` con la respuesta limpia.

### TC-11 — ROLLBACK por Stock Insuficiente (POS)
1. Asegúrate de que un producto tiene `stock = 2` en la BD (verificar en DevTools o phpMyAdmin).
2. En la vista de Ventas (POS), agrega ese producto con **cantidad 10** al carrito.
3. **Captura A:** El carrito mostrando cantidad 10.
4. Haz clic en "Cobrar / Registrar Venta".
5. **Captura B:** Toast rojo con el mensaje de stock insuficiente y que "ningún producto fue descontado".
6. **Captura C:** DevTools → Network → `POST /api/ventas` con status `400` y el JSON de error.
7. **Captura D:** Verifica en phpMyAdmin o recargando el catálogo que el `stock` sigue siendo `2`.

### TC-12 — Duplicidad de Nombre de Equipo en BD
1. En **Administrador → Equipos**, verifica que existe `"PC 01"`.
2. Intenta agregar un nuevo equipo con el mismo nombre `"PC 01"`.
3. **Captura A:** El modal con el nombre `"PC 01"` antes de guardar.
4. Haz clic en "Agregar".
5. **Captura B:** Toast rojo con el mensaje `"Ya existe un equipo registrado con el nombre "PC 01""`.
6. **Captura C:** DevTools → Network → `POST /api/equipos` con status `400`.
7. **Captura D:** La lista de equipos sin equipo duplicado nuevo.

### TC-13 — Integridad Atómica al Asignar Tiempo (sesiones_uso)
1. Selecciona un equipo inactivo (ej. `"PC 03"`).
2. Asigna **60 minutos** al cliente `"Juan"`.
3. **Captura A:** El modal de asignación con los datos.
4. Confirma la operación.
5. **Captura B:** El equipo en la grilla mostrando estado `"activa"`, cliente `"Juan"`, temporizador activo.
6. **Captura C (prueba de traza):** En phpMyAdmin → tabla `sesiones_uso` → verificar que existe la fila exacta con `equipo_id`, `segundos_alquilados=3600`, `monto_cobrado` calculado correctamente y `fecha_registro` con el timestamp de la operación.
7. **Captura D:** DevTools → Network → `POST /api/equipos/{id}/tiempo` con status `200` y el JSON con `monto` y `data.estado="activa"`.

---

## Resumen de Cobertura

| Categoría | Casos | Archivos Cubiertos |
|---|---|---|
| Valores Límite y Datos Inválidos | TC-06, TC-07, TC-08 | `Producto.php`, `Equipo.php`, `Configuracion.php`, `SolicitudController.php`, frontend `crud.js`, `ventas.js`, `cliente.js`, `configuracion.js` |
| Chaos Testing (XSS / SQLi) | TC-09, TC-10 | `Producto.php`, `Equipo.php`, `Controller.php`, `api.js`, `crud.js`, `ventas.js`, `dashboard.js` |
| Integridad Transaccional y Unicidad | TC-11, TC-12, TC-13 | `VentaController.php`, `Pedido.php`, `Equipo.php`, `Solicitud.php` |

> [!TIP]
> Para ejecutar pruebas automatizadas rápidas, usa `curl` o Postman con el token de sesión del administrador. Ejemplo: `curl -X POST http://localhost/api/snacks -H "X-Auth-Token: TU_TOKEN" -d '{"nombre":" ","precio":-1}'`
