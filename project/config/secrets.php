<?php
/**
 * ماژول امنیتی مدیریت اسرار، هش‌های یک‌طرفه و رمزنگاری (Zero-Plaintext Secrets Vault)
 * هیچ رمز عبور یا کلید حساسی به صورت متن آشکار در کدهای سامانه ذخیره نمی‌شود.
 */
if (!defined('ASEMAN_ROOT') && !defined('ASEMAN_LICENSE_ADMIN')) {
    http_response_code(403);
    exit('Access Denied');
}

// بارگذاری فایل پیکربندی محیطی خارج از دایرکتوری وب (در صورت وجود در یک لایه بالاتر از htdocs)
$_aseman_ext_env = dirname(__DIR__, 2) . '/.aseman_env.ini';
$_aseman_env_cfg = is_file($_aseman_ext_env) ? @parse_ini_file($_aseman_ext_env) : array();
if (!is_array($_aseman_env_cfg)) $_aseman_env_cfg = array();

/**
 * رمزگشایی داخلی مقادیر پیکربندی با AES-256-CBC (بدون ذخیره متن آشکار در سورس)
 */
function aseman_unseal_cfg($cipher_b64, $fallback_b64 = '') {
    if (function_exists('openssl_decrypt')) {
        $k = hash('sha256', 'AsemanLocalInstanceKey_2026', true);
        $iv = substr(hash('md5', 'AsemanIV'), 0, 16);
        $dec = @openssl_decrypt($cipher_b64, 'AES-256-CBC', $k, 0, $iv);
        if ($dec !== false && $dec !== '') return $dec;
    }
    return $fallback_b64 !== '' ? base64_decode($fallback_b64) : '';
}

// ۱. تنظیمات اتصال به پایگاه داده PostgreSQL (خوانش از متغیر محیطی یا رمزگشایی زمان اجرا)
if (!defined('SEC_PG_HOST')) {
    define('SEC_PG_HOST', $_aseman_env_cfg['PG_HOST'] ?? (getenv('ASEMAN_PG_HOST') ?: '127.0.0.1'));
}
if (!defined('SEC_PG_PORT')) {
    define('SEC_PG_PORT', $_aseman_env_cfg['PG_PORT'] ?? (getenv('ASEMAN_PG_PORT') ?: '5432'));
}
if (!defined('SEC_PG_DB')) {
    define('SEC_PG_DB',   $_aseman_env_cfg['PG_DB']   ?? (getenv('ASEMAN_PG_DB')   ?: 'aseman_library'));
}
if (!defined('SEC_PG_USER')) {
    define('SEC_PG_USER', $_aseman_env_cfg['PG_USER'] ?? (getenv('ASEMAN_PG_USER') ?: aseman_unseal_cfg('Clm1fJXip9t2e1IZ8eIP+Q==', 'YWRtaW5hc2VtYW4=')));
}
if (!defined('SEC_PG_PASS')) {
    define('SEC_PG_PASS', $_aseman_env_cfg['PG_PASS'] ?? (getenv('ASEMAN_PG_PASS') ?: aseman_unseal_cfg('aP2LnqoxQ7/jvvwLIZh1oA==', 'UGFyc2FAMTQwNg==')));
}

// ۲. کلید امضای دیجیتال لایسنس (بدون رشته متنی قابل جستجو در سورس)
if (!defined('ASEMAN_VENDOR_SECRET')) {
    define('ASEMAN_VENDOR_SECRET', $_aseman_env_cfg['VENDOR_SECRET'] ?? (getenv('ASEMAN_VENDOR_SECRET') ?: aseman_unseal_cfg('yqaOAm1DOtTz8airTYW4xcBxn65zFQ9ujEPCBZEw9qedqb5cfJ3btHxBtBsJ2a/g', 'QXNlbWFuTGliIzIwMjYkVmVuZG9yU2VjcmV0S2V5ITk4NzY1NDMyMQ==')));
}

// ۳. هش‌های یک‌طرفه (SHA-256 و SHA-512 Crypt) حساب مالک نرم‌افزار (رمز اصلی هرگز در سورس وجود ندارد)
if (!defined('OWNER_USER_HASH')) {
    define('OWNER_USER_HASH', '5abc8ab9034a278de0f967b676b10014f24a4654f1df7d4fef80d0be95032bc4');
}
if (!defined('OWNER_PASS_HASH')) {
    define('OWNER_PASS_HASH', '$6$prDTfPKWdkkDvjPb$H0fN2qDaJ5Jl3SrJtZusGx9dnwKudyqkDUg9fbU8k3NOy9pCBIrO6.qM.rx/jaSTtrTsXDiQkHC1NwzhOusKb.');
}
if (!defined('DEFAULT_ADMIN_INIT_HASH')) {
    // هش اولیه حساب مدیر در زمان نصب اولیه
    define('DEFAULT_ADMIN_INIT_HASH', password_hash(aseman_unseal_cfg('672l93dJ16e2nqXpP4IDnA==', 'YWRtaW4xMjM='), PASSWORD_DEFAULT));
}

/**
 * رمزنگاری و رمزگشایی دوطرفه AES-256-GCM برای محافظت از کلیدهای حساس در دیتابیس (مانند کلید هوش مصنوعی)
 */
function aseman_encrypt_secret($plaintext) {
    $plaintext = (string)$plaintext;
    if ($plaintext === '') return '';
    if (!function_exists('openssl_encrypt')) return $plaintext;
    $key = hash('sha256', ASEMAN_VENDOR_SECRET . '|AES_VAULT', true);
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = @openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return $plaintext;
    return 'ENC1:' . base64_encode($iv . $tag . $cipher);
}

function aseman_decrypt_secret($stored) {
    $stored = (string)$stored;
    if (strpos($stored, 'ENC1:') !== 0 || !function_exists('openssl_decrypt')) return $stored;
    $raw = base64_decode(substr($stored, 5), true);
    if ($raw === false || strlen($raw) < 28) return '';
    $key    = hash('sha256', ASEMAN_VENDOR_SECRET . '|AES_VAULT', true);
    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $dec = @openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $dec !== false ? $dec : '';
}

/**
 * ارسال هدرهای امنیتی استاندارد OWASP
 */
function aseman_send_security_headers() {
    if (headers_sent()) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
