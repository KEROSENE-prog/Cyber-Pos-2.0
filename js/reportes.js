// reportes.js – Lógica de la página de Reportes Gerenciales

// ---------------------------------------------------------------------------
// Inicialización de la página
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', async () => {
  // Verificar que el usuario sea admin
  if (!Auth.requireAuth('administrador')) return;

  // Elementos del DOM
  const $tipoSelect = document.getElementById('selectReporte');
  const $desdeInput = document.getElementById('fechaDesde');
  const $hastaInput = document.getElementById('fechaHasta');
  const $btnPrevisualizar = document.getElementById('btnGenerarPreview');
  const $btnPDF = document.getElementById('btnDescargarPdf');
  const $btnXLSX = document.getElementById('btnDescargarExcel');
  const $btnEmail = document.getElementById('btnAbrirModalEmail');
  const $kpiContainer = document.getElementById('kpisReporteContainer');
  const $recoDiv = document.getElementById('textoRecomendacion');
  const $tablaHead = document.getElementById('tablaReporteHead');
  const $tablaBody = document.getElementById('tablaReporteBody');
  const $modal = document.getElementById('modalEmail');
  const $modalEmailInput = document.getElementById('emailDestino');
  const $modalSubjectInput = document.getElementById('emailAsunto');
  const $modalSendBtn = document.getElementById('btnSubmitEmail');
  const $modalCloseBtn = document.getElementById('btnCerrarModalEmail');

  // -----------------------------------------------------------------------
  // Utilidades locales
  // -----------------------------------------------------------------------
  const formatCurrencyUSD = (val) => `$${parseFloat(val).toFixed(2)}`;
  const formatCurrencyBs = (val) => `${parseFloat(val).toFixed(2)} Bs`;
  const formatPercent = (val) => `${(parseFloat(val) * 100).toFixed(2)}%`;

  // Rellena los campos de fecha con valores por defecto (últimos 30 días)
  const today = new Date();
  const prior = new Date();
  prior.setDate(today.getDate() - 30);
  $desdeInput.value = prior.toISOString().split('T')[0];
  $hastaInput.value = today.toISOString().split('T')[0];

  // -----------------------------------------------------------------------
  // Renderizado de KPIs y tabla a partir de la respuesta del backend
  // -----------------------------------------------------------------------
  function renderKPIs(kpis) {
    $kpiContainer.innerHTML = '';
    if (!Array.isArray(kpis)) return;
    kpis.forEach((kpi) => {
      const card = document.createElement('div');
      card.className = 'kpi-card'; // estilo definido en CSS existente
      card.innerHTML = `
        <h4>${escapeHtml(kpi.titulo)}</h4>
        <p class="kpi-valor">${escapeHtml(kpi.valor)}</p>
        <small>${escapeHtml(kpi.descripcion || '')}</small>
      `;
      $kpiContainer.appendChild(card);
    });
  }

  function renderRecommendation(text) {
    $recoDiv.textContent = text || '';
    const banner = document.getElementById('bannerRecomendacion');
    if (banner) banner.style.display = text ? 'flex' : 'none';
  }
    $recoDiv.textContent = text || '';
  }

  function renderTable(columns, rows) {
    // Limpiar cabecera y cuerpo
    $tablaHead.innerHTML = '';
    $tablaBody.innerHTML = '';
    // Cabecera
    const trHead = document.createElement('tr');
    columns.forEach((col) => {
      const th = document.createElement('th');
      th.textContent = col;
      trHead.appendChild(th);
    });
    $tablaHead.appendChild(trHead);
    // Cuerpo
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      row.forEach((cell) => {
        const td = document.createElement('td');
        // Detectar tipos para formatear moneda o porcentaje si se provee
        if (typeof cell === 'number') {
          td.textContent = cell;
        } else {
          td.textContent = cell;
        }
        tr.appendChild(td);
      });
      $tablaBody.appendChild(tr);
    });
  }

  // -----------------------------------------------------------------------
  // Handlers de la UI
  // -----------------------------------------------------------------------
  async function cargarPrevisualizacion() {
    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    if (!tipo) {
      showToast('Selecciona un tipo de reporte.', 'warning');
      return;
    }
    try {
      const resp = await apiFetch(`/reportes/datos?tipo=${encodeURIComponent(tipo)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`);
      const { kpis, recomendacion, columnas, filas } = resp.data || {};
      renderKPIs(kpis);
      renderRecommendation(recomendacion);
      renderTable(columnas, filas);
      // Habilitar botones de descarga y email
      $btnPDF.disabled = false;
      $btnXLSX.disabled = false;
      $btnEmail.disabled = false;
    } catch (e) {
      showToast(e.message, 'error');
    }
  }

  async function descargarArchivo(formato) {
    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    if (!tipo) return;
    try {
      const blobResp = await apiFetch(`/reportes/exportar?tipo=${encodeURIComponent(tipo)}&formato=${formato}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`, {
        responseType: 'blob', // custom flag, our apiFetch will need to treat it – we'll handle manually below
      });
      // El API devuelve un JSON con la URL del archivo temporal o directamente el blob.
      // Como apiFetch siempre intenta .json(), we bypass it by using fetch directly.
    } catch (e) {
      // fallback: use native fetch for blob
    }
    // Usaremos fetch directamente para obtener blob (más sencillo)
    const url = `${API_BASE}?r=reportes/exportar&tipo=${encodeURIComponent(tipo)}&formato=${formato}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;
    const headers = {};
    const token = Auth.getToken();
    if (token) headers['X-Auth-Token'] = token;
    try {
      const res = await fetch(url, { headers });
      if (!res.ok) throw new Error('Error al descargar el archivo');
      const blob = await res.blob();
      const disposition = res.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename\*?=([^;]+);?/i);
      let filename = `reporte_${tipo}.${formato}`;
      if (match) {
        filename = decodeURIComponent(match[1].replace(/^"|"$/g, ''));
      }
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      a.click();
      URL.revokeObjectURL(a.href);
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // -----------------------------------------------------------------------
  // Modal de envío de email
  // -----------------------------------------------------------------------
  $btnEmail.addEventListener('click', () => {
    // Reset modal fields
    $modalEmailInput.value = '';
    $modalSubjectInput.value = `Reporte ${$tipoSelect.selectedOptions[0].textContent}`;
    $modal.style.display = 'block';
  });

  $modalCloseBtn.addEventListener('click', () => {
    $modal.style.display = 'none';
  });

  $modalSendBtn.addEventListener('click', async () => {
    const to = $modalEmailInput.value.trim();
    const subject = $modalSubjectInput.value.trim();
    if (!to) {
      showToast('Ingresa un correo destino.', 'warning');
      return;
    }
    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    try {
      const resp = await apiFetch('/reportes/enviar-email', {
        method: 'POST',
        body: {
          to_email: to,
          subject: subject,
          tipo: tipo,
          desde: desde,
          hasta: hasta,
        },
      });
      showToast(resp.message || 'Correo enviado correctamente', 'success');
      $modal.style.display = 'none';
    } catch (e) {
      showToast(e.message, 'error');
    }
  });

  // -----------------------------------------------------------------------
  // Botones de acción
  // -----------------------------------------------------------------------
  $btnPrevisualizar.addEventListener('click', cargarPrevisualizacion);
  $btnPDF.addEventListener('click', () => descargarArchivo('pdf'));
  $btnXLSX.addEventListener('click', () => descargarArchivo('xlsx'));

  // Deshabilitar botones hasta que haya datos
  $btnPDF.disabled = true;
  $btnXLSX.disabled = true;
  $btnEmail.disabled = true;
});

