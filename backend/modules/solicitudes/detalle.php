<?php
/**
 * Endpoint: Detalle de solicitud
 * Método: GET
 * Parámetros: ?id=N
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID de solicitud inválido.');

$pdo = Database::getConnection();

// Datos principales
$sql = "SELECT sp.*, 
        u.t_nombres, u.t_apellidos, u.t_correo, u.t_codigoinstitucional,
        l.t_nombre AS laboratorio_nombre, l.t_ubicacion AS laboratorio_ubicacion,
        ua.t_nombres AS aprobador_nombres, ua.t_apellidos AS aprobador_apellidos
        FROM solicitudes_prestamo sp
        JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
        JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
        LEFT JOIN usuarios ua ON ua.n_idusuario = sp.n_idusuarioaprobo
        WHERE sp.n_idsolicitud = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$solicitud = $stmt->fetch();

if (!$solicitud) Response::error('Solicitud no encontrada.', 404);

// Verificar permisos: profesor solo ve las suyas
$currentUser = Auth::currentUser();
$esAdmin = in_array('administrador', $currentUser['roles']) || in_array('auxiliar_tecnico', $currentUser['roles']);
if (!$esAdmin && $solicitud['n_idusuario'] != $currentUser['n_idusuario']) {
    Response::error('No tiene permisos para ver esta solicitud.', 403);
}

// Elementos de la solicitud
$sqlElem = "SELECT se.*, e.t_nombre AS elemento_nombre, e.t_numeroinventario, e.t_marca, e.t_modelo
            FROM solicitudes_elementos se
            JOIN elementos e ON e.n_idelemento = se.n_idelemento
            WHERE se.n_idsolicitud = :id";
$stmtElem = $pdo->prepare($sqlElem);
$stmtElem->execute([':id' => $id]);
$solicitud['elementos'] = $stmtElem->fetchAll();

// Historial
$sqlHist = "SELECT h.*, u.t_nombres AS usuario_nombres, u.t_apellidos AS usuario_apellidos 
            FROM historial_solicitudes h
            JOIN usuarios u ON u.n_idusuario = h.n_idusuario
            WHERE h.n_idsolicitud = :id ORDER BY h.dt_fechaactu ASC";
$stmtHist = $pdo->prepare($sqlHist);
$stmtHist->execute([':id' => $id]);
$solicitud['historial'] = $stmtHist->fetchAll();

Response::json($solicitud);
