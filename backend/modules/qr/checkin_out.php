<?php
/**
 * Endpoint: Check-in / check-out por QR (HU-08.02).
 * Método: POST | Body: { payload: "PRESTAMO:<id>:<token>", accion: "salida"|"entrada" }
 *
 * Valida el token, y según la acción dispara el cambio de estado equivalente
 * a registrar_entrega.php (salida = pasar a 'prestada') o
 * registrar_devolucion.php (entrada = pasar a 'finalizada').
 *
 * Solo admin/auxiliar pueden escanear (es el flujo del laboratorio).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$user = Auth::currentUser();

$data    = Validator::obtenerBodyJSON();
$payload = Validator::obtenerCampo($data, 'payload');
$accion  = Validator::obtenerCampo($data, 'accion');

if (empty($payload))                                Response::error('Falta payload del QR.');
if (!in_array($accion, ['salida','entrada'], true)) Response::error('Acción debe ser "salida" o "entrada".');

// Parsear payload
if (!preg_match('/^PRESTAMO:(\d+):([a-f0-9]+)$/i', $payload, $m)) {
    Response::error('Código QR inválido.');
}
$idSol = (int)$m[1];
$token = $m[2];

$secreto = 'UPB-NUV-2026-PRESTAMOS';
$tokenEsperado = substr(sha1($idSol . '|' . $secreto), 0, 12);
if (!hash_equals($tokenEsperado, $token)) {
    Response::error('Token del QR no válido (posible falsificación).', 403);
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT t_estado, n_idusuario FROM solicitudes_prestamo
     WHERE n_idsolicitud = :id"
);
$stmt->execute([':id' => $idSol]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);

// SALIDA: aprobada -> prestada
if ($accion === 'salida') {
    if ($sol['t_estado'] !== 'aprobada') {
        Response::error("La solicitud está en estado '{$sol['t_estado']}', no se puede registrar salida.");
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado='prestada' WHERE n_idsolicitud=:id")
            ->execute([':id' => $idSol]);
        // Marcar elementos como 'prestado'
        $elems = $pdo->prepare("SELECT n_idelemento FROM solicitudes_elementos WHERE n_idsolicitud=:id");
        $elems->execute([':id' => $idSol]);
        foreach ($elems->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $pdo->prepare("UPDATE elementos SET t_estado='prestado' WHERE n_idelemento=:eid")
                ->execute([':eid' => $eid]);
        }
        Logger::registrar($idSol, 'aprobada', 'prestada', 'Salida registrada por QR', $user['n_idusuario']);
        Notificador::notificar($sol['n_idusuario'], 'prestada',
            "Préstamo #$idSol entregado", "Recibiste tus elementos. Recuerda devolverlos a tiempo.",
            'dashboard-usuario.html', $idSol);
        $pdo->commit();
        Response::json(['accion' => 'salida', 'idsolicitud' => $idSol], 200, 'Salida registrada por QR.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('qr/checkin_out salida: ' . $e->getMessage());
        Response::error('Error al registrar salida.', 500);
    }
}

// ENTRADA: prestada -> finalizada
if ($accion === 'entrada') {
    if ($sol['t_estado'] !== 'prestada') {
        Response::error("La solicitud está en estado '{$sol['t_estado']}', no se puede registrar entrada.");
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE solicitudes_prestamo SET t_estado='finalizada', dt_fechadevolucion=NOW()
                       WHERE n_idsolicitud=:id")->execute([':id' => $idSol]);
        $elems = $pdo->prepare("SELECT n_idelemento FROM solicitudes_elementos WHERE n_idsolicitud=:id");
        $elems->execute([':id' => $idSol]);
        foreach ($elems->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            $pdo->prepare("UPDATE elementos SET t_estado='disponible' WHERE n_idelemento=:eid")
                ->execute([':eid' => $eid]);
        }
        Logger::registrar($idSol, 'prestada', 'finalizada', 'Entrada/devolución registrada por QR', $user['n_idusuario']);
        Notificador::notificar($sol['n_idusuario'], 'devuelta',
            "Préstamo #$idSol devuelto", 'Devolución registrada correctamente. ¡Gracias!',
            'dashboard-usuario.html', $idSol);
        $pdo->commit();
        Response::json(['accion' => 'entrada', 'idsolicitud' => $idSol], 200, 'Devolución registrada por QR.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('qr/checkin_out entrada: ' . $e->getMessage());
        Response::error('Error al registrar entrada.', 500);
    }
}
