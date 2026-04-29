<?php
/**
 * Endpoint: Obtener estado de foto de perfil de un usuario (HU-08.10)
 * Método: GET | Parámetros: ?id=<n_idusuario>
 *
 * Devuelve si el usuario tiene foto, si tiene alerta vigente, y los datos
 * de la alerta (cuándo, quién, motivo) para que el admin tome decisión.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
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
$horasRestantes = null;
$minutosRestantes = null;
$textoGracia = null;       // "6 días 23 horas restantes", "5 minutos restantes", etc.

if ($tieneAlerta) {
    $tsAlerta = strtotime($u['dt_alerta_foto']);
    $tsAhora  = time();
    $segundosTotalGracia = $diasGracia * 86400;     // 7 días en segundos
    $segundosTranscurridos = max(0, $tsAhora - $tsAlerta);
    $segundosRestantes = max(0, $segundosTotalGracia - $segundosTranscurridos);

    // Granularidad real: días + horas + minutos (no solo días enteros).
    // Antes el sistema mostraba "7/7" durante 24h aunque ya hubieran
    // pasado horas — ahora el conteo es realista hasta el último minuto.
    $diasRestantes    = (int)floor($segundosRestantes / 86400);
    $horasRestantes   = (int)floor(($segundosRestantes % 86400) / 3600);
    $minutosRestantes = (int)floor(($segundosRestantes % 3600) / 60);

    $venceGracia = ($segundosRestantes <= 0);

    // Texto humano amigable para mostrar en el modal
    if ($segundosRestantes <= 0) {
        $textoGracia = 'Plazo de gracia terminado';
    } elseif ($diasRestantes >= 1) {
        $textoGracia = $diasRestantes . ' día' . ($diasRestantes !== 1 ? 's' : '')
                     . ' y ' . $horasRestantes . ' hora' . ($horasRestantes !== 1 ? 's' : '');
    } elseif ($horasRestantes >= 1) {
        $textoGracia = $horasRestantes . ' hora' . ($horasRestantes !== 1 ? 's' : '')
                     . ' y ' . $minutosRestantes . ' minuto' . ($minutosRestantes !== 1 ? 's' : '');
    } else {
        $textoGracia = max(1, $minutosRestantes) . ' minuto' . ($minutosRestantes !== 1 ? 's' : '');
    }
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
    'horas_restantes'  => $horasRestantes,
    'minutos_restantes'=> $minutosRestantes,
    'texto_gracia'     => $textoGracia,
    'puede_eliminar'   => $tieneAlerta && $venceGracia && $tieneFoto
]);
