<?php
/**
 * Endpoint: Genera la URL de un QR para una solicitud de préstamo (HU-08.02).
 * Método: GET | Parámetros: ?id=<n_idsolicitud>
 *
 * Devuelve la URL del QR (usando api.qrserver.com como servicio público)
 * y el payload codificado dentro del QR. El QR codifica un token simple
 * (idsolicitud + hash corto) que el endpoint checkin_out.php valida.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT n_idsolicitud, t_estado, n_idusuario FROM solicitudes_prestamo
     WHERE n_idsolicitud = :id"
);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) Response::error('Solicitud no encontrada.', 404);

// Token = sha1(idsolicitud . secreto). El secreto es el hash de la BD inicial,
// no se expone al cliente. Si quieres rotarlo cambia este string.
$secreto = 'UPB-NUV-2026-PRESTAMOS';
$token   = substr(sha1($id . '|' . $secreto), 0, 12);
$payload = "PRESTAMO:$id:$token";

// URL del QR (servicio gratuito de qrserver.com — funciona client-side sin
// dependencias). El frontend puede mostrar la imagen o el SVG directo.
$urlQR = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data='
       . urlencode($payload);

Response::json([
    'idsolicitud' => (int)$id,
    'estado'      => $sol['t_estado'],
    'token'       => $token,
    'payload'     => $payload,
    'qr_url'      => $urlQR
]);
