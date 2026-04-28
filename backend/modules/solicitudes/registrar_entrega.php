<?php
/**
 * Endpoint: Registrar entrega física de la solicitud
 * Método: POST | Body: { id, observaciones? }
 * Flujo: aprobada -> prestada
 * Cambia el estado físico de los elementos a 'prestado'.
 *
 * Este endpoint cierra el ciclo que se abrió al corregir el BUG 7:
 * aprobar.php ya NO bloquea los elementos; es aquí donde se marcan
 * como 'prestado' al momento de salir físicamente del laboratorio.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: HU-04.04 pide PATCH para cambio de estado a 'prestada'
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id  = $data['id'] ?? 0;
// HU-10.03: limitar observaciones (campo libre)
$obs = Validator::limitarLongitud(Validator::obtenerCampo($data, 'observaciones'), 500);
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    // Lock de la fila de la solicitud para evitar doble entrega concurrente.
    $stmt = $pdo->prepare("SELECT t_estado FROM solicitudes_prestamo
                           WHERE n_idsolicitud = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch();

    if (!$sol) {
        $pdo->rollBack();
        Response::error('Solicitud no encontrada.', 404);
    }
    if ($sol['t_estado'] !== 'aprobada') {
        $pdo->rollBack();
        Response::error('Solo se puede registrar entrega de solicitudes aprobadas.');
    }

    // Obtener elementos de la solicitud y bloquearlos.
    $stmtElem = $pdo->prepare(
        "SELECT se.n_idelemento, e.t_estado, e.t_nombre, e.t_numeroinventario
         FROM solicitudes_elementos se
         JOIN elementos e ON e.n_idelemento = se.n_idelemento
         WHERE se.n_idsolicitud = :id FOR UPDATE"
    );
    $stmtElem->execute([':id' => $id]);
    $elementos = $stmtElem->fetchAll();

    // Verificar que todos estén disponibles antes de marcar.
    foreach ($elementos as $elem) {
        if ($elem['t_estado'] !== 'disponible') {
            $pdo->rollBack();
            $nom = $elem['t_nombre'] . ' (' . $elem['t_numeroinventario'] . ')';
            Response::error("El elemento \"$nom\" no está disponible (estado actual: {$elem['t_estado']}).");
        }
    }

    // Cambiar estado de elementos -> prestado.
    $sqlUpd = "UPDATE elementos SET t_estado = 'prestado' WHERE n_idelemento = :eid";
    $stmtUpd = $pdo->prepare($sqlUpd);
    foreach ($elementos as $elem) {
        $stmtUpd->execute([':eid' => $elem['n_idelemento']]);
    }

    // Cambiar estado de la solicitud -> prestada.
    $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado = 'prestada'
                   WHERE n_idsolicitud = :id")
        ->execute([':id' => $id]);

    Logger::registrar($id, 'aprobada', 'prestada',
        $obs ?: 'Entrega física registrada',
        $currentUser['n_idusuario']);

    // Notificar al solicitante
    $solQ = $pdo->prepare("SELECT n_idusuario, dt_fechafin FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
    $solQ->execute([':id' => $id]);
    $solRow = $solQ->fetch();
    if ($solRow) {
        $fechaDev = (new DateTime($solRow['dt_fechafin']))->format('d/m/Y');
        Notificador::notificar(
            $solRow['n_idusuario'], 'prestada',
            'Préstamo #' . $id . ' entregado',
            "Recuerda devolver los elementos antes del $fechaDev.",
            'dashboard-usuario.html', $id
        );
    }

    // HU-09.02: dejar trazabilidad detallada (con lista de elementos entregados)
    $idsEntregados = array_map(fn($e) => (int)$e['n_idelemento'], $elementos);
    Auditor::registrar('solicitudes_prestamo', 'registrar_entrega', (int)$id, $currentUser['n_idusuario'],
        "Entrega física de la solicitud #$id (" . count($elementos) . " elementos)" . ($obs ? " — $obs" : ''),
        ['antes' => ['t_estado' => 'aprobada'],
         'despues' => ['t_estado' => 'prestada', 'elementos_entregados' => $idsEntregados]]);

    $pdo->commit();
    Response::json(null, 200, 'Entrega registrada correctamente.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('registrar_entrega.php: ' . $e->getMessage());
    Response::error('Error al registrar la entrega.', 500);
}
