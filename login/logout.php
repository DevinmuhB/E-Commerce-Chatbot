<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logout</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil logout!',
        text: 'Kamu akan diarahkan ke halaman utama...',
        timer: 1000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = '../index.php';
    });
</script>
</body>
</html>
