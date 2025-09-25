<?php
session_start();
include 'Config/koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil semua pesanan user
$stmt = $conn->prepare("
    SELECT p.*, pr.nama_produk, pr.harga,
    (SELECT path_foto FROM foto_produk WHERE id_produk = pr.id_produk ORDER BY id_foto ASC LIMIT 1) as foto
    FROM pesanan p
    JOIN produk pr ON p.id_produk = pr.id_produk
    WHERE p.id_user = ?
    ORDER BY p.tanggal_pesanan DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - TechShop</title>
    <link rel="stylesheet" href="resource/css/style.css">
    <script src="https://kit.fontawesome.com/fd85fb070c.js" crossorigin="anonymous"></script>
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-id {
            font-weight: bold;
            color: #333;
        }
        
        .order-date {
            color: #666;
            font-size: 14px;
        }
        
        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-menunggu {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-diproses {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-kurir {
            background: #d4edda;
            color: #155724;
        }
        
        .status-dikirim {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-sampai {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .order-content {
            padding: 20px;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .product-price {
            color: #e74c3c;
            font-weight: bold;
            font-size: 18px;
        }
        
        .order-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .order-details h5 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        
        .payment-info {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .payment-info h5 {
            margin: 0 0 10px 0;
            color: #007bff;
        }
        
        .bukti-pembayaran {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .empty-orders {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-orders i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container top-nav">
            <a href="index.php" class="logo"><img src="resource/img/logo-black.png" alt=""></a>
            <div class="cart_header">
                <div onclick="open_cart()" class="icon_cart">
                    <i class="fa-solid fa-bag-shopping"></i>
                    <span class="count_item"></span>
                </div>
                <div class="total_price">
                    <p>My Cart:</p>
                    <p class="price_cart_Head">$0</p>
                </div>
            </div>
        </div>

        <nav>
            <div class="links container">
                <i onclick="open_menu()" class="fa-solid fa-bars btn_open_menu"></i>
                <ul id="menu">
                    <span onclick="close_menu()" class="bg-overlay"></span>
                    <i onclick="close_menu()" class="fa-regular fa-circle-xmark btn_close_menu"></i>
                    <img class="logo_menu" src="resource/img/logo-black.png" alt="">
                    <li><a href="index.php">home</a></li>
                    <li><a href="all_products.php">all products</a></li>
                </ul>

                <div class="loging_signup">
                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="profile_dropdown" id="profileDropdown">
                            <a href="#" id="profileBtn"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
                            <div class="dropdown_content" id="dropdownContent">
                                <a href="profile.php">Profil Saya</a>
                                <a href="my_orders.php">Pesanan Saya</a>
                                <a href="#" onclick="konfirmasiLogout()">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login/index.php">login <i class="fa-solid fa-right-to-bracket"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="orders-container">
            <h1><i class="fa-solid fa-shopping-bag"></i> Pesanan Saya</h1>
            
            <?php if ($result->num_rows > 0): ?>
                <?php while ($order = $result->fetch_assoc()): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Pesanan #<?= $order['id'] ?></div>
                                <div class="order-date"><?= date('d/m/Y H:i', strtotime($order['tanggal_pesanan'])) ?></div>
                            </div>
                            <div class="order-status status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                <?= $order['status'] ?>
                            </div>
                        </div>
                        
                        <div class="order-content">
                            <div class="product-info">
                                <img src="<?= htmlspecialchars($order['foto'] ?? 'uploads/default.png') ?>" 
                                     alt="<?= htmlspecialchars($order['nama_produk']) ?>" 
                                     class="product-image">
                                <div class="product-details">
                                    <h4><?= htmlspecialchars($order['nama_produk']) ?></h4>
                                    <div class="product-price">Rp <?= number_format($order['harga'], 0, ',', '.') ?></div>
                                </div>
                            </div>
                            
                            <div class="order-details">
                                <h5><i class="fa-solid fa-user"></i> Informasi Penerima</h5>
                                <div class="detail-row">
                                    <span class="detail-label">Nama:</span>
                                    <span><?= htmlspecialchars($order['nama_penerima']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email:</span>
                                    <span><?= htmlspecialchars($order['email']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Telepon:</span>
                                    <span><?= htmlspecialchars($order['telepon']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Alamat:</span>
                                    <span><?= htmlspecialchars($order['alamat']) ?></span>
                                </div>
                            </div>
                            
                            <?php if ($order['metode_pembayaran']): ?>
                                <div class="payment-info">
                                    <h5><i class="fa-solid fa-credit-card"></i> Informasi Pembayaran</h5>
                                    <div class="detail-row">
                                        <span class="detail-label">Metode:</span>
                                        <span><?= htmlspecialchars($order['metode_pembayaran']) ?></span>
                                    </div>
                                    <?php if ($order['tanggal_pembayaran']): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Tanggal Pembayaran:</span>
                                            <span><?= date('d/m/Y H:i', strtotime($order['tanggal_pembayaran'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['catatan_pembayaran']): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Catatan:</span>
                                            <span><?= htmlspecialchars($order['catatan_pembayaran']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['bukti_pembayaran']): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Bukti Pembayaran:</span>
                                            <a href="<?= htmlspecialchars($order['bukti_pembayaran']) ?>" target="_blank">
                                                <img src="uploads/bukti_pembayaran <?= htmlspecialchars($order['bukti_pembayaran']) ?>" 
                                                     alt="Bukti Pembayaran" class="bukti-pembayaran">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <?php if ($order['status'] === 'Menunggu Pembayaran'): ?>
                                    <a href="payment.php?id=<?= $order['id'] ?>" class="btn btn-primary">
                                        <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
                                    </a>
                                <?php elseif ($order['status'] === 'Diproses Admin'): ?>
                                    <span class="btn btn-warning">
                                        <i class="fa-solid fa-clock"></i> Menunggu Konfirmasi Admin
                                    </span>
                                <?php elseif ($order['status'] === 'Menunggu Kurir'): ?>
                                    <span class="btn btn-success">
                                        <i class="fa-solid fa-truck"></i> Pesanan Diproses
                                    </span>
                                <?php elseif ($order['status'] === 'Dikirim'): ?>
                                    <span class="btn btn-primary">
                                        <i class="fa-solid fa-shipping-fast"></i> Dalam Pengiriman
                                    </span>
                                <?php elseif ($order['status'] === 'Sampai'): ?>
                                    <span class="btn btn-success">
                                        <i class="fa-solid fa-check-circle"></i> Pesanan Selesai
                                    </span>
                                <?php endif; ?>
                                
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fa-solid fa-home"></i> Kembali ke Beranda
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-orders">
                    <i class="fa-solid fa-shopping-bag"></i>
                    <h3>Belum ada pesanan</h3>
                    <p>Anda belum memiliki pesanan. Mulai berbelanja sekarang!</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fa-solid fa-shopping-cart"></i> Mulai Belanja
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function konfirmasiLogout() {
            if (confirm('Yakin ingin logout?')) {
                window.location.href = 'login/logout.php';
            }
        }
    </script>
</body>
</html> 