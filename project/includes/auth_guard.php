<?php
// مدیریت نشست کاربری، سطوح دسترسی، ورود/خروج و درخواست‌های AJAX
if (!defined('ASEMAN_ROOT')) die('Access Denied');

$in = isset($_SESSION['u']);
$p = isset($_GET['p']) ? $_GET['p'] : ($in ? 'dash' : 'home');
if ($p === 'nlai') $p = 'imp_exp';
if ($idle_expired) {
    sess_end_sid($idle_expired['sid'], 'idle', $idle_expired['last']);
    if (!in_array($p, array('home', 'news_list', 'page', 'login', 'res', 'add_order', 'chat_api', 'mem_login', 'mem_register', 'do_mem_login', 'do_mem_register', 'my_panel'))) { header('Location: ?p=home&timeout=1'); exit; }
}
if (!isset($_SESSION['t'])) {
    $_SESSION['t'] = bin2hex(random_bytes(16));
}
$tok = $_SESSION['t'];
$post = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($post && !hash_equals($tok, isset($_POST['t']) ? $_POST['t'] : '')) {
    http_response_code(400);
    exit('نشست نامعتبر');
}

if ($in) {
    $st_cu = $db->prepare("SELECT * FROM users WHERE u=?");
    $st_cu->execute(array($_SESSION['u']));
    $cu = $st_cu->fetch(PDO::FETCH_ASSOC);
    if ($cu) {
        $is_super = ($cu['u'] === 'admin' || $cu['u'] === 'admin1');
        $_SESSION['perms'] = ($is_super || $cu['perms'] === 'all' || empty($cu['perms'])) ? array_keys($AllPerms) : norm_perms(explode(',', $cu['perms']));
        $_SESSION['uloc']  = $is_super ? 'all' : ($cu['uloc'] ? $cu['uloc'] : 'all');
    }
}

function is_super_admin() {
    return isset($_SESSION['u']) && ($_SESSION['u'] === 'admin' || $_SESSION['u'] === 'admin1');
}
function user_loc() {
    if (is_super_admin()) return 'all';
    return isset($_SESSION['uloc']) && $_SESSION['uloc'] !== '' ? $_SESSION['uloc'] : 'all';
}
function is_loc_restricted() {
    return user_loc() !== 'all';
}
function allowed_locations() {
    return is_loc_restricted() ? array(user_loc()) : get_locations();
}
function is_module_licensed($perm) {
    if ($perm === 'dash') return true;
    $lic = get_license();
    $k = 'mod_' . $perm;
    if (isset($lic[$k]) && empty($lic[$k])) return false;
    return true;
}
function can($perm) {
    if (!isset($_SESSION['u'])) return false;
    if (!is_module_licensed($perm)) return false;
    if ($perm === 'bulk') return is_super_admin();
    if (is_super_admin()) return true;
    return in_array($perm, isset($_SESSION['perms']) ? $_SESSION['perms'] : array());
}

function check_login_rate_limit() {
    global $db;
    $ip_h = hash('sha256', client_ip() . '|login_guard');
    $st = $db->prepare("SELECT tries, locked_until FROM login_attempts WHERE ip_hash=?");
    $st->execute(array($ip_h));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)$row['locked_until'] > time()) {
        $rem = ceil(((int)$row['locked_until'] - time()) / 60);
        die('<div dir="rtl" style="font-family:Tahoma,sans-serif;padding:20px;background:#fff1f2;color:#be123c;border:2px solid #fecdd3;border-radius:12px;max-width:480px;margin:50px auto;text-align:center">به‌دلیل تلاش‌های ناموفق متوالی، دسترسی شما به مدت ' . tr_num($rem) . ' دقیقه مسدود شده است.</div>');
    }
}

function record_login_failure() {
    global $db;
    $lic = get_license();
    $max_try = max(3, (int)($lic['max_login_try'] ?? 5));
    $ip_h = hash('sha256', client_ip() . '|login_guard');
    $db->prepare("INSERT INTO login_attempts(ip_hash, tries, locked_until) VALUES(?, 1, 0) ON CONFLICT (ip_hash) DO UPDATE SET tries = login_attempts.tries + 1")->execute(array($ip_h));
    $st = $db->prepare("SELECT tries FROM login_attempts WHERE ip_hash=?");
    $st->execute(array($ip_h));
    if ((int)$st->fetchColumn() >= $max_try) {
        $db->prepare("UPDATE login_attempts SET locked_until=?, tries=0 WHERE ip_hash=?")->execute(array(time() + 900, $ip_h));
    }
}

function clear_login_failures() {
    global $db;
    $ip_h = hash('sha256', client_ip() . '|login_guard');
    $db->prepare("DELETE FROM login_attempts WHERE ip_hash=?")->execute(array($ip_h));
}

