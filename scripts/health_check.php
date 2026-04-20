<?php
/**
 * Health check integral del sistema.
 * Solo CLI. Verifica conexión, tablas, integridad referencial, vistas,
 * estados válidos y alerta cualquier inconsistencia.
 */
require_once __DIR__ . '/../backend/config/db.php';

$pdo = Database::getConnection();

function seccion($t) { echo "\n" . str_repeat('=', 70) . "\n $t\n" . str_repeat('=', 70) . "\n"; }
function ok($t) { echo "  [OK]   $t\n"; }
function warn($t) { echo "  [WARN] $t\n"; }
function fail($t) { echo "  [FAIL] $t\n"; }

$problemas = 0;

// 1) Tablas esperadas
seccion('1. Tablas y conteos');
$tablas_esperadas = [
    'roles', 'usuarios', 'usuarios_roles', 'sedes', 'edificios',
    'laboratorios', 'tipos_elementos', 'elementos',
    'asignaturas', 'solicitudes_prestamo', 'solicitudes_elementos',
    'historial_solicitudes', 'consumibles', 'movimientos_consumibles',
    'solicitudes_laboratorio'
];
$stmt = $pdo->query("SHOW TABLES");
$existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tablas_esperadas as $t) {
    if (in_array($t, $existentes, true)) {
        $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        ok(sprintf("%-30s %5d registros", $t, $n));
    } else {
        fail("Tabla ausente: $t");
        $problemas++;
    }
}

// 2) Vistas
seccion('2. Vistas (unificacion BUG 6)');
$vistas = ['v_elementos_disponibles', 'v_prestamos_activos', 'v_prestamos_vencidos'];
foreach ($vistas as $v) {
    try {
        $n = $pdo->query("SELECT COUNT(*) FROM $v")->fetchColumn();
        ok(sprintf("%-30s %5d filas", $v, $n));
    } catch (Exception $e) {
        fail("Vista rota: $v — " . $e->getMessage());
        $problemas++;
    }
}

// Validar que v_prestamos_activos use 'prestada' (no 'en_curso')
$def = $pdo->query("SHOW CREATE VIEW v_prestamos_activos")->fetch();
$sqlVista = $def['Create View'] ?? '';
if (strpos($sqlVista, "'en_curso'") !== false) {
    fail("v_prestamos_activos AUN usa 'en_curso' — correr migracion_bug6_estados.sql");
    $problemas++;
} else {
    ok("v_prestamos_activos usa nomenclatura nueva ('prestada')");
}

// 3) Integridad de datos
seccion('3. Integridad de datos');

$hashesFalsos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE t_contrasena = '\$2y\$10\$8K1p/a0dR1xFc0aEiQwCkeOw8qXdFz7K5NxGxJKmQLqYZ7Wday4jS'")->fetchColumn();
if ($hashesFalsos > 0) { fail("Hay $hashesFalsos usuarios con el hash falso del seed original"); $problemas++; }
else ok("Ningun usuario con hash falso");

$sinRol = $pdo->query("SELECT COUNT(*) FROM usuarios u WHERE NOT EXISTS (SELECT 1 FROM usuarios_roles ur WHERE ur.n_idusuario = u.n_idusuario AND ur.t_activo = 'S')")->fetchColumn();
if ($sinRol > 0) { warn("$sinRol usuarios sin rol activo asignado"); }
else ok("Todos los usuarios tienen rol activo");

$mojibake = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM usuarios WHERE t_apellidos LIKE '%??%') +
            (SELECT COUNT(*) FROM laboratorios WHERE t_nombre LIKE '%??%' OR t_descripcion LIKE '%??%') +
            (SELECT COUNT(*) FROM elementos WHERE t_nombre LIKE '%??%' OR t_marca LIKE '%??%') +
            (SELECT COUNT(*) FROM asignaturas WHERE t_nombre LIKE '%??%')"
)->fetchColumn();
if ($mojibake > 0) { fail("$mojibake campos con mojibake '??' — correr scripts/fix_mojibake_bd.php"); $problemas++; }
else ok("Sin mojibake '??' en datos principales");

