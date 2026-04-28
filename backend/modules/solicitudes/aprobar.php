<?php
/**
 * Endpoint: Aprobar solicitud
 * Método: POST | Body: { id, observaciones? }
 * Flujo: pendiente → aprobada
 * Cambia estado de los elementos a "prestado"
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id  = $data['id'] ?? 0;
$obs = Validator::obtenerCampo($data, 'observaciones');
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();

// Verificar que la solicitud existe y está pendiente
$stmt = $pdo->prepare("SELECT t_estado FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);
if ($sol['t_estado'] !== 'pendiente') Response::error('Solo se pueden aprobar solicitudes pendientes.');

try {
    $pdo->beginTransaction();

    // Actualizar estado de la solicitud a 'aprobada'.
    // NOTA: los elementos NO cambian de estado aquí. Siguen 'disponible'
    // hasta que se registre la entrega física (salida del laboratorio).
    $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'aprobada',
                   n_idusuarioaprobo = :aprobador, dt_fechaaprobacion = NOW(),
                   t_observacionesauxiliar = :obs WHERE n_idsolicitud = :id")
        ->execute([':aprobador' => $currentUser['n_idusuario'], ':obs' => $obs, ':id' => $id]);

    Logger::registrar($id, 'pendiente', 'aprobada', $obs ?: 'Solicitud aprobada', $currentUser['n_idusuario']);

    // Notificar al solicitante (HU-07.01)
    $solInfo = $pdo->prepare("SELECT n_idusuario FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
    $solInfo->execute([':id' => $id]);
    $idSolicitante = $solInfo->fetchColumn();
    if ($idSolicitante) {
        Notificador::notificar(
            $idSolicitante, 'aprobada',
            'Tu solicitud #' . $id . ' fue aprobada',
            'Pasa por el laboratorio a recoger tus elementos. ' . ($obs ? "Observación: $obs" : ''),
            'dashboard-usuario.html', $id
        );
    }

    $pdo->commit();
    Response::json(null, 200, 'Solicitud aprobada correctamente.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('aprobar.php: ' . $e->getMessage());
    Response::error('Error al aprobar la solicitud.', 500);
}
