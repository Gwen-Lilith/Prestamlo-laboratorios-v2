<?php
/**
 * Endpoint: Crear usuario
 * Método: POST
 * Body: { nombres, apellidos, correo, password, codigoinstitucional, rol }
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

$data = Validator::obtenerBodyJSON();

// Si es autoregistro (desde el formulario público), no requiere login
$autoregistro = isset($data['autoregistro']) && $data['autoregistro'] === true;
if (!$autoregistro) {
    Auth::requireRole(['administrador', 'auxiliar_tecnico']);
}

// Validaciones
$nombres   = Validator::obtenerCampo($data, 'nombres');
$apellidos = Validator::obtenerCampo($data, 'apellidos');
$correo    = Validator::obtenerCampo($data, 'correo');
$password  = $data['password'] ?? '';
$codigo    = Validator::obtenerCampo($data, 'codigoinstitucional');
$rolNombre = Validator::obtenerCampo($data, 'rol');

if (empty($nombres) || empty($correo)) {
    Response::error('Nombre y correo son obligatorios.');
}

if (!Validator::validarCorreo($correo)) {
    Response::error('El formato del correo no es válido.');
}

$pdo = Database::getConnection();

// Verificar que el correo no exista ya
$stmt = $pdo->prepare("SELECT n_idusuario FROM usuarios WHERE t_correo = :correo");
$stmt->execute([':correo' => $correo]);
if ($stmt->fetch()) {
    Response::error('Ya existe un usuario con ese correo.');
}

// Hash de la contraseña con bcrypt
$hashPass = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;

// Insertar usuario
$sql = "INSERT INTO usuarios (t_codigoinstitucional, t_nombres, t_apellidos, t_correo, t_contrasena, t_activo) 
        VALUES (:codigo, :nombres, :apellidos, :correo, :contrasena, :activo)";
$stmt = $pdo->prepare($sql);
// HU-08.03: el auto-registro queda 'N' (pendiente de aprobación);
// solo cuando lo crea un admin/auxiliar logueado se activa directo.
$stmt->execute([
    ':codigo'     => $codigo,
    ':nombres'    => $nombres,
    ':apellidos'  => $apellidos,
    ':correo'     => $correo,
    ':contrasena' => $hashPass,
    ':activo'     => $autoregistro ? 'N' : 'S'
]);
$nuevoId = $pdo->lastInsertId();

// Asignar rol si se especificó
if (!empty($rolNombre)) {
    $stmtRol = $pdo->prepare("SELECT n_idrol FROM roles WHERE t_nombrerol = :rol");
    $stmtRol->execute([':rol' => $rolNombre]);
    $rol = $stmtRol->fetch();
    if ($rol) {
        $usuarioAsigno = $autoregistro ? $nuevoId : ($_SESSION['n_idusuario'] ?? $nuevoId);
        $sqlRol = "INSERT INTO usuarios_roles (n_idusuario, n_idrol, n_idusuarioasigno) VALUES (:uid, :rid, :asigno)";
        $stmtInsRol = $pdo->prepare($sqlRol);
        $stmtInsRol->execute([
            ':uid'    => $nuevoId,
            ':rid'    => $rol['n_idrol'],
            ':asigno' => $usuarioAsigno
        ]);
    }
}

Response::json(['n_idusuario' => (int)$nuevoId], 201, 'Usuario creado correctamente.');
