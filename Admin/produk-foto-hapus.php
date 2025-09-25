<?php
include '../Config/koneksi.php';

if (isset($_POST['id_foto'], $_POST['id_produk'])) {
    $id_foto = $_POST['id_foto'];
    $id_produk = $_POST['id_produk'];

    // Ambil path file
    $res = $conn->query("SELECT path_foto FROM foto_produk WHERE id_foto = $id_foto");
    $row = $res->fetch_assoc();
    if ($row && file_exists($row['path_foto'])) {
        unlink($row['path_foto']);
    }

    // Hapus dari DB
    $conn->query("DELETE FROM foto_produk WHERE id_foto = $id_foto");

    header("Location: produk-edit.php?id=$id_produk");
    exit();
}
?>
