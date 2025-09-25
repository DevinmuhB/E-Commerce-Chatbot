<?php
session_start();
include 'Config/koneksi.php';

header('Content-Type: application/json');

$id_user = $_SESSION['user_id'] ?? 0;
$response = ['count' => 0, 'total' => '0'];

if ($id_user > 0) {
    try {
        // Get cart count and total
        $stmt = $conn->prepare("
            SELECT 
                SUM(k.jumlah) as total_items,
                SUM(k.jumlah * p.harga) as total_price
            FROM keranjang k
            JOIN produk p ON k.id_produk = p.id_produk
            WHERE k.id_user = ?
        ");
        
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $response['count'] = (int)($row['total_items'] ?? 0);
            $response['total'] = number_format($row['total_price'] ?? 0, 0, ',', '.');
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Cart count error: " . $e->getMessage());
    }
}

echo json_encode($response);
?>