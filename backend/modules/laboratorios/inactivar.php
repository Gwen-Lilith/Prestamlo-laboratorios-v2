<?php
/**
 * Endpoint: Inactivar laboratorio (lógico)
 * Método: POST | Body: { id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// Aceptar POST/DELETE para REST
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'DELETE'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? ($_GET['id'] ?? 0);
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
// Capturar nombre para descripción de auditoría
$ant = $pdo->prepare("SELECT t_nombre FROM laboratorios WHERE n_idlaboratorio = :id");
$ant->execute([':id' => $id]);
$nombreAnt = $ant->fetchColumn() ?: 'desconocido';

$pdo->prepare("UPDATE laboratorios SET t_activo = 'N' WHERE n_idlaboratorio = :id")->execute([':id' => $id]);

// HU-09.03 + HU-03.05: dejar trazabilidad del soft-delete
Auditor::registrar('laboratorios', 'inactivar', (int)$id, $currentUser['n_idusuario'],
    "Laboratorio '$nombreAnt' inactivado (soft-delete)");

Response::json(null, 200, 'Laboratorio inactivado.');
