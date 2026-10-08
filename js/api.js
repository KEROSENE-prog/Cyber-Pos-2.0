/**
 * CyberPOS — api.js v4
 * Cliente del backend PHP (MVC). Reemplaza al antiguo motor LocalDB.
 * Incluye: Auth, apiFetch (fetch real), toasts, notificaciones de esquina,
 * ticker global (servidor), config global y utilidades.
 */

// =========================================================================
// GESTOR DE SESIÓN
// =========================================================================
const Auth = {
  getToken()  { return localStorage.getItem('cyberpos_token') || null; },
  getUser()   {
    const raw = localStorage.getItem('cyberpos_user');
    try { return raw ? JSON.parse(raw) : null; } catch { return null; }
  },
  setSession(user, token) {
    if (token) localStorage.setItem('cyberpos_token', token);
    if (user)  localStorage.setItem('cyberpos_user', JSON.stringify(user));
  },
  clearSession() {
    localStorage.removeItem('cyberpos_token');
    localStorage.removeItem('cyberpos_user');
  },
  isAuthenticated() { return !!this.getToken(); },
  requireAuth(allowedRole = null) {
    const user  = this.getUser();
    const token = this.getToken();
    if (!token || !user) { window.location.href = 'login.html'; return false; }
    if (allowedRole && user.rol !== allowedRole && user.rol !== 'administrador') {
      showToast(`Acceso restringido a usuarios con rol ${allowedRole}`, 'error');
      setTimeout(() => { window.location.href = 'login.html'; }, 1500);
      return false;
    }
    return true;
  }
};

// =========================================================================
// CLIENTE DE LA API (apiFetch) — apunta al backend PHP
// =========================================================================
const API_BASE = 'api/index.php';

