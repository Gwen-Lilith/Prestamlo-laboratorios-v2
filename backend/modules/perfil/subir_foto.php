<?php
/**
 * Endpoint: Subir foto de perfil del usuario actual.
 * Método: POST (multipart/form-data)
 * Campo del form: "foto" (archivo imagen). Opcional: "data" (dataURL base64).
 *
 * Guarda en assets/fotos/<idusuario>_<timestamp>.<ext> y actualiza la
 * columna usuarios.t_fotoperfil con la ruta relativa.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();
$user = Auth::currentUser();
$uid  = (int)$user['n_idusuario'];

// Carpeta destino (relativa a la raíz del proyecto).
$carpetaAbs = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fotos';
if (!is_dir($carpetaAbs)) @mkdir($carpetaAbs, 0755, true);
if (!is_dir($carpetaAbs) || !is_writable($carpetaAbs)) {
    Response::error('No se puede escribir en la carpeta de fotos.', 500);
}

$extPermitidas = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
$rutaRelativa  = null;

// Caso 1: upload multipart/form-data con campo "foto"
if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $tmp   = $_FILES['foto']['tmp_name'];
    $orig  = $_FILES['foto']['name'] ?? 'foto';
    $size  = (int)($_FILES['foto']['size'] ?? 0);
    if ($size > 3 * 1024 * 1024) Response::error('La imagen supera 3 MB.');

    $info = @getimagesize($tmp);
    if (!$info) Response::error('El archivo no es una imagen válida.');

    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!isset($extPermitidas[$ext])) Response::error('Extensión no permitida. Usa JPG, PNG, WEBP o GIF.');
    $ext = $extPermitidas[$ext];

    $nombre = "u{$uid}_" . time() . ".$ext";
    $destino = $carpetaAbs . DIRECTORY_SEPARATOR . $nombre;
    if (!move_uploaded_file($tmp, $destino)) {
        Response::error('No se pudo guardar la imagen.', 500);
    }
    $rutaRelativa = 'assets/fotos/' . $nombre;
}

// Caso 2: dataURL base64 enviada por JSON/form-encoded como "data"
if ($rutaRelativa === null && !empty($_POST['data'])) {
    $data = $_POST['data'];
    if (preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#i', $data, $m)) {
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $bin = base64_decode($m[2]);
        if ($bin === false || strlen($bin) === 0) Response::error('Imagen base64 inválida.');
        if (strlen($bin) > 3 * 1024 * 1024) Response::error('La imagen supera 3 MB.');
        $nombre = "u{$uid}_" . time() . ".$ext";
        $destino = $carpetaAbs . DIRECTORY_SEPARATOR . $nombre;
        if (file_put_contents($destino, $bin) === false) {
            Response::error('No se pudo guardar la imagen.', 500);
        }
        $rutaRelativa = 'assets/fotos/' . $nombre;
    } else {
        Response::error('Formato de imagen no reconocido (se esperaba dataURL image/*).');
    }
}

if ($rutaRelativa === null) Response::error('No se recibió ninguna imagen.');

// Guardar la ruta relativa en la BD y devolverla al cliente.
$pdo = Database::getConnection();
$pdo->prepare("UPDATE usuarios SET t_fotoperfil = :p WHERE n_idusuario = :id")
    ->execute([':p' => $rutaRelativa, ':id' => $uid]);

Response::json(['ruta' => $rutaRelativa], 200, 'Foto de perfil actualizada.');
