<?php
include '../Config/koneksi.php';

// Tangani aksi admin untuk konfirmasi pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id_pesanan']);
    if ($id > 0 && isset($_POST['konfirmasi'])) {
        // Ambil id_produk dari pesanan
        $sql = "SELECT id_produk FROM pesanan WHERE id = $id";
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            $id_produk = $row['id_produk'];
            // Kurangi stok produk sebanyak 1 (karena tidak ada kolom qty di pesanan)
            $conn->query("UPDATE produk SET stok = stok - 1 WHERE id_produk = $id_produk");
        }
        $query = "UPDATE pesanan SET admin_konfirmasi = 1, status = 'Menunggu Kurir' WHERE id = $id";
        $conn->query($query);
    }
    header("Location: index.php");
    exit;
}

// Ambil data pesanan
$sql = "
    SELECT 
        ps.id AS id_pesanan,
        ps.tanggal_pesanan,
        p.nama_produk,
        p.harga,
        fp.path_foto,
        u.username,
        ps.alamat,
        ps.status,
        ps.tanggal_pembayaran,
        ps.admin_konfirmasi,
        ps.metode_pembayaran,
        ps.bukti_pembayaran,
        ps.catatan_pembayaran
    FROM pesanan ps
    JOIN produk p ON ps.id_produk = p.id_produk
    JOIN users u ON u.id = ps.id_user
    LEFT JOIN (
        SELECT id_produk, MIN(path_foto) AS path_foto
        FROM foto_produk
        GROUP BY id_produk
    ) fp ON fp.id_produk = p.id_produk
    ORDER BY ps.tanggal_pesanan DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}
?>

<h3 style="padding-left: 40px;">Data Pesanan Customer</h3>

<div style="max-width: 100%; overflow-x: auto; padding: 20px 40px;">
<table cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
    <thead style="background-color: #f8f8f8;">
        <tr>
            <th>No</th>
            <th>Foto</th>
            <th>Nama Produk</th>
            <th>Harga</th>
            <th>Customer</th>
            <th>Alamat</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Pembayaran</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): 
            $no = 1;
            while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td style="text-align: center;">
                    <?php if (!empty($row['path_foto'])): ?>
                        <img src="../uploads/<?= htmlspecialchars(basename($row['path_foto'])) ?>" width="60" style="border-radius: 6px;">
                    <?php else: ?>
                        <em>Tidak ada foto</em>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['alamat']) ?></td>
                <td><?= date('d-m-Y H:i', strtotime($row['tanggal_pesanan'])) ?></td>
                <td><strong><?= htmlspecialchars($row['status']) ?></strong></td>
                <td>
                    <?php if ($row['status'] === 'Diproses Admin'): ?>
                        <div style="font-size: 12px;">
                            <strong>Metode:</strong> <?= htmlspecialchars($row['metode_pembayaran'] ?? 'Transfer Bank') ?><br>
                            <?php if ($row['bukti_pembayaran']): ?>
                                <a href="../uploads/bukti_pembayaran/<?= htmlspecialchars(basename($row['bukti_pembayaran'])) ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                    <i class="fa-solid fa-image"></i> Lihat Bukti
                                </a><br>
                            <?php endif; ?>
                            <?php if ($row['catatan_pembayaran']): ?>
                                <small>Catatan: <?= htmlspecialchars(substr($row['catatan_pembayaran'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($row['status'] === 'Menunggu Pembayaran'): ?>
                        <em style="color: #ffc107;">Belum dibayar</em>
                    <?php else: ?>
                        <em style="color: #28a745;">Sudah dibayar</em>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="customer.php" style="display:inline;">
                        <input type="hidden" name="id_pesanan" value="<?= $row['id_pesanan'] ?>">
                        <?php if ($row['status'] === 'Diproses Admin'): ?>
                            <button type="submit" name="konfirmasi" style="padding:5px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Konfirmasi Pembayaran</button>
                        <?php else: ?>
                            <em>-</em>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="10" style="text-align:center;"><em>Tidak ada pesanan</em></td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<style>
table {
    font-family: Arial, sans-serif;
    font-size: 14px;
    border: 1px solid #ddd;
}
th, td {
    border: 1px solid #ddd;
    padding: 10px 12px;
    text-align: left;
}
th {
    background-color: #f2f2f2;
}
button {
    background-color: #333;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover {
    background-color: #555;
}
</style>
<?php include '../ai-chat.php'; ?>
</body>