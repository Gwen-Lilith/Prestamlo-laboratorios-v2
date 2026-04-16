<?php
/**
 * Endpoint: Uso por laboratorio (resumen estadístico)
 * Método: GET
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$pdo = Database::getConnection();

$sql = "SELECT 
        l.n_idlaboratorio, l.t_nombre AS laboratorio,
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_activo = 'S') AS total_elementos,
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_estado = 'disponible' AND e.t_activo = 'S') AS disponibles,
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_estado = 'prestado' AND e.t_activo = 'S') AS prestados,
        (SELECT COUNT(*) FROM elementos e WHERE e.n_idlaboratorio = l.n_idlaboratorio AND e.t_estado = 'mantenimiento' AND e.t_activo = 'S') AS mantenimiento,
        (SELECT COUNT(*) FROM solicitudes_prestamo sp WHERE sp.n_idlaboratorio = l.n_idlaboratorio) AS total_solicitudes,
        (SELECT COUNT(*) FROM solicitudes_prestamo sp WHERE sp.n_idlaboratorio = l.n_idlaboratorio AND sp.t_estado = 'pendiente') AS solicitudes_pendientes
        FROM laboratorios l WHERE l.t_activo = 'S' ORDER BY l.t_nombre";
Response::json($pdo->query($sql)->fetchAll());
