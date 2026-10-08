/**
 * CyberPOS — cliente.js v4
 * Vista cliente: tiempo restante, solicitud de tiempo, pedido de snacks con carrito.
 * Backend PHP: /equipos, /productos, /solicitudes, /pedidos, /config.
 */

let carrito      = [];
let miEquipoId   = null;
let ultimaSolicitudPendienteId = null;

document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.requireAuth('cliente')) return;

  const usuario = Auth.getUser();
  const topNombre = document.getElementById('clienteNombreTop');
  if (topNombre && usuario) topNombre.textContent = usuario.nombre_completo;

  document.getElementById('linkSalirCliente')?.addEventListener('click', async (e) => {
    e.preventDefault();
    try { await apiFetch('/auth/logout', { method: 'POST' }); } catch (_) {}
    Auth.clearSession();
    showToast('Sesión finalizada', 'info', 1200);
    setTimeout(() => { window.location.href = 'login.html'; }, 500);
  });

  await cargarConfigSistema();

  try {
    const equipos = await cargarEquipos();
    const eqAsignado = equipos.find(e => e.cliente && e.cliente.toLowerCase() === (usuario.nombre_completo || '').toLowerCase()) || equipos[0];
    if (eqAsignado) {
      miEquipoId = eqAsignado.id;
      const spanNombre = document.getElementById('equipoNombreSpan');
      if (spanNombre) spanNombre.textContent = eqAsignado.nombre_equipo;
    }
  } catch (err) {
    showToast(err.message || 'Error al cargar tu estación.', 'error');
  }

  // El ticker global de api.js irá llamando a este callback.
  window.actualizarEstacionCliente = (equipos) => pintarEstadoTiempo(equipos);

  pintarEstadoTiempo(getEquiposCache());
  cargarSnacksCliente();
  setupSolicitudTiempo(usuario);
  setupCarrito(usuario);
  verificarSolicitudesPendientes(usuario);
});

// =========================================================================
// ESTADO DEL TIEMPO
// =========================================================================
function pintarEstadoTiempo(equipos) {
  if (!miEquipoId) return;
  const eq = (equipos || []).find(e => String(e.id) === String(miEquipoId));
  if (!eq) return;

  const timerDisplay = document.getElementById('clientTiempoRestante');
  const badge        = document.getElementById('badgeEstado');
  const fill         = document.getElementById('clientProgressFill');

  const transcurrido = parseInt(eq.tiempo_transcurrido || 0, 10);
  const limite       = parseInt(eq.tiempo_limite || 0, 10);
  const restante     = Math.max(0, limite - transcurrido);

  if (timerDisplay) {
    timerDisplay.textContent = formatSeconds(restante);
    timerDisplay.classList.toggle('danger-time', restante <= 300 && restante > 0 && eq.estado === 'activa');
  }

  let pct = 0;
  if (limite > 0) pct = Math.max(0, Math.min(100, Math.round((restante / limite) * 100)));
  if (fill) {
    const danger  = restante <= 300 && restante > 0;
    fill.style.width = pct + '%';
    fill.className = danger ? 'timer-progress-fill danger' : 'timer-progress-fill';
    const track = fill.parentElement;
    if (track) track.classList.toggle('danger', danger);
  }

  if (badge) {
    if (restante > 0 && eq.estado === 'activa') {
      badge.className = 'status-badge active';
      badge.textContent = 'En Juego';
    } else if (eq.estado === 'finalizada' || (limite > 0 && restante === 0)) {
      badge.className = 'status-badge finished';
      badge.textContent = 'Tiempo Agotado';
    } else {
      badge.className = 'status-badge inactive';
      badge.textContent = 'Inactiva';
    }
  }
}

