<?php
/**
 * Endpoint: Obtener estado de foto de perfil de un usuario (HU-08.10)
 * Método: GET | Parámetros: ?id=<n_idusuario>
 *
 * Devuelve si el usuario tiene foto, si tiene alerta vigente, y los datos
 * de la alerta (cuándo, quién, motivo) para que el admin tome decisión.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) Response::error('ID inválido.');

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT u.n_idusuario, u.t_correo, u.t_nombres, u.t_apellidos,
            u.t_fotoperfil, u.dt_alerta_foto, u.t_motivo_alerta,
            u.n_idusuario_alerto,
            au.t_nombres  AS alertador_nombres,
            au.t_apellidos AS alertador_apellidos
     FROM usuarios u
     LEFT JOIN usuarios au ON au.n_idusuario = u.n_idusuario_alerto
     WHERE u.n_idusuario = :id"
);
$stmt->execute([':id' => $id]);
$u = $stmt->fetch();
if (!$u) Response::error('Usuario no encontrado.', 404);

$tieneFoto    = !empty($u['t_fotoperfil']);
$tieneAlerta  = !empty($u['dt_alerta_foto']);
$diasGracia   = 7; // días que tiene el usuario para actualizar tras alerta
$venceGracia  = false;
$diasRestantes = null;
if ($tieneAlerta) {
    $tsAlerta = strtotime($u['dt_alerta_foto']);
    $tsAhora  = time();
    $diasTranscurridos = floor(($tsAhora - $tsAlerta) / 86400);
    $diasRestantes = max(0, $diasGracia - $diasTranscurridos);
    $venceGracia   = $diasRestantes === 0;
}

Response::json([
    'usuario'          => [
        'id'        => (int)$u['n_idusuario'],
        'nombre'    => trim($u['t_nombres'] . ' ' . $u['t_apellidos']),
        'correo'    => $u['t_correo'],
        'foto'      => $u['t_fotoperfil']
    ],
    'tiene_foto'       => $tieneFoto,
    'tiene_alerta'     => $tieneAlerta,
    'fecha_alerta'     => $u['dt_alerta_foto'],
    'motivo_alerta'    => $u['t_motivo_alerta'],
    'alertado_por'     => $u['n_idusuario_alerto']
        ? trim(($u['alertador_nombres'] ?? '') . ' ' . ($u['alertador_apellidos'] ?? ''))
        : null,
    'dias_gracia_total'=> $diasGracia,
    'dias_restantes'   => $diasRestantes,
    'puede_eliminar'   => $tieneAlerta && $venceGracia && $tieneFoto
]);
