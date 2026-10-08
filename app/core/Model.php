<?php
/**
 * CyberPOS - Modelo base. Provee acceso a PDO y utilidades comunes.
 */

abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}