// 4) Estados validos
seccion('4. Estados de solicitudes y elementos');
$estadosSolInvalidos = $pdo->query(
    "SELECT t_estado, COUNT(*) AS n FROM solicitudes_prestamo
     WHERE t_estado NOT IN ('pendiente','aprobada','prestada','finalizada','rechazada','cancelada')
     GROUP BY t_estado"
)->fetchAll();
if (count($estadosSolInvalidos) === 0) ok("Todos los estados de solicitud son validos");
else {
    foreach ($estadosSolInvalidos as $r) fail("Estado de solicitud invalido: '{$r['t_estado']}' ({$r['n']} filas)");
    $problemas++;
}

$estadosElemInvalidos = $pdo->query(
    "SELECT t_estado, COUNT(*) AS n FROM elementos
     WHERE t_estado NOT IN ('disponible','prestado','mantenimiento','dado_de_baja')
     GROUP BY t_estado"
)->fetchAll();
if (count($estadosElemInvalidos) === 0) ok("Todos los estados de elemento son validos");
else {
    foreach ($estadosElemInvalidos as $r) fail("Estado de elemento invalido: '{$r['t_estado']}' ({$r['n']} filas)");
    $problemas++;
}

// 5) Coherencia: elemento 'prestado' debe tener solicitud 'prestada' asociada
seccion('5. Coherencia elementos prestados <-> solicitudes prestadas');
$elemsPrestadosSinSol = $pdo->query(
    "SELECT e.n_idelemento, e.t_nombre FROM elementos e
     WHERE e.t_estado = 'prestado'
       AND NOT EXISTS (
         SELECT 1 FROM solicitudes_elementos se
         JOIN solicitudes_prestamo sp ON sp.n_idsolicitud = se.n_idsolicitud
         WHERE se.n_idelemento = e.n_idelemento AND sp.t_estado = 'prestada'
       )"
)->fetchAll();
if (count($elemsPrestadosSinSol) === 0) ok("Todo elemento 'prestado' tiene solicitud 'prestada' asociada");
else {
    foreach ($elemsPrestadosSinSol as $r) warn("Elemento id={$r['n_idelemento']} '{$r['t_nombre']}' esta 'prestado' sin solicitud 'prestada' asociada");
}

// 6) Foreign keys huerfanos
seccion('6. Integridad referencial');
$huerfanos = [
    ['solicitudes_prestamo', 'n_idusuario', 'usuarios', 'n_idusuario'],
    ['solicitudes_prestamo', 'n_idlaboratorio', 'laboratorios', 'n_idlaboratorio'],
    ['solicitudes_elementos', 'n_idsolicitud', 'solicitudes_prestamo', 'n_idsolicitud'],
    ['solicitudes_elementos', 'n_idelemento', 'elementos', 'n_idelemento'],
    ['elementos', 'n_idlaboratorio', 'laboratorios', 'n_idlaboratorio'],
    ['elementos', 'n_idtipoelemento', 'tipos_elementos', 'n_idtipoelemento'],
    ['usuarios_roles', 'n_idusuario', 'usuarios', 'n_idusuario'],
    ['usuarios_roles', 'n_idrol', 'roles', 'n_idrol'],
    ['historial_solicitudes', 'n_idsolicitud', 'solicitudes_prestamo', 'n_idsolicitud'],
];
$ref_ok = true;
foreach ($huerfanos as [$t1, $c1, $t2, $c2]) {
    $n = $pdo->query("SELECT COUNT(*) FROM $t1 WHERE $c1 NOT IN (SELECT $c2 FROM $t2)")->fetchColumn();
    if ($n > 0) { fail("$t1.$c1 tiene $n referencias huerfanas a $t2.$c2"); $problemas++; $ref_ok = false; }
}
if ($ref_ok) ok("Sin FKs rotas");

// 7) Resumen
seccion('RESUMEN');
if ($problemas === 0) echo "  TODO OK — sistema saludable. " . count($tablas_esperadas) . " tablas, " . count($vistas) . " vistas.\n";
else echo "  ENCONTRADOS $problemas PROBLEMA(S). Revisar arriba.\n";
echo "\n";
