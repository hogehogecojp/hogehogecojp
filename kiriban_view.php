<?php
// セキュリティヘッダーの追加
header("Content-Type: text/html; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN"); // iframe内での表示を同一オリジンのみ許可

$kiriban_file = getenv('HOGEHOGE_KIRIBAN_FILE') ?: __DIR__ . "/kiriban.txt";

// ファイルが存在するかチェック
if (!file_exists($kiriban_file)) {
    echo '<div style="font-family:\'ＭＳ Ｐゴシック\'; font-size:12px; text-align:center; padding:20px;">
            ☆まだ投稿がありません☆<br>
            最初のキリ番ゲッターになってね♪
          </div>';
    exit;
}

// 書き込み中の中途半端な内容を読まないよう共有ロックをかける
$lines = [];
$kiribanHandle = @fopen($kiriban_file, 'r');
if ($kiribanHandle !== false) {
    if (flock($kiribanHandle, LOCK_SH)) {
        $contents = stream_get_contents($kiribanHandle);
        flock($kiribanHandle, LOCK_UN);
        if (is_string($contents) && trim($contents) !== '') {
            $split = preg_split('/\r\n|\r|\n/', rtrim($contents, "\r\n"));
            if ($split !== false) {
                $lines = $split;
            }
        }
    }
    fclose($kiribanHandle);
}

if (empty($lines)) {
    echo '<div style="font-family:\'ＭＳ Ｐゴシック\'; font-size:12px; text-align:center; padding:20px;">
            ☆まだ投稿がありません☆<br>
            最初のキリ番ゲッターになってね♪
          </div>';
    exit;
}

// HTMLとして出力（レトロスタイル維持）
echo '<div style="font-family:\'ＭＳ Ｐゴシック\'; font-size:12px; line-height:1.6; overflow-y:auto; overflow-x:hidden; height:300px; padding:5px; word-wrap:break-word; word-break:break-all;">';

foreach ($lines as $line) {
    $line = trim($line);
    if ($line !== '') {
        // HTMLエスケープ（既にされているはずだが念のため）
        $escaped_line = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        echo $escaped_line . '<br>';
    }
}

echo '</div>';
?>
