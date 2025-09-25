<?php
include 'Config/koneksi.php';

$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$id_kategori = isset($_GET['kategori']) && $_GET['kategori'] !== '' ? (int)$_GET['kategori'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Function to build smart search conditions
function buildSearchConditions($search, &$params, &$types) {
    if (empty($search)) {
        return '';
    }
    
    // Split search terms by space and filter empty values
    $searchTerms = array_filter(explode(' ', $search));
    
    if (empty($searchTerms)) {
        return '';
    }
    
    $searchConditions = [];
    
    // For each search term, create conditions for both nama_produk and deskripsi
    foreach ($searchTerms as $term) {
        $likeParam = '%' . $term . '%';
        $searchConditions[] = "(p.nama_produk LIKE ? OR p.deskripsi LIKE ?)";
        $params[] = $likeParam;
        $params[] = $likeParam;
        $types .= "ss";
    }
    
    // Join all search conditions with AND (all terms must be found)
    return '(' . implode(' AND ', $searchConditions) . ')';
}

// Build base query with JOIN to kategori for better search
$sql = "SELECT p.*, k.nama_kategori, GROUP_CONCAT(f.path_foto ORDER BY f.id_foto ASC) AS all_foto
        FROM produk p
        LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
        LEFT JOIN foto_produk f ON p.id_produk = f.id_produk";

$conditions = [];
$params = [];
$types = "";

// Add category filter
if ($id_kategori !== null) {
    $conditions[] = "p.id_kategori = ?";
    $params[] = $id_kategori;
    $types .= "i";
}

// Add smart search filter
if (!empty($search)) {
    $searchCondition = buildSearchConditions($search, $params, $types);
    if (!empty($searchCondition)) {
        $conditions[] = $searchCondition;
    }
}

// Add WHERE clause if there are conditions
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add relevance scoring for search results
$orderBy = "ORDER BY ";
if (!empty($search)) {
    // Create relevance score based on:
    // 1. Exact match in product name (highest priority)
    // 2. Partial match in product name
    // 3. Match in description
    $searchLower = mysqli_real_escape_string($conn, strtolower($search));
    $orderBy .= "
        CASE 
            WHEN LOWER(p.nama_produk) = '$searchLower' THEN 1
            WHEN LOWER(p.nama_produk) LIKE '%$searchLower%' THEN 2
            WHEN LOWER(p.deskripsi) LIKE '%$searchLower%' THEN 3
            ELSE 4
        END ASC,
        p.created_at DESC";
} else {
    $orderBy .= "p.created_at DESC";
}

$sql .= " GROUP BY p.id_produk
          $orderBy
          LIMIT $limit OFFSET $offset";

// Execute query with prepared statement if there are parameters
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        echo "Query preparation error: " . $conn->error;
        exit;
    }
} else {
    $result = $conn->query($sql);
}

if (!$result) {
    echo "Query error: " . $conn->error;
    exit;
}

// Get rating data for all products
$ratingMap = [];
$res = $conn->query("SELECT id_produk, AVG(rating) as avg_rating FROM ulasan GROUP BY id_produk");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ratingMap[$row['id_produk']] = round($row['avg_rating'], 1);
    }
}

// Function to get search suggestions
function getSuggestions($conn, $search) {
    $suggestions = [];
    
    // Get popular product names that might be similar
    $sql = "SELECT DISTINCT nama_produk FROM produk 
            WHERE nama_produk LIKE ? 
            ORDER BY nama_produk 
            LIMIT 5";
    
    $likeParam = '%' . $search . '%';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $likeParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Extract key words from product names
            $words = explode(' ', $row['nama_produk']);
            foreach ($words as $word) {
                if (strlen($word) > 3 && !in_array($word, $suggestions)) {
                    $suggestions[] = $word;
                    if (count($suggestions) >= 3) break 2;
                }
            }
        }
        $stmt->close();
    }
    
    return $suggestions;
}

