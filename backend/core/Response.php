<?php
/**
 * Helper para respuestas JSON estandarizadas
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 * 
 * Todas las respuestas del backend siguen la estructura:
 * { "ok": true|false, "data": ..., "mensaje": "..." }
 */

class Response {

    /**
     * Respuesta JSON exitosa
     * @param mixed  $data    Datos a devolver
     * @param int    $status  Código HTTP (200 por defecto)
     * @param string $mensaje Mensaje opcional
     */
    public static function json($data, $status = 200, $mensaje = 'OK') {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => true,
            'data'    => $data,
            'mensaje' => $mensaje
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Respuesta JSON de error
     * @param string $mensaje Mensaje de error
     * @param int    $status  Código HTTP (400 por defecto)
     * @param mixed  $data    Datos adicionales opcionales
     */
    public static function error($mensaje, $status = 400, $data = null) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'data'    => $data,
            'mensaje' => $mensaje
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
