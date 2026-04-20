<?php
/**
 * Endpoint: Listar solicitudes de creación de laboratorio
 * Método: GET | Parámetros: ?estado=pendiente|aprobada|rechazada
 *
 * - Administrador/Auxiliar ve todas.
 * - Profesor sólo ve las suyas.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$currentUser = Auth::currentUser();

$estado = $_GET['estado'] ?? '';

$sql = "SELECT sl.n_idsolicitudlab, sl.t_nombre, sl.t_ubicacion, sl.t_motivo,
               sl.t_estado, sl.t_comentarioresp, sl.dt_fechacreacion, sl.dt_fecharespuesta,
               u.n_idusuario AS n_idsolicitante,
               u.t_nombres AS solicitante_nombres,
               u.t_apellidos AS solicitante_apellidos,
               u.t_correo AS solicitante_correo
        FROM solicitudes_laboratorio sl
        JOIN usuarios u ON u.n_idusuario = sl.n_idsolicitante
        WHERE 1=1";
$params = [];

// Profesor solo ve las suyas.
$esAdmin = in_array('administrador', $currentUser['roles']) ||
           in_array('auxiliar_tecnico', $currentUser['roles']);
if (!$esAdmin) {
    $sql .= " AND sl.n_idsolicitante = :miid";
    $params[':miid'] = $currentUser['n_idusuario'];
}

if (!empty($estado)) {
    $sql .= " AND sl.t_estado = :estado";
    $params[':estado'] = $estado;
}

$sql .= " ORDER BY sl.dt_fechacreacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
