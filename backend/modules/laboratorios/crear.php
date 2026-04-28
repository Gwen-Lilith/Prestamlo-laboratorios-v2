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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
// HU-10.03: limitar longitudes para evitar inyección masiva o datos inconsistentes
$nombre      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombre'), 120);
$codigo      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'codigo'), 30);
$ubicacion   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'ubicacion'), 200);
$descripcion = Validator::limitarLongitud(Validator::obtenerCampo($data, 'descripcion'), 500);
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
$nuevoId = (int)$pdo->lastInsertId();

// HU-09.03: dejar trazabilidad de creación de laboratorio
Auditor::registrar('laboratorios', 'crear', $nuevoId, $currentUser['n_idusuario'],
    "Laboratorio '$nombre' (codigo: $codigo) creado",
    ['despues' => ['t_nombre' => $nombre, 't_codigo' => $codigo,
                   't_ubicacion' => $ubicacion, 'n_capacidad' => $capacidad]]);

Response::json(['n_idlaboratorio' => $nuevoId], 201, 'Laboratorio creado correctamente.');
