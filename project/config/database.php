<?php
// اتصال به پایگاه داده PostgreSQL و ایجاد جداول پایه
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ==================== بارگذاری گاوصندوق امنیتی و اتصال به PostgreSQL ====================
require_once ASEMAN_ROOT . '/config/secrets.php';
aseman_send_security_headers();

define('PG_HOST', SEC_PG_HOST);
define('PG_PORT', SEC_PG_PORT);
define('PG_DB',   SEC_PG_DB);
define('PG_USER', SEC_PG_USER);
define('PG_PASS', SEC_PG_PASS);

try {
    $dsn = "pgsql:host=" . PG_HOST . ";port=" . PG_PORT . ";dbname=" . PG_DB . ";options='--client_encoding=UTF8'";
    $db = new PDO($dsn, PG_USER, PG_PASS, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ));
} catch (PDOException $e) {
    error_log('Aseman DB Connection Error: ' . $e->getMessage());
    die('<div dir="rtl" style="font-family:Tahoma,sans-serif;padding:24px;background:#fff1f2;color:#9f1239;border:2px solid #fecdd3;border-radius:16px;max-width:620px;margin:40px auto;line-height:1.9;text-align:center">
        <h3 style="margin-top:0">خطا در برقراری ارتباط با پایگاه داده</h3>
        <p>سرویس پایگاه داده موقتاً در دسترس نیست. لطفاً از فعال بودن سرویس PostgreSQL اطمینان حاصل کنید.</p>
    </div>');
}

// تابع کمکی تبدیل ایمن شماره ثبت به عدد در PostgreSQL
function sql_reg_int($col = 'reg_no') {
    return "COALESCE(NULLIF(regexp_replace($col, '[^0-9]', '', 'g'), '')::bigint, 0)";
}

// ۱. ایجاد جداول پایه و پیشرفته در PostgreSQL
$db->exec("
CREATE TABLE IF NOT EXISTS users(id SERIAL PRIMARY KEY,u TEXT UNIQUE,pw TEXT,perms TEXT DEFAULT 'all',uloc TEXT DEFAULT 'all');
CREATE TABLE IF NOT EXISTS records(id SERIAL PRIMARY KEY,reg_no TEXT,loc TEXT DEFAULT 'مخزن اصلی',rtype TEXT DEFAULT 'book',file TEXT, file_access TEXT DEFAULT 'public',f010 TEXT,f101 TEXT DEFAULT 'per',f200a TEXT,f200e TEXT,f200f TEXT,f205 TEXT,f210a TEXT,f210c TEXT,f210d TEXT,f215 TEXT,f225 TEXT,f300 TEXT,f606 TEXT,f676 TEXT,f686 TEXT,f700 TEXT,f710 TEXT,copies INTEGER DEFAULT 1,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS members(id SERIAL PRIMARY KEY,name TEXT,phone TEXT,mcode TEXT,loc TEXT DEFAULT 'مخزن اصلی');
CREATE TABLE IF NOT EXISTS reservations(id SERIAL PRIMARY KEY, rid INT, mid INT, loc TEXT, status TEXT DEFAULT 'pending', created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS loans(id SERIAL PRIMARY KEY,rid INT,mid INT,d_out TEXT,due TEXT,ret TEXT);
CREATE TABLE IF NOT EXISTS cms(k TEXT PRIMARY KEY,v TEXT);
CREATE TABLE IF NOT EXISTS gallery(id SERIAL PRIMARY KEY,title TEXT,img TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders(id SERIAL PRIMARY KEY,title TEXT,author TEXT,pub TEXT,isbn TEXT,requester TEXT,phone TEXT,loc TEXT,status TEXT DEFAULT 'pending',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS tickets(id SERIAL PRIMARY KEY,sender TEXT,uloc TEXT,target TEXT,subj TEXT,msg TEXT,reply TEXT,status TEXT DEFAULT 'open',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS pages(
    id SERIAL PRIMARY KEY,
    slug TEXT UNIQUE,
    title TEXT,
    body TEXT,
    active INTEGER DEFAULT 1,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS portal_menus(
    id SERIAL PRIMARY KEY,
    parent_id INTEGER DEFAULT 0,
    pos TEXT DEFAULT 'footer',
    title TEXT,
    target_type TEXT DEFAULT 'url',
    target_val TEXT DEFAULT '',
    ord INTEGER DEFAULT 10,
    active INTEGER DEFAULT 1
);
CREATE TABLE IF NOT EXISTS login_attempts(ip_hash TEXT PRIMARY KEY, tries INT DEFAULT 0, locked_until BIGINT DEFAULT 0);
CREATE TABLE IF NOT EXISTS portal_boxes(id SERIAL PRIMARY KEY,bkey TEXT UNIQUE,title TEXT,btype TEXT DEFAULT 'custom',content TEXT,pos TEXT DEFAULT 'main',ord INTEGER DEFAULT 10,active INTEGER DEFAULT 1);
");

// ۲. ارتقای خودکار دیتابیس قبلی بدون خطا در PostgreSQL
$alter_queries = array(
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS perms TEXT DEFAULT 'all'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS uloc TEXT DEFAULT 'all'",
    "ALTER TABLE records ADD COLUMN IF NOT EXISTS rtype TEXT DEFAULT 'book'",
    "ALTER TABLE records ADD COLUMN IF NOT EXISTS file TEXT",
    "ALTER TABLE records ADD COLUMN IF NOT EXISTS file_access TEXT DEFAULT 'public'",
    "ALTER TABLE records ADD COLUMN IF NOT EXISTS reg_no TEXT",
    "ALTER TABLE records ADD COLUMN IF NOT EXISTS loc TEXT DEFAULT 'مخزن اصلی'",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS mcode TEXT",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS u TEXT",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS pw TEXT",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS loc TEXT DEFAULT 'مخزن اصلی'",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active'",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS father TEXT DEFAULT ''",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS bdate TEXT DEFAULT ''",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS exp_date TEXT DEFAULT ''",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS photo TEXT DEFAULT ''",
    "ALTER TABLE members ADD COLUMN IF NOT EXISTS created TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE reservations ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP",
    "DELETE FROM reservations WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP",

);
foreach ($alter_queries as $q_alt) {
    try { $db->exec($q_alt); } catch (PDOException $e) {}
}

// ۳. ساخت ایندکس‌ها و مقداردهی اولیه
$db->exec("
CREATE INDEX IF NOT EXISTS i_t ON records(f200a);
CREATE INDEX IF NOT EXISTS i_a ON records(f700);
CREATE INDEX IF NOT EXISTS i_i ON records(f010);
CREATE INDEX IF NOT EXISTS i_r ON records(reg_no);
CREATE INDEX IF NOT EXISTS i_l ON records(loc);
UPDATE records SET loc = 'مخزن اصلی' WHERE loc IS NULL OR loc = '';
UPDATE members SET loc = 'مخزن اصلی' WHERE loc IS NULL OR loc = '';
UPDATE members SET status = 'active' WHERE status IS NULL OR status = '';
UPDATE users SET uloc = 'all' WHERE uloc IS NULL OR uloc = '';
");

// ۳-ب. مدل جدید: رکورد عنوان + مجلدها (items) + سریال رکورد هر محل (loc_rec) + اخبار
$db->exec("
CREATE TABLE IF NOT EXISTS items(id SERIAL PRIMARY KEY,rid INT,loc TEXT,reg_no TEXT,vol TEXT DEFAULT '',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS loc_rec(rid INT,loc TEXT,rec_no INTEGER,PRIMARY KEY(rid,loc));
CREATE TABLE IF NOT EXISTS news(id SERIAL PRIMARY KEY,title TEXT,subtitle TEXT,body TEXT,img TEXT,pub TEXT,active INTEGER DEFAULT 1,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS it_r ON items(rid);
CREATE UNIQUE INDEX IF NOT EXISTS it_lr ON items(loc,reg_no);
CREATE INDEX IF NOT EXISTS lc_l ON loc_rec(loc,rec_no);
CREATE INDEX IF NOT EXISTS ln_r ON loans(rid,ret);
CREATE INDEX IF NOT EXISTS ln_m ON loans(mid,ret);
CREATE INDEX IF NOT EXISTS i_ty ON records(rtype);
");

$up_dir = ASEMAN_ROOT . '/uploads';
if (!is_dir($up_dir)) {
    @mkdir($up_dir, 0755, true);
}
if (!is_file($up_dir . '/.htaccess')) {
    $ht_rules = "Options -Indexes -ExecCGI\n"
              . "php_flag engine off\n"
              . "AddType text/plain .php .php3 .php4 .php5 .phtml .phar .pl .py .jsp .asp .htm .html .shtml .sh .cgi .svg\n"
              . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|pl|py|cgi|sh|exe|bat|cmd|svg|htaccess)\$\">\n"
              . "    Require all denied\n"
              . "</FilesMatch>\n";
    @file_put_contents($up_dir . '/.htaccess', $ht_rules);
}
if (!is_file($up_dir . '/index.html')) {
    @file_put_contents($up_dir . '/index.html', '');
}

// ۴. ایجاد کاربران پیش‌فرض: admin (مدیر کتابخانه) و admin1 (مدیر فنی) با هش امن از secrets.php
$st_ins_u = $db->prepare("INSERT INTO users(u,pw,perms,uloc) VALUES(?,?,?,?) ON CONFLICT (u) DO NOTHING");
$st_ins_u->execute(array('admin', DEFAULT_ADMIN_INIT_HASH, 'all', 'all'));
$st_ins_u->execute(array('admin1', DEFAULT_ADMIN_INIT_HASH, 'all', 'all'));

// ۵. مقادیر پیش‌فرض مدیریت پرتال و کتابخانه‌ها همراه با پیش‌شماره (۱۰، ۱۱، ۱۲ و...)
$ins_cms = $db->prepare("INSERT INTO cms(k,v) VALUES(?,?) ON CONFLICT (k) DO NOTHING");
foreach ($cms_defaults as $k => $v) {
    $ins_cms->execute(array($k, $v));
}
$ins_box = $db->prepare("INSERT INTO portal_boxes(bkey,title,btype,content,pos,ord,active) VALUES(?,?,?,?,?,?,?) ON CONFLICT (bkey) DO NOTHING");
foreach ($default_boxes as $dbx) {
    $ins_box->execute($dbx);
}
$db->exec("UPDATE portal_boxes SET pos='side', ord=3 WHERE bkey='gallery' AND btype='system'");

function get_cms($k) {
    global $db, $cms_defaults;
    $s = $db->prepare("SELECT v FROM cms WHERE k=?");
    $s->execute(array($k));
    $r = $s->fetchColumn();
    return ($r !== false && $r !== null) ? $r : (isset($cms_defaults[$k]) ? $cms_defaults[$k] : '');
}
function set_cms($k, $v) {
    global $db;
    $db->prepare("INSERT INTO cms(k,v) VALUES(?,?) ON CONFLICT (k) DO UPDATE SET v=EXCLUDED.v")->execute(array($k, $v));
}
