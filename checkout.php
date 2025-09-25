<?php
session_start();
include 'Config/koneksi.php';

$user_id = $_SESSION['user_id'] ?? 0;
$id_beli = isset($_GET['beli']) ? intval($_GET['beli']) : null;
$id_produk_post = isset($_POST['id_produk']) ? intval($_POST['id_produk']) : null;
$produkLangsung = ($id_beli !== null) || ($id_produk_post !== null);

// Ambil produk yang akan dibeli
$produkList = [];
if ($produkLangsung) {
    // Tentukan ID produk yang akan dibeli
    $id_produk_beli = $id_beli ?? $id_produk_post;
    
    $stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id_produk_beli);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Ambil gambar pertama dari foto_produk
        $foto_stmt = $conn->prepare("SELECT path_foto FROM foto_produk WHERE id_produk = ? ORDER BY id_foto ASC LIMIT 1");
        $foto_stmt->bind_param("i", $row['id_produk']);
        $foto_stmt->execute();
        $foto_result = $foto_stmt->get_result();
        $foto = $foto_result->fetch_assoc();
        $row['foto'] = $foto['path_foto'] ?? 'uploads/default.png';
        $row['jumlah'] = 1; // Set default quantity untuk pembelian langsung
        $produkList[] = $row;
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("
        SELECT p.*, k.jumlah,
        (SELECT path_foto FROM foto_produk WHERE id_produk = p.id_produk ORDER BY id_foto ASC LIMIT 1) as foto
        FROM keranjang k
        JOIN produk p ON k.id_produk = p.id_produk
        WHERE k.id_user = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $produkList[] = $row;
    }
    $stmt->close();
}

