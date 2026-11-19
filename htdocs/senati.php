<?php
require __DIR__ . '/app.php';

// Define SENATI_CLIENT_ID and SENATI_CLIENT_SECRET in the private conexion.php.
if (!defined('SENATI_CLIENT_ID') || !defined('SENATI_CLIENT_SECRET') || !SENATI_CLIENT_ID || !SENATI_CLIENT_SECRET) {
    go('index.php', 'El acceso de Microsoft 365 está pendiente de activación por la organización.');
}
const RETURN_URL = 'https://certamen-software-senati.kesug.com/senati.php';
const AUTHORITY = 'https://login.microsoftonline.com/senati.pe/oauth2/v2.0/';
function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function microsoftJson(string $url, array $options): array {
    $request = curl_init($url);
    curl_setopt_array($request, $options + [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
    if ($status !== 200 || !is_string($body)) {
        go('index.php', 'Microsoft no completó el acceso. Vuelve a intentarlo con tu cuenta de SENATI.');
    }
    return json_decode($body, true) ?: [];
}
if (!isset($_GET['code'], $_GET['state']) && !isset($_GET['error'])) {
    $flow = ['state' => bin2hex(random_bytes(32)), 'verifier' => base64url(random_bytes(48)), 'expires' => time() + 600];
    $_SESSION['ms_flow'] = $flow;
    header('Location: ' . AUTHORITY . 'authorize?' . http_build_query([
        'client_id' => SENATI_CLIENT_ID, 'redirect_uri' => RETURN_URL, 'response_type' => 'code',
        'scope' => 'https://graph.microsoft.com/User.Read', 'response_mode' => 'query', 'state' => $flow['state'],
        'prompt' => 'select_account', 'code_challenge_method' => 'S256',
        'code_challenge' => base64url(hash('sha256', $flow['verifier'], true))
    ]));
    exit;
}
$flow = $_SESSION['ms_flow'] ?? null;
unset($_SESSION['ms_flow']);
if (!$flow || $flow['expires'] < time() || !is_string($_GET['state'] ?? null)
    || !hash_equals($flow['state'], $_GET['state']) || !is_string($_GET['code'] ?? null)) {
    go('index.php', 'El ingreso se canceló o venció. Puedes volver a intentarlo.');
}
$tokens = microsoftJson(AUTHORITY . 'token', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
    'client_id' => SENATI_CLIENT_ID, 'client_secret' => SENATI_CLIENT_SECRET, 'grant_type' => 'authorization_code',
    'redirect_uri' => RETURN_URL, 'code' => $_GET['code'], 'code_verifier' => $flow['verifier']
])]);
if (empty($tokens['access_token'])) { go('index.php', 'Microsoft no autorizó el ingreso.'); }
$profile = microsoftJson('https://graph.microsoft.com/v1.0/me?$select=id,userPrincipalName', [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokens['access_token']]
]);
$address = email($profile['userPrincipalName'] ?? '');
if (!$address || !preg_match('/^[0-9]+@senati\.pe$/D', $address)) {
    go('index.php', 'Selecciona tu cuenta estudiantil con el formato ID@senati.pe.');
}
q('INSERT INTO votantes (correo, codigo_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE correo = VALUES(correo)',
    [$address, bin2hex(random_bytes(32))]);
$row = q('SELECT id, codigo_hash FROM votantes WHERE correo = ?', [$address])->fetch(PDO::FETCH_ASSOC);
session_regenerate_id(true);
unset($_SESSION['csrf']);
$_SESSION['voter_id'] = $row['id'];
$_SESSION['voter_version'] = $row['codigo_hash'];
$_SESSION['senati_verified'] = true;
go('index.php', 'Sesión iniciada con tu cuenta SENATI.');
