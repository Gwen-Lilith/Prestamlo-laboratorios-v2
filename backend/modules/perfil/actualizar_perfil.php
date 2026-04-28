<?php
/**
 * Endpoint: Actualizar datos del perfil propio (HU-08.07).
 * Método: POST | PUT
 * Body: {
 *   nombres?, apellidos?, telefono?, telefono_apikey?, canal_preferido?,
 *   password_actual?, password_nuevo?
 * }
 *
 * Cualquier usuario logueado puede actualizar SUS PROPIOS datos.
 * Si quiere cambiar la contraseña, debe enviar password_actual válida.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST','PUT'], true)) {
    Response::error('Método no permitido.', 405);
}
Auth::requireLogin();
$user = Auth::currentUser();
$uid  = (int)$user['n_idusuario'];

$data = Validator::obtenerBodyJSON();
$nombres   = Validator::obtenerCampo($data, 'nombres');
$apellidos = Validator::obtenerCampo($data, 'apellidos');
$tel       = Validator::obtenerCampo($data, 'telefono');
$apikey    = Validator::obtenerCampo($data, 'telefono_apikey');
$canal     = Validator::obtenerCampo($data, 'canal_preferido');
$passAct   = $data['password_actual'] ?? '';
$passNew   = $data['password_nuevo']  ?? '';

$canalesOk = ['inapp','correo','whatsapp','todos'];
$pdo = Database::getConnection();

// Cambio de contraseña (opcional, requiere validación)
if (!empty($passNew)) {
    if (strlen($passNew) < 4) Response::error('La contraseña debe tener al menos 4 caracteres.');
    if (empty($passAct))      Response::error('Debes proporcionar tu contraseña actual.');
    $stmt = $pdo->prepare("SELECT t_contrasena FROM usuarios WHERE n_idusuario = :id");
    $stmt->execute([':id' => $uid]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($passAct, $row['t_contrasena'])) {
        Response::error('Contraseña actual incorrecta.', 403);
    }
    $hash = password_hash($passNew, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE usuarios SET t_contrasena = :h WHERE n_idusuario = :id")
        ->execute([':h' => $hash, ':id' => $uid]);
    Auditor::registrar('usuarios', 'cambiar_password', $uid, $uid, 'Contraseña actualizada por el propio usuario');
}

// Actualizar datos generales si se enviaron
$sets = []; $params = [':id' => $uid];
if (!empty($nombres))   { $sets[] = 't_nombres = :n';        $params[':n'] = mb_substr($nombres, 0, 100, 'UTF-8'); }
if (!empty($apellidos)) { $sets[] = 't_apellidos = :a';      $params[':a'] = mb_substr($apellidos, 0, 100, 'UTF-8'); }
if (isset($data['telefono']))         { $sets[] = 't_telefono = :t';       $params[':t'] = mb_substr($tel ?: '', 0, 20, 'UTF-8'); }
if (isset($data['telefono_apikey']))  { $sets[] = 't_telefono_apikey = :k'; $params[':k'] = mb_substr($apikey ?: '', 0, 100, 'UTF-8'); }
if (!empty($canal) && in_array($canal, $canalesOk, true)) {
    $sets[] = 't_canal_preferido = :c';
    $params[':c'] = $canal;
}

if (!empty($sets)) {
    $sql = "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE n_idusuario = :id";
    $pdo->prepare($sql)->execute($params);
    Auditor::registrar('usuarios', 'actualizar_perfil', $uid, $uid,
        'Perfil propio actualizado: ' . implode(', ', array_keys($params)));
}

Response::json(null, 200, 'Perfil actualizado correctamente.');
