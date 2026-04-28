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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
// HU-10.03: limitar longitud para prevenir ataques de buffer y datos malformados
$nombre      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombre'), 150);
$inventario  = Validator::limitarLongitud(Validator::obtenerCampo($data, 'numeroinventario'), 50);
$marca       = Validator::limitarLongitud(Validator::obtenerCampo($data, 'marca'), 80);
$modelo      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'modelo'), 80);
$descripcion = Validator::limitarLongitud(Validator::obtenerCampo($data, 'descripcion'), 500);
$observ      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'observaciones'), 500);
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

// HU-09.03: registrar la creación en auditoría con todos los datos del nuevo elemento
Auditor::registrar('elementos', 'crear', (int)$nuevoId, $currentUser['n_idusuario'],
    "Elemento '$nombre' creado en laboratorio $idLab",
    ['despues' => [
        't_nombre' => $nombre, 't_numeroinventario' => $inventario,
        't_marca' => $marca, 't_modelo' => $modelo,
        'n_idlaboratorio' => $idLab, 'n_idtipoelemento' => $idTipo,
        't_estado' => $estado, 't_codigoqr' => $codigoQr
    ]]);

Response::json(['n_idelemento' => (int)$nuevoId, 't_codigoqr' => $codigoQr], 201, 'Elemento creado correctamente.');
