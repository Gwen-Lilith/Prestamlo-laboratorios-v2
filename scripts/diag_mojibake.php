<?php
/**
 * Reporta todas las filas de las tablas principales que contengan
 * secuencias '??' literales (mojibake causado por import con charset malo).
 */
require_once __DIR__ . '/../backend/config/db.php';

$pdo = Database::getConnection();

$campos = [
    'usuarios'        => ['n_idusuario',      ['t_nombres', 't_apellidos']],
    'laboratorios'    => ['n_idlaboratorio',  ['t_nombre', 't_ubicacion', 't_descripcion']],
    'tipos_elementos' => ['n_idtipoelemento', ['t_nombre', 't_descripcion']],
    'elementos'       => ['n_idelemento',     ['t_nombre', 't_marca', 't_modelo', 't_descripcion', 't_observaciones']],
    'asignaturas'     => ['n_idasignatura',   ['t_nombre']],
    'sedes'           => ['n_idsede',         ['t_nombre']],
    'edificios'       => ['n_idedificio',     ['t_nombre']],
];

$total = 0;
foreach ($campos as $tabla => $cfg) {
    [$idCol, $cols] = $cfg;
    $whereParts = array_map(function ($c) { return "$c LIKE '%??%'"; }, $cols);
    $sql = "SELECT $idCol AS id, " . implode(', ', $cols) . " FROM $tabla
            WHERE " . implode(' OR ', $whereParts);
    $rows = $pdo->query($sql)->fetchAll();
    if (empty($rows)) continue;

    echo "Tabla $tabla (" . count($rows) . " filas con mojibake):\n";
    foreach ($rows as $r) {
        echo "  id=" . $r['id'] . "\n";
        foreach ($cols as $c) {
            if ($r[$c] && strpos($r[$c], '??') !== false) {
                echo "    $c = '{$r[$c]}'\n";
                $total++;
            }
        }
    }
    echo "\n";
}
echo "Total de campos con '??': $total\n";
