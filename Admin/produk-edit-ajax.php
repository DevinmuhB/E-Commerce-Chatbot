<?php
include '../Config/koneksi.php';

$id = $_POST['id'] ?? 0;
$result = $conn->query("SELECT * FROM produk WHERE id_produk = $id");
$data = $result->fetch_assoc();

if (!$data) {
    echo "<p>Produk tidak ditemukan.</p>";
    exit;
}

$kategori = $conn->query("SELECT * FROM kategori");
$foto_produk = $conn->query("SELECT * FROM foto_produk WHERE id_produk = $id");
?>

<h3>Edit Produk</h3>
<form id="formEditProduk" enctype="multipart/form-data">
    <input type="hidden" name="id_produk" value="<?= $data['id_produk'] ?>">

    <input type="text" name="nama_produk" value="<?= $data['nama_produk'] ?>" required><br><br>

    <select name="id_kategori" required>
        <?php while ($row = $kategori->fetch_assoc()): ?>
            <option value="<?= $row['id_kategori'] ?>" <?= $row['id_kategori'] == $data['id_kategori'] ? 'selected' : '' ?>>
                <?= $row['nama_kategori'] ?>
            </option>
        <?php endwhile; ?>
    </select><br><br>

    <textarea name="deskripsi" required><?= $data['deskripsi'] ?></textarea><br><br>
    <input type="number" name="harga" value="<?= $data['harga'] ?>" required><br><br>
    <input type="number" name="stok" value="<?= (int)$data['stok'] ?>" min="0" required><br><br>

    <label>Foto Saat Ini:</label><br>
    <?php while ($foto = $foto_produk->fetch_assoc()): ?>
        <div style="margin-bottom: 10px;">
            <img src="<?= $foto['path_foto'] ?>" width="80">
        </div>
    <?php endwhile; ?>

    <input type="file" name="foto_produk[]" multiple><br><br>

    <button type="submit">Simpan Perubahan</button>
</form>
