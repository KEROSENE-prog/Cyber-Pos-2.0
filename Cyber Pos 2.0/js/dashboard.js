/**
 * CyberPOS — dashboard.js v4
 * Monitoreo en tiempo real, KPIs, gestión de equipos y panel de notificaciones admin.
 * Consume el backend PHP a través de apiFetch.
 */

let currentEquipoAccion = null; // { id, nombre, accion }

document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.requireAuth('administrador')) return;

  const usuario = Auth.getUser();
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

  setupModalesEquipos();
  setupModalAgregarEquipo();

  await refrescarTodo();
});

// Exponer funciones para el ticker global (api.js).
// OJO: no usar envoltorios tipo "() => renderizarGrid()" porque sobrescriben
// la propia función global y provocan recursión infinita.
window.renderizarGrid = renderizarGrid;
window.actualizarGridEnVivo = actualizarGridEnVivo;
window.actualizarKPIs = actualizarKPIs;
window.actualizarNotificacionesAdmin = actualizarNotificacionesAdmin;

async function refrescarTodo() {
  try {
    await cargarEquipos();
    renderizarGrid();
    actualizarKPIs();
    actualizarNotificacionesAdmin();
  } catch (err) {
    showToast(err.message || 'Error al cargar el panel.', 'error');
  }
}

// =========================================================================
// KPIs
// =========================================================================
async function actualizarKPIs() {
  try {
    const res = await apiFetch('/dashboard/kpis');
    const k = res.data;
    const tasa = getTasaBCV();
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('kpiIngresos', `$${parseFloat(k.ingresos_hoy).toFixed(2)}`);
    set('kpiIngresosBs', `Bs. ${(parseFloat(k.ingresos_hoy) * tasa).toFixed(2)}`);
    set('kpiPCs', k.pcs_activas);
    set('kpiConsolas', k.consolas_activas);
    set('kpiPedidos', (k.pedidos_pendientes || 0) + (k.solicitudes_pendientes || 0));
  } catch (err) {
    /* silencioso */
  }
}

