<?php
/**
 * Endpoint: Préstamos activos (usa la vista v_prestamos_activos)
 * Método: GET
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$pdo = Database::getConnection();
Response::json($pdo->query("SELECT * FROM v_prestamos_activos")->fetchAll());
