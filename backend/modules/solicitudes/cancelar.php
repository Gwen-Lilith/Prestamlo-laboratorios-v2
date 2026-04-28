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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: HU-04.07 pide PATCH para cancelar
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH'])) Response::error('Método no permitido.', 405);
Auth::requireLogin();

$data = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
// HU-04.07: motivo de cancelación (opcional pero registrar si viene)
$motivo = Validator::limitarLongitud(Validator::obtenerCampo($data, 'motivo') ?: Validator::obtenerCampo($data, 'observaciones'), 500);
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

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'cancelada' WHERE n_idsolicitud = :id")
        ->execute([':id' => $id]);

    $detalleHist = $motivo ? "Solicitud cancelada — $motivo" : 'Solicitud cancelada';
    Logger::registrar($id, 'pendiente', 'cancelada', $detalleHist, $currentUser['n_idusuario']);

    // HU-09.02: registrar en auditoría con el motivo
    Auditor::registrar('solicitudes_prestamo', 'cancelar', (int)$id, $currentUser['n_idusuario'],
        "Solicitud #$id cancelada" . ($motivo ? " — motivo: $motivo" : ''),
        ['antes' => ['t_estado' => 'pendiente'], 'despues' => ['t_estado' => 'cancelada']]);

    $pdo->commit();
    Response::json(null, 200, 'Solicitud cancelada.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('cancelar.php: ' . $e->getMessage());
    Response::error('Error al cancelar la solicitud.', 500);
}
