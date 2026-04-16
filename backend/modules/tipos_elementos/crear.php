<?php
/**
 * Endpoint: Crear tipo de elemento
 * Método: POST | Body: { nombre, descripcion?, unidadmedida? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$nombre = Validator::obtenerCampo($data, 'nombre');
$desc   = Validator::obtenerCampo($data, 'descripcion');
$unidad = Validator::obtenerCampo($data, 'unidadmedida') ?: 'unidad';

if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare("INSERT INTO tipos_elementos (t_nombre, t_descripcion, t_unidadmedida) VALUES (:n, :d, :u)");
$stmt->execute([':n' => $nombre, ':d' => $desc, ':u' => $unidad]);
Response::json(['n_idtipoelemento' => (int)$pdo->lastInsertId()], 201, 'Tipo de elemento creado.');
