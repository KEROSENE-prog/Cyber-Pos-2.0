<?php
/**
 * CyberPOS - Modelo Equipo (PC / Consola).
 * El tiempo transcurrido se calcula en el servidor a partir de fecha_inicio.
 */

class Equipo extends Model
{
    private function cast(array $e): array
    {
        $e['id']                  = (int) $e['id'];
        $e['tiempo_transcurrido'] = (int) $e['tiempo_transcurrido'];
        $e['tiempo_limite']       = (int) $e['tiempo_limite'];
        $e['tarifa_por_hora']     = (float) $e['tarifa_por_hora'];
        $e['notificado']          = (bool) $e['notificado'];
        return $e;
    }

    /** Segundos transcurridos reales (acumulado + tiempo de la corrida activa). */
    private function transcurridoActual(array $e): int
    {
        $base = (int) $e['tiempo_transcurrido'];
        if ($e['estado'] === 'activa' && !empty($e['fecha_inicio'])) {
            $base += max(0, time() - strtotime($e['fecha_inicio']));
        }
        return $base;
    }

    /** Congela un equipo activo cuyo limite ya se alcanzo. */
    private function normalizar(array $e): array
    {
        $e['tiempo_transcurrido'] = $this->transcurridoActual($e);
        if ($e['estado'] === 'activa'
            && (int) $e['tiempo_limite'] > 0
            && $e['tiempo_transcurrido'] >= (int) $e['tiempo_limite']) {
            $st = $this->db->prepare(
                'UPDATE equipos SET estado = "finalizada", tiempo_transcurrido = ?, fecha_inicio = NULL, notificado = 1 WHERE id = ?'
            );
            $st->execute([$e['tiempo_transcurrido'], $e['id']]);
            $e['estado']       = 'finalizada';
            $e['fecha_inicio'] = null;
            $e['notificado']   = 1;
        }
        return $this->cast($e);
    }

    private function raw(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM equipos WHERE id = ?');
        $st->execute([$id]);
        $e = $st->fetch();
        return $e ?: null;
    }

    /** Igual que raw(), pero bloquea la fila. Solo usar dentro de una transacción. */
    private function rawForUpdate(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM equipos WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $e = $st->fetch();
        return $e ?: null;
    }

