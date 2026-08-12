<?php
// セキュリティヘッダーの追加
header("Content-Type: text/html; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN"); // iframe内での表示を同一オリジンのみ許可
// 投稿を出力する画面。scriptも外部リソースも使わないため全面的に禁止する
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");

$kiriban_file = getenv('HOGEHOGE_KIRIBAN_FILE') ?: __DIR__ . "/kiriban.txt";

// 表示量の上限。投稿が増えてもページの表示量を一定に保つ
$recentBytes = 100000; // 新しい投稿を表示するバイト数
$oldestCount = 100;   // 常に表示する最初期の投稿数
$headerCount = 2;     // 先頭の固定行。新しい投稿はこの下に挿入される

// 1行ずつエスケープして出力する
function kiriban_render(array $lines): void
{
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            // HTMLエスケープ（既にされているはずだが念のため）
            echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '<br>';
        }
    }
}

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

// 先頭の固定行と投稿を分ける。空行はここで除いて件数を数えやすくする
$headerLines = array_slice($lines, 0, $headerCount);
$postLines = array_values(array_filter(
    array_slice($lines, $headerCount),
    static fn($line) => trim($line) !== ''
));

// ファイルは新しい順なので、上から $recentBytes 分を取る
$recentLines = [];
$usedBytes = 0;
foreach ($postLines as $line) {
    $usedBytes += strlen($line) + 1;
    if ($usedBytes > $recentBytes && $recentLines !== []) {
        break;
    }
    $recentLines[] = $line;
}

// 最初期の投稿はファイルの末尾にある。0件指定を負のoffsetにしない
$oldestLines = $oldestCount > 0 ? array_slice($postLines, -$oldestCount) : [];

// 両者が繋がっているなら省略せずすべて表示する
$omittedCount = count($postLines) - count($recentLines) - count($oldestLines);
if ($omittedCount < 1) {
    $recentLines = $postLines;
    $oldestLines = [];
    $omittedCount = 0;
}

// HTMLとして出力（レトロスタイル維持）
echo '<div style="font-family:\'ＭＳ Ｐゴシック\'; font-size:12px; line-height:1.6; overflow-y:auto; overflow-x:hidden; height:300px; padding:5px; word-wrap:break-word; word-break:break-all;">';

kiriban_render($headerLines);
kiriban_render($recentLines);

if ($omittedCount > 0) {
    echo '<div style="text-align:center; color:#cc00cc; margin:8px 0;">';
    echo '☆彡 たくさんの書き込みありがとう！<br>';
    echo '途中の' . $omittedCount . '行は管理人が保管して非表示になりました ☆彡';
    echo '</div>';
}

kiriban_render($oldestLines);

echo '</div>';
?>
