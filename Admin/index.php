<?php
include '../Config/koneksi.php';

// Total Produk
$qProduk = $conn->query("SELECT COUNT(*) as total FROM produk");
$totalProduk = $qProduk->fetch_assoc()['total'];

// Total Pesanan
$qPesanan = $conn->query("SELECT COUNT(*) as total FROM pesanan");
$totalPesanan = $qPesanan->fetch_assoc()['total'];

// Total Earning (Jumlah semua harga produk yang dipesan)
$qEarning = $conn->query("
    SELECT SUM(p.harga) as total
    FROM pesanan ps
    JOIN produk p ON ps.id_produk = p.id_produk
");
$totalEarning = $qEarning->fetch_assoc()['total'] ?? 0;

// Recent Produk (limit 5)
$qProdukBaru = $conn->query("
    SELECT nama_produk, harga
    FROM produk
    ORDER BY id_produk DESC
    LIMIT 5
");

// Recent Customer / Pemesan
$qCustomerBaru = $conn->query("
    SELECT u.username, ps.alamat
    FROM pesanan ps
    JOIN users u ON u.id = ps.id_user
    ORDER BY ps.id DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://kit.fontawesome.com/fd85fb070c.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../Admin/assets/css/style.css">
</head>

<body>
    <div class="container">
        <div class="navigation">
            <ul>
                <li>
                    <a href="#">
                        <span class="icon">
                            <ion-icon name="storefront-outline"></ion-icon>
                        </span>
                        <span class="title">TechShop</span>
                    </a>
                </li>

                <li>
                    <a href="#" id="dashboardNav">
                        <span class="icon">
                            <ion-icon name="home-outline"></ion-icon>
                        </span>
                        <span class="title">Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="#" id="navCustomers">
                        <span class="icon">
                            <ion-icon name="people-outline"></ion-icon>
                        </span>
                        <span class="title">Customers</span>
                    </a>
                </li>

                <li>
                    <a href="#" id="messagesNav">
                        <span class="icon">
                            <ion-icon name="chatbubble-outline"></ion-icon>
                        </span>
                        <span class="title">Messages</span>
                    </a>
                </li>

                <li>
                    <a href="#" id="productsNav">
                        <span class="icon">
                            <ion-icon name="cloud-upload-outline"></ion-icon>
                        </span>
                        <span class="title">Products</span>
                    </a>
                </li>

                <li>
                    <a href="../login/index.php">
                        <span class="icon">
                            <ion-icon name="log-out-outline"></ion-icon>
                        </span>
                        <span class="title">Sign Out</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ========================= Main ==================== -->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <ion-icon name="menu-outline"></ion-icon>
                </div>

                <div class="search">
                    <label>
                        <input type="text" placeholder="Search here">
                        <ion-icon name="search-outline"></ion-icon>
                    </label>
                </div>

                <div class="user">
                    <img src="assets/imgs/customer01.jpg" alt="">
                </div>
            </div>

            <div id="contentDynamic"></div>

            <!-- ======================= Cards ================== -->
            <div class="cardBox">
            <!-- Total Produk -->
            <div class="card">
                <div>
                    <div class="numbers"><?= $totalProduk ?></div>
                    <div class="cardName">Produk</div>
                </div>
                <div class="iconBx">
                    <ion-icon name="cube-outline"></ion-icon>
                </div>
            </div>

            <!-- Total Pesanan -->
            <div class="card">
                <div>
                    <div class="numbers"><?= $totalPesanan ?></div>
                    <div class="cardName">Pesanan</div>
                </div>
                <div class="iconBx">
                    <ion-icon name="cart-outline"></ion-icon>
                </div>
            </div>

            <!-- Komentar (Dummy) -->
            <div class="card">
                <div>
                    <div class="numbers">0</div>
                    <div class="cardName">Komentar</div>
                </div>
                <div class="iconBx">
                    <ion-icon name="chatbubbles-outline"></ion-icon>
                </div>
            </div>

            <!-- Pendapatan -->
            <div class="card">
                <div>
                    <div class="numbers">Rp <?= number_format($totalEarning, 0, ',', '.') ?></div>
                    <div class="cardName">Pendapatan</div>
                </div>
                <div class="iconBx">
                    <ion-icon name="cash-outline"></ion-icon>
                </div>
            </div>
        </div>

            <!-- ================ Order Details List ================= -->
            <div class="details">
                <div class="recentOrders">
                    <div class="cardHeader">
                        <h2>Status Produk</h2>
                        <a href="#" class="btn">View All</a>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <td>Name</td>
                                <td>Price</td>
                                <td>Payment</td>
                                <td>Status</td>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = $qProdukBaru->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                                <td>-</td>
                                <td><span class="status delivered">Tersedia</span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ================= New Customers ================ -->
                <div class="recentCustomers">
                    <div class="cardHeader">
                        <h2>Status Pesanan</h2>
                    </div>

                    <table>
                        <?php while ($row = $qCustomerBaru->fetch_assoc()): ?>
                        <tr>
                            <td width="60px">
                                <div class="imgBx"><img src="assets/imgs/customer01.jpg" alt=""></div>
                            </td>
                            <td>
                                <h4><?= htmlspecialchars($row['username']) ?><br><span><?= htmlspecialchars($row['alamat']) ?></span></h4>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>

            <div class="productCrud" id="productCrud" style="display: none; padding: 20px;">
            <div class="crudSelector">
                <button id="btnKategori" class="active">Tambah Kategori</button>
                <button id="btnProduk">Tambah Produk</button>
            </div>

            <!-- Form Tambah Kategori -->
            <form id="formKategori" style="display: block;" method="post" action="kategori-simpan.php">
                <input type="text" name="nama_kategori" placeholder="Nama Kategori" required><br><br>
                <button type="submit">Simpan Kategori</button>
            </form>

                <!-- Form Tambah Produk -->
                <form id="formProduk" enctype="multipart/form-data" method="post" action="produk-simpan.php" style="display: none;">
                    <input type="text" name="nama_produk" placeholder="Nama Produk" required><br><br>

                    <select name="id_kategori" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php
                            include '../Config/koneksi.php';
                            $query = $conn->query("SELECT * FROM kategori");
                            while ($row = $query->fetch_assoc()) {
                                echo '<option value="'.$row['id_kategori'].'">'.$row['nama_kategori'].'</option>';
                            }
                        ?>
                    </select><br><br>


                    <input type="file" name="foto_produk[]" multiple accept="image/*" required><br><br>

                    <textarea name="deskripsi" placeholder="Deskripsi Produk" required></textarea><br><br>

                    <input type="number" name="harga" placeholder="Harga" required><br><br>
                    <input type="number" name="stok" placeholder="Stok" min="0" required><br><br>

                    <button type="submit">Simpan Produk</button>
                </form>

                <!-- Daftar Produk (nanti digenerate PHP atau AJAX) -->
                <div id="daftarProduk" style="margin-top: 30px;">
                    <h3>Daftar Produk</h3>
                    <table border="1" cellpadding="10" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th> 
                            <th>Harga</th>
                            <th>Foto</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    include '../Config/koneksi.php';

                    // Ambil semua produk + nama kategori
                    $sql = "SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, k.nama_kategori,
                            (SELECT path_foto FROM foto_produk WHERE id_produk = p.id_produk LIMIT 1) AS foto
                            FROM produk p
                            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                            ORDER BY p.id_produk DESC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                            <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                            <td>
                                <?php if ($row['foto']): ?>
                                    <img src="../uploads/<?= htmlspecialchars(basename($row['foto'])) ?>" width="60">
                                <?php else: ?>
                                    <em>Tidak ada foto</em>
                                <?php endif; ?>
                            </td>
                            <td>
                            <button type="button" class="btnEdit" data-id="<?= $row['id_produk'] ?>">Edit</button>
                            <form method="post" action="produk-hapus.php" class="formHapusProduk" style="display:inline;">
                                <input type="hidden" name="id_produk" value="<?= $row['id_produk'] ?>">
                                <button type="submit" style="background:red; color:white; border:none; padding:5px 10px;">Hapus</button>
                            </form>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                        echo '<tr><td colspan="6"><em>Belum ada produk.</em></td></tr>';
                    endif;
                    ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========== Scripts =========  -->
    <script src="../Admin/assets/js/main.js"></script>

    <!-- ====== ionicons ======= -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('.formHapusProduk').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // hentikan submit dulu

            Swal.fire({
                title: 'Yakin ingin menghapus?',
                text: "Data tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // lanjut submit form
                }
            });
        });
    });
</script>
<?php include '../ai-chat.php'; ?>
</body>

</html>