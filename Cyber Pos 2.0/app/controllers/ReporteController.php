<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - ReporteController
 * Controlador de reportes gerenciales para la toma de decisiones,
 * exportación autónoma a PDF (.pdf) y Excel (.xlsx), y despacho de correo en vivo (SMTP).
 */
class ReporteController extends Controller
{
    private function resolverReporte(string $tipo, ?string $desde, ?string $hasta): array
    {
        $modelo = new Reporte();
        switch ($tipo) {
            case '1':
            case 'rentabilidad_equipos':
                return $modelo->rentabilidadEquipos($desde, $hasta);

            case '2':
            case 'rotacion_inventario':
                return $modelo->rotacionInventario($desde, $hasta);

            case '3':
            case 'arqueo_caja':
                return $modelo->arqueoCaja($desde, $hasta);

            case '4':
            case 'fidelizacion_clientes':
                return $modelo->fidelizacionClientes($desde, $hasta);

            case '5':
            case 'eficiencia_operativa':
            default:
                return $modelo->eficienciaOperativa($desde, $hasta);
        }
    }

    /**
     * GET /reportes/datos?tipo=...&desde=...&hasta=...
     */
    public function datos(): void
    {
        try {
            $this->requireAdmin();
            $tipo  = (string) $this->input('tipo', '1');
            $desde = $this->input('desde');
            $hasta = $this->input('hasta');

            $res = $this->resolverReporte($tipo, $desde ? (string) $desde : null, $hasta ? (string) $hasta : null);
            $this->ok($res);
        } catch (\Throwable $e) {
            $this->fail('Error al generar vista previa del reporte: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /reportes/exportar?tipo=...&formato=(pdf|xlsx)&desde=...&hasta=...
     */
    public function exportar(): void
    {
        try {
            $this->requireAdmin();
            $tipo    = (string) $this->input('tipo', '1');
            $formato = strtolower((string) $this->input('formato', 'pdf'));
            $desde   = $this->input('desde');
            $hasta   = $this->input('hasta');

            $rep = $this->resolverReporte($tipo, $desde ? (string) $desde : null, $hasta ? (string) $hasta : null);
            $slug = preg_replace('/[^a-z0-9_-]/i', '_', $rep['reporte'] ?? 'reporte');
            $timestamp = date('Ymd_His');

            if ($formato === 'xlsx') {
                $this->generarExcel($rep, "{$slug}_{$timestamp}.xlsx");
            } else {
                $this->generarPdf($rep, "{$slug}_{$timestamp}.pdf");
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

            $toEmail = trim((string) ($b['to_email'] ?? ''));
            if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Debe proporcionar un correo electrónico destinatario válido.', 400);
            }

            $tipo    = (string) ($b['tipo'] ?? '1');
            $desde   = isset($b['desde']) ? (string) $b['desde'] : null;
            $hasta   = isset($b['hasta']) ? (string) $b['hasta'] : null;
            $subject = trim((string) ($b['subject'] ?? ''));

            $rep = $this->resolverReporte($tipo, $desde, $hasta);

            if ($subject === '') {
                $subject = "Reporte Gerencial: {$rep['titulo']} - CyberPOS Laneros Gamer";
            }

            // Generar PDF en memoria para adjuntar
            $pdfGen = $this->construirPdf($rep);
            $pdfBin = $pdfGen->output();
            $pdfName = ($rep['reporte'] ?? 'reporte') . '_' . date('Ymd') . '.pdf';

            // Construir cuerpo HTML
            $html = $this->construirHtmlCorreo($rep);

            // Configuración SMTP opcional provista en la petición
            $smtpConfig = [];
            if (!empty($b['smtp_host']))     $smtpConfig['host']     = (string) $b['smtp_host'];
            if (!empty($b['smtp_port']))     $smtpConfig['port']     = (int) $b['smtp_port'];
            if (!empty($b['smtp_user']))     $smtpConfig['username'] = (string) $b['smtp_user'];
            if (!empty($b['smtp_pass']))     $smtpConfig['password'] = (string) $b['smtp_pass'];
            if (!empty($b['smtp_from']))     $smtpConfig['from_email']= (string) $b['smtp_from'];

            $mailer = new SimpleMailer($smtpConfig);
            $res = $mailer->send($toEmail, $subject, $html, $pdfBin, $pdfName);

            if (!$res['success']) {
                $this->fail($res['error'], 500);
            }

            $this->ok($res, $res['message']);
        } catch (\Throwable $e) {
            $this->fail('Error en envío de correo: ' . $e->getMessage(), 500);
        }
    }

    private function construirPdf(array $rep): SimplePDF
    {
        $pdf = new SimplePDF('L', $rep['titulo'] ?? 'Reporte Gerencial');
        $sub = sprintf('Período auditado: %s al %s  |  Emisión: %s', $rep['fecha_desde'], $rep['fecha_hasta'], $rep['fecha_emision']);
        $pdf->drawHeader($rep['titulo'], $sub, $rep['fecha_emision'], (float) $rep['tasa_bcv']);
        $pdf->drawKPIs($rep['kpis'] ?? []);
        $pdf->drawRecommendation($rep['recomendacion'] ?? '');

        // Configurar columnas según el reporte
        $code = $rep['reporte'] ?? '';
        switch ($code) {
            case 'rentabilidad_equipos':
                $headers = ['Estación', 'Tipo', 'Tarifa/h', 'Sesiones', 'Horas', 'Ticket Med ($)', 'Ingreso USD', 'Ingreso Bs', '% Sala', 'Diagnóstico Gerencial'];
                $widths  = [100, 50, 50, 50, 50, 65, 65, 75, 45, 175];
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
                break;

            case 'rotacion_inventario':
                $headers = ['Producto', 'Categoría', 'Precio ($)', 'Stock Actual', 'Valor Stock ($)', 'Valor Stock (Bs)', 'Vendidas', 'Ingresos ($)', 'Ingresos (Bs)', 'Rotación', 'Estado Gerencial'];
                $widths  = [120, 60, 50, 50, 65, 70, 45, 60, 70, 45, 90];
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
                break;

            case 'arqueo_caja':
                $headers = ['Fecha Auditoría', 'Operaciones', 'Alquiler USD', 'Snacks/Bar USD', 'Total Recaudado USD', 'Total Equivalente Bs'];
                $widths  = [110, 80, 110, 110, 130, 140];
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
                break;

            case 'fidelizacion_clientes':
                $headers = ['Rnk', 'Cliente Gamer', 'Visitas', 'Horas Jugadas', 'Alquiler ($)', 'Snacks ($)', 'Inversión Total ($)', 'Inversión Total (Bs)', 'Ticket Prom ($)', '% Aporte', 'Segmento'];
                $widths  = [28, 120, 45, 60, 55, 55, 75, 80, 65, 50, 90];
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
                break;

            case 'eficiencia_operativa':
            default:
                $headers = ['Estación / Área', 'Gestiones Totales', 'Completadas', 'Canceladas', 'Pendientes', 'Efectividad (%)', 'Cobrado ($)', 'Cobrado (Bs)', 'Lucro Cesante ($)', 'Lucro Cesante (Bs)'];
                $widths  = [110, 65, 60, 55, 55, 65, 65, 75, 75, 80];
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
                break;
        }

        $pdf->drawTable($headers, $widths, $rows, $aligns);
        return $pdf;
    }

    private function generarPdf(array $rep, string $filename): void
    {
        $pdf = $this->construirPdf($rep);
        $pdf->download($filename);
    }

    private function generarExcel(array $rep, string $filename): void
    {
        $xlsx = new SimpleXLSXGen($rep['titulo']);
        $xlsx->setMeta(sprintf('Período: %s al %s | Tasa BCV: %.2f Bs/$ | Generado: %s', $rep['fecha_desde'], $rep['fecha_hasta'], $rep['tasa_bcv'], $rep['fecha_emision']));
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
                $totals = ['TOTALES SALA', '', '', '', $rep['totales']['sesiones'] ?? 0, $rep['totales']['total_horas'] ?? 0, '', '', $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0, 1.0, ''];
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
                $totals = ['TOTALES INVENTARIO', '', '', '', $rep['totales']['valor_stock_usd'] ?? 0, $rep['totales']['valor_stock_bs'] ?? 0, $rep['totales']['unidades_vendidas'] ?? 0, $rep['totales']['ingresos_usd'] ?? 0, $rep['totales']['ingresos_bs'] ?? 0, '', ''];
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
                $totals = ['TOTAL RECAUDADO', $rep['totales']['operaciones'] ?? 0, '', '', $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0];
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
                $totals = ['', 'TOTALES', $rep['totales']['visitas'] ?? 0, $rep['totales']['total_horas'] ?? 0, '', '', $rep['totales']['total_usd'] ?? 0, $rep['totales']['total_bs'] ?? 0, '', '', 1.0, ''];
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
                $totals = ['TOTALES SALA', $rep['totales']['total_gestiones'] ?? 0, $rep['totales']['completadas'] ?? 0, '', '', '', $rep['totales']['cobrado_usd'] ?? 0, $rep['totales']['cobrado_bs'] ?? 0, $rep['totales']['lucro_cesante_usd'] ?? 0, $rep['totales']['lucro_cesante_bs'] ?? 0];
                break;
        }

        $xlsx->setHeaders($headers, $types)
             ->setRows($rows)
             ->setTotals($totals)
             ->download($filename);
    }

    private function construirHtmlCorreo(array $rep): string
    {
        $kpisHtml = '';
        foreach ($rep['kpis'] ?? [] as $k) {
            $kpisHtml .= '
                <td style="padding: 10px; background: #16202a; border-radius: 6px; border: 1px solid #233140; min-width: 110px; text-align: center;">
                    <div style="font-size: 11px; color: #8fa0b5; text-transform: uppercase;">' . htmlspecialchars($k['label']) . '</div>
                    <div style="font-size: 16px; font-weight: bold; color: #35c029; margin-top: 4px;">' . htmlspecialchars($k['value']) . '</div>
                </td>
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
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #152219 0%, #0d151c 100%); padding: 25px; border-bottom: 2px solid #35c029;">
                    <h1 style="margin: 0; font-size: 20px; color: #35c029; letter-spacing: 0.5px;">CYBERPOS 2.0 &bull; LANEROS GAMER</h1>
                    <h2 style="margin: 6px 0 0; font-size: 16px; color: #ffffff; font-weight: 600;">' . htmlspecialchars($rep['titulo']) . '</h2>
                    <p style="margin: 8px 0 0; font-size: 12px; color: #9bb0c4;">
                        Período: <strong>' . htmlspecialchars($rep['fecha_desde']) . '</strong> al <strong>' . htmlspecialchars($rep['fecha_hasta']) . '</strong> &bull;
                        Tasa BCV: <strong>' . number_format($rep['tasa_bcv'], 2) . ' Bs/$</strong>
                    </p>
                </div>

                <!-- KPIs -->
                <div style="padding: 20px;">
                    <h3 style="margin: 0 0 12px; font-size: 13px; color: #a4b3c6; text-transform: uppercase; letter-spacing: 0.5px;">Indicadores Clave de Desempeño (KPIs)</h3>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 8px;">
                        <tr>' . $kpisHtml . '</tr>
                    </table>

                    <!-- Recomendacion -->
                    <div style="margin-top: 20px; padding: 14px; background: rgba(53, 192, 41, 0.08); border-left: 4px solid #35c029; border-radius: 4px;">
                        <strong style="color: #35c029; font-size: 13px; display: block; margin-bottom: 4px;">RECOMENDACIÓN GERENCIAL:</strong>
                        <span style="font-size: 13px; color: #d4e0eb; line-height: 1.4;">' . htmlspecialchars($rep['recomendacion']) . '</span>
                    </div>

                    <p style="margin-top: 25px; font-size: 13px; color: #8fa0b5; line-height: 1.5;">
                        Se adjunta el reporte formal completo en formato binario <strong>PDF</strong> con la tabla analítica detallada y desglose por estación para su archivo y auditoría gerencial.
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

