<?php
/**
 * Endpoint: Genera evidencia/formato de salida de un préstamo (HU-04.06)
 * Método: GET | Parámetros: ?id=<n_idsolicitud>
 *
 * Devuelve un HTML imprimible con todos los datos del préstamo. El navegador
 * lo convierte a PDF con Ctrl+P → "Guardar como PDF". Esto evita instalar
 * librerías PHP de PDF (Dompdf/TCPDF) y mantiene el sistema sin dependencias
 * adicionales en Composer. La salida es server-side: el HTML se genera con
 * datos verificados de BD, no se puede manipular desde el cliente.
 */
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

Auth::requireLogin();
$user = Auth::currentUser();

$id = $_GET['id'] ?? 0;
if (!Validator::validarEntero($id)) {
    header('Content-Type: text/plain'); echo 'ID inválido.'; exit;
}

$pdo = Database::getConnection();

$stmt = $pdo->prepare(
    "SELECT sp.*,
            u.t_nombres, u.t_apellidos, u.t_correo, u.t_codigoinstitucional,
            l.t_nombre AS laboratorio_nombre, l.t_ubicacion AS laboratorio_ubicacion,
            ua.t_nombres  AS aprobador_nombres,
            ua.t_apellidos AS aprobador_apellidos
     FROM solicitudes_prestamo sp
     JOIN usuarios u ON u.n_idusuario = sp.n_idusuario
     JOIN laboratorios l ON l.n_idlaboratorio = sp.n_idlaboratorio
     LEFT JOIN usuarios ua ON ua.n_idusuario = sp.n_idusuarioaprobo
     WHERE sp.n_idsolicitud = :id"
);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch();
if (!$sol) {
    header('Content-Type: text/plain', true, 404); echo 'Solicitud no encontrada.'; exit;
}

// Permisos: el dueño o admin/auxiliar
$esAdmin = in_array('administrador', $user['roles']) || in_array('auxiliar_tecnico', $user['roles']);
if (!$esAdmin && (int)$sol['n_idusuario'] !== (int)$user['n_idusuario']) {
    header('Content-Type: text/plain', true, 403); echo 'No tiene permisos para ver esta evidencia.'; exit;
}

$elems = $pdo->prepare(
    "SELECT se.n_cantidad, se.t_estadoretorno,
            e.t_nombre, e.t_numeroinventario, e.t_marca, e.t_modelo
     FROM solicitudes_elementos se
     JOIN elementos e ON e.n_idelemento = se.n_idelemento
     WHERE se.n_idsolicitud = :id"
);
$elems->execute([':id' => $id]);
$listaElems = $elems->fetchAll();

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$nombre = $sol['t_nombres'] . ' ' . $sol['t_apellidos'];
$aprob  = $sol['aprobador_nombres']
    ? $sol['aprobador_nombres'] . ' ' . $sol['aprobador_apellidos']
    : '—';
