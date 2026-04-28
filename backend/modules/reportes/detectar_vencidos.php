<?php
/**
 * Endpoint: Detectar préstamos vencidos y emitir notificaciones (HU-07.02)
 * Método: GET (idempotente — no notifica el mismo préstamo dos veces el mismo día)
 *
 * Llamar desde el dashboard admin al cargar; sustituye un cron real.
 * Devuelve la lista de vencidos para mostrarla en pantalla.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$pdo = Database::getConnection();

// Solicitudes prestadas con fecha fin pasada (la vista v_prestamos_vencidos
// está disponible pero hacemos query directo para tener n_idusuario)
$rows = $pdo->query(
    "SELECT sp.n_idsolicitud, sp.n_idusuario, sp.dt_fechafin,
            u.t_nombres, u.t_apellidos, u.t_correo,
            l.t_nombre AS laboratorio_nombre,
            GROUP_CONCAT(e.t_nombre SEPARATOR ', ') AS elementos
     FROM solicitudes_prestamo sp
     JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
     JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
     LEFT JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
     LEFT JOIN elementos e ON e.n_idelemento = se.n_idelemento
     WHERE sp.t_estado = 'prestada'
       AND sp.dt_fechafin < CURRENT_TIMESTAMP
     GROUP BY sp.n_idsolicitud
     ORDER BY sp.dt_fechafin ASC"
)->fetchAll();

// Notificar (idempotente: no notificar la misma solicitud dos veces el mismo día)
$hoy = date('Y-m-d');
$nuevas = 0;
foreach ($rows as $r) {
    $existe = $pdo->prepare(
        "SELECT 1 FROM notificaciones
         WHERE n_idusuario = :uid AND t_tipo = 'vencida'
           AND n_referencia = :sid AND DATE(dt_fechacreacion) = :hoy
         LIMIT 1"
    );
    $existe->execute([':uid' => $r['n_idusuario'], ':sid' => $r['n_idsolicitud'], ':hoy' => $hoy]);
    if ($existe->fetch()) continue;

    // Notif al solicitante
    Notificador::notificar(
        $r['n_idusuario'], 'vencida',
        'Préstamo #' . $r['n_idsolicitud'] . ' vencido',
        'Tu fecha de devolución era ' . substr($r['dt_fechafin'], 0, 10) .
        '. Por favor pasa por el laboratorio cuanto antes.',
        'dashboard-usuario.html', (int)$r['n_idsolicitud']
    );
    // Y a admin/auxiliares
    Notificador::notificarAdmins('vencida',
        'Préstamo #' . $r['n_idsolicitud'] . ' vencido',
        ($r['t_nombres'] . ' ' . $r['t_apellidos']) .
        ' no ha devuelto: ' . ($r['elementos'] ?? '—'),
        'Admin-Solicitud-Detalle.html', (int)$r['n_idsolicitud']);
    $nuevas++;
}

Response::json([
    'total_vencidos'      => count($rows),
    'notificados_hoy'     => $nuevas,
    'lista'               => $rows
]);