// Check if no results found
if ($result->num_rows === 0 && $page === 1) {
    if (!empty($search)) {
        echo '<div style="text-align:center;padding:40px;background:#fff;border-radius:8px;">';
        echo '<i class="fa fa-search" style="font-size:48px;color:#ccc;margin-bottom:20px;"></i>';
        echo '<h3 style="color:#666;margin-bottom:10px;">Tidak ada produk ditemukan untuk "' . htmlspecialchars($search) . '"</h3>';
        echo '<p style="color:#999;">Coba gunakan kata kunci yang berbeda atau lebih spesifik</p>';
        
        // Suggest alternative searches
        $suggestions = getSuggestions($conn, $search);
        if (!empty($suggestions)) {
            echo '<div style="margin-top:20px;">';
            echo '<p style="color:#666;margin-bottom:10px;">Mungkin yang Anda cari:</p>';
            foreach ($suggestions as $suggestion) {
                echo '<a href="all_products.php?search=' . urlencode($suggestion) . '" style="display:inline-block;margin:5px;padding:8px 15px;background:#f0f0f0;color:#666;text-decoration:none;border-radius:20px;font-size:14px;">' . htmlspecialchars($suggestion) . '</a>';
            }
            echo '</div>';
        }
        
        echo '<a href="all_products.php" style="color:var(--main-color);text-decoration:none;font-weight:bold;margin-top:20px;display:inline-block;">← Lihat Semua Produk</a>';
        echo '</div>';
    } else {
        echo '<div style="text-align:center;padding:40px;background:#fff;border-radius:8px;">';
        echo '<p style="color:#666;">Tidak ada produk ditemukan</p>';
        echo '</div>';
    }
    exit;
}

// Display search results
while ($produk = $result->fetch_assoc()):
    $gambarArray = explode(',', $produk['all_foto'] ?? '');
    $foto1 = $gambarArray[0] ?? 'uploads/default.png';
    $foto2 = $gambarArray[1] ?? $foto1;

    // Fix image paths to use uploads folder in root directory
    if (!file_exists($foto1)) $foto1 = 'uploads/default.png';
    if (!file_exists($foto2)) $foto2 = $foto1;
    
    // Highlight search terms in product name
    $highlightedName = $produk['nama_produk'];
    if (!empty($search)) {
        $searchTerms = explode(' ', $search);
        foreach ($searchTerms as $term) {
            if (!empty($term)) {
                $highlightedName = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark style="background:#ffff00;padding:0;">$1</mark>', $highlightedName);
            }
        }
    }
?>
<div class="product-card">
    <div class="img_product">
        <img class="img_main" src="<?= htmlspecialchars($foto1) ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
        <img class="img_hover" src="<?= htmlspecialchars($foto2) ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>">
    </div>
    
    <div class="name_product">
        <a href="detail_produk.php?id=<?= $produk['id_produk'] ?>"><?= $highlightedName ?></a>
    </div>
    
    <!-- Show category if search is active -->
    <?php if (!empty($search) && !empty($produk['nama_kategori'])): ?>
    <div class="product_category" style="font-size:12px;color:#666;margin-bottom:5px;">
        Kategori: <?= htmlspecialchars($produk['nama_kategori']) ?>
    </div>
    <?php endif; ?>
    
    <div class="stars">
        <?php
        $avg = $ratingMap[$produk['id_produk']] ?? 0;
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
    
    <!-- Button container with proper ordering -->
    <div class="button-container">
        <button type="button" class="btnKeranjang btn-action" data-id="<?= $produk['id_produk'] ?>">
            <i class="fa fa-shopping-cart"></i> Tambah ke Keranjang
        </button>
        <form method="post" action="checkout.php" style="margin: 0;">
            <input type="hidden" name="id_produk" value="<?= $produk['id_produk'] ?>">
            <button type="submit" class="btn-action btn-beli">
                <i class="fa fa-bolt"></i> Beli Sekarang
            </button>
        </form>
    </div>
</div>
<?php endwhile; ?>

<?php
// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT p.id_produk) as total 
             FROM produk p
             LEFT JOIN kategori k ON p.id_kategori = k.id_kategori";

$countConditions = [];
$countParams = [];
$countTypes = "";

// Add same filters as main query
if ($id_kategori !== null) {
    $countConditions[] = "p.id_kategori = ?";
    $countParams[] = $id_kategori;
    $countTypes .= "i";
}

if (!empty($search)) {
    $searchCondition = buildSearchConditions($search, $countParams, $countTypes);
    if (!empty($searchCondition)) {
        $countConditions[] = $searchCondition;
    }
}

if (!empty($countConditions)) {
    $countSql .= " WHERE " . implode(" AND ", $countConditions);
}

$totalProducts = 0;
if (!empty($countParams)) {
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        $countStmt->bind_param($countTypes, ...$countParams);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRow = $countResult->fetch_assoc();
        $totalProducts = $totalRow['total'];
        $countStmt->close();
    }
} else {
    $countResult = $conn->query($countSql);
    if ($countResult) {
        $totalRow = $countResult->fetch_assoc();
        $totalProducts = $totalRow['total'];
    }
}

// Add pagination info as hidden element for JavaScript
echo '<div id="pagination-info" data-current-page="' . $page . '" data-total-products="' . $totalProducts . '" data-limit="' . $limit . '" style="display:none;"></div>';
?>