<?php
/**
 * Endpoint: Registrar movimiento de consumible (entrada/salida)
 * Método: POST | Body: { idconsumible, tipo: "entrada"|"salida", cantidad, motivo? }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405);
Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$idConsumible = $data['idconsumible'] ?? 0;
$tipo     = Validator::obtenerCampo($data, 'tipo');
$cantidad = $data['cantidad'] ?? 0;
$motivo   = Validator::obtenerCampo($data, 'motivo');
$currentUser = Auth::currentUser();

if (!Validator::validarEntero($idConsumible)) Response::error('ID de consumible inválido.');
if (!Validator::validarEnSet($tipo, ['entrada', 'salida'])) Response::error('Tipo debe ser entrada o salida.');
if (!Validator::validarEntero($cantidad)) Response::error('Cantidad inválida.');

$pdo = Database::getConnection();

// Obtener stock actual
$stmt = $pdo->prepare("SELECT n_stockactual FROM consumibles WHERE n_idconsumible = :id AND t_activo = 'S'");
$stmt->execute([':id' => $idConsumible]);
$consumible = $stmt->fetch();
if (!$consumible) Response::error('Consumible no encontrado.', 404);

$stockAnterior = (int)$consumible['n_stockactual'];
$stockNuevo = $tipo === 'entrada' ? $stockAnterior + (int)$cantidad : $stockAnterior - (int)$cantidad;

if ($stockNuevo < 0) Response::error('Stock insuficiente. Stock actual: ' . $stockAnterior);

try {
    $pdo->beginTransaction();

    // Actualizar stock
    $pdo->prepare("UPDATE consumibles SET n_stockactual = :stock WHERE n_idconsumible = :id")
        ->execute([':stock' => $stockNuevo, ':id' => $idConsumible]);

    // Registrar movimiento
    $sqlMov = "INSERT INTO movimientos_consumibles (n_idconsumible, t_tipo, n_cantidad, n_stockanterior, n_stocknuevo, t_motivo, n_idusuario) 
               VALUES (:cid, :tipo, :cant, :anterior, :nuevo, :motivo, :uid)";
    $pdo->prepare($sqlMov)->execute([
        ':cid' => $idConsumible, ':tipo' => $tipo, ':cant' => $cantidad,
        ':anterior' => $stockAnterior, ':nuevo' => $stockNuevo,
        ':motivo' => $motivo, ':uid' => $currentUser['n_idusuario']
    ]);

    $pdo->commit();
    Response::json(['stock_nuevo' => $stockNuevo], 200, 'Movimiento registrado correctamente.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('consumibles/movimiento.php: ' . $e->getMessage());
    Response::error('Error al registrar el movimiento.', 500);
}
