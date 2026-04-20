<?php
/**
 * Endpoint: Crear anuncio
 * Método: POST | Body: { titulo, mensaje, tipo, estado, fechaPub, fechaExp? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$titulo   = Validator::obtenerCampo($data, 'titulo');
$mensaje  = Validator::obtenerCampo($data, 'mensaje');
$tipo     = Validator::obtenerCampo($data, 'tipo');
$estado   = Validator::obtenerCampo($data, 'estado');
$fechaPub = Validator::obtenerCampo($data, 'fechaPub');
$fechaExp = Validator::obtenerCampo($data, 'fechaExp');
$currentUser = Auth::currentUser();

if (empty($titulo))   Response::error('El título es obligatorio.');
if (empty($mensaje))  Response::error('El mensaje es obligatorio.');
if (empty($fechaPub)) Response::error('La fecha de publicación es obligatoria.');

$tiposOk   = ['informativo','advertencia','urgente'];
$estadosOk = ['activo','inactivo'];
if (!in_array($tipo,   $tiposOk,   true)) $tipo   = 'informativo';
if (!in_array($estado, $estadosOk, true)) $estado = 'activo';

$pdo = Database::getConnection();
$sql = "INSERT INTO anuncios (t_titulo, t_mensaje, t_tipo, t_estado, dt_fechapub, dt_fechaexp, n_idusuario)
        VALUES (:titulo, :mensaje, :tipo, :estado, :fechaPub, :fechaExp, :uid)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':titulo'   => $titulo,
    ':mensaje'  => $mensaje,
    ':tipo'     => $tipo,
    ':estado'   => $estado,
    ':fechaPub' => $fechaPub,
    ':fechaExp' => $fechaExp ?: null,
    ':uid'      => $currentUser['n_idusuario']
]);

Response::json(['n_idanuncio' => (int)$pdo->lastInsertId()], 201, 'Anuncio creado correctamente.');
