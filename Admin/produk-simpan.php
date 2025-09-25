<?php
include '../Config/koneksi.php';

$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama        = $_POST['nama_produk'];
    $id_kategori = $_POST['id_kategori'];
    $deskripsi   = $_POST['deskripsi'];
    $harga       = $_POST['harga'];
    $stok        = isset($_POST['stok']) ? intval($_POST['stok']) : 0;

    if (!$nama || !$id_kategori || $harga <= 0) {
        $message = "Semua kolom wajib diisi dan harga harus lebih dari 0.";
    } else {
        // Simpan produk dulu
        $stmt = $conn->prepare("INSERT INTO produk (nama_produk, deskripsi, harga, stok, id_kategori) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $nama, $deskripsi, $harga, $stok, $id_kategori);
        $stmt->execute();

        $id_produk = $stmt->insert_id;
        $jumlah_file = count($_FILES['foto_produk']['name']);
        $upload_failed = false;

        for ($i = 0; $i < $jumlah_file; $i++) {
            $nama_file = $_FILES['foto_produk']['name'][$i];
            $tmp_file  = $_FILES['foto_produk']['tmp_name'][$i];
            $nama_baru = time() . '-' . basename($nama_file);

            // Path fisik untuk upload (di server)
            $path_upload = __DIR__ . '/../uploads/' . $nama_baru;
            // Path yang disimpan di DB (untuk <img src="">)
            $path_db     = 'uploads/' . $nama_baru;

            if (move_uploaded_file($tmp_file, $path_upload)) {
                $stmtFoto = $conn->prepare("INSERT INTO foto_produk (id_produk, path_foto) VALUES (?, ?)");
                $stmtFoto->bind_param("is", $id_produk, $path_db);
                $stmtFoto->execute();
            } else {
                $upload_failed = true;
            }
        }

        $success = true;
        $message = $upload_failed ? 'Produk berhasil ditambahkan, namun ada foto yang gagal diunggah.' : 'Produk berhasil ditambahkan!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
<?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: '<?= $message ?>',
        showConfirmButton: false,
        timer: 1000
    }).then(() => {
        window.location.href = 'index.php';
    });
<?php else: ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: '<?= $message ?>'
    }).then(() => {
        window.history.back();
    });
<?php endif; ?>
</script>
</body>
</html>
