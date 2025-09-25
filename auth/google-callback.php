<?php
session_start();
require_once 'config-google.php';

use GuzzleHttp\Client as GuzzleClient;
$guzzleClient = new GuzzleClient(['verify' => false]);
$client->setHttpClient($guzzleClient);

if (!isset($_GET['code'])) {
    exit('Tidak ada kode dikirim dari Google.');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (!isset($token['access_token'])) {
    echo "<h2>Token Error</h2><pre>";
    var_dump($token);
    exit;
}

$client->setAccessToken($token['access_token']);
$google_oauth = new \Google_Service_Oauth2($client);
$userInfo = $google_oauth->userinfo->get();

$email = $userInfo->email;
$name = $userInfo->name;

include '../config/koneksi.php';

$check = $conn->prepare("SELECT * FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, '', 'user')");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();
    $inserted_id = $conn->insert_id;
    $user = ['id' => $inserted_id, 'username' => $name, 'email' => $email];
}

// Simpan semua session penting
$_SESSION['user'] = $user;
$_SESSION['id'] = $user['id'];
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

header('Location: ../index.php');
exit();