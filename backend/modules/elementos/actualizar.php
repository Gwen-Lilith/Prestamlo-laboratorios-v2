<?php
/**
 * Endpoint: Actualizar elemento
 * Método: POST
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

// Capturar valores anteriores para el diff de auditoría (HU-09.03)
$antes = $pdo->prepare("SELECT t_nombre, t_numeroinventario, t_marca, t_modelo,
                              t_descripcion, t_observaciones, n_idlaboratorio, n_idtipoelemento
                       FROM elementos WHERE n_idelemento = :id");
$antes->execute([':id' => $id]);
$antesData = $antes->fetch();

$sql = "UPDATE elementos SET t_nombre = :nombre, t_numeroinventario = :inv, t_marca = :marca,
        t_modelo = :modelo, t_descripcion = :desc, t_observaciones = :obs,
        n_idlaboratorio = :lab, n_idtipoelemento = :tipo WHERE n_idelemento = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre' => $nombre, ':inv' => $inventario, ':marca' => $marca, ':modelo' => $modelo,
    ':desc' => $descripcion, ':obs' => $observ, ':lab' => $idLab, ':tipo' => $idTipo, ':id' => $id
]);

// Auditar cambio
$despues = ['t_nombre'=>$nombre,'t_numeroinventario'=>$inventario,'t_marca'=>$marca,
            't_modelo'=>$modelo,'t_descripcion'=>$descripcion,'t_observaciones'=>$observ,
            'n_idlaboratorio'=>$idLab,'n_idtipoelemento'=>$idTipo];
Auditor::registrar('elementos', 'actualizar', $id, $currentUser['n_idusuario'],
    "Elemento '$nombre' actualizado",
    ['antes' => $antesData ?: [], 'despues' => $despues]);

Response::json(null, 200, 'Elemento actualizado.');
