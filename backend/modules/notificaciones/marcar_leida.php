<?php
/**
 * Endpoint: Marcar notificación(es) como leída(s)
 * Método: POST | Body: { id?: <num>, todas?: true }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();
$user = Auth::currentUser();
$pdo  = Database::getConnection();
$data = Validator::obtenerBodyJSON();

if (isset($data['todas']) && $data['todas']) {
    $pdo->prepare("UPDATE notificaciones SET t_leida='S', dt_fechalectura=NOW()
                   WHERE n_idusuario = :uid AND t_leida='N'")
        ->execute([':uid' => $user['n_idusuario']]);
    Response::json(null, 200, 'Todas marcadas como leídas.');
}

$id = $data['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo->prepare("UPDATE notificaciones SET t_leida='S', dt_fechalectura=NOW()
               WHERE n_idnotificacion = :id AND n_idusuario = :uid")
    ->execute([':id' => $id, ':uid' => $user['n_idusuario']]);

Response::json(null, 200, 'Notificación marcada como leída.');
