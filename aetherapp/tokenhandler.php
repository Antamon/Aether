<?php

session_start(); // We gebruiken een sessie om gegevens mee te nemen

// Configuratie
$token_url = 'https://oneiros.be/wp-json/moserver/token';
$client_id = 'uBYZRtFvEQtySjlydmwvqqqEBIEBRUPg';
$client_secret = 'oACAPFILqwADshdCXpcfPqQdkzusDFfn';
$redirect_uri = 'https://oneiros.be/aether/aetherapp/tokenhandler.php'; 
$redirect_success = 'index.html'; // Waarheen bij succes

// Functie om JWT payload te decoderen
function decode_jwt_payload($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $payload = $parts[1];
    $payload = strtr($payload, '-_', '+/');
    $payload = base64_decode($payload);
    if (!$payload) {
        return null;
    }
    return json_decode($payload, true);
}

// Stap 1: Controleer of we een 'code' gekregen hebben
if (!isset($_GET['code']) || empty($_GET['code'])) {
    die('Geen autorisatiecode ontvangen!');
}

$code = $_GET['code'];

// Stap 2: Vraag access token op via cURL
$post_data = http_build_query([
    'grant_type'    => 'authorization_code',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'code'          => $code,
    'redirect_uri'  => $redirect_uri,
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
]);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die('Fout bij ophalen van token: ' . curl_error($ch));
}

$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_status !== 200) {
    die('HTTP-fout bij ophalen van token: ' . $http_status . '<br>Response: ' . htmlspecialchars($response));
}

// Stap 3: Verwerk de response
$data = json_decode($response, true);

if (!isset($data['access_token']) || !isset($data['id_token'])) {
    die('Fout: access_token of id_token ontbreekt! Response: ' . htmlspecialchars($response));
}

// Stap 4: Decodeer de ID Token
$userinfo = decode_jwt_payload($data['id_token']);

if (!$userinfo) {
    die('Fout bij decoderen van gebruikersinformatie!');
}

// Stap 5: Sla belangrijke gebruikersgegevens op in sessie
$_SESSION['user'] = [
    'id'          => $userinfo['ID'] ?? null,
    'username'    => $userinfo['username'] ?? null,
    'email'       => $userinfo['email'] ?? null,
    'displayName' => $userinfo['display_name'] ?? null,
    'firstName'   => $userinfo['first_name'] ?? null,
    'lastName'    => $userinfo['last_name'] ?? null,
    'avatar'      => $userinfo['avatar'] ?? null,
];

// Stap 6: Doorsturen naar dashboard
header('Location: ' . $redirect_success);
exit;

?>
