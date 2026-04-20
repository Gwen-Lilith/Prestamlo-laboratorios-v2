<?php
/**
 * Endpoint: Listar anuncios
 * Método: GET | Parámetros: ?estado=activo|inactivo (opcional)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$estado = $_GET['estado'] ?? '';
$sql = "SELECT a.*, u.t_nombres AS autor_nombres, u.t_apellidos AS autor_apellidos
        FROM anuncios a
        JOIN usuarios u ON u.n_idusuario = a.n_idusuario
        WHERE 1=1";
$params = [];
if ($estado === 'activo' || $estado === 'inactivo') {
    $sql .= " AND a.t_estado = :estado";
    $params[':estado'] = $estado;
}
$sql .= " ORDER BY a.dt_fechapub DESC, a.n_idanuncio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
