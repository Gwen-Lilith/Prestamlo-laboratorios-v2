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

// Verificar que el usuario exista y traer si ya hay alerta vigente
$exists = $pdo->prepare("SELECT n_idusuario, dt_alerta_foto, t_motivo_alerta FROM usuarios WHERE n_idusuario = :id");
$exists->execute([':id' => $id]);
$prev = $exists->fetch();
if (!$prev) Response::error('Usuario no encontrado.', 404);

// Si ya existía una alerta, mantenemos la fecha original (no reseteamos la
// gracia) y solo permitimos al admin actualizar el motivo. Si el admin quiere
// resetear el plazo, debe primero cancelar la alerta y enviar una nueva.
$yaHabiaAlerta = !empty($prev['dt_alerta_foto']);
if ($yaHabiaAlerta) {
    // Solo actualiza motivo y quien alertó por última vez
    $pdo->prepare("UPDATE usuarios SET
                      n_idusuario_alerto  = :alertador,
                      t_motivo_alerta     = :motivo
                   WHERE n_idusuario = :id")
        ->execute([
            ':alertador' => $user['n_idusuario'],
            ':motivo'    => substr($motivo, 0, 300),
            ':id'        => $id
        ]);
} else {
    // Primera alerta — registrar fecha (inicia plazo de gracia de 7 días)
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
}

// Notificación in-app al usuario afectado (sea alerta nueva o motivo actualizado).
// El link apunta al panel del USUARIO para que pueda actualizar su foto.
$tituloNotif = $yaHabiaAlerta ? 'Motivo de alerta de foto actualizado' : 'Actualiza tu foto de perfil';
Notificador::notificar($id, 'alerta_foto', $tituloNotif, $motivo,
    'dashboard-usuario.html', $id);

// Auditar diferenciando los dos casos para trazabilidad
Auditor::registrar('usuarios',
    $yaHabiaAlerta ? 'modificar_alerta_foto' : 'alertar_foto',
    $id, $user['n_idusuario'],
    ($yaHabiaAlerta ? 'Motivo de alerta actualizado: ' : 'Alerta de foto enviada: ') . $motivo,
    ['antes'   => ['t_motivo_alerta' => $prev['t_motivo_alerta']],
     'despues' => ['t_motivo_alerta' => $motivo]]);

Response::json(null, 200,
    $yaHabiaAlerta ? 'Motivo de la alerta actualizado.' : 'Alerta enviada al usuario.');
