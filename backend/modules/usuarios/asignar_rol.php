<?php
/**
 * Endpoint: Asignar rol a usuario
 * Método: POST
 * Body: { id_usuario, rol }
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

Auth::requireRole(['administrador']);

$data      = Validator::obtenerBodyJSON();
$idUsuario = $data['id_usuario'] ?? 0;
$rolNombre = Validator::obtenerCampo($data, 'rol');

if (!Validator::validarEntero($idUsuario)) {
    Response::error('ID de usuario inválido.');
}

$rolesPermitidos = ['administrador', 'auxiliar_tecnico', 'profesor'];
if (!Validator::validarEnSet($rolNombre, $rolesPermitidos)) {
    Response::error('Rol no válido. Opciones: ' . implode(', ', $rolesPermitidos));
}

$pdo = Database::getConnection();

// Obtener ID del rol
$stmtRol = $pdo->prepare("SELECT n_idrol FROM roles WHERE t_nombrerol = :rol");
$stmtRol->execute([':rol' => $rolNombre]);
$rol = $stmtRol->fetch();
if (!$rol) {
    Response::error('Rol no encontrado en la base de datos.');
}

// Inactivar roles anteriores del usuario
$pdo->prepare("UPDATE usuarios_roles SET t_activo = 'N' WHERE n_idusuario = :uid")
    ->execute([':uid' => $idUsuario]);

// Asignar nuevo rol
$sql = "INSERT INTO usuarios_roles (n_idusuario, n_idrol, n_idusuarioasigno) VALUES (:uid, :rid, :asigno)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':uid'    => $idUsuario,
    ':rid'    => $rol['n_idrol'],
    ':asigno' => $_SESSION['n_idusuario']
]);

// Auditoría
Auditor::registrar('usuarios', 'asignar_rol', $idUsuario,
    $_SESSION['n_idusuario'] ?? 0,
    "Rol '$rolNombre' asignado al usuario #$idUsuario");

Response::json(null, 200, "Rol '$rolNombre' asignado correctamente.");
