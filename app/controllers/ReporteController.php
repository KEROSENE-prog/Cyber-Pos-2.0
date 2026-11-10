<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - ReporteController
 * Controlador de reportes gerenciales para la toma de decisiones,
 * exportación autónoma a PDF (.pdf) y Excel (.xlsx), y despacho de correo en vivo (SMTP).
 */
class ReporteController extends Controller
{
    private function resolverReporte(string $tipo, ?string $desde, ?string $hasta, ?string $subfiltro = null): array
    {
        $modelo = new Reporte();
        switch ($tipo) {
            case '1':
            case 'rentabilidad_equipos':
                return $modelo->rentabilidadEquipos($desde, $hasta, $subfiltro);

            case '2':
            case 'rotacion_inventario':
                return $modelo->rotacionInventario($desde, $hasta, $subfiltro);

            case '3':
            case 'arqueo_caja':
                return $modelo->arqueoCaja($desde, $hasta, $subfiltro);

            case '4':
            case 'fidelizacion_clientes':
                return $modelo->fidelizacionClientes($desde, $hasta, $subfiltro);

            case '5':
            case 'eficiencia_operativa':
            default:
                return $modelo->eficienciaOperativa($desde, $hasta, $subfiltro);
        }
    }

    private function resolverResponsable(): string
    {
        $user = $this->user();
        $respNombre = $user['nombre_completo'] ?? (string) $this->input('responsable_nombre', 'Administrador General');
        $respRol    = ucfirst($user['rol'] ?? (string) $this->input('responsable_rol', 'Administrador'));
        return "{$respNombre} ({$respRol})";
    }

