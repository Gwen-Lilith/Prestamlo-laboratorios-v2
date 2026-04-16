<?php
/**
 * Endpoint: Listar solicitudes de préstamo
 * Método: GET
 * Parámetros: ?estado=pendiente|aprobada|en_curso|finalizada|rechazada|cancelada
 *             &usuario=ID  (filtrar por usuario específico, útil para profesores)
 *             &buscar=texto
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$estado  = $_GET['estado'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$buscar  = $_GET['buscar'] ?? '';
$currentUser = Auth::currentUser();

$sql = "SELECT sp.*, 
        u.t_nombres, u.t_apellidos, u.t_correo, u.t_codigoinstitucional,
        l.t_nombre AS laboratorio_nombre,
        GROUP_CONCAT(e.t_nombre SEPARATOR ', ') AS elementos_nombres
        FROM solicitudes_prestamo sp
        JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
        JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
        LEFT JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
        LEFT JOIN elementos e ON e.n_idelemento = se.n_idelemento
        WHERE 1=1";
$params = [];

// Si es profesor, solo puede ver sus propias solicitudes
if (in_array('profesor', $currentUser['roles']) && !in_array('administrador', $currentUser['roles']) && !in_array('auxiliar_tecnico', $currentUser['roles'])) {
    $sql .= " AND sp.n_idusuario = :miid";
    $params[':miid'] = $currentUser['n_idusuario'];
} elseif (!empty($usuario)) {
    $sql .= " AND sp.n_idusuario = :uid";
    $params[':uid'] = $usuario;
}

if (!empty($estado)) {
    $sql .= " AND sp.t_estado = :estado";
    $params[':estado'] = $estado;
}

if (!empty($buscar)) {
    $sql .= " AND (u.t_nombres LIKE :b1 OR u.t_apellidos LIKE :b2 OR l.t_nombre LIKE :b3)";
    $params[':b1'] = "%$buscar%";
    $params[':b2'] = "%$buscar%";
    $params[':b3'] = "%$buscar%";
}

$sql .= " GROUP BY sp.n_idsolicitud ORDER BY sp.dt_fechacreacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
