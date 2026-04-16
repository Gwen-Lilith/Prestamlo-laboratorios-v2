<?php
/**
 * Endpoint: Crear laboratorio
 * Método: POST
 * Body: { nombre, codigo?, ubicacion?, descripcion?, capacidad? }
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
$codigo      = Validator::obtenerCampo($data, 'codigo');
$ubicacion   = Validator::obtenerCampo($data, 'ubicacion');
$descripcion = Validator::obtenerCampo($data, 'descripcion');
$capacidad   = $data['capacidad'] ?? null;

if (empty($nombre)) Response::error('El nombre del laboratorio es obligatorio.');

$pdo = Database::getConnection();
$sql = "INSERT INTO laboratorios (t_nombre, t_codigo, t_ubicacion, t_descripcion, n_capacidad, n_idusuario) 
        VALUES (:nombre, :codigo, :ubicacion, :descripcion, :capacidad, :usuario)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre'      => $nombre,
    ':codigo'      => $codigo,
    ':ubicacion'   => $ubicacion,
    ':descripcion' => $descripcion,
    ':capacidad'   => $capacidad ? (int)$capacidad : null,
    ':usuario'     => $_SESSION['n_idusuario']
]);

Response::json(['n_idlaboratorio' => (int)$pdo->lastInsertId()], 201, 'Laboratorio creado correctamente.');
