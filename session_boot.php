<?php
// session開始と送信元判定の共通処理。
// CGI/FastCGI環境では .htaccess の php_value が効かないため、session設定はここで行う。

if (!function_exists('hogehoge_is_same_origin')) {
    // 送信元が自ホストかを返す。Origin も Referer も無い場合は null。
    function hogehoge_is_same_origin(): ?bool
    {
        $selfHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if (!is_string($selfHost) || $selfHost === '') {
            return null;
        }

        // POSTでは Origin が送られる。Referer は送信元の設定で欠けることがある
        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if (!is_string($value) || $value === '') {
                continue;
            }

            $sentHost = parse_url($value, PHP_URL_HOST);
            if (!is_string($sentHost) || $sentHost === '') {
                continue;
            }

            return strcasecmp($sentHost, $selfHost) === 0;
        }

        return null;
    }
}

if (!function_exists('hogehoge_session_start')) {
    // session を開始する。失敗しても例外は投げず false を返す。
    function hogehoge_session_start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        // 既定の24分だと、ページを開いたまま離席した間にキリ番の番号が消える
        @ini_set('session.gc_maxlifetime', '10800'); // 3時間
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');

        // HTTP接続で secure を立てるとCookieが保存されず、番号を渡せなくなる
        $https = $_SERVER['HTTPS'] ?? '';
        $isSecure = ($https !== '' && strtolower($https) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $isSecure,
            'samesite' => 'Strict',
        ]);

        return @session_start();
    }
}
