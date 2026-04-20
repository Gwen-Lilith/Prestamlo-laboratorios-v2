<?php
/**
 * Repara los 18 campos con mojibake '??' en la BD, reponiendo los
 * valores correctos del seed original (los acentos se perdieron al
 * importar con charset equivocado).
 *
 * Idempotente: solo hace UPDATE si el valor actual coincide con el
 * mojibake esperado. Si ya fue reparado o modificado manualmente,
 * la fila queda intacta.
 */
require_once __DIR__ . '/../backend/config/db.php';

$pdo = Database::getConnection();

// [tabla, columna_id, id, columna_texto, valor_actual_mojibake, valor_correcto]
$fixes = [
    // Usuarios
    ['usuarios', 'n_idusuario', 1, 't_apellidos', 'Pe??a Fuentes',        'Peña Fuentes'],
    ['usuarios', 'n_idusuario', 2, 't_apellidos', 'Rodr??guez L??pez',    'Rodríguez López'],
    ['usuarios', 'n_idusuario', 3, 't_apellidos', 'Santoya Mart??nez',    'Santoya Martínez'],

    // Laboratorios
    ['laboratorios', 'n_idlaboratorio', 1, 't_nombre',
        'Lab Automatizaci??n Procesos Industriales',
        'Lab Automatización Procesos Industriales'],
    ['laboratorios', 'n_idlaboratorio', 1, 't_descripcion',
        'Laboratorio de automatizaci??n y control de procesos',
        'Laboratorio de automatización y control de procesos'],
    ['laboratorios', 'n_idlaboratorio', 2, 't_nombre',
        'Lab de F??sica No 1',
        'Lab de Física No 1'],
    ['laboratorios', 'n_idlaboratorio', 2, 't_descripcion',
        'Laboratorio de f??sica general y mec??nica',
        'Laboratorio de física general y mecánica'],
    ['laboratorios', 'n_idlaboratorio', 3, 't_descripcion',
        'Centro de administraci??n general de laboratorios',
        'Centro de administración general de laboratorios'],
    ['laboratorios', 'n_idlaboratorio', 4, 't_nombre',
        'Almac??n General',
        'Almacén General'],
    ['laboratorios', 'n_idlaboratorio', 4, 't_ubicacion',
        'Bloque K, S??tano',
        'Bloque K, Sótano'],
    ['laboratorios', 'n_idlaboratorio', 4, 't_descripcion',
        'Almac??n general de equipos y materiales',
        'Almacén general de equipos y materiales'],

    // Elementos
    ['elementos', 'n_idelemento', 2, 't_nombre',
        'Mult??metro Fluke 117', 'Multímetro Fluke 117'],
    ['elementos', 'n_idelemento', 5, 't_marca',  'Gen??rico', 'Genérico'],
    ['elementos', 'n_idelemento', 7, 't_nombre',
        'Mult??metro Fluke 87V', 'Multímetro Fluke 87V'],

    // Asignaturas
    ['asignaturas', 'n_idasignatura', 1, 't_nombre',
        'Electr??nica Anal??gica', 'Electrónica Analógica'],
    ['asignaturas', 'n_idasignatura', 2, 't_nombre',
        'Circuitos El??ctricos',  'Circuitos Eléctricos'],
    ['asignaturas', 'n_idasignatura', 3, 't_nombre',
        'F??sica Mec??nica',       'Física Mecánica'],
    ['asignaturas', 'n_idasignatura', 4, 't_nombre',
        'Automatizaci??n Industrial', 'Automatización Industrial'],
];

$actualizados = 0;
$saltados = 0;

foreach ($fixes as [$tabla, $idCol, $id, $col, $viejo, $nuevo]) {
    $sql = "UPDATE $tabla SET $col = :nuevo WHERE $idCol = :id AND $col = :viejo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nuevo' => $nuevo, ':id' => $id, ':viejo' => $viejo]);
    if ($stmt->rowCount() > 0) {
        echo "  [$tabla id=$id] $col  =>  '$nuevo'\n";
        $actualizados++;
    } else {
        $saltados++;
    }
}

echo "\nActualizados: $actualizados\n";
echo "Saltados (ya arreglados o valor distinto): $saltados\n";
