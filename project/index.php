<?php
/**
 * نرم‌افزار جامع مدیریت کتابخانه آسمان (نسخه ماژولار PHP + PostgreSQL)
 * نقطه ورود اصلی سامانه (Bootstrap & Router)
 */
define('ASEMAN_ROOT', __DIR__);

// ۱. راه‌اندازی نشست و بررسی زمان بی‌فعالیتی کاربر
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '1800');
session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')));
session_start();
define('IDLE_LIMIT', 600);
$idle_expired = null;
if (isset($_SESSION['u'])) {
    $now_ts = time();
    $cur_idle_lim = isset($_SESSION['idle_lim']) && (int)$_SESSION['idle_lim'] >= 120 ? (int)$_SESSION['idle_lim'] : IDLE_LIMIT;
    if (isset($_SESSION['last']) && ($now_ts - (int)$_SESSION['last']) > $cur_idle_lim) {
        $idle_expired = array('sid' => substr(hash('sha256', session_id()), 0, 20), 'last' => (int)$_SESSION['last']);
        $_SESSION = array();
        session_regenerate_id(true);
    } else {
        $_SESSION['last'] = $now_ts;
    }
}
date_default_timezone_set('Asia/Tehran');

// ۲. بارگذاری تنظیمات، اتصال به پایگاه داده PostgreSQL و هسته مرکزی
require_once ASEMAN_ROOT . '/config/constants.php';
require_once ASEMAN_ROOT . '/config/database.php';
require_once ASEMAN_ROOT . '/includes/license.php';
require_once ASEMAN_ROOT . '/includes/helpers.php';
require_once ASEMAN_ROOT . '/includes/fts_stats_auth.php';
require_once ASEMAN_ROOT . '/includes/chat_ai.php';
require_once ASEMAN_ROOT . '/includes/marc_parser.php';
require_once ASEMAN_ROOT . '/includes/z3950_client.php';
require_once ASEMAN_ROOT . '/includes/auth_guard.php';

// ۳. پردازش خروجی‌های فایل (اکسل، مارک، پشتیبان) و درخواست‌های POST
require_once ASEMAN_ROOT . '/actions/exports.php';
require_once ASEMAN_ROOT . '/actions/post_handlers.php';

// ۴. بارگذاری قالب گرافیکی و باکس‌های پرتال
require_once ASEMAN_ROOT . '/templates/layout.php';
require_once ASEMAN_ROOT . '/templates/portal_boxes.php';

// ۵. ثبت آمار بازدید و مسیریابی به ماژول مربوطه
track_request();
ob_start();

$routes = array(
    'home'      => 'portal.php',
    'mem_login' => 'portal.php',
    'mem_register'=> 'portal.php',
    'my_panel'  => 'member_panel.php',
    'res'       => 'portal.php',
    'news_list' => 'portal.php',
    'page'      => 'portal.php',
    'dash'      => 'dash.php',
    'form'      => 'catalog.php',
    'marc'      => 'catalog.php',
    'org'       => 'catalog.php',
    'rec'       => 'circulation.php',
    'mem'       => 'circulation.php',
    'loan'      => 'circulation.php',
    'imp_exp'   => 'imp_exp.php',
    'authority' => 'authority.php',
    'bulk'      => 'bulk.php',
    'rep'       => 'reports_stats.php',
    'stats'     => 'reports_stats.php',
    'locs'      => 'settings.php',
    'orders'    => 'settings.php',
    'tickets'   => 'settings.php',
    'cms'       => 'settings.php',
    'backup'    => 'settings.php',
    'users'     => 'settings.php',
    'settings'  => 'settings.php'
);

if (!in_array($p, array('home', 'res', 'news_list', 'page', 'marc', 'mem_login', 'mem_register', 'my_panel', 'mem_logout')) && !can($p)) {
    echo '<div class="card"><p class="warn">شما مجوز دسترسی به این بخش را ندارید.</p></div>';
} elseif (isset($routes[$p])) {
    require ASEMAN_ROOT . '/modules/' . $routes[$p];
} else {
    $p = 'home';
    require ASEMAN_ROOT . '/modules/portal.php';
}

shell(isset($AllPerms[$p]) ? $AllPerms[$p] : 'پرتال کتابخانه آسمان', ob_get_clean(), $p, $in);
