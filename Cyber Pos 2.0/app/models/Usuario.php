<?php
/**
 * CyberPOS - Modelo Usuario (autenticacion y perfil).
 */

class Usuario extends Model
{
    private function safe(array $u): array
    {
        unset($u['password'], $u['token'], $u['token_expira']);
        $u['id'] = (int) $u['id'];
        return $u;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM usuarios ORDER BY id')->fetchAll();
        return array_map([$this, 'safe'], $rows);
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ? $this->safe($u) : null;
    }

    /** Usuario asociado a un token vigente, o null. */
    public function findByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $st = $this->db->prepare(
            'SELECT * FROM usuarios WHERE token = ? AND token_expira IS NOT NULL AND token_expira > NOW() LIMIT 1'
        );
        $st->execute([$token]);
        $u = $st->fetch();
        return $u ? $this->safe($u) : null;
    }

    public function login(string $email, string $password, ?string $rol): array
    {
        $email = strtolower(trim($email));
        $rol   = $rol !== null ? strtolower(trim($rol)) : '';

        $st = $this->db->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u) {
            return ['success' => false, 'error' => 'No existe una cuenta con ese correo.'];
        }
        if (!password_verify($password, $u['password'])) {
            return ['success' => false, 'error' => 'Contraseña incorrecta.'];
        }
        if ($rol !== '' && $u['rol'] !== 'administrador' && $u['rol'] !== $rol) {
            return ['success' => false, 'error' => 'El rol seleccionado no coincide con tu cuenta.'];
        }

        $token = $this->emitirToken((int) $u['id']);
        return ['success' => true, 'token' => $token, 'user' => $this->safe($u)];
    }

    public function register(array $data): array
    {
        $email    = strtolower(trim($data['email'] ?? ''));
        $nombre   = trim($data['nombre_completo'] ?? '');
        $password = trim($data['password'] ?? '');
        $rol      = strtolower(trim($data['rol'] ?? 'cliente'));
        if (!in_array($rol, ['administrador', 'cliente'], true)) {
            $rol = 'cliente';
        }

        if ($email === '' || $nombre === '' || $password === '') {
            return ['success' => false, 'error' => 'Todos los campos son obligatorios.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'El correo no es válido.'];
        }

        $st = $this->db->prepare('SELECT id FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            return ['success' => false, 'error' => 'Ya existe una cuenta con ese correo.'];
        }

        $st = $this->db->prepare(
            'INSERT INTO usuarios (nombre_completo, email, password, rol, avatar_url, fecha_creacion)
             VALUES (?, ?, ?, ?, "", NOW())'
        );
        $st->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT), $rol]);
        $id = (int) $this->db->lastInsertId();

        $token = $this->emitirToken($id);
        return ['success' => true, 'token' => $token, 'user' => $this->getById($id), 'message' => 'Cuenta creada exitosamente.'];
    }

    public function updateProfile(int $id, array $data): array
    {
        $st = $this->db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) {
            return ['success' => false, 'error' => 'Usuario no encontrado.'];
        }

        $sets   = [];
        $params = [];

        if (isset($data['nombre_completo']) && trim((string) $data['nombre_completo']) !== '') {
            $sets[]   = 'nombre_completo = ?';
            $params[] = trim((string) $data['nombre_completo']);
        }
        if (array_key_exists('avatar_url', $data)) {
            $sets[]   = 'avatar_url = ?';
            $params[] = (string) $data['avatar_url'];
        }
        if (!empty($data['password'])) {
            if (strlen((string) $data['password']) < 6) {
                return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
            }
            $sets[]   = 'password = ?';
            $params[] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        if (empty($sets)) {
            return ['success' => false, 'error' => 'No hay cambios para guardar.'];
        }

        $params[] = $id;
        $st = $this->db->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $st->execute($params);

        return ['success' => true, 'user' => $this->getById($id), 'message' => 'Perfil actualizado.'];
    }

    public function emitirToken(int $id): string
    {
        $config = require __DIR__ . '/../config.php';
        $token  = bin2hex(random_bytes(24));
        $expira = date('Y-m-d H:i:s', time() + (int) $config['token_ttl']);
        $st = $this->db->prepare('UPDATE usuarios SET token = ?, token_expira = ? WHERE id = ?');
        $st->execute([$token, $expira, $id]);
        return $token;
    }

    public function logout(?string $token): void
    {
        if (!$token) {
            return;
        }
        $st = $this->db->prepare('UPDATE usuarios SET token = NULL, token_expira = NULL WHERE token = ?');
        $st->execute([$token]);
    }
}