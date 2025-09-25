<?php
session_start();
include 'Config/koneksi.php';
if (!isset($_SESSION['user_id'])) { header('Location: login/index.php'); exit; }
$user_id = $_SESSION['user_id'];
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Ambil detail pesanan
$stmt = $conn->prepare("
    SELECT p.*, pr.nama_produk, pr.harga, pr.deskripsi,
    (SELECT path_foto FROM foto_produk WHERE id_produk = pr.id_produk ORDER BY id_foto ASC LIMIT 1) as foto
    FROM pesanan p
    JOIN produk pr ON p.id_produk = pr.id_produk
    WHERE p.id = ? AND p.id_user = ?
");
$stmt->bind_param("ii", $id_pesanan, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
if (!$order) {
    echo '<div style="margin:40px;text-align:center;color:red;">Pesanan tidak ditemukan atau Anda tidak berhak mengaksesnya.</div>';
    exit;
}

// Cek apakah user bisa review (status 'Sampai' dan belum pernah review produk ini untuk pesanan ini)
$show_review_form = false;
if ($order['status'] === 'Sampai') {
    $cek = $conn->prepare("SELECT id_ulasan FROM ulasan WHERE id_user = ? AND id_produk = ? AND id_pesanan = ?");
    $cek->bind_param("iii", $user_id, $order['id_produk'], $order['id']);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows === 0) {
        $show_review_form = true;
    }
}
// Proses submit ulasan
$review_success = false;
$review_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $show_review_form) {
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    $review_image = null;
    if ($rating < 1 || $rating > 5) {
        $review_error = 'Rating harus diisi (1-5 bintang).';
    } else {
        // Upload gambar jika ada
        if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/ulasan/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['review_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png'];
            if (in_array($ext, $allowed)) {
                $filename = 'ulasan_' . $user_id . '_' . $order['id_produk'] . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['review_image']['tmp_name'], $filepath)) {
                    $review_image = $filepath;
                }
            }
        }
        // Simpan ke DB
        $stmt = $conn->prepare("INSERT INTO ulasan (id_user, id_produk, id_pesanan, rating, review_text, review_image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiss", $user_id, $order['id_produk'], $order['id'], $rating, $review_text, $review_image);
        if ($stmt->execute()) {
            $review_success = true;
            $show_review_form = false;
        } else {
            $review_error = 'Gagal menyimpan ulasan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - TechShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #f5f6f9; font-family: 'Poppins', Arial, sans-serif; margin:0; }
        .detail-container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 32px 28px;
        }
        .detail-header { display: flex; align-items: center; gap: 18px; margin-bottom: 18px; }
        .detail-header img { width: 90px; height: 90px; object-fit: cover; border-radius: 12px; border:1px solid #eee; }
        .detail-header .prod-info { flex:1; }
        .detail-header .prod-info h2 { margin:0 0 8px 0; font-size:1.2em; color:#800000; }
        .detail-header .prod-info p { margin:0; color:#666; font-size:0.98em; }
        .detail-row { margin-bottom: 12px; }
        .detail-label { color:#888; font-size:0.97em; min-width:120px; display:inline-block; }
        .detail-value { color:#222; font-weight:500; }
        .status-badge { display:inline-block; padding:3px 12px; border-radius:12px; font-size:0.95em; background:#f7f7fa; color:#800000; border:1px solid #eee; }
        .pay-info { background:#f7f7fa; border-radius:8px; padding:12px 16px; margin-top:18px; }
        .pay-info h4 { margin:0 0 8px 0; color:#800000; font-size:1.05em; }
        .pay-info p { margin:0 0 6px 0; color:#333; font-size:0.97em; }
        .btn-back { display:inline-block; margin-top:24px; background:#800000; color:#fff; padding:10px 24px; border-radius:6px; text-decoration:none; font-weight:600; }
        .btn-back:hover { background:#a83232; }
    </style>
</head>
<body>
<div class="detail-container">
    <div class="detail-header">
        <img src="uploads/ <?= htmlspecialchars($order['foto'] ?? 'default.png') ?>" alt="<?= htmlspecialchars($order['nama_produk']) ?>">
        <div class="prod-info">
            <h2><?= htmlspecialchars($order['nama_produk']) ?></h2>
            <p><?= htmlspecialchars($order['deskripsi']) ?></p>
        </div>
    </div>
    <div class="detail-row"><span class="detail-label">Harga:</span> <span class="detail-value">Rp <?= number_format($order['harga'], 0, ',', '.') ?></span></div>
    <div class="detail-row"><span class="detail-label">Status:</span> <span class="status-badge"><?= htmlspecialchars($order['status']) ?></span></div>
    <div class="detail-row"><span class="detail-label">Tanggal Pesanan:</span> <span class="detail-value"><?= date('d/m/Y H:i', strtotime($order['tanggal_pesanan'])) ?></span></div>
    <div class="detail-row"><span class="detail-label">Alamat Pengiriman:</span> <span class="detail-value"><?= htmlspecialchars($order['alamat']) ?></span></div>
    <?php if ($order['metode_pembayaran'] || $order['bukti_pembayaran']): ?>
    <div class="pay-info">
        <h4>Info Pembayaran</h4>
        <p><b>Metode:</b> <?= htmlspecialchars($order['metode_pembayaran']) ?></p>
        <?php if ($order['bukti_pembayaran']): ?>
            <p><b>Bukti:</b> <a href="<?= htmlspecialchars($order['bukti_pembayaran']) ?>" target="_blank" style="color:#800000;">Lihat Bukti</a></p>
        <?php endif; ?>
        <?php if ($order['catatan_pembayaran']): ?>
            <p><b>Catatan:</b> <?= htmlspecialchars($order['catatan_pembayaran']) ?></p>
        <?php endif; ?>
        <?php if ($order['tanggal_pembayaran']): ?>
            <p><b>Tgl Bayar:</b> <?= date('d/m/Y H:i', strtotime($order['tanggal_pembayaran'])) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($show_review_form): ?>
    <hr style="margin:32px 0 24px 0;">
    <h3 style="color:#800000; margin-bottom:12px;">Tulis Ulasan Produk</h3>
    <?php if ($review_error): ?><div style="color:red; margin-bottom:10px;"><?= htmlspecialchars($review_error) ?></div><?php endif; ?>
    <?php if ($review_success): ?><div style="color:green; margin-bottom:10px;">Ulasan berhasil dikirim!</div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div style="margin-bottom:12px;">
            <label for="rating">Rating:</label>
            <span id="star-rating">
                <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" style="display:none;">
                    <label for="star<?= $i ?>" style="font-size:1.5em; color:#FFD700; cursor:pointer;">&#9733;</label>
                <?php endfor; ?>
            </span>
        </div>
        <div style="margin-bottom:12px;">
            <label for="review_text">Ulasan:</label><br>
            <textarea name="review_text" id="review_text" rows="3" style="width:100%;"></textarea>
        </div>
        <div style="margin-bottom:12px;">
            <label for="review_image">Foto (opsional):</label><br>
            <input type="file" name="review_image" accept="image/*">
        </div>
        <button type="submit" name="submit_review" style="background:#800000; color:#fff; padding:10px 28px; border:none; border-radius:6px; font-weight:600;">Kirim Ulasan</button>
    </form>
    <script>
    // Highlight bintang rating
    document.addEventListener('DOMContentLoaded', function() {
        const stars = document.querySelectorAll('#star-rating label');
        const radios = document.querySelectorAll('#star-rating input[type=radio]');
        stars.forEach((star, idx) => {
            star.addEventListener('mouseenter', function() {
                for (let i=0; i<=idx; i++) stars[i].style.color = '#FFD700';
                for (let i=idx+1; i<stars.length; i++) stars[i].style.color = '#ddd';
            });
            star.addEventListener('mouseleave', function() {
                let checked = -1;
                radios.forEach((r, i) => { if (r.checked) checked = i; });
                for (let i=0; i<stars.length; i++) stars[i].style.color = (i<=checked) ? '#FFD700' : '#ddd';
            });
            star.addEventListener('click', function() {
                radios[idx].checked = true;
                for (let i=0; i<stars.length; i++) stars[i].style.color = (i<=idx) ? '#FFD700' : '#ddd';
            });
        });
    });
    </script>
<?php elseif ($review_success): ?>
    <div style="color:green; margin:24px 0 0 0;">Ulasan berhasil dikirim!</div>
<?php endif; ?>
    <a href="profile.php" class="btn-back"><i class="fa fa-arrow-left"></i> Kembali ke Profil</a>
</div>
</body>
</html> 