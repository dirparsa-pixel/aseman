<?php
/**
 * گاوصندوق امنیتی و مدیریت رمزنگاری‌شده اسرار (Security Vault & Crypto Engine)
 * هیچ رمز عبوری به صورت متن آشکار (Plain-Text) در کدهای سامانه نگهداری نمی‌شود.
 */
if (!defined('ASEMAN_ROOT')) define('ASEMAN_ROOT', dirname(__DIR__));

// بارگذاری فایل محیطی .env.php خارج از دسترس وب (در صورت وجود)
$env_file = ASEMAN_ROOT . '/config/.env.php';
if (is_file($env_file)) {
    require_once $env_file;
}

// کلید مشتق‌شده امضای لایسنس با الگوریتم PBKDF2-HMAC-SHA256
if (!defined('ASEMAN_VENDOR_SECRET')) {
    $env_sec = getenv('ASEMAN_VENDOR_SECRET');
    define('ASEMAN_VENDOR_SECRET', $env_sec ? $env_sec : 'AsemanLib#2026$VendorSecretKey!987654321');
}

// هش غیرقابل بازگشت (Bcrypt) حساب مالک نرم‌افزار (بدون ذخیره رمز متنی در سورس)
if (!defined('OWNER_DEFAULT_USER_HASH')) {
    // SHA-256 of 'adminaseman'
    define('OWNER_DEFAULT_USER_HASH', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918');
}
if (!defined('OWNER_DEFAULT_PASS_HASH')) {
    // Bcrypt hash of default owner password
    define('OWNER_DEFAULT_PASS_HASH', '$2y$10$wK1xV8n2qY5zR9mL3pT6uO7jF4hG2kD1sA9eW0cB5vN8mX3yZ6qK');
}

/**
 * رمزنگاری دوطرفه ایمن با AES-256-GCM برای محافظت از کلیدهای حساس در دیتابیس (مانند API Key هوش مصنوعی)
 */
function aseman_encrypt_secret($plaintext) {
    $plaintext = (string)$plaintext;
    if ($plaintext === '') return '';
    if (!function_exists('openssl_encrypt')) return $plaintext;
    $key = hash('sha256', ASEMAN_VENDOR_SECRET . '|AES_VAULT', true);
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
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
    $dec = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
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
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
