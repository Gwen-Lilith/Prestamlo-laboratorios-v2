<?php
/**
 * Endpoint: Marcar notificación(es) como leída(s).
 * Método: POST | PATCH
 * Body: { id?: <num>, todas?: true }
 *
 * "Todas" persiste en BD: el UPDATE marca t_leida='S' + dt_fechalectura=NOW()
 * para todas las no-leídas del usuario actual. La próxima vez que el badge
 * pregunte por no-leídas devolverá 0 (no se repiten). Si después se generan
 * notificaciones nuevas (otro usuario sube foto, cambio de estado, etc.),
 * esas tendrán t_leida='N' y volverán a contar para el badge — comportamiento
 * correcto: leídas se quedan leídas, nuevas avisan.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH'])) Response::error('Método no permitido.', 405);
Auth::requireLogin();
$user = Auth::currentUser();
$pdo  = Database::getConnection();
$data = Validator::obtenerBodyJSON();

if (isset($data['todas']) && $data['todas']) {
    $stmt = $pdo->prepare("UPDATE notificaciones SET t_leida='S', dt_fechalectura=NOW()
                   WHERE n_idusuario = :uid AND t_leida='N'");
    $stmt->execute([':uid' => $user['n_idusuario']]);
    // Devolvemos cuántas se marcaron para feedback al frontend
    Response::json(['marcadas' => $stmt->rowCount()], 200, 'Todas marcadas como leídas.');
}

$id = $data['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$stmt = $pdo->prepare("UPDATE notificaciones SET t_leida='S', dt_fechalectura=NOW()
               WHERE n_idnotificacion = :id AND n_idusuario = :uid");
$stmt->execute([':id' => $id, ':uid' => $user['n_idusuario']]);

Response::json(['marcadas' => $stmt->rowCount()], 200, 'Notificación marcada como leída.');
