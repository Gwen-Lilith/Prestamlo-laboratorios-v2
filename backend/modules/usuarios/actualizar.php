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
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido.', 405);
}

Auth::requireRole(['administrador', 'auxiliar_tecnico']);

$data = Validator::obtenerBodyJSON();
$id        = $data['id'] ?? 0;
$nombres   = Validator::obtenerCampo($data, 'nombres');
$apellidos = Validator::obtenerCampo($data, 'apellidos');
$correo    = Validator::obtenerCampo($data, 'correo');
$codigo    = Validator::obtenerCampo($data, 'codigoinstitucional');
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

Response::json(null, 200, 'Usuario actualizado correctamente.');
