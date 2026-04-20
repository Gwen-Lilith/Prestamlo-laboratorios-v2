<?php
/**
 * Seed de datos de demostración (solicitudes, entregas, anuncios).
 * Usa los usuarios, elementos y laboratorios reales ya insertados.
 * Idempotente: si ya hay solicitudes, no inserta nada nuevo (para no duplicar).
 *
 *   "C:/xampp/php/php.exe" scripts/seed_demo.php
 */
require_once __DIR__ . '/../backend/config/db.php';

$pdo = Database::getConnection();

// ─── Si ya hay solicitudes, salir ───
$nSol = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes_prestamo")->fetchColumn();
if ($nSol > 0) {
    echo "Ya existen $nSol solicitudes. Nada que insertar.\n";
    echo "Si quieres re-sembrar, limpia primero:\n";
    echo "  DELETE FROM historial_solicitudes;\n";
    echo "  DELETE FROM solicitudes_elementos;\n";
    echo "  DELETE FROM solicitudes_prestamo;\n";
    echo "  UPDATE elementos SET t_estado='disponible' WHERE t_estado='prestado';\n";
    exit(0);
}

// ─── Verificar precondiciones ───
$usuarios = $pdo->query("SELECT n_idusuario, t_correo FROM usuarios ORDER BY n_idusuario")->fetchAll();
$labs     = $pdo->query("SELECT n_idlaboratorio FROM laboratorios ORDER BY n_idlaboratorio")->fetchAll(PDO::FETCH_COLUMN);
$elems    = $pdo->query("SELECT n_idelemento, n_idlaboratorio FROM elementos ORDER BY n_idelemento")->fetchAll();

if (count($usuarios) < 3 || count($labs) < 2 || count($elems) < 4) {
    echo "Faltan datos base (usuarios/labs/elementos). Corre antes el seed principal.\n"; exit(1);
}

// IDs reales de tu BD (admin=1, auxiliar=2, profesor=3)
$idAdmin    = 1;
$idAuxiliar = 2;
$idProfesor = 3;

