<?php
declare(strict_types=1);

/**
 * CyberPOS 2.0 - Modelo Reporte
 * Motor de reportes gerenciales con criterios de decisión, agregaciones SQL avanzadas,
 * conversión multimoneda (USD / Bs BCV), filtros por rango de fechas y subfiltros segmentados.
 */
class Reporte extends Model
{
    private function getTasaBcv(): float
    {
        try {
            $configModel = new Configuracion();
            $cfg = $configModel->get();
            return (float) ($cfg['tasa_bcv'] ?? 36.85);
        } catch (\Throwable $e) {
            return 36.85;
        }
    }

    private function normalizarFechas(?string $desde, ?string $hasta): array
    {
        $fechaHasta = ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) ? $hasta : date('Y-m-d');
        $fechaDesde = ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) ? $desde : date('Y-m-01', strtotime('-1 month'));
        if ($fechaDesde > $fechaHasta) {
            $temp = $fechaDesde;
            $fechaDesde = $fechaHasta;
            $fechaHasta = $temp;
        }
        return [$fechaDesde, $fechaHasta];
    }

    /**
     * Reporte 1: Rentabilidad, Ocupación y Rendimiento por Estación (PCs vs Consolas)
     * Subfiltro: 'todos', 'pc', 'consola'
     */
    public function rentabilidadEquipos(?string $desde = null, ?string $hasta = null, ?string $subfiltro = null): array
    {
        [$fechaDesde, $fechaHasta] = $this->normalizarFechas($desde, $hasta);
        $tasaBcv = $this->getTasaBcv();
        $subfiltro = ($subfiltro && in_array(strtolower($subfiltro), ['pc', 'consola'], true)) ? strtolower($subfiltro) : 'todos';

        $whereEquipo = "";
        $params = [':desde' => $fechaDesde, ':hasta' => $fechaHasta];
        if ($subfiltro !== 'todos') {
            $whereEquipo = "WHERE e.tipo = :tipo";
            $params[':tipo'] = $subfiltro;
        }

        $sql = "
            SELECT 
                e.id AS equipo_id,
                e.nombre_equipo,
                e.numero,
                e.tipo,
                e.tarifa_por_hora,
                COUNT(s.id) AS cantidad_sesiones,
                COALESCE(SUM(s.segundos_alquilados), 0) AS segundos_totales,
                ROUND(COALESCE(SUM(s.segundos_alquilados), 0) / 3600, 2) AS horas_totales,
                ROUND(COALESCE(SUM(s.monto_cobrado), 0), 2) AS total_usd
            FROM equipos e
            LEFT JOIN sesiones_uso s 
                ON s.equipo_id = e.id 
               AND s.tipo_registro = 'alquiler_tiempo'
               AND DATE(s.fecha_registro) >= :desde 
               AND DATE(s.fecha_registro) <= :hasta
            {$whereEquipo}
            GROUP BY e.id, e.nombre_equipo, e.numero, e.tipo, e.tarifa_por_hora
            ORDER BY total_usd DESC, horas_totales DESC, e.id ASC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $totalIngresoUsd = 0.0;
        $totalHorasSala  = 0.0;
        $totalSesiones   = 0;
        $usdPc           = 0.0;
        $usdConsola      = 0.0;

        foreach ($rows as $r) {
            $usd = (float) $r['total_usd'];
            $totalIngresoUsd += $usd;
            $totalHorasSala  += (float) $r['horas_totales'];
            $totalSesiones   += (int) $r['cantidad_sesiones'];
            if ($r['tipo'] === 'pc') {
                $usdPc += $usd;
            } else {
                $usdConsola += $usd;
            }
        }

        $items = [];
        $estacionTop = 'N/A';
        $maxUsd = -1.0;

        foreach ($rows as $r) {
            $usd = (float) $r['total_usd'];
            $horas = (float) $r['horas_totales'];
            $ses = (int) $r['cantidad_sesiones'];
            $tarifa = (float) $r['tarifa_por_hora'];
            $bs = round($usd * $tasaBcv, 2);
            $ticketUsd = $ses > 0 ? round($usd / $ses, 2) : 0.0;
            $ticketBs  = round($ticketUsd * $tasaBcv, 2);
            $porcentaje = $totalIngresoUsd > 0 ? round(($usd / $totalIngresoUsd) * 100, 1) : 0.0;

            if ($horas >= 12.0) {
                $diagnostico = 'ALTO RENDIMIENTO';
            } elseif ($horas >= 4.0) {
                $diagnostico = 'ESTÁNDAR';
            } else {
                $diagnostico = 'SUBUTILIZADO - Evaluar Tarifa/Mantenimiento';
            }

            if ($usd > $maxUsd && $ses > 0) {
                $maxUsd = $usd;
                $estacionTop = $r['nombre_equipo'];
            }

            $items[] = [
                'equipo_id'           => (int) $r['equipo_id'],
                'nombre_equipo'       => $r['nombre_equipo'],
                'numero'              => $r['numero'],
                'tipo'                => strtoupper($r['tipo']),
                'tarifa_usd'          => $tarifa,
                'cantidad_sesiones'   => $ses,
                'horas_totales'       => $horas,
                'ticket_promedio_usd' => $ticketUsd,
                'ticket_promedio_bs'  => $ticketBs,
                'total_usd'           => $usd,
                'total_bs'            => $bs,
                'porcentaje_ingreso'  => $porcentaje,
                'diagnostico'         => $diagnostico,
            ];
        }

        $subfiltroLabel = ($subfiltro === 'pc') ? 'Solo PCs' : (($subfiltro === 'consola') ? 'Solo Consolas' : 'Todas las Estaciones');
        $rendimientoTipo = ($usdPc >= $usdConsola)
            ? 'PCs lideran con ' . ($totalIngresoUsd > 0 ? round(($usdPc / $totalIngresoUsd) * 100, 1) : 0) . '% de ingresos'
            : 'Consolas lideran con ' . ($totalIngresoUsd > 0 ? round(($usdConsola / $totalIngresoUsd) * 100, 1) : 0) . '% de ingresos';

        $recomendacion = "Optimizar la rotación de las estaciones subutilizadas mediante promociones en horas valle o revisión de hardware. Las consolas mantienen un ticket promedio por hora superior a las PCs.";

        $ticketPromedioGeneral = $totalSesiones > 0 ? round($totalIngresoUsd / $totalSesiones, 2) : 0.0;

        return [
            'reporte'         => 'rentabilidad_equipos',
            'titulo'          => 'Rentabilidad, Ocupación y Rendimiento por Estación (PCs vs. Consolas)',
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'subfiltro'       => $subfiltro,
            'subfiltro_label' => $subfiltroLabel,
            'tasa_bcv'        => $tasaBcv,
            'fecha_emision'   => date('d/m/Y H:i:s'),
            'kpis'            => [
                ['label' => 'Ingreso Total Alquiler', 'value' => '$' . number_format($totalIngresoUsd, 2)],
                ['label' => 'Total Equivalente Bs',  'value' => 'Bs ' . number_format($totalIngresoUsd * $tasaBcv, 2, ',', '.')],
                ['label' => 'Horas Alquiladas Sala', 'value' => number_format($totalHorasSala, 1) . ' hrs'],
                ['label' => 'Sesiones Totales',      'value' => (string) $totalSesiones],
                ['label' => 'Estación Estrella',     'value' => $estacionTop],
                ['label' => 'Segmento Dominante',    'value' => $rendimientoTipo],
            ],
            'totales' => [
                'total_usd'           => round($totalIngresoUsd, 2),
                'total_bs'            => round($totalIngresoUsd * $tasaBcv, 2),
                'total_horas'         => round($totalHorasSala, 2),
                'sesiones'            => $totalSesiones,
                'ticket_promedio_usd' => $ticketPromedioGeneral,
                'ticket_promedio_bs'  => round($ticketPromedioGeneral * $tasaBcv, 2),
            ],
            'recomendacion'   => $recomendacion,
            'datos'           => $items,
        ];
    }

    /**
     * Reporte 2: Rotación de Inventario, Valoración de Stock y Alerta de Reposición Crítica
     * Subfiltro: 'todos', 'snacks' (estándar), 'personalizados' (elaborados)
     */
    public function rotacionInventario(?string $desde = null, ?string $hasta = null, ?string $subfiltro = null): array
    {
        [$fechaDesde, $fechaHasta] = $this->normalizarFechas($desde, $hasta);
        $tasaBcv = $this->getTasaBcv();
        $subfiltro = ($subfiltro && in_array(strtolower($subfiltro), ['snacks', 'estandar', 'personalizados', 'personalizado'], true)) ? strtolower($subfiltro) : 'todos';

        $whereProd = "";
        $paramsProd = [];
        if ($subfiltro === 'snacks' || $subfiltro === 'estandar') {
            $whereProd = "WHERE origen = 'snacks'";
            $subfiltro = 'snacks';
        } elseif ($subfiltro === 'personalizados' || $subfiltro === 'personalizado') {
            $whereProd = "WHERE origen = 'personalizados'";
            $subfiltro = 'personalizados';
        }

        $stProd = $this->db->prepare("
            SELECT id, nombre, categoria, precio, stock, origen
            FROM productos
            {$whereProd}
            ORDER BY stock ASC, nombre ASC
        ");
        $stProd->execute($paramsProd);
        $prods = $stProd->fetchAll(PDO::FETCH_ASSOC);

        // Ventas sesiones_uso
        $stSes = $this->db->prepare("
            SELECT detalle, monto_cobrado
            FROM sesiones_uso
            WHERE tipo_registro = 'venta_snack'
              AND DATE(fecha_registro) >= :desde 
              AND DATE(fecha_registro) <= :hasta
        ");
        $stSes->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $ventasSes = $stSes->fetchAll(PDO::FETCH_ASSOC);

        // Pedidos completados
        $stPed = $this->db->prepare("
            SELECT items, total
            FROM pedidos_snacks
            WHERE estado = 'completado'
              AND DATE(fecha) >= :desde 
              AND DATE(fecha) <= :hasta
        ");
        $stPed->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $pedidosComp = $stPed->fetchAll(PDO::FETCH_ASSOC);

        $ventasMap = [];
        foreach ($ventasSes as $v) {
            $items = json_decode((string) ($v['detalle'] ?? ''), true);
            if (is_array($items)) {
                foreach ($items as $it) {
                    $pid = (int) ($it['id'] ?? 0);
                    $cant = (int) ($it['cantidad'] ?? 0);
                    if ($pid > 0 && $cant > 0) {
                        $ventasMap[$pid] = ($ventasMap[$pid] ?? 0) + $cant;
                    }
                }
            }
        }

        foreach ($pedidosComp as $p) {
            $items = json_decode((string) ($p['items'] ?? ''), true);
            if (is_array($items)) {
                foreach ($items as $it) {
                    $pid = (int) ($it['id'] ?? 0);
                    $cant = (int) ($it['cantidad'] ?? 0);
                    if ($pid > 0 && $cant > 0) {
                        $ventasMap[$pid] = ($ventasMap[$pid] ?? 0) + $cant;
                    }
                }
            }
        }

        $items = [];
        $totalStock = 0;
        $totalValorStockUsd = 0.0;
        $totalVentaSnacksUsd = 0.0;
        $totalUnidadesVendidas = 0;
        $alertasCriticas = 0;
        $prodTopNombre = 'N/A';
        $prodTopUnidades = -1;

        foreach ($prods as $p) {
            $pid = (int) $p['id'];
            $stock = (int) $p['stock'];
            $precio = (float) $p['precio'];
            $vendidas = $ventasMap[$pid] ?? 0;

            $valorStockUsd = round($stock * $precio, 2);
            $valorStockBs  = round($valorStockUsd * $tasaBcv, 2);
            $ingresoUsd    = round($vendidas * $precio, 2);
            $ingresoBs     = round($ingresoUsd * $tasaBcv, 2);

            $totalStock           += $stock;
            $totalValorStockUsd   += $valorStockUsd;
            $totalVentaSnacksUsd  += $ingresoUsd;
            $totalUnidadesVendidas += $vendidas;

            $baseTotal = $stock + $vendidas;
            $rotacion = $baseTotal > 0 ? round(($vendidas / $baseTotal) * 100, 1) : 0.0;

            if ($stock <= 5) {
                $estado = 'CRÍTICO: Reponer Urgente';
                $alertasCriticas++;
            } elseif ($stock <= 12) {
                $estado = 'ALERTA: Stock Bajo';
            } elseif ($stock > 25 && $vendidas <= 2) {
                $estado = 'SOBRESTOCK / POCA SALIDA';
            } else {
                $estado = 'ÓPTIMO';
            }

            if ($vendidas > $prodTopUnidades && $vendidas > 0) {
                $prodTopUnidades = $vendidas;
                $prodTopNombre = $p['nombre'];
            }

            $items[] = [
                'id'                => $pid,
                'nombre'            => $p['nombre'],
                'categoria'         => ucfirst($p['categoria']),
                'origen'            => $p['origen'],
                'precio_usd'        => $precio,
                'stock_actual'      => $stock,
                'valor_stock_usd'   => $valorStockUsd,
                'valor_stock_bs'    => $valorStockBs,
                'unidades_vendidas' => $vendidas,
                'ingresos_usd'      => $ingresoUsd,
                'ingresos_bs'       => $ingresoBs,
                'indice_rotacion'   => $rotacion,
                'estado_gerencial'  => $estado,
            ];
        }

        $subfiltroLabel = ($subfiltro === 'snacks') ? 'Snacks / Bebidas Estándar' : (($subfiltro === 'personalizados') ? 'Elaborados / Personalizados' : 'Todos los Artículos');
        $recomendacion = "Emitir de inmediato orden de compra para los productos en estado CRÍTICO (stock <= 5). Aplicar combos promocionales (tiempo de juego + snack) sobre artículos con sobrestock para liberar capital de trabajo.";

        return [
            'reporte'         => 'rotacion_inventario',
            'titulo'          => 'Rotación de Inventario, Valoración de Stock y Alerta de Reposición Crítica',
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'subfiltro'       => $subfiltro,
            'subfiltro_label' => $subfiltroLabel,
            'tasa_bcv'        => $tasaBcv,
            'fecha_emision'   => date('d/m/Y H:i:s'),
            'kpis'            => [
                ['label' => 'Valor Inventario USD',   'value' => '$' . number_format($totalValorStockUsd, 2)],
                ['label' => 'Valor Inventario Bs',    'value' => 'Bs ' . number_format($totalValorStockUsd * $tasaBcv, 2, ',', '.')],
                ['label' => 'Ventas Realizadas USD',  'value' => '$' . number_format($totalVentaSnacksUsd, 2)],
                ['label' => 'Unidades Vendidas',      'value' => (string) $totalUnidadesVendidas . ' unds'],
                ['label' => 'Alertas Críticas Stock', 'value' => (string) $alertasCriticas . ' productos'],
                ['label' => 'Producto Más Vendido',   'value' => $prodTopNombre],
            ],
            'totales' => [
                'stock_actual'      => $totalStock,
                'valor_stock_usd'   => round($totalValorStockUsd, 2),
                'valor_stock_bs'    => round($totalValorStockUsd * $tasaBcv, 2),
                'unidades_vendidas' => $totalUnidadesVendidas,
                'ingresos_usd'      => round($totalVentaSnacksUsd, 2),
                'ingresos_bs'       => round($totalVentaSnacksUsd * $tasaBcv, 2),
            ],
            'recomendacion'   => $recomendacion,
            'datos'           => $items,
        ];
    }

    /**
     * Reporte 3: Cierre y Arqueo Gerencial Multimoneda (USD / Bs BCV) por Flujo de Ingreso
     * Subfiltro: 'todos', 'alquiler_tiempo', 'venta_snack'
     */
    public function arqueoCaja(?string $desde = null, ?string $hasta = null, ?string $subfiltro = null): array
    {
        [$fechaDesde, $fechaHasta] = $this->normalizarFechas($desde, $hasta);
        $tasaBcv = $this->getTasaBcv();
        $subfiltro = ($subfiltro && in_array(strtolower($subfiltro), ['alquiler_tiempo', 'venta_snack', 'alquiler', 'snacks'], true)) ? strtolower($subfiltro) : 'todos';
        if ($subfiltro === 'alquiler') $subfiltro = 'alquiler_tiempo';
        if ($subfiltro === 'snacks')   $subfiltro = 'venta_snack';

        $whereFlujo = "";
        $params = [':desde' => $fechaDesde, ':hasta' => $fechaHasta];
        if ($subfiltro !== 'todos') {
            $whereFlujo = "AND tipo_registro = :tipo_flujo";
            $params[':tipo_flujo'] = $subfilujo = $subfiltro;
        }

        $sqlFlujos = "
            SELECT 
                tipo_registro,
                COUNT(*) AS total_operaciones,
                COALESCE(SUM(monto_cobrado), 0) AS total_usd
            FROM sesiones_uso
            WHERE DATE(fecha_registro) >= :desde 
              AND DATE(fecha_registro) <= :hasta
              {$whereFlujo}
            GROUP BY tipo_registro
        ";
        $st = $this->db->prepare($sqlFlujos);
        $st->execute($params);
        $rowsFlujos = $st->fetchAll(PDO::FETCH_ASSOC);

        // Hora pico
        $sqlHoras = "
            SELECT 
                HOUR(fecha_registro) AS hora,
                COUNT(*) AS transacciones,
                COALESCE(SUM(monto_cobrado), 0) AS monto_hora
            FROM sesiones_uso
            WHERE DATE(fecha_registro) >= :desde 
              AND DATE(fecha_registro) <= :hasta
              {$whereFlujo}
            GROUP BY HOUR(fecha_registro)
            ORDER BY monto_hora DESC, transacciones DESC
            LIMIT 1
        ";
        $stH = $this->db->prepare($sqlHoras);
        $stH->execute($params);
        $horaPicoRow = $stH->fetch(PDO::FETCH_ASSOC);

        $horaPicoStr = $horaPicoRow ? sprintf('%02d:00 - %02d:00 hrs ($%.2f)', $horaPicoRow['hora'], $horaPicoRow['hora'] + 1, $horaPicoRow['monto_hora']) : 'Sin registros';

        // Detalle por día
        $sqlDiario = "
            SELECT 
                DATE(fecha_registro) AS fecha,
                COUNT(*) AS operaciones,
                ROUND(COALESCE(SUM(CASE WHEN tipo_registro = 'alquiler_tiempo' THEN monto_cobrado ELSE 0 END), 0), 2) AS alquiler_usd,
                ROUND(COALESCE(SUM(CASE WHEN tipo_registro = 'venta_snack' THEN monto_cobrado ELSE 0 END), 0), 2) AS snacks_usd,
                ROUND(COALESCE(SUM(monto_cobrado), 0), 2) AS total_dia_usd
            FROM sesiones_uso
            WHERE DATE(fecha_registro) >= :desde 
              AND DATE(fecha_registro) <= :hasta
              {$whereFlujo}
            GROUP BY DATE(fecha_registro)
            ORDER BY fecha DESC
        ";
        $stD = $this->db->prepare($sqlDiario);
        $stD->execute($params);
        $diasRows = $stD->fetchAll(PDO::FETCH_ASSOC);

        $granTotalUsd = 0.0;
        $totalAlquilerUsd = 0.0;
        $totalSnacksUsd   = 0.0;
        $totalOperaciones = 0;
        $flujosResumen = [
            'alquiler_tiempo' => ['operaciones' => 0, 'total_usd' => 0.0],
            'venta_snack'     => ['operaciones' => 0, 'total_usd' => 0.0],
        ];

        foreach ($rowsFlujos as $rf) {
            $t = $rf['tipo_registro'];
            $flujosResumen[$t] = [
                'operaciones' => (int) $rf['total_operaciones'],
                'total_usd'   => (float) $rf['total_usd'],
            ];
            $granTotalUsd     += (float) $rf['total_usd'];
            $totalOperaciones += (int) $rf['total_operaciones'];
            if ($t === 'alquiler_tiempo') $totalAlquilerUsd += (float) $rf['total_usd'];
            if ($t === 'venta_snack')     $totalSnacksUsd   += (float) $rf['total_usd'];
        }

        $baseImponibleUsd = round($granTotalUsd / 1.16, 2);
        $ivaFiscalUsd     = round($granTotalUsd - $baseImponibleUsd, 2);
        $granTotalBs      = round($granTotalUsd * $tasaBcv, 2);
        $ticketPromedioUsd = $totalOperaciones > 0 ? round($granTotalUsd / $totalOperaciones, 2) : 0.0;

        $items = [];
        foreach ($diasRows as $d) {
            $diaUsd = (float) $d['total_dia_usd'];
            $diaBs  = round($diaUsd * $tasaBcv, 2);
            $items[] = [
                'fecha'        => $d['fecha'],
                'operaciones'  => (int) $d['operaciones'],
                'alquiler_usd' => (float) $d['alquiler_usd'],
                'snacks_usd'   => (float) $d['snacks_usd'],
                'total_usd'    => $diaUsd,
                'total_bs'     => $diaBs,
            ];
        }

        $subfiltroLabel = ($subfiltro === 'alquiler_tiempo') ? 'Solo Alquiler de Estaciones' : (($subfiltro === 'venta_snack') ? 'Solo Venta de Snacks/Bar' : 'Todos los Flujos de Caja');
        $recomendacion = "El cierre de caja refleja un arqueo multimoneda equilibrado. Se recomienda custodiar el efectivo en divisas y conciliar diariamente los pagos en Bolívares contra la tasa BCV del día para prevenir desfases cambiarios.";

        return [
            'reporte'         => 'arqueo_caja',
            'titulo'          => 'Cierre y Arqueo Gerencial Multimoneda (USD / Bs BCV) por Flujo de Ingreso',
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'subfiltro'       => $subfiltro,
            'subfiltro_label' => $subfiltroLabel,
            'tasa_bcv'        => $tasaBcv,
            'fecha_emision'   => date('d/m/Y H:i:s'),
            'kpis'            => [
                ['label' => 'Total Recaudado USD',    'value' => '$' . number_format($granTotalUsd, 2)],
                ['label' => 'Total Equivalente Bs',   'value' => 'Bs ' . number_format($granTotalBs, 2, ',', '.')],
                ['label' => 'Base Imponible (84%)',   'value' => '$' . number_format($baseImponibleUsd, 2)],
                ['label' => 'IVA Estimado (16%)',     'value' => '$' . number_format($ivaFiscalUsd, 2)],
                ['label' => 'Ticket Promedio General', 'value' => '$' . number_format($ticketPromedioUsd, 2)],
                ['label' => 'Hora Pico de Caja',      'value' => $horaPicoStr],
            ],
            'totales' => [
                'operaciones'    => $totalOperaciones,
                'alquiler_usd'   => round($totalAlquilerUsd, 2),
                'snacks_usd'     => round($totalSnacksUsd, 2),
                'base_imponible' => $baseImponibleUsd,
                'iva'            => $ivaFiscalUsd,
                'total_usd'      => round($granTotalUsd, 2),
                'total_bs'       => $granTotalBs,
            ],
            'recomendacion'   => $recomendacion,
            'datos'           => $items,
        ];
    }

    /**
     * Reporte 4: Ranking Pareto de Clientes VIP y Hábitos de Consumo
     * Subfiltro: 'todos', 'vip', 'frecuente', 'ocasional'
     */
    public function fidelizacionClientes(?string $desde = null, ?string $hasta = null, ?string $subfiltro = null): array
    {
        [$fechaDesde, $fechaHasta] = $this->normalizarFechas($desde, $hasta);
        $tasaBcv = $this->getTasaBcv();
        $subfiltro = ($subfiltro && in_array(strtolower($subfiltro), ['vip', 'frecuente', 'ocasional'], true)) ? strtolower($subfiltro) : 'todos';

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(cliente), ''), 'Cliente Mostrador') AS cliente_nombre,
                COUNT(*) AS total_visitas,
                ROUND(COALESCE(SUM(CASE WHEN tipo_registro = 'alquiler_tiempo' THEN segundos_alquilados ELSE 0 END), 0) / 3600, 2) AS horas_jugadas,
                ROUND(COALESCE(SUM(CASE WHEN tipo_registro = 'alquiler_tiempo' THEN monto_cobrado ELSE 0 END), 0), 2) AS gasto_alquiler_usd,
                ROUND(COALESCE(SUM(CASE WHEN tipo_registro = 'venta_snack' THEN monto_cobrado ELSE 0 END), 0), 2) AS gasto_snacks_usd,
                ROUND(COALESCE(SUM(monto_cobrado), 0), 2) AS total_inversion_usd
            FROM sesiones_uso
            WHERE DATE(fecha_registro) >= :desde 
              AND DATE(fecha_registro) <= :hasta
            GROUP BY cliente_nombre
            ORDER BY total_inversion_usd DESC, total_visitas DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $granTotalInversionUsd = 0.0;
        $totalVisitas = 0;
        $totalHoras   = 0.0;
        $totalGastoAlquiler = 0.0;
        $totalGastoSnacks   = 0.0;

        foreach ($rows as $r) {
            $granTotalInversionUsd += (float) $r['total_inversion_usd'];
            $totalVisitas += (int) $r['total_visitas'];
            $totalHoras   += (float) $r['horas_jugadas'];
            $totalGastoAlquiler += (float) $r['gasto_alquiler_usd'];
            $totalGastoSnacks   += (float) $r['gasto_snacks_usd'];
        }

        $items = [];
        $paretoTop20IngresoUsd = 0.0;
        $clienteTopNombre = 'N/A';
        $topCount = max(1, (int) ceil(count($rows) * 0.2));

        $filteredRows = [];
        foreach ($rows as $idx => $r) {
            $usd = (float) $r['total_inversion_usd'];
            if ($usd >= 25.00) {
                $clasificacion = 'VIP DIAMANTE';
                $clave = 'vip';
            } elseif ($usd >= 10.00) {
                $clasificacion = 'CLIENTE FRECUENTE';
                $clave = 'frecuente';
            } else {
                $clasificacion = 'OCASIONAL';
                $clave = 'ocasional';
            }

            if ($subfiltro !== 'todos' && $subfiltro !== $clave) {
                continue;
            }

            $filteredRows[] = array_merge($r, ['clasificacion' => $clasificacion]);
        }

        foreach ($filteredRows as $idx => $r) {
            $usd = (float) $r['total_inversion_usd'];
            $visitas = (int) $r['total_visitas'];
            $horas   = (float) $r['horas_jugadas'];
            $bs = round($usd * $tasaBcv, 2);
            $ticketUsd = $visitas > 0 ? round($usd / $visitas, 2) : 0.0;
            $ticketBs  = round($ticketUsd * $tasaBcv, 2);
            $aporte = $granTotalInversionUsd > 0 ? round(($usd / $granTotalInversionUsd) * 100, 1) : 0.0;

            if ($idx === 0) {
                $clienteTopNombre = $r['cliente_nombre'];
            }
            if ($idx < $topCount) {
                $paretoTop20IngresoUsd += $usd;
            }

            $items[] = [
                'posicion'            => $idx + 1,
                'cliente'             => $r['cliente_nombre'],
                'visitas'             => $visitas,
                'horas_jugadas'       => $horas,
                'gasto_alquiler_usd'  => (float) $r['gasto_alquiler_usd'],
                'gasto_snacks_usd'    => (float) $r['gasto_snacks_usd'],
                'inversion_total_usd' => $usd,
                'inversion_total_bs'  => $bs,
                'ticket_promedio_usd' => $ticketUsd,
                'ticket_promedio_bs'  => $ticketBs,
                'porcentaje_aporte'   => $aporte,
                'clasificacion'       => $r['clasificacion'],
            ];
        }

        $subfiltroLabel = ($subfiltro === 'vip') ? 'Solo VIP Diamante (>= $25)' : (($subfiltro === 'frecuente') ? 'Solo Frecuentes ($10 - $25)' : (($subfiltro === 'ocasional') ? 'Solo Ocasionales (< $10)' : 'Todos los Segmentos'));
        $paretoPorcentaje = $granTotalInversionUsd > 0 ? round(($paretoTop20IngresoUsd / $granTotalInversionUsd) * 100, 1) : 0.0;
        $promedioGastoCliente = count($items) > 0 ? round($granTotalInversionUsd / count($items), 2) : 0.0;

        $recomendacion = "El 20% de clientes VIP aporta el {$paretoPorcentaje}% de los ingresos totales (Regla de Pareto). Diseñar pases mensuales premium y recompensas de tiempo acumulado para retener al segmento VIP DIAMANTE.";

        return [
            'reporte'         => 'fidelizacion_clientes',
            'titulo'          => 'Ranking Pareto de Clientes VIP y Hábitos de Consumo',
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'subfiltro'       => $subfiltro,
            'subfiltro_label' => $subfiltroLabel,
            'tasa_bcv'        => $tasaBcv,
            'fecha_emision'   => date('d/m/Y H:i:s'),
            'kpis'            => [
                ['label' => 'Total Invertido USD',   'value' => '$' . number_format($granTotalInversionUsd, 2)],
                ['label' => 'Total Invertido Bs',    'value' => 'Bs ' . number_format($granTotalInversionUsd * $tasaBcv, 2, ',', '.')],
                ['label' => 'Clientes Segmentados',  'value' => (string) count($items)],
                ['label' => 'Cliente Top N° 1',      'value' => $clienteTopNombre],
                ['label' => 'Gasto Promedio/Cliente', 'value' => '$' . number_format($promedioGastoCliente, 2)],
                ['label' => 'Concentración Pareto',  'value' => $paretoPorcentaje . '% en Top Clientes'],
            ],
            'totales' => [
                'total_usd'          => round($granTotalInversionUsd, 2),
                'total_bs'           => round($granTotalInversionUsd * $tasaBcv, 2),
                'total_horas'        => round($totalHoras, 2),
                'visitas'            => $totalVisitas,
                'gasto_alquiler_usd' => round($totalGastoAlquiler, 2),
                'gasto_snacks_usd'   => round($totalGastoSnacks, 2),
            ],
            'recomendacion'   => $recomendacion,
            'datos'           => $items,
        ];
    }

    /**
     * Reporte 5: Eficiencia Operativa de Sala y Conversión de Solicitudes/Pedidos
     * Subfiltro: 'todos', 'pc', 'consola'
     */
    public function eficienciaOperativa(?string $desde = null, ?string $hasta = null, ?string $subfiltro = null): array
    {
        [$fechaDesde, $fechaHasta] = $this->normalizarFechas($desde, $hasta);
        $tasaBcv = $this->getTasaBcv();
        $subfiltro = ($subfiltro && in_array(strtolower($subfiltro), ['pc', 'consola'], true)) ? strtolower($subfiltro) : 'todos';

        // Solicitudes
        $sqlSol = "
            SELECT 
                COALESCE(NULLIF(TRIM(e.nombre_equipo), ''), 'Mostrador') AS equipo,
                e.tipo AS equipo_tipo,
                COUNT(*) AS total_solicitudes,
                COALESCE(SUM(CASE WHEN s.estado = 'completada' THEN 1 ELSE 0 END), 0) AS completadas,
                COALESCE(SUM(CASE WHEN s.estado = 'cancelada' THEN 1 ELSE 0 END), 0) AS canceladas,
                COALESCE(SUM(CASE WHEN s.estado IN ('pendiente', 'esperando_admin', 'esperando_cliente') THEN 1 ELSE 0 END), 0) AS pendientes,
                COALESCE(SUM(CASE WHEN s.estado = 'completada' THEN s.monto ELSE 0 END), 0) AS cobrado_usd,
                COALESCE(SUM(CASE WHEN s.estado = 'cancelada' THEN s.monto ELSE 0 END), 0) AS lucro_cesante_usd
            FROM solicitudes_tiempo s
            LEFT JOIN equipos e ON e.id = s.equipo_id
            WHERE DATE(s.fecha) >= :desde AND DATE(s.fecha) <= :hasta
            GROUP BY equipo, e.tipo
        ";
        $stSol = $this->db->prepare($sqlSol);
        $stSol->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $solRows = $stSol->fetchAll(PDO::FETCH_ASSOC);

        // Pedidos
        $sqlPed = "
            SELECT 
                COALESCE(NULLIF(TRIM(p.equipo_nombre), ''), 'Mostrador') AS equipo,
                e.tipo AS equipo_tipo,
                COUNT(*) AS total_pedidos,
                COALESCE(SUM(CASE WHEN p.estado = 'completado' THEN 1 ELSE 0 END), 0) AS completados,
                COALESCE(SUM(CASE WHEN p.estado = 'cancelada' OR p.estado = 'cancelado' THEN 1 ELSE 0 END), 0) AS cancelados,
                COALESCE(SUM(CASE WHEN p.estado = 'pendiente' THEN 1 ELSE 0 END), 0) AS pendientes,
                COALESCE(SUM(CASE WHEN p.estado = 'completado' THEN p.total ELSE 0 END), 0) AS cobrado_usd,
                COALESCE(SUM(CASE WHEN p.estado = 'cancelada' OR p.estado = 'cancelado' THEN p.total ELSE 0 END), 0) AS lucro_cesante_usd
            FROM pedidos_snacks p
            LEFT JOIN equipos e ON e.id = p.equipo_id
            WHERE DATE(p.fecha) >= :desde AND DATE(p.fecha) <= :hasta
            GROUP BY equipo, e.tipo
        ";
        $stPed = $this->db->prepare($sqlPed);
        $stPed->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $pedRows = $stPed->fetchAll(PDO::FETCH_ASSOC);

        // Hora pico
        $sqlHora = "
            SELECT hora, SUM(cantidad) AS volumen FROM (
                SELECT HOUR(fecha) AS hora, COUNT(*) AS cantidad FROM solicitudes_tiempo WHERE DATE(fecha) >= :d1 AND DATE(fecha) <= :h1 GROUP BY HOUR(fecha)
                UNION ALL
                SELECT HOUR(fecha) AS hora, COUNT(*) AS cantidad FROM pedidos_snacks WHERE DATE(fecha) >= :d2 AND DATE(fecha) <= :h2 GROUP BY HOUR(fecha)
            ) AS combined
            GROUP BY hora
            ORDER BY volumen DESC
            LIMIT 1
        ";
        $stH = $this->db->prepare($sqlHora);
        $stH->execute([':d1' => $fechaDesde, ':h1' => $fechaHasta, ':d2' => $fechaDesde, ':h2' => $fechaHasta]);
        $horaCongestion = $stH->fetch(PDO::FETCH_ASSOC);
        $franjaPico = $horaCongestion ? sprintf('%02d:00 - %02d:00 hrs (%d solicitudes/pedidos)', $horaCongestion['hora'], $horaCongestion['hora'] + 1, $horaCongestion['volumen']) : 'Sin registros';

        $equiposMap = [];
        foreach ($solRows as $s) {
            $eq = $s['equipo'];
            $tipo = strtolower((string) ($s['equipo_tipo'] ?? ''));
            if ($subfiltro !== 'todos' && $tipo !== '' && $tipo !== $subfiltro) {
                continue;
            }

            $equiposMap[$eq] = [
                'equipo'                  => $eq,
                'solicitudes_total'       => (int) $s['total_solicitudes'],
                'solicitudes_completadas' => (int) $s['completadas'],
                'solicitudes_canceladas'  => (int) $s['canceladas'],
                'solicitudes_pendientes'  => (int) $s['pendientes'],
                'pedidos_total'           => 0,
                'pedidos_completados'     => 0,
                'pedidos_cancelados'      => 0,
                'pedidos_pendientes'      => 0,
                'cobrado_usd'             => (float) $s['cobrado_usd'],
                'lucro_cesante_usd'       => (float) $s['lucro_cesante_usd'],
            ];
        }

        foreach ($pedRows as $p) {
            $eq = $p['equipo'];
            $tipo = strtolower((string) ($p['equipo_tipo'] ?? ''));
            if ($subfiltro !== 'todos' && $tipo !== '' && $tipo !== $subfiltro) {
                continue;
            }

            if (!isset($equiposMap[$eq])) {
                $equiposMap[$eq] = [
                    'equipo'                  => $eq,
                    'solicitudes_total'       => 0,
                    'solicitudes_completadas' => 0,
                    'solicitudes_canceladas'  => 0,
                    'solicitudes_pendientes'  => 0,
                    'pedidos_total'           => 0,
                    'pedidos_completados'     => 0,
                    'pedidos_cancelados'      => 0,
                    'pedidos_pendientes'      => 0,
                    'cobrado_usd'             => 0.0,
                    'lucro_cesante_usd'       => 0.0,
                ];
            }
            $equiposMap[$eq]['pedidos_total']       += (int) $p['total_pedidos'];
            $equiposMap[$eq]['pedidos_completados'] += (int) $p['completados'];
            $equiposMap[$eq]['pedidos_cancelados']  += (int) $p['cancelados'];
            $equiposMap[$eq]['pedidos_pendientes']  += (int) $p['pendientes'];
            $equiposMap[$eq]['cobrado_usd']         += (float) $p['cobrado_usd'];
            $equiposMap[$eq]['lucro_cesante_usd']   += (float) $p['lucro_cesante_usd'];
        }

        $items = [];
        $totalGestionado = 0;
        $totalAtendido   = 0;
        $totalCanceladas = 0;
        $totalPendientes = 0;
        $totalCobradoUsd = 0.0;
        $totalLucroCesanteUsd = 0.0;
        $estacionTopEfic = 'N/A';
        $maxEfic = -1.0;

        foreach ($equiposMap as $eq => $d) {
            $tot = $d['solicitudes_total'] + $d['pedidos_total'];
            $ate = $d['solicitudes_completadas'] + $d['pedidos_completados'];
            $can = $d['solicitudes_canceladas'] + $d['pedidos_cancelados'];
            $pen = $d['solicitudes_pendientes'] + $d['pedidos_pendientes'];

            $conversion = $tot > 0 ? round(($ate / $tot) * 100, 1) : 0.0;
            $cobradoUsd = round($d['cobrado_usd'], 2);
            $cobradoBs  = round($cobradoUsd * $tasaBcv, 2);
            $lucroUsd   = round($d['lucro_cesante_usd'], 2);
            $lucroBs    = round($lucroUsd * $tasaBcv, 2);

            $totalGestionado      += $tot;
            $totalAtendido        += $ate;
            $totalCanceladas      += $can;
            $totalPendientes      += $pen;
            $totalCobradoUsd      += $cobradoUsd;
            $totalLucroCesanteUsd += $lucroUsd;

            if ($conversion > $maxEfic && $tot >= 2) {
                $maxEfic = $conversion;
                $estacionTopEfic = $eq;
            }

            $items[] = [
                'equipo'            => $eq,
                'total_gestiones'   => $tot,
                'completadas'       => $ate,
                'canceladas'        => $can,
                'pendientes'        => $pen,
                'tasa_conversion'   => $conversion,
                'cobrado_usd'       => $cobradoUsd,
                'cobrado_bs'        => $cobradoBs,
                'lucro_cesante_usd' => $lucroUsd,
                'lucro_cesante_bs'  => $lucroBs,
            ];
        }

        usort($items, fn($a, $b) => $b['tasa_conversion'] <=> $a['tasa_conversion']);

        $subfiltroLabel = ($subfiltro === 'pc') ? 'Solo Estaciones PC' : (($subfiltro === 'consola') ? 'Solo Estaciones Consola' : 'Toda la Sala');
        $tasaConversionGlobal = $totalGestionado > 0 ? round(($totalAtendido / $totalGestionado) * 100, 1) : 0.0;
        $recomendacion = "El lucro cesante de $" . number_format($totalLucroCesanteUsd, 2) . " se debe primordialmente a cancelaciones durante la franja pico ({$franjaPico}). Se recomienda reforzar la atención en mostrador y validar inventario para evitar cancelaciones de pedidos.";

        return [
            'reporte'         => 'eficiencia_operativa',
            'titulo'          => 'Eficiencia Operativa de Sala y Conversión de Solicitudes/Pedidos',
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'subfiltro'       => $subfiltro,
            'subfiltro_label' => $subfiltroLabel,
            'tasa_bcv'        => $tasaBcv,
            'fecha_emision'   => date('d/m/Y H:i:s'),
            'kpis'            => [
                ['label' => 'Tasa Conversión Sala',   'value' => $tasaConversionGlobal . '%'],
                ['label' => 'Efectivo Cobrado USD',   'value' => '$' . number_format($totalCobradoUsd, 2)],
                ['label' => 'Lucro Cesante Total USD','value' => '$' . number_format($totalLucroCesanteUsd, 2)],
                ['label' => 'Lucro Cesante en Bs',    'value' => 'Bs ' . number_format($totalLucroCesanteUsd * $tasaBcv, 2, ',', '.')],
                ['label' => 'Estación Más Eficiente', 'value' => $estacionTopEfic],
                ['label' => 'Franja Horaria Crítica', 'value' => $franjaPico],
            ],
            'totales' => [
                'total_gestiones'   => $totalGestionado,
                'completadas'       => $totalAtendido,
                'canceladas'        => $totalCanceladas,
                'pendientes'        => $totalPendientes,
                'tasa_conversion'   => $tasaConversionGlobal,
                'cobrado_usd'       => round($totalCobradoUsd, 2),
                'cobrado_bs'        => round($totalCobradoUsd * $tasaBcv, 2),
                'lucro_cesante_usd' => round($totalLucroCesanteUsd, 2),
                'lucro_cesante_bs'  => round($totalLucroCesanteUsd * $tasaBcv, 2),
            ],
            'recomendacion'   => $recomendacion,
            'datos'           => $items,
        ];
    }
}
