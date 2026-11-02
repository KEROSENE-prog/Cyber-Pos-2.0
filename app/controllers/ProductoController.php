<?php
/**
 * CyberPOS - Controlador Producto (snacks y personalizados).
 * Cero pantallas blancas: cada endpoint responde JSON controlado ante fallos.
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

    // ---- Snacks (catalogo estandar) ----
    public function snacksIndex(): void
    {
        try {
            $this->requireAdmin();
            $f = $this->filtros();
            $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'snacks'));
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudieron obtener los snacks.');
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
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo crear el snack.');
        }
    }

    // ---- Personalizados ----
    public function persIndex(): void
    {
        try {
            $this->requireAdmin();
            $f = $this->filtros();
            $this->ok((new Producto())->getAll($f['categoria'], $f['busqueda'], 'personalizados'));
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo obtener el catálogo personalizado.');
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
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo crear el producto personalizado.');
        }
    }

    // ---- Comunes ----
    public function update($id): void
    {
        try {
            $this->requireAdmin();
            $id  = $this->idValido($id);
            $res = (new Producto())->update($id, $this->body());
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo actualizar el producto.');
        }
    }

    public function destroy($id): void
    {
        try {
            $this->requireAdmin();
            $id  = $this->idValido($id);
            $res = (new Producto())->delete($id);
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo eliminar el producto.');
        }
    }

    /** Listado unificado para POS y cliente. */
    public function index(): void
    {
        try {
            $this->requireAuth();
            $busqueda = trim((string) $this->input('busqueda', ''));
            $this->ok((new Producto())->getAllUnificados($busqueda));
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo obtener el catálogo.');
        }
    }

    private function idValido($id): int
    {
        $error = Validador::errorEnteroPositivo($id, 'identificador del producto');
        if ($error !== null) {
            $this->fail($error, 400);
        }
        return (int) $id;
    }
}
