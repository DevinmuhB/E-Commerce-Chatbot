<?php
session_start();
include 'Config/koneksi.php';

$id_produk = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_produk <= 0) {
    echo '<div style="margin:40px;text-align:center;color:red;">Produk tidak ditemukan.</div>';
    exit;
}

// Ambil data produk
$stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
$stmt->bind_param("i", $id_produk);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
if (!$produk) {
    echo '<div style="margin:40px;text-align:center;color:red;">Produk tidak ditemukan.</div>';
    exit;
}

// Ambil foto-foto produk
$fotos = [];
$foto_stmt = $conn->prepare("SELECT path_foto FROM foto_produk WHERE id_produk = ?");
$foto_stmt->bind_param("i", $id_produk);
$foto_stmt->execute();
$foto_res = $foto_stmt->get_result();
while ($row = $foto_res->fetch_assoc()) {
    $fotos[] = $row['path_foto'];
}
if (empty($fotos)) {
    $fotos[] = 'uploads/default.png';
}

// Ambil ulasan produk
$ulasan_stmt = $conn->prepare("SELECT u.*, us.username FROM ulasan u JOIN users us ON u.id_user = us.id WHERE u.id_produk = ? ORDER BY u.created_at DESC");
$ulasan_stmt->bind_param("i", $id_produk);
$ulasan_stmt->execute();
$ulasan_res = $ulasan_stmt->get_result();
$ulasans = [];
$total_rating = 0;
while ($row = $ulasan_res->fetch_assoc()) {
    $ulasans[] = $row;
    $total_rating += $row['rating'];
}
$jumlah_ulasan = count($ulasans);
$rata_rating = $jumlah_ulasan > 0 ? round($total_rating / $jumlah_ulasan, 1) : 0;

