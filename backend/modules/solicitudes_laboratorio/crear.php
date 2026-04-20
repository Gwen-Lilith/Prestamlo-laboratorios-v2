<?php
/**
 * Endpoint: Profesor/usuario solicita la creación de un nuevo laboratorio
 * Método: POST | Body: { nombre, ubicacion?, motivo }
 *
 * La solicitud queda en estado 'pendiente' hasta que un administrador la
 * apruebe (desde el endpoint responder.php).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();

$data = Validator::obtenerBodyJSON();
$nombre    = Validator::obtenerCampo($data, 'nombre');
$ubicacion = Validator::obtenerCampo($data, 'ubicacion');
$motivo    = Validator::obtenerCampo($data, 'motivo');
$currentUser = Auth::currentUser();

if (empty($nombre))  Response::error('El nombre del laboratorio es obligatorio.');
if (empty($motivo))  Response::error('El motivo de la solicitud es obligatorio.');

$pdo = Database::getConnection();
$sql = "INSERT INTO solicitudes_laboratorio
        (t_nombre, t_ubicacion, t_motivo, n_idsolicitante, t_estado)
        VALUES (:nombre, :ubicacion, :motivo, :uid, 'pendiente')";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre'    => $nombre,
    ':ubicacion' => $ubicacion,
    ':motivo'    => $motivo,
    ':uid'       => $currentUser['n_idusuario']
]);

Response::json(['n_idsolicitudlab' => (int)$pdo->lastInsertId()], 201,
    'Solicitud enviada. El administrador recibirá tu petición.');
