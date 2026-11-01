<?php
/**
 * CyberPOS - Modelo Configuracion (fila unica).
 * Inmunidad ante SQLi y sanitización XSS (Etapa 2).
 */

class Configuracion extends Model
{
    private function cast(array $c): array
    {
        $c['id']                  = (int) $c['id'];
        $c['tasa_bcv']            = (float) $c['tasa_bcv'];
        $c['tasa_manual']         = (bool) $c['tasa_manual'];
        $c['precio_hora_pc']      = (float) $c['precio_hora_pc'];
        $c['precio_hora_consola'] = (float) $c['precio_hora_consola'];
        return $c;
    }

    private function sanitizarAvatar(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('/^(https?:\/\/|\/|\.\/|data:image\/[a-zA-Z0-9\+\-\.]+;base64,|[a-zA-Z0-9_\-\.\/]+\.(jpg|jpeg|png|gif|webp|svg))/i', $url)) {
            return '';
        }
        return $url;
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

        // Tasa BCV
        if (array_key_exists('tasa_bcv', $data)) {
            if (!is_numeric($data['tasa_bcv'])) {
                return ['success' => false, 'error' => 'Tasa BCV inválida: debe ser un valor numérico.'];
            }
            $tasa = (float) $data['tasa_bcv'];
            if (is_nan($tasa) || is_infinite($tasa) || $tasa <= 0) {
                return ['success' => false, 'error' => 'Tasa BCV inválida: debe ser mayor a 0.'];
            }
            $sets[] = 'tasa_bcv = ?';
            $params[] = round($tasa, 2);
            $sets[] = 'ultima_actualizacion_tasa = NOW()';
        }

        if (array_key_exists('tasa_manual', $data)) {
            $sets[] = 'tasa_manual = ?';
            $params[] = $data['tasa_manual'] ? 1 : 0;
        }

        // Nombre del negocio
        if (array_key_exists('nombre_cyber', $data)) {
            $nombreCyber = trim(strip_tags((string) $data['nombre_cyber']));
            if ($nombreCyber === '') {
                return ['success' => false, 'error' => 'El nombre del negocio no puede estar vacío ni consistir solo de espacios o etiquetas HTML.'];
            }
            if (mb_strlen($nombreCyber) > 150) {
                return ['success' => false, 'error' => 'El nombre del negocio no puede exceder los 150 caracteres.'];
            }
            $sets[] = 'nombre_cyber = ?';
            $params[] = $nombreCyber;
        }

        // Color del tema
        if (array_key_exists('theme_color', $data)) {
            $color = trim((string) $data['theme_color']);
            if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                return ['success' => false, 'error' => 'Formato de color hexadecimal inválido.'];
            }
            if (mb_strlen($color) > 20) {
                return ['success' => false, 'error' => 'El código de color no puede exceder los 20 caracteres.'];
            }
            $sets[] = 'theme_color = ?';
            $params[] = $color;
        }

        if (array_key_exists('avatar_url', $data)) {
            $sets[] = 'avatar_url = ?';
            $params[] = $this->sanitizarAvatar((string) $data['avatar_url']);
        }

        // Tarifas por hora generales
        if (array_key_exists('precio_hora_pc', $data)) {
            if (!is_numeric($data['precio_hora_pc'])) {
                return ['success' => false, 'error' => 'Precio por hora PC inválido: debe ser un número.'];
            }
            $precioPc = (float) $data['precio_hora_pc'];
            if (is_nan($precioPc) || is_infinite($precioPc) || $precioPc <= 0) {
                return ['success' => false, 'error' => 'Precio por hora PC inválido: debe ser mayor a 0.'];
            }
            $sets[] = 'precio_hora_pc = ?';
            $params[] = round($precioPc, 2);
        }

        if (array_key_exists('precio_hora_consola', $data)) {
            if (!is_numeric($data['precio_hora_consola'])) {
                return ['success' => false, 'error' => 'Precio por hora Consola inválido: debe ser un número.'];
            }
            $precioConsola = (float) $data['precio_hora_consola'];
            if (is_nan($precioConsola) || is_infinite($precioConsola) || $precioConsola <= 0) {
                return ['success' => false, 'error' => 'Precio por hora Consola inválido: debe ser mayor a 0.'];
            }
            $sets[] = 'precio_hora_consola = ?';
            $params[] = round($precioConsola, 2);
        }

        if (!empty($sets)) {
            $this->db->prepare('UPDATE configuracion SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
        }
        return ['success' => true, 'data' => $this->get(), 'message' => 'Configuración guardada.'];
    }
}
