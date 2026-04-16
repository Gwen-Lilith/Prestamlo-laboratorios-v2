<?php
/**
 * Endpoint: Listar laboratorios
 * Método: GET
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$sql = "SELECT l.*, 
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_activo = 'S') AS total_elementos
        FROM laboratorios l WHERE l.t_activo = 'S' ORDER BY l.t_nombre ASC";
$stmt = $pdo->query($sql);
Response::json($stmt->fetchAll());
