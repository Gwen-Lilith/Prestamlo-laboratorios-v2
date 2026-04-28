<?php
/**
 * Endpoint: Registrar devolución de préstamo
 * Método: POST | Body: { id, observaciones?, elementos: [{ idelemento, estadoretorno }] }
 * Flujo: aprobada → finalizada
 * Cambia estado de los elementos de vuelta a "disponible"
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: HU-04.05 pide PATCH para registrar devolución
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH'])) Response::error('Método no permitido.', 405);
// Cualquier usuario logueado puede iniciar una devolución; más abajo
// validamos que sea el dueño de la solicitud o admin/auxiliar.
Auth::requireLogin();

$data = Validator::obtenerBodyJSON();
$id       = $data['id'] ?? 0;
// HU-10.03: limitar observaciones (campo libre)
$obs      = Validator::limitarLongitud(Validator::obtenerCampo($data, 'observaciones'), 500);
$elementos= $data['elementos'] ?? [];
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT t_estado, n_idusuario FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);

// Autorización: admin/auxiliar o el propio dueño.
$esAdmin = in_array('administrador', $currentUser['roles']) ||
           in_array('auxiliar_tecnico', $currentUser['roles']);
if (!$esAdmin && (int)$sol['n_idusuario'] !== (int)$currentUser['n_idusuario']) {
    Response::error('No tiene permisos para devolver esta solicitud.', 403);
}

if (!in_array($sol['t_estado'], ['aprobada', 'prestada'])) {
    Response::error('Solo se puede registrar devolución de solicitudes aprobadas o prestadas.');
}

try {
    $pdo->beginTransaction();

    $estadoAnterior = $sol['t_estado'];
    $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'finalizada', 
                   dt_fechadevolucion = NOW(), t_observaciones = :obs WHERE n_idsolicitud = :id")
        ->execute([':obs' => $obs, ':id' => $id]);

    // Devolver elementos a disponible y registrar estado de retorno
    $sqlElemSol = "SELECT se.n_idelemento FROM solicitudes_elementos se WHERE se.n_idsolicitud = :id";
    $stmtElemSol = $pdo->prepare($sqlElemSol);
    $stmtElemSol->execute([':id' => $id]);
    $elemsSol = $stmtElemSol->fetchAll();

    foreach ($elemsSol as $elem) {
        $pdo->prepare("UPDATE elementos SET t_estado = 'disponible' WHERE n_idelemento = :eid")
            ->execute([':eid' => $elem['n_idelemento']]);
    }

    // Actualizar estado de retorno si se proporcionaron
    foreach ($elementos as $elem) {
        if (isset($elem['idelemento']) && isset($elem['estadoretorno'])) {
            $pdo->prepare("UPDATE solicitudes_elementos SET t_estadoretorno = :est 
                          WHERE n_idsolicitud = :sid AND n_idelemento = :eid")
                ->execute([':est' => $elem['estadoretorno'], ':sid' => $id, ':eid' => $elem['idelemento']]);
        }
    }

    Logger::registrar($id, $estadoAnterior, 'finalizada', $obs ?: 'Devolución registrada', $currentUser['n_idusuario']);

    // HU-09.02: registrar en auditoría con lista de estados de retorno
    Auditor::registrar('solicitudes_prestamo', 'registrar_devolucion', (int)$id, $currentUser['n_idusuario'],
        "Devolución de la solicitud #$id (" . count($elemsSol) . " elementos)" . ($obs ? " — $obs" : ''),
        ['antes' => ['t_estado' => $estadoAnterior],
         'despues' => ['t_estado' => 'finalizada', 'elementos_estado_retorno' => $elementos]]);

    // HU-07.01: notificar al dueño que su devolución quedó registrada
    if (!empty($sol['n_idusuario'])) {
        Notificador::notificar(
            (int)$sol['n_idusuario'], 'devuelta',
            'Devolución registrada para la solicitud #' . $id,
            'Tu préstamo fue devuelto correctamente. Gracias por usar el laboratorio.',
            'dashboard-usuario.html', $id
        );
    }

    $pdo->commit();
    Response::json(null, 200, 'Devolución registrada correctamente.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('registrar_devolucion.php: ' . $e->getMessage());
    Response::error('Error al registrar la devolución.', 500);
}
