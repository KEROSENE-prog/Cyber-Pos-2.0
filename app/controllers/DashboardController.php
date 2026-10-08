<?php
/**
 * CyberPOS - Controlador Dashboard (KPIs).
 */

class DashboardController extends Controller
{
    public function kpis(): void
    {
        $this->requireAuth();
        $equipos = new Equipo();
        $lista   = $equipos->getAll();

        $pcsActivas     = 0;
        $consolasActivas = 0;
        foreach ($lista as $e) {
            if ($e['estado'] !== 'activa') {
                continue;
            }
            if ($e['tipo'] === 'consola') {
                $consolasActivas++;
            } else {
                $pcsActivas++;
            }
        }

        $solicitudes = new Solicitud();
        $pedidos     = new Pedido();
        $solPend = count(array_filter($solicitudes->getAll(), fn ($s) => in_array($s['estado'], ['pendiente', 'esperando_admin'], true)));
        $pedPend = count(array_filter($pedidos->getAll(), fn ($p) => $p['estado'] === 'pendiente'));

        $this->ok([
            'ingresos_hoy'         => (new SesionUso())->ingresosHoy(),
            'pcs_activas'          => $pcsActivas,
            'consolas_activas'     => $consolasActivas,
            'pedidos_pendientes'   => $pedPend,
            'solicitudes_pendientes' => $solPend,
        ]);
    }
}