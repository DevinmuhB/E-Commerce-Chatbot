<?php
include 'Config/koneksi.php';

// Set content type to JSON
header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

// Prepare suggestions array
$suggestions = [];

try {
    // Split query into words for better matching
    $queryWords = explode(' ', $query);
    $queryWords = array_filter($queryWords); // Remove empty elements
    
    // Build search conditions for multiple words
    $searchConditions = [];
    $params = [];
    $types = "";
    
    foreach ($queryWords as $word) {
        $likeParam = '%' . $word . '%';
        $searchConditions[] = "(p.nama_produk LIKE ? OR p.deskripsi LIKE ? OR k.nama_kategori LIKE ?)";
        $params[] = $likeParam;
        $params[] = $likeParam;
        $params[] = $likeParam;
        $types .= "sss";
    }
    
    $whereClause = implode(' AND ', $searchConditions);
    
    // Query to get product name suggestions with relevance scoring
    $sql = "SELECT DISTINCT p.nama_produk, k.nama_kategori,
            CASE 
                WHEN LOWER(p.nama_produk) LIKE LOWER(?) THEN 1
                WHEN LOWER(p.nama_produk) LIKE LOWER(?) THEN 2
                WHEN LOWER(k.nama_kategori) LIKE LOWER(?) THEN 3
                WHEN LOWER(p.deskripsi) LIKE LOWER(?) THEN 4
                ELSE 5
            END as relevance
            FROM produk p 
            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
            WHERE $whereClause
            ORDER BY relevance ASC, p.nama_produk ASC 
            LIMIT 8";
    
    // Prepare relevance parameters
    $queryLike = '%' . $query . '%';
    $relevanceParams = [$queryLike, $queryLike, $queryLike, $queryLike];
    
    // Combine all parameters
    $allParams = array_merge($relevanceParams, $params);
    $allTypes = "ssss" . $types;
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $productNames = [];
        while ($row = $result->fetch_assoc()) {
            // Add full product name
            if (!in_array($row['nama_produk'], $productNames)) {
                $suggestions[] = $row['nama_produk'];
                $productNames[] = $row['nama_produk'];
            }
            
            // Add category name if relevant
            if (!empty($row['nama_kategori']) && 
                stripos($row['nama_kategori'], $query) !== false && 
                !in_array($row['nama_kategori'], $suggestions)) {
                $suggestions[] = $row['nama_kategori'];
            }
        }
        
        $stmt->close();
    }
    
    // If we have few suggestions, add keyword-based suggestions
    if (count($suggestions) < 5) {
        $keywordSuggestions = getKeywordSuggestions($conn, $query);
        $suggestions = array_merge($suggestions, $keywordSuggestions);
    }
    // Remove duplicates and limit results
    $suggestions = array_unique($suggestions);
    $suggestions = array_slice($suggestions, 0, 8);
    
} catch (Exception $e) {
    error_log("Search suggestions error: " . $e->getMessage());
    $suggestions = [];
}

// Function to get keyword-based suggestions
function getKeywordSuggestions($conn, $query) {
    $keywords = [];
    
    try {
        // Extract common keywords from product names
        $sql = "SELECT nama_produk FROM produk WHERE nama_produk LIKE ? LIMIT 20";
        $stmt = $conn->prepare($sql);
        $likeQuery = '%' . $query . '%';
        $stmt->bind_param("s", $likeQuery);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $words = explode(' ', $row['nama_produk']);
            foreach ($words as $word) {
                $word = trim($word);
                if (strlen($word) > 2 && 
                    stripos($word, $query) !== false && 
                    !in_array($word, $keywords)) {
                    $keywords[] = $word;
                }
            }
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Keyword suggestions error: " . $e->getMessage());
    }
    
    return array_slice($keywords, 0, 3);
}

// Return JSON response
echo json_encode(array_values($suggestions));
?>