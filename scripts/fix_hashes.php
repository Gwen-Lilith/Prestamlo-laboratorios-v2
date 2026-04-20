<?php
/**
 * Regenera los hashes bcrypt de los 3 usuarios de prueba directamente
 * sobre la BD. NO reimporta la BD, solo hace UPDATE de t_contrasena.
 * Solo CLI.
 */
require_once __DIR__ . '/../backend/config/db.php';

$correos = ['admin@upb.edu.co', 'auxiliar@upb.edu.co', 'profesor@upb.edu.co'];
$password = '1234';

$pdo = Database::getConnection();
$stmt = $pdo->prepare("UPDATE usuarios SET t_contrasena = :hash WHERE t_correo = :correo");

foreach ($correos as $correo) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt->execute([':hash' => $hash, ':correo' => $correo]);
    echo sprintf("  %-28s  updated=%d  verify=%s\n",
        $correo, $stmt->rowCount(),
        password_verify($password, $hash) ? 'SI ✓' : 'NO ✗'
    );
}

echo "\nListo. Password de los 3 usuarios = '1234'.\n";
