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
$id   = $data['id'] ?? 0;
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

// Si admin elimina foto de OTRO usuario, debe haber alerta vigente con 7+ días.
if ($esAdmin && !$esDueno) {
    if (empty($u['dt_alerta_foto'])) {
        Response::error('Primero debes enviar una alerta antes de eliminar la foto.');
    }
    $diasTranscurridos = floor((time() - strtotime($u['dt_alerta_foto'])) / 86400);
    if ($diasTranscurridos < 7) {
        $faltan = 7 - $diasTranscurridos;
        Response::error("Faltan $faltan día(s) de gracia desde la alerta. Aún no puedes eliminar la foto.");
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
    Notificador::notificar($id, 'alerta_foto',
        'Tu foto de perfil fue eliminada',
        'Un administrador eliminó tu foto de perfil. Por favor súbela de nuevo cumpliendo los requisitos.',
        'Admin-Foto-Perfil.html', $id);
}

// Auditar
Auditor::registrar('usuarios',
    $esDueno ? 'eliminar_foto_propia' : 'eliminar_foto_admin',
    $id, $user['n_idusuario'],
    'Foto de perfil eliminada' . ($esDueno ? ' por el propio usuario' : ' por admin'));

Response::json(null, 200, 'Foto eliminada correctamente.');