    /**
     * GET /reportes/datos?tipo=...&desde=...&hasta=...&subfiltro=...
     */
    public function datos(): void
    {
        try {
            $this->requireAdmin();
            $tipo      = (string) $this->input('tipo', '1');
            $desde     = $this->input('desde');
            $hasta     = $this->input('hasta');
            $subfiltro = $this->input('subfiltro');

            $res = $this->resolverReporte(
                $tipo,
                $desde ? (string) $desde : null,
                $hasta ? (string) $hasta : null,
                $subfiltro ? (string) $subfiltro : null
            );
            $res['responsable'] = $this->resolverResponsable();
            $this->ok($res);
        } catch (\Throwable $e) {
            $this->fail('Error al generar vista previa del reporte: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /reportes/exportar?tipo=...&formato=(pdf|xlsx)&desde=...&hasta=...&subfiltro=...
     */
    public function exportar(): void
    {
        try {
            $this->requireAdmin();
            $tipo      = (string) $this->input('tipo', '1');
            $formato   = strtolower((string) $this->input('formato', 'pdf'));
            $desde     = $this->input('desde');
            $hasta     = $this->input('hasta');
            $subfiltro = $this->input('subfiltro');

            $rep = $this->resolverReporte(
                $tipo,
                $desde ? (string) $desde : null,
                $hasta ? (string) $hasta : null,
                $subfiltro ? (string) $subfiltro : null
            );
            $responsableStr = $this->resolverResponsable();

            $slug = preg_replace('/[^a-z0-9_-]/i', '_', $rep['reporte'] ?? 'reporte');
            $timestamp = date('Ymd_His');

            if ($formato === 'xlsx') {
                $xlsx = $this->construirExcel($rep, $responsableStr);
                $xlsx->download("{$slug}_{$timestamp}.xlsx");
            } else {
                $pdf = $this->construirPdf($rep, $responsableStr);
                $pdf->download("{$slug}_{$timestamp}.pdf");
            }
        } catch (\Throwable $e) {
            $this->fail('Error al exportar reporte: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /reportes/enviar-email
     */
    public function enviarEmail(): void
    {
        try {
            $this->requireAdmin();
            $b = $this->body();

            $toEmail = trim((string) ($b['to_email'] ?? 'zedmous@gmail.com'));
            if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $toEmail = 'zedmous@gmail.com';
            }

            $tipo      = (string) ($b['tipo'] ?? '1');
            $desde     = isset($b['desde']) ? (string) $b['desde'] : null;
            $hasta     = isset($b['hasta']) ? (string) $b['hasta'] : null;
            $subfiltro = isset($b['subfiltro']) ? (string) $b['subfiltro'] : null;
            $formato   = strtolower((string) ($b['formato'] ?? 'pdf'));
            $subject   = trim((string) ($b['subject'] ?? ''));

            $rep = $this->resolverReporte($tipo, $desde, $hasta, $subfiltro);
            $responsableStr = $this->resolverResponsable();

            if ($subject === '') {
                $subject = "[CyberPOS 2.0 - Laneros Gamer] Acta Gerencial: {$rep['titulo']}";
            }

            $slug = preg_replace('/[^a-z0-9_-]/i', '_', $rep['reporte'] ?? 'reporte');
            $timestamp = date('Ymd_His');

            if ($formato === 'xlsx') {
                $xlsxGen = $this->construirExcel($rep, $responsableStr);
                $attBin = $xlsxGen->output();
                $attName = "{$slug}_{$timestamp}.xlsx";
            } else {
                $pdfGen = $this->construirPdf($rep, $responsableStr);
                $attBin = $pdfGen->output();
                $attName = "{$slug}_{$timestamp}.pdf";
            }

            $html = $this->construirHtmlCorreo($rep, $responsableStr);

            $smtpConfig = [];
            if (!empty($b['smtp_host']))     $smtpConfig['host']       = (string) $b['smtp_host'];
            if (!empty($b['smtp_port']))     $smtpConfig['port']       = (int) $b['smtp_port'];
            if (!empty($b['smtp_user']))     $smtpConfig['username']   = (string) $b['smtp_user'];
            if (!empty($b['smtp_pass']))     $smtpConfig['password']   = (string) $b['smtp_pass'];
            if (!empty($b['smtp_from']))     $smtpConfig['from_email'] = (string) $b['smtp_from'];

            $mailer = new SimpleMailer($smtpConfig);
            $res = $mailer->send($toEmail, $subject, $html, $attBin, $attName);

            if (!$res['success']) {
                $this->fail($res['error'], 500);
            }

            $this->ok($res, $res['message']);
        } catch (\Throwable $e) {
            $this->fail('Error en envío de correo: ' . $e->getMessage(), 500);
        }
    }

    private function construirPdf(array $rep, string $responsableStr): SimplePDF
    {
        $pdf = new SimplePDF('L', $rep['titulo'] ?? 'Reporte Gerencial');
        $filtrosStr = sprintf("Período: %s al %s | Segmento: %s", $rep['fecha_desde'], $rep['fecha_hasta'], $rep['subfiltro_label'] ?? 'Todos');
        $sub = sprintf('Período auditado: %s al %s  |  Emisión: %s', $rep['fecha_desde'], $rep['fecha_hasta'], $rep['fecha_emision']);

        $pdf->drawHeader(
            $rep['titulo'],
            $sub,
            $rep['fecha_emision'],
            (float) $rep['tasa_bcv'],
            $filtrosStr,
            $responsableStr
        );
        $pdf->drawKPIs($rep['kpis'] ?? []);
        $pdf->drawRecommendation($rep['recomendacion'] ?? '');

        $code = $rep['reporte'] ?? '';
        switch ($code) {
            case 'rentabilidad_equipos':
                $headers = ['Estación', 'Tipo', 'Tarifa/h', 'Sesiones', 'Horas', 'Ticket Med ($)', 'Ingreso USD', 'Ingreso Bs', '% Sala', 'Diagnóstico Gerencial'];
                $widths  = [110, 50, 50, 50, 50, 65, 65, 75, 45, 170];
                $aligns  = ['L', 'C', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['nombre_equipo'],
                        $d['tipo'],
                        '$' . number_format($d['tarifa_usd'], 2),
                        $d['cantidad_sesiones'],
                        number_format($d['horas_totales'], 1) . 'h',
                        '$' . number_format($d['ticket_promedio_usd'], 2),
                        '$' . number_format($d['total_usd'], 2),
                        'Bs ' . number_format($d['total_bs'], 2, ',', '.'),
                        $d['porcentaje_ingreso'] . '%',
                        $d['diagnostico'],
                    ];
                }
                $totalsRow = [
                    'TOTALES SALA',
                    '',
                    '',
                    (string) ($rep['totales']['sesiones'] ?? 0),
                    number_format((float) ($rep['totales']['total_horas'] ?? 0), 1) . 'h',
                    '$' . number_format((float) ($rep['totales']['ticket_promedio_usd'] ?? 0), 2),
                    '$' . number_format((float) ($rep['totales']['total_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['total_bs'] ?? 0), 2, ',', '.'),
                    '100.0%',
                    'AUDITORÍA CONSOLIDADA'
                ];
                break;

            case 'rotacion_inventario':
                $headers = ['Producto', 'Categoría', 'Precio ($)', 'Stock Actual', 'Valor Stock ($)', 'Valor Stock (Bs)', 'Vendidas', 'Ingresos ($)', 'Ingresos (Bs)', 'Rotación', 'Estado Gerencial'];
                $widths  = [125, 60, 50, 50, 65, 70, 45, 60, 70, 45, 110];
                $aligns  = ['L', 'L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['nombre'],
                        $d['categoria'],
                        '$' . number_format($d['precio_usd'], 2),
                        $d['stock_actual'],
                        '$' . number_format($d['valor_stock_usd'], 2),
                        'Bs ' . number_format($d['valor_stock_bs'], 2, ',', '.'),
                        $d['unidades_vendidas'],
                        '$' . number_format($d['ingresos_usd'], 2),
                        'Bs ' . number_format($d['ingresos_bs'], 2, ',', '.'),
                        $d['indice_rotacion'] . '%',
                        $d['estado_gerencial'],
                    ];
                }
                $totalsRow = [
                    'TOTALES INVENTARIO',
                    '',
                    '',
                    (string) ($rep['totales']['stock_actual'] ?? 0),
                    '$' . number_format((float) ($rep['totales']['valor_stock_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['valor_stock_bs'] ?? 0), 2, ',', '.'),
                    (string) ($rep['totales']['unidades_vendidas'] ?? 0),
                    '$' . number_format((float) ($rep['totales']['ingresos_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['ingresos_bs'] ?? 0), 2, ',', '.'),
                    '',
                    'STOCK AUDITADO'
                ];
                break;

            case 'arqueo_caja':
                $headers = ['Fecha Auditoría', 'Operaciones', 'Alquiler USD', 'Snacks/Bar USD', 'Total Recaudado USD', 'Total Equivalente Bs'];
                $widths  = [110, 90, 120, 120, 140, 150];
                $aligns  = ['C', 'R', 'R', 'R', 'R', 'R'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['fecha'],
                        $d['operaciones'],
                        '$' . number_format($d['alquiler_usd'], 2),
                        '$' . number_format($d['snacks_usd'], 2),
                        '$' . number_format($d['total_usd'], 2),
                        'Bs ' . number_format($d['total_bs'], 2, ',', '.'),
                    ];
                }
                $totalsRow = [
                    'TOTAL RECAUDADO',
                    (string) ($rep['totales']['operaciones'] ?? 0),
                    '$' . number_format((float) ($rep['totales']['alquiler_usd'] ?? 0), 2),
                    '$' . number_format((float) ($rep['totales']['snacks_usd'] ?? 0), 2),
                    '$' . number_format((float) ($rep['totales']['total_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['total_bs'] ?? 0), 2, ',', '.')
                ];
                break;

            case 'fidelizacion_clientes':
                $headers = ['Rnk', 'Cliente Gamer', 'Visitas', 'Horas Jugadas', 'Alquiler ($)', 'Snacks ($)', 'Inversión Total ($)', 'Inversión Total (Bs)', 'Ticket Prom ($)', '% Aporte', 'Segmento'];
                $widths  = [30, 125, 45, 60, 60, 60, 75, 80, 65, 45, 95];
                $aligns  = ['C', 'L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'L'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        '#' . $d['posicion'],
                        $d['cliente'],
                        $d['visitas'],
                        number_format($d['horas_jugadas'], 1) . 'h',
                        '$' . number_format($d['gasto_alquiler_usd'], 2),
                        '$' . number_format($d['gasto_snacks_usd'], 2),
                        '$' . number_format($d['inversion_total_usd'], 2),
                        'Bs ' . number_format($d['inversion_total_bs'], 2, ',', '.'),
                        '$' . number_format($d['ticket_promedio_usd'], 2),
                        $d['porcentaje_aporte'] . '%',
                        $d['clasificacion'],
                    ];
                }
                $totalsRow = [
                    '#',
                    'TOTALES CARTERA',
                    (string) ($rep['totales']['visitas'] ?? 0),
                    number_format((float) ($rep['totales']['total_horas'] ?? 0), 1) . 'h',
                    '$' . number_format((float) ($rep['totales']['gasto_alquiler_usd'] ?? 0), 2),
                    '$' . number_format((float) ($rep['totales']['gasto_snacks_usd'] ?? 0), 2),
                    '$' . number_format((float) ($rep['totales']['total_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['total_bs'] ?? 0), 2, ',', '.'),
                    '',
                    '100.0%',
                    'SEGMENTACIÓN AUDITADA'
                ];
                break;

            case 'eficiencia_operativa':
            default:
                $headers = ['Estación / Área', 'Gestiones Totales', 'Completadas', 'Canceladas', 'Pendientes', 'Efectividad (%)', 'Cobrado ($)', 'Cobrado (Bs)', 'Lucro Cesante ($)', 'Lucro Cesante (Bs)'];
                $widths  = [115, 65, 60, 55, 55, 65, 65, 75, 75, 85];
                $aligns  = ['L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['equipo'],
                        $d['total_gestiones'],
                        $d['completadas'],
                        $d['canceladas'],
                        $d['pendientes'],
                        $d['tasa_conversion'] . '%',
                        '$' . number_format($d['cobrado_usd'], 2),
                        'Bs ' . number_format($d['cobrado_bs'], 2, ',', '.'),
                        '$' . number_format($d['lucro_cesante_usd'], 2),
                        'Bs ' . number_format($d['lucro_cesante_bs'], 2, ',', '.'),
                    ];
                }
                $totalsRow = [
                    'TOTALES SALA',
                    (string) ($rep['totales']['total_gestiones'] ?? 0),
                    (string) ($rep['totales']['completadas'] ?? 0),
                    (string) ($rep['totales']['canceladas'] ?? 0),
                    (string) ($rep['totales']['pendientes'] ?? 0),
                    ($rep['totales']['tasa_conversion'] ?? 0) . '%',
                    '$' . number_format((float) ($rep['totales']['cobrado_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['cobrado_bs'] ?? 0), 2, ',', '.'),
                    '$' . number_format((float) ($rep['totales']['lucro_cesante_usd'] ?? 0), 2),
                    'Bs ' . number_format((float) ($rep['totales']['lucro_cesante_bs'] ?? 0), 2, ',', '.')
                ];
                break;
        }

        $pdf->drawTable($headers, $widths, $rows, $aligns, $totalsRow);
        return $pdf;
    }

    private function construirExcel(array $rep, string $responsableStr): SimpleXLSXGen
    {
        $xlsx = new SimpleXLSXGen($rep['titulo']);
        $filtrosStr = sprintf("Desde %s hasta %s | Segmentación: %s", $rep['fecha_desde'], $rep['fecha_hasta'], $rep['subfiltro_label'] ?? 'Todos');

        $xlsx->setInstitutionalHeader(
            $rep['titulo'],
            $rep['fecha_emision'],
            $responsableStr,
            $filtrosStr,
            (float) $rep['tasa_bcv']
        );
        $xlsx->setKPIs($rep['kpis'] ?? []);

        $code = $rep['reporte'] ?? '';
        switch ($code) {
            case 'rentabilidad_equipos':
                $headers = ['Estación', 'Número', 'Tipo', 'Tarifa USD', 'Sesiones', 'Horas Alquiladas', 'Ticket Promedio USD', 'Ticket Promedio Bs', 'Total USD', 'Total Bs', '% Aporte Sala', 'Diagnóstico Gerencial'];
                $types   = ['string', 'string', 'string', 'currency', 'int', 'number', 'currency', 'number', 'currency', 'number', 'percent', 'string'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['nombre_equipo'],
                        $d['numero'],
                        $d['tipo'],
                        $d['tarifa_usd'],
                        $d['cantidad_sesiones'],
                        $d['horas_totales'],
                        $d['ticket_promedio_usd'],
                        $d['ticket_promedio_bs'],
                        $d['total_usd'],
                        $d['total_bs'],
                        $d['porcentaje_ingreso'] / 100,
                        $d['diagnostico'],
                    ];
                }
                $totals = ['TOTALES SALA', '', '', '', $rep['totales']['sesiones'] ?? 0, $rep['totales']['total_horas'] ?? 0, $rep['totales']['ticket_promedio_usd'] ?? 0, $rep['totales']['ticket_promedio_bs'] ?? 0, $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0, 1.0, 'CONSOLIDADO'];
                break;

            case 'rotacion_inventario':
                $headers = ['Producto', 'Categoría', 'Precio USD', 'Stock Actual', 'Valor Stock USD', 'Valor Stock Bs', 'Unidades Vendidas', 'Ingresos USD', 'Ingresos Bs', 'Rotación (%)', 'Estado Gerencial'];
                $types   = ['string', 'string', 'currency', 'int', 'currency', 'number', 'int', 'currency', 'number', 'percent', 'string'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['nombre'],
                        $d['categoria'],
                        $d['precio_usd'],
                        $d['stock_actual'],
                        $d['valor_stock_usd'],
                        $d['valor_stock_bs'],
                        $d['unidades_vendidas'],
                        $d['ingresos_usd'],
                        $d['ingresos_bs'],
                        $d['indice_rotacion'] / 100,
                        $d['estado_gerencial'],
                    ];
                }
                $totals = ['TOTALES INVENTARIO', '', '', $rep['totales']['stock_actual'] ?? 0, $rep['totales']['valor_stock_usd'] ?? 0, $rep['totales']['valor_stock_bs'] ?? 0, $rep['totales']['unidades_vendidas'] ?? 0, $rep['totales']['ingresos_usd'] ?? 0, $rep['totales']['ingresos_bs'] ?? 0, '', 'AUDITADO'];
                break;

            case 'arqueo_caja':
                $headers = ['Fecha', 'Operaciones', 'Alquiler USD', 'Snacks USD', 'Total Día USD', 'Total Día Bs'];
                $types   = ['string', 'int', 'currency', 'currency', 'currency', 'number'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['fecha'],
                        $d['operaciones'],
                        $d['alquiler_usd'],
                        $d['snacks_usd'],
                        $d['total_usd'],
                        $d['total_bs'],
                    ];
                }
                $totals = ['TOTAL RECAUDADO', $rep['totales']['operaciones'] ?? 0, $rep['totales']['alquiler_usd'] ?? 0, $rep['totales']['snacks_usd'] ?? 0, $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0];
                break;

            case 'fidelizacion_clientes':
                $headers = ['Posición', 'Cliente', 'Visitas', 'Horas Jugadas', 'Alquiler USD', 'Snacks USD', 'Inversión Total USD', 'Inversión Total Bs', 'Ticket Promedio USD', 'Ticket Promedio Bs', '% Aporte', 'Segmento'];
                $types   = ['int', 'string', 'int', 'number', 'currency', 'currency', 'currency', 'number', 'currency', 'number', 'percent', 'string'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['posicion'],
                        $d['cliente'],
                        $d['visitas'],
                        $d['horas_jugadas'],
                        $d['gasto_alquiler_usd'],
                        $d['gasto_snacks_usd'],
                        $d['inversion_total_usd'],
                        $d['inversion_total_bs'],
                        $d['ticket_promedio_usd'],
                        $d['ticket_promedio_bs'],
                        $d['porcentaje_aporte'] / 100,
                        $d['clasificacion'],
                    ];
                }
                $totals = ['', 'TOTALES CARTERA', $rep['totales']['visitas'] ?? 0, $rep['totales']['total_horas'] ?? 0, $rep['totales']['gasto_alquiler_usd'] ?? 0, $rep['totales']['gasto_snacks_usd'] ?? 0, $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0, '', '', 1.0, 'AUDITADO'];
                break;

            case 'eficiencia_operativa':
            default:
                $headers = ['Estación / Área', 'Total Gestiones', 'Completadas', 'Canceladas', 'Pendientes', 'Tasa Conversión', 'Cobrado USD', 'Cobrado Bs', 'Lucro Cesante USD', 'Lucro Cesante Bs'];
                $types   = ['string', 'int', 'int', 'int', 'int', 'percent', 'currency', 'number', 'currency', 'number'];
                $rows = [];
                foreach ($rep['datos'] as $d) {
                    $rows[] = [
                        $d['equipo'],
                        $d['total_gestiones'],
                        $d['completadas'],
                        $d['canceladas'],
                        $d['pendientes'],
                        $d['tasa_conversion'] / 100,
                        $d['cobrado_usd'],
                        $d['cobrado_bs'],
                        $d['lucro_cesante_usd'],
                        $d['lucro_cesante_bs'],
                    ];
                }
                $totals = ['TOTALES SALA', $rep['totales']['total_gestiones'] ?? 0, $rep['totales']['completadas'] ?? 0, $rep['totales']['canceladas'] ?? 0, $rep['totales']['pendientes'] ?? 0, ($rep['totales']['tasa_conversion'] ?? 0) / 100, $rep['totales']['cobrado_usd'] ?? 0, $rep['totales']['cobrado_bs'] ?? 0, $rep['totales']['lucro_cesante_usd'] ?? 0, $rep['totales']['lucro_cesante_bs'] ?? 0];
                break;
        }

        $xlsx->setHeaders($headers, $types)
             ->setRows($rows)
             ->setTotals($totals);

        return $xlsx;
    }

    private function construirHtmlCorreo(array $rep, string $responsableStr): string
    {
        $kpisHtml = '';
        foreach ($rep['kpis'] ?? [] as $k) {
            $kpisHtml .= '
                <td style="padding: 10px; background: #16202a; border-radius: 6px; border: 1px solid #233140; min-width: 110px; text-align: center;">
                    <div style="font-size: 11px; color: #8fa0b5; text-transform: uppercase;">' . htmlspecialchars($k['label']) . '</div>
                    <div style="font-size: 15px; font-weight: bold; color: #35c029; margin-top: 4px;">' . htmlspecialchars($k['value']) . '</div>
                </td>
            ';
        }

        // Tabla resumen HTML
        $tableRowsHtml = '';
        $code = $rep['reporte'] ?? '';
        $maxShow = min(count($rep['datos']), 8);

        for ($i = 0; $i < $maxShow; $i++) {
            $d = $rep['datos'][$i];
            $c1 = $d['nombre_equipo'] ?? $d['nombre'] ?? $d['fecha'] ?? $d['cliente'] ?? $d['equipo'] ?? '';
            $c2 = isset($d['total_usd']) ? '$' . number_format($d['total_usd'], 2) : (isset($d['inversion_total_usd']) ? '$' . number_format($d['inversion_total_usd'], 2) : (isset($d['cobrado_usd']) ? '$' . number_format($d['cobrado_usd'], 2) : ''));
            $c3 = isset($d['total_bs']) ? 'Bs ' . number_format($d['total_bs'], 2, ',', '.') : (isset($d['inversion_total_bs']) ? 'Bs ' . number_format($d['inversion_total_bs'], 2, ',', '.') : (isset($d['cobrado_bs']) ? 'Bs ' . number_format($d['cobrado_bs'], 2, ',', '.') : ''));
            $c4 = $d['diagnostico'] ?? $d['estado_gerencial'] ?? $d['clasificacion'] ?? ($d['tasa_conversion'] ?? '') . '%' ?? '';

            $tableRowsHtml .= '
                <tr style="border-bottom: 1px solid #1f2d3d;">
                    <td style="padding: 8px 10px; color: #e6edf3; font-size: 12px;">' . htmlspecialchars((string) $c1) . '</td>
                    <td style="padding: 8px 10px; color: #35c029; font-size: 12px; text-align: right; font-weight: 600;">' . htmlspecialchars((string) $c2) . '</td>
                    <td style="padding: 8px 10px; color: #9bb0c4; font-size: 12px; text-align: right;">' . htmlspecialchars((string) $c3) . '</td>
                    <td style="padding: 8px 10px; color: #cbd5e1; font-size: 11px; text-align: right;">' . htmlspecialchars((string) $c4) . '</td>
                </tr>
            ';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . htmlspecialchars($rep['titulo']) . '</title>
        </head>
        <body style="font-family: \'Segoe UI\', Arial, sans-serif; background-color: #0b0f13; color: #e6edf3; margin: 0; padding: 25px;">
            <div style="max-width: 680px; margin: 0 auto; background-color: #12181f; border: 1px solid #1f2e3d; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5);">
                <!-- Header Institucional -->
                <div style="background: linear-gradient(135deg, #152219 0%, #0d151c 100%); padding: 25px; border-bottom: 2px solid #35c029;">
                    <h1 style="margin: 0; font-size: 20px; color: #35c029; letter-spacing: 0.5px;">LANEROS GAMER — CYBERPOS 2.0</h1>
                    <h2 style="margin: 6px 0 0; font-size: 16px; color: #ffffff; font-weight: 600;">' . htmlspecialchars($rep['titulo']) . '</h2>
                    <p style="margin: 8px 0 0; font-size: 12px; color: #9bb0c4;">
                        Período: <strong>' . htmlspecialchars($rep['fecha_desde']) . '</strong> al <strong>' . htmlspecialchars($rep['fecha_hasta']) . '</strong> &bull;
                        Filtro: <strong>' . htmlspecialchars($rep['subfiltro_label'] ?? 'Todos') . '</strong> &bull;
                        Tasa BCV: <strong>' . number_format($rep['tasa_bcv'], 2, ',', '.') . ' Bs/$</strong>
                    </p>
                    <p style="margin: 4px 0 0; font-size: 12px; color: #35c029;">
                        Responsable Emisión: <strong>' . htmlspecialchars($responsableStr) . '</strong>
                    </p>
                </div>

                <!-- KPIs -->
                <div style="padding: 20px;">
                    <h3 style="margin: 0 0 12px; font-size: 13px; color: #a4b3c6; text-transform: uppercase; letter-spacing: 0.5px;">Indicadores Clave de Desempeño (KPIs)</h3>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 6px;">
                        <tr>' . $kpisHtml . '</tr>
                    </table>

                    <!-- Recomendacion -->
                    <div style="margin-top: 18px; padding: 14px; background: rgba(53, 192, 41, 0.08); border-left: 4px solid #35c029; border-radius: 4px;">
                        <strong style="color: #35c029; font-size: 12px; display: block; margin-bottom: 4px;">RECOMENDACIÓN GERENCIAL:</strong>
                        <span style="font-size: 13px; color: #d4e0eb; line-height: 1.4;">' . htmlspecialchars($rep['recomendacion']) . '</span>
                    </div>

                    <!-- Muestra de Tabla Resumen -->
                    <h3 style="margin: 20px 0 10px; font-size: 13px; color: #a4b3c6; text-transform: uppercase; letter-spacing: 0.5px;">Resumen Ejecutivo de Auditoría</h3>
                    <table style="width: 100%; border-collapse: collapse; background: #0e141a; border-radius: 6px; overflow: hidden;">
                        <thead>
                            <tr style="background: #17222d; color: #35c029; font-size: 11px; text-transform: uppercase;">
                                <th style="padding: 8px 10px; text-align: left;">Elemento / Auditoría</th>
                                <th style="padding: 8px 10px; text-align: right;">Total USD</th>
                                <th style="padding: 8px 10px; text-align: right;">Total Bs (BCV)</th>
                                <th style="padding: 8px 10px; text-align: right;">Diagnóstico / Estado</th>
                            </tr>
                        </thead>
                        <tbody>' . $tableRowsHtml . '</tbody>
                    </table>

                    <p style="margin-top: 20px; font-size: 12px; color: #8fa0b5; line-height: 1.5;">
                        Se adjunta el reporte oficial completo con la tabla analítica detallada y fila final de totalización para su archivo y auditoría gerencial.
                    </p>
                </div>

                <!-- Footer -->
                <div style="background-color: #0d1217; padding: 14px 20px; text-align: center; border-top: 1px solid #1a2530; font-size: 11px; color: #64748b;">
                    Reporte generado automáticamente por <strong>CyberPOS 2.0 (Laneros Gamer)</strong> el ' . htmlspecialchars($rep['fecha_emision']) . '.
                </div>
            </div>
        </body>
        </html>
        ';
    }
}