// Ambil produk serupa (kategori sama, kecuali produk ini)
$serupa_stmt = $conn->prepare("
    SELECT p.*, 
           (SELECT fp.path_foto 
            FROM foto_produk fp 
            WHERE fp.id_produk = p.id_produk 
            ORDER BY fp.id_foto ASC 
            LIMIT 1) AS path_foto
    FROM produk p 
    WHERE p.id_kategori = ? AND p.id_produk != ? 
    LIMIT 6
");
$serupa_stmt->bind_param("ii", $produk['id_kategori'], $id_produk);
$serupa_stmt->execute();
$serupa_res = $serupa_stmt->get_result();
$produk_serupa = [];
while ($row = $serupa_res->fetch_assoc()) {
    $produk_serupa[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produk['nama_produk']) ?> - TechShop</title>
    <link rel="stylesheet" href="resource/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Base styles (existing styles remain) */
.detail-wrap { 
    max-width: 1100px; 
    margin: 40px auto; 
    background: #fff; 
    border-radius: 16px; 
    box-shadow: 0 4px 24px rgba(0,0,0,0.08); 
    display: flex; 
    gap: 40px; 
    padding: 36px 32px; 
}

.detail-imgs { 
    flex: 0 0 340px; 
}

.main-img { 
    width: 100%; 
    height: 320px; 
    object-fit: cover; 
    border-radius: 12px; 
    border: 1px solid #eee; 
}

.thumbs { 
    display: flex; 
    gap: 10px; 
    margin-top: 12px; 
}

.thumbs img { 
    width: 60px; 
    height: 60px; 
    object-fit: cover; 
    border-radius: 8px; 
    border: 2px solid #eee; 
    cursor: pointer; 
    transition: 0.2s; 
}

.thumbs img.active, 
.thumbs img:hover { 
    border-color: #800000; 
}

.detail-info { 
    flex: 1; 
}

.prod-title { 
    font-size: 2em; 
    color: #800000; 
    margin-bottom: 8px; 
}

.prod-price { 
    font-size: 1.5em; 
    color: #d32f2f; 
    font-weight: 700; 
    margin-bottom: 12px; 
}

.prod-desc { 
    color: #444; 
    font-size: 1.1em; 
    margin-bottom: 18px; 
}

.prod-rating { 
    margin-bottom: 10px; 
}

.prod-rating .fa-star { 
    color: #FFD700; 
}

.prod-rating .fa-star.empty { 
    color: #ddd; 
}

.btns { 
    display: flex; 
    gap: 16px; 
    margin-bottom: 24px; 
}

.btn-beli, 
.btn-keranjang { 
    padding: 12px 32px; 
    border: none; 
    border-radius: 6px; 
    font-size: 1.1em; 
    font-weight: 600; 
    cursor: pointer; 
}

.btn-beli { 
    background: #800000; 
    color: #fff; 
}

.btn-beli:hover { 
    background: #a83232; 
}

.btn-keranjang { 
    background: #fff; 
    color: #800000; 
    border: 2px solid #800000; 
}

.btn-keranjang:hover { 
    background: #f7f7fa; 
}

.ulasan-section, 
.serupa-section {
    max-width: 800px;
    margin: 48px auto 0 auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.04);
    padding: 32px 28px;
}

.ulasan-title, 
.serupa-title {
    text-align: center;
    font-size: 1.5em;
    margin-bottom: 24px;
    color: #800000;
}

.ulasan-list {
    margin-bottom: 32px;
}

.ulasan-item {
    background: #f7f7fa; 
    border-radius: 10px; 
    padding: 18px 20px; 
    margin-bottom: 18px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.ulasan-user { 
    font-weight: 600; 
    color: #800000; 
}

.ulasan-rating .fa-star { 
    color: #FFD700; 
}

.ulasan-rating .fa-star.empty { 
    color: #ddd; 
}

.ulasan-text { 
    margin: 8px 0 0 0; 
    color: #333; 
}

.ulasan-img { 
    margin-top: 8px; 
    max-width: 120px; 
    border-radius: 8px; 
    border: 1px solid #eee; 
}

.serupa-list {
    display: flex; 
    gap: 18px; 
    flex-wrap: wrap; 
    justify-content: center; 
    align-items: center;
}

.serupa-item { 
    width: 170px; 
    background: #fff; 
    border-radius: 10px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.04); 
    padding: 12px; 
    text-align: center; 
}

.serupa-item img { 
    width: 100%; 
    height: 110px; 
    object-fit: cover; 
    border-radius: 8px; 
    border: 1px solid #eee; 
}

.serupa-item .name { 
    font-size: 1em; 
    color: #800000; 
    margin: 8px 0 4px 0; 
}

.serupa-item .price { 
    color: #d32f2f; 
    font-weight: 600; 
}

.serupa-item a { 
    text-decoration: none; 
    color: inherit; 
}

/* Responsive Cart Styles */
.cart {
    position: fixed;
    top: 0;
    right: -400px;
    bottom: 0;
    background: #fff;
    z-index: 1100;
    padding: 30px;
    border-left: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    width: 400px;
    transition: 0.3s ease;
}

.cart.active {
    right: 0;
}

/* RESPONSIVE BREAKPOINTS */

/* Large tablets and small desktops */
@media (max-width: 1200px) {
    .detail-wrap {
        max-width: 95%;
        margin: 20px auto;
        gap: 30px;
        padding: 24px 20px;
    }
    
    .detail-imgs {
        flex: 0 0 300px;
    }
    
    .main-img {
        height: 280px;
    }
}

/* Tablets */
@media (max-width: 768px) {
    .detail-wrap {
        flex-direction: column;
        gap: 24px;
        margin: 15px;
        padding: 20px 16px;
        border-radius: 12px;
    }
    
    .detail-imgs {
        flex: none;
        width: 100%;
    }
    
    .main-img {
        height: 250px;
    }
    
    .thumbs {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .thumbs img {
        width: 50px;
        height: 50px;
    }
    
    .prod-title {
        font-size: 1.6em;
        text-align: center;
    }
    
    .prod-price {
        font-size: 1.3em;
        text-align: center;
    }
    
    .prod-stock {
        text-align: center;
        margin-bottom: 16px;
    }
    
    .prod-rating {
        text-align: center;
        margin-bottom: 16px;
    }
    
    .prod-desc {
        font-size: 1em;
        text-align: center;
        margin-bottom: 20px;
    }
    
    .btns {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-beli, 
    .btn-keranjang {
        width: 100%;
        padding: 14px;
        font-size: 1em;
    }
    
    /* Reviews and similar products sections */
    .ulasan-section, 
    .serupa-section {
        margin: 24px 15px 0 15px;
        padding: 20px 16px;
        border-radius: 12px;
    }
    
    .ulasan-title, 
    .serupa-title {
        font-size: 1.3em;
        margin-bottom: 20px;
    }
    
    .ulasan-item {
        padding: 14px 16px;
    }
    
    .serupa-list {
        gap: 12px;
    }
    
    .serupa-item {
        width: 140px;
        padding: 10px;
    }
    
    .serupa-item img {
        height: 90px;
    }
    
    .serupa-item .name {
        font-size: 0.9em;
    }
    
    /* Cart responsive */
    .cart {
        width: 100%;
        right: -100%;
        padding: 20px;
    }
    
    .cart.active {
        right: 0;
    }
}

/* Mobile phones */
@media (max-width: 480px) {
    .detail-wrap {
        margin: 10px;
        padding: 16px 12px;
        gap: 20px;
    }
    
    .main-img {
        height: 220px;
    }
    
    .thumbs img {
        width: 45px;
        height: 45px;
    }
    
    .prod-title {
        font-size: 1.4em;
        line-height: 1.3;
    }
    
    .prod-price {
        font-size: 1.2em;
    }
    
    .prod-desc {
        font-size: 0.95em;
        line-height: 1.5;
    }
    
    .btn-beli, 
    .btn-keranjang {
        padding: 12px;
        font-size: 0.95em;
    }
    
    /* Reviews section mobile */
    .ulasan-section, 
    .serupa-section {
        margin: 20px 10px 0 10px;
        padding: 16px 12px;
    }
    
    .ulasan-title, 
    .serupa-title {
        font-size: 1.2em;
        margin-bottom: 16px;
    }
    
    .ulasan-item {
        padding: 12px 14px;
        margin-bottom: 14px;
    }
    
    .ulasan-user {
        font-size: 0.95em;
    }
    
    .ulasan-text {
        font-size: 0.9em;
        line-height: 1.4;
    }
    
    .ulasan-img {
        max-width: 100px;
    }
    
    /* Similar products mobile */
    .serupa-list {
        gap: 10px;
        justify-content: space-between;
    }
    
    .serupa-item {
        width: calc(50% - 5px);
        padding: 8px;
    }
    
    .serupa-item img {
        height: 80px;
    }
    
    .serupa-item .name {
        font-size: 0.85em;
        margin: 6px 0 4px 0;
        line-height: 1.2;
    }
    
    .serupa-item .price {
        font-size: 0.9em;
    }
    
    /* Cart mobile optimization */
    .cart {
        padding: 15px;
    }
    
    .cart .top_cart h3 {
        font-size: 16px;
    }
    
    .cart .top_cart h3 span {
        font-size: 12px;
    }
    
    .cart .items_in_cart {
        padding: 15px 0;
    }
    
    .cart_item {
        margin-bottom: 15px !important;
    }
    
    .cart_item img {
        width: 40px !important;
    }
    
    .cart_item p {
        font-size: 0.9em;
        margin-bottom: 4px;
    }
    
    .qty_control {
        margin-top: 4px !important;
    }
    
    .qty_control button {
        padding: 4px 8px;
        font-size: 0.9em;
    }
    
    .cart .button_Cart .btn_cart {
        padding: 12px 0;
        font-size: 14px;
    }
}

/* Extra small screens */
@media (max-width: 360px) {
    .detail-wrap {
        margin: 8px;
        padding: 12px 8px;
    }
    
    .main-img {
        height: 200px;
    }
    
    .prod-title {
        font-size: 1.3em;
    }
    
    .prod-price {
        font-size: 1.1em;
    }
    
    .ulasan-section, 
    .serupa-section {
        margin: 16px 8px 0 8px;
        padding: 12px 8px;
    }
    
    .serupa-item {
        width: calc(50% - 4px);
        padding: 6px;
    }
    
    .serupa-item .name {
        font-size: 0.8em;
    }
    
    .serupa-item .price {
        font-size: 0.85em;
    }
}
    </style>
    <script>
    // JS untuk galeri gambar
    document.addEventListener('DOMContentLoaded', function() {
        const thumbs = document.querySelectorAll('.thumbs img');
        const mainImg = document.querySelector('.main-img');
        thumbs.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImg.src = this.src;
                thumbs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
    </script>
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
<?php
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

<div class="detail-wrap">
    <div class="detail-imgs">
        <img class="main-img" src="<?= htmlspecialchars($fotos[0]) ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
        <div class="thumbs">
            <?php foreach ($fotos as $i => $foto): ?>
                <img src="<?= htmlspecialchars($foto) ?>" class="<?= $i === 0 ? 'active' : '' ?>" alt="thumb">
            <?php endforeach; ?>
        </div>
    </div>
    <div class="detail-info">
        <div class="prod-title"><?= htmlspecialchars($produk['nama_produk']) ?></div>
        <div class="prod-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></div>
        <div class="prod-stock" style="margin-bottom:12px; color:#333; font-size:1.08em;">Stok: <b><?= (int)$produk['stok'] ?></b></div>
        <div class="prod-rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa fa-star<?= $i <= $rata_rating ? '' : ' empty' ?>"></i>
            <?php endfor; ?>
            <span style="color:#888; font-size:0.98em; margin-left:8px;">(<?= $rata_rating ?> dari <?= $jumlah_ulasan ?> ulasan)</span>
        </div>
        <div class="prod-desc"><?= nl2br(htmlspecialchars($produk['deskripsi'])) ?></div>
        <div class="btns">
            <button type="button" class="btn-keranjang btnKeranjang" data-id="<?= $produk['id_produk'] ?>"><i class="fa fa-cart-plus"></i> Keranjang</button>
            <form action="checkout.php" method="get" style="display:inline;">
                <input type="hidden" name="beli" value="<?= $produk['id_produk'] ?>">
                <button type="submit" class="btn-beli">Beli Sekarang</button>
            </form>
        </div>
    </div>
</div>
<div class="ulasan-section">
    <div class="ulasan-title"><i class="fa fa-comments"></i> Ulasan Pembeli</div>
    <div class="ulasan-list">
        <?php if ($jumlah_ulasan === 0): ?>
            <div style="color:#888;">Belum ada ulasan untuk produk ini.</div>
        <?php else: ?>
            <?php foreach ($ulasans as $ulasan): ?>
                <div class="ulasan-item">
                    <div class="ulasan-user"><i class="fa fa-user"></i> <?= htmlspecialchars($ulasan['username']) ?> <span style="color:#aaa; font-size:0.95em; margin-left:8px;">(<?= date('d/m/Y', strtotime($ulasan['created_at'])) ?>)</span></div>
                    <div class="ulasan-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa fa-star<?= $i <= $ulasan['rating'] ? '' : ' empty' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="ulasan-text"><?= nl2br(htmlspecialchars($ulasan['review_text'])) ?></div>
                    <?php if (!empty($ulasan['review_image'])): ?>
                        <img class="ulasan-img" src="<?= htmlspecialchars($ulasan['review_image']) ?>" alt="foto ulasan">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div class="serupa-section">
    <div class="serupa-title"><i class="fa fa-th-large"></i> Produk Serupa</div>
    <div class="serupa-list">
        <?php if (empty($produk_serupa)): ?>
            <div style="color:#888;">Tidak ada produk serupa.</div>
        <?php else: ?>
            <?php foreach ($produk_serupa as $serupa): ?>
                <div class="serupa-item">
                    <a href="detail_produk.php?id=<?= $serupa['id_produk'] ?>">
                        <img src="<?= htmlspecialchars($serupa['path_foto'] ?? 'uploads/default.png') ?>" alt="<?= htmlspecialchars($serupa['nama_produk']) ?>">
                        <div class="name"><?= htmlspecialchars($serupa['nama_produk']) ?></div>
                        <div class="price">Rp <?= number_format($serupa['harga'], 0, ',', '.') ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://kit.fontawesome.com/fd85fb070c.js" crossorigin="anonymous"></script>
    <script src="resource/js/main.js"></script>
</body>
</html> 