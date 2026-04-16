<?php
/**
 * Endpoint: Actualizar elemento
 * Método: POST
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id          = $data['id'] ?? 0;
$nombre      = Validator::obtenerCampo($data, 'nombre');
$inventario  = Validator::obtenerCampo($data, 'numeroinventario');
$marca       = Validator::obtenerCampo($data, 'marca');
$modelo      = Validator::obtenerCampo($data, 'modelo');
$descripcion = Validator::obtenerCampo($data, 'descripcion');
$observ      = Validator::obtenerCampo($data, 'observaciones');
$idLab       = $data['idlaboratorio'] ?? 0;
$idTipo      = $data['idtipoelemento'] ?? 0;

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
$sql = "UPDATE elementos SET t_nombre = :nombre, t_numeroinventario = :inv, t_marca = :marca,
        t_modelo = :modelo, t_descripcion = :desc, t_observaciones = :obs,
        n_idlaboratorio = :lab, n_idtipoelemento = :tipo WHERE n_idelemento = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre' => $nombre, ':inv' => $inventario, ':marca' => $marca, ':modelo' => $modelo,
    ':desc' => $descripcion, ':obs' => $observ, ':lab' => $idLab, ':tipo' => $idTipo, ':id' => $id
]);
Response::json(null, 200, 'Elemento actualizado.');
