<?php
/**
 * Endpoint: Exportar CSV de solicitudes
 * Método: GET | Parámetros: ?estado=... (opcionales)
 * Devuelve un archivo CSV descargable
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$pdo = Database::getConnection();

$estado = $_GET['estado'] ?? '';

$sql = "SELECT sp.n_idsolicitud, u.t_nombres, u.t_apellidos, u.t_correo, u.t_codigoinstitucional,
        l.t_nombre AS laboratorio, sp.t_estado, sp.t_proposito,
        sp.dt_fechainicio, sp.dt_fechafin, sp.dt_fechadevolucion, sp.dt_fechacreacion
        FROM solicitudes_prestamo sp
        JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
        JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio";
$params = [];

if (!empty($estado)) {
    $sql .= " WHERE sp.t_estado = :estado";
    $params[':estado'] = $estado;
}

$sql .= " ORDER BY sp.dt_fechacreacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datos = $stmt->fetchAll();

// Generar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="solicitudes_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
// BOM para Excel UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Encabezados
fputcsv($output, ['ID', 'Nombres', 'Apellidos', 'Correo', 'Código', 'Laboratorio', 'Estado', 'Propósito', 'Fecha Inicio', 'Fecha Fin', 'Fecha Devolución', 'Fecha Creación']);

foreach ($datos as $row) {
    fputcsv($output, [
        $row['n_idsolicitud'], $row['t_nombres'], $row['t_apellidos'], $row['t_correo'],
        $row['t_codigoinstitucional'], $row['laboratorio'], $row['t_estado'],
        $row['t_proposito'], $row['dt_fechainicio'], $row['dt_fechafin'],
        $row['dt_fechadevolucion'], $row['dt_fechacreacion']
    ]);
}

fclose($output);
exit;
