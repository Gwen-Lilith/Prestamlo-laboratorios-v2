<?php
/**
 * Endpoint: Listar log de auditoría
 * Método: GET | Parámetros opcionales: ?tabla=X&id=N&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
 * Solo admin/auxiliar pueden consultar el log completo.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$pdo = Database::getConnection();

$tabla = $_GET['tabla'] ?? '';
$id    = $_GET['id']    ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$sql = "SELECT a.*, u.t_nombres AS autor_nombres, u.t_apellidos AS autor_apellidos
        FROM auditoria_acciones a
        JOIN usuarios u ON u.n_idusuario = a.n_idusuario
        WHERE 1=1";
$params = [];
if (!empty($tabla)) { $sql .= " AND a.t_tabla = :t"; $params[':t'] = $tabla; }
if (!empty($id))    { $sql .= " AND a.n_idregistro = :r"; $params[':r'] = (int)$id; }
if (!empty($desde)) { $sql .= " AND a.dt_fecha >= :d"; $params[':d'] = $desde . ' 00:00:00'; }
if (!empty($hasta)) { $sql .= " AND a.dt_fecha <= :h"; $params[':h'] = $hasta . ' 23:59:59'; }
$sql .= " ORDER BY a.dt_fecha DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