// ورود، خروج و سفارش کتاب از پرتال
if ($p === 'login' && $post) {
    check_login_rate_limit();
    $s = $db->prepare("SELECT * FROM users WHERE u=?");
    $s->execute(array(trim(isset($_POST['u']) ? $_POST['u'] : '')));
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify(isset($_POST['pw']) ? $_POST['pw'] : '', $u['pw'])) {
        clear_login_failures();
        session_regenerate_id(true);
        $_SESSION['u'] = $u['u'];
        $is_sup = ($u['u'] === 'admin' || $u['u'] === 'admin1');
        $_SESSION['perms'] = ($is_sup || $u['perms'] === 'all' || empty($u['perms'])) ? array_keys($AllPerms) : norm_perms(explode(',', $u['perms']));
        $_SESSION['uloc'] = $is_sup ? 'all' : ($u['uloc'] ? $u['uloc'] : 'all');
        $_SESSION['t'] = bin2hex(random_bytes(16));
        $_SESSION['last'] = time();
        sess_log_start();
        header('Location: ?p=dash');
        exit;
    }
    record_login_failure();
    $err = 'نام کاربری یا گذرواژه نادرست است.';
    $p = 'home';
}
if ($p === 'add_order' && $post) {
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $author = trim(isset($_POST['author']) ? $_POST['author'] : '');
    $pub = trim(isset($_POST['pub']) ? $_POST['pub'] : '');
    $isbn = trim(isset($_POST['isbn']) ? $_POST['isbn'] : '');
    $req = trim(isset($_POST['requester']) ? $_POST['requester'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    $loc = trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
    if ($title !== '') {
        $db->prepare("INSERT INTO orders(title,author,pub,isbn,requester,phone,loc,status) VALUES(?,?,?,?,?,?,?,'pending')")->execute(array($title, $author, $pub, $isbn, $req, $phone, $loc));
    }
    header('Location: ?p=home&order_ok=1');
    exit;
}
if ($p === 'ping') {
    header('Content-Type: application/json');
    echo '{"ok":' . (isset($_SESSION['u']) ? 1 : 0) . '}';
    exit;
}
if ($p === 'ac' && isset($_SESSION['u']) && (can('form') || can('authority'))) {
    header('Content-Type: application/json; charset=utf-8');
    $af = isset($_GET['f']) ? $_GET['f'] : '';
    echo json_encode(in_array($af, array('f700', 'f710', 'f200f', 'f606')) ? auth_suggest($af, isset($_GET['q']) ? $_GET['q'] : '') : array(), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($p === 'chat_api' && $post) {
    header('Content-Type: application/json; charset=utf-8');
    $lic_c = get_license();
    $cmode = empty($lic_c['mod_chat']) ? 'off' : get_cms('chat_mode');
    if ($cmode !== 'plain' && $cmode !== 'ai') { echo json_encode(array('text' => 'گفتگو در حال حاضر غیرفعال است.', 'items' => array())); exit; }
    $q = trim(mb_substr(isset($_POST['q']) ? $_POST['q'] : '', 0, 300, 'UTF-8'));
    $vh = visitor_hash();
    $lim = max(3, (int)chat_cfg('chat_limit', '15'));
    $cst = $db->prepare("SELECT COUNT(*) FROM chat_log WHERE vh=? AND ts>?");
    $cst->execute(array($vh, time() - 600));
    if ($q === '') { echo json_encode(array('text' => '', 'items' => array())); exit; }
    if ((int)$cst->fetchColumn() >= $lim) { echo json_encode(array('text' => 'تعداد پیام‌های شما در این بازه زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 'items' => array())); exit; }
    $rows = chat_retrieve($q, max(1, min(8, (int)chat_cfg('chat_max', '5'))));
    $items = chat_items($rows);
    $used = 'plain';
    $text = '';
    if ($cmode === 'ai' && chat_key() !== '') {
        $dst = $db->prepare("SELECT COUNT(*) FROM chat_log WHERE mode='ai' AND ts>?");
        $dst->execute(array(strtotime(date('Y-m-d'))));
        if ((int)$dst->fetchColumn() < (int)chat_cfg('chat_daily', '300')) {
            $hist = array();
            $hj = json_decode(isset($_POST['h']) ? $_POST['h'] : '[]', true);
            if (is_array($hj)) foreach (array_slice($hj, -6) as $hm) if (isset($hm['r'], $hm['t']) && is_string($hm['t'])) $hist[] = array('r' => $hm['r'] === 'a' ? 'a' : 'u', 't' => mb_substr($hm['t'], 0, 400, 'UTF-8'));
            $aerr = '';
            $text = chat_ai($q, $hist, $rows, $aerr);
            if ($text !== '') $used = 'ai';
        }
    }
    if ($used === 'plain') $text = chat_plain($q, $rows, $items);
    $db->prepare("INSERT INTO chat_log(ts,vh,q,mode,nres) VALUES(?,?,?,?,?)")->execute(array(time(), $vh, $q, $used, count($items)));
    echo json_encode(array('text' => $text, 'items' => $items, 'mode' => $used), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($p === 'mem_logout') {
    unset($_SESSION['mid'], $_SESSION['mname']);
    header('Location: ?p=home');
    exit;
}
if ($p === 'cancel_iso_preview' || isset($_GET['cancel_preview'])) {
    unset($_SESSION['iso_preview'], $_SESSION['iso_loc'], $_SESSION['iso_from']);
    $fp = isset($_GET['from']) && preg_match('/^[a-z_]+$/', $_GET['from']) ? $_GET['from'] : 'imp_exp';
    header('Location: ?p=' . $fp);
    exit;
}
if ($p === 'logout') {
    if (isset($_SESSION['u'])) sess_end_sid(my_sid(), 'logout');
    session_destroy();
    header('Location: ?p=home');
    exit;
}
if (!$in && !in_array($p, array('home', 'news_list', 'page', 'login', 'add_order', 'res', 'mem_login', 'mem_register', 'do_mem_login', 'do_mem_register', 'my_panel', 'mem_ticket', 'mem_profile', 'mem_logout', 'reserve_book', 'cancel_res', 'mem_renew'))) {
    header('Location: ?p=home');
    exit;
}
