<?php
/**
 * CRON CLI — Detecta préstamos vencidos y notifica (HU-07.02).
 *
 * Programar en Windows Task Scheduler (8:00 AM diario):
 *   schtasks /create /sc DAILY /st 08:00 /tn "UPB-Vencidos" \
 *     /tr "C:\xampp\php\php.exe C:\PI 1\prestamo-laboratorios-front\scripts\cron_vencidos.php"
 *
 * En Linux (crontab -e):
 *   0 8 * * * /usr/bin/php /path/scripts/cron_vencidos.php
 *
 * Idempotente: solo notifica una vez por solicitud por día.
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Solo CLI.'); }

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/core/Notificador.php';

$pdo = Database::getConnection();
$rows = $pdo->query(
    "SELECT sp.n_idsolicitud, sp.n_idusuario, sp.dt_fechafin,
            u.t_nombres, u.t_apellidos,
            l.t_nombre AS laboratorio_nombre,
            GROUP_CONCAT(e.t_nombre SEPARATOR ', ') AS elementos
     FROM solicitudes_prestamo sp
     JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
     JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
     LEFT JOIN solicitudes_elementos se ON se.n_idsolicitud = sp.n_idsolicitud
     LEFT JOIN elementos e ON e.n_idelemento = se.n_idelemento
     WHERE sp.t_estado = 'prestada' AND sp.dt_fechafin < CURRENT_TIMESTAMP
     GROUP BY sp.n_idsolicitud"
)->fetchAll();

$hoy = date('Y-m-d');
$nuevos = 0;
foreach ($rows as $r) {
    $existe = $pdo->prepare(
        "SELECT 1 FROM notificaciones
         WHERE n_idusuario=:u AND t_tipo='vencida'
           AND n_referencia=:r AND DATE(dt_fechacreacion)=:h LIMIT 1"
    );
    $existe->execute([':u'=>$r['n_idusuario'], ':r'=>$r['n_idsolicitud'], ':h'=>$hoy]);
    if ($existe->fetch()) continue;

    Notificador::notificar(
        $r['n_idusuario'], 'vencida',
        'Préstamo #' . $r['n_idsolicitud'] . ' vencido',
        'Tu fecha de devolución era ' . substr($r['dt_fechafin'],0,10) .
        '. Pasa por el laboratorio cuanto antes.',
        'dashboard-usuario.html', (int)$r['n_idsolicitud']
    );
    Notificador::notificarAdmins('vencida',
        'Préstamo #' . $r['n_idsolicitud'] . ' vencido',
        ($r['t_nombres'].' '.$r['t_apellidos']) . ' no ha devuelto: ' . ($r['elementos']??'—'),
        'Admin-Solicitud-Detalle.html', (int)$r['n_idsolicitud']);
    $nuevos++;
}

echo "[" . date('Y-m-d H:i:s') . "] cron_vencidos: " . count($rows) . " vencidos · " . $nuevos . " nuevas notificaciones\n";
