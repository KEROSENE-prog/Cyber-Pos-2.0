<?php
/**
 * CyberPOS - Controlador Producto (snacks y personalizados).
 * Cero pantallas blancas / cero Error 500 crudo: try/catch global en todos los endpoints (Etapa 3).
 */

class ProductoController extends Controller
{
    private function filtros(): array
    {
        return [
            'categoria' => trim((string) $this->input('categoria', 'all')),
            'busqueda'  => trim((string) $this->input('busqueda', '')),
        ];
    }

    // ---- Snacks (catálogo estándar) ----
    public function snacksIndex(): void
    {
        try {
            $this->requireAdmin();
            $f = $this->filtros();
            $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'snacks'));
        } catch (\Throwable $e) {
            $this->fail('Error al obtener catálogo de snacks: ' . $e->getMessage(), 500);
        }
    }

    public function snacksCreate(): void
    {
        try {
            $this->requireAdmin();
            $res = (new Producto())->create($this->body(), 'snacks');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al crear snack: ' . $e->getMessage(), 500);
        }
    }

    // ---- Personalizados ----
    public function persIndex(): void
    {
        try {
            $this->requireAdmin();
            $f = $this->filtros();
            $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'personalizados'));
        } catch (\Throwable $e) {
            $this->fail('Error al obtener productos personalizados: ' . $e->getMessage(), 500);
        }
    }

    public function persCreate(): void
    {
        try {
            $this->requireAdmin();
            $res = (new Producto())->create($this->body(), 'personalizados');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al crear producto: ' . $e->getMessage(), 500);
        }
    }

    // ---- Comunes ----
    public function update($id): void
    {
        try {
            $this->requireAdmin();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de producto inválido.', 400);
            }
            $res = (new Producto())->update((int) $id, $this->body());
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al actualizar producto: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id): void
    {
        try {
            $this->requireAdmin();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de producto inválido.', 400);
            }
            $res = (new Producto())->delete((int) $id);
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al eliminar producto: ' . $e->getMessage(), 500);
        }
    }

    // ---- Catálogo unificado para cliente (snacks + personalizados) ----
    public function catalogoIndex(): void
    {
        try {
            $this->requireAuth();
            $f = $this->filtros();
            $snacks  = (new Producto())->getAll($f['categoria'], $f['busqueda'], 'snacks');
            $pers    = (new Producto())->getAll($f['categoria'], $f['busqueda'], 'personalizados');
            $todos   = array_merge($snacks, $pers);
            $this->ok($todos);
        } catch (\Throwable $e) {
            $this->fail('Error al obtener catálogo: ' . $e->getMessage(), 500);
        }
    }
}
