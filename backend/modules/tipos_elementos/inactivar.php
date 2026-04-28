<?php
/**
 * Endpoint: Inactivar tipo de elemento (lógico)
 * Método: POST | Body: { id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: aceptar POST/DELETE
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'DELETE'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? ($_GET['id'] ?? 0);
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$ant = $pdo->prepare("SELECT t_nombre FROM tipos_elementos WHERE n_idtipoelemento = :id");
$ant->execute([':id' => $id]);
$nombreAnt = $ant->fetchColumn() ?: 'desconocido';

$pdo->prepare("UPDATE tipos_elementos SET t_activo = 'N' WHERE n_idtipoelemento = :id")->execute([':id' => $id]);

Auditor::registrar('tipos_elementos', 'inactivar', (int)$id, $currentUser['n_idusuario'],
    "Tipo de elemento '$nombreAnt' inactivado");

Response::json(null, 200, 'Tipo de elemento inactivado.');