// =========================================================================
// SOLICITUD DE TIEMPO
// =========================================================================
function setupSolicitudTiempo(usuario) {
  const modal     = document.getElementById('modalSolicitarTiempo');
  const btnAbrir  = document.getElementById('btnSolicitarTiempo');
  const btnClose  = document.getElementById('btnCloseSolicitud');
  const btnCancel = document.getElementById('btnCancelSolicitud');
  const form      = document.getElementById('formSolicitarTiempo');
  const inputMin  = document.getElementById('minutosSolicitud');
  const txtMonto  = document.getElementById('montoEstimadoTexto');
  if (!modal) return;

  const cerrar = () => modal.close();
  btnAbrir?.addEventListener('click', () => modal.showModal());
  btnClose?.addEventListener('click', cerrar);
  btnCancel?.addEventListener('click', cerrar);

  const calcMonto = (min) => {
    const eq     = getEquiposCache().find(e => String(e.id) === String(miEquipoId)) || { tarifa_por_hora: 1.5 };
    const tarifa = eq.tarifa_por_hora || 1.5;
    const usd    = ((min / 60) * tarifa).toFixed(2);
    const bs     = (parseFloat(usd) * getTasaBCV()).toFixed(2);
    if (txtMonto) txtMonto.innerHTML = `$${usd} <small style="color:#64748b;font-size:.8em">/ Bs. ${bs}</small>`;
    return usd;
  };

  modal.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (inputMin) inputMin.value = btn.dataset.min;
      calcMonto(parseInt(btn.dataset.min));
    });
  });

  inputMin?.addEventListener('input', () => {
    const v = parseInt(inputMin.value, 10);
    if (v > 0) calcMonto(v);
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const min = parseInt(inputMin?.value, 10);
      if (!min || min < 5) { showToast('El tiempo mínimo es 5 minutos.', 'error'); return; }

      const eq     = getEquiposCache().find(e => String(e.id) === String(miEquipoId)) || { tarifa_por_hora: 1.5 };
      const tarifa = eq.tarifa_por_hora || 1.5;
      const monto  = parseFloat(((min / 60) * tarifa).toFixed(2));

      try {
        await apiFetch('/solicitudes', {
          method: 'POST',
          body: JSON.stringify({
            equipo_id: miEquipoId,
            cliente_id: usuario.id,
            cliente_nombre: usuario.nombre_completo,
            minutos: min,
            monto: monto
          })
        });
        showToast('Solicitud enviada a caja. Espera la aprobación del administrador.', 'info', 5000);
        cerrar();
        verificarSolicitudesPendientes(usuario);
      } catch (err) {
        showToast(err.message || 'Error al enviar solicitud.', 'error');
      }
    });
  }

  document.getElementById('btnCancelarSolicitud')?.addEventListener('click', async () => {
    const sol = await obtenerSolicitudPendiente(usuario);
    if (!sol) return;
    try {
      await apiFetch(`/solicitudes/${sol.id}/cancelar`, { method: 'POST' });
      showToast('Solicitud cancelada.', 'info');
      verificarSolicitudesPendientes(usuario);
    } catch (err) { showToast(err.message, 'error'); }
  });
}

async function obtenerSolicitudPendiente(usuario) {
  try {
    const res = await apiFetch('/solicitudes');
    return (res.data || []).find(s =>
      String(s.cliente_id) === String(usuario.id) &&
      s.estado !== 'completada' && s.estado !== 'cancelada'
    ) || null;
  } catch (err) {
    return undefined;
  }
}

// =========================================================================
// VERIFICAR SOLICITUDES PENDIENTES
// =========================================================================
async function verificarSolicitudesPendientes(usuario) {
  const card = document.getElementById('cardSolicitudPendiente');
  const info = document.getElementById('solicitudTextoInfo');
  if (card) {
    const sol = await obtenerSolicitudPendiente(usuario);
    if (sol) {
      card.style.display = 'block';
      const tasa = getTasaBCV();
      if (info) info.innerHTML = `Solicitud pendiente: +${sol.minutos} min · $${sol.monto} / Bs. ${(sol.monto * tasa).toFixed(2)}`;
      ultimaSolicitudPendienteId = sol.id;
    } else {
      card.style.display = 'none';
      if (ultimaSolicitudPendienteId) {
        showToast('✅ Caja aprobó tu solicitud. El tiempo ya fue añadido a tu estación.', 'success', 5000);
        ultimaSolicitudPendienteId = null;
        pintarEstadoTiempo(getEquiposCache());
      }
    }
  }
  setTimeout(() => verificarSolicitudesPendientes(usuario), 3000);
}

// =========================================================================
// SNACKS CLIENTE (CATÁLOGO + PRECIOS EN BS)
// =========================================================================
async function cargarSnacksCliente() {
  const grid = document.getElementById('clienteSnacksGrid');
  if (!grid) return;

  let snacks = [];
  try {
    const res = await apiFetch('/productos');
    snacks = res.data || [];
  } catch (err) {
    showToast(err.message || 'Error al cargar productos.', 'error');
    return;
  }

  const tasa = getTasaBCV();

  if (snacks.length === 0) {
    grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:24px">No hay productos disponibles.</p>`;
    return;
  }

  grid.innerHTML = snacks.map(snack => {
    const precio   = parseFloat(snack.precio).toFixed(2);
    const precioBs = (parseFloat(precio) * tasa).toFixed(2);
    const img      = snack.url_imagen || 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80';
    return `
      <article class="snack-card">
        <img src="${escapeAttr(img)}" alt="${escapeAttr(snack.nombre)}" class="snack-image"
          onerror="this.src='https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80'">
        <section class="snack-info">
          <h3>${escapeHtml(snack.nombre)}</h3>
          <p class="snack-meta">
            <span>
              <data class="snack-price" value="${precio}">$${precio}</data>
              <small style="display:block;color:#64748b;font-size:.75rem;margin-top:2px">Bs. ${precioBs}</small>
            </span>
            <span>Stock: ${snack.stock}</span>
          </p>
        </section>
        <footer class="card-actions">
          <button type="button" class="primary btn-agregar-carrito"
            data-id="${snack.id}"
            data-origen="${snack._origen || 'snacks'}"
            data-nombre="${escapeAttr(snack.nombre)}"
            data-precio="${snack.precio}"
            ${snack.stock <= 0 ? 'disabled' : ''}>
            ${snack.stock <= 0 ? 'Agotado' : '+ Agregar'}
          </button>
        </footer>
      </article>`;
  }).join('');

  grid.querySelectorAll('.btn-agregar-carrito').forEach(btn => {
    btn.addEventListener('click', () => {
      agregarAlCarrito({
        id:     btn.dataset.id,
        origen: btn.dataset.origen,
        nombre: btn.dataset.nombre,
        precio: parseFloat(btn.dataset.precio)
      });
    });
  });
}

