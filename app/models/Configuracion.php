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

    private function sanitizarAvatar($url): string
    {
        return Validador::urlImagen($url, true);
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

        if (array_key_exists('tasa_bcv', $data)) {
            $error = Validador::errorDecimalPositivo($data['tasa_bcv'], 'tasa BCV');
            if ($error !== null) {
                return ['success' => false, 'error' => 'Tasa BCV inválida. ' . $error];
            }
            $sets[] = 'tasa_bcv = ?';
            $params[] = round((float) $data['tasa_bcv'], 2);
            $sets[] = 'ultima_actualizacion_tasa = NOW()';
        }
        if (array_key_exists('tasa_manual', $data)) {
            $sets[] = 'tasa_manual = ?';
            $params[] = $data['tasa_manual'] ? 1 : 0;
        }
        if (array_key_exists('nombre_cyber', $data)) {
            $error = Validador::errorTexto($data['nombre_cyber'], 150, 'nombre del negocio');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $sets[] = 'nombre_cyber = ?';
            $params[] = Validador::texto($data['nombre_cyber']);
        }
        if (array_key_exists('theme_color', $data)) {
            $color = Validador::texto($data['theme_color']);
            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                return ['success' => false, 'error' => 'El color debe tener formato hexadecimal, por ejemplo #35c029.'];
            }
            if (mb_strlen($color, 'UTF-8') > 20) {
                return ['success' => false, 'error' => 'El color no puede exceder los 20 caracteres.'];
            }
            $sets[] = 'theme_color = ?';
            $params[] = $color;
        }
        if (array_key_exists('avatar_url', $data)) {
            $sets[] = 'avatar_url = ?';
            $params[] = $this->sanitizarAvatar($data['avatar_url']);
        }
        if (array_key_exists('precio_hora_pc', $data)) {
            $error = Validador::errorDecimalPositivo($data['precio_hora_pc'], 'precio por hora de PC');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $sets[] = 'precio_hora_pc = ?';
            $params[] = round((float) $data['precio_hora_pc'], 2);
        }
        if (array_key_exists('precio_hora_consola', $data)) {
            $error = Validador::errorDecimalPositivo($data['precio_hora_consola'], 'precio por hora de consola');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $sets[] = 'precio_hora_consola = ?';
            $params[] = round((float) $data['precio_hora_consola'], 2);
        }

        if (!empty($sets)) {
            $this->db->prepare('UPDATE configuracion SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
        }
        return ['success' => true, 'data' => $this->get(), 'message' => 'Configuración guardada.'];
    }
}