// =========================================================================
// GRID DE EQUIPOS
// =========================================================================
function renderizarGrid(equipos) {
  const grid = document.getElementById('equiposGrid');
  if (!grid) return;
  if (!Array.isArray(equipos)) equipos = getEquiposCache();

  if (equipos.length === 0) {
    grid.innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:24px;grid-column:1/-1">No hay equipos registrados.</p>`;
    return;
  }

  grid.innerHTML = equipos.map(eq => {
    const transcurrido = parseInt(eq.tiempo_transcurrido || 0, 10);
    const limite       = parseInt(eq.tiempo_limite || 0, 10);
    const restante     = Math.max(0, limite - transcurrido);
    const activa       = eq.estado === 'activa';
    const pct          = limite > 0 ? Math.max(0, Math.min(100, Math.round((restante / limite) * 100))) : 0;
    const color        = restante <= 300 && activa ? '#ef4444' : 'var(--brand-primary)';
    const monto        = limite > 0 ? ((limite / 3600) * (eq.tarifa_por_hora || 1.5)).toFixed(2) : '0.00';
    const icono        = eq.tipo === 'consola' ? '🎮' : '🖥️';
    const danger       = activa && restante <= 300;
    const neon         = danger || eq.estado === 'finalizada' ? 'neon-danger'
                       : (activa ? 'neon-on' : '');
    const fp           = fpEquipo(eq);

    return `
      <article class="device-card ${eq.estado === 'finalizada' ? 'card-finished' : ''} ${neon}" id="equipo-card-${eq.id}" data-fp="${escapeAttr(fp)}">
        <header class="device-header">
          <h3>${icono} ${escapeHtml(eq.nombre_equipo)}</h3>
          <mark class="status-badge ${activa ? 'active' : (eq.estado === 'finalizada' ? 'finished' : 'inactive')}">
            ${activa ? 'Activa' : (eq.estado === 'finalizada' ? 'Finalizada' : 'Inactiva')}
          </mark>
        </header>
        <section class="device-body">
          <time class="device-time" id="timer-${eq.id}" style="${eq.estado === 'finalizada' ? 'color:#f87171' : ''}">${formatSeconds(restante)}</time>
          ${eq.cliente ? `<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:8px">👤 ${escapeHtml(eq.cliente)}</p>` : ''}
          ${limite > 0 ? `
            <div class="pc-bar-track${danger ? ' danger' : ''}">
              <div class="pc-bar-fill" style="width:${pct}%;background:${color}"></div>
            </div>
            <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px">Tarifa: $${eq.tarifa_por_hora}/h · Total: $${monto}</p>
          ` : ''}
          <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
            <button class="primary btn-agregar-tiempo" data-id="${eq.id}" data-nombre="${escapeAttr(eq.nombre_equipo)}" style="flex:1;min-width:120px">
              + Añadir Tiempo
            </button>
            ${activa || eq.estado === 'finalizada' ? `<button class="danger btn-detener" data-id="${eq.id}" data-nombre="${escapeAttr(eq.nombre_equipo)}" style="flex:1;min-width:100px">⏹ Detener</button>` : ''}
            <button class="secondary btn-eliminar-equipo" data-id="${eq.id}" data-nombre="${escapeAttr(eq.nombre_equipo)}" style="padding:8px;font-size:.8rem" title="Eliminar equipo">🗑</button>
          </div>
        </section>
      </article>
    `;
  }).join('');

  grid.querySelectorAll('.btn-agregar-tiempo').forEach(btn => {
    btn.addEventListener('click', () => {
      currentEquipoAccion = { id: btn.dataset.id, nombre: btn.dataset.nombre, accion: 'agregar' };
      abrirModalTiempo();
    });
  });

  grid.querySelectorAll('.btn-detener').forEach(btn => {
    btn.addEventListener('click', async () => {
      try {
        await apiFetch(`/equipos/${btn.dataset.id}/tiempo`, { method: 'POST', body: JSON.stringify({ accion: 'apagar' }) });
        showToast(`${btn.dataset.nombre} detenida.`, 'info');
        await refrescarTodo();
      } catch (err) { showToast(err.message, 'error'); }
    });
  });

  grid.querySelectorAll('.btn-eliminar-equipo').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm(`¿Eliminar "${btn.dataset.nombre}"? Esta acción no se puede deshacer.`)) return;
      try {
        await apiFetch(`/equipos/${btn.dataset.id}`, { method: 'DELETE' });
        showToast(`${btn.dataset.nombre} eliminada.`, 'success');
        await refrescarTodo();
      } catch (err) { showToast(err.message, 'error'); }
    });
  });
}

// Actualización en vivo: solo pinta los números si nada estructural cambió.
// Evita reconstruir las tarjetas cada segundo (el neón respira sin reiniciarse).
function actualizarGridEnVivo(equipos) {
  const grid = document.getElementById('equiposGrid');
  if (!grid) return;
  if (!Array.isArray(equipos) || equipos.length === 0) { renderizarGrid(equipos); return; }
  if (grid.querySelectorAll('.device-card').length !== equipos.length) { renderizarGrid(equipos); return; }

  let hayCambio = false;
  for (const eq of equipos) {
    const card = grid.querySelector('#equipo-card-' + eq.id);
    if (!card) { hayCambio = true; break; }

    const activa       = eq.estado === 'activa';
    const transcurrido = parseInt(eq.tiempo_transcurrido || 0, 10);
    const limite       = parseInt(eq.tiempo_limite || 0, 10);
    const restante     = Math.max(0, limite - transcurrido);
    const danger       = activa && restante <= 300;

    if (card.dataset.fp !== fpEquipo(eq)) { hayCambio = true; break; }

    const pct  = limite > 0 ? Math.max(0, Math.min(100, Math.round((restante / limite) * 100))) : 0;
    const time = card.querySelector('.device-time');
    if (time) time.textContent = formatSeconds(restante);
    const fill  = card.querySelector('.pc-bar-fill');
    const track = card.querySelector('.pc-bar-track');
    if (fill)  fill.style.background = danger ? '#ef4444' : 'var(--brand-primary)';
    if (fill)  fill.style.width = pct + '%';
    if (track) track.classList.toggle('danger', danger);
  }

  if (hayCambio) renderizarGrid(equipos);
}

function fpEquipo(eq) {
  return `${eq.estado}|${eq.cliente || ''}|${eq.tipo}|${eq.tarifa_por_hora}|${eq.tiempo_limite}|${eq.nombre_equipo}`;
}

// =========================================================================
// MODAL: AGREGAR / MODIFICAR TIEMPO
// =========================================================================
function abrirModalTiempo() {
  const modal = document.getElementById('modalTiempo');
  if (!modal || !currentEquipoAccion) return;
  const titleEl = modal.querySelector('#modalTiempoTitle');
  if (titleEl) titleEl.textContent = `Añadir Tiempo — ${currentEquipoAccion.nombre}`;
  const inputMin  = modal.querySelector('#inputMinutos');
  const inputCliente = modal.querySelector('#inputClienteNombre');
  if (inputMin) inputMin.value = '';
  const eq = getEquiposCache().find(e => String(e.id) === String(currentEquipoAccion.id));
  if (inputCliente) inputCliente.value = eq ? (eq.cliente || '') : '';
  modal.showModal();
}

function setupModalesEquipos() {
  const modal     = document.getElementById('modalTiempo');
  if (!modal) return;
  const form      = modal.querySelector('form');
  const btnClose  = modal.querySelector('.close-modal');
  const btnCancel = modal.querySelector('.modal-actions button[type="button"]');

  const cerrar = () => modal.close();
  if (btnClose)  btnClose.addEventListener('click', cerrar);
  if (btnCancel) btnCancel.addEventListener('click', cerrar);

  modal.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = modal.querySelector('#inputMinutos');
      if (input) input.value = btn.dataset.min;
    });
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!currentEquipoAccion) return;
      const minutos  = parseInt(modal.querySelector('#inputMinutos')?.value, 10);
      const cliente  = modal.querySelector('#inputClienteNombre')?.value.trim() || 'Cliente';
      if (!minutos || minutos <= 0) { showToast('Ingresa los minutos.', 'error'); return; }

      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Procesando...';

      try {
        const eq = getEquiposCache().find(e => String(e.id) === String(currentEquipoAccion.id));
        const accion = eq && (eq.estado === 'activa' || eq.estado === 'finalizada') ? 'agregar' : 'iniciar';
        const res = await apiFetch(`/equipos/${currentEquipoAccion.id}/tiempo`, {
          method: 'POST',
          body: JSON.stringify({ minutos, accion, cliente })
        });
        cerrar();
        showToast(res.message || 'Tiempo procesado.', 'success');
        await refrescarTodo();
      } catch (err) {
        showToast(err.message, 'error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Confirmar';
      }
    });
  }
}

// =========================================================================
// MODAL: AGREGAR NUEVO EQUIPO
// =========================================================================
function setupModalAgregarEquipo() {
  const modal     = document.getElementById('modalNuevoEquipo');
  const btnAbrir  = document.getElementById('btnNuevoEquipo');
  if (!modal || !btnAbrir) return;

  const form     = modal.querySelector('form');
  const btnClose = modal.querySelector('.close-modal');

  btnAbrir.addEventListener('click', () => modal.showModal());
  btnClose.addEventListener('click', () => modal.close());

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nombre = modal.querySelector('#nuevoEquipoNombre')?.value.trim() || '';
      const tipo   = modal.querySelector('#nuevoEquipoTipo')?.value || 'pc';
      const tarifa = parseFloat(modal.querySelector('#nuevoEquipoTarifa')?.value) || (tipo === 'consola' ? 2.00 : 1.50);
      if (!nombre) { showToast('El nombre del equipo es obligatorio.', 'error'); return; }

      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      try {
        const res = await apiFetch('/equipos', { method: 'POST', body: JSON.stringify({ nombre_equipo: nombre, tipo, tarifa_por_hora: tarifa }) });
        modal.close();
        form.reset();
        showToast(res.message || 'Equipo agregado.', 'success');
        await refrescarTodo();
      } catch (err) {
        showToast(err.message, 'error');
      } finally {
        submitBtn.disabled = false;
      }
    });
  }
}

// =========================================================================
// PANEL DE NOTIFICACIONES ADMIN
// =========================================================================
async function actualizarNotificacionesAdmin() {
  const panel = document.getElementById('panelNotificaciones');
  if (!panel) return;

  let solicitudes = [];
  let pedidos = [];
  try {
    const [rs, rp] = await Promise.all([apiFetch('/solicitudes'), apiFetch('/pedidos')]);
    solicitudes = (rs.data || []).filter(s => s.estado === 'pendiente' || s.estado === 'esperando_admin');
    pedidos     = (rp.data || []).filter(p => p.estado === 'pendiente');
  } catch (err) {
    return;
  }

  const total = solicitudes.length + pedidos.length;
  const badge = document.getElementById('notifBadge');
  if (badge) { badge.textContent = total; badge.style.display = total > 0 ? 'inline-flex' : 'none'; }

  if (total === 0) {
    panel.innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:16px;font-size:.9rem">✅ Sin notificaciones pendientes</p>`;
    return;
  }

  const equipos = getEquiposCache();
  const tasa = getTasaBCV();
  let html = '';

  solicitudes.forEach(sol => {
    const eq = equipos.find(e => String(e.id) === String(sol.equipo_id));
    const eqNombre = eq ? eq.nombre_equipo : `Equipo #${sol.equipo_id}`;
    const monto = parseFloat(sol.monto || 0);
    html += `
      <article class="notif-card" style="background:rgba(37,99,235,.1);border-left:3px solid #2563eb;padding:12px 14px;border-radius:8px;margin-bottom:8px">
        <p style="font-weight:700;font-size:.92rem;margin-bottom:4px">⏱ Solicitud de Tiempo — ${escapeHtml(eqNombre)}</p>
        <p style="font-size:.85rem;color:var(--text-muted)">Cliente: <strong>${escapeHtml(sol.cliente_nombre)}</strong> · +${sol.minutos} min · $${monto.toFixed(2)} / Bs. ${(monto * tasa).toFixed(2)}</p>
        <div style="display:flex;gap:8px;margin-top:8px">
          <button class="success" style="padding:6px 12px;font-size:.82rem" onclick="confirmarSolicitudAdmin(${sol.id})">✅ Aprobar y Sumar Tiempo</button>
          <button class="danger"  style="padding:6px 12px;font-size:.82rem" onclick="cancelarSolicitudAdmin(${sol.id})">Cancelar</button>
        </div>
      </article>`;
  });

  pedidos.forEach(ped => {
    const itemsList = (ped.items || []).map(i => `${i.nombre} x${i.cantidad}`).join(', ');
    const totalPed = parseFloat(ped.total || 0);
    html += `
      <article class="notif-card" style="background:rgba(217,119,6,.1);border-left:3px solid #d97706;padding:12px 14px;border-radius:8px;margin-bottom:8px">
        <p style="font-weight:700;font-size:.92rem;margin-bottom:4px">🍔 Pedido — ${escapeHtml(ped.equipo_nombre || 'Mostrador')}</p>
        <p style="font-size:.85rem;color:var(--text-muted)">Cliente: <strong>${escapeHtml(ped.cliente_nombre)}</strong></p>
        <p style="font-size:.85rem;color:var(--text-muted)">Productos: ${escapeHtml(itemsList)}</p>
        <p style="font-size:.85rem;color:var(--brand-primary)">Total: $${totalPed.toFixed(2)} / Bs. ${(totalPed * tasa).toFixed(2)}</p>
        <div style="display:flex;gap:8px;margin-top:8px">
          <button class="success" style="padding:6px 12px;font-size:.82rem" onclick="completarPedido(${ped.id})">✅ Completado</button>
          <button class="danger"  style="padding:6px 12px;font-size:.82rem" onclick="cancelarPedido(${ped.id})">Cancelar</button>
        </div>
      </article>`;
  });

  panel.innerHTML = html;
}

async function confirmarSolicitudAdmin(id) {
  try {
    const res = await apiFetch(`/solicitudes/${id}/admin`, { method: 'POST' });
    showToast(res.message || 'Tiempo otorgado al cliente.', 'success');
    await refrescarTodo();
  } catch (err) { showToast(err.message, 'error'); }
}

async function cancelarSolicitudAdmin(id) {
  try {
    await apiFetch(`/solicitudes/${id}/cancelar`, { method: 'POST' });
    showToast('Solicitud cancelada.', 'info');
    await refrescarTodo();
  } catch (err) { showToast(err.message, 'error'); }
}

async function completarPedido(id) {
  try {
    await apiFetch(`/pedidos/${id}/completar`, { method: 'POST' });
    showToast('Pedido completado.', 'success');
    await refrescarTodo();
  } catch (err) { showToast(err.message, 'error'); }
}

async function cancelarPedido(id) {
  try {
    await apiFetch(`/pedidos/${id}/cancelar`, { method: 'POST' });
    showToast('Pedido cancelado.', 'info');
    await refrescarTodo();
  } catch (err) { showToast(err.message, 'error'); }
}

// =========================================================================
// Helpers
// =========================================================================
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}