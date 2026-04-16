<?php
/**
 * Endpoint: Listar consumibles
 * Método: GET | Parámetros: ?laboratorio=ID&buscar=texto
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$lab = $_GET['laboratorio'] ?? '';
$buscar = $_GET['buscar'] ?? '';

$sql = "SELECT c.*, l.t_nombre AS laboratorio_nombre
        FROM consumibles c
        JOIN laboratorios l ON l.n_idlaboratorio = c.n_idlaboratorio
        WHERE c.t_activo = 'S'";
$params = [];

if (!empty($lab)) { $sql .= " AND c.n_idlaboratorio = :lab"; $params[':lab'] = $lab; }
if (!empty($buscar)) { $sql .= " AND c.t_nombre LIKE :b"; $params[':b'] = "%$buscar%"; }

$sql .= " ORDER BY c.t_nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
