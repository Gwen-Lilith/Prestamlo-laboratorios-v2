<?php
/**
 * Endpoint: Rechazar solicitud
 * Método: POST | Body: { id, observaciones }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: HU-04.03 pide PATCH
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id  = $data['id'] ?? 0;
// HU-10.03: limitar longitud (motivo de rechazo es free-form)
$obs = Validator::limitarLongitud(Validator::obtenerCampo($data, 'observaciones'), 500);
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($obs)) Response::error('La razón del rechazo es obligatoria.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT t_estado FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);
if ($sol['t_estado'] !== 'pendiente') Response::error('Solo se pueden rechazar solicitudes pendientes.');

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'rechazada',
                   n_idusuarioaprobo = :uid, dt_fechaaprobacion = NOW(),
                   t_observacionesauxiliar = :obs WHERE n_idsolicitud = :id")
        ->execute([':uid' => $currentUser['n_idusuario'], ':obs' => $obs, ':id' => $id]);

    Logger::registrar($id, 'pendiente', 'rechazada', $obs, $currentUser['n_idusuario']);

    // Notificar al solicitante
    $solInfo = $pdo->prepare("SELECT n_idusuario FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
    $solInfo->execute([':id' => $id]);
    $idSolicitante = $solInfo->fetchColumn();
    if ($idSolicitante) {
        Notificador::notificar($idSolicitante, 'rechazada',
            'Tu solicitud #' . $id . ' fue rechazada',
            $obs ?: 'Sin motivo especificado.',
            'dashboard-usuario.html', $id);
    }

    // HU-09.02: trazabilidad en auditoría
    Auditor::registrar('solicitudes_prestamo', 'rechazar', (int)$id, $currentUser['n_idusuario'],
        "Solicitud #$id rechazada — $obs",
        ['antes' => ['t_estado' => 'pendiente'], 'despues' => ['t_estado' => 'rechazada']]);

    $pdo->commit();
    Response::json(null, 200, 'Solicitud rechazada.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('rechazar.php: ' . $e->getMessage());
    Response::error('Error al rechazar la solicitud.', 500);
}
