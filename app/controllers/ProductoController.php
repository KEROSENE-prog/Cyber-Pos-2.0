<?php
/**
 * CyberPOS - Controlador Producto (snacks y personalizados).
 */

class ProductoController extends Controller
{
    private function filtros(): array
    {
        return [
            'categoria' => (string) $this->input('categoria', 'all'),
            'busqueda'  => (string) $this->input('busqueda', ''),
        ];
    }

    // ---- Snacks (catalogo estandar) ----
    public function snacksIndex(): void
    {
        $this->requireAdmin();
        $f = $this->filtros();
        $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'snacks'));
    }

    public function snacksCreate(): void
    {
        $this->requireAdmin();
        $res = (new Producto())->create($this->body(), 'snacks');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    // ---- Personalizados ----
    public function persIndex(): void
    {
        $this->requireAdmin();
        $f = $this->filtros();
        $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'personalizados'));
    }

    public function persCreate(): void
    {
        $this->requireAdmin();
        $res = (new Producto())->create($this->body(), 'personalizados');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    // ---- Comunes ----
    public function update($id): void
    {
        $this->requireAdmin();
        $res = (new Producto())->update((int) $id, $this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function destroy($id): void
    {
        $this->requireAdmin();
        $res = (new Producto())->delete((int) $id);
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    /** Listado unificado para POS y cliente. */
    public function index(): void
    {
        $this->requireAuth();
        $busqueda = (string) $this->input('busqueda', '');
        $this->ok((new Producto())->getAllUnificados($busqueda));
    }
}