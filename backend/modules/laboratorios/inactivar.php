<?php
/**
 * Endpoint: Inactivar laboratorio (lógico)
 * Método: POST | Body: { id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador']);

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$pdo->prepare("UPDATE laboratorios SET t_activo = 'N' WHERE n_idlaboratorio = :id")->execute([':id' => $id]);
Response::json(null, 200, 'Laboratorio inactivado.');
