<?php
/**
 * Endpoint: Cambiar estado de un elemento
 * Método: POST
 * Body: { id, estado: "disponible"|"prestado"|"mantenimiento"|"dado_de_baja" }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data   = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
$estado = Validator::obtenerCampo($data, 'estado');

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$estadosValidos = ['disponible', 'prestado', 'mantenimiento', 'dado_de_baja'];
if (!Validator::validarEnSet($estado, $estadosValidos)) {
    Response::error('Estado no válido. Opciones: ' . implode(', ', $estadosValidos));
}

$pdo = Database::getConnection();
$pdo->prepare("UPDATE elementos SET t_estado = :estado WHERE n_idelemento = :id")
    ->execute([':estado' => $estado, ':id' => $id]);

Response::json(null, 200, "Estado del elemento cambiado a '$estado'.");
