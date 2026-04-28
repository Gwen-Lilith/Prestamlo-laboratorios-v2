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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// Aceptar POST/PATCH/PUT para REST
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'PUT'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data   = Validator::obtenerBodyJSON();
$id     = $data['id'] ?? 0;
$estado = Validator::obtenerCampo($data, 'estado');

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$estadosValidos = ['disponible', 'prestado', 'mantenimiento', 'dado_de_baja'];
if (!Validator::validarEnSet($estado, $estadosValidos)) {
    Response::error('Estado no válido. Opciones: ' . implode(', ', $estadosValidos));
}

$pdo = Database::getConnection();
// Capturar estado anterior para registrar el diff
$ant = $pdo->prepare("SELECT t_estado, t_nombre FROM elementos WHERE n_idelemento = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();
$estadoAnt = $antData['t_estado'] ?? null;
$nombreAnt = $antData['t_nombre'] ?? 'elemento';

$pdo->prepare("UPDATE elementos SET t_estado = :estado WHERE n_idelemento = :id")
    ->execute([':estado' => $estado, ':id' => $id]);

// HU-09.03: dejar trazabilidad del cambio de estado
Auditor::registrar('elementos', 'cambiar_estado', (int)$id, $currentUser['n_idusuario'],
    "Estado del elemento '$nombreAnt' cambiado de '$estadoAnt' a '$estado'",
    ['antes' => ['t_estado' => $estadoAnt], 'despues' => ['t_estado' => $estado]]);

Response::json(null, 200, "Estado del elemento cambiado a '$estado'.");
