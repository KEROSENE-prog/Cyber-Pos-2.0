<?php
/**
 * CyberPOS - Modelo Configuracion (fila unica).
 */

class Configuracion extends Model
{
    private function cast(array $c): array
    {
        $c['id']                = (int) $c['id'];
        $c['tasa_bcv']          = (float) $c['tasa_bcv'];
        $c['tasa_manual']       = (bool) $c['tasa_manual'];
        $c['precio_hora_pc']    = (float) $c['precio_hora_pc'];
        $c['precio_hora_consola'] = (float) $c['precio_hora_consola'];
        return $c;
    }

    public function get(): array
    {
        $row = $this->db->query('SELECT * FROM configuracion WHERE id = 1')->fetch();
        if (!$row) {
            $this->db->exec(
                'INSERT INTO configuracion (id, tasa_bcv, tasa_manual, nombre_cyber, precio_hora_pc, precio_hora_consola, ultima_actualizacion_tasa, theme_color, avatar_url)
                 VALUES (1, 36.85, 0, "CyberPOS Laneros Gamer", 1.50, 2.00, NOW(), "#35c029", "")'
            );
            $row = $this->db->query('SELECT * FROM configuracion WHERE id = 1')->fetch();
        }
        return $this->cast($row);
    }

    public function update(array $data): array
    {
        $actual = $this->get();
        $sets   = [];
        $params = [];

        if (isset($data['tasa_bcv'])) {
            $tasa = (float) $data['tasa_bcv'];
            if ($tasa <= 0) {
                return ['success' => false, 'error' => 'La tasa debe ser mayor a 0.'];
            }
            $sets[] = 'tasa_bcv = ?';
            $params[] = $tasa;
            $sets[] = 'ultima_actualizacion_tasa = NOW()';
        }
        if (array_key_exists('tasa_manual', $data)) {
            $sets[] = 'tasa_manual = ?';
            $params[] = $data['tasa_manual'] ? 1 : 0;
        }
        if (isset($data['nombre_cyber'])) {
            $sets[] = 'nombre_cyber = ?';
            $params[] = (string) $data['nombre_cyber'];
        }
        if (array_key_exists('theme_color', $data)) {
            $sets[] = 'theme_color = ?';
            $params[] = (string) $data['theme_color'];
        }
        if (array_key_exists('avatar_url', $data)) {
            $sets[] = 'avatar_url = ?';
            $params[] = (string) $data['avatar_url'];
        }
        if (isset($data['precio_hora_pc'])) {
            $sets[] = 'precio_hora_pc = ?';
            $params[] = (float) $data['precio_hora_pc'];
        }
        if (isset($data['precio_hora_consola'])) {
            $sets[] = 'precio_hora_consola = ?';
            $params[] = (float) $data['precio_hora_consola'];
        }

        if (!empty($sets)) {
            $this->db->prepare('UPDATE configuracion SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
        }
        return ['success' => true, 'data' => $this->get(), 'message' => 'Configuración guardada.'];
    }
}