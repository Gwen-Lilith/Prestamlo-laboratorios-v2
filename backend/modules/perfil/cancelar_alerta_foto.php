<?php
/**
 * Endpoint: Cancelar/quitar la alerta de foto de un usuario (HU-08.10).
 * Método: POST | Body: { id }
 *
 * Solo admin/auxiliar puede revocar la alerta. La foto del usuario NO se
 * elimina; lo único que se hace es limpiar los campos dt_alerta_foto,
 * n_idusuario_alerto y t_motivo_alerta para que el plazo de gracia deje
 * de correr y el usuario ya no vea la advertencia.
 *
 * Casos de uso:
 *   - El admin envió la alerta por error.
 *   - El usuario actualizó la foto y ahora SÍ cumple los requisitos.
 *   - Cambio de criterio: la foto resultó aceptable.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','DELETE'], true)) {
    Response::error('Método no permitido.', 405);
}
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$user = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? ($_GET['id'] ?? 0);
if (!Validator::validarEntero($id)) Response::error('ID de usuario inválido.');

$pdo = Database::getConnection();

// Cargar el estado actual para reportar bien y auditar correctamente
$stmt = $pdo->prepare(
    "SELECT t_correo, t_nombres, t_apellidos, dt_alerta_foto, t_motivo_alerta
     FROM usuarios WHERE n_idusuario = :id"
);
$stmt->execute([':id' => $id]);
$u = $stmt->fetch();
if (!$u) Response::error('Usuario no encontrado.', 404);
if (empty($u['dt_alerta_foto'])) {
    Response::error('Este usuario no tiene una alerta activa para cancelar.');
}

$motivoPrev = $u['t_motivo_alerta'];

// Limpiar la alerta — la foto se conserva intacta
$pdo->prepare("UPDATE usuarios SET
                  dt_alerta_foto      = NULL,
                  n_idusuario_alerto  = NULL,
                  t_motivo_alerta     = NULL
               WHERE n_idusuario = :id")
    ->execute([':id' => $id]);

// Notificar al usuario afectado para que sepa que ya no debe preocuparse
Notificador::notificar($id, 'info',
    'Alerta sobre tu foto retirada',
    'Un administrador retiró la alerta sobre tu foto de perfil. Ya no necesitas tomar acción.',
    'dashboard-usuario.html', $id);

// Auditar — guardamos el motivo previo en el diff por si hay que rastrear
Auditor::registrar('usuarios', 'cancelar_alerta_foto', (int)$id, $user['n_idusuario'],
    "Alerta sobre la foto de '{$u['t_correo']}' retirada por administrador",
    ['antes' => ['t_motivo_alerta' => $motivoPrev, 'dt_alerta_foto' => $u['dt_alerta_foto']],
     'despues' => ['dt_alerta_foto' => null]]);

Response::json(null, 200, 'Alerta retirada correctamente.');
