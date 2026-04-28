<?php
/**
 * Endpoint: Renovar préstamo activo (HU-04.11)
 * Método: POST | Body: { id, nueva_fecha_fin: "YYYY-MM-DD HH:MM:SS", motivo? }
 *
 * Solo se puede renovar si la solicitud está en estado 'aprobada' o 'prestada'
 * y el solicitante (o admin/auxiliar) es quien hace la petición.
 *
 * La nueva fecha fin debe ser posterior a la actual y no debe colisionar con
 * otras solicitudes activas para los mismos elementos.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireLogin();
$user = Auth::currentUser();

$data           = Validator::obtenerBodyJSON();
$id             = $data['id'] ?? 0;
$nuevaFechaFin  = $data['nueva_fecha_fin'] ?? '';
$motivo         = Validator::obtenerCampo($data, 'motivo');

if (!Validator::validarEntero($id))           Response::error('ID inválido.');
if (empty($nuevaFechaFin))                    Response::error('La nueva fecha de devolución es obligatoria.');
if (!Validator::validarFecha($nuevaFechaFin)) Response::error('Formato de fecha inválido.');

$pdo = Database::getConnection();

// Cargar solicitud actual
$stmt = $pdo->prepare(
    "SELECT n_idusuario, t_estado, dt_fechainicio, dt_fechafin
     FROM solicitudes_prestamo WHERE n_idsolicitud = :id"
);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);

// Autorización: el dueño o admin/auxiliar
$esAdmin = in_array('administrador', $user['roles']) || in_array('auxiliar_tecnico', $user['roles']);
if (!$esAdmin && (int)$sol['n_idusuario'] !== (int)$user['n_idusuario']) {
    Response::error('No tiene permisos para renovar esta solicitud.', 403);
}

if (!in_array($sol['t_estado'], ['aprobada', 'prestada'])) {
    Response::error('Solo se puede renovar una solicitud aprobada o prestada.');
}

$tsActual = strtotime($sol['dt_fechafin']);
$tsNueva  = strtotime($nuevaFechaFin);
if ($tsNueva === false || $tsNueva <= $tsActual) {
    Response::error('La nueva fecha debe ser posterior a la fecha de devolución actual.');
}

// Validar que no colisione con otras solicitudes activas para los mismos elementos
$elemsRow = $pdo->prepare(
    "SELECT se.n_idelemento, e.t_nombre, e.t_numeroinventario
     FROM solicitudes_elementos se
     JOIN elementos e ON e.n_idelemento = se.n_idelemento
     WHERE se.n_idsolicitud = :id"
);
$elemsRow->execute([':id' => $id]);
$elementos = $elemsRow->fetchAll();

$colision = $pdo->prepare(
    "SELECT sp.n_idsolicitud
     FROM solicitudes_prestamo sp
     JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
     WHERE se.n_idelemento = :eid
       AND sp.n_idsolicitud != :sid
       AND sp.t_estado IN ('pendiente','aprobada','prestada')
       AND NOT (sp.dt_fechafin <= :fi OR sp.dt_fechainicio >= :ff)
     LIMIT 1"
);
foreach ($elementos as $e) {
    $colision->execute([
        ':eid' => $e['n_idelemento'], ':sid' => $id,
        ':fi'  => $sol['dt_fechafin'],
        ':ff'  => $nuevaFechaFin
    ]);
    if ($colision->fetch()) {
        $nom = $e['t_nombre'] . ' (' . $e['t_numeroinventario'] . ')';
        Response::error("No se puede renovar: el elemento \"$nom\" ya está reservado en el período de extensión.");
    }
}

// Aplicar la renovación
try {
    $pdo->beginTransaction();
    $pdo->prepare(
        "UPDATE solicitudes_prestamo SET dt_fechafin = :ff WHERE n_idsolicitud = :id"
    )->execute([':ff' => $nuevaFechaFin, ':id' => $id]);

    $com = "Renovación: nueva fecha de devolución " . substr($nuevaFechaFin, 0, 10)
         . ($motivo ? " — Motivo: $motivo" : '');
    Logger::registrar($id, $sol['t_estado'], $sol['t_estado'], $com, $user['n_idusuario']);
    Auditor::registrar('solicitudes_prestamo', 'renovar', $id, $user['n_idusuario'], $com,
        ['antes' => ['dt_fechafin' => $sol['dt_fechafin']], 'despues' => ['dt_fechafin' => $nuevaFechaFin]]);

    // Notificar al admin/auxiliar (HU-07.01 ampliada)
    Notificador::notificarAdmins('info',
        'Renovación de préstamo #' . $id,
        "El usuario solicitó extender el préstamo hasta " . substr($nuevaFechaFin, 0, 10),
        'Admin-Solicitud-Detalle.html', $id);

    $pdo->commit();
    Response::json(['nueva_fecha_fin' => $nuevaFechaFin], 200, 'Préstamo renovado correctamente.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('renovar.php: ' . $e->getMessage());
    Response::error('Error al renovar el préstamo.', 500);
}