// ─── 12 solicitudes con distintos estados (set completo de ejemplos) ───
// Cada una: [idUsuario, idLab, fechaInicio, fechaFin, proposito, estado, elementos[], historial[]]
$demo = [
    // ───── PENDIENTES (2) — esperando aprobación ─────
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-04-22 08:00:00', 'ff' => '2026-04-24 17:00:00',
        'prop'  => 'Práctica de electrónica — circuitos combinacionales',
        'estado'=> 'pendiente', 'elems' => [1], 'aprobador' => null, 'obsAux' => null,
    ],
    [
        'uid'   => $idProfesor, 'lab' => 2,
        'fi'    => '2026-04-23 10:00:00', 'ff' => '2026-04-23 18:00:00',
        'prop'  => 'Laboratorio de redes — configuración de switches',
        'estado'=> 'pendiente', 'elems' => [4], 'aprobador' => null, 'obsAux' => null,
    ],

    // ───── APROBADAS (2) — listas para entrega física ─────
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-04-25 08:00:00', 'ff' => '2026-04-30 18:00:00',
        'prop'  => 'Proyecto de grado — sistema de medición automatizada',
        'estado'=> 'aprobada', 'elems' => [2], 'aprobador' => $idAdmin,
        'obsAux' => 'Aprobado. Pasar por el lab a recoger.',
    ],
    [
        'uid'   => $idProfesor, 'lab' => 2,
        'fi'    => '2026-04-28 08:00:00', 'ff' => '2026-04-30 18:00:00',
        'prop'  => 'Montaje de protoboard para parcial de Electrónica II',
        'estado'=> 'aprobada', 'elems' => [5], 'aprobador' => $idAuxiliar,
        'obsAux' => 'Aprobado por el auxiliar técnico.',
    ],

    // ───── PRESTADAS (1) — elementos físicamente fuera del laboratorio ─────
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-04-15 08:00:00', 'ff' => '2026-04-22 17:00:00',
        'prop'  => 'Mediciones para trabajo de Electrónica III',
        'estado'=> 'prestada', 'elems' => [3], 'aprobador' => $idAuxiliar,
        'obsAux' => 'Entregar con cable de prueba.',
    ],

    // ───── FINALIZADAS (2) — ya devueltas ─────
    [
        'uid'   => $idProfesor, 'lab' => 2,
        'fi'    => '2026-03-10 08:00:00', 'ff' => '2026-03-15 17:00:00',
        'prop'  => 'Sesiones de cálculo numérico del semestre pasado',
        'estado'=> 'finalizada', 'elems' => [5], 'aprobador' => $idAdmin,
        'obsAux' => 'Entregado sin novedad.',
    ],
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-03-01 08:00:00', 'ff' => '2026-03-05 17:00:00',
        'prop'  => 'Prueba de osciloscopios para laboratorio de comunicaciones',
        'estado'=> 'finalizada', 'elems' => [1], 'aprobador' => $idAuxiliar,
        'obsAux' => 'Devuelto con todos los accesorios.',
    ],

    // ───── RECHAZADAS (3) — admin rechazó por distintos motivos ─────
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-04-10 08:00:00', 'ff' => '2026-04-12 17:00:00',
        'prop'  => 'Laboratorio de análisis de señales',
        'estado'=> 'rechazada', 'elems' => [6], 'aprobador' => $idAuxiliar,
        'obsAux' => 'Equipo en mantenimiento, intentar la próxima semana.',
    ],
    [
        'uid'   => $idProfesor, 'lab' => 2,
        'fi'    => '2026-04-05 08:00:00', 'ff' => '2026-04-06 17:00:00',
        'prop'  => 'Solicitud de laptop para presentación',
        'estado'=> 'rechazada', 'elems' => [4], 'aprobador' => $idAdmin,
        'obsAux' => 'Justificación insuficiente. Contactar coordinación.',
    ],
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-03-20 08:00:00', 'ff' => '2026-03-21 17:00:00',
        'prop'  => 'Calibración de multímetros sin fecha definida',
        'estado'=> 'rechazada', 'elems' => [7], 'aprobador' => $idAuxiliar,
        'obsAux' => 'Solapamiento con otra solicitud previa.',
    ],

    // ───── CANCELADAS (2) — profesor canceló antes de la aprobación ─────
    [
        'uid'   => $idProfesor, 'lab' => 1,
        'fi'    => '2026-04-18 08:00:00', 'ff' => '2026-04-19 17:00:00',
        'prop'  => 'Solicitud realizada por error — se cancela',
        'estado'=> 'cancelada', 'elems' => [2], 'aprobador' => null,
        'obsAux' => 'Solicitud cancelada por el solicitante.',
    ],
    [
        'uid'   => $idProfesor, 'lab' => 2,
        'fi'    => '2026-04-08 08:00:00', 'ff' => '2026-04-09 17:00:00',
        'prop'  => 'Cambio en el cronograma de la clase',
        'estado'=> 'cancelada', 'elems' => [5], 'aprobador' => null,
        'obsAux' => 'Cancelado: la práctica se reprogramó para mayo.',
    ],
];

$pdo->beginTransaction();

$insSol  = $pdo->prepare(
    "INSERT INTO solicitudes_prestamo (n_idusuario, n_idlaboratorio, dt_fechainicio, dt_fechafin, t_proposito, t_estado,
     n_idusuarioaprobo, dt_fechaaprobacion, t_observacionesauxiliar)
     VALUES (:uid, :lab, :fi, :ff, :prop, :estado, :aprobador, :fechaAprob, :obsAux)"
);
$insSE   = $pdo->prepare("INSERT INTO solicitudes_elementos (n_idsolicitud, n_idelemento, n_cantidad) VALUES (:sid, :eid, 1)");
$insHist = $pdo->prepare(
    "INSERT INTO historial_solicitudes (n_idsolicitud, t_estadoanterior, t_estadonuevo, t_comentario, n_idusuario, dt_fechaactu)
     VALUES (:sid, :ant, :nuevo, :com, :uid, :fecha)"
);