async function apiFetch(endpoint, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const raw    = String(endpoint).replace(/^\//, '');
  const qIndex = raw.indexOf('?');
  const path   = qIndex === -1 ? raw : raw.slice(0, qIndex);
  const query  = qIndex === -1 ? ''  : raw.slice(qIndex + 1);

  let url = `${API_BASE}?r=${encodeURIComponent(path)}`;
  if (query) url += `&${query}`;

  const headers = { 'Content-Type': 'application/json' };
  const token = Auth.getToken();
  if (token) headers['X-Auth-Token'] = token;

  const init = { method, headers };
  if (options.body !== undefined && options.body !== null) {
    init.body = (typeof options.body === 'string') ? options.body : JSON.stringify(options.body);
  }

  let res;
  try {
    res = await fetch(url, init);
  } catch (e) {
    throw new Error('No se pudo conectar con el servidor. Verifica que PHP esté en ejecución.');
  }

  // Sesión expirada/inválida: limpiar y volver al login.
  if (res.status === 401 && Auth.getToken() && !/login\.html$/.test(location.pathname)) {
    Auth.clearSession();
    location.href = 'login.html';
    throw new Error('Sesión expirada. Inicia sesión de nuevo.');
  }

  let data;
  try {
    data = await res.json();
  } catch (e) {
    throw new Error(`Respuesta inválida del servidor (HTTP ${res.status}).`);
  }

  if (!res.ok || data.success === false) {
    throw new Error(data.error || `Error del servidor (HTTP ${res.status}).`);
  }
  return data;
}

// =========================================================================
// CONFIGURACIÓN GLOBAL (cargada una sola vez desde el backend)
// =========================================================================
let __configPromise = null;

function cargarConfigSistema() {
  if (!__configPromise) {
    __configPromise = apiFetch('/config')
      .then((res) => {
        window.__appConfig = res.data || null;
        aplicarConfigVisual(window.__appConfig);
        return window.__appConfig;
      })
      .catch(() => null);
  }
  return __configPromise;
}

function aplicarConfigVisual(config) {
  if (!config) return;
  if (config.theme_color) {
    document.documentElement.style.setProperty('--brand-primary', config.theme_color);
  }
  const usuario = Auth.getUser();
  const avatar  = (usuario && usuario.avatar_url) || config.avatar_url;
  if (avatar) {
    document.querySelectorAll('img[alt^="Avatar"]').forEach(img => { img.src = avatar; });
  }
}

function getConfig() {
  return window.__appConfig || {};
}

// =========================================================================
// CACHÉ DE EQUIPOS (compartida entre el ticker y las páginas)
// =========================================================================
async function cargarEquipos() {
  const res = await apiFetch('/equipos');
  window.__equiposCache = res.data || [];
  return window.__equiposCache;
}

function getEquiposCache() {
  return window.__equiposCache || [];
}

// Si la config cambia (tema/avatar), refresca la vista.
function refrescarConfigGlobal(config) {
  if (config) window.__appConfig = config;
  aplicarConfigVisual(window.__appConfig);
}

// =========================================================================
// TOASTS (notificaciones breves en esquina superior derecha)
// =========================================================================
function showToast(message, type = 'info', duration = 3000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;top:20px;right:20px;display:flex;flex-direction:column;gap:10px;z-index:99999;max-width:360px;pointer-events:none;';
    document.body.appendChild(container);
  }
  const colors = { success: '#16a34a', error: '#991b1b', info: '#2563eb', warning: '#d97706' };
  const icons  = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
  const toast = document.createElement('div');
  toast.style.cssText = `background:var(--bg-card,#2b2b36);border-left:4px solid ${colors[type]||colors.info};color:var(--text-main,#f8fafc);padding:14px 18px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.55);display:flex;align-items:center;gap:10px;font-size:.92rem;font-family:Inter,sans-serif;pointer-events:all;animation:slideIn .3s ease;`;
  toast.innerHTML = `<span style="font-size:1.1rem">${icons[type]||''}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'fadeOut .3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// =========================================================================
// NOTIFICACIÓN DE ESQUINA (persistente, para alertas de tiempo)
// =========================================================================
function showCornerNotification(message, equipoId = null) {
  let panel = document.getElementById('corner-notifications');
  if (!panel) {
    panel = document.createElement('div');
    panel.id = 'corner-notifications';
    panel.style.cssText = 'position:fixed;bottom:20px;left:20px;display:flex;flex-direction:column;gap:8px;z-index:99998;max-width:320px;';
    document.body.appendChild(panel);
  }
  const notif = document.createElement('div');
  notif.style.cssText = 'background:linear-gradient(135deg,#991b1b,#7f1d1d);color:#fff;padding:14px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(153,27,27,.5);display:flex;flex-direction:column;gap:6px;font-family:Inter,sans-serif;animation:slideIn .3s ease;border-left:4px solid #fca5a5;';
  notif.innerHTML = `
    <span style="font-weight:700;font-size:.95rem">⏰ Tiempo Agotado</span>
    <span style="font-size:.87rem;opacity:.9">${message}</span>
    <button onclick="this.parentElement.remove()" style="background:rgba(255,255,255,.15);border:none;color:#fff;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:.8rem;width:fit-content;margin-top:4px;">Cerrar</button>
  `;
  panel.appendChild(notif);
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('⏰ Tiempo Agotado', { body: message, icon: 'logo-laneros.jpg' });
  }
  setTimeout(() => {
    notif.style.animation = 'fadeOut .4s ease forwards';
    setTimeout(() => notif.remove(), 400);
  }, 12000);
}

// =========================================================================
// INYECCIÓN DE KEYFRAMES
// =========================================================================
(function injectKeyframes() {
  if (document.getElementById('cyberpos-keyframes')) return;
  const style = document.createElement('style');
  style.id = 'cyberpos-keyframes';
  style.textContent = `
    @keyframes slideIn { from { opacity:0; transform:translateX(24px); } to { opacity:1; transform:translateX(0); } }
    @keyframes fadeOut { from { opacity:1; } to { opacity:0; transform:translateX(24px); } }
    @keyframes pulseRed { 0%,100% { box-shadow:0 0 0 0 rgba(153,27,27,.7); } 50% { box-shadow:0 0 0 8px rgba(153,27,27,0); } }
  `;
  document.head.appendChild(style);
})();

// =========================================================================
// UTILIDADES
// =========================================================================
function formatSeconds(totalSeg) {
  const s = Math.max(0, Math.floor(totalSeg));
  const hh = Math.floor(s / 3600).toString().padStart(2, '0');
  const mm = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
  const ss = (s % 60).toString().padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

function getTasaBCV() {
  const c = window.__appConfig;
  const t = c ? parseFloat(c.tasa_bcv) : NaN;
  return t > 0 ? t : 36.85;
}

function precioBs(precioUSD) {
  return (parseFloat(precioUSD) * getTasaBCV()).toFixed(2);
}

// =========================================================================
// TICKER GLOBAL — consulta los equipos al servidor y notifica finalizaciones
// =========================================================================
const PREV_ESTADOS_KEY = 'cyberpos_estados_equipos';
let __equiposEstadosPrevios = {};
try { __equiposEstadosPrevios = JSON.parse(localStorage.getItem(PREV_ESTADOS_KEY)) || {}; } catch (e) { __equiposEstadosPrevios = {}; }
let __tickContador = 0;

async function tickEquipos() {
  if (!Auth.isAuthenticated()) return;
  try {
    const res     = await apiFetch('/equipos');
    const equipos = res.data || [];
    window.__equiposCache = equipos;

    equipos.forEach(eq => {
      const prev = __equiposEstadosPrevios[eq.id];
      if (prev === 'activa' && eq.estado === 'finalizada') {
        const esCliente = window.location.pathname.includes('cliente');
        const msg = esCliente
          ? `Tiempo Agotado ${eq.nombre_equipo}. Acércate a caja para solicitar más tiempo.`
          : `Tiempo Agotado ${eq.nombre_equipo}`;
        showCornerNotification(msg, eq.id);
      }
      __equiposEstadosPrevios[eq.id] = eq.estado;
    });

    try { localStorage.setItem(PREV_ESTADOS_KEY, JSON.stringify(__equiposEstadosPrevios)); } catch (e) { /* sin almacenamiento */ }

    if (document.getElementById('equiposGrid')) {
      if (typeof window.actualizarGridEnVivo === 'function') {
        window.actualizarGridEnVivo(equipos);
      } else if (typeof window.renderizarGrid === 'function') {
        window.renderizarGrid(equipos);
      }
    }
    if (typeof window.actualizarEstacionCliente === 'function') {
      window.actualizarEstacionCliente(equipos);
    }

    __tickContador++;
    if (__tickContador % 5 === 0) {
      if (typeof window.actualizarKPIs === 'function') window.actualizarKPIs();
      if (typeof window.actualizarNotificacionesAdmin === 'function') window.actualizarNotificacionesAdmin();
    }
  } catch (e) {
    /* silencioso: el siguiente tick reintenta */
  }
}

// =========================================================================
// INICIALIZACIÓN GLOBAL
// =========================================================================
async function inicializarConfiguracionGlobal() {
  const config = await cargarConfigSistema() || {};

  // Avatar del usuario logueado (prioridad) — ya aplicado en cargarConfigSistema.
  const usuario = Auth.getUser();
  if (usuario && usuario.avatar_url) {
    document.querySelectorAll('img[alt^="Avatar"]').forEach(img => { img.src = usuario.avatar_url; });
  }

  // Actualización automática de la tasa BCV (solo admin, si no es manual y >3h).
  if (!config.tasa_manual && usuario && usuario.rol === 'administrador') {
    const ahora  = Date.now();
    const ultima = config.ultima_actualizacion_tasa ? new Date(config.ultima_actualizacion_tasa.replace(' ', 'T')).getTime() : 0;
    if ((ahora - ultima) > 3 * 60 * 60 * 1000) {
      try {
        const res  = await fetch('https://ve.dolarapi.com/v1/dolares/oficial', { signal: AbortSignal.timeout(5000) });
        if (res.ok) {
          const d = await res.json();
          const tasa = d.promedio || d.precio;
          if (tasa) {
            const upd = await apiFetch('/config', { method: 'PUT', body: JSON.stringify({ tasa_bcv: tasa, tasa_manual: false }) });
            refrescarConfigGlobal(upd.data);
          }
        }
      } catch (e) { /* silencioso */ }
    }
  }

  // Permiso de notificaciones
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {});
  }

  // Ticker global (cada segundo)
  if (Auth.isAuthenticated()) {
    tickEquipos();
    setInterval(tickEquipos, 1000);
  }
}

document.addEventListener('DOMContentLoaded', inicializarConfiguracionGlobal);