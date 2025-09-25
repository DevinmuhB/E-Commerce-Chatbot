<?php
session_start();
include 'Config/koneksi.php';

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    echo 'unauthorized';
    exit;
}

$id_user = (int)$_SESSION['user_id'];
$id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : null;
$aksi = $_POST['aksi'] ?? null;

// Validasi input
if (!$id_produk || !$aksi) {
    echo 'invalid';
    exit;
}

if ($aksi === 'tambah') {
    // Tambah jumlah produk
    $stmt = $conn->prepare("UPDATE keranjang SET jumlah = jumlah + 1 WHERE id_user = ? AND id_produk = ?");
    $stmt->bind_param("ii", $id_user, $id_produk);
    $stmt->execute();
} elseif ($aksi === 'kurang') {
    // Cek jumlah sekarang
    $stmt = $conn->prepare("SELECT jumlah FROM keranjang WHERE id_user = ? AND id_produk = ?");
    $stmt->bind_param("ii", $id_user, $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $jumlah = (int)$row['jumlah'];

        if ($jumlah <= 1) {
            // Hapus produk dari keranjang
            $delete = $conn->prepare("DELETE FROM keranjang WHERE id_user = ? AND id_produk = ?");
            $delete->bind_param("ii", $id_user, $id_produk);
            $delete->execute();
        } else {
            // Kurangi jumlah
            $update = $conn->prepare("UPDATE keranjang SET jumlah = jumlah - 1 WHERE id_user = ? AND id_produk = ?");
            $update->bind_param("ii", $id_user, $id_produk);
            $update->execute();
        }
    }
}

echo 'success';
?>