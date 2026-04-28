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

// Coherencia de fechas
$tsInicio = strtotime($fechaInicio);
$tsFin    = strtotime($fechaFin);
if ($tsInicio === false || $tsFin === false) {
    Response::error('Las fechas tienen un formato inválido.');
}
if ($tsFin <= $tsInicio) {
    Response::error('La fecha fin debe ser posterior a la fecha de inicio.');
}

$pdo = Database::getConnection();

// HU-05.03 — Validar y calcular dias habiles entre fechaInicio y fechaFin.
// Saltamos sabados, domingos y dias en la tabla dias_no_habiles.
$diasNoHabiles = $pdo->query("SELECT dt_fecha FROM dias_no_habiles")->fetchAll(PDO::FETCH_COLUMN);
$noHabilesSet  = array_flip($diasNoHabiles);
$cur = strtotime(date('Y-m-d', $tsInicio));
$end = strtotime(date('Y-m-d', $tsFin));
$diasHabiles = 0;
while ($cur <= $end) {
    $f   = date('Y-m-d', $cur);
    $dow = (int)date('N', $cur);  // 1=Lunes ... 7=Domingo
    if ($dow < 6 && !isset($noHabilesSet[$f])) {
        $diasHabiles++;
    }
    $cur = strtotime('+1 day', $cur);
}
if ($diasHabiles < 1) {
    Response::error('El rango de fechas seleccionado no incluye ningún día hábil. Elige otras fechas.');
}

// Validar disponibilidad de cada elemento en el rango de fechas solicitado.
// Se considera "ocupado" si existe otra solicitud (pendiente, aprobada o
// prestada) que solape con el intervalo [fechaInicio, fechaFin].
$sqlColision = "SELECT sp.n_idsolicitud, e.t_nombre, e.t_numeroinventario
                FROM solicitudes_prestamo sp
                JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
                JOIN elementos e ON e.n_idelemento = se.n_idelemento
                WHERE se.n_idelemento = :eid
                  AND sp.t_estado IN ('pendiente','aprobada','prestada')
                  AND NOT (sp.dt_fechafin <= :fi OR sp.dt_fechainicio >= :ff)
                LIMIT 1";
$stmtColision = $pdo->prepare($sqlColision);
foreach ($elementos as $elem) {
    $eid = $elem['idelemento'] ?? 0;
    if (!Validator::validarEntero($eid)) {
        Response::error('Hay un elemento con ID inválido en la solicitud.');
    }
    $stmtColision->execute([':eid' => $eid, ':fi' => $fechaInicio, ':ff' => $fechaFin]);
    $choque = $stmtColision->fetch();
    if ($choque) {
        $nom = $choque['t_nombre'] . ' (' . $choque['t_numeroinventario'] . ')';
        Response::error("El elemento \"$nom\" ya está reservado en otra solicitud que se cruza con esas fechas.");
    }
}

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
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('solicitudes/crear.php: ' . $e->getMessage());
    Response::error('Error al crear la solicitud.', 500);
}
