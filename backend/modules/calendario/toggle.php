<?php
/**
 * Endpoint: Marcar/desmarcar un día como no hábil (toggle)
 * Método: POST | Body: { fecha: "YYYY-MM-DD", motivo?, descripcion? }
 * Solo admin/auxiliar pueden modificar el calendario.
 * Si la fecha ya existe se elimina (desmarcar). Si no existe se crea.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$fecha      = Validator::obtenerCampo($data, 'fecha');
$motivo     = Validator::obtenerCampo($data, 'motivo');
$desc       = Validator::obtenerCampo($data, 'descripcion');
$currentUser = Auth::currentUser();

if (empty($fecha) || !Validator::validarFecha($fecha)) {
    Response::error('Fecha inválida. Formato: YYYY-MM-DD');
}
if (empty($motivo)) $motivo = 'Marcado manual';

$pdo = Database::getConnection();

// ¿Ya existe?
$stmt = $pdo->prepare("SELECT n_iddia FROM dias_no_habiles WHERE dt_fecha = :f");
$stmt->execute([':f' => $fecha]);
$row = $stmt->fetch();

if ($row) {
    // Desmarcar
    $pdo->prepare("DELETE FROM dias_no_habiles WHERE n_iddia = :id")
        ->execute([':id' => $row['n_iddia']]);
    Response::json(['accion' => 'eliminado'], 200, 'Día habilitado nuevamente.');
} else {
    // Marcar
    $pdo->prepare("INSERT INTO dias_no_habiles (dt_fecha, t_motivo, t_descripcion, n_idusuario)
                   VALUES (:f, :m, :d, :u)")
        ->execute([
            ':f' => $fecha, ':m' => $motivo,
            ':d' => $desc ?: null, ':u' => $currentUser['n_idusuario']
        ]);
    Response::json(['accion' => 'creado', 'n_iddia' => (int)$pdo->lastInsertId()], 201,
        'Día marcado como no hábil.');
}
