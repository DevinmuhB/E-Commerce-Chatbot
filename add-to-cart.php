<?php
session_start();
include 'Config/koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo 'login_required';
    exit;
}

$id_user = $_SESSION['user_id'];
$id_produk = isset($_POST['id_produk']) ? (int)$_POST['id_produk'] : 0;

if ($id_produk <= 0) {
    echo 'invalid_product';
    exit;
}

try {
    // Check if product exists
    $check_product = $conn->prepare("SELECT id_produk FROM produk WHERE id_produk = ?");
    $check_product->bind_param("i", $id_produk);
    $check_product->execute();
    
    if ($check_product->get_result()->num_rows === 0) {
        echo 'product_not_found';
        exit;
    }
    $check_product->close();
    
    // Check if item already in cart
    $check_cart = $conn->prepare("SELECT id_keranjang, jumlah FROM keranjang WHERE id_user = ? AND id_produk = ?");
    $check_cart->bind_param("ii", $id_user, $id_produk);
    $check_cart->execute();
    $cart_result = $check_cart->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update existing item
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['jumlah'] + 1;
        
        $update_stmt = $conn->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['id_keranjang']);
        
        if ($update_stmt->execute()) {
            echo 'success';
        } else {
            echo 'update_failed';
        }
        $update_stmt->close();
        
    } else {
        // Add new item
        $insert_stmt = $conn->prepare("INSERT INTO keranjang (id_user, id_produk, jumlah) VALUES (?, ?, 1)");
        $insert_stmt->bind_param("ii", $id_user, $id_produk);
        
        if ($insert_stmt->execute()) {
            echo 'success';
        } else {
            echo 'insert_failed';
        }
        $insert_stmt->close();
    }
    
    $check_cart->close();
    
} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    echo 'database_error';
}
?>