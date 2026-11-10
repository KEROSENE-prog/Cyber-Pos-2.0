// Vista cliente: estado de tiempo, solicitud de minutos y pedido de snacks.
let carrito      = [];
let miEquipoId   = null;
let ultimaSolicitudPendienteId = null;

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
    // 1. Vincular primero por clave foránea numérica
    // 2. Solo si cliente_id es nulo, comparar nombre como respaldo
    // 3. Cero fallback a equipos[0]
    const eqAsignado = (equipos || []).find(e =>
      e.cliente_id !== null && e.cliente_id !== undefined && Number(e.cliente_id) === Number(usuario.id)
    ) || (equipos || []).find(e =>
      (e.cliente_id === null || e.cliente_id === undefined) && e.cliente && e.cliente.toLowerCase() === (usuario.nombre_completo || '').toLowerCase()
    ) || null;

    if (eqAsignado) {
      miEquipoId = eqAsignado.id;
      const spanNombre = document.getElementById('equipoNombreSpan');
      if (spanNombre) spanNombre.textContent = eqAsignado.nombre_equipo;
    } else {
      miEquipoId = null;
      const spanNombre = document.getElementById('equipoNombreSpan');
      if (spanNombre) spanNombre.textContent = 'Sin estación asignada';
      const badge = document.getElementById('badgeEstado');
      if (badge) {
        badge.className = 'status-badge inactive';
        badge.textContent = 'Sin Estación';
      }
      const btnSolicitar = document.getElementById('btnSolicitarTiempo');
      if (btnSolicitar) {
        btnSolicitar.disabled = true;
        btnSolicitar.title = 'No tienes una estación asignada';
      }
      const btnPedir = document.getElementById('btnPedirLugar');
      if (btnPedir) {
        btnPedir.disabled = true;
        btnPedir.title = 'No tienes una estación asignada';
      }
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
  const badge        = document.getElementById('badgeEstado');
  const spanNombre   = document.getElementById('equipoNombreSpan');
  const timerDisplay = document.getElementById('clientTiempoRestante');
  const fill         = document.getElementById('clientProgressFill');

  if (!miEquipoId) {
    if (spanNombre) spanNombre.textContent = 'Sin estación asignada';
    if (badge) {
      badge.className = 'status-badge inactive';
      badge.textContent = 'Sin Estación';
    }
    if (timerDisplay) timerDisplay.textContent = '00:00:00';
    if (fill) fill.style.width = '0%';
    const btnSolicitar = document.getElementById('btnSolicitarTiempo');
    if (btnSolicitar) btnSolicitar.disabled = true;
    const btnPedir = document.getElementById('btnPedirLugar');
    if (btnPedir) btnPedir.disabled = true;
    return;
  }

  const eq = (equipos || []).find(e => String(e.id) === String(miEquipoId));
  if (!eq) return;

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
  btnAbrir?.addEventListener('click', () => {
    if (!miEquipoId) {
      showToast('No tienes una estación asignada para solicitar tiempo.', 'error');
      return;
    }
    modal.showModal();
  });
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
      calcMonto(parseInt(btn.dataset.min, 10));
    });
  });

  inputMin?.addEventListener('input', () => {
    const v = parseInt(inputMin.value, 10);
    if (v > 0) calcMonto(v);
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!miEquipoId) {
        showToast('No tienes una estación asignada.', 'error');
        return;
      }
      const rawMin = (inputMin?.value || '').trim();

      if (rawMin === '' || isNaN(Number(rawMin)) || !Number.isInteger(Number(rawMin))) {
        showToast('Minutos inválidos: debe ser un número entero sin decimales.', 'error');
        inputMin?.focus();
        return;
      }
      const min = Number(rawMin);
      if (min < 5) {
        showToast('El tiempo mínimo permitido es 5 minutos.', 'error');
        inputMin?.focus();
        return;
      }
      if (min > 1440) {
        showToast('Minutos excesivos: el tiempo máximo permitido es 1440 minutos (24 horas).', 'error');
        inputMin?.focus();
        return;
      }

      const eq     = getEquiposCache().find(e => String(e.id) === String(miEquipoId)) || { tarifa_por_hora: 1.5 };
      const tarifa = eq.tarifa_por_hora || 1.5;
      const monto  = parseFloat(((min / 60) * tarifa).toFixed(2));

      try {
        await apiFetch('/solicitudes', {
          method: 'POST',
          body: JSON.stringify({
            equipo_id: miEquipoId,
            cliente_id: usuario.id,
            cliente_nombre: (usuario.nombre_completo || 'Cliente').trim(),
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
    } catch (err) {
      showToast(err.message, 'error');
    }
  });
}

async function obtenerSolicitudPendiente(usuario) {
  try {
    const res = await apiFetch('/solicitudes');
    return (res.data || []).find(s =>
      (s.cliente_id === usuario.id || (s.cliente_nombre && s.cliente_nombre.toLowerCase() === (usuario.nombre_completo || '').toLowerCase())) &&
      (s.estado === 'pendiente' || s.estado === 'esperando_admin')
    ) || null;
  } catch {
    return null;
  }
}

async function verificarSolicitudesPendientes(usuario) {
  const card = document.getElementById('cardSolicitudPendiente');
  const txt  = document.getElementById('solicitudTextoInfo');
  if (!card) return;

  const sol = await obtenerSolicitudPendiente(usuario);
  if (sol) {
    const tasa  = getTasaBCV();
    const monto = parseFloat(sol.monto || 0);
    if (txt) {
      txt.textContent = `Solicitud pendiente: +${sol.minutos} min ($${monto.toFixed(2)} / Bs. ${(monto * tasa).toFixed(2)})`;
    }
    card.style.display = 'block';
    ultimaSolicitudPendienteId = sol.id;
  } else {
    card.style.display = 'none';
    if (ultimaSolicitudPendienteId) {
      ultimaSolicitudPendienteId = null;
    }
  }
}

// =========================================================================
// SNACKS DEL CLIENTE
// =========================================================================
async function cargarSnacksCliente() {
  const grid = document.getElementById('clienteSnacksGrid');
  if (!grid) return;

  let snacks = [];
  try {
    const res = await apiFetch('/productos');
    snacks = res.data || [];
  } catch (err) {
    showToast(err.message || 'Error al cargar catálogo.', 'error');
    return;
  }

  const tasa = getTasaBCV();

  if (snacks.length === 0) {
    grid.innerHTML = `<p style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:24px">No hay productos disponibles.</p>`;
    return;
  }

  grid.innerHTML = snacks.map(snack => {
    const precio   = parseFloat(snack.precio || 0).toFixed(2);
    const precioBs = (parseFloat(precio) * tasa).toFixed(2);
    const agotado  = (parseInt(snack.stock || 0, 10)) <= 0;
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
            data-origen="${escapeAttr(snack._origen || 'snacks')}"
            data-nombre="${escapeAttr(snack.nombre)}"
            data-precio="${snack.precio}"
            ${agotado ? 'disabled' : ''}>
            ${agotado ? 'Agotado' : '+ Agregar'}
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
  const precio = Number(producto.precio);
  if (isNaN(precio) || precio <= 0) return;
  const id = parseInt(producto.id, 10);
  if (isNaN(id) || id <= 0) return;

  const existente = carrito.find(i => String(i.id) === String(id) && i.origen === producto.origen);
  if (existente) {
    existente.cantidad = Math.floor(existente.cantidad) + 1;
  } else {
    carrito.push({ id, origen: producto.origen, nombre: String(producto.nombre).trim(), precio, cantidad: 1 });
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
        <button type="button" class="btn-cart-minus" data-idx="${i}" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">−</button>
        <span style="min-width:20px;text-align:center">${item.cantidad}</span>
        <button type="button" class="btn-cart-plus" data-idx="${i}" style="background:rgba(255,255,255,.1);border:none;color:var(--text-main);width:24px;height:24px;border-radius:50%;cursor:pointer">+</button>
        <strong style="min-width:50px;text-align:right;color:var(--brand-primary)">$${(item.precio * item.cantidad).toFixed(2)}</strong>
        <button type="button" class="btn-cart-remove" data-idx="${i}" style="background:none;border:none;color:#f87171;cursor:pointer;font-size:.9rem">✕</button>
      </span>
    </li>
  `).join('');

  lista.querySelectorAll('.btn-cart-minus').forEach(b => {
    b.addEventListener('click', () => cambiarCantidad(parseInt(b.dataset.idx, 10), -1));
  });
  lista.querySelectorAll('.btn-cart-plus').forEach(b => {
    b.addEventListener('click', () => cambiarCantidad(parseInt(b.dataset.idx, 10), 1));
  });
  lista.querySelectorAll('.btn-cart-remove').forEach(b => {
    b.addEventListener('click', () => quitarDelCarrito(parseInt(b.dataset.idx, 10)));
  });

  const total = carrito.reduce((acc, i) => acc + (i.precio * i.cantidad), 0);
  if (totalEl) totalEl.innerHTML = `$${total.toFixed(2)} <small style="display:block;color:#64748b;font-size:.75rem">Bs. ${(total * tasa).toFixed(2)}</small>`;
  if (btnPedir) btnPedir.disabled = !miEquipoId || carrito.length === 0;
}

function cambiarCantidad(idx, delta) {
  if (!carrito[idx]) return;
  const deltaInt = parseInt(delta, 10);
  if (isNaN(deltaInt)) return;

  const nuevaCant = Math.floor(carrito[idx].cantidad) + deltaInt;
  if (nuevaCant <= 0) {
    carrito.splice(idx, 1);
  } else {
    carrito[idx].cantidad = nuevaCant;
  }
  renderizarCarrito();
}

function quitarDelCarrito(idx) {
  if (carrito[idx]) {
    carrito.splice(idx, 1);
    renderizarCarrito();
  }
}

window.cambiarCantidad = cambiarCantidad;
window.quitarDelCarrito = quitarDelCarrito;

function setupCarrito(usuario) {
  const btnPedir = document.getElementById('btnPedirLugar');
  if (!btnPedir) return;

  btnPedir.addEventListener('click', async () => {
    if (!miEquipoId) {
      showToast('No tienes una estación asignada para enviar pedidos.', 'error');
      return;
    }
    if (carrito.length === 0) {
      showToast('El carrito está vacío.', 'error');
      return;
    }

    for (const item of carrito) {
      if (!Number.isInteger(item.cantidad) || item.cantidad <= 0) {
        showToast(`Cantidad inválida para "${item.nombre}". Debe ser un número entero mayor a 0.`, 'error');
        return;
      }
    }

    const eq       = getEquiposCache().find(e => String(e.id) === String(miEquipoId));
    const eqNombre = eq ? eq.nombre_equipo : 'Mostrador';
    const total    = carrito.reduce((acc, i) => acc + (i.precio * i.cantidad), 0);
    const items    = carrito.map(i => ({ id: parseInt(i.id, 10), origen: i.origen, nombre: i.nombre, precio: i.precio, cantidad: Math.floor(i.cantidad) }));

    try {
      await apiFetch('/pedidos', {
        method: 'POST',
        body: JSON.stringify({
          equipo_id: miEquipoId,
          equipo_nombre: eqNombre,
          cliente_nombre: (usuario.nombre_completo || 'Cliente').trim(),
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
