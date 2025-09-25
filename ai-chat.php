<?php
include_once 'Config/koneksi.php';
$user_id = $_SESSION['user_id'] ?? null;
$username = 'User';
$isLoggedIn = false;

if ($user_id) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fetchedUsername);
    if ($stmt->fetch()) {
        $username = htmlspecialchars($fetchedUsername);
        $isLoggedIn = true;
    }
    $stmt->close();
}
?>
<!-- Bubble Chat AI Customer Service -->
<!-- Pastikan FontAwesome sudah di-load di <head> -->
<link rel="stylesheet" href="resource/css/ai-chat.css">

<button id="chatButton"><i class="fa-solid fa-headset"></i></button>
<div id="chatBox">
    <div id="chatHeader">TechAI <span class="close-button" style="cursor:pointer;">✖️</span></div>
    <div id="chatMessages"></div>
    <div id="chatInputArea">
        <input type="text" id="chatInput" placeholder="Tulis pesan..." />
        <button id="sendButton">Kirim</button>
    </div>
</div>

<script>
// Set nama user dan status login untuk JavaScript
window.namaUser = "<?php echo $username; ?>";
window.isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
</script>
<script src="resource/js/ai-chat.js"></script>
<!-- End Bubble Chat --> 