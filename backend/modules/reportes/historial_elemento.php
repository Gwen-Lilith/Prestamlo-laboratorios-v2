<?php
/**
 * Endpoint: Historial completo de préstamos de un elemento específico (HU-06.05)
 * Método: GET | Parámetros: ?id=<n_idelemento>
 *
 * Devuelve todas las solicitudes que han incluido este elemento, con su
 * historial de cambios de estado.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();

$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID de elemento inválido.');

$pdo = Database::getConnection();

// Datos básicos del elemento
$stmtE = $pdo->prepare(
    "SELECT e.n_idelemento, e.t_nombre, e.t_numeroinventario, e.t_marca, e.t_modelo,
            e.t_estado, te.t_nombre AS tipo_nombre, l.t_nombre AS laboratorio_nombre
     FROM elementos e
     JOIN tipos_elementos te ON te.n_idtipoelemento = e.n_idtipoelemento
     JOIN laboratorios l ON l.n_idlaboratorio = e.n_idlaboratorio
     WHERE e.n_idelemento = :id"
);
$stmtE->execute([':id' => $id]);
$elem = $stmtE->fetch();
if (!$elem) Response::error('Elemento no encontrado.', 404);

// Todas las solicitudes que incluyeron este elemento
$stmtS = $pdo->prepare(
    "SELECT sp.n_idsolicitud, sp.t_estado, sp.dt_fechainicio, sp.dt_fechafin,
            sp.dt_fechadevolucion, sp.t_proposito, sp.dt_fechacreacion,
            u.t_nombres, u.t_apellidos, u.t_correo,
            se.n_cantidad, se.t_estadoretorno, se.t_observaciones AS obs_elem
     FROM solicitudes_elementos se
     JOIN solicitudes_prestamo sp ON sp.n_idsolicitud = se.n_idsolicitud
     JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
     WHERE se.n_idelemento = :id
     ORDER BY sp.dt_fechacreacion DESC"
);
$stmtS->execute([':id' => $id]);
$solicitudes = $stmtS->fetchAll();

// Cambios de estado de TODAS esas solicitudes
$idsSol = array_column($solicitudes, 'n_idsolicitud');
$historial = [];
if (!empty($idsSol)) {
    $placeholders = implode(',', array_fill(0, count($idsSol), '?'));
    $stmtH = $pdo->prepare(
        "SELECT h.n_idsolicitud, h.t_estadoanterior, h.t_estadonuevo,
                h.t_comentario, h.dt_fechaactu,
                u.t_nombres AS usuario_nombres, u.t_apellidos AS usuario_apellidos
         FROM historial_solicitudes h
         JOIN usuarios u ON u.n_idusuario = h.n_idusuario
         WHERE h.n_idsolicitud IN ($placeholders)
         ORDER BY h.dt_fechaactu ASC"
    );
    $stmtH->execute($idsSol);
    $historial = $stmtH->fetchAll();
}

// Estadísticas rápidas
$stats = [
    'total_prestamos'       => count($solicitudes),
    'finalizados'           => count(array_filter($solicitudes, fn($s) => $s['t_estado'] === 'finalizada')),
    'activos'               => count(array_filter($solicitudes, fn($s) => in_array($s['t_estado'], ['aprobada','prestada']))),
    'rechazados_cancelados' => count(array_filter($solicitudes, fn($s) => in_array($s['t_estado'], ['rechazada','cancelada']))),
];

Response::json([
    'elemento'     => $elem,
    'solicitudes'  => $solicitudes,
    'historial'    => $historial,
    'estadisticas' => $stats
]);
