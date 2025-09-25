<?php
$host = "localhost";
$user = "root"; // sesuaikan dengan username database kamu
$pass = "@IluviaDx123";     // sesuaikan dengan password database kamu
$db   = "skripsi_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
