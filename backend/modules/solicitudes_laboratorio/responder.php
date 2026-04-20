<?php
/**
 * Endpoint: Responder a una solicitud de creación de laboratorio
 * Método: POST | Body: { id, accion: "aprobar"|"rechazar", comentario? }
 *
 * Si se aprueba: se crea el laboratorio real en la tabla `laboratorios`
 * usando los datos de la solicitud, y la solicitud queda en estado
 * 'aprobada'. Si se rechaza: solo cambia el estado a 'rechazada'.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id         = $data['id'] ?? 0;
$accion     = Validator::obtenerCampo($data, 'accion');
$comentario = Validator::obtenerCampo($data, 'comentario');
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($id))                        Response::error('ID inválido.');
if (!in_array($accion, ['aprobar', 'rechazar'], true))    Response::error('Acción no válida.');

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM solicitudes_laboratorio
                           WHERE n_idsolicitudlab = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch();

    if (!$sol) {
        $pdo->rollBack();
        Response::error('Solicitud no encontrada.', 404);
    }
    if ($sol['t_estado'] !== 'pendiente') {
        $pdo->rollBack();
        Response::error('Esta solicitud ya fue respondida.');
    }

    $idLabNuevo = null;

    if ($accion === 'aprobar') {
        // Crear el laboratorio real. Responsable por defecto: el admin que aprueba.
        $sqlLab = "INSERT INTO laboratorios (t_nombre, t_ubicacion, t_descripcion, n_idusuario)
                   VALUES (:nombre, :ubicacion, :desc, :uid)";
        $descripcion = 'Creado desde solicitud #' . $sol['n_idsolicitudlab']
                     . '. Motivo: ' . $sol['t_motivo'];
        $pdo->prepare($sqlLab)->execute([
            ':nombre'    => $sol['t_nombre'],
            ':ubicacion' => $sol['t_ubicacion'],
            ':desc'      => $descripcion,
            ':uid'       => $currentUser['n_idusuario']
        ]);
        $idLabNuevo = (int)$pdo->lastInsertId();
        $nuevoEstado = 'aprobada';
        $mensaje = 'Solicitud aprobada: laboratorio creado correctamente.';
    } else {
        $nuevoEstado = 'rechazada';
        $mensaje = 'Solicitud rechazada.';
    }

    $pdo->prepare("UPDATE solicitudes_laboratorio SET
                    t_estado = :estado,
                    n_idusuarioresponde = :uid,
                    t_comentarioresp = :comentario,
                    dt_fecharespuesta = NOW()
                  WHERE n_idsolicitudlab = :id")
        ->execute([
            ':estado'     => $nuevoEstado,
            ':uid'        => $currentUser['n_idusuario'],
            ':comentario' => $comentario,
            ':id'         => $id
        ]);

    $pdo->commit();
    Response::json(['n_idlaboratorio' => $idLabNuevo], 200, $mensaje);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('solicitudes_laboratorio/responder.php: ' . $e->getMessage());
    Response::error('Error al procesar la solicitud.', 500);
}
