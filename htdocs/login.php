<?php
require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (adminUser()) {
        go('admin.php');
    }
    page('Acceso de organizadores');
    ?>
    <section class="shell section narrow"><div class="panel">
    <h1>Acceso de organizadores</h1>
    <form method="post" class="form">
    <?= csrf() ?>
    <label>Usuario<input name="usuario" required maxlength="64" autocomplete="username"></label>
    <label>Contraseña<input type="password" name="clave" required maxlength="72" autocomplete="current-password"></label>
    <details><summary>Primer ingreso</summary>
    <label>Clave de instalación<input type="password" name="instalacion" maxlength="32" autocomplete="off"></label>
    </details>
    <button class="button primary" name="accion" value="admin">Ingresar</button>
    </form></div></section>
    <?php
    footer();
    exit;
}

requirePost();
$action = post('accion');
if ($action === 'salir_admin' || $action === 'salir_estudiante') {
    if ($action === 'salir_admin') {
        unset($_SESSION['admin_id'], $_SESSION['admin_version'], $_SESSION['new_code']);
    } else {
        unset($_SESSION['voter_id'], $_SESSION['voter_version']);
    }
    unset($_SESSION['csrf']);
    session_regenerate_id(true);
    go($action === 'salir_admin' ? 'login.php' : 'index.php', 'Sesión cerrada.');
}

if ($action !== 'admin') {
    http_response_code(400);
    exit('Acción desconocida.');
}
$now = time();
if (($_SESSION['admin_retry'] ?? 0) > $now) {
    go('login.php', 'Espera unos segundos antes de volver a intentar.');
}
$user = trim(post('usuario'));
$password = post('clave');
$admin = strlen($user) <= 64
    ? q('SELECT * FROM administradores WHERE usuario = ?', [$user])->fetch(PDO::FETCH_ASSOC)
    : false;
if ($admin) {
    if ($admin['cambiar_clave'] && (!preg_match('/\A[a-f0-9]{32}\z/', CLAVE_INSTALACION)
        || !hash_equals(CLAVE_INSTALACION, post('instalacion')))) {
        $_SESSION['admin_retry'] = $now + 3;
        go('login.php', 'Verifica tus credenciales y la clave de instalación para el primer ingreso.');
    }
    $slot = q('UPDATE administradores SET proximo_intento = ? WHERE id = ? AND proximo_intento <= ?',
        [$now + 3, $admin['id'], $now]);
    if ($slot->rowCount() !== 1) {
        go('login.php', 'Espera unos segundos antes de volver a intentar.');
    }
}
$hash = $admin['clave_hash'] ?? '$2y$10$XyNBdgfTJlfUUWVewTm.1ufxSCs0wFUyTlCyHrowe9ASeHCV8WIWm';
$valid = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
if (!$admin || !$valid) {
    $_SESSION['admin_retry'] = $now + 3;
    go('login.php', 'Usuario o contraseña incorrectos.');
}
session_regenerate_id(true);
unset($_SESSION['csrf'], $_SESSION['admin_retry']);
$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_version'] = hash('sha256', $admin['clave_hash']);
go('admin.php');
