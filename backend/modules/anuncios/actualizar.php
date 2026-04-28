<?php
/**
 * Endpoint: Actualizar anuncio
 * Método: POST | Body: { id, titulo, mensaje, tipo, estado, fechaPub, fechaExp? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: aceptar POST/PATCH/PUT
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'PUT'])) Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id       = $data['id'] ?? 0;
// HU-10.03: limitar longitudes
$titulo   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'titulo'), 150);
$mensaje  = Validator::limitarLongitud(Validator::obtenerCampo($data, 'mensaje'), 1000);
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
// Capturar valor anterior para diff
$ant = $pdo->prepare("SELECT t_titulo, t_tipo, t_estado, dt_fechapub, dt_fechaexp FROM anuncios WHERE n_idanuncio = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();

$sql = "UPDATE anuncios SET t_titulo = :titulo, t_mensaje = :mensaje, t_tipo = :tipo,
        t_estado = :estado, dt_fechapub = :fechaPub, dt_fechaexp = :fechaExp
        WHERE n_idanuncio = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':titulo' => $titulo, ':mensaje' => $mensaje, ':tipo' => $tipo,
    ':estado' => $estado, ':fechaPub' => $fechaPub,
    ':fechaExp' => $fechaExp ?: null, ':id' => $id
]);

// HU-09.03: registrar cambio
Auditor::registrar('anuncios', 'actualizar', (int)$id, $currentUser['n_idusuario'],
    "Anuncio '$titulo' actualizado",
    ['antes' => $antData ?: [],
     'despues' => ['t_titulo' => $titulo, 't_tipo' => $tipo, 't_estado' => $estado,
                   'dt_fechapub' => $fechaPub, 'dt_fechaexp' => $fechaExp]]);

Response::json(null, 200, 'Anuncio actualizado.');
