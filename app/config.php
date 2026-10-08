<?php
/**
 * CyberPOS - Configuracion general del backend.
 * Puedes sobreescribir estos valores con variables de entorno.
 */

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: 3306,
        'name'    => getenv('DB_NAME') ?: 'cyberpos',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    // Duracion del token de sesion (segundos). 7 dias por defecto.
    'token_ttl' => 60 * 60 * 24 * 7,
];