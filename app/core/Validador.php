<?php
/**
 * CyberPOS - Validador central de valores limite.
 * Cada metodo devuelve un mensaje de error (string) o null si el dato es valido.
 * Se usa tanto en modelos como en controladores para evitar datos corruptos
 * aunque el HTML sea alterado desde el navegador (F12).
 */

final class Validador
{
    /** Limpia un texto: convierte a string, elimina etiquetas y recorta espacios. */
    public static function texto($valor): string
    {
        if (is_array($valor) || is_object($valor) || is_bool($valor)) {
            return '';
        }
        return trim(strip_tags((string) ($valor ?? '')));
    }

    /** Normaliza una URL de imagen y descarta esquemas o longitudes peligrosas. */
    public static function urlImagen($valor, bool $permitirData = false): string
    {
        $url = self::texto($valor);
        if ($url === '' || mb_strlen($url, 'UTF-8') > 2000) {
            return '';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }
        $patron = $permitirData
            ? '/^(https?:\/\/|\/|\.\/|data:image\/[a-zA-Z0-9\+\-\.]+;base64,|[a-zA-Z0-9_\-\.\/]+\.(jpg|jpeg|png|gif|webp|svg))/i'
            : '/^(https?:\/\/|\/|\.\/|[a-zA-Z0-9_\-\.\/]+\.(jpg|jpeg|png|gif|webp|svg))/i';
        return preg_match($patron, $url) ? $url : '';
    }

    /** Valida un texto obligatorio y su longitud maxima (VARCHAR de la BD). */
    public static function errorTexto($valor, int $max, string $campo, bool $obligatorio = true): ?string
    {
        $texto = self::texto($valor);
        if ($obligatorio && $texto === '') {
            return "El campo {$campo} es obligatorio y no puede contener solo espacios.";
        }
        if ($texto !== '' && mb_strlen($texto, 'UTF-8') > $max) {
            return "El campo {$campo} no puede exceder {$max} caracteres.";
        }
        return null;
    }

    /** Valida un entero (sin decimales) con rango opcional. */
    public static function errorEntero($valor, string $campo, ?int $min = null, ?int $max = null): ?string
    {
        if (!is_numeric($valor)) {
            return "El campo {$campo} debe ser un número entero.";
        }
        $numero = (float) $valor;
        if (is_nan($numero) || is_infinite($numero) || floor($numero) != $numero) {
            return "El campo {$campo} debe ser un número entero sin decimales.";
        }
        $entero = (int) $numero;
        if ($min !== null && $entero < $min) {
            return "El campo {$campo} debe ser mayor o igual a {$min}.";
        }
        if ($max !== null && $entero > $max) {
            return "El campo {$campo} no puede ser mayor a {$max}.";
        }
        return null;
    }

    /** Entero estrictamente mayor a 0 (cantidades, minutos, ids). */
    public static function errorEnteroPositivo($valor, string $campo, ?int $max = null): ?string
    {
        return self::errorEntero($valor, $campo, 1, $max);
    }

    /** Entero mayor o igual a 0 (stock). */
    public static function errorEnteroNoNegativo($valor, string $campo, ?int $max = null): ?string
    {
        return self::errorEntero($valor, $campo, 0, $max);
    }

    /** Numero decimal estrictamente mayor a 0 (precios, tarifas, tasa BCV). */
    public static function errorDecimalPositivo($valor, string $campo): ?string
    {
        return self::errorDecimal($valor, $campo, 0.0, false);
    }

    /** Numero decimal mayor o igual a 0 (montos calculados, totales). */
    public static function errorDecimalNoNegativo($valor, string $campo): ?string
    {
        return self::errorDecimal($valor, $campo, 0.0, true);
    }

    private static function errorDecimal($valor, string $campo, float $min, bool $inclusivo): ?string
    {
        if (!is_numeric($valor)) {
            return "El campo {$campo} debe ser un valor numérico.";
        }
        $numero = (float) $valor;
        if (is_nan($numero) || is_infinite($numero)) {
            return "El campo {$campo} debe ser un valor numérico válido.";
        }
        if ($inclusivo ? $numero < $min : $numero <= $min) {
            return $inclusivo
                ? "El campo {$campo} no puede ser negativo."
                : "El campo {$campo} debe ser mayor a {$min}.";
        }
        return null;
    }
}
