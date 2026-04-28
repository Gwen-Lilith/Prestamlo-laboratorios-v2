<?php
/**
 * Endpoint: Listar usuarios
 * Método: GET
 * Parámetros opcionales: ?estado=S|N&rol=administrador|auxiliar_tecnico|profesor&buscar=texto
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$pdo = Database::getConnection();

// Filtros opcionales
$estado = $_GET['estado'] ?? '';
$rol    = $_GET['rol'] ?? '';
$buscar = $_GET['buscar'] ?? '';

$sql = "SELECT u.n_idusuario, u.t_codigoinstitucional, u.t_nombres, u.t_apellidos,
               u.t_correo, u.t_activo, u.dt_fechacreacion, u.t_fotoperfil,
               u.dt_alerta_foto, u.t_motivo_alerta,
               GROUP_CONCAT(r.t_nombrerol SEPARATOR ',') AS roles
        FROM usuarios u
        LEFT JOIN usuarios_roles ur ON ur.n_idusuario = u.n_idusuario AND ur.t_activo = 'S'
        LEFT JOIN roles r ON r.n_idrol = ur.n_idrol
        WHERE 1=1";

$params = [];

if ($estado === 'S' || $estado === 'N') {
    $sql .= " AND u.t_activo = :estado";
    $params[':estado'] = $estado;
}

if (!empty($buscar)) {
    $sql .= " AND (u.t_nombres LIKE :buscar OR u.t_apellidos LIKE :buscar2 
              OR u.t_correo LIKE :buscar3 OR u.t_codigoinstitucional LIKE :buscar4)";
    $params[':buscar']  = "%$buscar%";
    $params[':buscar2'] = "%$buscar%";
    $params[':buscar3'] = "%$buscar%";
    $params[':buscar4'] = "%$buscar%";
}

$sql .= " GROUP BY u.n_idusuario ORDER BY u.t_nombres ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Filtrar por rol si se especificó (post-query porque es GROUP_CONCAT)
if (!empty($rol)) {
    $usuarios = array_filter($usuarios, function($u) use ($rol) {
        $rolesArr = explode(',', $u['roles'] ?? '');
        return in_array($rol, $rolesArr);
    });
    $usuarios = array_values($usuarios);
}

// Formatear roles como array
foreach ($usuarios as &$u) {
    $u['roles'] = !empty($u['roles']) ? explode(',', $u['roles']) : [];
}

Response::json($usuarios);
