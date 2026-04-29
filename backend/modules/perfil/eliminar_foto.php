<?php
/**
 * Endpoint: Eliminar foto de perfil de un usuario (HU-08.10)
 * Método: POST | Body: { id }
 *
 * Solo admin/auxiliar puede eliminar la foto de OTRO usuario, y solo si
 * ya pasaron 7 días desde que se le envió la alerta y aún no la cambió.
 * El usuario puede borrar su propia foto en cualquier momento.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();
$user = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
// HU-08.10: 'forzar' permite al admin saltar el plazo de gracia cuando lo
// considere urgente (foto ofensiva, identidad falsa, etc.). La acción queda
// igualmente registrada en auditoría para responsabilidad.
$forzar = !empty($data['forzar']);
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT t_fotoperfil, dt_alerta_foto FROM usuarios WHERE n_idusuario = :id"
);
$stmt->execute([':id' => $id]);
$u = $stmt->fetch();
if (!$u)              Response::error('Usuario no encontrado.', 404);
if (!$u['t_fotoperfil']) Response::error('El usuario no tiene foto para eliminar.');

$esAdmin = in_array('administrador', $user['roles']) || in_array('auxiliar_tecnico', $user['roles']);
$esDueno = (int)$user['n_idusuario'] === (int)$id;

if (!$esAdmin && !$esDueno) {
    Response::error('No tiene permisos para eliminar esta foto.', 403);
}

// Si admin elimina foto de OTRO usuario:
// - Sin 'forzar': debe haber alerta previa Y 7 días de gracia transcurridos
// - Con 'forzar': se permite inmediato pero queda registrado como eliminación forzada
if ($esAdmin && !$esDueno && !$forzar) {
    if (empty($u['dt_alerta_foto'])) {
        Response::error('Primero debes enviar una alerta antes de eliminar la foto. Si es urgente, envía la solicitud con forzar=true.');
    }
    $diasTranscurridos = floor((time() - strtotime($u['dt_alerta_foto'])) / 86400);
    if ($diasTranscurridos < 7) {
        $faltan = 7 - $diasTranscurridos;
        Response::error("Faltan $faltan día(s) de gracia desde la alerta. Si es urgente puedes saltar la gracia con 'forzar'.");
    }
}

// Eliminar archivo físico si existe (best-effort)
$rutaFisica = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $u['t_fotoperfil']);
if (is_file($rutaFisica)) @unlink($rutaFisica);

// Limpiar campos en BD
$pdo->prepare("UPDATE usuarios SET
                  t_fotoperfil       = NULL,
                  dt_alerta_foto     = NULL,
                  n_idusuario_alerto = NULL,
                  t_motivo_alerta    = NULL
               WHERE n_idusuario = :id")
    ->execute([':id' => $id]);

// Notificar al usuario afectado (solo si quien borra es otro)
if (!$esDueno) {
    $detalleNotif = $forzar
        ? 'Un administrador eliminó tu foto de perfil de manera inmediata. Por favor súbela de nuevo cumpliendo los requisitos institucionales.'
        : 'Un administrador eliminó tu foto de perfil tras el plazo de gracia. Por favor súbela de nuevo cumpliendo los requisitos.';
    Notificador::notificar($id, 'alerta_foto',
        'Tu foto de perfil fue eliminada',
        $detalleNotif,
        'dashboard-usuario.html', $id);
}

// Auditar — diferenciamos eliminación normal vs forzada para tener trazabilidad
$accionAud = $esDueno
    ? 'eliminar_foto_propia'
    : ($forzar ? 'eliminar_foto_admin_forzada' : 'eliminar_foto_admin');
$descAud = $esDueno
    ? 'Foto de perfil eliminada por el propio usuario'
    : ($forzar
        ? 'Foto de perfil ELIMINADA INMEDIATAMENTE por admin (saltando gracia de 7 días)'
        : 'Foto de perfil eliminada por admin tras plazo de gracia');
Auditor::registrar('usuarios', $accionAud, $id, $user['n_idusuario'], $descAud);

Response::json(null, 200, 'Foto eliminada correctamente.');