    /** Verifica si ya existe un equipo con el mismo nombre (excluyendo el id dado). */
    private function nombreExiste(string $nombre, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $st = $this->db->prepare(
                'SELECT COUNT(*) FROM equipos WHERE LOWER(nombre_equipo) = LOWER(?) AND id != ?'
            );
            $st->execute([$nombre, $excludeId]);
        } else {
            $st = $this->db->prepare(
                'SELECT COUNT(*) FROM equipos WHERE LOWER(nombre_equipo) = LOWER(?)'
            );
            $st->execute([$nombre]);
        }
        return (int) $st->fetchColumn() > 0;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM equipos ORDER BY id')->fetchAll();
        return array_map(function ($e) {
            return $this->normalizar($e);
        }, $rows);
    }

    public function getById(int $id): ?array
    {
        $e = $this->raw($id);
        return $e ? $this->normalizar($e) : null;
    }

    public function create(array $data): array
    {
        $error = Validador::errorTexto($data['nombre_equipo'] ?? '', 100, 'nombre del equipo');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }
        $nombre = Validador::texto($data['nombre_equipo'] ?? '');

        // ── UNICIDAD OPERATIVA: no se permiten dos equipos con el mismo nombre ──
        if ($this->nombreExiste($nombre)) {
            return [
                'success' => false,
                'error'   => "Ya existe un equipo registrado con el nombre \"{$nombre}\". Usa un nombre diferente.",
            ];
        }

        $tipo = ($data['tipo'] ?? 'pc') === 'consola' ? 'consola' : 'pc';

        $tarifa = $tipo === 'consola' ? 2.00 : 1.50;
        if (array_key_exists('tarifa_por_hora', $data) && Validador::texto($data['tarifa_por_hora']) !== '') {
            $error = Validador::errorDecimalPositivo($data['tarifa_por_hora'], 'tarifa por hora');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $tarifa = round((float) $data['tarifa_por_hora'], 2);
        }

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare(
                'INSERT INTO equipos (nombre_equipo, tipo, tarifa_por_hora, estado, tiempo_transcurrido, tiempo_limite, cliente, notificado, fecha_creacion)
                 VALUES (?, ?, ?, "inactiva", 0, 0, "", 0, NOW())'
            );
            $st->execute([$nombre, $tipo, $tarifa]);
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE equipos SET numero = LPAD(?, 2, "0") WHERE id = ?')->execute([$id, $id]);
            $equipo = $this->getById($id);

            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Violación de clave única a nivel de base de datos (SQLSTATE 23000 / MySQL 1062).
            if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'success' => false,
                    'error'   => "Ya existe un equipo con el nombre \"{$nombre}\" (conflicto en base de datos).",
                ];
            }
            throw $e;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['success' => true, 'data' => $equipo, 'message' => 'Equipo agregado.'];
    }

    public function update(int $id, array $data): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $sets = [];
        $params = [];
        if (array_key_exists('nombre_equipo', $data)) {
            $error = Validador::errorTexto($data['nombre_equipo'], 100, 'nombre del equipo');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $nombre = Validador::texto($data['nombre_equipo']);

            // ── UNICIDAD OPERATIVA: no renombrar a un nombre ya existente ──
            if ($this->nombreExiste($nombre, $id)) {
                return [
                    'success' => false,
                    'error'   => "Ya existe otro equipo registrado con el nombre \"{$nombre}\". Usa un nombre diferente.",
                ];
            }

            $sets[]   = 'nombre_equipo = ?';
            $params[] = $nombre;
        }
        if (array_key_exists('tipo', $data)) {
            if (!in_array($data['tipo'], ['pc', 'consola'], true)) {
                return ['success' => false, 'error' => 'El tipo de equipo debe ser "pc" o "consola".'];
            }
            $sets[] = 'tipo = ?';
            $params[] = $data['tipo'];
        }
        if (array_key_exists('tarifa_por_hora', $data)) {
            $error = Validador::errorDecimalPositivo($data['tarifa_por_hora'], 'tarifa por hora');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $sets[] = 'tarifa_por_hora = ?';
            $params[] = round((float) $data['tarifa_por_hora'], 2);
        }
        if (empty($sets)) {
            return ['success' => false, 'error' => 'No hay cambios para guardar.'];
        }
        $params[] = $id;
        try {
            $this->db->prepare('UPDATE equipos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        } catch (PDOException $e) {
            // Violación de clave única a nivel de base de datos (SQLSTATE 23000 / MySQL 1062).
            if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
                return [
                    'success' => false,
                    'error'   => 'Ya existe otro equipo con ese nombre (conflicto en base de datos).',
                ];
            }
            throw $e;
        }
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo actualizado.'];
    }

    public function delete(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare('DELETE FROM equipos WHERE id = ?')->execute([$id]);
        return ['success' => true, 'message' => 'Equipo eliminado.'];
    }

    private function registrarSesion(int $equipoId, string $equipoNombre, string $cliente, int $segundos, float $monto): void
    {
        $equipoNombre = mb_substr(Validador::texto($equipoNombre), 0, 100, 'UTF-8');
        $cliente      = mb_substr(Validador::texto($cliente), 0, 120, 'UTF-8');
        if ($cliente === '') {
            $cliente = 'Cliente';
        }
        $st = $this->db->prepare(
            'INSERT INTO sesiones_uso (equipo_id, equipo_nombre, cliente, segundos_alquilados, monto_cobrado, tipo_registro, fecha_registro)
             VALUES (?, ?, ?, ?, ?, "alquiler_tiempo", NOW())'
        );
        $st->execute([$equipoId, $equipoNombre, $cliente, $segundos, $monto]);
    }

    /**
     * Inicia el temporizador y registra la sesión de uso en una sola transacción.
     * $enTransaccion = true permite reutilizar la transacción de otro modelo
     * (por ejemplo, la aprobación de una solicitud) sin anidar BEGIN.
     */
    public function iniciarConLimite(int $id, int $minutos, string $cliente = '', bool $enTransaccion = false): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $error = Validador::errorEnteroPositivo($minutos, 'minutos de alquiler', 1440);
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $propia = !$enTransaccion;
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $e = $this->rawForUpdate($id);
            if (!$e) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => 'Equipo no encontrado.'];
            }

            $cliente = Validador::texto($cliente);
            if ($cliente === '') {
                $cliente = 'Cliente';
            }
            $error = Validador::errorTexto($cliente, 120, 'nombre del cliente');
            if ($error !== null) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => $error];
            }

            $segundos = $minutos * 60;
            $tarifa   = (float) $e['tarifa_por_hora'];
            $monto    = round(($minutos / 60) * $tarifa, 2);

            $st = $this->db->prepare(
                'UPDATE equipos SET estado = "activa", tiempo_transcurrido = 0, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
            );
            $st->execute([$segundos, $cliente, $id]);
            $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

            $data = $this->getById($id);
            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $ex) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log(sprintf(
                '[CyberPOS] Error al iniciar tiempo: %s en %s:%d',
                $ex->getMessage(),
                $ex->getFile(),
                $ex->getLine()
            ));
            return ['success' => false, 'error' => 'No se pudo iniciar el tiempo. Intenta de nuevo.'];
        }

        return ['success' => true, 'data' => $data, 'monto' => $monto, 'message' => 'Tiempo iniciado.'];
    }

    /**
     * Agrega tiempo y registra la sesión de uso en una sola transacción.
     * $enTransaccion = true permite reutilizar la transacción de otro modelo.
     */
    public function agregarTiempo(int $id, int $minutos, ?string $cliente = null, bool $enTransaccion = false): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $error = Validador::errorEnteroPositivo($minutos, 'minutos de alquiler', 1440);
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $propia = !$enTransaccion;
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $e = $this->rawForUpdate($id);
            if (!$e) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => 'Equipo no encontrado.'];
            }

            $cliente = $cliente !== null ? Validador::texto($cliente) : '';
            if ($cliente === '') {
                $cliente = ($e['cliente'] !== '' && $e['cliente'] !== null)
                    ? Validador::texto($e['cliente'])
                    : 'Cliente';
            }
            $error = Validador::errorTexto($cliente, 120, 'nombre del cliente');
            if ($error !== null) {
                if ($propia) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => $error];
            }

            $segundos = $minutos * 60;
            $tarifa   = (float) $e['tarifa_por_hora'];
            $monto    = round(($minutos / 60) * $tarifa, 2);

            $transcurrido = $this->transcurridoActual($e);
            $limite       = (int) $e['tiempo_limite'] + $segundos;

            $this->db->prepare(
                'UPDATE equipos SET estado = "activa", tiempo_transcurrido = ?, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
            )->execute([$transcurrido, $limite, $cliente, $id]);
            $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

            $data = $this->getById($id);
            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $ex) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log(sprintf(
                '[CyberPOS] Error al agregar tiempo: %s en %s:%d',
                $ex->getMessage(),
                $ex->getFile(),
                $ex->getLine()
            ));
            return ['success' => false, 'error' => 'No se pudo agregar el tiempo. Intenta de nuevo.'];
        }

        return ['success' => true, 'data' => $data, 'monto' => $monto, 'message' => 'Tiempo agregado.'];
    }

    public function detener(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare(
            'UPDATE equipos SET estado = "inactiva", tiempo_transcurrido = 0, tiempo_limite = 0, cliente = "", fecha_inicio = NULL, notificado = 0 WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo detenido.'];
    }

    public function reiniciarA0(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare(
            'UPDATE equipos SET tiempo_transcurrido = 0, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Tiempo reiniciado.'];
    }
}