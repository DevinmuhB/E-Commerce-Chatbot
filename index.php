<?php
session_start();
include 'Config/koneksi.php';

function getProdukDenganGambar($conn) {
    $sql = "SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga,
               GROUP_CONCAT(f.path_foto ORDER BY f.id_foto ASC) AS all_foto
        FROM produk p
        LEFT JOIN foto_produk f ON p.id_produk = f.id_produk
        GROUP BY p.id_produk, p.nama_produk, p.deskripsi, p.harga";

    $result = $conn->query($sql);
    $produkList = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $gambar = explode(',', $row['all_foto']);
            $row['foto1'] = $gambar[0] ?? 'uploads/default.png';
            $row['foto2'] = $gambar[1] ?? $row['foto1'];
            $produkList[] = $row;
        }
    }
    return $produkList;
}

function getProdukByKategori($conn, $id_kategori) {
    $sql = "SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga,
               GROUP_CONCAT(f.path_foto ORDER BY f.id_foto ASC) AS all_foto
        FROM produk p
        LEFT JOIN foto_produk f ON p.id_produk = f.id_produk
        WHERE p.id_kategori = ?
        GROUP BY p.id_produk, p.nama_produk, p.deskripsi, p.harga";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_kategori);
    $stmt->execute();
    $result = $stmt->get_result();

    $produkList = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $gambar = explode(',', $row['all_foto']);
            $row['foto1'] = $gambar[0] ?? 'uploads/default.png';
            $row['foto2'] = $gambar[1] ?? $row['foto1'];
            $produkList[] = $row;
        }
    }

    return $produkList;
}

$produkLaptop = getProdukByKategori($conn, 1);
$produkHP = getProdukByKategori($conn, 2);
$produkList = getProdukDenganGambar($conn);

