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
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id  = $data['id'] ?? 0;
$obs = Validator::obtenerCampo($data, 'observaciones');
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($obs)) Response::error('La razón del rechazo es obligatoria.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT t_estado FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);
if ($sol['t_estado'] !== 'pendiente') Response::error('Solo se pueden rechazar solicitudes pendientes.');

$pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'rechazada', 
               n_idusuarioaprobo = :uid, dt_fechaaprobacion = NOW(), 
               t_observacionesauxiliar = :obs WHERE n_idsolicitud = :id")
    ->execute([':uid' => $currentUser['n_idusuario'], ':obs' => $obs, ':id' => $id]);

Logger::registrar($id, 'pendiente', 'rechazada', $obs, $currentUser['n_idusuario']);
Response::json(null, 200, 'Solicitud rechazada.');
