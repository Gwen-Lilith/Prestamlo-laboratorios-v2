<?php
/**
 * Endpoint: Verificar sesión activa
 * Método: GET
 * Devuelve los datos del usuario si hay sesión, o error 401
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';

$usuario = Auth::currentUser();

if ($usuario === null) {
    Response::error('No hay sesión activa.', 401);
}

Response::json($usuario, 200, 'Sesión activa.');
