<?php
require_once '../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('YOUR_CLIENT_ID');
$client->setClientSecret('YOUR_CLIENT_SECRET');
$client->setRedirectUri('http://localhost/skripsi/auth/google-callback.php');
$client->addScope("email");
$client->addScope("profile");

// Bypass SSL untuk XAMPP
$httpClient = new GuzzleHttp\Client(['verify' => false]);
$client->setHttpClient($httpClient);
