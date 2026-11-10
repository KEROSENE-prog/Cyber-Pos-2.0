// reportes.js – Lógica y Controlador de Reportes Gerenciales CyberPOS 2.0
// Diseñado para la toma de decisiones, exportación binaria y entrega de email en vivo.

document.addEventListener('DOMContentLoaded', async () => {
  if (!Auth.requireAuth('administrador')) return;

  // Elementos principales del DOM
  const $tipoSelect        = document.getElementById('selectReporte');
  const $desdeInput        = document.getElementById('fechaDesde');
  const $hastaInput        = document.getElementById('fechaHasta');
  const $subfiltroSelect   = document.getElementById('selectSubfiltro');
  const $subfiltroLabel    = document.getElementById('lblSubfiltro');
  const $btnPrevisualizar  = document.getElementById('btnGenerarPreview');
  const $btnPDF            = document.getElementById('btnDescargarPdf');
  const $btnXLSX           = document.getElementById('btnDescargarExcel');
  const $btnEmail          = document.getElementById('btnAbrirModalEmail');
  const $kpiContainer      = document.getElementById('kpisReporteContainer');
  const $bannerReco        = document.getElementById('bannerRecomendacion');
  const $recoDiv           = document.getElementById('textoRecomendacion');
  const $previewTitulo     = document.getElementById('previewTitulo');
  const $previewMeta       = document.getElementById('previewMeta');
  const $tablaHead         = document.getElementById('tablaReporteHead');
  const $tablaBody         = document.getElementById('tablaReporteBody');
  const $tablaFoot         = document.getElementById('tablaReporteFoot');

  // Modal Email
  const $modal             = document.getElementById('modalEmail');
  const $modalEmailInput   = document.getElementById('emailDestino');
  const $modalFormato      = document.getElementById('emailFormatoAdjunto');
  const $modalSubjectInput = document.getElementById('emailAsunto');
  const $modalSendBtn      = document.getElementById('btnSubmitEmail');
  const $modalCloseBtn     = document.getElementById('btnCerrarModalEmail');
  const $modalCancelBtn    = document.getElementById('btnCancelarEmail');

  // Campos SMTP opcionales
  const $smtpHost          = document.getElementById('smtpHost');
  const $smtpPort          = document.getElementById('smtpPort');
  const $smtpUser          = document.getElementById('smtpUser');
  const $smtpPass          = document.getElementById('smtpPass');

  let reporteActualData = null;

  // Fechas por defecto: últimos 30 días hasta hoy
  const hoy = new Date();
  const hace30Dias = new Date();
  hace30Dias.setDate(hoy.getDate() - 30);
  $desdeInput.value = hace30Dias.toISOString().split('T')[0];
  $hastaInput.value = hoy.toISOString().split('T')[0];

  // Definición de subfiltros dinámicos según el reporte seleccionado
  const SUBFILTROS_CONFIG = {
    '1': {
      label: 'Tipo de Estación',
      opciones: [
        { value: 'todos', text: 'Todas las Estaciones (PCs y Consolas)' },
        { value: 'pc', text: 'Solo Computadoras (PCs)' },
        { value: 'consola', text: 'Solo Consolas (PS5)' }
      ]
    },
    '2': {
      label: 'Origen del Producto',
      opciones: [
        { value: 'todos', text: 'Todos los Artículos del Catálogo' },
        { value: 'snacks', text: 'Snacks y Bebidas Estándar' },
        { value: 'personalizados', text: 'Elaborados / Personalizados' }
      ]
    },
    '3': {
      label: 'Flujo de Caja',
      opciones: [
        { value: 'todos', text: 'Todos los Flujos (Alquiler + Snacks)' },
        { value: 'alquiler_tiempo', text: 'Solo Alquiler de Tiempo' },
        { value: 'venta_snack', text: 'Solo Venta de Snacks / Bar' }
      ]
    },
    '4': {
      label: 'Segmentación de Cliente',
      opciones: [
        { value: 'todos', text: 'Todos los Clientes' },
        { value: 'vip', text: 'Solo VIP Diamante (>= $25)' },
        { value: 'frecuente', text: 'Clientes Frecuentes ($10 - $25)' },
        { value: 'ocasional', text: 'Clientes Ocasionales (< $10)' }
      ]
    },
    '5': {
      label: 'Segmento Operativo',
      opciones: [
        { value: 'todos', text: 'Toda la Sala de Juego' },
        { value: 'pc', text: 'Solo Estaciones PC' },
        { value: 'consola', text: 'Solo Estaciones Consola' }
      ]
    }
  };

  function actualizarOpcionesSubfiltro() {
    const tipo = $tipoSelect.value;
    const cfg = SUBFILTROS_CONFIG[tipo] || SUBFILTROS_CONFIG['1'];
    if ($subfiltroLabel) $subfiltroLabel.textContent = cfg.label;
    if ($subfiltroSelect) {
      $subfiltroSelect.innerHTML = '';
      cfg.opciones.forEach(opt => {
        const optionEl = document.createElement('option');
        optionEl.value = opt.value;
        optionEl.textContent = opt.text;
        $subfiltroSelect.appendChild(optionEl);
      });
    }
  }

  $tipoSelect.addEventListener('change', () => {
    actualizarOpcionesSubfiltro();
    cargarPrevisualizacion();
  });

  if ($subfiltroSelect) {
    $subfiltroSelect.addEventListener('change', () => {
      cargarPrevisualizacion();
    });
  }

  // -------------------------------------------------------------------------
  // Renderizado de KPIs
  // -------------------------------------------------------------------------
  function renderKPIs(kpis) {
    $kpiContainer.innerHTML = '';
    if (!Array.isArray(kpis)) return;
    kpis.forEach(kpi => {
      const card = document.createElement('article');
      card.className = 'kpi-card';
      card.innerHTML = `
        <header>
          <h3>${escapeHtml(kpi.label || kpi.titulo || '')}</h3>
        </header>
        <p class="kpi-value" style="color:var(--brand-primary);">${escapeHtml(kpi.value || kpi.valor || '')}</p>
        <small style="color:var(--text-muted);font-size:.78rem;">${escapeHtml(kpi.descripcion || 'Indicador clave consolidado')}</small>
      `;
      $kpiContainer.appendChild(card);
    });
  }

  // -------------------------------------------------------------------------
  // Renderizado de Recomendación Gerencial
  // -------------------------------------------------------------------------
  function renderRecommendation(text) {
    if ($recoDiv) $recoDiv.textContent = text || '';
    if ($bannerReco) $bannerReco.style.display = text ? 'flex' : 'none';
  }

  // -------------------------------------------------------------------------
  // Renderizado de la Tabla Analítica y Fila Final de Totales
  // -------------------------------------------------------------------------
  function renderTable(reporte) {
    $tablaHead.innerHTML = '';
    $tablaBody.innerHTML = '';
    if ($tablaFoot) $tablaFoot.innerHTML = '';

    const code = reporte.reporte;
    const items = reporte.datos || [];
    const totales = reporte.totales || {};

    let headers = [];
    let rowsHtml = '';
    let footHtml = '';

    if (code === 'rentabilidad_equipos') {
      headers = ['Estación', 'Número', 'Tipo', 'Tarifa/h', 'Sesiones', 'Horas Totales', 'Ticket Prom ($)', 'Total USD', 'Total Bs (BCV)', '% Aporte', 'Diagnóstico Gerencial'];
      items.forEach(d => {
        const diagClass = d.diagnostico.includes('ALTO') ? 'alto' : (d.diagnostico.includes('ESTÁNDAR') ? 'estandar' : 'critico');
        rowsHtml += `
          <tr>
            <td><strong>${escapeHtml(d.nombre_equipo)}</strong></td>
            <td><code>${escapeHtml(d.numero)}</code></td>
            <td><span class="status-tag ${d.tipo === 'PC' ? 'estandar' : 'alto'}">${escapeHtml(d.tipo)}</span></td>
            <td>$${parseFloat(d.tarifa_usd).toFixed(2)}</td>
            <td>${d.cantidad_sesiones}</td>
            <td>${parseFloat(d.horas_totales).toFixed(1)} hrs</td>
            <td>$${parseFloat(d.ticket_promedio_usd).toFixed(2)}</td>
            <td style="color:var(--brand-primary);font-weight:700;">$${parseFloat(d.total_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.total_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td>${parseFloat(d.porcentaje_ingreso).toFixed(1)}%</td>
            <td><span class="status-tag ${diagClass}">${escapeHtml(d.diagnostico)}</span></td>
          </tr>
        `;
      });
      footHtml = `
        <tr class="fila-totales">
          <td colspan="4">TOTALES SALA AUDITADA</td>
          <td>${totales.sesiones || 0} ses.</td>
          <td>${parseFloat(totales.total_horas || 0).toFixed(1)} hrs</td>
          <td>$${parseFloat(totales.ticket_promedio_usd || 0).toFixed(2)}</td>
          <td>$${parseFloat(totales.total_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.total_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          <td>100.0%</td>
          <td>CONSOLIDADO</td>
        </tr>
      `;
    } else if (code === 'rotacion_inventario') {
      headers = ['Producto', 'Categoría', 'Precio ($)', 'Stock Actual', 'Valor Stock ($)', 'Valor Stock (Bs)', 'Vendidas', 'Ingresos ($)', 'Ingresos (Bs)', 'Rotación', 'Estado Gerencial'];
      items.forEach(d => {
        const estClass = d.estado_gerencial.includes('CRÍTICO') ? 'critico' : (d.estado_gerencial.includes('ALERTA') ? 'alerta' : (d.estado_gerencial.includes('SOBRESTOCK') ? 'sobrestock' : 'optimo'));
        rowsHtml += `
          <tr>
            <td><strong>${escapeHtml(d.nombre)}</strong></td>
            <td>${escapeHtml(d.categoria)}</td>
            <td>$${parseFloat(d.precio_usd).toFixed(2)}</td>
            <td><strong>${d.stock_actual}</strong></td>
            <td>$${parseFloat(d.valor_stock_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.valor_stock_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td>${d.unidades_vendidas} unds</td>
            <td style="color:var(--brand-primary);font-weight:700;">$${parseFloat(d.ingresos_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.ingresos_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td>${parseFloat(d.indice_rotacion).toFixed(1)}%</td>
            <td><span class="status-tag ${estClass}">${escapeHtml(d.estado_gerencial)}</span></td>
          </tr>
        `;
      });
      footHtml = `
        <tr class="fila-totales">
          <td colspan="3">TOTALES INVENTARIO CONSOLIDADO</td>
          <td>${totales.stock_actual || 0} unds</td>
          <td>$${parseFloat(totales.valor_stock_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.valor_stock_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          <td>${totales.unidades_vendidas || 0} unds</td>
          <td>$${parseFloat(totales.ingresos_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.ingresos_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          <td>-</td>
          <td>AUDITADO</td>
        </tr>
      `;
    } else if (code === 'arqueo_caja') {
      headers = ['Fecha Auditoría', 'Operaciones', 'Alquiler USD', 'Snacks/Bar USD', 'Total Recaudado USD', 'Total Equivalente Bs (BCV)'];
      items.forEach(d => {
        rowsHtml += `
          <tr>
            <td><strong>${escapeHtml(d.fecha)}</strong></td>
            <td>${d.operaciones}</td>
            <td>$${parseFloat(d.alquiler_usd).toFixed(2)}</td>
            <td>$${parseFloat(d.snacks_usd).toFixed(2)}</td>
            <td style="color:var(--brand-primary);font-weight:700;">$${parseFloat(d.total_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.total_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          </tr>
        `;
      });
      footHtml = `
        <tr class="fila-totales">
          <td>TOTAL RECAUDADO</td>
          <td>${totales.operaciones || 0} ops.</td>
          <td>$${parseFloat(totales.alquiler_usd || 0).toFixed(2)}</td>
          <td>$${parseFloat(totales.snacks_usd || 0).toFixed(2)}</td>
          <td>$${parseFloat(totales.total_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.total_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        </tr>
      `;
    } else if (code === 'fidelizacion_clientes') {
      headers = ['Pos.', 'Cliente Gamer', 'Visitas', 'Horas Jugadas', 'Alquiler ($)', 'Snacks ($)', 'Inversión Total ($)', 'Inversión Total (Bs)', 'Ticket Prom ($)', '% Aporte', 'Clasificación'];
      items.forEach(d => {
        const segClass = d.clasificacion.includes('VIP') ? 'diamante' : (d.clasificacion.includes('FRECUENTE') ? 'frecuente' : 'estandar');
        rowsHtml += `
          <tr>
            <td><code>#${d.posicion}</code></td>
            <td><strong>${escapeHtml(d.cliente)}</strong></td>
            <td>${d.visitas}</td>
            <td>${parseFloat(d.horas_jugadas).toFixed(1)} hrs</td>
            <td>$${parseFloat(d.gasto_alquiler_usd).toFixed(2)}</td>
            <td>$${parseFloat(d.gasto_snacks_usd).toFixed(2)}</td>
            <td style="color:var(--brand-primary);font-weight:700;">$${parseFloat(d.inversion_total_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.inversion_total_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td>$${parseFloat(d.ticket_promedio_usd).toFixed(2)}</td>
            <td>${parseFloat(d.porcentaje_aporte).toFixed(1)}%</td>
            <td><span class="status-tag ${segClass}">${escapeHtml(d.clasificacion)}</span></td>
          </tr>
        `;
      });
      footHtml = `
        <tr class="fila-totales">
          <td colspan="2">TOTALES CARTERA DE CLIENTES</td>
          <td>${totales.visitas || 0} vis.</td>
          <td>${parseFloat(totales.total_horas || 0).toFixed(1)} hrs</td>
          <td>$${parseFloat(totales.gasto_alquiler_usd || 0).toFixed(2)}</td>
          <td>$${parseFloat(totales.gasto_snacks_usd || 0).toFixed(2)}</td>
          <td>$${parseFloat(totales.total_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.total_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          <td>-</td>
          <td>100.0%</td>
          <td>CARTERA AUDITADA</td>
        </tr>
      `;
    } else {
      // eficiencia_operativa
      headers = ['Estación / Área', 'Gestiones Totales', 'Completadas', 'Canceladas', 'Pendientes', 'Efectividad (%)', 'Cobrado ($)', 'Cobrado (Bs)', 'Lucro Cesante ($)', 'Lucro Cesante (Bs)'];
      items.forEach(d => {
        const efClass = d.tasa_conversion >= 80 ? 'alto' : (d.tasa_conversion >= 50 ? 'estandar' : 'critico');
        rowsHtml += `
          <tr>
            <td><strong>${escapeHtml(d.equipo)}</strong></td>
            <td>${d.total_gestiones}</td>
            <td>${d.completadas}</td>
            <td>${d.canceladas}</td>
            <td>${d.pendientes}</td>
            <td><span class="status-tag ${efClass}">${parseFloat(d.tasa_conversion).toFixed(1)}%</span></td>
            <td style="color:var(--brand-primary);font-weight:700;">$${parseFloat(d.cobrado_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.cobrado_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
            <td style="color:#f87171;font-weight:700;">$${parseFloat(d.lucro_cesante_usd).toFixed(2)}</td>
            <td>Bs ${parseFloat(d.lucro_cesante_bs).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          </tr>
        `;
      });
      footHtml = `
        <tr class="fila-totales">
          <td>TOTALES SALA DE JUEGO</td>
          <td>${totales.total_gestiones || 0}</td>
          <td>${totales.completadas || 0}</td>
          <td>${totales.canceladas || 0}</td>
          <td>${totales.pendientes || 0}</td>
          <td>${parseFloat(totales.tasa_conversion || 0).toFixed(1)}%</td>
          <td>$${parseFloat(totales.cobrado_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.cobrado_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
          <td>$${parseFloat(totales.lucro_cesante_usd || 0).toFixed(2)}</td>
          <td>Bs ${parseFloat(totales.lucro_cesante_bs || 0).toLocaleString('es-VE', {minimumFractionDigits:2})}</td>
        </tr>
      `;
    }

    // Cabecera
    const trHead = document.createElement('tr');
    headers.forEach(h => {
      const th = document.createElement('th');
      th.textContent = h;
      trHead.appendChild(th);
    });
    $tablaHead.appendChild(trHead);

    // Cuerpo
    $tablaBody.innerHTML = rowsHtml;

    // Pie con Totales
    if ($tablaFoot) {
      $tablaFoot.innerHTML = footHtml;
    }
  }

  // -------------------------------------------------------------------------
  // Carga y Vista Previa en Vivo
  // -------------------------------------------------------------------------
  async function cargarPrevisualizacion() {
    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    const subfiltro = $subfiltroSelect ? $subfiltroSelect.value : 'todos';

    if (!tipo) {
      showToast('Selecciona un tipo de reporte.', 'warning');
      return;
    }

    try {
      const resp = await apiFetch(`/reportes/datos?tipo=${encodeURIComponent(tipo)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}&subfiltro=${encodeURIComponent(subfiltro)}`);
      const rep = resp.data || {};
      reporteActualData = rep;

      if ($previewTitulo) $previewTitulo.textContent = rep.titulo || 'Vista Previa del Reporte';
      if ($previewMeta) {
        $previewMeta.textContent = `Período: ${rep.fecha_desde} al ${rep.fecha_hasta} | Filtro: ${rep.subfiltro_label || 'Todos'} | Tasa BCV: ${parseFloat(rep.tasa_bcv).toFixed(2)} Bs/$ | Responsable: ${rep.responsable || 'Administrador'}`;
      }

      renderKPIs(rep.kpis || []);
      renderRecommendation(rep.recomendacion || '');
      renderTable(rep);

      $btnPDF.disabled = false;
      $btnXLSX.disabled = false;
      $btnEmail.disabled = false;
    } catch (e) {
      showToast(e.message, 'error');
    }
  }

  // -------------------------------------------------------------------------
  // Descarga Autónoma (.pdf y .xlsx)
  // -------------------------------------------------------------------------
  async function descargarArchivo(formato) {
    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    const subfiltro = $subfiltroSelect ? $subfiltroSelect.value : 'todos';
    const usuario = Auth.getUser() || {};

    const url = `${API_BASE}?r=reportes/exportar&tipo=${encodeURIComponent(tipo)}&formato=${formato}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}&subfiltro=${encodeURIComponent(subfiltro)}&responsable_nombre=${encodeURIComponent(usuario.nombre_completo || 'Administrador')}&responsable_rol=${encodeURIComponent(usuario.rol || 'administrador')}`;

    const headers = {};
    const token = Auth.getToken();
    if (token) headers['X-Auth-Token'] = token;

    try {
      showToast(`Generando y descargando archivo ${formato.toUpperCase()}...`, 'info', 2000);
      const res = await fetch(url, { headers });
      if (!res.ok) throw new Error('Error al descargar el archivo del servidor.');

      const blob = await res.blob();
      const disposition = res.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename\*?=['"]?([^;'"]+)['"]?/i);
      let filename = `reporte_${tipo}_${Date.now()}.${formato}`;
      if (match && match[1]) {
        filename = decodeURIComponent(match[1]);
      }

      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(a.href);
      showToast(`Descarga completada: ${filename}`, 'success');
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // -------------------------------------------------------------------------
  // Modal de Envío por Email en Vivo
  // -------------------------------------------------------------------------
  $btnEmail.addEventListener('click', () => {
    const repTitulo = (reporteActualData && reporteActualData.titulo) || $tipoSelect.selectedOptions[0].textContent;
    $modalEmailInput.value = 'zedmous@gmail.com'; // Pre-llenado oficial para pruebas en 1 clic
    if ($modalFormato) $modalFormato.value = 'pdf';
    $modalSubjectInput.value = `[CyberPOS 2.0 - Laneros Gamer] Acta Gerencial: ${repTitulo}`;

    if ($modal.showModal) {
      $modal.showModal();
    } else {
      $modal.style.display = 'block';
    }
  });

  function cerrarModalEmail() {
    if ($modal.close) {
      $modal.close();
    } else {
      $modal.style.display = 'none';
    }
  }

  $modalCloseBtn.addEventListener('click', cerrarModalEmail);
  if ($modalCancelBtn) $modalCancelBtn.addEventListener('click', cerrarModalEmail);

  $modalSendBtn.addEventListener('click', async () => {
    const to = $modalEmailInput.value.trim();
    if (!to) {
      showToast('Por favor ingresa un correo destino válido.', 'warning');
      return;
    }

    const tipo = $tipoSelect.value;
    const desde = $desdeInput.value;
    const hasta = $hastaInput.value;
    const subfiltro = $subfiltroSelect ? $subfiltroSelect.value : 'todos';
    const formato = $modalFormato ? $modalFormato.value : 'pdf';
    const subject = $modalSubjectInput.value.trim();
    const usuario = Auth.getUser() || {};

    const payload = {
      to_email: to,
      subject: subject,
      tipo: tipo,
      desde: desde,
      hasta: hasta,
      subfiltro: subfiltro,
      formato: formato,
      responsable_nombre: usuario.nombre_completo || 'Administrador',
      responsable_rol: usuario.rol || 'administrador'
    };

    if ($smtpHost && $smtpHost.value.trim()) payload.smtp_host = $smtpHost.value.trim();
    if ($smtpPort && $smtpPort.value.trim()) payload.smtp_port = parseInt($smtpPort.value.trim());
    if ($smtpUser && $smtpUser.value.trim()) payload.smtp_user = $smtpUser.value.trim();
    if ($smtpPass && $smtpPass.value.trim()) payload.smtp_pass = $smtpPass.value.trim();

    $modalSendBtn.disabled = true;
    $modalSendBtn.innerHTML = '<span>⏳</span> Enviando...';

    try {
      const resp = await apiFetch('/reportes/enviar-email', {
        method: 'POST',
        body: payload
      });
      showToast(resp.message || `Reporte enviado con éxito a ${to}`, 'success', 4500);
      cerrarModalEmail();
    } catch (e) {
      showToast(e.message, 'error', 4500);
    } finally {
      $modalSendBtn.disabled = false;
      $modalSendBtn.innerHTML = '<span>🚀</span> Enviar Ahora';
    }
  });

  // -------------------------------------------------------------------------
  // Listeners de los botones principales
  // -------------------------------------------------------------------------
  $btnPrevisualizar.addEventListener('click', cargarPrevisualizacion);
  $btnPDF.addEventListener('click', () => descargarArchivo('pdf'));
  $btnXLSX.addEventListener('click', () => descargarArchivo('xlsx'));

  // Inicializar subfiltros y cargar vista previa inicial
  actualizarOpcionesSubfiltro();
  await cargarPrevisualizacion();
});
