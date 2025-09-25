<?php
include '../Config/koneksi.php'; 

$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama_kategori'];

    if (!$nama) {
        $message = "Nama kategori tidak boleh kosong!";
    } else {
        $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmt->bind_param("s", $nama);

        if ($stmt->execute()) {
            $success = true;
            $message = "Kategori berhasil ditambahkan!";
        } else {
            $message = "Gagal menyimpan kategori.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Kategori</title>
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
