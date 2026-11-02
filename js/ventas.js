/**
 * CyberPOS — ventas.js v4
 * Módulo POS de ventas: todos los productos, carrito, factura multimoneda.
 * Backend PHP: GET /productos, POST /ventas.
 */

let carritoVentas = [];
let IVA_RATE = 0.16;

document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.requireAuth('administrador')) return;

  const usuario    = Auth.getUser();
  const avatarName = document.querySelector('.topbar figcaption');
  if (avatarName && usuario) avatarName.textContent = `${usuario.nombre_completo} (${usuario.rol})`;

  document.querySelector('aside nav a[href="login.html"]')?.addEventListener('click', async (e) => {
    e.preventDefault();
    try { await apiFetch('/auth/logout', { method: 'POST' }); } catch (_) {}
    Auth.clearSession();
    showToast('Sesión finalizada', 'info', 1200);
    setTimeout(() => { window.location.href = 'login.html'; }, 700);
  });

  await cargarConfigSistema();
  await cargarProductosVenta();
  renderizarCarritoVenta();

  document.getElementById('searchVentas')?.addEventListener('input', (e) => {
    clearTimeout(window._ventasDebounce);
    window._ventasDebounce = setTimeout(() => cargarProductosVenta(e.target.value.trim()), 300);
  });
});

