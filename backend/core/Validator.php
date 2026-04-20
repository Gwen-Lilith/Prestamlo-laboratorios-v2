<?php
/**
 * Validador y sanitizador de inputs
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

class Validator {

    /**
     * Limpiar string: solo trim.
     * El escape para XSS se hace al RENDERIZAR en el frontend/HTML,
     * no al guardar en BD (guardar con htmlspecialchars corrompe datos:
     * "Lab & Redes" quedaba como "Lab &amp; Redes").
     * @param string $valor
     * @return string
     */
    public static function limpiarString($valor) {
        if ($valor === null) return '';
        return trim((string)$valor);
    }

    /**
     * Validar formato de correo electrónico
     * @param string $correo
     * @return bool
     */
    public static function validarCorreo($correo) {
        return filter_var(trim($correo), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validar que un valor sea un entero positivo
     * @param mixed $valor
     * @return bool
     */
    public static function validarEntero($valor) {
        return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    /**
     * Validar fecha en formato Y-m-d H:i:s
     * @param string $fecha
     * @return bool
     */
    public static function validarFecha($fecha) {
        // Aceptar tanto Y-m-d como Y-m-d H:i:s
        $formatos = ['Y-m-d H:i:s', 'Y-m-d', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];
        foreach ($formatos as $formato) {
            $dt = DateTime::createFromFormat($formato, $fecha);
            if ($dt && $dt->format($formato) === $fecha) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validar que un valor exista en un conjunto permitido
     * Útil para campos como t_estado
     * @param string $valor          Valor a validar
     * @param array  $valoresPermitidos  Array de valores válidos
     * @return bool
     */
    public static function validarEnSet($valor, array $valoresPermitidos) {
        return in_array($valor, $valoresPermitidos, true);
    }

    /**
     * Obtener y limpiar un campo del body JSON de la petición
     * @param array  $data  Array de datos
     * @param string $campo Nombre del campo
     * @param mixed  $default Valor por defecto
     * @return string
     */
    public static function obtenerCampo(array $data, $campo, $default = '') {
        if (!isset($data[$campo])) return $default;
        return self::limpiarString($data[$campo]);
    }

    /**
     * Obtener el body JSON de la petición actual
     * @return array
     */
    public static function obtenerBodyJSON() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) return [];
        return $data;
    }
}
