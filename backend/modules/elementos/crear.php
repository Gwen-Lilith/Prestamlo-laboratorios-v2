<?php
/**
 * Endpoint: Crear elemento
 * Método: POST
 * Body: { nombre, numeroinventario, marca?, modelo?, descripcion?, observaciones?, idlaboratorio, idtipoelemento, estado? }
 * Genera t_codigoqr automáticamente
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$nombre      = Validator::obtenerCampo($data, 'nombre');
$inventario  = Validator::obtenerCampo($data, 'numeroinventario');
$marca       = Validator::obtenerCampo($data, 'marca');
$modelo      = Validator::obtenerCampo($data, 'modelo');
$descripcion = Validator::obtenerCampo($data, 'descripcion');
$observ      = Validator::obtenerCampo($data, 'observaciones');
$idLab       = $data['idlaboratorio'] ?? 0;
$idTipo      = $data['idtipoelemento'] ?? 0;
$estado      = Validator::obtenerCampo($data, 'estado') ?: 'disponible';

if (empty($nombre)) Response::error('El nombre del elemento es obligatorio.');
if (!Validator::validarEntero($idLab)) Response::error('Laboratorio inválido.');
if (!Validator::validarEntero($idTipo)) Response::error('Tipo de elemento inválido.');

$estadosValidos = ['disponible', 'prestado', 'mantenimiento', 'dado_de_baja'];
if (!Validator::validarEnSet($estado, $estadosValidos)) {
    Response::error('Estado no válido.');
}

$pdo = Database::getConnection();

$sql = "INSERT INTO elementos (n_idlaboratorio, n_idtipoelemento, t_nombre, t_numeroinventario, 
        t_marca, t_modelo, t_descripcion, t_observaciones, t_estado) 
        VALUES (:lab, :tipo, :nombre, :inv, :marca, :modelo, :desc, :obs, :estado)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':lab' => $idLab, ':tipo' => $idTipo, ':nombre' => $nombre, ':inv' => $inventario,
    ':marca' => $marca, ':modelo' => $modelo, ':desc' => $descripcion,
    ':obs' => $observ, ':estado' => $estado
]);
$nuevoId = $pdo->lastInsertId();

// Generar código QR automáticamente (hash corto del id + timestamp)
$codigoQr = 'QR-' . $nuevoId . '-' . substr(md5($nuevoId . time()), 0, 8);
$pdo->prepare("UPDATE elementos SET t_codigoqr = :qr WHERE n_idelemento = :id")
    ->execute([':qr' => $codigoQr, ':id' => $nuevoId]);

Response::json(['n_idelemento' => (int)$nuevoId, 't_codigoqr' => $codigoQr], 201, 'Elemento creado correctamente.');