// Ambil rata-rata rating dan jumlah ulasan untuk semua produk
$ratingMap = [];
$res = $conn->query("SELECT id_produk, AVG(rating) as avg_rating, COUNT(*) as jml_ulasan FROM ulasan GROUP BY id_produk");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ratingMap[$row['id_produk']] = [
            'avg' => round($row['avg_rating'], 1),
            'count' => $row['jml_ulasan']
        ];
    }
}

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
</head>
<body>
    <header>
        <div class="container top-nav">
            <a href="index.php" class="logo"><img src="resource/img/logo-black.png" alt=""></a>
            <form action="all_products.php" method="GET" class="search">
                <input type="search" name="search" placeholder="Search for products..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
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
                            <a href="my_orders.php">Pesanan Saya</a>
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

    <section class="slider">
        <!-- Swiper -->
        <div class="slide-swp mySwiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <a href="all_products.php"><img src="resource/img/slider-01.jpg" alt=""></a>
                </div>
                <div class="swiper-slide">
                    <a href="all_products.php"><img src="resource/img/slider-02.jpg" alt=""></a>
                </div>
                <div class="swiper-slide">
                    <a href="all_products.php"><img src="resource/img/slider-03.jpg" alt=""></a>
                </div>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <div class="feature_item">
                <i class="fa-solid fa-truck-fast"></i>
                <div class="text">
                    <h4>Fast Delivery</h4>
                    <p>Fast Delivery to all areas</p>
                </div>
            </div>

            <div class="feature_item">
                <i class="fa-regular fa-credit-card"></i>
                <div class="text">
                    <h4>Payment Method</h4>
                    <p>Payment via Dana and Bank</p>
                </div>
            </div>

            <div class="feature_item">
                <i class="fa-solid fa-shield-halved"></i>
                <div class="text">
                    <h4>Protection</h4>
                    <p>Protection to your data</p>
                </div>
            </div>

            <div class="feature_item">
                <i class="fa-solid fa-headset"></i>
                <div class="text">
                    <h4>Customer Service</h4>
                    <p>Customer Service 24x7</p>
                </div>
            </div>
        </div>
    </section>


    <section class="banner">
        <div class="container">
            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-1.jpg" alt="">               
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-2.jpg" alt="">             
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-3.jpg" alt="">                
            </div>
        </div>
    </section>

    <?php
    if (isset($_SESSION['user_id'])) {
        $id_user = $_SESSION['user_id'];
        // Tampilkan produk terbaru untuk user login
        $produkList = $conn->query("
            SELECT 
                p.id_produk, 
                p.nama_produk, 
                p.harga,
                GROUP_CONCAT(fp.path_foto ORDER BY fp.id_foto ASC) AS semua_gambar
            FROM produk p
            LEFT JOIN foto_produk fp ON p.id_produk = fp.id_produk
            GROUP BY p.id_produk
            ORDER BY p.created_at DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);
    } else {
        // Tampilkan produk terbaru juga untuk non-login
        $produkList = $conn->query("
            SELECT 
                p.id_produk, 
                p.nama_produk, 
                p.harga,
                GROUP_CONCAT(fp.path_foto ORDER BY fp.id_foto ASC) AS semua_gambar
            FROM produk p
            LEFT JOIN foto_produk fp ON p.id_produk = fp.id_produk
            GROUP BY p.id_produk
            ORDER BY p.created_at DESC
            LIMIT 10
        ")->fetch_all(MYSQLI_ASSOC);
    }
    ?>
    <section class="slide slide_sale">
        <div class="container">
            <div class="sale_sec mySwiper">
                <div class="top_slide">
                    <h2>our product <span>Best Offers</span></h2>
                </div>
                <div class="products swiper-wrapper">
                    <?php foreach ($produkList as $produk): ?>
                        <?php
                            $gambarArray = explode(',', $produk['semua_gambar']);
                            $foto1 = $gambarArray[0] ?? 'uploads/default.png';
                            $foto2 = $gambarArray[1] ?? $foto1;

                            // Perbaiki path gambar agar menggunakan folder uploads di root directory
                            if (!file_exists($foto1)) {
                                $foto1 = 'uploads/default.png';
                            }
                            if (!file_exists($foto2)) {
                                $foto2 = $foto1;
                            }
                        ?>
                        <div class="swiper-slide product">
                            <div class="img_product">
                                <img class="img_main" src="<?= htmlspecialchars($foto1) ?>" alt="gambar utama">
                                <img class="img_hover" src="<?= htmlspecialchars($foto2) ?>" alt="gambar hover">
                            </div>
                            <div class="name_product">
                                <a href="detail_produk.php?id=<?= $produk['id_produk'] ?>"><?= htmlspecialchars($produk['nama_produk']) ?></a>
                            </div>
                            <div class="stars">
                                <?php
                                $avg = $ratingMap[$produk['id_produk']]['avg'] ?? 0;
                                $full = floor($avg);
                                $half = ($avg - $full) >= 0.5 ? 1 : 0;
                                $empty = 5 - $full - $half;
                                for ($i = 0; $i < $full; $i++): ?>
                                    <i class="fa fa-star"></i>
                                <?php endfor; ?>
                                <?php if ($half): ?><i class="fa fa-star-half-alt"></i><?php endif; ?>
                                <?php for ($i = 0; $i < $empty; $i++): ?>
                                    <i class="fa fa-star-o"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="price">
                                <p>Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                            </div>
                            <div class="button-container">
                                <button type="button" class="btnKeranjang btn-action" data-id="<?= $produk['id_produk'] ?>">
                                    <i class="fa fa-shopping-cart"></i> Keranjang
                                </button>
                                <form method="post" action="checkout.php" style="margin: 0;">
                                    <input type="hidden" name="id_produk" value="<?= $produk['id_produk'] ?>">
                                    <button type="submit" class="btn-action btn-beli">
                                        <i class="fa fa-bolt"></i> Beli Sekarang
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next btn_Swip"></div>
                <div class="swiper-button-prev btn_Swip"></div>
            </div>
        </div>
    </section>

    <section class="banner banner_big">
        <div class="container">
            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-4.jpg" alt="">             
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-5.jpg" alt="">               
            </div>
        </div>
    </section>

    <?php
    $id_user = $_SESSION['user_id'] ?? null;
    $query = "
        SELECT 
            p.id_produk, 
            p.nama_produk, 
            p.harga,
            COALESCE(SUM(j.jumlah), 0) AS total_terjual,
            GROUP_CONCAT(f.path_foto ORDER BY f.id_foto ASC) AS semua_gambar
        FROM produk p
        LEFT JOIN penjualan j ON p.id_produk = j.id_produk
        LEFT JOIN foto_produk f ON p.id_produk = f.id_produk
        GROUP BY p.id_produk
        ORDER BY total_terjual DESC
        LIMIT 10
    ";

    $produkList = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
    ?>

    <section class="slide slide_sale">
        <div class="container">
            <div class="sale_sec mySwiper">
                <div class="top_slide">
                    <h2>our product <span>Best Product</span></h2>
                </div>
                <div class="products swiper-wrapper">
                    <?php foreach ($produkList as $produk): ?>
                        <?php
                            $gambarArray = explode(',', $produk['semua_gambar']);
                            $foto1 = $gambarArray[0] ?? 'uploads/default.png';
                            $foto2 = $gambarArray[1] ?? $foto1;

                            // Perbaiki path gambar agar menggunakan folder uploads di root directory
                            if (!file_exists($foto1)) {
                                $foto1 = 'uploads/default.png';
                            }
                            if (!file_exists($foto2)) {
                                $foto2 = $foto1;
                            }
                        ?>
                        <div class="swiper-slide product">
                            <div class="img_product">
                                <img class="img_main" src="<?= htmlspecialchars($foto1) ?>" alt="gambar utama">
                                <img class="img_hover" src="<?= htmlspecialchars($foto2) ?>" alt="gambar hover">
                            </div>
                            <div class="name_product">
                                <a href="detail_produk.php?id=<?= $produk['id_produk'] ?>"><?= htmlspecialchars($produk['nama_produk']) ?></a>
                            </div>
                            <div class="stars">
                                <?php
                                $avg = $ratingMap[$produk['id_produk']]['avg'] ?? 0;
                                $full = floor($avg);
                                $half = ($avg - $full) >= 0.5 ? 1 : 0;
                                $empty = 5 - $full - $half;
                                for ($i = 0; $i < $full; $i++): ?>
                                    <i class="fa fa-star"></i>
                                <?php endfor; ?>
                                <?php if ($half): ?><i class="fa fa-star-half-alt"></i><?php endif; ?>
                                <?php for ($i = 0; $i < $empty; $i++): ?>
                                    <i class="fa fa-star-o"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="price">
                                <p>Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                            </div>
                            <div class="button-container">
                                <button type="button" class="btnKeranjang btn-action" data-id="<?= $produk['id_produk'] ?>">
                                    <i class="fa fa-shopping-cart"></i> Keranjang
                                </button>
                                <form method="post" action="checkout.php" style="margin: 0;">
                                    <input type="hidden" name="id_produk" value="<?= $produk['id_produk'] ?>">
                                    <button type="submit" class="btn-action btn-beli">
                                        <i class="fa fa-bolt"></i> Beli Sekarang
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next btn_Swip"></div>
                <div class="swiper-button-prev btn_Swip"></div>
            </div>
        </div>
    </section>

    <section class="banner banner_big">
        <div class="container">
            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-6.jpg" alt="">              
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-7.jpg" alt="">               
            </div>
        </div>
    </section>

    <?php
    $id_user = $_SESSION['user_id'] ?? null;
    $query = "
        SELECT 
            p.id_produk,
            p.nama_produk,
            p.harga,
            p.created_at,
            GROUP_CONCAT(f.path_foto ORDER BY f.id_foto ASC) AS semua_gambar
        FROM produk p
        LEFT JOIN foto_produk f ON p.id_produk = f.id_produk
        GROUP BY p.id_produk
        ORDER BY p.created_at DESC
        LIMIT 10
    ";

    $produkList = $conn->query($query)->fetch_all(MYSQLI_ASSOC);        
    ?>

    <section class="slide slide_sale">
        <div class="container">
            <div class="sale_sec mySwiper">
                <div class="top_slide">
                    <h2>our product <span>Recent Product</span></h2>
                </div>
                <div class="products swiper-wrapper">
                    <?php foreach ($produkList as $produk): ?>
                        <?php
                            $gambarArray = explode(',', $produk['semua_gambar']);
                            $foto1 = $gambarArray[0] ?? 'uploads/default.png';
                            $foto2 = $gambarArray[1] ?? $foto1;

                            // Perbaiki path gambar agar menggunakan folder uploads di root directory
                            if (!file_exists($foto1)) {
                                $foto1 = 'uploads/default.png';
                            }
                            if (!file_exists($foto2)) {
                                $foto2 = $foto1;
                            }
                        ?>
                        <div class="swiper-slide product">
                            <div class="img_product">
                                <img class="img_main" src="<?= htmlspecialchars($foto1) ?>" alt="gambar utama">
                                <img class="img_hover" src="<?= htmlspecialchars($foto2) ?>" alt="gambar hover">
                            </div>
                            <div class="name_product">
                                <a href="detail_produk.php?id=<?= $produk['id_produk'] ?>"><?= htmlspecialchars($produk['nama_produk']) ?></a>
                            </div>
                            <div class="stars">
                                <?php
                                $avg = $ratingMap[$produk['id_produk']]['avg'] ?? 0;
                                $full = floor($avg);
                                $half = ($avg - $full) >= 0.5 ? 1 : 0;
                                $empty = 5 - $full - $half;
                                for ($i = 0; $i < $full; $i++): ?>
                                    <i class="fa fa-star"></i>
                                <?php endfor; ?>
                                <?php if ($half): ?><i class="fa fa-star-half-alt"></i><?php endif; ?>
                                <?php for ($i = 0; $i < $empty; $i++): ?>
                                    <i class="fa fa-star-o"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="price">
                                <p>Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                            </div>
                            <div class="button-container">
                                <button type="button" class="btnKeranjang btn-action" data-id="<?= $produk['id_produk'] ?>">
                                    <i class="fa fa-shopping-cart"></i> Keranjang
                                </button>
                                <form method="post" action="checkout.php" style="margin: 0;">
                                    <input type="hidden" name="id_produk" value="<?= $produk['id_produk'] ?>">
                                    <button type="submit" class="btn-action btn-beli">
                                        <i class="fa fa-bolt"></i> Beli Sekarang
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next btn_Swip"></div>
                <div class="swiper-button-prev btn_Swip"></div>
            </div>
        </div>
    </section>

    <section class="banner">
        <div class="container">

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-8.jpg" alt="">              
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-9.jpg" alt="">  
            </div>

            <div class="banner_img">
                <div class="glass_hover"></div>
                <a href="all_products.php"></a>
                    <img src="resource/img/banner/banner-10.jpg" alt="">  
            </div>
        </div>
    </section>

    <footer>
        <div class="bottom_footer">
            <div class="container">
                <p>Copyright &copy; <span>TechShop.</span> all rights reserved</p>
                <div class="payment_img">
                    <img src="resource/img/payment-1.jpg" alt="">
                    <img src="resource/img/payment-2.jpg" alt="">
                    <img src="resource/img/payment-3.jpg" alt="">
                    <img src="resource/img/payment-4.jpg" alt="">
                </div>
            </div>
        </div>
    </footer>
    
 <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="resource/js/swiper.js"></script>
    <script src="resource/js/main.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
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
    </script>
    <?php include 'ai-chat.php'; ?>
    </body>
</html>