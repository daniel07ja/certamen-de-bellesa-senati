<?php
declare(strict_types=1);
require_once __DIR__ . '/conexion.php';

ini_set('display_errors', '0');
set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    http_response_code(500);
    exit('El servicio no está disponible en este momento.');
});
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (EXIGIR_HTTPS && !$https) {
    http_response_code(403);
    exit('Abre este sitio con HTTPS.');
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; media-src 'self' blob:; object-src 'none'");
ini_set('session.use_strict_mode', '1');
session_name('SENATICERTAMEN');
session_set_cookie_params(['secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function post(string $key): string
{
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function email(string $value): ?string
{
    $value = strtolower(trim($value));
    return strlen($value) <= 254 && str_ends_with($value, '@senati.pe')
        && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
}

function csrf(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido.');
    }
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], post('csrf'))) {
        http_response_code(403);
        exit('Formulario vencido. Vuelve a abrir la página.');
    }
}

function go(string $page, string $message = ''): void
{
    if ($message !== '') {
        $_SESSION['flash'] = $message;
    }
    header('Location: ' . $page, true, 303);
    exit;
}

function adminUser(): ?array
{
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    $admin = q('SELECT * FROM administradores WHERE id = ?', [$_SESSION['admin_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !hash_equals($_SESSION['admin_version'] ?? '', hash('sha256', $admin['clave_hash']))) {
        unset($_SESSION['admin_id'], $_SESSION['admin_version']);
        return null;
    }
    return $admin;
}

function voter(): ?array
{
    if (empty($_SESSION['senati_verified']) || !isset($_SESSION['voter_id'])) {
        return null;
    }
    $row = q('SELECT * FROM votantes WHERE id = ?', [$_SESSION['voter_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals($_SESSION['voter_version'] ?? '', $row['codigo_hash'])) {
        unset($_SESSION['voter_id'], $_SESSION['voter_version']);
        return null;
    }
    return $row;
}

// Upgrade existing installations without touching votes or candidate records.
function ensureElectionSchema(): void
{
    try {
        q('SELECT cierre FROM configuracion, fotos_candidato LIMIT 0');
    } catch (PDOException $error) {
        if (!in_array((int) ($error->errorInfo[1] ?? 0), [1054, 1146], true)) { throw $error; }
        q('CREATE TABLE IF NOT EXISTS fotos_candidato (candidato_id INT UNSIGNED NOT NULL, posicion TINYINT UNSIGNED NOT NULL, ruta VARCHAR(100) NOT NULL, PRIMARY KEY(candidato_id,posicion), FOREIGN KEY(candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE) ENGINE=InnoDB');
        try { q('ALTER TABLE configuracion ADD COLUMN cierre BIGINT UNSIGNED NULL'); }
        catch (PDOException $duplicate) {
            if ((int) ($duplicate->errorInfo[1] ?? 0) !== 1060) { throw $duplicate; }
        }
    }
}

function eventOpen(bool $lock = false): bool
{
    $value = q('SELECT (abierta = 1 AND (cierre IS NULL OR cierre > UNIX_TIMESTAMP())) FROM configuracion WHERE id = 1' . ($lock ? ' FOR UPDATE' : ''))->fetchColumn();
    if ($value === false) {
        throw new RuntimeException('Falta configurar el certamen.');
    }
    return (bool) $value;
}

function transaction(callable $operation)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function brand(): string
{
    return is_file(__DIR__ . '/img/logo-senati.png')
        ? '<img class="logo" src="img/logo-senati.png" width="120" height="44" alt="SENATI">'
        : '<span class="brand-word">SENATI</span>';
}

function page(string $title): void
{
    $adminArea = $title !== 'Votación';
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Certamen SENATI</title>
<link rel="stylesheet" href="assets/tailwind.css">
<link rel="stylesheet" href="assets/app.css?v=9">
<script src="assets/app.js?v=9" defer></script>
</head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<header class="site-header"><div class="shell bar">
<a class="brand" href="index.php"><?= brand() ?><span>Certamen estudiantil</span></a>
<a class="button ghost" href="<?= $adminArea ? 'index.php' : 'admin.php' ?>"><?= $adminArea ? 'Ver votación' : 'Organizadores' ?></a>
</div></header>
<main id="contenido">
<?php if (isset($_SESSION['flash'])): ?>
<div class="shell"><p class="notice" role="status"><?= e($_SESSION['flash']) ?></p></div>
<?php unset($_SESSION['flash']); endif;
}

function footer(): void
{
    ?>
</main>
<footer class="site-footer"><div class="shell bar">
<div><?= brand() ?><p>Ingeniería de Software con Inteligencia Artificial</p></div>
<p>Organización del certamen · SENATI</p>
</div></footer>
</body></html>
<?php
}
