<?php
session_start();
include 'Config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    echo 'unauthorized';
    exit;
}

$id_user = $_SESSION['user_id'];

$sql = "SELECT 
            k.id_produk,
            p.nama_produk,
            p.harga,
            (SELECT path_foto FROM foto_produk WHERE id_produk = p.id_produk LIMIT 1) AS path_foto,
            k.jumlah
        FROM keranjang k
        JOIN produk p ON k.id_produk = p.id_produk
        WHERE k.id_user = $id_user";

$result = $conn->query($sql);
$cart = [];

$total = 0;
while ($row = $result->fetch_assoc()) {
    $harga = $row['harga'];
    $subtotal = $harga * $row['jumlah'];
    $row['subtotal'] = $subtotal;
    $total += $subtotal;

    $cart[] = $row;
}

echo json_encode([
    'items' => $cart,
    'total' => $total
]);
