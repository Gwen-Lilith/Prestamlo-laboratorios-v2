<?php
/**
 * Endpoint: Cambiar estado de usuario (inactivación lógica — nunca DELETE físico).
 * Método: POST | PATCH | DELETE
 * Body: { id, activo: "S"|"N"|"R" }
 *
 * Estados (t_activo):
 *   S = activo
 *   N = inactivo / pendiente de aprobación (autoregistro)
 *   R = rechazado por admin (HU-08.04) — no aparece en lista de pendientes
 *       y no puede iniciar sesión, pero queda en BD para historial.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: aceptar POST/PATCH/DELETE (HU-08.04 activar/rechazar usa este mismo endpoint)
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'DELETE'])) {
    Response::error('Método no permitido.', 405);
}

Auth::requireRole(['administrador']);
$currentUser = Auth::currentUser();

$data   = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? ($_GET['id'] ?? 0);
$activo = $data['activo'] ?? ($metodo === 'DELETE' ? 'N' : '');

if (!Validator::validarEntero($id)) {
    Response::error('ID de usuario inválido.');
}

if (!Validator::validarEnSet($activo, ['S', 'N', 'R'])) {
    Response::error('El valor de activo debe ser S (activo), N (inactivo) o R (rechazado).');
}

$pdo = Database::getConnection();
$ant = $pdo->prepare("SELECT t_correo, t_activo FROM usuarios WHERE n_idusuario = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();
$correoAnt = $antData['t_correo'] ?? 'desconocido';
$activoAnt = $antData['t_activo'] ?? null;

$stmt = $pdo->prepare("UPDATE usuarios SET t_activo = :activo WHERE n_idusuario = :id");
$stmt->execute([':activo' => $activo, ':id' => $id]);

$accion = ['S' => 'activado', 'N' => 'inactivado', 'R' => 'rechazado'][$activo] ?? 'modificado';

// HU-09.01 + HU-08.04: trazabilidad de activación/rechazo
$accionAud = ['S' => 'activar', 'N' => 'inactivar', 'R' => 'rechazar'][$activo] ?? 'cambiar_estado';
Auditor::registrar('usuarios', $accionAud,
    (int)$id, $currentUser['n_idusuario'],
    "Usuario '$correoAnt' $accion (de '$activoAnt' a '$activo')",
    ['antes' => ['t_activo' => $activoAnt], 'despues' => ['t_activo' => $activo]]);

Response::json(null, 200, "Usuario $accion correctamente.");
