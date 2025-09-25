<?php
include '../Config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_produk'])) {
    $id_produk = $_POST['id_produk'];

    // Hapus semua foto terkait
    $get_foto = $conn->query("SELECT path_foto FROM foto_produk WHERE id_produk = $id_produk");
    while ($row = $get_foto->fetch_assoc()) {
        if (file_exists($row['path_foto'])) {
            unlink($row['path_foto']); // hapus file di folder
        }
    }
    $conn->query("DELETE FROM foto_produk WHERE id_produk = $id_produk");

    // Hapus produk
    $conn->query("DELETE FROM produk WHERE id_produk = $id_produk");

    header("Location: index.php");
    exit();
} else {
    echo "Permintaan tidak valid.";
}
?>
