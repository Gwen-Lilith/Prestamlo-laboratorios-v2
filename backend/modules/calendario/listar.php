<?php
/**
 * Endpoint: Listar días no hábiles (compartidos entre admin y usuarios)
 * Método: GET | Parámetros opcionales: ?year=YYYY&month=MM (1-12)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
// HU-05.01 corrección CTIC: filtro por laboratorio (opcional).
// Si no se pasa, devuelve globales (n_idlaboratorio NULL) + festivos.
$lab   = isset($_GET['lab']) ? (int)$_GET['lab'] : 0;

$sql = "SELECT d.n_iddia, d.dt_fecha, d.t_motivo, d.t_descripcion,
               d.dt_fechacreacion, d.n_idlaboratorio,
               l.t_nombre AS laboratorio_nombre,
               u.t_nombres AS autor_nombres, u.t_apellidos AS autor_apellidos
        FROM dias_no_habiles d
        LEFT JOIN usuarios u      ON u.n_idusuario      = d.n_idusuario
        LEFT JOIN laboratorios l  ON l.n_idlaboratorio  = d.n_idlaboratorio
        WHERE 1=1";
$params = [];
if ($year > 0) {
    $sql .= " AND YEAR(d.dt_fecha) = :y";
    $params[':y'] = $year;
}
if ($month >= 1 && $month <= 12) {
    $sql .= " AND MONTH(d.dt_fecha) = :m";
    $params[':m'] = $month;
}
if ($lab > 0) {
    // Devolver tanto los del lab específico como los globales (NULL).
    $sql .= " AND (d.n_idlaboratorio = :lab OR d.n_idlaboratorio IS NULL)";
    $params[':lab'] = $lab;
}
$sql .= " ORDER BY d.dt_fecha ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::json($stmt->fetchAll());
