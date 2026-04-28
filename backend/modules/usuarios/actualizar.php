<?php
/**
 * Endpoint: Actualizar usuario
 * Método: POST
 * Body: { id, nombres, apellidos, correo, codigoinstitucional, password? }
 * 
 * Sistema de Préstamo de Laboratorio - UPB Bucaramanga
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Auditor.php';
require_once __DIR__ . '/../../config/db.php';

// REST: aceptar POST/PATCH/PUT
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['POST', 'PATCH', 'PUT'])) {
    Response::error('Método no permitido.', 405);
}

Auth::requireRole(['administrador', 'auxiliar_tecnico']);
$currentUser = Auth::currentUser();

$data = Validator::obtenerBodyJSON();
$id        = $data['id'] ?? 0;
// HU-10.03: limitar longitudes para inputs sospechosos
$nombres   = Validator::limitarLongitud(Validator::obtenerCampo($data, 'nombres'), 100);
$apellidos = Validator::limitarLongitud(Validator::obtenerCampo($data, 'apellidos'), 100);
$correo    = Validator::limitarLongitud(Validator::obtenerCampo($data, 'correo'), 150);
$codigo    = Validator::limitarLongitud(Validator::obtenerCampo($data, 'codigoinstitucional'), 20);
$password  = $data['password'] ?? '';

if (!Validator::validarEntero($id)) {
    Response::error('ID de usuario inválido.');
}

if (empty($nombres) || empty($correo)) {
    Response::error('Nombre y correo son obligatorios.');
}

$pdo = Database::getConnection();

// Verificar que no se duplique el correo con otro usuario
$stmt = $pdo->prepare("SELECT n_idusuario FROM usuarios WHERE t_correo = :correo AND n_idusuario != :id");
$stmt->execute([':correo' => $correo, ':id' => $id]);
if ($stmt->fetch()) {
    Response::error('Otro usuario ya tiene ese correo.');
}

// Capturar valores anteriores para diff de auditoría
$ant = $pdo->prepare("SELECT t_nombres, t_apellidos, t_correo, t_codigoinstitucional FROM usuarios WHERE n_idusuario = :id");
$ant->execute([':id' => $id]);
$antData = $ant->fetch();

// Construir UPDATE
$sql = "UPDATE usuarios SET t_nombres = :nombres, t_apellidos = :apellidos,
        t_correo = :correo, t_codigoinstitucional = :codigo";
$params = [
    ':nombres'   => $nombres,
    ':apellidos' => $apellidos,
    ':correo'    => $correo,
    ':codigo'    => $codigo,
    ':id'        => $id
];

if (!empty($password)) {
    $sql .= ", t_contrasena = :pass";
    $params[':pass'] = password_hash($password, PASSWORD_BCRYPT);
}

$sql .= " WHERE n_idusuario = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// HU-09.01: registrar el cambio (sin incluir password en el diff)
Auditor::registrar('usuarios', 'actualizar', (int)$id, $currentUser['n_idusuario'],
    "Usuario '$correo' actualizado por administrador" . (!empty($password) ? ' (incluye cambio de contraseña)' : ''),
    ['antes' => $antData ?: [], 'despues' => [
        't_nombres' => $nombres, 't_apellidos' => $apellidos,
        't_correo' => $correo, 't_codigoinstitucional' => $codigo,
        'cambio_password' => !empty($password)
    ]]);

Response::json(null, 200, 'Usuario actualizado correctamente.');
