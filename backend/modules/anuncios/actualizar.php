<?php
/**
 * Endpoint: Actualizar anuncio
 * Método: POST | Body: { id, titulo, mensaje, tipo, estado, fechaPub, fechaExp? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id       = $data['id'] ?? 0;
$titulo   = Validator::obtenerCampo($data, 'titulo');
$mensaje  = Validator::obtenerCampo($data, 'mensaje');
$tipo     = Validator::obtenerCampo($data, 'tipo');
$estado   = Validator::obtenerCampo($data, 'estado');
$fechaPub = Validator::obtenerCampo($data, 'fechaPub');
$fechaExp = Validator::obtenerCampo($data, 'fechaExp');

if (!Validator::validarEntero($id)) Response::error('ID inválido.');
if (empty($titulo))   Response::error('El título es obligatorio.');
if (empty($mensaje))  Response::error('El mensaje es obligatorio.');
if (empty($fechaPub)) Response::error('La fecha de publicación es obligatoria.');

$tiposOk   = ['informativo','advertencia','urgente'];
$estadosOk = ['activo','inactivo'];
if (!in_array($tipo,   $tiposOk,   true)) $tipo   = 'informativo';
if (!in_array($estado, $estadosOk, true)) $estado = 'activo';

$pdo = Database::getConnection();
$sql = "UPDATE anuncios SET t_titulo = :titulo, t_mensaje = :mensaje, t_tipo = :tipo,
        t_estado = :estado, dt_fechapub = :fechaPub, dt_fechaexp = :fechaExp
        WHERE n_idanuncio = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':titulo' => $titulo, ':mensaje' => $mensaje, ':tipo' => $tipo,
    ':estado' => $estado, ':fechaPub' => $fechaPub,
    ':fechaExp' => $fechaExp ?: null, ':id' => $id
]);

Response::json(null, 200, 'Anuncio actualizado.');
