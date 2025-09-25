<?php
session_start();
include 'Config/koneksi.php';

function getKategori($conn) {
    $result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori");
    return $result->fetch_all(MYSQLI_ASSOC);
}

$kategoriList = getKategori($conn);

// Ambil parameter search dari URL
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products</title>
    <link rel="stylesheet" href="resource/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <header>
        <div class="container top-nav">
            <a href="index.php" class="logo"><img src="resource/img/logo-black.png" alt=""></a>
            <!-- Update form search dengan value dari parameter -->
            <form action="all_products.php" method="GET" class="search">
                <input type="search" name="search" placeholder="Search for products..." value="<?= htmlspecialchars($searchQuery) ?>">
                <button type="submit">Search</button>
            </form>
            <div class="cart_header">
                <div onclick="open_cart()" class="icon_cart">
                    <i class="fa-solid fa-bag-shopping"></i>
                    <span class="count_item">0</span>
                </div>
                <div class="tottal_price">
                    <p>My Cart:</p>
                    <p class="price_cart_Head">Rp 0</p>
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
                    <li class="active"><a href="all_products.php">all products</a></li>
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

    <div class="cart">
        <div class="top_cart">
            <h3>My cart <span class="count_item_cart">(0 Item in Cart)</span></h3>
            <span onclick="close_cart()" class="close_cart"><i class="fa-regular fa-circle-xmark"></i></span>
        </div>
        <div class="items_in_cart"></div>
        <div class="bottom_Cart">
            <div class="total">
                <p>Cart subtotal</p>
                <p class="price_cart_total">Rp 0</p>
            </div>
            <div class="button_Cart">
                <a href="checkout.php" class="btn_cart">Proceed to checkout</a>
                <button onclick="close_cart()" class="btn_cart trans_bg">Shop more</button>
            </div>
        </div>
    </div>

    <section class="all_products">
        <div class="container">
            <span class="btn_filter" onclick="open_close_filter()">filter <i class="fa-solid fa-filter"></i></span>
            
            <div class="filter">
                <h2>Filter</h2>
                <div class="filter_item">
                    <h4>Kategori</h4>
                    <div class="content">
                        <?php foreach ($kategoriList as $kategori): ?>
                            <div class="item">
                                <label>
                                    <input type="radio" name="kategori" value="<?= $kategori['id_kategori'] ?>" class="filter-kategori">
                                    <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <div class="item">
                            <label>
                                <input type="radio" name="kategori" value="" class="filter-kategori" checked>
                                Semua Kategori
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="products-section">
                <!-- Tampilkan info pencarian jika ada -->
                <?php if (!empty($searchQuery)): ?>
                <div class="search_info" style="background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; border-left: 4px solid var(--main-color);">
                    <p><strong>Hasil pencarian untuk:</strong> "<?= htmlspecialchars($searchQuery) ?>"</p>
                    <a href="all_products.php" style="color: var(--main-color); text-decoration: none;">← Tampilkan semua produk</a>
                </div>
                <?php endif; ?>
                
                <div class="products-grid" id="produkContainer">
                    <!-- produk di sini -->
                </div>
                <div id="produkLoader" style="display:none;text-align:center;padding:16px;">
                    <img src='resource/img/loading.svg' alt='Loading...' style='height:32px;'>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="bottom_footer">
            <div class="container">
                <p>Copyright &copy; <span>TechShop</span> all rights reserved</p>
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
                    window.location.href = 'login/logout.php';
                }
            });
        }
        
        // Pass search query to JavaScript
        const searchQuery = "<?= addslashes($searchQuery) ?>";
    </script>
    <script src="resource/js/main.js"></script>
    <script src="resource/js/all_products.js"></script>
    <?php include 'ai-chat.php'; ?>
</body>
</html>