<?php
/**
 * Endpoint: Eliminar anuncio (DELETE físico, son informativos)
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
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id = $data['id'] ?? ($_GET['id'] ?? 0);
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
// Capturar título antes para auditoría
$ant = $pdo->prepare("SELECT t_titulo, t_tipo, t_estado FROM anuncios WHERE n_idanuncio = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();

$pdo->prepare("DELETE FROM anuncios WHERE n_idanuncio = :id")->execute([':id' => $id]);

// HU-09.03: registrar la eliminación con snapshot del estado previo
Auditor::registrar('anuncios', 'eliminar', (int)$id, $currentUser['n_idusuario'],
    "Anuncio '" . ($antData['t_titulo'] ?? 'desconocido') . "' eliminado físicamente",
    ['antes' => $antData ?: []]);

Response::json(null, 200, 'Anuncio eliminado.');
