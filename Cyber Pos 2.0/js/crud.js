/**
 * CyberPOS — crud.js v3
 * Inventario de Snacks (catálogo estándar) + Catálogo Personalizado.
 * Precio en Bs (tasa BCV) mostrado en gris pequeño debajo del precio USD.
 */

let currentEditId   = null;
let currentDeleteId = null;
let currentType     = 'snacks'; // 'snacks' | 'personalizados'
let debounceTimeout = null;

document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.requireAuth('administrador')) return;

  const usuario    = Auth.getUser();
  const avatarName = document.querySelector('.topbar figcaption');
  if (avatarName && usuario) avatarName.textContent = `${usuario.nombre_completo} (${usuario.rol})`;

  // Botón salir
  const salirLink = document.querySelector('aside nav a[href="login.html"]');
  if (salirLink) {
    salirLink.addEventListener('click', async (e) => {
      e.preventDefault();
      try { await apiFetch('/auth/logout', { method: 'POST' }); } catch (_) {}
      Auth.clearSession();
      showToast('Sesión finalizada', 'info', 1200);
      setTimeout(() => { window.location.href = 'login.html'; }, 700);
    });
  }

  await cargarConfigSistema();

  // Cargar ambos catálogos
  cargarSnacks();
  cargarPersonalizados();

  // Filtros snacks
  const categorySelect = document.querySelector('#snacksCatalog .toolbar select');
  const searchInput    = document.querySelector('#snacksCatalog .toolbar input[type="text"]');
  if (categorySelect) categorySelect.addEventListener('change', () => cargarSnacks());
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => cargarSnacks(), 300);
    });
  }

  // Filtros personalizados
  const catPers    = document.getElementById('categoriaPersonalizados');
  const searchPers = document.getElementById('searchPersonalizados');
  if (catPers)    catPers.addEventListener('change', () => cargarPersonalizados());
  if (searchPers) {
    searchPers.addEventListener('input', () => {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => cargarPersonalizados(), 300);
    });
  }

  setupModales();
});

// =========================================================================
// SNACKS — catálogo estándar
// =========================================================================
async function cargarSnacks() {
  const cat  = document.querySelector('#snacksCatalog .toolbar select')?.value || 'all';
  const busq = document.querySelector('#snacksCatalog .toolbar input[type="text"]')?.value.trim() || '';
  let qs = [];
  if (cat && cat !== 'all') qs.push(`categoria=${encodeURIComponent(cat)}`);
  if (busq) qs.push(`busqueda=${encodeURIComponent(busq)}`);
  const url = `/snacks${qs.length > 0 ? '?' + qs.join('&') : ''}`;
  try {
    const res = await apiFetch(url);
    if (res && res.success) renderizarProductos(res.data, 'snacksGrid', 'snacks');
  } catch (err) {
    showToast(err.message || 'Error al cargar snacks.', 'error');
  }
}

// =========================================================================
// PERSONALIZADOS
// =========================================================================
async function cargarPersonalizados() {
  const cat  = document.getElementById('categoriaPersonalizados')?.value || 'all';
  const busq = document.getElementById('searchPersonalizados')?.value.trim() || '';
  let qs = [];
  if (cat && cat !== 'all') qs.push(`categoria=${encodeURIComponent(cat)}`);
  if (busq) qs.push(`busqueda=${encodeURIComponent(busq)}`);
  const url = `/personalizados${qs.length > 0 ? '?' + qs.join('&') : ''}`;
  try {
    const res = await apiFetch(url);
    if (res && res.success) renderizarProductos(res.data, 'personalizadosGrid', 'personalizados');
  } catch (err) {
    showToast(err.message || 'Error al cargar catálogo personalizado.', 'error');
  }
}