// Proses checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checkout'])) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $telepon = $_POST['telepon'];
        $alamat = $_POST['alamat'];
        $latitude = $_POST['latitude'] ?? '';
        $longitude = $_POST['longitude'] ?? '';

        // Validasi alamat
        if (empty($alamat)) {
            throw new Exception('Alamat pengiriman harus diisi!');
        }

        // Validasi user login
        if (empty($user_id)) {
            throw new Exception('User harus login terlebih dahulu!');
        }

        // Validasi produk list
        if (empty($produkList)) {
            throw new Exception('Tidak ada produk yang akan dibeli!');
        }

        $pesanan_ids = [];
        
        if ($produkLangsung) {
            // Tentukan ID produk yang akan dibeli
            $id_produk_beli = $id_beli ?? $id_produk_post;
            
            // Validasi produk ada di database
            $check_stmt = $conn->prepare("SELECT id_produk, stok FROM produk WHERE id_produk = ?");
            $check_stmt->bind_param("i", $id_produk_beli);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                throw new Exception('Produk tidak ditemukan!');
            }
            
            $produk_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            // Cek stok
            if ($produk_data['stok'] < 1) {
                throw new Exception('Stok produk tidak mencukupi!');
            }
            
            // Insert pesanan dengan jumlah default 1
            $jumlah_beli = 1;
            $stmt = $conn->prepare("INSERT INTO pesanan (id_user, id_produk, jumlah, nama_penerima, email, telepon, alamat, latitude, longitude, status, tanggal_pesan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', NOW())");
            $stmt->bind_param("iiissssss", $user_id, $id_produk_beli, $jumlah_beli, $nama, $email, $telepon, $alamat, $latitude, $longitude);
            
            if (!$stmt->execute()) {
                throw new Exception('Gagal menyimpan pesanan: ' . $stmt->error);
            }
            
            $pesanan_id = $conn->insert_id;
            $pesanan_ids[] = $pesanan_id;
            $stmt->close();
            
            // Update stok produk
            $update_stmt = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");
            $update_stmt->bind_param("ii", $jumlah_beli, $id_produk_beli);
            $update_stmt->execute();
            $update_stmt->close();
            
        } else {
            // Pembelian dari keranjang
            foreach ($produkList as $item) {
                // Validasi produk dan stok
                $check_stmt = $conn->prepare("SELECT id_produk, stok FROM produk WHERE id_produk = ?");
                $check_stmt->bind_param("i", $item['id_produk']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows == 0) {
                    throw new Exception('Produk ' . $item['nama_produk'] . ' tidak ditemukan!');
                }
                
                $produk_data = $check_result->fetch_assoc();
                $check_stmt->close();
                
                // Cek stok
                if ($produk_data['stok'] < $item['jumlah']) {
                    throw new Exception('Stok produk ' . $item['nama_produk'] . ' tidak mencukupi!');
                }
                
                // Insert pesanan
                $stmt = $conn->prepare("INSERT INTO pesanan (id_user, id_produk, jumlah, nama_penerima, email, telepon, alamat, latitude, longitude, status, tanggal_pesan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', NOW())");
                $stmt->bind_param("iiissssss", $user_id, $item['id_produk'], $item['jumlah'], $nama, $email, $telepon, $alamat, $latitude, $longitude);
                
                if (!$stmt->execute()) {
                    throw new Exception('Gagal menyimpan pesanan untuk produk ' . $item['nama_produk'] . ': ' . $stmt->error);
                }
                
                $pesanan_ids[] = $conn->insert_id;
                $stmt->close();
                
                // Update stok produk
                $update_stmt = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");
                $update_stmt->bind_param("ii", $item['jumlah'], $item['id_produk']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            // Hapus item dari keranjang setelah semua pesanan berhasil
            $delete_stmt = $conn->prepare("DELETE FROM keranjang WHERE id_user = ?");
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect ke halaman pembayaran dengan ID pesanan pertama
        $pesanan_id = $pesanan_ids[0];
        
        echo "<script>
            alert('Pesanan berhasil dibuat! Silakan lakukan pembayaran.');
            window.location='payment.php?id=" . $pesanan_id . "';
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction jika ada error
        $conn->rollback();
        
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "');
            window.history.back();
        </script>";
        exit;
    }
}

// Query untuk cart items (sama seperti sebelumnya)
$id_user = $_SESSION['user_id'] ?? 0;
$cart_items_stmt = $conn->prepare("
    SELECT 
        k.id_keranjang,
        k.jumlah,
        p.id_produk,
        p.nama_produk,
        p.harga,
        (
            SELECT fp.path_foto 
            FROM foto_produk fp 
            WHERE fp.id_produk = p.id_produk 
            ORDER BY fp.id_foto ASC 
            LIMIT 1
        ) AS path_foto
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_user = ?
");
$cart_items_stmt->bind_param("i", $id_user);
$cart_items_stmt->execute();
$cart_items = $cart_items_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop</title>

    <!-- File CSS -->
    <link rel="stylesheet" href="resource/css/style.css">

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/fd85fb070c.js" crossorigin="anonymous"></script>

    <!-- Link Swiper's CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        .address-picker {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .address-picker h3 {
            margin-bottom: 15px;
            color: #800000;
            font-size: 1.1em;
        }
        
        .map-container {
            margin-bottom: 15px;
            position: relative;
        }
        
        .map-container iframe {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .address-inputs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .address-inputs input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .address-inputs button {
            white-space: nowrap;
            transition: background-color 0.3s;
        }
        
        .address-inputs button:hover {
            background: #a83232 !important;
        }
        
        .selected-address {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }
        
        .selected-address p {
            margin: 5px 0;
        }
        
        #selectedAddress {
            color: #666;
            font-style: italic;
        }
        
        .checkout-form textarea {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
        }
        
        .checkout-form textarea:focus {
            background-color: white;
            border-color: #800000;
        }
        
        .map-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .map-overlay:hover {
            background: rgba(0,0,0,0.2);
        }
        
        /* Preset address buttons styling */
        .preset-addresses button {
            background: #f8f9fa !important;
            border: 1px solid #ddd !important;
            color: #333 !important;
            transition: all 0.3s ease !important;
        }
        
        .preset-addresses button:hover {
            background: #800000 !important;
            border-color: #800000 !important;
            color: white !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .address-inputs {
                flex-direction: column;
                gap: 8px;
            }
            
            .address-inputs input {
                margin-bottom: 0;
            }
            
            .preset-addresses button {
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .address-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .address-actions button {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container top-nav">
            <a href="index.php" class="logo"><img src="resource/img/logo-black.png" alt=""></a>
            <form action="" class="search">
                <input type="search" placeholder="Search for products...">
                <button type="submit">Search</button>
            </form>

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
                    <li class="active"><a href="index.php">home</a></li>
                    <li><a href="all_products.php">all products</a></li>
                </ul>

                <div class="loging_signup">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="profile_dropdown" id="profileDropdown">
                        <a href="#" id="profileBtn"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
                        <div class="dropdown_content" id="dropdownContent">
                            <a href="profile.php">Profil Saya</a>
                            <a href="#" onclick="konfirmasiLogout()">Logout</a>
                                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                            <script>
                                function konfirmasiLogout() {
                                    Swal.fire({
                                        title: 'Yakin ingin logout?',
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#d33',
                                        cancelButtonColor: '#aaa',
                                        confirmButtonText: 'Ya, logout',
                                        cancelButtonText: 'Batal'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = '../skripsi/login/logout.php';
                                        }
                                    });
                                }
                            </script>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login/index.php">login <i class="fa-solid fa-right-to-bracket"></i></a>
                <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="cart">
        <div class="top_cart">
        <?php
            $total_jumlah_item = 0;
            if ($cart_items && $cart_items->num_rows > 0) {
                $cart_items->data_seek(0); // Reset pointer
                while ($item = $cart_items->fetch_assoc()) {
                    $total_jumlah_item += $item['jumlah'];
                }
                $cart_items->data_seek(0); // Reset ulang agar nanti dipakai di loop bawah
            }
        ?>
        <h3>My cart <span class="count_item_cart">(<?= $total_jumlah_item ?> Item in Cart)</span></h3>
            <span onclick="close_cart()" class="close_cart"><i class="fa-regular fa-circle-xmark"></i></span>
        </div>

        <div class="items_in_cart">
            <?php
            $total = 0;

            if ($cart_items && $cart_items->num_rows > 0):
                while ($item = $cart_items->fetch_assoc()):
                    $harga = $item['harga'];
                    $subtotal = $harga * $item['jumlah'];
                    $total += $subtotal;
            ?>
                <div class="cart_item" style="display:flex; gap:10px;">
                    <img src="<?= htmlspecialchars($item['path_foto']) ?>" width="50">
                    <div>
                        <p><?= htmlspecialchars($item['nama_produk']) ?></p>
                        <p>Rp <?= number_format($harga, 0, ',', '.') ?> × <?= $item['jumlah'] ?></p>
                        <div class="qty_control" style="margin-top: 5px;">
                        <button class="btnKurang" data-id="<?= $item['id_produk'] ?>">−</button>
                        <span style="margin: 0 8px;"><?= $item['jumlah'] ?></span>
                        <button class="btnTambah" data-id="<?= $item['id_produk'] ?>">+</button>
                    </div>
                    </div>
                </div>
            <?php
                endwhile;
            else:
                echo '<p style="padding:10px;">Keranjang kosong</p>';
            endif;
            ?>
        </div>

        <!-- Total Cart -->
        <div class="bottom_Cart">
            <div class="total">
                <p>Cart subtotal</p>
                <p class="price_cart_total">Rp <?= number_format($total, 0, ',', '.') ?></p>
            </div>

            <div class="button_Cart">
                <a href="checkout.php" class="btn_cart">Proceed to checkout</a>
                <button onclick="close_cart()" class="btn_cart trans_bg">Shop more</button>
            </div>
        </div>
    </div>

<!-- ======= MAIN CONTENT ======= -->
<main>
    <form method="post"> <!-- FORM dibuka di luar semua -->
        <!-- Tambahkan hidden input untuk pembelian langsung -->
        <?php if ($produkLangsung): ?>
            <input type="hidden" name="id_produk" value="<?= $id_beli ?? $id_produk_post ?>">
        <?php endif; ?>
        
    <div class="checkout-wrapper container">
        <!-- Form -->
        <div class="checkout-form">
            <h2>Form Checkout</h2>
            <input type="text" name="nama" placeholder="Nama Lengkap" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="telepon" placeholder="Telepon" required>
            
            <!-- Google Maps Address Picker -->
            <div class="address-picker">
                <h3><i class="fa fa-map-marker-alt"></i> Pilih Alamat di Peta</h3>
                <div class="map-container">
                    <iframe id="googleMap" 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126915.067434!2d106.7891575!3d-6.2297465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f3e945e34b9d%3A0x5371bf0fdad786a2!2sJakarta!5e0!3m2!1sen!2sid!4v1234567890"
                            width="100%" 
                            height="300" 
                            style="border:0; border-radius: 8px;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
                <div class="address-inputs">
                    <input type="text" id="searchAddress" placeholder="Cari alamat..." style="margin-bottom: 10px;">
                    <button type="button" id="searchBtn" style="background: #800000; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-search"></i> Cari
                    </button>
                </div>
                <div class="preset-addresses" style="margin: 15px 0;">
                    <p style="margin-bottom: 10px; font-weight: bold; color: #333;">Alamat Populer:</p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" onclick="selectPresetAddress('Jakarta Pusat')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Jakarta Pusat</button>
                        <button type="button" onclick="selectPresetAddress('Jakarta Selatan')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Jakarta Selatan</button>
                        <button type="button" onclick="selectPresetAddress('Bandung')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Bandung</button>
                        <button type="button" onclick="selectPresetAddress('Surabaya')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Surabaya</button>
                        <button type="button" onclick="selectPresetAddress('Medan')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Medan</button>
                        <button type="button" onclick="selectPresetAddress('Semarang')" style="background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #333; transition: all 0.3s ease;">Semarang</button>
                    </div>
                </div>
                <div class="selected-address">
                    <p><strong>Alamat yang dipilih:</strong></p>
                    <p id="selectedAddress">Klik pada peta untuk memilih alamat</p>
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                </div>
            </div>
            
            <div class="address-actions" style="margin: 15px 0;">
                <button type="button" onclick="setManualAddress()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                    <i class="fa fa-edit"></i> Input Alamat Manual
                </button>
                <button type="button" onclick="clearAddress()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    <i class="fa fa-times"></i> Reset Alamat
                </button>
            </div>
            
            <textarea name="alamat" id="alamatTextarea" placeholder="Alamat Pengiriman (akan terisi otomatis dari peta)" rows="4" required readonly></textarea>
            <small style="color: #666; display: block; margin-top: 5px;">
                <i class="fa fa-info-circle"></i> Klik pada peta untuk memilih lokasi, atau ketik alamat di kotak pencarian
            </small>
        </div>

        <!-- Produk -->
        <div class="checkout-produk">
            <h3>Produk yang Dibeli</h3>
            <?php
            $total_harga = 0;
            ?>
            <?php if (empty($produkList)): ?>
                <p>Tidak ada produk yang diproses.</p>
            <?php else: ?>
                <?php foreach ($produkList as $produk): ?>
                    <?php
                        $jumlah = $produk['jumlah'] ?? 1;
                        $subtotal = $jumlah * $produk['harga'];
                        $total_harga += $subtotal;
                        $pathFoto = 'uploads/' . ($produk['foto'] ?? 'default.png');
                    ?>
                    <div class="produk-item">
                        <img src="<?= htmlspecialchars($produk['foto'] ?? 'uploads/default.png') ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
                        <div>
                            <h4><?= htmlspecialchars($produk['nama_produk']) ?></h4>
                            <p>Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                            <?php if (isset($produk['jumlah'])): ?>
                                <p>Jumlah: <?= $jumlah ?></p>
                            <?php endif; ?>
                            <p><strong>Subtotal:</strong> Rp <?= number_format($subtotal, 0, ',', '.') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 15px;">
                    <h4 style="margin-bottom: 10px;">Total Harga:</h4>
                    <p style="font-size: 1.2em; font-weight: bold; color: red;">Rp <?= number_format($total_harga, 0, ',', '.') ?></p>
                    <button type="submit" name="submit_checkout" class="btn-action btn-beli" style="margin-top: 15px;">Proses Checkout</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </form>
</main>

<!-- ======= FOOTER (tambahkan jika kamu punya versi aslinya) ======= -->
<footer style="text-align:center; padding:20px; background:#f2f2f2; margin-top:40px;">
    <p>© <?= date("Y") ?> TechShop. All rights reserved.</p>
</footer>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="resource/js/swiper.js"></script>
    <script src="resource/js/main.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Cart functionality
        document.querySelectorAll(".btnTambah").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.dataset.id;
                updateCart(id, "tambah");
            });
        });

        document.querySelectorAll(".btnKurang").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.dataset.id;
                updateCart(id, "kurang");
            });
        });

        // Address picker functionality
        initializeAddressPicker();
    });

    function updateCart(id_produk, aksi) {
        fetch("update-cart.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id_produk=${id_produk}&aksi=${aksi}`
        })
        .then(res => res.text())
        .then(response => {
            if (response.trim() === "success") {
                loadCart(); // bisa diganti jadi loadCart(); jika sudah ada
            } else {
                alert("Gagal memperbarui keranjang: " + response);
            }
        });
    }

    // Address Picker Functions
    function initializeAddressPicker() {
        const searchBtn = document.getElementById('searchBtn');
        const searchInput = document.getElementById('searchAddress');
        const mapIframe = document.getElementById('googleMap');
        
        // Search functionality
        searchBtn.addEventListener('click', function() {
            const address = searchInput.value.trim();
            if (address) {
                searchAddress(address);
            }
        });
        
        // Enter key search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const address = searchInput.value.trim();
                if (address) {
                    searchAddress(address);
                }
            }
        });
        
        // Map click simulation (since we can't directly interact with iframe)
        const mapContainer = document.querySelector('.map-container');
        mapContainer.addEventListener('click', function(e) {
            // Show instruction to use search instead
            showMapInstruction();
        });
    }
    
    function searchAddress(address) {
        // Create a new iframe with the searched address
        const mapIframe = document.getElementById('googleMap');
        const encodedAddress = encodeURIComponent(address);
        const newMapUrl = `https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126915.067434!2d106.7891575!3d-6.2297465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f3e945e34b9d%3A0x5371bf0fdad786a2!2s${encodedAddress}!5e0!3m2!1sen!2sid!4v1234567890`;
        
        mapIframe.src = newMapUrl;
        
        // Update the selected address
        document.getElementById('selectedAddress').textContent = address;
        document.getElementById('alamatTextarea').value = address;
        
        // Simulate coordinates (in real implementation, you'd use Google Maps API)
        // For now, we'll use placeholder coordinates
        document.getElementById('latitude').value = '-6.2297465';
        document.getElementById('longitude').value = '106.7891575';
        
        // Show success message
        showNotification('Alamat ditemukan dan dipilih!', 'success');
    }
    
    function showMapInstruction() {
        showNotification('Gunakan kotak pencarian di atas untuk mencari alamat spesifik', 'info');
    }
    
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        // Set background color based on type
        switch(type) {
            case 'success':
                notification.style.backgroundColor = '#28a745';
                break;
            case 'error':
                notification.style.backgroundColor = '#dc3545';
                break;
            case 'warning':
                notification.style.backgroundColor = '#ffc107';
                notification.style.color = '#212529';
                break;
            default:
                notification.style.backgroundColor = '#17a2b8';
        }
        
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 3000);
    }
    
    // Manual address input function
    function setManualAddress() {
        const address = prompt('Masukkan alamat lengkap:');
        if (address) {
            document.getElementById('selectedAddress').textContent = address;
            document.getElementById('alamatTextarea').value = address;
            document.getElementById('alamatTextarea').readOnly = false;
            showNotification('Alamat manual berhasil diset!', 'success');
        }
    }

    function clearAddress() {
        document.getElementById('selectedAddress').textContent = 'Klik pada peta untuk memilih alamat';
        document.getElementById('alamatTextarea').value = '';
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        document.getElementById('alamatTextarea').readOnly = true;
        showNotification('Alamat berhasil direset!', 'info');
    }
    
    function selectPresetAddress(address) {
        document.getElementById('searchAddress').value = address;
        searchAddress(address);
    }
    </script>
<?php include 'ai-chat.php'; ?>
</body>
</html>