<?php
/**
 * Endpoint: Inactivar elemento (lógico)
 * Método: POST | Body: { id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$pdo->prepare("UPDATE elementos SET t_activo = 'N' WHERE n_idelemento = :id")->execute([':id' => $id]);
Response::json(null, 200, 'Elemento inactivado.');
