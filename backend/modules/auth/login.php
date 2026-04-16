<?php
/**
 * Endpoint: Login de usuario
 * Método: POST
 * Body: { "correo": "...", "password": "..." }
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

// Obtener datos del body
$data = Validator::obtenerBodyJSON();
$correo   = Validator::obtenerCampo($data, 'correo');
$password = $data['password'] ?? '';

// Validaciones
if (empty($correo) || empty($password)) {
    Response::error('Correo y contraseña son obligatorios.');
}

if (!Validator::validarCorreo($correo)) {
    Response::error('El formato del correo no es válido.');
}

// Intentar login
$usuario = Auth::login($correo, $password);

if ($usuario === false) {
    Response::error('Correo o contraseña incorrectos.', 401);
}

// Login exitoso — devolver datos del usuario
Response::json($usuario, 200, 'Login exitoso.');
