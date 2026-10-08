<?php
/**
 * CyberPOS - Definicion de rutas de la API.
 * Formato: [metodo, patron_regex, "Controlador@accion"].
 */

return [
    // Autenticacion
    ['POST', '/auth/login',          'AuthController@login'],
    ['POST', '/auth/register',       'AuthController@register'],
    ['POST', '/auth/logout',         'AuthController@logout'],
    ['GET',  '/auth/logout',         'AuthController@logout'],
    ['GET',  '/auth/me',             'AuthController@me'],

    // Perfil
    ['PUT',  '/usuarios/(\d+)',      'UsuarioController@update'],

    // Dashboard (KPIs)
    ['GET',  '/dashboard/kpis',      'DashboardController@kpis'],

    // Equipos
    ['GET',  '/equipos',             'EquipoController@index'],
    ['GET',  '/equipos/(\d+)',       'EquipoController@show'],
    ['POST', '/equipos',             'EquipoController@store'],
    ['PUT',  '/equipos/(\d+)',       'EquipoController@update'],
    ['DELETE', '/equipos/(\d+)',     'EquipoController@destroy'],
    ['POST', '/equipos/(\d+)/tiempo','EquipoController@tiempo'],

    // Snacks (catalogo estandar)
    ['GET',  '/snacks',              'ProductoController@snacksIndex'],
    ['POST', '/snacks',              'ProductoController@snacksCreate'],
    ['PUT',  '/snacks/(\d+)',        'ProductoController@update'],
    ['DELETE', '/snacks/(\d+)',      'ProductoController@destroy'],

    // Productos personalizados
    ['GET',  '/personalizados',      'ProductoController@persIndex'],
    ['POST', '/personalizados',      'ProductoController@persCreate'],
    ['PUT',  '/personalizados/(\d+)','ProductoController@update'],
    ['DELETE', '/personalizados/(\d+)', 'ProductoController@destroy'],

    // Productos unificados (POS / cliente)
    ['GET',  '/productos',           'ProductoController@index'],

    // Ventas directas (POS)
    ['GET',  '/ventas',              'VentaController@index'],
    ['POST', '/ventas',              'VentaController@store'],

    // Solicitudes de tiempo
    ['GET',  '/solicitudes',                    'SolicitudController@index'],
    ['POST', '/solicitudes',                    'SolicitudController@store'],
    ['POST', '/solicitudes/(\d+)/admin',        'SolicitudController@admin'],
    ['POST', '/solicitudes/(\d+)/cliente',      'SolicitudController@cliente'],
    ['POST', '/solicitudes/(\d+)/cancelar',     'SolicitudController@cancelar'],

    // Pedidos de snacks
    ['GET',  '/pedidos',             'PedidoController@index'],
    ['POST', '/pedidos',             'PedidoController@store'],
    ['POST', '/pedidos/(\d+)/completar', 'PedidoController@completar'],
    ['POST', '/pedidos/(\d+)/cancelar',  'PedidoController@cancelar'],

    // Configuracion
    ['GET',  '/config',              'ConfigController@show'],
    ['PUT',  '/config',              'ConfigController@update'],

    // Base de datos
    ['GET',  '/db/stats',            'BackupController@stats'],
    ['GET',  '/backup/export',       'BackupController@export'],
    ['POST', '/backup/import',       'BackupController@import'],
    ['POST', '/backup/reset',        'BackupController@reset'],
];