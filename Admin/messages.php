<?php
include '../Config/koneksi.php';

// Ambil data chat_history join users
$sql = "SELECT ch.*, u.username FROM chat_history ch LEFT JOIN users u ON ch.user_id = u.id ORDER BY 
    CASE WHEN ch.ai_responded_at IS NOT NULL THEN ch.ai_responded_at ELSE ch.timestamp END DESC LIMIT 100";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Messages - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .messages-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .messages-table th, .messages-table td {
            border: 1px solid #eee;
            padding: 12px 14px;
            text-align: left;
            vertical-align: top;
        }
        .messages-table th {
            background: #f7f7fa;
            color: #800000;
            font-weight: 600;
        }
        .messages-table td .ai-response {
            background: #f7f7fa;
            border-radius: 8px;
            padding: 10px 12px;
            margin: 0;
            font-size: 15px;
        }
        .messages-table tr:nth-child(even) { background: #fafbfc; }
    </style>
</head>
<body>
    <h2 style="margin:30px 0 10px 30px; color:#800000;">Pesan Customer Service AI</h2>
    <div style="overflow-x:auto; margin:0 30px 30px 30px;">
    <table class="messages-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Pesan User</th>
                <th>Jawaban AI</th>
                <th>Response Time</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                <td><?= nl2br(htmlspecialchars($row['user_message'])) ?></td>
                <td><div class="ai-response"><?= $row['ai_response'] ?></div></td>
                <td>
                    <?php
                    if (!empty($row['user_sent_at']) && !empty($row['ai_responded_at'])) {
                        $userTime = strtotime($row['user_sent_at']);
                        $aiTime = strtotime($row['ai_responded_at']);
                        $responseTime = $aiTime - $userTime;
                        if ($responseTime < 60) {
                            echo $responseTime . ' detik';
                        } else {
                            echo round($responseTime / 60, 1) . ' menit';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align:center;">Belum ada pesan</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</body>
</html> 