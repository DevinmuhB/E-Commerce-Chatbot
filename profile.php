<?php
session_start();
include 'Config/koneksi.php';
if (!isset($_SESSION['user_id'])) { header('Location: login/index.php'); exit; }
$user_id = $_SESSION['user_id'];
// Ambil data user
$stmt = $conn->prepare("SELECT username, email, telepon FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
// Ambil riwayat pesanan
$orders_stmt = $conn->prepare("
    SELECT p.*, pr.nama_produk, pr.harga,
    (SELECT path_foto FROM foto_produk WHERE id_produk = pr.id_produk ORDER BY id_foto ASC LIMIT 1) as foto
    FROM pesanan p
    JOIN produk pr ON p.id_produk = pr.id_produk
    WHERE p.id_user = ?
    ORDER BY p.tanggal_pesanan DESC
");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - TechShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #f5f6f9;
            min-height: 100vh;
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
        }
        .profile-wrapper {
            display: flex;
            max-width: 1100px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .profile-sidebar {
            width: 280px;
            background: #f7f7fa;
            padding: 32px 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px solid #eee;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #bbb;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .profile-name {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 4px;
            color: #222;
        }
        .profile-email {
            font-size: 0.98em;
            color: #666;
            margin-bottom: 18px;
        }
        .profile-menu {
            width: 100%;
            margin-top: 24px;
        }
        .profile-menu button {
            width: 100%;
            background: none;
            border: none;
            padding: 12px 0;
            font-size: 1em;
            color: #333;
            text-align: left;
            cursor: pointer;
            border-radius: 6px;
            margin-bottom: 6px;
            transition: background 0.2s;
        }
        .profile-menu button.active, .profile-menu button:hover {
            background: #e0e0e0;
            color: #800000;
        }
        .profile-main {
            flex: 1;
            padding: 36px 40px;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .biodata-form {
            max-width: 420px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .biodata-form label {
            font-weight: 500;
            margin-bottom: 4px;
        }
        .biodata-form input {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        .biodata-form .btn {
            background: #800000;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 0;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
        }
        .biodata-form .btn:hover {
            background: #a83232;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .orders-table th, .orders-table td {
            border: 1px solid #eee;
            padding: 10px 8px;
            text-align: left;
            font-size: 0.98em;
        }
        .orders-table th {
            background: #f7f7fa;
        }
        .orders-table img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
        }
        @media (max-width: 900px) {
            .profile-wrapper { flex-direction: column; }
            .profile-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #eee; }
            .profile-main { padding: 24px 10px; }
        }
    </style>
</head>
<body>
<div class="profile-wrapper">
    <div class="profile-sidebar">
        <div class="profile-avatar">
            <i class="fa-solid fa-user"></i>
        </div>
        <div class="profile-name"><?= htmlspecialchars($user['username']) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        <div class="profile-menu">
            <button id="tabBtnBiodata" class="active" onclick="showTab('biodata')"><i class="fa-solid fa-id-card"></i> Biodata Diri</button>
            <button id="tabBtnRiwayat" onclick="showTab('riwayat')"><i class="fa-solid fa-box"></i> Riwayat Produk</button>
            <button onclick="logout()" style="margin-top:30px;background:#fff;color:#800000;border:1px solid #800000;"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </div>
    </div>
    <div class="profile-main">
        <div id="tabBiodata" class="tab-content active">
            <h2 style="margin-bottom:18px; color:#800000;">Biodata Diri</h2>
            <form class="biodata-form" method="post">
                <label for="username">Nama</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                <label for="telepon">Telepon</label>
                <input type="text" name="telepon" id="telepon" value="<?= htmlspecialchars($user['telepon']) ?>">
                <button type="submit" name="update_profile" class="btn"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
            </form>
            <hr style="margin:32px 0;">
            <h3 style="color:#800000;">Ganti Password</h3>
            <form class="biodata-form" method="post">
                <label for="old_password">Password Lama</label>
                <input type="password" name="old_password" id="old_password" required>
                <label for="new_password">Password Baru</label>
                <input type="password" name="new_password" id="new_password" required>
                <label for="new_password2">Ulangi Password Baru</label>
                <input type="password" name="new_password2" id="new_password2" required>
                <button type="submit" name="change_password" class="btn"><i class="fa-solid fa-key"></i> Ganti Password</button>
            </form>
        </div>
        <div id="tabRiwayat" class="tab-content">
            <h2 style="margin-bottom:18px; color:#800000;">Riwayat Produk</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($orders->num_rows > 0):
                    while ($order = $orders->fetch_assoc()): ?>
                    <tr>
                        <td style="display:flex;align-items:center;gap:10px;">
                            <img src="<?= htmlspecialchars($order['foto'] ?? 'uploads/default.png') ?>" alt="<?= htmlspecialchars($order['nama_produk']) ?>">
                            <div>
                                <div style="font-weight:600; color:#222;"> <?= htmlspecialchars($order['nama_produk']) ?> </div>
                            </div>
                        </td>
                        <td>Rp <?= number_format($order['harga'], 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($order['status']) ?></td>
                        <td><?= date('d/m/Y', strtotime($order['tanggal_pesanan'])) ?></td>
                        <td><a href="detail_pesanan.php?id=<?= $order['id'] ?>" style="color:#800000;text-decoration:underline;font-size:13px;">Detail</a></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center;">Belum ada riwayat pesanan</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function showTab(tab) {
    document.getElementById('tabBiodata').classList.remove('active');
    document.getElementById('tabRiwayat').classList.remove('active');
    document.getElementById('tabBtnBiodata').classList.remove('active');
    document.getElementById('tabBtnRiwayat').classList.remove('active');
    if(tab === 'biodata') {
        document.getElementById('tabBiodata').classList.add('active');
        document.getElementById('tabBtnBiodata').classList.add('active');
    } else {
        document.getElementById('tabRiwayat').classList.add('active');
        document.getElementById('tabBtnRiwayat').classList.add('active');
    }
}
function logout() {
    if(confirm('Yakin ingin logout?')) {
        window.location.href = 'login/logout.php';
    }
}
</script>
</body>
</html> 