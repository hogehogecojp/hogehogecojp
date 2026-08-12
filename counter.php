<?php
// リファラーチェック
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!empty($referer) && strpos($referer, $host) === false) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// セキュリティヘッダー
header("Content-Type: image/png");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("X-Content-Type-Options: nosniff");

// カウンターファイルのパス
$counterFile = getenv('HOGEHOGE_COUNTER_FILE') ?: __DIR__ . "/counter.txt";
$digitImage = __DIR__ . "/img/strip.gif"; // 数字画像のパス
$frameThickness = 4; // フレームの太さ（ピクセル単位）

// ページごとの訪問ID（表示番号と投稿番号の紐付けに使用）
$visitId = $_GET['visit_id'] ?? '';
if (!is_string($visitId) || ($visitId !== '' && preg_match('/\A[a-f0-9]{32}\z/', $visitId) !== 1)) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

if ($visitId !== '' && !session_start()) {
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

// 読み込み・加算・書き込みを同じロック内で行う
$counterHandle = @fopen($counterFile, 'r+');
if ($counterHandle === false || !flock($counterHandle, LOCK_EX)) {
    if (is_resource($counterHandle)) {
        fclose($counterHandle);
    }
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

$counterText = stream_get_contents($counterHandle);
$counterText = $counterText === false ? '' : trim($counterText);
if (preg_match('/\A[0-9]+\z/', $counterText) !== 1) {
    flock($counterHandle, LOCK_UN);
    fclose($counterHandle);
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

$currentCounter = filter_var($counterText, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);
if ($currentCounter === false || $currentCounter >= PHP_INT_MAX) {
    flock($counterHandle, LOCK_UN);
    fclose($counterHandle);
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

$counter = $currentCounter + 1;
$newCounterText = (string)$counter;
rewind($counterHandle);

if (!ftruncate($counterHandle, 0)
    || fwrite($counterHandle, $newCounterText) !== strlen($newCounterText)
    || !fflush($counterHandle)
) {
    flock($counterHandle, LOCK_UN);
    fclose($counterHandle);
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

flock($counterHandle, LOCK_UN);
fclose($counterHandle);

if ($visitId !== '') {
    if (!isset($_SESSION['counter_visits']) || !is_array($_SESSION['counter_visits'])) {
        $_SESSION['counter_visits'] = [];
    }

    $_SESSION['counter_visits'][$visitId] = $counter;

    // F5連打でsessionが増え続けないよう、直近20ページ分だけ保持する
    while (count($_SESSION['counter_visits']) > 20) {
        array_shift($_SESSION['counter_visits']);
    }

    session_write_close();
}

// 数字ごとの座標計算
$digitWidth = 15;
$digitHeight = 20;
$numDigits = strlen((string)$counter);

// 画像リソースの作成（フレームを含めたサイズ）
$totalWidth = ($digitWidth * $numDigits) + ($frameThickness * 2);
$totalHeight = $digitHeight + ($frameThickness * 2);
$image = imagecreatetruecolor($totalWidth, $totalHeight);

// 背景色の設定
$bgColor = imagecolorallocate($image, 0, 0, 0);
// Ridge 効果用の色設定（必要に応じて調整してください）
$lightColor = imagecolorallocate($image, 179, 198, 235);
$darkColor  = imagecolorallocate($image, 90, 126, 173);

// 背景を一括塗りつぶし
imagefilledrectangle($image, 0, 0, $totalWidth - 1, $totalHeight - 1, $bgColor);

// Ridge 風エッジの描画
// 枠の厚みを半分に分割（例：4px → 2px 外側、2px 内側）
$halfThickness = $frameThickness / 2;

// 外側のエッジ（上・左：明るい、下・右：暗い）
for ($i = 0; $i < $halfThickness; $i++) {
    // 上側
    imageline($image, $i, $i, $totalWidth - $i - 1, $i, $lightColor);
    // 左側
    imageline($image, $i, $i, $i, $totalHeight - $i - 1, $lightColor);
    // 下側
    imageline($image, $i, $totalHeight - $i - 1, $totalWidth - $i - 1, $totalHeight - $i - 1, $darkColor);
    // 右側
    imageline($image, $totalWidth - $i - 1, $i, $totalWidth - $i - 1, $totalHeight - $i - 1, $darkColor);
}

// 内側のエッジ（上・左：暗い、下・右：明るい）
for ($i = $halfThickness; $i < $frameThickness; $i++) {
    // 上側
    imageline($image, $i, $i, $totalWidth - $i - 1, $i, $darkColor);
    // 左側
    imageline($image, $i, $i, $i, $totalHeight - $i - 1, $darkColor);
    // 下側
    imageline($image, $i, $totalHeight - $i - 1, $totalWidth - $i - 1, $totalHeight - $i - 1, $lightColor);
    // 右側
    imageline($image, $totalWidth - $i - 1, $i, $totalWidth - $i - 1, $totalHeight - $i - 1, $lightColor);
}

// 数字画像の読み込み
if (!file_exists($digitImage)) {
    // エラー情報を漏洩させない
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}
$digitsImg = imagecreatefromgif($digitImage);


// 各桁の数字を画像から切り出し、描画（枠の内側に配置）
$digits = str_split((string)$counter);
foreach ($digits as $i => $digit) {
    $srcX = $digit * $digitWidth;
    imagecopy($image, $digitsImg, ($i * $digitWidth) + $frameThickness, $frameThickness, $srcX, 0, $digitWidth, $digitHeight);
}

// 画像の出力とリソース解放
imagepng($image);
imagedestroy($image);
imagedestroy($digitsImg);
?>