$fechaCreacion  = substr($sol['dt_fechacreacion'], 0, 10);
$fechaInicio    = substr($sol['dt_fechainicio'], 0, 10);
$fechaFin       = substr($sol['dt_fechafin'], 0, 10);
$fechaDevol     = $sol['dt_fechadevolucion'] ? substr($sol['dt_fechadevolucion'], 0, 10) : '—';

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Evidencia de Préstamo #<?= esc($id) ?> — UPB Bucaramanga</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1A1A2E; background: #fff; padding: 28px; line-height: 1.4; }
  .doc { max-width: 720px; margin: 0 auto; }
  .header { border-bottom: 3px solid #6B1F7C; padding-bottom: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
  .header .titulo { font-size: 22px; font-weight: 800; color: #6B1F7C; }
  .header .sub { font-size: 12px; color: #6B6B85; margin-top: 4px; }
  .header .id { background: #6B1F7C; color: #fff; padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 700; }
  .estado { display: inline-block; padding: 3px 10px; border-radius: 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
  .estado.pendiente { background: #FEF3C7; color: #92400E; }
  .estado.aprobada  { background: #DBEAFE; color: #1E40AF; }
  .estado.prestada  { background: #FEF3C7; color: #B45309; }
  .estado.finalizada{ background: #D1FAE5; color: #065F46; }
  .estado.rechazada { background: #FEE2E2; color: #991B1B; }
  .estado.cancelada { background: #F3F4F6; color: #6B7280; }
  .seccion { margin: 18px 0; }
  .seccion h3 { font-size: 13px; text-transform: uppercase; color: #6B1F7C; letter-spacing: .04em; margin-bottom: 8px; border-bottom: 1px solid #E2E2EE; padding-bottom: 4px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
  .field label { font-size: 10px; color: #AAAABF; text-transform: uppercase; font-weight: 700; }
  .field p { font-size: 13px; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #F5EEF8; color: #6B1F7C; padding: 7px 10px; font-size: 11px; text-align: left; text-transform: uppercase; }
  td { padding: 7px 10px; border-bottom: 1px solid #E2E2EE; font-size: 12px; }
  .firmas { margin-top: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
  .firma { text-align: center; }
  .firma .linea { border-top: 1.5px solid #1A1A2E; margin-bottom: 6px; height: 50px; }
  .firma .nombre { font-size: 12px; font-weight: 700; }
  .firma .rol { font-size: 10px; color: #6B6B85; }
  .pie { margin-top: 38px; text-align: center; font-size: 10px; color: #AAAABF; border-top: 1px solid #E2E2EE; padding-top: 10px; }
  .btn-imprimir { position: fixed; top: 16px; right: 16px; background: #6B1F7C; color: #fff; padding: 10px 16px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
  @media print { .btn-imprimir { display: none; } body { padding: 0; } }
</style>
</head>
<body>
  <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
  <div class="doc">
    <div class="header">
      <div>
        <div class="titulo">UPB · Sistema de Préstamo de Laboratorio</div>
        <div class="sub">Evidencia oficial de préstamo de equipos · Universidad Pontificia Bolivariana — Bucaramanga</div>
      </div>
      <div class="id">#<?= esc($id) ?></div>
    </div>

    <div class="seccion">
      <h3>Estado actual</h3>
      <span class="estado <?= esc($sol['t_estado']) ?>"><?= esc($sol['t_estado']) ?></span>
    </div>

    <div class="seccion">
      <h3>Solicitante</h3>
      <div class="grid">
        <div class="field"><label>Nombre completo</label><p><?= esc($nombre) ?></p></div>
        <div class="field"><label>Código institucional</label><p><?= esc($sol['t_codigoinstitucional']) ?></p></div>
        <div class="field"><label>Correo</label><p><?= esc($sol['t_correo']) ?></p></div>
        <div class="field"><label>Laboratorio / Módulo</label><p><?= esc($sol['laboratorio_nombre']) ?></p></div>
      </div>
    </div>

    <div class="seccion">
      <h3>Fechas del préstamo</h3>
      <div class="grid">
        <div class="field"><label>Fecha de solicitud</label><p><?= esc($fechaCreacion) ?></p></div>
        <div class="field"><label>Aprobado por</label><p><?= esc($aprob) ?></p></div>
        <div class="field"><label>Fecha inicio</label><p><?= esc($fechaInicio) ?></p></div>
        <div class="field"><label>Fecha fin (devolución)</label><p><?= esc($fechaFin) ?></p></div>
        <div class="field"><label>Fecha de devolución real</label><p><?= esc($fechaDevol) ?></p></div>
      </div>
    </div>

    <div class="seccion">
      <h3>Propósito</h3>
      <p style="font-size:13px"><?= esc($sol['t_proposito']) ?></p>
      <?php if (!empty($sol['t_observacionesauxiliar'])): ?>
        <p style="font-size:12px;color:#6B6B85;margin-top:6px"><strong>Observaciones del auxiliar:</strong> <?= esc($sol['t_observacionesauxiliar']) ?></p>
      <?php endif; ?>
    </div>

    <div class="seccion">
      <h3>Elementos prestados (<?= count($listaElems) ?>)</h3>
      <table>
        <thead>
          <tr><th>Inventario</th><th>Elemento</th><th>Marca / Modelo</th><th>Cant.</th><th>Estado retorno</th></tr>
        </thead>
        <tbody>
          <?php foreach ($listaElems as $e): ?>
          <tr>
            <td><?= esc($e['t_numeroinventario']) ?></td>
            <td><?= esc($e['t_nombre']) ?></td>
            <td><?= esc(trim($e['t_marca'] . ' ' . $e['t_modelo'])) ?></td>
            <td><?= esc($e['n_cantidad']) ?></td>
            <td><?= esc($e['t_estadoretorno'] ?: '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="firmas">
      <div class="firma">
        <div class="linea"></div>
        <div class="nombre"><?= esc($nombre) ?></div>
        <div class="rol">Solicitante</div>
      </div>
      <div class="firma">
        <div class="linea"></div>
        <div class="nombre"><?= esc($aprob) ?></div>
        <div class="rol">Encargado de laboratorio</div>
      </div>
    </div>

    <div class="pie">
      Documento generado automáticamente el <?= date('d/m/Y H:i') ?>.
      Universidad Pontificia Bolivariana — Bucaramanga · Sistema de préstamo de laboratorios.
    </div>
  </div>
</body>
</html>