// =========================================================================
// RENDERIZADO COMÚN DE PRODUCTOS
// =========================================================================
function renderizarProductos(productos, gridId, tipo) {
  const grid = document.getElementById(gridId);
  if (!grid) return;
  const tasa = getTasaBCV();

  if (productos.length === 0) {
    const tipoLabel = tipo === 'personalizados' ? 'productos personalizados' : 'snacks';
    grid.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted);background:var(--bg-card);border-radius:12px;border:1px dashed var(--border-color)">
        <p style="font-size:1.1rem;margin-bottom:8px">No se encontraron ${tipoLabel}.</p>
        <span style="font-size:.9rem">Agrega uno con el botón <strong>+ Nuevo</strong>.</span>
      </div>`;
    return;
  }

  grid.innerHTML = productos.map(prod => {
    const precio   = parseFloat(prod.precio || 0).toFixed(2);
    const precioBs = (parseFloat(precio) * tasa).toFixed(2);
    const stock    = parseInt(prod.stock || 0, 10);
    const imgUrl   = prod.url_imagen || 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80';
    const catLabel = prod.categoria || '';

    return `
      <article class="snack-card" data-id="${prod.id}" data-tipo="${tipo}">
        <img src="${escapeAttr(imgUrl)}" alt="${escapeAttr(prod.nombre)}" class="snack-image"
          onerror="this.src='https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80'">
        <section class="snack-info">
          <h3>${escapeHtml(prod.nombre)}</h3>
          ${catLabel ? `<span style="font-size:.75rem;color:var(--text-muted);margin-bottom:4px;display:block">📁 ${escapeHtml(catLabel)}</span>` : ''}
          <p class="snack-meta">
            <span>
              <data class="snack-price" value="${precio}">$${precio}</data>
              <small style="display:block;color:#64748b;font-size:.75rem;margin-top:2px">Bs. ${precioBs}</small>
            </span>
            <span>Stock: ${stock}</span>
          </p>
        </section>
        <footer class="card-actions">
          <button class="secondary btn-editar"
            data-id="${prod.id}"
            data-tipo="${tipo}"
            data-nombre="${escapeAttr(prod.nombre)}"
            data-precio="${precio}"
            data-stock="${stock}"
            data-imagen="${escapeAttr(imgUrl)}"
            data-categoria="${escapeAttr(catLabel)}">
            ✏️ Editar
          </button>
          <button class="danger btn-eliminar"
            data-id="${prod.id}"
            data-tipo="${tipo}"
            data-nombre="${escapeAttr(prod.nombre)}">
            🗑 Eliminar
          </button>
        </footer>
      </article>`;
  }).join('');

  vincularEventosTarjetas(gridId, tipo);
}

// =========================================================================
// VINCULAR EVENTOS DE TARJETAS
// =========================================================================
function vincularEventosTarjetas(gridId, tipo) {
  const grid     = document.getElementById(gridId);
  const modalForm   = document.getElementById('modalForm');
  const modalDelete = document.getElementById('modalDelete');
  const modalTitle  = document.getElementById('modalTitle');

  grid.querySelectorAll('.btn-editar').forEach(btn => {
    btn.addEventListener('click', () => {
      currentEditId = btn.dataset.id;
      currentType   = btn.dataset.tipo || tipo;
      modalTitle.textContent = `Editar Producto`;

      document.getElementById('snackName').value     = btn.dataset.nombre   || '';
      document.getElementById('snackPrice').value    = btn.dataset.precio   || '';
      document.getElementById('snackStock').value    = btn.dataset.stock    || '';
      document.getElementById('snackImage').value    = btn.dataset.imagen   || '';

      const catSelect = document.getElementById('snackCategory');
      const catCustom = document.getElementById('snackCategoryCustom');
      const isStandard = ['bebidas', 'comida'].includes(btn.dataset.categoria);

      if (currentType === 'personalizados') {
        if (catSelect) catSelect.style.display = 'none';
        if (catCustom) { catCustom.style.display = 'block'; catCustom.value = btn.dataset.categoria || ''; }
      } else {
        if (catSelect) { catSelect.style.display = 'block'; catSelect.value = btn.dataset.categoria || 'bebidas'; }
        if (catCustom) catCustom.style.display = 'none';
      }

      modalForm.showModal();
    });
  });

  grid.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', () => {
      currentDeleteId = btn.dataset.id;
      currentType     = btn.dataset.tipo || tipo;
      const deleteText = modalDelete.querySelector('.form-fields p');
      if (deleteText) deleteText.textContent = `¿Eliminar "${btn.dataset.nombre}"? Esta acción no se puede deshacer.`;
      modalDelete.showModal();
    });
  });
}

// =========================================================================
// SETUP DE MODALES (CRUD)
// =========================================================================
function setupModales() {
  const modalForm    = document.getElementById('modalForm');
  const modalDelete  = document.getElementById('modalDelete');
  const snackForm    = document.getElementById('snackForm');
  const modalTitle   = document.getElementById('modalTitle');
  const catSelect    = document.getElementById('snackCategory');
  const catCustom    = document.getElementById('snackCategoryCustom');

  // Botones "Nuevo"
  const btnNuevoSnack = document.getElementById('btnNuevoSnack');
  const btnNuevoPers  = document.getElementById('btnNuevoPers');

  document.getElementById('btnCloseModal')?.addEventListener('click', () => modalForm.close());
  document.getElementById('btnCancelModal')?.addEventListener('click', () => modalForm.close());
  document.getElementById('btnCloseDelete')?.addEventListener('click', () => modalDelete.close());
  document.getElementById('btnCancelDelete')?.addEventListener('click', () => modalDelete.close());

  if (btnNuevoSnack) {
    btnNuevoSnack.addEventListener('click', () => {
      currentEditId = null;
      currentType   = 'snacks';
      modalTitle.textContent = 'Nuevo Snack';
      snackForm.reset();
      if (catSelect) catSelect.style.display = 'block';
      if (catCustom) catCustom.style.display = 'none';
      modalForm.showModal();
    });
  }

  if (btnNuevoPers) {
    btnNuevoPers.addEventListener('click', () => {
      currentEditId = null;
      currentType   = 'personalizados';
      modalTitle.textContent = 'Nuevo Producto Personalizado';
      snackForm.reset();
      if (catSelect) catSelect.style.display = 'none';
      if (catCustom) { catCustom.style.display = 'block'; catCustom.value = ''; }
      modalForm.showModal();
    });
  }

  // SUBMIT del formulario
  if (snackForm) {
    snackForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nombre     = document.getElementById('snackName').value.trim();
      const precio     = parseFloat(document.getElementById('snackPrice').value);
      const stock      = parseInt(document.getElementById('snackStock').value, 10);
      const url_imagen = document.getElementById('snackImage').value.trim();

      let categoria;
      if (currentType === 'personalizados') {
        categoria = (catCustom?.value || '').trim() || 'Otro';
      } else {
        categoria = catSelect?.value || 'bebidas';
      }

      if (!nombre || isNaN(precio) || isNaN(stock)) {
        showToast('Completa todos los campos requeridos.', 'error');
        return;
      }

      const payload    = { nombre, precio, stock, url_imagen, categoria };
      const submitBtn  = snackForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Guardando...';

      try {
        let res;
        const endpoint = currentType === 'personalizados' ? 'personalizados' : 'snacks';
        if (currentEditId) {
          res = await apiFetch(`/${endpoint}/${currentEditId}`, { method: 'PUT', body: JSON.stringify(payload) });
        } else {
          res = await apiFetch(`/${endpoint}`, { method: 'POST', body: JSON.stringify(payload) });
        }
        if (res && res.success) {
          modalForm.close();
          showToast(res.message || 'Guardado.', 'success');
          if (currentType === 'personalizados') await cargarPersonalizados();
          else await cargarSnacks();
        } else {
          throw new Error(res?.error || 'No se pudo guardar.');
        }
      } catch (err) {
        showToast(err.message || 'Error al guardar.', 'error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar';
      }
    });
  }

  // CONFIRMAR ELIMINACIÓN
  const btnConfirmDelete = modalDelete?.querySelector('.modal-actions button.danger');
  if (btnConfirmDelete) {
    btnConfirmDelete.addEventListener('click', async () => {
      if (!currentDeleteId) return;
      const origText = btnConfirmDelete.textContent;
      btnConfirmDelete.disabled = true;
      btnConfirmDelete.textContent = 'Eliminando...';
      try {
        const endpoint = currentType === 'personalizados' ? 'personalizados' : 'snacks';
        const res = await apiFetch(`/${endpoint}/${currentDeleteId}`, { method: 'DELETE' });
        if (res && res.success) {
          modalDelete.close();
          showToast(res.message || 'Eliminado.', 'success');
          if (currentType === 'personalizados') await cargarPersonalizados();
          else await cargarSnacks();
        } else {
          throw new Error(res?.error || 'No se pudo eliminar.');
        }
      } catch (err) {
        showToast(err.message || 'Error al eliminar.', 'error');
      } finally {
        btnConfirmDelete.disabled = false;
        btnConfirmDelete.textContent = origText;
        currentDeleteId = null;
      }
    });
  }
}

// Helpers
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
