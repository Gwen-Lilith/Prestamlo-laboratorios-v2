<?php
/**
 * Endpoint: Listar elementos
 * Método: GET
 * Parámetros: ?laboratorio=ID&estado=disponible|prestado|mantenimiento|dado_de_baja&buscar=texto
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$lab    = $_GET['laboratorio'] ?? '';
$estado = $_GET['estado'] ?? '';
$buscar = $_GET['buscar'] ?? '';

$sql = "SELECT e.*, te.t_nombre AS tipo_nombre, l.t_nombre AS laboratorio_nombre
        FROM elementos e
        JOIN tipos_elementos te ON te.n_idtipoelemento = e.n_idtipoelemento
        JOIN laboratorios l ON l.n_idlaboratorio = e.n_idlaboratorio
        WHERE e.t_activo = 'S'";
$params = [];

if (!empty($lab)) {
    $sql .= " AND e.n_idlaboratorio = :lab";
    $params[':lab'] = $lab;
}
if (!empty($estado)) {
    $sql .= " AND e.t_estado = :estado";
    $params[':estado'] = $estado;
}
if (!empty($buscar)) {
    $sql .= " AND (e.t_nombre LIKE :b1 OR e.t_numeroinventario LIKE :b2 OR e.t_marca LIKE :b3)";
    $params[':b1'] = "%$buscar%";
    $params[':b2'] = "%$buscar%";
    $params[':b3'] = "%$buscar%";
}

$sql .= " ORDER BY e.t_nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
