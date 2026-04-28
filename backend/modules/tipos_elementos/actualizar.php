<?php
/**
 * Endpoint: Actualizar tipo de elemento
 * Método: POST | Body: { id, nombre, descripcion?, unidadmedida? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: aceptar POST/PATCH/PUT
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'PUT'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
// HU-10.03: limitar longitudes
$nombre = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombre'), 100);
$desc   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'descripcion'), 300);
$unidad = Validator::limitarLongitud(Validator::obtenerCampo($data, 'unidadmedida'), 30);

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
// Capturar valor anterior para diff
$ant = $pdo->prepare("SELECT t_nombre, t_descripcion, t_unidadmedida FROM tipos_elementos WHERE n_idtipoelemento = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();

$pdo->prepare("UPDATE tipos_elementos SET t_nombre = :n, t_descripcion = :d, t_unidadmedida = :u WHERE n_idtipoelemento = :id")
    ->execute([':n' => $nombre, ':d' => $desc, ':u' => $unidad, ':id' => $id]);

Auditor::registrar('tipos_elementos', 'actualizar', (int)$id, $currentUser['n_idusuario'],
    "Tipo de elemento '$nombre' actualizado",
    ['antes' => $antData ?: [], 'despues' => ['t_nombre' => $nombre, 't_descripcion' => $desc, 't_unidadmedida' => $unidad]]);

Response::json(null, 200, 'Tipo de elemento actualizado.');