foreach ($demo as $i => $d) {
    $insSol->execute([
        ':uid'       => $d['uid'], ':lab' => $d['lab'],
        ':fi'        => $d['fi'], ':ff' => $d['ff'],
        ':prop'      => $d['prop'], ':estado' => $d['estado'],
        ':aprobador' => $d['aprobador'],
        ':fechaAprob'=> $d['aprobador'] ? date('Y-m-d H:i:s', strtotime($d['fi']) - 86400) : null,
        ':obsAux'    => $d['obsAux'],
    ]);
    $sid = (int)$pdo->lastInsertId();

    foreach ($d['elems'] as $eid) {
        $insSE->execute([':sid' => $sid, ':eid' => $eid]);
    }

    // Historial: siempre empieza con "Solicitud creada"
    $fCreacion = date('Y-m-d H:i:s', strtotime($d['fi']) - 2 * 86400);
    $insHist->execute([':sid'=>$sid,':ant'=>null,':nuevo'=>'pendiente',':com'=>'Solicitud creada',':uid'=>$d['uid'],':fecha'=>$fCreacion]);

    $fAprob = date('Y-m-d H:i:s', strtotime($d['fi']) - 86400);
    if (in_array($d['estado'], ['aprobada','prestada','finalizada'])) {
        $insHist->execute([':sid'=>$sid,':ant'=>'pendiente',':nuevo'=>'aprobada',':com'=>$d['obsAux']?:'Aprobada',':uid'=>$d['aprobador'],':fecha'=>$fAprob]);
    }
    if (in_array($d['estado'], ['prestada','finalizada'])) {
        $fPrest = date('Y-m-d H:i:s', strtotime($d['fi']));
        $insHist->execute([':sid'=>$sid,':ant'=>'aprobada',':nuevo'=>'prestada',':com'=>'Entrega física registrada',':uid'=>$d['aprobador'],':fecha'=>$fPrest]);
    }
    if ($d['estado'] === 'finalizada') {
        $fDev = date('Y-m-d H:i:s', strtotime($d['ff']));
        $insHist->execute([':sid'=>$sid,':ant'=>'prestada',':nuevo'=>'finalizada',':com'=>'Devuelto sin novedad.',':uid'=>$d['aprobador'],':fecha'=>$fDev]);
        // Actualizar fechadevolucion
        $pdo->prepare("UPDATE solicitudes_prestamo SET dt_fechadevolucion = :f WHERE n_idsolicitud = :sid")
            ->execute([':f' => $fDev, ':sid' => $sid]);
    }
    if ($d['estado'] === 'rechazada') {
        $insHist->execute([':sid'=>$sid,':ant'=>'pendiente',':nuevo'=>'rechazada',':com'=>$d['obsAux']?:'Rechazada',':uid'=>$d['aprobador'],':fecha'=>$fAprob]);
    }
    if ($d['estado'] === 'cancelada') {
        // Cancelada por el propio solicitante (no hay aprobador).
        $insHist->execute([':sid'=>$sid,':ant'=>'pendiente',':nuevo'=>'cancelada',':com'=>$d['obsAux']?:'Cancelada por el solicitante',':uid'=>$d['uid'],':fecha'=>$fAprob]);
    }

    // Elementos prestados físicamente
    if ($d['estado'] === 'prestada') {
        foreach ($d['elems'] as $eid) {
            $pdo->prepare("UPDATE elementos SET t_estado='prestado' WHERE n_idelemento=:eid")->execute([':eid' => $eid]);
        }
    }
}

// ─── Anuncios de demo ───
$nAn = (int)$pdo->query("SELECT COUNT(*) FROM anuncios")->fetchColumn();
if ($nAn === 0) {
    $insAn = $pdo->prepare(
        "INSERT INTO anuncios (t_titulo, t_mensaje, t_tipo, t_estado, dt_fechapub, dt_fechaexp, n_idusuario)
         VALUES (:t, :m, :tipo, :est, :fp, :fe, :uid)"
    );
    $anuncios = [
        ['Mantenimiento Lab de Física', 'El Lab de Física No 1 estará cerrado el sábado 26 de abril por mantenimiento preventivo.',
         'urgente', 'activo', '2026-04-19', '2026-04-26'],
        ['Nueva política de préstamos', 'A partir de mayo, el plazo máximo de préstamo estándar se amplía de 7 a 10 días hábiles.',
         'informativo', 'activo', '2026-04-15', null],
        ['Recuerda devolver a tiempo', 'Los préstamos con retraso superior a 3 días suspenderán la cuenta del solicitante por 2 semanas.',
         'advertencia', 'activo', '2026-04-10', null],
    ];
    foreach ($anuncios as $a) {
        $insAn->execute([
            ':t' => $a[0], ':m' => $a[1], ':tipo' => $a[2], ':est' => $a[3],
            ':fp' => $a[4], ':fe' => $a[5], ':uid' => $idAdmin
        ]);
    }
    echo "  " . count($anuncios) . " anuncios insertados.\n";
}

$pdo->commit();

echo "\nSEED DEMO completo:\n";
echo "  Solicitudes: " . count($demo) . " (pendiente x2, aprobada, prestada, finalizada, rechazada)\n";
echo "  Anuncios:    3\n";
echo "\nAhora el frontend tiene datos reales para mostrar.\n";
