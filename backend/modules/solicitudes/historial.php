<?php
/**
 * Endpoint: Historial de una solicitud
 * Método: GET | Parámetros: ?id=N
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$sql = "SELECT h.*, u.t_nombres, u.t_apellidos, u.t_correo
        FROM historial_solicitudes h
        JOIN usuarios u ON u.n_idusuario = h.n_idusuario
        WHERE h.n_idsolicitud = :id ORDER BY h.dt_fechaactu ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
Response::json($stmt->fetchAll());
