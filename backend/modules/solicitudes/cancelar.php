<?php
/**
 * Endpoint: Cancelar solicitud (solo el solicitante o admin, solo si está pendiente)
 * Método: POST | Body: { id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? 0;
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT n_idusuario, t_estado FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);
if ($sol['t_estado'] !== 'pendiente') Response::error('Solo se pueden cancelar solicitudes pendientes.');

// Solo el dueño o un admin puede cancelar
$esAdmin = in_array('administrador', $currentUser['roles']) || in_array('auxiliar_tecnico', $currentUser['roles']);
if ($sol['n_idusuario'] != $currentUser['n_idusuario'] && !$esAdmin) {
    Response::error('No tiene permisos para cancelar esta solicitud.', 403);
}

$pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'cancelada' WHERE n_idsolicitud = :id")
    ->execute([':id' => $id]);
Logger::registrar($id, 'pendiente', 'cancelada', 'Solicitud cancelada', $currentUser['n_idusuario']);
Response::json(null, 200, 'Solicitud cancelada.');
