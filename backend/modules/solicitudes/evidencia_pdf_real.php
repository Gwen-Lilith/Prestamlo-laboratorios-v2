<?php
/**
 * Endpoint: Generación REAL de PDF binario server-side (HU-04.06).
 * Método: GET | Parámetros: ?id=<n_idsolicitud>
 *
 * A diferencia de evidencia_pdf.php (que devuelve HTML imprimible), este
 * endpoint genera un PDF binario completo usando la clase MicroPdf
 * (PHP puro, sin Composer ni dependencias). El navegador recibe un
 * archivo .pdf descargable directamente.
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/MicroPdf.php';

Auth::requireLogin();
$user = Auth::currentUser();

$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) {
    header('Content-Type: text/plain', true, 400); echo 'ID inválido.'; exit;
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare(
    "SELECT sp.*, u.t_nombres, u.t_apellidos, u.t_correo, u.t_codigoinstitucional,
            l.t_nombre AS laboratorio_nombre,
            ua.t_nombres  AS aprobador_nombres,
            ua.t_apellidos AS aprobador_apellidos
     FROM solicitudes_prestamo sp
     JOIN usuarios u  ON u.n_idusuario  = sp.n_idusuario
     JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
     LEFT JOIN usuarios ua ON ua.n_idusuario = sp.n_idusuarioaprobo
     WHERE sp.n_idsolicitud = :id"
);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) { header('Content-Type: text/plain', true, 404); echo 'No encontrada.'; exit; }

$esAdmin = in_array('administrador', $user['roles']) || in_array('auxiliar_tecnico', $user['roles']);
if (!$esAdmin && (int)$sol['n_idusuario'] !== (int)$user['n_idusuario']) {
    header('Content-Type: text/plain', true, 403); echo 'Sin permisos.'; exit;
}

$elems = $pdo->prepare(
    "SELECT se.n_cantidad, se.t_estadoretorno,
            e.t_nombre, e.t_numeroinventario, e.t_marca, e.t_modelo
     FROM solicitudes_elementos se
     JOIN elementos e ON e.n_idelemento = se.n_idelemento
     WHERE se.n_idsolicitud = :id"
);
$elems->execute([':id' => $id]);
$lista = $elems->fetchAll();

// Construcción del PDF
$pdf = new MicroPdf("Evidencia Préstamo #$id - UPB");

// Header morado UPB
$pdf->rect(0, 0, 595.28, 90, '0.42 0.12 0.49');
$pdf->salto(20);
$pdf->texto("UPB - Sistema de Prestamo de Laboratorios", 16, true, 'left', '1 1 1');
$pdf->texto("Universidad Pontificia Bolivariana - Bucaramanga", 9, false, 'left', '0.9 0.9 0.95');
$pdf->salto(28);

// ID y estado
$pdf->texto("EVIDENCIA OFICIAL DE PRESTAMO #$id", 14, true, 'left', '0.42 0.12 0.49');
$pdf->texto("Estado actual: " . strtoupper($sol['t_estado']), 11, true);
$pdf->salto(8);
$pdf->linea();

// Datos del solicitante
$nombre = $sol['t_nombres'] . ' ' . $sol['t_apellidos'];
$pdf->texto("SOLICITANTE", 11, true, 'left', '0.42 0.12 0.49');
$pdf->texto("Nombre: $nombre");
$pdf->texto("Codigo institucional: " . ($sol['t_codigoinstitucional'] ?: '-'));
$pdf->texto("Correo: " . $sol['t_correo']);
$pdf->texto("Laboratorio: " . $sol['laboratorio_nombre']);
$pdf->salto(10);

// Fechas
$pdf->texto("FECHAS", 11, true, 'left', '0.42 0.12 0.49');
$pdf->texto("Fecha de solicitud: " . substr($sol['dt_fechacreacion'], 0, 10));
$pdf->texto("Inicio: " . substr($sol['dt_fechainicio'], 0, 10) .
           "    Fin: " . substr($sol['dt_fechafin'], 0, 10));
if (!empty($sol['dt_fechadevolucion'])) {
    $pdf->texto("Devolucion real: " . substr($sol['dt_fechadevolucion'], 0, 10));
}
if (!empty($sol['aprobador_nombres'])) {
    $pdf->texto("Aprobado por: " . $sol['aprobador_nombres'] . ' ' . $sol['aprobador_apellidos']);
}
$pdf->salto(10);

// Propósito
$pdf->texto("PROPOSITO", 11, true, 'left', '0.42 0.12 0.49');
$prop = mb_substr($sol['t_proposito'] ?: '-', 0, 200, 'UTF-8');
$pdf->texto($prop, 10);
$pdf->salto(10);

// Tabla de elementos
$pdf->texto("ELEMENTOS PRESTADOS (" . count($lista) . ")", 11, true, 'left', '0.42 0.12 0.49');
$pdf->salto(2);
$rows = [];
foreach ($lista as $e) {
    $rows[] = [
        $e['t_numeroinventario'] ?: '-',
        mb_substr($e['t_nombre'], 0, 30, 'UTF-8'),
        mb_substr(trim(($e['t_marca']??'') . ' ' . ($e['t_modelo']??'')), 0, 20, 'UTF-8') ?: '-',
        $e['n_cantidad'],
        $e['t_estadoretorno'] ?: '-'
    ];
}
$pdf->tabla(['Inventario','Elemento','Marca/Modelo','Cant','Estado retorno'], $rows,
    [80, 180, 130, 35, 90]);

$pdf->salto(40);

// Firmas
$pdf->linea(60, 245);
$pdf->linea(310, 495);
$pdf->y -= 8;
$pdf->texto("Solicitante", 9, true);
$pdf->texto($nombre, 9);
$pdf->salto(20);
$pdf->texto("Encargado de laboratorio (Firma y sello)", 9, true, 'right');

// Pie
$pdf->salto(20);
$pdf->linea();
$pdf->texto("Documento generado automaticamente el " . date('d/m/Y H:i') .
           " - Universidad Pontificia Bolivariana - Bucaramanga",
    8, false, 'center', '0.55 0.55 0.6');

$bin = $pdf->output();

// Enviar como descarga PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="evidencia_prestamo_' . $id . '.pdf"');
header('Content-Length: ' . strlen($bin));
header('Cache-Control: no-store, must-revalidate');
echo $bin;