// =========================================================================
// CARGAR PRODUCTOS
// =========================================================================
async function cargarProductosVenta(busqueda = '') {
  const grid = document.getElementById('ventasGrid');
  if (!grid) return;

  let productos = [];
  try {
    const qs  = busqueda ? `?busqueda=${encodeURIComponent(busqueda)}` : '';
    const res = await apiFetch(`/productos${qs}`);
    productos = res.data || [];
  } catch (err) {
    showToast(err.message || 'Error al cargar productos.', 'error');
    return;
  }

  const tasa = getTasaBCV();

  if (productos.length === 0) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted);background:var(--bg-card);border-radius:12px;border:1px dashed var(--border-color)">
      <p style="font-size:1.1rem">No se encontraron productos.</p>
    </div>`;
    return;
  }

  grid.innerHTML = productos.map(prod => {
    const precio   = parseFloat(prod.precio).toFixed(2);
    const precioBs = (parseFloat(precio) * tasa).toFixed(2);
    const agotado  = prod.stock <= 0;
    const img      = prod.url_imagen || 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80';
    return `
      <article class="snack-card" style="${agotado ? 'opacity:.55' : ''}">
        <img src="${escapeAttr(img)}" alt="${escapeAttr(prod.nombre)}" class="snack-image"
          onerror="this.src='https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80'">
        <section class="snack-info">
          <h3 style="font-size:1rem">${escapeHtml(prod.nombre)}</h3>
          ${prod.categoria ? `<span style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;display:block">📁 ${escapeHtml(prod.categoria)}</span>` : ''}
          <p class="snack-meta">
            <span>
              <data class="snack-price" value="${precio}">$${precio}</data>
              <small style="display:block;color:#64748b;font-size:.75rem;margin-top:2px">Bs. ${precioBs}</small>
            </span>
            <span style="font-size:.8rem">Stock: ${prod.stock}</span>
          </p>
        </section>
        <footer class="card-actions">
          <button type="button" class="primary btn-agregar-venta"
            data-id="${prod.id}"
            data-origen="${escapeAttr(prod._origen || 'snacks')}"
            data-nombre="${escapeAttr(prod.nombre)}"
            data-precio="${precio}"
            data-stock="${prod.stock}"
            ${agotado ? 'disabled' : ''}>
            ${agotado ? 'Agotado' : '+ Agregar'}
          </button>
        </footer>
      </article>`;
  }).join('');

  grid.querySelectorAll('.btn-agregar-venta').forEach(btn => {
    btn.addEventListener('click', () => {
      agregarAVentas(btn.dataset.id, btn.dataset.origen, btn.dataset.nombre, btn.dataset.precio, btn.dataset.stock);
    });
  });
}

// =========================================================================
// CARRITO DE VENTAS
// =========================================================================
function agregarAVentas(id, origen, nombre, precio, stock) {
  const stockDisponible = parseInt(stock, 10);
  const existente = carritoVentas.find(i => String(i.id) === String(id) && i.origen === origen);
  const cantidadActual = existente ? existente.cantidad : 0;
  if (Number.isInteger(stockDisponible) && cantidadActual + 1 > stockDisponible) {
    showToast(`Stock insuficiente para "${nombre}". Disponible: ${stockDisponible}.`, 'error');
    return;
  }
  if (existente) {
    existente.cantidad += 1;
  } else {
    carritoVentas.push({
      id,
      origen,
      nombre,
      precio: parseFloat(precio),
      cantidad: 1,
      stock: Number.isInteger(stockDisponible) ? stockDisponible : null
    });
  }
  renderizarCarritoVenta();
  showToast(`"${nombre}" agregado`, 'info', 1200);
}

function cambiarCantidadVenta(idx, delta) {
  const item = carritoVentas[idx];
  if (!item) return;
  if (!Number.isInteger(delta)) return;
  const nueva = item.cantidad + delta;
  if (delta > 0 && item.stock !== null && nueva > item.stock) {
    showToast(`Stock insuficiente. Disponible: ${item.stock}.`, 'error');
    return;
  }
  item.cantidad = nueva;
  if (item.cantidad <= 0) carritoVentas.splice(idx, 1);
  renderizarCarritoVenta();
}

function quitarDeVentas(idx) {
  carritoVentas.splice(idx, 1);
  renderizarCarritoVenta();
}

function renderizarCarritoVenta() {
  const listaReal = document.getElementById('ventasCarritoLista');
  const btnFinal  = document.getElementById('btnFinalizarVenta');
  const tasa      = getTasaBCV();
  if (!listaReal) return;

  if (carritoVentas.length === 0) {
    listaReal.innerHTML = `<li style="text-align:center;color:var(--text-muted);padding:20px 0">El carrito está vacío</li>`;
    actualizarResumen(0, 0, 0, tasa);
    if (btnFinal) btnFinal.disabled = true;
    return;
  }

  listaReal.innerHTML = carritoVentas.map((item, i) => `
    <li style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color)">
      <span style="flex:1;font-size:.9rem">${escapeHtml(item.nombre)}</span>
      <span style="display:flex;align-items:center;gap:6px">
        <button onclick="cambiarCantidadVenta(${i}, -1)" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">−</button>
        <span style="min-width:20px;text-align:center">${item.cantidad}</span>
        <button onclick="cambiarCantidadVenta(${i}, 1)" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">+</button>
        <strong style="min-width:60px;text-align:right;color:var(--brand-primary)">$${(item.precio * item.cantidad).toFixed(2)}</strong>
        <button onclick="quitarDeVentas(${i})" style="background:none;border:none;color:#f87171;cursor:pointer">✕</button>
      </span>
    </li>
  `).join('');

  const subtotal = carritoVentas.reduce((acc, i) => acc + i.precio * i.cantidad, 0);
  const impuesto = subtotal * IVA_RATE;
  const total    = subtotal + impuesto;

  actualizarResumen(subtotal, impuesto, total, tasa);
  if (btnFinal) btnFinal.disabled = false;
}

function actualizarResumen(subtotal, impuesto, total, tasa) {
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.innerHTML = v; };
  set('ventaSubtotal', `$${subtotal.toFixed(2)}`);
  set('ventaImpuesto', `$${impuesto.toFixed(2)}`);
  set('ventaTotal',    `$${total.toFixed(2)}`);
  set('ventaTotalBs',  `Bs. ${(total * tasa).toFixed(2)}`);
}

// =========================================================================
// FINALIZAR VENTA
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnFinalizarVenta')?.addEventListener('click', async () => {
    if (carritoVentas.length === 0) return;
    const btn = document.getElementById('btnFinalizarVenta');
    if (btn) btn.disabled = true;

    const items = [];
    for (let i = 0; i < carritoVentas.length; i++) {
      const linea   = carritoVentas[i];
      const vId     = Validacion.entero(linea.id, { campo: `identificador del producto #${i + 1}`, min: 1 });
      const vCant   = Validacion.entero(linea.cantidad, { campo: `cantidad de "${linea.nombre}"`, min: 1, max: 10000 });
      if (!vId.ok)   { showToast(vId.error, 'error');   if (btn) btn.disabled = false; return; }
      if (!vCant.ok) { showToast(vCant.error, 'error'); if (btn) btn.disabled = false; return; }
      items.push({ id: vId.valor, cantidad: vCant.valor });
    }

    try {
      const res = await apiFetch('/ventas', { method: 'POST', body: JSON.stringify({ items }) });
      const total = parseFloat(res.total || 0);
      const tasa  = getTasaBCV();

      const ticketModal = document.getElementById('modalTicket');
      if (ticketModal) {
        document.getElementById('ticketItems').innerHTML = carritoVentas.map(i =>
          `<tr>
            <td style="padding:6px 0">${escapeHtml(i.nombre)}</td>
            <td style="text-align:center">${i.cantidad}</td>
            <td style="text-align:right">$${i.precio.toFixed(2)}</td>
            <td style="text-align:right">$${(i.precio * i.cantidad).toFixed(2)}</td>
          </tr>`
        ).join('');
        document.getElementById('ticketSubtotal').textContent = `$${parseFloat(res.subtotal).toFixed(2)}`;
        document.getElementById('ticketImpuesto').textContent = `$${parseFloat(res.impuesto).toFixed(2)}`;
        document.getElementById('ticketTotal').textContent    = `$${total.toFixed(2)}`;
        document.getElementById('ticketTotalBs').textContent  = `Bs. ${(total * tasa).toFixed(2)}`;
        document.getElementById('ticketFecha').textContent    = new Date().toLocaleString('es-VE');
        ticketModal.showModal();
      } else {
        showToast(`Venta registrada. Total: $${total.toFixed(2)} / Bs. ${(total * tasa).toFixed(2)}`, 'success', 5000);
      }

      carritoVentas = [];
      renderizarCarritoVenta();
      await cargarProductosVenta();
    } catch (err) {
      showToast(err.message || 'Error al registrar la venta.', 'error');
      if (btn) btn.disabled = false;
    }
  });

  document.getElementById('btnCerrarTicket')?.addEventListener('click', () => {
    document.getElementById('modalTicket')?.close();
  });

  document.getElementById('btnNuevaVenta')?.addEventListener('click', () => {
    document.getElementById('modalTicket')?.close();
    cargarProductosVenta();
  });

  document.getElementById('btnVaciarCarrito')?.addEventListener('click', () => {
    carritoVentas = [];
    renderizarCarritoVenta();
  });
});

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
function escapeAttr(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}