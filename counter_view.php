<?php
// セキュリティヘッダー
header("Content-Type: text/html; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN"); // iframe内での表示を同一オリジンのみ許可

$visitId = $_GET['visit_id'] ?? '';
$number = null;

if (is_string($visitId) && preg_match('/\A[a-f0-9]{32}\z/', $visitId) === 1) {
    session_start();
    $storedNumber = $_SESSION['counter_visits'][$visitId] ?? null;
    if (is_int($storedNumber) && $storedNumber > 0) {
        $number = $storedNumber;
    }
    session_write_close();
}

// HTML出力（タグ付き）
echo '<div style="font-family:\'ＭＳ Ｐゴシック\'; font-size:12px;">';
echo '<b>キリ番BBS</b><br>';
if ($number === null) {
    echo '番号を取得できませんでした。ページを再読み込みしてください。';
} else {
    echo 'あなたは <strong>' . htmlspecialchars((string)$number, ENT_QUOTES, 'UTF-8') . '</strong> 番目のお客様♥';
}
echo '</div>';

if ($number !== null) {
    echo '<script>if (window.parent && window.parent.setKiribanVisitId) {';
    echo 'window.parent.setKiribanVisitId(' . json_encode($visitId) . ');';
    echo '}</script>';
}
?>
