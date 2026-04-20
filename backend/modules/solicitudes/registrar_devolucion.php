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
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id       = $data['id'] ?? 0;
$obs      = Validator::obtenerCampo($data, 'observaciones');
$elementos= $data['elementos'] ?? [];
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();

$stmt = $pdo->prepare("SELECT t_estado FROM solicitudes_prestamo WHERE n_idsolicitud = :id");
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);
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

    $pdo->commit();
    Response::json(null, 200, 'Devolución registrada correctamente.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('registrar_devolucion.php: ' . $e->getMessage());
    Response::error('Error al registrar la devolución.', 500);
}
