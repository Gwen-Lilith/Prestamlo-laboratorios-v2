<?php
/**
 * Endpoint: Crear anuncio
 * Método: POST | Body: { titulo, mensaje, tipo, estado, fechaPub, fechaExp? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
// HU-10.03: limitar longitudes para anuncios (evitan páginas cargadas con basura)
$titulo   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'titulo'), 150);
$mensaje  = Validator::limitarLongitud(Validator::obtenerCampo($data, 'mensaje'), 1000);
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

$nuevoId = (int)$pdo->lastInsertId();

// HU-09.03: trazabilidad
Auditor::registrar('anuncios', 'crear', $nuevoId, $currentUser['n_idusuario'],
    "Anuncio '$titulo' (tipo: $tipo) publicado",
    ['despues' => ['t_titulo' => $titulo, 't_tipo' => $tipo, 't_estado' => $estado,
                   'dt_fechapub' => $fechaPub, 'dt_fechaexp' => $fechaExp]]);

Response::json(['n_idanuncio' => $nuevoId], 201, 'Anuncio creado correctamente.');
