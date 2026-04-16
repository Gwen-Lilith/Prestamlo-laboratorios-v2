<?php
/**
 * Endpoint: Inactivar usuario (inactivación lógica, nunca DELETE)
 * Método: POST
 * Body: { id, activo: "S"|"N" }
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

Auth::requireRole(['administrador']);

$data   = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
$activo = $data['activo'] ?? '';

if (!Validator::validarEntero($id)) {
    Response::error('ID de usuario inválido.');
}

if (!Validator::validarEnSet($activo, ['S', 'N'])) {
    Response::error('El valor de activo debe ser S o N.');
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("UPDATE usuarios SET t_activo = :activo WHERE n_idusuario = :id");
$stmt->execute([':activo' => $activo, ':id' => $id]);

$accion = $activo === 'S' ? 'activado' : 'inactivado';
Response::json(null, 200, "Usuario $accion correctamente.");