// =========================================================================
// CARRITO
// =========================================================================
function agregarAlCarrito(producto) {
  const existente = carrito.find(i => String(i.id) === String(producto.id) && i.origen === producto.origen);
  if (existente) {
    existente.cantidad += 1;
  } else {
    carrito.push({ ...producto, cantidad: 1 });
  }
  renderizarCarrito();
  showToast(`"${producto.nombre}" agregado al carrito`, 'info', 1500);
}

function renderizarCarrito() {
  const lista    = document.getElementById('cartItemsList');
  const totalEl  = document.getElementById('cartTotal');
  const btnPedir = document.getElementById('btnPedirLugar');
  const tasa     = getTasaBCV();
  if (!lista) return;

  if (carrito.length === 0) {
    lista.innerHTML = `<li style="text-align:center;color:var(--text-muted);padding:20px 0">El carrito está vacío</li>`;
    if (totalEl) totalEl.innerHTML = '$0.00';
    if (btnPedir) btnPedir.disabled = true;
    return;
  }

  lista.innerHTML = carrito.map((item, i) => `
    <li style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color)">
      <span style="flex:1;font-size:.9rem">${escapeHtml(item.nombre)}</span>
      <span style="display:flex;align-items:center;gap:6px">
        <button onclick="cambiarCantidad(${i}, -1)" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">−</button>
        <span style="min-width:20px;text-align:center">${item.cantidad}</span>
        <button onclick="cambiarCantidad(${i}, 1)" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">+</button>
        <strong style="min-width:50px;text-align:right;color:var(--brand-primary)">$${(item.precio * item.cantidad).toFixed(2)}</strong>
        <button onclick="quitarDelCarrito(${i})" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:.9rem">✕</button>
      </span>
    </li>
  `).join('');

  const total = carrito.reduce((acc, i) => acc + i.precio * i.cantidad, 0);
  if (totalEl) totalEl.innerHTML = `$${total.toFixed(2)} <small style="display:block;color:#64748b;font-size:.75rem">Bs. ${(total * tasa).toFixed(2)}</small>`;
  if (btnPedir) btnPedir.disabled = false;
}

function cambiarCantidad(idx, delta) {
  carrito[idx].cantidad += delta;
  if (carrito[idx].cantidad <= 0) carrito.splice(idx, 1);
  renderizarCarrito();
}

function quitarDelCarrito(idx) {
  carrito.splice(idx, 1);
  renderizarCarrito();
}

function setupCarrito(usuario) {
  const btnPedir = document.getElementById('btnPedirLugar');
  if (!btnPedir) return;

  btnPedir.addEventListener('click', async () => {
    if (carrito.length === 0) { showToast('El carrito está vacío.', 'error'); return; }
    const eq       = getEquiposCache().find(e => String(e.id) === String(miEquipoId));
    const eqNombre = eq ? eq.nombre_equipo : 'Mostrador';
    const total    = carrito.reduce((acc, i) => acc + i.precio * i.cantidad, 0);
    const items    = carrito.map(i => ({ id: i.id, origen: i.origen, nombre: i.nombre, precio: i.precio, cantidad: i.cantidad }));

    try {
      await apiFetch('/pedidos', {
        method: 'POST',
        body: JSON.stringify({
          equipo_id: miEquipoId,
          equipo_nombre: eqNombre,
          cliente_nombre: usuario.nombre_completo,
          items: items,
          total: total
        })
      });
      showToast('Pedido enviado a caja. Espera que te lo lleven a tu estación.', 'success', 5000);
      carrito = [];
      renderizarCarrito();
    } catch (err) {
      showToast(err.message || 'Error al enviar pedido.', 'error');
    }
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}