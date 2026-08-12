<?php
// 文字コード指定
header("Content-Type: text/html; charset=UTF-8");

// セキュリティヘッダー
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
date_default_timezone_set('Asia/Tokyo');

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit;
}

// CSRF対策（リファラーチェック）
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($referer) || strpos($referer, $host) === false) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// レート制限
session_start();
$now = time();
$last_submit = $_SESSION['last_kiriban_submit'] ?? 0;
if ($now - $last_submit < 10) { // 10秒間隔制限
    header("Location: index.html");
    exit;
}
$_SESSION['last_kiriban_submit'] = $now;

// 入力取得とサニタイズ（文字数制限追加）
$nameInput = $_POST['name'] ?? '';
$commentInput = $_POST['comment'] ?? '';
$name = is_string($nameInput) ? htmlspecialchars(trim($nameInput), ENT_QUOTES, 'UTF-8') : '';
$comment = is_string($commentInput) ? htmlspecialchars(trim($commentInput), ENT_QUOTES, 'UTF-8') : '';

// 文字数制限
$name = mb_substr($name, 0, 50);      // 50文字まで
$comment = mb_substr($comment, 0, 200); // 200文字まで

// 不適切な文字列のフィルタリング
$prohibited_words = ['<script>', 'javascript:', 'data:', 'vbscript:'];
foreach ($prohibited_words as $word) {
    $name = str_ireplace($word, '***', $name);
    $comment = str_ireplace($word, '***', $comment);
}


// 画面に表示した番号を訪問IDから取得
$visitId = $_POST['visit_id'] ?? '';
if (!is_string($visitId) || preg_match('/\A[a-f0-9]{32}\z/', $visitId) !== 1) {
    header("Location: index.html");
    exit;
}

$number = $_SESSION['counter_visits'][$visitId] ?? null;
if (!is_int($number) || $number <= 0) {
    header("Location: index.html");
    exit;
}

if ($name === '') $name = '匿名';
if ($comment === '') $comment = '';

// 1行のエントリを生成（改行なし）
$entry = "{$number}番 {$name}さん";
if ($comment !== '') {
    $entry .= "（{$comment}）";
}

// ファイルを読み込み、3行目に挿入
$file = getenv('HOGEHOGE_KIRIBAN_FILE') ?: __DIR__ . "/kiriban.txt";
$kiribanHandle = @fopen($file, 'c+');
if ($kiribanHandle === false || !flock($kiribanHandle, LOCK_EX)) {
    if (is_resource($kiribanHandle)) {
        fclose($kiribanHandle);
    }
    header("Location: index.html");
    exit;
}

$fileStat = fstat($kiribanHandle);
if ($fileStat === false || $fileStat['size'] > 100000) { // 100KB制限
    flock($kiribanHandle, LOCK_UN);
    fclose($kiribanHandle);
    header("Location: index.html");
    exit;
}

$contents = stream_get_contents($kiribanHandle);
if ($contents === false) {
    flock($kiribanHandle, LOCK_UN);
    fclose($kiribanHandle);
    header("Location: index.html");
    exit;
}

$lines = $contents === '' ? [] : preg_split('/\r\n|\r|\n/', rtrim($contents, "\r\n"));
if ($lines === false) {
    flock($kiribanHandle, LOCK_UN);
    fclose($kiribanHandle);
    header("Location: index.html");
    exit;
}

// 3行目に挿入（インデックス2）
array_splice($lines, 2, 0, $entry);

// 保存（1行ずつ + 最後に \n を1つだけ）
$newContents = implode("\n", $lines);
rewind($kiribanHandle);
if (!ftruncate($kiribanHandle, 0)
    || fwrite($kiribanHandle, $newContents) !== strlen($newContents)
    || !fflush($kiribanHandle)
) {
    flock($kiribanHandle, LOCK_UN);
    fclose($kiribanHandle);
    header("Location: index.html");
    exit;
}

flock($kiribanHandle, LOCK_UN);
fclose($kiribanHandle);

unset($_SESSION['counter_visits'][$visitId]);
session_write_close();

header("Location: index.html");
exit;
?>
