<?php
/**
 * Diagnóstico: verifica qué hash tiene cada usuario en la BD y si 'password_verify'
 * lo acepta para la contraseña '1234'. Solo CLI.
 */
require_once __DIR__ . '/../backend/config/db.php';

$pdo = Database::getConnection();
$rows = $pdo->query("SELECT n_idusuario, t_correo, t_activo, t_contrasena FROM usuarios")->fetchAll();

echo "Usuarios en BD:\n";
echo str_repeat('=', 80) . "\n";
foreach ($rows as $r) {
    $hash = $r['t_contrasena'];
    $ok   = password_verify('1234', $hash);
    echo sprintf(
        "id=%-3d  correo=%-28s  activo=%s  verify('1234')=%s\n  hash=%s\n\n",
        $r['n_idusuario'],
        $r['t_correo'],
        $r['t_activo'],
        $ok ? 'SI ✓' : 'NO ✗',
        $hash
    );
}

echo "Roles por usuario:\n";
echo str_repeat('=', 80) . "\n";
$rolesQ = $pdo->query(
    "SELECT u.n_idusuario, u.t_correo, r.t_nombrerol, ur.t_activo
     FROM usuarios u
     LEFT JOIN usuarios_roles ur ON ur.n_idusuario = u.n_idusuario
     LEFT JOIN roles r ON r.n_idrol = ur.n_idrol
     ORDER BY u.n_idusuario"
)->fetchAll();
foreach ($rolesQ as $r) {
    echo sprintf("id=%-3d  %-28s  rol=%-20s  activo=%s\n",
        $r['n_idusuario'], $r['t_correo'], $r['t_nombrerol'] ?? '(ninguno)', $r['t_activo'] ?? '-');
}
