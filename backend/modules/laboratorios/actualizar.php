<?php
/**
 * Endpoint: Actualizar laboratorio
 * Método: POST
 * Body: { id, nombre, codigo?, ubicacion?, descripcion?, capacidad? }
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
$codigo      = Validator::obtenerCampo($data, 'codigo');
$ubicacion   = Validator::obtenerCampo($data, 'ubicacion');
$descripcion = Validator::obtenerCampo($data, 'descripcion');
$capacidad   = $data['capacidad'] ?? null;

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
$sql = "UPDATE laboratorios SET t_nombre = :nombre, t_codigo = :codigo, t_ubicacion = :ubicacion, 
        t_descripcion = :descripcion, n_capacidad = :capacidad WHERE n_idlaboratorio = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre' => $nombre, ':codigo' => $codigo, ':ubicacion' => $ubicacion,
    ':descripcion' => $descripcion, ':capacidad' => $capacidad ? (int)$capacidad : null, ':id' => $id
]);
Response::json(null, 200, 'Laboratorio actualizado.');
