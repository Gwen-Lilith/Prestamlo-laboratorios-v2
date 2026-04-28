<?php
/**
 * Endpoint: Obtener datos del perfil del usuario LOGUEADO
 * Método: GET (sin parámetros — siempre se trae los datos del usuario activo)
 *
 * Devuelve solo los campos editables desde "Mi Perfil" para no exponer
 * más superficie de la necesaria. La contraseña nunca se devuelve.
 *
 * HU-08.07 Gestionar perfil propio + HU-07.04 Canal preferido.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido.', 405);
}

Auth::requireLogin();
$user = Auth::currentUser();
$uid  = (int)$user['n_idusuario'];

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT n_idusuario, t_codigoinstitucional, t_nombres, t_apellidos, t_correo,
            t_telefono, t_telefono_apikey, t_canal_preferido, t_fotoperfil
     FROM usuarios WHERE n_idusuario = :id LIMIT 1"
);
$stmt->execute([':id' => $uid]);
$datos = $stmt->fetch();

if (!$datos) {
    Response::error('Usuario no encontrado.', 404);
}

Response::json($datos);
