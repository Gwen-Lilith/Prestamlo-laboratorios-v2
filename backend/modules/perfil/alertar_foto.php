<?php
/**
 * Endpoint: Alertar al usuario sobre su foto de perfil (HU-08.10)
 * Método: POST | Body: { id, motivo? }
 * Solo admin/auxiliar.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$user = Auth::currentUser();

$data   = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
$motivo = Validator::obtenerCampo($data, 'motivo');
if (!Validator::validarEntero($id)) Response::error('ID de usuario inválido.');
if (empty($motivo)) {
    $motivo = 'Tu foto de perfil no cumple con los requisitos. Por favor actualízala.';
}

$pdo = Database::getConnection();

// Verificar que el usuario exista
$exists = $pdo->prepare("SELECT n_idusuario FROM usuarios WHERE n_idusuario = :id");
$exists->execute([':id' => $id]);
if (!$exists->fetch()) Response::error('Usuario no encontrado.', 404);

// Marcar la alerta
$pdo->prepare("UPDATE usuarios SET
                  dt_alerta_foto      = NOW(),
                  n_idusuario_alerto  = :alertador,
                  t_motivo_alerta     = :motivo
               WHERE n_idusuario = :id")
    ->execute([
        ':alertador' => $user['n_idusuario'],
        ':motivo'    => substr($motivo, 0, 300),
        ':id'        => $id
    ]);

// Crear notificación in-app al usuario
Notificador::notificar($id, 'alerta_foto',
    'Actualiza tu foto de perfil',
    $motivo,
    'Admin-Foto-Perfil.html', $id);

// Auditar
Auditor::registrar('usuarios', 'alertar_foto', $id, $user['n_idusuario'],
    'Alerta de foto enviada: ' . $motivo);

Response::json(null, 200, 'Alerta enviada al usuario.');
