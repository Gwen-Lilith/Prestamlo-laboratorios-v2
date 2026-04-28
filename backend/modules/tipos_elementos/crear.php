<?php
/**
 * Endpoint: Crear tipo de elemento
 * Método: POST | Body: { nombre, descripcion?, unidadmedida? }
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
// HU-10.03: limitar longitudes
$nombre = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombre'), 100);
$desc   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'descripcion'), 300);
$unidad = Validator::limitarLongitud(Validator::obtenerCampo($data, 'unidadmedida'), 30) ?: 'unidad';

if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare("INSERT INTO tipos_elementos (t_nombre, t_descripcion, t_unidadmedida) VALUES (:n, :d, :u)");
$stmt->execute([':n' => $nombre, ':d' => $desc, ':u' => $unidad]);
$nuevoId = (int)$pdo->lastInsertId();

// HU-09.03: trazabilidad
Auditor::registrar('tipos_elementos', 'crear', $nuevoId, $currentUser['n_idusuario'],
    "Tipo de elemento '$nombre' creado",
    ['despues' => ['t_nombre' => $nombre, 't_unidadmedida' => $unidad]]);

Response::json(['n_idtipoelemento' => $nuevoId], 201, 'Tipo de elemento creado.');
