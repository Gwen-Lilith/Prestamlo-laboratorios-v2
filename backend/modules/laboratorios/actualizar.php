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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// Aceptar POST/PATCH/PUT para REST
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'PUT'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id          = $data['id'] ?? 0;
// HU-10.03: limitar longitud
$nombre      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombre'), 120);
$codigo      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'codigo'), 30);
$ubicacion   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'ubicacion'), 200);
$descripcion = Validator::limitarLongitud(Validator::obtenerCampo($data, 'descripcion'), 500);
$capacidad   = $data['capacidad'] ?? null;

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($nombre)) Response::error('El nombre es obligatorio.');

$pdo = Database::getConnection();
// Capturar valores anteriores para diff de auditoría
$ant = $pdo->prepare("SELECT t_nombre, t_codigo, t_ubicacion, t_descripcion, n_capacidad
                      FROM laboratorios WHERE n_idlaboratorio = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();

$sql = "UPDATE laboratorios SET t_nombre = :nombre, t_codigo = :codigo, t_ubicacion = :ubicacion,
        t_descripcion = :descripcion, n_capacidad = :capacidad WHERE n_idlaboratorio = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre' => $nombre, ':codigo' => $codigo, ':ubicacion' => $ubicacion,
    ':descripcion' => $descripcion, ':capacidad' => $capacidad ? (int)$capacidad : null, ':id' => $id
]);

// HU-09.03: registrar el cambio con diff antes/después
Auditor::registrar('laboratorios', 'actualizar', (int)$id, $currentUser['n_idusuario'],
    "Laboratorio '$nombre' actualizado",
    ['antes' => $antData ?: [], 'despues' => [
        't_nombre' => $nombre, 't_codigo' => $codigo, 't_ubicacion' => $ubicacion,
        't_descripcion' => $descripcion, 'n_capacidad' => $capacidad
    ]]);

Response::json(null, 200, 'Laboratorio actualizado.');
