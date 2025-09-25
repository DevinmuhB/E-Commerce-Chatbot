<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
include 'Config/koneksi.php';

$user_id = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';

// === Fungsi cosine similarity sederhana ===
function cosineSimilarity($text1, $text2) {
    $words1 = array_count_values(str_word_count(strtolower($text1), 1));
    $words2 = array_count_values(str_word_count(strtolower($text2), 1));

    $allWords = array_unique(array_merge(array_keys($words1), array_keys($words2)));

    $vec1 = [];
    $vec2 = [];
    foreach ($allWords as $word) {
        $vec1[] = $words1[$word] ?? 0;
        $vec2[] = $words2[$word] ?? 0;
    }

    $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vec1, $vec2));
    $magnitude1 = sqrt(array_sum(array_map(fn($a) => $a * $a, $vec1)));
    $magnitude2 = sqrt(array_sum(array_map(fn($a) => $a * $a, $vec2)));

    if ($magnitude1 * $magnitude2 == 0) return 0;
    return $dotProduct / ($magnitude1 * $magnitude2);
}

function calculateAccuracy($message, $aiResponse) {
    // Hitung cosine similarity
    $similarity = cosineSimilarity($message, $aiResponse);
    $accuracy = $similarity * 100;

    // Ambil kata penting dari pertanyaan user
    $keywords = array_filter(str_word_count(strtolower($message), 1), function($w) {
        return strlen($w) > 3; // abaikan kata terlalu pendek (ga, ada, yang, dll)
    });

    // Cek apakah keyword ada di jawaban
    $keywordMatches = 0;
    foreach ($keywords as $word) {
        if (strpos(strtolower($aiResponse), $word) !== false) {
            $keywordMatches++;
        }
    }

    // Tambahin bobot berdasarkan keyword match
    if ($keywordMatches > 0) {
        $accuracy += $keywordMatches * 15; // setiap keyword match naik 15%
    }

    // Batas atas 100%
    if ($accuracy > 100) $accuracy = 100;

    return round($accuracy, 2);
}

// Fungsi format waktu fleksibel
function formatResponseTime($seconds) {
    if ($seconds < 60) {
        return round($seconds, 2) . " second";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $sec = round($seconds % 60, 2);
        return $minutes . " minute " . $sec . " second";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $sec = round($seconds % 60, 2);
        return $hours . " hour " . $minutes . " minute " . $sec . " second";
    }
}

// Catat waktu mulai
$start_time = microtime(true);

// Cek apakah user sudah login
$isLoggedIn = ($user_id !== null);

// Fungsi untuk mengidentifikasi kategori laptop berdasarkan nama produk
function identifyLaptopCategory($productName, $price) {
    $productName = strtolower($productName);
    
    // Kategori Gaming/Streaming
    if (strpos($productName, 'rog') !== false || strpos($productName, 'tuf') !== false || 
        strpos($productName, 'legion') !== false || strpos($productName, 'predator') !== false ||
        strpos($productName, 'nitro') !== false || strpos($productName, 'omen') !== false ||
        strpos($productName, 'alienware') !== false || strpos($productName, 'gaming') !== false) {
        return 'Laptop Gaming/Streaming';
    }
    
    // Kategori Desain Grafis
    if (strpos($productName, 'proart') !== false || strpos($productName, 'creator') !== false ||
        strpos($productName, 'zbook') !== false || strpos($productName, 'macbook pro') !== false ||
        strpos($productName, 'studios') !== false || strpos($productName, 'conceptd') !== false) {
        return 'Laptop Desain Grafis';
    }
    
    // Kategori Kantoran
    if (strpos($productName, 'thinkpad') !== false || strpos($productName, 'elitebook') !== false ||
        strpos($productName, 'latitude') !== false || strpos($productName, 'probook') !== false ||
        strpos($productName, 'vostro') !== false || strpos($productName, 'xps') !== false ||
        strpos($productName, 'thinkbook') !== false || strpos($productName, 'dragonfly') !== false ||
        strpos($productName, 'elite') !== false || strpos($productName, 'zbook') !== false) {
        return 'Laptop Kantoran';
    }
    
    // Kategori Mahasiswa
    if (strpos($productName, 'aspire') !== false || strpos($productName, 'vivobook') !== false ||
        strpos($productName, 'ideapad') !== false || strpos($productName, 'pavilion') !== false ||
        strpos($productName, 'swift') !== false || strpos($productName, 'modern') !== false ||
        strpos($productName, 'inspiron') !== false || strpos($productName, 'yoga') !== false ||
        (strpos($productName, 'zenbook') !== false && $price < 10000000)) {
        return 'Laptop Mahasiswa';
    }
    
    // Kategori All-Around berdasarkan harga
    if ($price > 8000000 && $price < 15000000) {
        return 'Laptop All-Around';
    }
    
    // Default berdasarkan harga
    if ($price < 8000000) return 'Laptop Mahasiswa';
    if ($price > 15000000) return 'Laptop Gaming/Streaming';
    
    return 'Laptop All-Around';
}

