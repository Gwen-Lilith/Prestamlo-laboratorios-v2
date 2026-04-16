<?php
/**
 * Endpoint: Actualizar tipo de elemento
 * Método: POST | Body: { id, nombre, descripcion?, unidadmedida? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
$nombre = Validator::obtenerCampo($data, 'nombre');
$desc   = Validator::obtenerCampo($data, 'descripcion');
$unidad = Validator::obtenerCampo($data, 'unidadmedida');

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
$pdo->prepare("UPDATE tipos_elementos SET t_nombre = :n, t_descripcion = :d, t_unidadmedida = :u WHERE n_idtipoelemento = :id")
    ->execute([':n' => $nombre, ':d' => $desc, ':u' => $unidad, ':id' => $id]);
Response::json(null, 200, 'Tipo de elemento actualizado.');
