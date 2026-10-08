/**
 * CyberPOS — configuracion.js v4
 * Perfil, tema de color y tasa BCV. Persistido en el backend PHP.
 */

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatDuracionSesion(segundos) {
  const total = Math.max(0, parseInt(segundos, 10) || 0);
  if (total === 0) return '0 min';
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  if (h > 0) return m > 0 ? `${h}h ${m}m` : `${h}h`;
  if (m > 0) return `${m} min`;
  return `${s} s`;
}

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
  cargarConfiguracion();
  setupFormPerfil(usuario);
  setupFormTema();
  setupFormTasa();
  setupBaseDatos();
  setupHistorialVentas();
});

// =========================================================================
// HISTORIAL DE VENTAS
// =========================================================================
function setupHistorialVentas() {
  const cargar = async () => {
    const tbody = document.getElementById('ventasBody');
    if (!tbody) return;
    try {
      const res    = await apiFetch('/ventas');
      const ventas = Array.isArray(res.data) ? res.data : [];
      const resumen = document.getElementById('ventasResumen');

      if (ventas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="padding:16px;text-align:center;color:var(--text-muted)">Aún no hay ventas registradas.</td></tr>`;
        if (resumen) resumen.innerHTML = '';
        return;
      }

      const totalGeneral = ventas.reduce((acc, v) => acc + parseFloat(v.monto_cobrado || 0), 0);
      if (resumen) resumen.innerHTML =
        `<span>Ventas registradas: <strong style="color:var(--text-color)">${ventas.length}</strong></span>` +
        `<span>Total recaudado: <strong style="color:var(--brand-primary)">$${totalGeneral.toFixed(2)}</strong></span>`;

      tbody.innerHTML = ventas.map(v => {
        const fecha = new Date(String(v.fecha_registro).replace(' ', 'T'));
        const ok    = !isNaN(fecha.getTime());
        const f     = ok ? fecha.toLocaleDateString('es-VE') : '—';
        const h     = ok ? fecha.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';

        const items = Array.isArray(v.items) ? v.items : [];
        let productos = '';

        if (v.tipo_registro === 'alquiler_tiempo') {
          const nombreEquipo = String(v.equipo_nombre || '').trim();
          const tipo  = v.equipo_tipo || (/^consola/i.test(nombreEquipo) ? 'consola' : 'pc');
          const icono = tipo === 'consola' ? '🎮' : '🖥️';
          const etiqueta = tipo === 'consola' ? 'Consola' : 'PC';
          productos =
            `${icono} Sesión de ${etiqueta}: <strong>${escapeHtml(nombreEquipo || 'Equipo')}</strong> ` +
            `<span style="color:var(--text-muted)">· ${formatDuracionSesion(v.segundos_alquilados)}</span>`;
        } else if (items.length) {
          productos = items.map(i => `${escapeHtml(i.nombre)} <span style="color:var(--text-muted)">x${i.cantidad}</span>`).join('<br>');
        } else {
          productos = `<span style="color:var(--text-muted)">🛍️ Venta directa</span>`;
        }

        const total = parseFloat(v.monto_cobrado || 0);
        return `<tr style="border-bottom:1px solid rgba(255,255,255,.06)">
          <td style="padding:10px 8px;white-space:nowrap">${f}</td>
          <td style="padding:10px 8px;white-space:nowrap;color:var(--text-muted)">${h}</td>
          <td style="padding:10px 8px">${productos}</td>
          <td style="padding:10px 8px;text-align:right;font-weight:600">$${total.toFixed(2)}</td>
        </tr>`;
      }).join('');
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="4" style="padding:16px;text-align:center;color:#f87171">${escapeHtml(err.message)}</td></tr>`;
    }
  };

  document.getElementById('btnRecargarVentas')?.addEventListener('click', cargar);
  cargar();
}

// =========================================================================
// BASE DE DATOS (copia de seguridad / restauración / reset)
// =========================================================================
function setupBaseDatos() {
  const actualizarStats = async () => {
    try {
      const res = await apiFetch('/db/stats');
      const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      s('statUsuarios', res.data.usuarios);
      s('statEquipos',  res.data.equipos);
      s('statSnacks',   res.data.productos);
      s('statSesiones', res.data.sesiones);
    } catch (err) { showToast(err.message, 'error'); }
  };

  actualizarStats();

  document.getElementById('btnExportarDB')?.addEventListener('click', async () => {
    try {
      const data = await apiFetch('/backup/export');
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href = url;
      a.download = `cyberpos_backup_${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
      showToast('Copia de seguridad descargada.', 'success');
    } catch (err) { showToast(err.message, 'error'); }
  });

  document.getElementById('fileImportarDB')?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async (evt) => {
      try {
        await apiFetch('/backup/import', { method: 'POST', body: JSON.stringify({ contenido: evt.target.result }) });
        showToast('Copia de seguridad restaurada.', 'success');
        await actualizarStats();
      } catch (err) { showToast(err.message, 'error'); }
    };
    reader.readAsText(file);
  });

  document.getElementById('btnResetearDB')?.addEventListener('click', async () => {
    if (!confirm('¿Restablecer todos los datos de fábrica? Se perderán todos los registros.')) return;
    try {
      await apiFetch('/backup/reset', { method: 'POST' });
      showToast('Base de datos restablecida.', 'info');
      await actualizarStats();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

// =========================================================================
// CARGAR CONFIG ACTUAL
// =========================================================================
function cargarConfiguracion() {
  const config  = getConfig();
  const usuario = Auth.getUser();

  if (usuario) {
    const inputNombre   = document.getElementById('configNombre');
    const previewAvatar = document.getElementById('previewAvatar');
    if (inputNombre) inputNombre.value = usuario.nombre_completo || '';
    if (previewAvatar && (usuario.avatar_url || config.avatar_url)) {
      previewAvatar.src = usuario.avatar_url || config.avatar_url;
    }
    const inputAvatarUrl = document.getElementById('configAvatarUrl');
    if (inputAvatarUrl) inputAvatarUrl.value = usuario.avatar_url || '';
  }

  const inputTasa   = document.getElementById('configTasa');
  const checkManual = document.getElementById('configTasaManual');
  const tasaActual  = document.getElementById('tasaActualDisplay');
  if (inputTasa)   inputTasa.value = config.tasa_bcv || 36.85;
  if (checkManual) checkManual.checked = config.tasa_manual || false;
  if (tasaActual) {
    const origen = config.tasa_manual ? 'Manual' : 'API BCV';
    const fecha  = config.ultima_actualizacion_tasa
      ? new Date(config.ultima_actualizacion_tasa.replace(' ', 'T')).toLocaleString('es-VE')
      : 'N/A';
    tasaActual.innerHTML = `<strong>Bs. ${config.tasa_bcv}</strong> · <small style="color:var(--text-muted)">Origen: ${origen} · Actualizado: ${fecha}</small>`;
  }

  const colorPicker = document.getElementById('configColor');
  if (colorPicker) colorPicker.value = config.theme_color || '#35c029';

  const inputNombreCyber = document.getElementById('configNombreCyber');
  if (inputNombreCyber) inputNombreCyber.value = config.nombre_cyber || 'CyberPOS Laneros Gamer';
}

// =========================================================================
// FORMULARIO DE PERFIL
// =========================================================================
function setupFormPerfil(usuario) {
  const form      = document.getElementById('formPerfil');
  const inputAvatar = document.getElementById('configAvatarUrl');
  const preview   = document.getElementById('previewAvatar');
  const fileInput = document.getElementById('configAvatarFile');

  if (inputAvatar && preview) {
    inputAvatar.addEventListener('input', () => {
      if (inputAvatar.value.trim()) preview.src = inputAvatar.value.trim();
    });
  }

  if (fileInput && preview) {
    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (evt) => {
        preview.src = evt.target.result;
        if (inputAvatar) inputAvatar.value = evt.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const nombre    = document.getElementById('configNombre')?.value.trim();
    const avatarUrl = document.getElementById('configAvatarUrl')?.value.trim();
    const password  = document.getElementById('configPassword')?.value.trim();
    const password2 = document.getElementById('configPassword2')?.value.trim();

    if (password && password !== password2) {
      showToast('Las contraseñas no coinciden.', 'error'); return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      const data = { nombre_completo: nombre, avatar_url: avatarUrl };
      if (password) data.password = password;

      const res = await apiFetch(`/usuarios/${usuario.id}`, { method: 'PUT', body: JSON.stringify(data) });
      Auth.setSession(res.user, Auth.getToken());

      if (avatarUrl) {
        document.querySelectorAll('img[alt^="Avatar"]').forEach(img => { img.src = avatarUrl; });
        const cfgRes = await apiFetch('/config', { method: 'PUT', body: JSON.stringify({ avatar_url: avatarUrl }) });
        refrescarConfigGlobal(cfgRes.data);
      }

      showToast('Perfil actualizado exitosamente.', 'success');
      cargarConfiguracion();
    } catch (err) {
      showToast(err.message || 'Error al guardar perfil.', 'error');
    } finally {
      submitBtn.disabled = false;
    }
  });
}

// =========================================================================
// FORMULARIO DE TEMA (COLOR)
// =========================================================================
function setupFormTema() {
  const form        = document.getElementById('formTema');
  const colorPicker = document.getElementById('configColor');
  const preview     = document.getElementById('colorPreview');

  const COLORES_PRESETS = [
    { nombre: 'Laneros Green', valor: '#35c029' },
    { nombre: 'Cyber Blue',    valor: '#2563eb' },
    { nombre: 'Neon Purple',   valor: '#7c3aed' },
    { nombre: 'Hot Red',       valor: '#dc2626' },
    { nombre: 'Amber',         valor: '#d97706' },
    { nombre: 'Cyan',          valor: '#0891b2' },
  ];

  const presetsContainer = document.getElementById('colorPresets');
  if (presetsContainer) {
    presetsContainer.innerHTML = COLORES_PRESETS.map(c => `
      <button type="button" class="color-preset-btn"
        onclick="seleccionarColor('${c.valor}')"
        style="width:36px;height:36px;border-radius:50%;background:${c.valor};border:3px solid transparent;cursor:pointer;transition:all .2s"
        title="${c.nombre}"></button>
    `).join('');
  }

  if (colorPicker && preview) {
    colorPicker.addEventListener('input', () => {
      preview.style.background = colorPicker.value;
      document.documentElement.style.setProperty('--brand-primary', colorPicker.value);
    });
  }

  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const color = colorPicker?.value || '#35c029';
    const nombreCyber = document.getElementById('configNombreCyber')?.value.trim();

    try {
      const res = await apiFetch('/config', { method: 'PUT', body: JSON.stringify({ theme_color: color, nombre_cyber: nombreCyber }) });
      refrescarConfigGlobal(res.data);
      document.documentElement.style.setProperty('--brand-primary', color);
      showToast('Tema aplicado y guardado.', 'success');
    } catch (err) {
      showToast(err.message || 'Error al guardar el tema.', 'error');
    }
  });
}

window.seleccionarColor = function(color) {
  const colorPicker = document.getElementById('configColor');
  const preview     = document.getElementById('colorPreview');
  if (colorPicker) colorPicker.value = color;
  if (preview)     preview.style.background = color;
  document.documentElement.style.setProperty('--brand-primary', color);
};

// =========================================================================
// FORMULARIO DE TASA BCV
// =========================================================================
function setupFormTasa() {
  const form        = document.getElementById('formTasa');
  const checkManual = document.getElementById('configTasaManual');
  const inputTasa   = document.getElementById('configTasa');

  if (checkManual && inputTasa) {
    checkManual.addEventListener('change', () => { inputTasa.disabled = !checkManual.checked; });
  }

  document.getElementById('btnActualizarTasaAPI')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnActualizarTasaAPI');
    btn.disabled = true;
    btn.textContent = 'Actualizando...';
    try {
      const res  = await fetch('https://ve.dolarapi.com/v1/dolares/oficial', { signal: AbortSignal.timeout(7000) });
      if (!res.ok) throw new Error('No se pudo consultar la API BCV.');
      const d = await res.json();
      const tasa = d.promedio || d.precio;
      if (!tasa) throw new Error('La API no devolvió una tasa válida.');
      const upd = await apiFetch('/config', { method: 'PUT', body: JSON.stringify({ tasa_bcv: tasa, tasa_manual: false }) });
      refrescarConfigGlobal(upd.data);
      showToast(`Tasa actualizada: Bs. ${parseFloat(tasa).toFixed(2)}`, 'success');
      cargarConfiguracion();
    } catch (err) {
      showToast(err.message || 'Error al actualizar la tasa.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = '🔄 Actualizar desde API BCV';
    }
  });

  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const tasa   = parseFloat(inputTasa?.value);
    const manual = !!checkManual?.checked;
    if (!tasa || tasa <= 0) { showToast('Ingresa una tasa válida.', 'error'); return; }

    try {
      const res = await apiFetch('/config', { method: 'PUT', body: JSON.stringify({ tasa_bcv: tasa, tasa_manual: manual }) });
      refrescarConfigGlobal(res.data);
      showToast(`Tasa ${manual ? 'manual' : 'automática'} guardada: Bs. ${tasa.toFixed(2)}`, 'success');
      cargarConfiguracion();
    } catch (err) {
      showToast(err.message || 'Error al guardar la tasa.', 'error');
    }
  });
}