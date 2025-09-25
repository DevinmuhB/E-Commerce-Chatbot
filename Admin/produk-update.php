<?php
include '../Config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id_produk'];
    $nama = $_POST['nama_produk'];
    $kategori = $_POST['id_kategori'];
    $deskripsi = $_POST['deskripsi'];
    $harga = $_POST['harga'];
    $stok = isset($_POST['stok']) ? intval($_POST['stok']) : 0;

    $stmt = $conn->prepare("UPDATE produk SET nama_produk=?, deskripsi=?, harga=?, stok=?, id_kategori=? WHERE id_produk=?");
    $stmt->bind_param("ssdiis", $nama, $deskripsi, $harga, $stok, $kategori, $id);
    $stmt->execute();

    // Upload foto baru jika ada
    if (!empty($_FILES['foto_produk']['name'][0])) {
        $count = count($_FILES['foto_produk']['name']);
        for ($i = 0; $i < $count; $i++) {
            $nama_file = $_FILES['foto_produk']['name'][$i];
            $tmp = $_FILES['foto_produk']['tmp_name'][$i];
            // Ubah path agar menggunakan folder uploads di root directory
            $path = '../uploads/' . time() . '-' . basename($nama_file);

            if (move_uploaded_file($tmp, $path)) {
                $conn->query("INSERT INTO foto_produk (id_produk, path_foto) VALUES ('$id', '$path')");
            }
        }
    }

    echo "success";
}
?>