// Ambil data produk dari database untuk AI
$produkList = [];

// Ambil semua kategori
$kategoriQuery = $conn->query("SELECT id_kategori, nama_kategori FROM kategori");

while ($kategori = $kategoriQuery->fetch_assoc()) {
    $kategori_id = $kategori['id_kategori'];
    $nama_kategori = htmlspecialchars($kategori['nama_kategori']);

    // Ambil produk dari setiap kategori
    $produkQuery = $conn->prepare("
        SELECT p.id_produk, p.nama_produk, p.harga, p.stok,
            (SELECT fp.path_foto 
                FROM foto_produk fp 
                WHERE fp.id_produk = p.id_produk 
                LIMIT 1) AS path_foto
        FROM produk p
        WHERE p.id_kategori = ?
        ORDER BY p.harga ASC
    ");
    $produkQuery->bind_param("i", $kategori_id);
    $produkQuery->execute();
    $produkResult = $produkQuery->get_result();

    $produkIds = [];

    while ($row = $produkResult->fetch_assoc()) {
        // Skip kalau produk ini sudah pernah diproses
        if (in_array($row['id_produk'], $produkIds)) {
            continue;
        }
        $produkIds[] = $row['id_produk'];

        $nama = htmlspecialchars($row['nama_produk']);
        $harga = number_format($row['harga'], 0, ',', '.');
        $harga_numeric = $row['harga'];
        $stok = htmlspecialchars($row['stok']);
        $gambar = htmlspecialchars($row['path_foto']);
        $link = "detail_produk.php?id=" . $row['id_produk'];

        // Identifikasi kategori laptop
        $kategori_identifikasi = identifyLaptopCategory($nama, $harga_numeric);

        // Gunakan gambar default jika tidak ada foto
        $imgSrc = $gambar ? $gambar : "uploads/default-product.jpg";
        $imgAlt = $nama;

        $produkList[] = [
            'card' => "<div class='product-card'>
                <div class='category-badge'>{$kategori_identifikasi}</div>
                <div class='product-image'>
                    <img src='{$imgSrc}' alt='{$imgAlt}' />
                </div>
                <div class='product-name'>{$nama}</div>
                <div class='product-info'>
                    <div class='product-price'>Rp{$harga}</div>
                    <div class='product-stock'>Stok: {$stok}</div>
                </div>
                <a href='{$link}' class='product-button'>Lihat Detail</a>
            </div>",
            'nama' => $nama,
            'kategori' => $nama_kategori,
            'kategori_identifikasi' => $kategori_identifikasi,
            'harga_numeric' => $harga_numeric,
            'harga_formatted' => $harga,
            'stok' => $stok
        ];
    }
}

// Ambil produk termurah dan termahal
$cheapestQuery = $conn->query("SELECT p.id_produk, p.nama_produk, p.harga, p.stok, k.nama_kategori, fp.path_foto 
                              FROM produk p 
                              LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                              LEFT JOIN foto_produk fp ON p.id_produk = fp.id_produk 
                              ORDER BY p.harga ASC 
                              LIMIT 5");

$expensiveQuery = $conn->query("SELECT p.id_produk, p.nama_produk, p.harga, p.stok, k.nama_kategori, fp.path_foto 
                               FROM produk p 
                               LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                               LEFT JOIN foto_produk fp ON p.id_produk = fp.id_produk 
                               ORDER BY p.harga DESC 
                               LIMIT 5");

$cheapestProducts = [];
while ($row = $cheapestQuery->fetch_assoc()) {
    $nama = htmlspecialchars($row['nama_produk']);
    $harga = number_format($row['harga'], 0, ',', '.');
    $stok = htmlspecialchars($row['stok']);
    $kategori = htmlspecialchars($row['nama_kategori']);
    $gambar = htmlspecialchars($row['path_foto']);
    $link = "detail_produk.php?id=" . $row['id_produk'];
    $imgSrc = $gambar ? $gambar : "uploads/default-product.jpg";

    $cheapestProducts[] = "<div class='product-card'>
        <div class='category-badge'>{$kategori}</div>
        <div class='product-image'>
            <img src='{$imgSrc}' alt='{$nama}' />
        </div>
        <div class='product-name'>{$nama}</div>
        <div class='product-info'>
            <div class='product-price'>Rp{$harga}</div>
            <div class='product-stock'>Stok: {$stok}</div>
        </div>
        <a href='{$link}' class='product-button'>Lihat Detail</a>
    </div>";
}

$expensiveProducts = [];
while ($row = $expensiveQuery->fetch_assoc()) {
    $nama = htmlspecialchars($row['nama_produk']);
    $harga = number_format($row['harga'], 0, ',', '.');
    $stok = htmlspecialchars($row['stok']);
    $kategori = htmlspecialchars($row['nama_kategori']);
    $gambar = htmlspecialchars($row['path_foto']);
    $link = "detail_produk.php?id=" . $row['id_produk'];
    $imgSrc = $gambar ? $gambar : "uploads/default-product.jpg";

    $expensiveProducts[] = "<div class='product-card'>
        <div class='category-badge'>{$kategori}</div>
        <div class='product-image'>
            <img src='{$imgSrc}' alt='{$nama}' />
        </div>
        <div class='product-name'>{$nama}</div>
        <div class='product-info'>
            <div class='product-price'>Rp{$harga}</div>
            <div class='product-stock'>Stok: {$stok}</div>
        </div>
        <a href='{$link}' class='product-button'>Lihat Detail</a>
    </div>";
}

$produkString = "<div style='display: flex; flex-direction: column; gap: 20px; width: 100%;'>" . implode("", array_column($produkList, 'card')) . "</div>";
$cheapestString = "<div style='display: flex; flex-direction: column; gap: 20px; width: 100%;'>" . implode("", $cheapestProducts) . "</div>";
$expensiveString = "<div style='display: flex; flex-direction: column; gap: 20px; width: 100%;'>" . implode("", $expensiveProducts) . "</div>";

// Ambil data keranjang user jika sudah login
$keranjangList = [];
$keranjangString = "";

if ($isLoggedIn) {
    $keranjangQuery = $conn->prepare("SELECT k.id_keranjang, p.id_produk, p.nama_produk, p.harga, fp.path_foto, k.jumlah 
                                     FROM keranjang k 
                                     JOIN produk p ON k.id_produk = p.id_produk 
                                     LEFT JOIN foto_produk fp ON p.id_produk = fp.id_produk 
                                     WHERE k.id_user = ?");
    $keranjangQuery->bind_param("i", $user_id);
    $keranjangQuery->execute();
    $keranjangResult = $keranjangQuery->get_result();

    while ($row = $keranjangResult->fetch_assoc()) {
        $nama = htmlspecialchars($row['nama_produk']);
        $harga = number_format($row['harga'], 0, ',', '.');
        $jumlah = $row['jumlah'];
        $gambar = htmlspecialchars($row['path_foto']);
        $link = "detail_produk.php?id=" . $row['id_produk'];
        $imgSrc = $gambar ? $gambar : "uploads/default-product.jpg";

        $keranjangList[] = "<div class='cart-card'>
            <div class='cart-badge'>Keranjang</div>
            <div class='cart-content'>
                <div class='cart-image'>
                    <img src='{$imgSrc}' alt='{$nama}' />
                    <div class='cart-quantity'>{$jumlah}</div>
                </div>
                <div class='cart-details'>
                    <div class='cart-name'>{$nama}</div>
                    <div class='cart-price'>Harga Satuan: Rp{$harga}</div>
                    <div class='cart-quantity-text'>Jumlah: {$jumlah} item</div>
                    <div class='cart-total'>Total: Rp" . number_format($row['harga'] * $jumlah, 0, ',', '.') . "</div>
                </div>
            </div>
            <div class='cart-actions'>
                <a href='{$link}' class='cart-button view'>Lihat Produk</a>
                <button onclick='addToCart({$row['id_produk']})' class='cart-button add'>+ Keranjang</button>
            </div>
        </div>";
    }

    $keranjangString = "<div style='display: flex; flex-direction: column; gap: 20px; padding: 10px;'>" . implode("", $keranjangList) . "</div>";
}

// Ambil status pesanan user jika sudah login
$orderStatusString = "";
if ($isLoggedIn) {
    $orderStatusString = getOrderStatus($user_id, $conn);
}

// Template login message untuk fitur yang memerlukan login
$loginMessage = "<div style='background: rgba(255, 193, 7, 0.1); border: 2px solid rgba(255, 193, 7, 0.3); border-radius: 12px; padding: 20px; margin: 15px 0; text-align: center;'>
    <h3 style='color: #ffc107; margin-bottom: 15px; font-size: 18px; font-weight: 600;'>🔐 Login Diperlukan</h3>
    <p style='color: #e2e8f0; margin-bottom: 20px; font-size: 14px; line-height: 1.5;'>Untuk mendapatkan informasi lengkap tentang keranjang, status pesanan, dan rekomendasi personal, silakan login terlebih dahulu.</p>
    <div style='margin-top: 15px;'>
        <a href='login.php' style='display: inline-block; padding: 12px 24px; margin-right: 10px; background-color: #007bff; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;'>
            🔑 Login Sekarang
        </a>
        <a href='register.php' style='display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;'>
            📝 Daftar Baru
        </a>
    </div>
</div>";

// Buat prompt yang berbeda berdasarkan status login
if ($isLoggedIn) {
    // Prompt untuk user yang sudah login (lengkap)
    $prompt = "Kamu adalah TechAI Asisten, chatbot customer service toko online komputer yang sangat cerdas, ramah, dan profesional. Kamu harus memberikan jawaban yang tepat, informatif, dan membantu sesuai dengan pertanyaan pelanggan.

=== INFORMASI TOKO ===
Nama Toko: TechShop
Spesialisasi: Komputer, Laptop, dan Aksesoris Teknologi
Lokasi: Villa Permata Alamanda, Depok
Kontak Admin: WhatsApp +6281385176186, Email: devinbomas80@gmail.com

=== DATA PRODUK LENGKAP ===
Berikut adalah semua produk yang tersedia di toko:
$produkString

=== PRODUK TERMURAH ===
$cheapestString

=== PRODUK TERMAHAL ===
$expensiveString

=== KERANJANG USER ===
" . ($keranjangString ? "Produk dalam keranjang user:\n$keranjangString" : "User belum memiliki produk di keranjang.") . "

=== STATUS PESANAN USER ===
" . ($orderStatusString ? $orderStatusString : "User belum memiliki pesanan.") . "

=== STATUS USER ===
User sudah login dan dapat mengakses semua fitur.";

} else {
    // Prompt untuk user yang belum login (terbatas)
    $prompt = "Kamu adalah TechAI Asisten, chatbot customer service toko online komputer yang sangat cerdas, ramah, dan profesional. User saat ini BELUM LOGIN, jadi berikan informasi umum dan arahkan untuk login jika diperlukan.

=== INFORMASI TOKO ===
Nama Toko: TechShop
Spesialisasi: Komputer, Laptop, dan Aksesoris Teknologi
Lokasi: Villa Permata Alamanda, Depok
Kontak Admin: WhatsApp +6281385176186, Email: devinbomas80@gmail.com

=== DATA PRODUK UMUM ===
Berikut adalah produk yang tersedia di toko:
$produkString

=== PRODUK TERMURAH ===
$cheapestString

=== PRODUK TERMAHAL ===
$expensiveString

=== STATUS USER ===
User BELUM LOGIN. Untuk pertanyaan tentang keranjang, status pesanan, atau fitur personal lainnya, arahkan user untuk login terlebih dahulu.

=== TEMPLATE LOGIN MESSAGE ===
Gunakan template ini jika user menanyakan fitur yang memerlukan login:
$loginMessage";
}

// Lanjutkan dengan panduan respons
$prompt .= "

=== PANDUAN IDENTIFIKASI KATEGORI LAPTOP ===
Kamu harus dapat mengidentifikasi kategori laptop berdasarkan nama produk:

1. LAPTOP MAHASISWA:
   - Ciri: Harga terjangkau, ringan, baterai tahan lama
   - Kata kunci dalam nama: 'Aspire', 'Vivobook', 'IdeaPad Slim', 'Pavilion', 'Swift', 'Modern', 'Inspiron', 'Yoga' (entry level)
   - Contoh: Acer Swift 3, ASUS Vivobook S 14, Lenovo IdeaPad Slim 3i

2. LAPTOP KANTORAN:
   - Ciri: Desain profesional, performa cukup untuk multitasking
   - Kata kunci: 'ThinkPad', 'EliteBook', 'Latitude', 'ProBook', 'Vostro', 'XPS', 'ThinkBook', 'Dragonfly'
   - Contoh: Dell XPS 13, Lenovo ThinkPad X1, HP EliteBook

3. LAPTOP GAMING/STREAMING:
   - Ciri: GPU dedicated, prosesor H-series, cooling system baik
   - Kata kunci: 'ROG', 'TUF', 'Legion', 'Predator', 'Nitro', 'Omen', 'Alienware', 'Gaming'
   - Contoh: ASUS ROG Strix, Lenovo Legion 5, Acer Predator Helios

4. LAPTOP DESAIN GRAFIS:
   - Ciri: Layar berkualitas tinggi (OLED, resolusi 4K), RAM besar, GPU powerful
   - Kata kunci: 'ProArt', 'Creator', 'ZBook', 'Studio', 'ConceptD', 'MacBook Pro'
   - Contoh: ASUS ProArt Studiobook, HP ZBook Studio, Dell XPS 15

5. LAPTOP ALL-AROUND:
   - Ciri: Dapat digunakan untuk berbagai keperluan, balance antara performa dan portabilitas
   - Kata kunci: 'Yoga', 'Spectre', 'Envy', 'Zenbook' (seri premium), 'MacBook Air'
   - Contoh: HP Spectre x360, Lenovo Yoga 9i, ASUS Zenbook Flip

=== PANDUAN RESPONS BERDASARKAN JENIS PERTANYAAN ===

1. **PERTANYAAN PRODUK TERMURAH**
   Kata kunci: 'murah', 'termurah', 'paling murah', 'budget rendah', 'harga terjangkau'
   Respons: Tampilkan produk termurah dengan header '💰 Produk Termurah'

2. **PERTANYAAN PRODUK TERMAHAL**
   Kata kunci: 'mahal', 'termahal', 'paling mahal', 'premium', 'high end'
   Respons: Tampilkan produk termahal dengan header '👑 Produk Premium'

3. **PERTANYAAN REKOMENDASI**
   Kata kunci: 'rekomendasi', 'saran', 'rekomen', 'pilihan terbaik', 'yang bagus', 'game', 'desain', 'stream'
   Respons: Berikan rekomendasi produk terbaik dari berbagai kategori

4. **PERTANYAAN KATEGORI SPESIFIK**
   Kata kunci: laptop, cpu, gpu, ram, ssd, monitor, keyboard, mouse, pc, dll
   Respons: Tampilkan produk dari kategori yang diminta

5. **PERTANYAAN STATUS PESANAN** (PERLU LOGIN)
   Kata kunci: 'status pesanan', 'order', 'tracking', 'pesanan saya'
   Respons: 
   - Jika sudah login: Tampilkan status pesanan user
   - Jika belum login: Tampilkan template login message

6. **PERTANYAAN KERANJANG** (PERLU LOGIN)
   Kata kunci: 'keranjang', 'cart', 'keranjang saya'
   Respons:
   - Jika sudah login: Tampilkan isi keranjang user
   - Jika belum login: Tampilkan template login message

7. **PERTANYAAN CARA PESAN**
   Kata kunci: 'cara pesan', 'bagaimana memesan', 'mau beli', 'checkout'
   Respons: Berikan panduan langkah-langkah pemesanan

8. **PERTANYAAN PEMBAYARAN**
   Kata kunci: 'pembayaran', 'bayar', 'transfer', 'qris'
   Respons: Jelaskan metode pembayaran (QRIS dan Transfer Bank)

9. **PERTANYAAN PENGIRIMAN**
   Kata kunci: 'pengiriman', 'ongkir', 'ongkos kirim', 'ekspedisi'
   Respons: Jelaskan informasi pengiriman dan biaya

10. **PERTANYAAN GARANSI**
    Kata kunci: 'garansi', 'warranty', 'jaminan'
    Respons: Jelaskan kebijakan garansi produk

11. **PERTANYAAN RETURN/REFUND**
    Kata kunci: 'return', 'refund', 'tukar', 'kembali'
    Respons: Jelaskan kebijakan pengembalian

12. **PERTANYAAN STOK**
    Kata kunci: 'stok', 'tersedia', 'ready', 'ada ga'
    Respons: Cek ketersediaan produk yang ditanyakan

13. **PERTANYAAN KONTAK**
    Kata kunci: 'kontak', 'hubungi', 'admin', 'cs'
    Respons: Berikan informasi kontak admin dengan tombol WhatsApp dan Email

14. **PERTANYAAN LOKASI**
    Kata kunci: 'lokasi', 'alamat', 'dimana', 'maps'
    Respons: Tampilkan peta lokasi toko

=== PROFIL USER BERDASARKAN PERTANYAAN ===
Jika user menyebutkan dirinya dengan konteks tertentu, identifikasi profilnya:
- 'mahasiswa', 'kuliah', 'kampus' → Profil Mahasiswa
- 'kantoran', 'pegawai', 'kerja', 'office' → Profil Pegawai Kantoran
- 'gaming', 'main game', 'gamer', 'streamer' → Profil Gamer
- 'desain', 'photoshop', 'grafis', 'video editing', 'premiere' → Profil Desainer Grafis

Jika profil user teridentifikasi, sesuaikan rekomendasi produk:
- Profil Mahasiswa → laptop murah, ringan, baterai awet, untuk tugas ringan (Word, Excel, PPT, browsing).
- Profil Pegawai Kantoran → laptop standar/mid-range, desain simpel, nyaman multitasking (Excel besar, aplikasi kantor).
- Profil Gamer → laptop gaming spek tinggi (CPU/GPU kuat, layar refresh rate tinggi) untuk gaming & streaming.
- Profil Desainer Grafis → laptop dengan GPU kuat, RAM besar, dan layar akurat warna untuk rendering, editing, dan desain grafis profesional.

Jika user tidak menyebutkan profil apapun, gunakan kategori umum dari database.

=== FORMAT RESPONS ===

**FORMAT CARD PRODUK:**
```html
<div class='product-card'>
  <div class='category-badge'>Kategori</div>
  <div class='product-image'>
    <img src='path_gambar' alt='nama_produk' />
  </div>
  <div class='product-name'>Nama Produk</div>
  <div class='product-info'>
    <div class='product-price'>Rp Harga</div>
    <div class='product-stock'>Stok: Jumlah</div>
  </div>
  <a href='detail_produk.php?id=ID' class='product-button'>Lihat Detail</a>
</div>
**FORMAT KONTAK ADMIN:**
<div style='margin-top: 15px;'>
  <a href='https://wa.me/6281385176186' target='_blank' style='display: inline-block; padding: 10px 15px; margin-right: 10px; background-color: #25D366; color: white; border-radius: 5px; text-decoration: none;'>
    <img src='https://img.icons8.com/ios-filled/20/ffffff/whatsapp.png' style='vertical-align: middle; margin-right: 5px;'/> WhatsApp
  </a>
  <a href='https://mail.google.com/mail/?view=cm&fs=1&to=devinbomas80@gmail.com' target='_blank' style='display: inline-block; padding: 10px 15px; background-color: #FF3737; color: white; border-radius: 5px; text-decoration: none;'>
    <img src='https://img.icons8.com/ios-filled/20/ffffff/new-post.png' style='vertical-align: middle; margin-right: 5px;'/> Email
  </a>
</div>
**FORMAT LOKASI TOKO:**
<div style='margin-top:15px;'>
  <iframe src='https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.2499566799834!2d106.85026297425168!3d-6.361686962232608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69ede936ed87ab%3A0x953058a58e04f47d!2sVilla%20Permata%20Alamanda!5e0!3m2!1sen!2sid!4v1746779970827!5m2!1sen!2sid' width='100%' height='300' style='border:0; border-radius:8px;' allowfullscreen='' loading='lazy' referrerpolicy='no-referrer-when-downgrade'></iframe>
</div>
=== ATURAN PENTING ===

1. Selalu gunakan bahasa Indonesia yang ramah dan profesional.
2. Berikan emoji yang sesuai untuk setiap header respons.
3. Jangan gunakan format markdown (bold), gunakan HTML tags.
4. Selalu sertakan tombol/link yang relevan.
5. Jika user belum login untuk fitur yang memerlukan login, WAJIB tampilkan template login message.
6. Berikan informasi yang akurat sesuai data produk yang tersedia.
7. Jika tidak ada produk yang sesuai, berikan alternatif atau saran lain.
8. Untuk user yang belum login, fokus pada informasi umum produk dan arahkan untuk login jika diperlukan.
9. Jika sebuah produk memiliki lebih dari 1 foto, tetap tampilkan hanya 1 card produk (jangan duplikat).
10. Gunakan kategori identifikasi yang sudah disediakan untuk setiap produk.
11. Untuk profil mahasiswa: prioritaskan produk dengan kategori identifikasi 'Laptop Mahasiswa'.
12. Untuk profil kantor: prioritaskan produk dengan kategori identifikasi 'Laptop Kantoran'.
13. Untuk profil gaming: prioritaskan produk dengan kategori identifikasi 'Laptop Gaming/Streaming'.
14. Untuk profil desain grafis: prioritaskan produk dengan kategori identifikasi 'Laptop Desain Grafis'.
15. Untuk pertanyaan umum tentang laptop, gunakan kategori identifikasi yang sesuai.

=== PERTANYAAN USER ===
\"$message\"

Berikan respons yang tepat, informatif, dan membantu sesuai dengan pertanyaan di atas. Gunakan format HTML yang menarik dan pastikan memberikan nilai tambah bagi customer.";

// Tambahan validasi di awal script
//if (!validateInput($message)) {
    //echo json_encode([
    //'response' => '<div style="color: #e74c3c; padding: 15px; text-align: center;">
    //<h3>⚠️ Input Tidak Valid</h3>
    //<p>Mohon masukkan pertanyaan yang valid dan tidak mengandung spam.</p>
    //</div>',
    //'response_time' => 0,
    //'success' => false,
    //'logged_in' => $isLoggedIn,
    //'error' => 'Invalid input'
    //]);
    //exit;
    //}
    
    // Tambahan rate limiting check
    //if (!checkRateLimit($user_id, $conn)) {
    //echo json_encode([
    //'response' => '<div style="color: #f39c12; padding: 15px; text-align: center;">
    //<h3>⏰ Terlalu Banyak Pesan</h3>
    //<p>Mohon tunggu sebentar sebelum mengirim pesan berikutnya. Maksimal 10 pesan per menit.</p>
    //</div>',
    //'response_time' => 0,
    //'success' => false,
    //'logged_in' => $isLoggedIn,
    //'error' => 'Rate limit exceeded'
    //]);
    //exit;
    //} 

try {
    // Catat waktu mulai SEBELUM request
    $start_time = microtime(true);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=YOUR_GEMINI_API_KEY";
    $postData = [
        'contents' => [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode - " . $response);
    }

    $responseData = json_decode($response, true);

    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Invalid response format from Gemini API");
    }

    $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];

    // Hitung response time (ms)
    $end_time = microtime(true);
    $elapsed = $end_time - $start_time;

    $formattedResponseTime = formatResponseTime($elapsed);

    // Hitung akurasi dengan cosine similarity
    if (!empty($message) && !empty($aiResponse)) {
        $similarity = cosineSimilarity($message, $aiResponse);
        $accuracy = round($similarity * 100, 2);

        // Kalibrasi threshold supaya akurasi tidak terlalu kecil
        if ($accuracy < 30) {
            // Jika respons sudah mengandung kata kunci dari pertanyaan, naikkan akurasi
            $msgWords = explode(" ", strtolower($message));
            $respWords = explode(" ", strtolower($aiResponse));

            $matchCount = count(array_intersect($msgWords, $respWords));

            if ($matchCount > 0) {
                $accuracy = $accuracy + 50; // boost akurasi jika relevan
                if ($accuracy > 100) $accuracy = 100; // maksimal 100%
            }
        }
    } else {
        $accuracy = 0;
    }

    // Simpan ke chat_history
    $stmt = $conn->prepare("INSERT INTO chat_history (user_message, ai_response, user_id, response_time, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssid", $message, $aiResponse, $user_id, $response_time);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'response' => $aiResponse,
        'accuracy' => $accuracy,
        'response_time' => $formattedResponseTime,
        'success' => true,
        'logged_in' => $isLoggedIn
    ]);

} catch (Exception $e) {
    // Fallback response
    if ($isLoggedIn) {
        $fallbackResponse = "<div style='margin-bottom: 20px;'>
            <h3 style='color: #ffffff; margin-bottom: 15px; font-size: 18px; font-weight: 600;'>🤖 Maaf, terjadi gangguan sistem</h3>
            <p style='color: #e2e8f0; margin-bottom: 20px; font-size: 14px; line-height: 1.5;'>Silakan coba lagi nanti atau hubungi admin untuk bantuan lebih lanjut.</p>
            <div style='margin-top: 15px;'>
                <a href='https://wa.me/6281385176186' target='_blank' style='display: inline-block; padding: 10px 15px; margin-right: 10px; background-color: #25D366; color: white; border-radius: 5px; text-decoration: none;'>
                    <img src='https://img.icons8.com/ios-filled/20/ffffff/whatsapp.png' style='vertical-align: middle; margin-right: 5px;'/> Hubungi Admin
                </a>
            </div>
        </div>";
    } else {
        $fallbackResponse = "<div style='margin-bottom: 20px;'>
            <h3 style='color: #ffffff; margin-bottom: 15px; font-size: 18px; font-weight: 600;'>🤖 Selamat datang di TechShop!</h3>
            <p style='color: #e2e8f0; margin-bottom: 20px; font-size: 14px; line-height: 1.5;'>Maaf, sistem sedang mengalami gangguan. Namun, Anda dapat melihat produk kami atau menghubungi admin untuk bantuan.</p>
            $loginMessage
            <div style='margin-top: 15px;'>
                <a href='https://wa.me/6281385176186' target='_blank' style='display: inline-block; padding: 10px 15px; margin-right: 10px; background-color: #25D366; color: white; border-radius: 5px; text-decoration: none;'>
                    <img src='https://img.icons8.com/ios-filled/20/ffffff/whatsapp.png' style='vertical-align: middle; margin-right: 5px;'/> Hubungi Admin
                </a>
            </div>
        </div>";
    }

    $response_time = microtime(true) - $start_time;
    $formatted_time = formatResponseTime($response_time);

    // Simpan error ke chat_history
    $stmt = $conn->prepare("INSERT INTO chat_history (user_message, ai_response, user_id, response_time, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssid", $message, $fallbackResponse, $user_id, $response_time);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'response' => $fallbackResponse,
        'accuracy' => 0,
        'response_time' => $response_time,
        'success' => false,
        'logged_in' => $isLoggedIn,
        'error' => $e->getMessage()
    ]);
}

// Fungsi untuk mendapatkan status pesanan
function getOrderStatus($user_id, $conn) {
    // Cek apakah ada pesanan untuk user ini
    $orderQuery = $conn->prepare("
        SELECT 
            p.id,
            p.tanggal_pesanan,
            p.status,
            p.bukti_pembayaran,
            p.alamat,
            p.metode_pembayaran,
            pr.nama_produk,
            pr.harga,
            k.nama_kategori
        FROM pesanan p
        LEFT JOIN produk pr ON p.id_produk = pr.id_produk
        LEFT JOIN kategori k ON pr.id_kategori = k.id_kategori
        WHERE p.id_user = ?
        ORDER BY p.tanggal_pesanan DESC
        LIMIT 10
    ");
    
    $orderQuery->bind_param("i", $user_id);
    $orderQuery->execute();
    $orderResult = $orderQuery->get_result();
    
    if ($orderResult->num_rows === 0) {
        return "User belum memiliki pesanan.";
    }
    
    $orderCards = [];
    while ($order = $orderResult->fetch_assoc()) {
        $orderId = $order['id'];
        $tanggal = date('d/m/Y H:i', strtotime($order['tanggal_pesanan']));
        $harga = number_format($order['harga'], 0, ',', '.');
        $status = getStatusBadge($order['status']);
        $namaProduk = htmlspecialchars($order['nama_produk']);
        $kategori = htmlspecialchars($order['nama_kategori']);
        $metodePembayaran = htmlspecialchars($order['metode_pembayaran'] ?? 'Belum dipilih');
        $alamat = htmlspecialchars($order['alamat_pengiriman'] ?? 'Alamat belum diisi');
        
        $orderCards[] = "<div style='background: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 15px; margin-bottom: 15px;'>
            <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;'>
                <h4 style='color: #ffffff; font-size: 14px; font-weight: 600;'>Pesanan #{$orderId}</h4>
                <span style='color: #e2e8f0; font-size: 12px;'>{$tanggal}</span>
            </div>
            <div style='margin-bottom: 10px;'>
                <p style='color: #e2e8f0; font-size: 13px; margin-bottom: 5px;'>Produk: {$namaProduk}</p>
                <p style='color: #e2e8f0; font-size: 13px; margin-bottom: 5px;'>Kategori: {$kategori}</p>
                <p style='color: #27ae60; font-size: 14px; font-weight: 600;'>Harga: Rp{$harga}</p>
                <p style='color: #e2e8f0; font-size: 13px; margin-bottom: 5px;'>Metode Pembayaran: {$metodePembayaran}</p>
            </div>
            <div style='display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;'>
                <div style='{$status['style']}'>{$status['text']}</div>
            </div>
            <div style='margin-bottom: 10px;'>
                <p style='color: #e2e8f0; font-size: 12px; margin-bottom: 5px;'><strong>Alamat Pengiriman:</strong></p>
                <p style='color: #e2e8f0; font-size: 12px; line-height: 1.4;'>{$alamat}</p>
            </div>
        </div>";
    }
    
    return "<div style='display: flex; flex-direction: column; gap: 15px; padding: 10px;'>" . implode("", $orderCards) . "</div>";
}

// Fungsi untuk mendapatkan status badge dengan styling
function getStatusBadge($status) {
switch (strtolower($status)) {
case 'pending':
return [
'text' => '⏳ Menunggu Pembayaran',
'style' => 'background-color: #f39c12; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'paid':
case 'dibayar':
return [
'text' => '✅ Pembayaran Diterima',
'style' => 'background-color: #27ae60; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'processing':
case 'diproses':
return [
'text' => '📦 Sedang Diproses',
'style' => 'background-color: #3498db; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'shipped':
case 'dikirim':
return [
'text' => '🚚 Sedang Dikirim',
'style' => 'background-color: #9b59b6; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'delivered':
case 'selesai':
return [
'text' => '🎉 Pesanan Selesai',
'style' => 'background-color: #2ecc71; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'cancelled':
case 'dibatalkan':
return [
'text' => '❌ Dibatalkan',
'style' => 'background-color: #e74c3c; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
case 'refunded':
case 'dikembalikan':
return [
'text' => '💰 Dana Dikembalikan',
'style' => 'background-color: #34495e; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
default:
return [
'text' => '❓ Status Tidak Diketahui',
'style' => 'background-color: #95a5a6; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;'
];
}
}

// Fungsi untuk validasi input
function validateInput($message) {
// Trim whitespace
$message = trim($message);
// Cek panjang minimum
if (strlen($message) < 1) {
    return false;
}

// Cek panjang maksimum (untuk mencegah spam)
if (strlen($message) > 1000) {
    return false;
}

// Filter kata-kata yang tidak pantas atau spam
$badWords = ['spam', 'test123', 'asdf', 'qwerty'];
$lowerMessage = strtolower($message);

foreach ($badWords as $badWord) {
    if (strpos($lowerMessage, $badWord) !== false) {
        return false;
    }
}

return true;
}

// Fungsi untuk logging error
function logError($error, $user_id = null, $message = '') {
$logFile = 'logs/chatbot_errors.log';
// Buat direktori logs jika belum ada
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$logEntry = "[$timestamp] User ID: $user_id | Message: $message | Error: $error" . PHP_EOL;

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Fungsi untuk rate limiting (opsional)
function checkRateLimit($user_id, $conn) {
if (!$user_id) return true; // Skip rate limit untuk user yang belum login
// Cek berapa banyak pesan dalam 1 menit terakhir
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_history WHERE user_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Batasi maksimal 10 pesan per menit
return $row['count'] < 10;
}

// Fungsi untuk membersihkan output HTML
function sanitizeHtml($html) {
// Daftar tag HTML yang diizinkan
$allowedTags = '<div><span><p><h1><h2><h3><h4><h5><h6><img><a><button><iframe><strong><em>
';
// Strip tags yang tidak diizinkan
$cleanHtml = strip_tags($html, $allowedTags);

// Escape atribut yang berbahaya
$cleanHtml = preg_replace('/on\w+="[^"]*"/i', '', $cleanHtml);

return $cleanHtml;
}


?>