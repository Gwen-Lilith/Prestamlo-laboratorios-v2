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
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

$data = Validator::obtenerBodyJSON();

// Si es autoregistro (desde el formulario público), no requiere login
$autoregistro = isset($data['autoregistro']) && $data['autoregistro'] === true;
$currentUser = null;
if (!$autoregistro) {
    Auth::requireRole(['administrador', 'auxiliar_tecnico']);
    $currentUser = Auth::currentUser();
}

// Validaciones — HU-10.03: limitar longitudes para prevenir overflow / SQL malformado
$nombres   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombres'), 100);
$apellidos = Validator::limitarLongitud(Validator::obtenerCampo($data, 'apellidos'), 100);
$correo    = Validator::limitarLongitud(Validator::obtenerCampo($data, 'correo'), 150);
$password  = $data['password'] ?? '';
$codigo    = Validator::limitarLongitud(Validator::obtenerCampo($data, 'codigoinstitucional'), 20);
$rolNombre = Validator::limitarLongitud(Validator::obtenerCampo($data, 'rol'), 50);

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

// HU-09.01 + HU-09.03: registrar la creación del usuario en auditoría
$autorId = $autoregistro ? (int)$nuevoId : ($currentUser['n_idusuario'] ?? (int)$nuevoId);
Auditor::registrar('usuarios', $autoregistro ? 'autoregistro' : 'crear',
    (int)$nuevoId, $autorId,
    ($autoregistro
        ? "Auto-registro de '$correo' (queda pendiente de aprobación)"
        : "Usuario '$correo' creado por administrador con rol '$rolNombre'"),
    ['despues' => ['t_correo' => $correo, 't_nombres' => $nombres,
                   't_apellidos' => $apellidos, 't_codigoinstitucional' => $codigo,
                   'rol' => $rolNombre, 't_activo' => $autoregistro ? 'N' : 'S']]);

Response::json(['n_idusuario' => (int)$nuevoId], 201, 'Usuario creado correctamente.');
