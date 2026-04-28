<?php
/**
 * Endpoint: Listar notificaciones del usuario actual
 * Método: GET | Parámetros opcionales: ?solo_no_leidas=1
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$user = Auth::currentUser();
$pdo  = Database::getConnection();

$soloNoLeidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] === '1';
$sql = "SELECT * FROM notificaciones WHERE n_idusuario = :uid";
$params = [':uid' => $user['n_idusuario']];
if ($soloNoLeidas) $sql .= " AND t_leida = 'N'";
$sql .= " ORDER BY dt_fechacreacion DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$noLeidas = 0;
foreach ($rows as $r) if ($r['t_leida'] === 'N') $noLeidas++;

Response::json(['notificaciones' => $rows, 'no_leidas' => $noLeidas]);
