<?php
/**
 * Endpoint: Detectar consumibles con stock por debajo del mínimo (HU-07.03)
 * Método: GET
 *
 * Notifica a los admins una vez al día y devuelve el listado.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Notificador.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$pdo = Database::getConnection();

$rows = $pdo->query(
    "SELECT c.n_idconsumible, c.t_nombre, c.n_stockactual, c.n_stockminimo,
            l.t_nombre AS laboratorio_nombre
     FROM consumibles c
     JOIN laboratorios l ON l.n_idlaboratorio = c.n_idlaboratorio
     WHERE c.t_activo = 'S'
       AND c.n_stockactual <= c.n_stockminimo
     ORDER BY (c.n_stockactual - c.n_stockminimo) ASC"
)->fetchAll();

// Notificar a admins una vez al día
$hoy = date('Y-m-d');
foreach ($rows as $r) {
    $exists = $pdo->prepare(
        "SELECT 1 FROM notificaciones
         WHERE t_tipo='stock_bajo' AND n_referencia = :ref
           AND DATE(dt_fechacreacion) = :hoy LIMIT 1"
    );
    $exists->execute([':ref' => $r['n_idconsumible'], ':hoy' => $hoy]);
    if ($exists->fetch()) continue;

    Notificador::notificarAdmins('stock_bajo',
        'Stock bajo: ' . $r['t_nombre'],
        'En ' . $r['laboratorio_nombre'] . ' quedan ' . $r['n_stockactual'] .
        ' unidades (mínimo configurado: ' . $r['n_stockminimo'] . ').',
        null, (int)$r['n_idconsumible']);
}

Response::json(['total' => count($rows), 'consumibles' => $rows]);
