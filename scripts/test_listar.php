<?php
require_once __DIR__ . '/backend/config/db.php';
require_once __DIR__ . '/backend/core/Response.php';

try {
    $pdo = Database::getConnection();
    $sql = "SELECT l.*, (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_activo = 'S') AS total_elementos FROM laboratorios l WHERE l.t_activo = 'S' ORDER BY l.t_nombre ASC";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'data' => $data, 'mensaje' => 'OK']);
} catch (Exception $e) {
    echo $e->getMessage();
}
