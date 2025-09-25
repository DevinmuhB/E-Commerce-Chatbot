<?php
session_start();
header('Content-Type: application/json');

include '../Config/koneksi.php';

// Ambil produk
$sql = "SELECT 
            p.id_produk AS id,
            p.nama_produk AS name,
            p.harga AS price,
            p.diskon AS diskon_default,
            (
                SELECT path_foto FROM foto_produk fp 
                WHERE fp.id_produk = p.id_produk 
                ORDER BY id_foto ASC LIMIT 1
            ) AS img,
            (
                SELECT path_foto FROM foto_produk fp 
                WHERE fp.id_produk = p.id_produk 
                ORDER BY id_foto DESC LIMIT 1
            ) AS img_hover
        FROM produk p";
$result = $conn->query($sql);

$produkList = [];

while ($row = $result->fetch_assoc()) {
    $nama = htmlspecialchars($row['nama_produk']);
    $harga = number_format($row['harga'], 0, ',', '.');
    $stok = htmlspecialchars($row['stok']);
    $gambar = htmlspecialchars($row['path_foto']);
    $link = "detail_produk.php?id=" . $row['id_produk'];

    // Gunakan gambar default jika tidak ada foto
    $imgSrc = $gambar ? $gambar : "uploads/default-product.jpg";
    $imgAlt = $nama;

    $produkList[$row['id']] = $row;
}

// Ambil diskon user jika login
if (isset($_SESSION['user_id'])) {
    $id_user = $_SESSION['user_id'];
    $diskon = $conn->query("SELECT id_produk, diskon FROM diskon_user_produk WHERE id_user = $id_user");

    while ($d = $diskon->fetch_assoc()) {
        $produk_id = $d['id_produk'];
        if (isset($produkList[$produk_id])) {
            $produkList[$produk_id]['diskon'] = (int)$d['diskon'];
        }
    }
}

// Pastikan semua produk punya diskon
foreach ($produkList as &$p) {
    if (!isset($p['diskon'])) {
        $p['diskon'] = (int)$p['diskon_default'];
    }
}

echo json_encode(array_values($produkList));
