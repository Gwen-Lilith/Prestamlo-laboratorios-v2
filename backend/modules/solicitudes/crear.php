<?php
/**
 * Endpoint: Crear solicitud de préstamo
 * Método: POST
 * Body: { idlaboratorio, fechainicio, fechafin, proposito, observaciones?, elementos: [{ idelemento, cantidad }] }
 * Usa transacción PDO para insertar en solicitudes_prestamo + solicitudes_elementos
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();

$data = Validator::obtenerBodyJSON();
$currentUser = Auth::currentUser();

$idLab        = $data['idlaboratorio'] ?? 0;
$fechaInicio  = $data['fechainicio'] ?? '';
$fechaFin     = $data['fechafin'] ?? '';
$proposito    = Validator::obtenerCampo($data, 'proposito');
$observaciones= Validator::obtenerCampo($data, 'observaciones');
$elementos    = $data['elementos'] ?? [];

if (!Validator::validarEntero($idLab)) Response::error('Laboratorio inválido.');
if (empty($fechaInicio) || empty($fechaFin)) Response::error('Las fechas son obligatorias.');
if (empty($proposito)) Response::error('El propósito es obligatorio.');
if (empty($elementos) || !is_array($elementos)) Response::error('Debe seleccionar al menos un elemento.');

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // Insertar solicitud
    $sql = "INSERT INTO solicitudes_prestamo (n_idusuario, n_idlaboratorio, dt_fechainicio, dt_fechafin, t_proposito, t_observaciones, t_estado) 
            VALUES (:uid, :lab, :fi, :ff, :prop, :obs, 'pendiente')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'  => $currentUser['n_idusuario'],
        ':lab'  => $idLab,
        ':fi'   => $fechaInicio,
        ':ff'   => $fechaFin,
        ':prop' => $proposito,
        ':obs'  => $observaciones
    ]);
    $idSolicitud = $pdo->lastInsertId();

    // Insertar elementos de la solicitud
    $sqlElem = "INSERT INTO solicitudes_elementos (n_idsolicitud, n_idelemento, n_cantidad) VALUES (:sid, :eid, :cant)";
    $stmtElem = $pdo->prepare($sqlElem);
    foreach ($elementos as $elem) {
        $stmtElem->execute([
            ':sid'  => $idSolicitud,
            ':eid'  => $elem['idelemento'],
            ':cant' => $elem['cantidad'] ?? 1
        ]);
    }

    // Registrar en historial
    Logger::registrar($idSolicitud, null, 'pendiente', 'Solicitud creada', $currentUser['n_idusuario']);

    $pdo->commit();
    Response::json(['n_idsolicitud' => (int)$idSolicitud], 201, 'Solicitud creada correctamente.');

} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('Error al crear la solicitud: ' . $e->getMessage(), 500);
}
