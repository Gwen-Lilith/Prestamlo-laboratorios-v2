<?php
/**
 * CRON CLI — Detecta consumibles bajo stock mínimo y notifica admins (HU-07.03).
 *
 * Programar en Windows Task Scheduler (cada lunes 7:00 AM):
 *   schtasks /create /sc WEEKLY /d MON /st 07:00 /tn "UPB-StockBajo" \
 *     /tr "C:\xampp\php\php.exe C:\PI 1\prestamo-laboratorios-front\scripts\cron_inventario_bajo.php"
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Solo CLI.'); }

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/core/Notificador.php';

$pdo = Database::getConnection();
$rows = $pdo->query(
    "SELECT c.n_idconsumible, c.t_nombre, c.n_stockactual, c.n_stockminimo,
            l.t_nombre AS laboratorio_nombre
     FROM consumibles c
     JOIN laboratorios l ON l.n_idlaboratorio = c.n_idlaboratorio
     WHERE c.t_activo='S' AND c.n_stockactual <= c.n_stockminimo
     ORDER BY (c.n_stockactual - c.n_stockminimo) ASC"
)->fetchAll();

$hoy = date('Y-m-d');
$nuevos = 0;
foreach ($rows as $r) {
    $exists = $pdo->prepare(
        "SELECT 1 FROM notificaciones
         WHERE t_tipo='stock_bajo' AND n_referencia=:r
           AND DATE(dt_fechacreacion)=:h LIMIT 1"
    );
    $exists->execute([':r'=>$r['n_idconsumible'], ':h'=>$hoy]);
    if ($exists->fetch()) continue;

    Notificador::notificarAdmins('stock_bajo',
        'Stock bajo: ' . $r['t_nombre'],
        'En ' . $r['laboratorio_nombre'] . ' quedan ' . $r['n_stockactual'] .
        ' unidades (mínimo: ' . $r['n_stockminimo'] . ').',
        null, (int)$r['n_idconsumible']);
    $nuevos++;
}

echo "[" . date('Y-m-d H:i:s') . "] cron_inventario_bajo: " . count($rows) . " items bajos · " . $nuevos . " nuevas notificaciones\n";
