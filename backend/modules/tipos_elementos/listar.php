<?php
/**
 * Endpoint: Listar tipos de elementos
 * Método: GET
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$sql = "SELECT te.*, 
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idtipoelemento = te.n_idtipoelemento AND e.t_activo = 'S') AS total_elementos
        FROM tipos_elementos te WHERE te.t_activo = 'S' ORDER BY te.t_nombre ASC";
Response::json($pdo->query($sql)->fetchAll());
