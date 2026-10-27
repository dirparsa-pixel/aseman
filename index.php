<?php
// نرم‌افزار کتابخانه‌ای آسمان — PHP + SQLite — فایل واحد (نسخه جامع ریسپانسیو موبایل، پیش‌شماره ثبت اختصاصی مراکز و پرتال مدرن)
session_start();
date_default_timezone_set('Asia/Tehran');
$db = new PDO('sqlite:' . __DIR__ . '/library.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ۱. ایجاد جداول پایه و پیشرفته
$db->exec("PRAGMA journal_mode=WAL;
CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,u TEXT UNIQUE,pw TEXT,perms TEXT DEFAULT 'all',uloc TEXT DEFAULT 'all');
CREATE TABLE IF NOT EXISTS records(id INTEGER PRIMARY KEY,reg_no TEXT,loc TEXT DEFAULT 'مخزن اصلی',rtype TEXT DEFAULT 'book',file TEXT,f010 TEXT,f101 TEXT DEFAULT 'per',f200a TEXT,f200e TEXT,f200f TEXT,f205 TEXT,f210a TEXT,f210c TEXT,f210d TEXT,f215 TEXT,f225 TEXT,f300 TEXT,f606 TEXT,f676 TEXT,f686 TEXT,f700 TEXT,f710 TEXT,copies INTEGER DEFAULT 1,created TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS members(id INTEGER PRIMARY KEY,name TEXT,phone TEXT,mcode TEXT,loc TEXT DEFAULT 'مخزن اصلی');
CREATE TABLE IF NOT EXISTS loans(id INTEGER PRIMARY KEY,rid INT,mid INT,d_out TEXT,due TEXT,ret TEXT);
CREATE TABLE IF NOT EXISTS cms(k TEXT PRIMARY KEY,v TEXT);
CREATE TABLE IF NOT EXISTS gallery(id INTEGER PRIMARY KEY,title TEXT,img TEXT,created TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY,title TEXT,author TEXT,pub TEXT,isbn TEXT,requester TEXT,phone TEXT,loc TEXT,status TEXT DEFAULT 'pending',created TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS tickets(id INTEGER PRIMARY KEY,sender TEXT,uloc TEXT,target TEXT,subj TEXT,msg TEXT,reply TEXT,status TEXT DEFAULT 'open',created TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS portal_boxes(id INTEGER PRIMARY KEY,bkey TEXT UNIQUE,title TEXT,btype TEXT DEFAULT 'custom',content TEXT,pos TEXT DEFAULT 'main',ord INTEGER DEFAULT 10,active INTEGER DEFAULT 1);");

// ۲. ارتقای خودکار دیتابیس قبلی بدون خطا
$alter_queries = array(
    "ALTER TABLE users ADD COLUMN perms TEXT DEFAULT 'all'",
    "ALTER TABLE users ADD COLUMN uloc TEXT DEFAULT 'all'",
    "ALTER TABLE records ADD COLUMN rtype TEXT DEFAULT 'book'",
    "ALTER TABLE records ADD COLUMN file TEXT",
    "ALTER TABLE records ADD COLUMN reg_no TEXT",
    "ALTER TABLE records ADD COLUMN loc TEXT DEFAULT 'مخزن اصلی'",
    "ALTER TABLE members ADD COLUMN mcode TEXT",
    "ALTER TABLE members ADD COLUMN loc TEXT DEFAULT 'مخزن اصلی'"
);
foreach ($alter_queries as $q_alt) {
    try { $db->exec($q_alt); } catch (PDOException $e) {}
}

// ۳. ساخت ایندکس‌ها و مقداردهی اولیه
$db->exec("CREATE INDEX IF NOT EXISTS i_t ON records(f200a);
CREATE INDEX IF NOT EXISTS i_a ON records(f700);
CREATE INDEX IF NOT EXISTS i_i ON records(f010);
CREATE INDEX IF NOT EXISTS i_r ON records(reg_no);
CREATE INDEX IF NOT EXISTS i_l ON records(loc);
UPDATE records SET loc = 'مخزن اصلی' WHERE loc IS NULL OR loc = '';
UPDATE members SET loc = 'مخزن اصلی' WHERE loc IS NULL OR loc = '';
UPDATE users SET uloc = 'all' WHERE uloc IS NULL OR uloc = '';");

if (!is_dir(__DIR__ . '/uploads')) {
    @mkdir(__DIR__ . '/uploads', 0755, true);
}

// ۴. ایجاد کاربران پیش‌فرض: admin (مدیر کتابخانه) و admin1 (مدیر فنی)
$st_chk = $db->prepare("SELECT COUNT(*) FROM users WHERE u=?");
$st_ins_u = $db->prepare("INSERT INTO users(u,pw,perms,uloc) VALUES(?,?,?,?)");
$st_chk->execute(array('admin'));
if ((int)$st_chk->fetchColumn() === 0) {
    $st_ins_u->execute(array('admin', password_hash('admin123', PASSWORD_DEFAULT), 'all', 'all'));
}
$st_chk->execute(array('admin1'));
if ((int)$st_chk->fetchColumn() === 0) {
    $st_ins_u->execute(array('admin1', password_hash('admin123', PASSWORD_DEFAULT), 'all', 'all'));
}

// ۵. مقادیر پیش‌فرض مدیریت پرتال و محل‌های نگهداری همراه با پیش‌شماره (۱۰، ۱۱، ۱۲ و...)
$cms_defaults = array(
    'site_title'   => 'کتابخانه عمومی آسمان',
    'site_sub'     => 'پرتال جامع اطلاع‌رسانی، جستجوی منابع (OPAC) و خدمات هوشمند کتابخانه‌ای',
    'font'         => 'Vazirmatn',
    'theme'        => 'royal_dark',
    'services'     => "امانت کتاب، مجلات و منابع دیجیتال\nسالن‌های مطالعه مجزا و آرام\nبخش کودک و نوجوان و نشست‌های کتاب‌خوان\nسفارش و پیشنهاد خرید منابع جدید توسط اعضا\nدسترسی به پایگاه‌های اطلاعاتی و پایان‌نامه‌ها",
    'news'         => "آغاز ثبت‌نام رایگان عضویت کتابخانه به مناسبت هفته کتاب | ۱۴۰۵/۰۸/۲۴ | به اطلاع کلیه علاقه‌مندان و پژوهشگران گرامی می‌رساند به مناسبت فرارسیدن هفته کتاب و کتابخوانی، ثبت‌نام و صدور کارت عضویت در تمامی بخش‌های کتابخانه آسمان به صورت کاملاً رایگان انجام می‌پذیرد.\nراه‌اندازی بخش سفارش آنلاین منابع و کتاب برای تمامی بخش‌ها | ۱۴۰۵/۰۸/۱۰ | اعضای محترم کتابخانه از این پس می‌توانند کتاب‌ها و منابع درخواستی خود را از طریق فرم سفارش منبع در صفحه اول پرتال ثبت نمایند تا در اولویت خرید بخش مربوطه قرار گیرد.\nتازه‌های نشر: بیش از ۲۰۰ عنوان کتاب جدید به مخزن اصلی اضافه شد | ۱۴۰۵/۰۷/۲۸ | فهرست کامل کتاب‌های جدید در حوزه هوش مصنوعی، ادبیات، تاریخ و علوم اسلامی فهرست‌نویسی شده و آماده امانت به اعضا می‌باشد.",
    'links'        => "نهاد کتابخانه‌های عمومی کشور | https://www.iranpl.ir\nسامانه مدیریت کتابخانه‌ها (سامان) | https://samanpl.ir\nسازمان اسناد و کتابخانه ملی ایران | https://opac.nlai.ir/opac-prod/search/searchBF.do",
    'about'        => '<p><b>کتابخانه آسمان</b> با هدف ارتقای فرهنگ مطالعه مفید، تسهیل دسترسی پژوهشگران و شهروندان به منابع معتبر علمی، فرهنگی و ادبی، و ارائه خدمات نوین کتابداری بر پایه استانداردهای ایران‌مارک فعالیت می‌نماید.</p>',
    'contact'      => '<p><b>آدرس:</b> مشهد مقدس، بلوار وکیل‌آباد، پلاک ۱۲۰<br><b>تلفن تماس:</b> ۰۵۱-۳۸۸۸۰۰۰۰<br><b>پست الکترونیک:</b> info@asemanlib.ir<br><b>ساعات فعالیت:</b> شنبه تا پنجشنبه ۸:۰۰ الی ۱۹:۳۰</p>',
    'locations'    => "مخزن اصلی|10\nبخش مرجع|11\nبخش کودک و نوجوان|12\nبخش نشریات و مقالات|13\nبخش پایان‌نامه‌ها|14",
    'growth_chart' => "فروردین|120\nاردیبهشت|185\nخرداد|240\nتیر|310\nمرداد|390\nشهریور|450\nمهر|530\nآبان|610\nآذر|680\nدی|740\nبهمن|820\nاسفند|950"
);
$ins_cms = $db->prepare("INSERT OR IGNORE INTO cms(k,v) VALUES(?,?)");
foreach ($cms_defaults as $k => $v) {
    $ins_cms->execute(array($k, $v));
}

// ایجاد باکس‌های پیش‌فرض پرتال
$default_boxes = array(
    array('search', 'جستجو و سفارش منابع (OPAC)', 'system', '', 'main', 1, 1),
    array('news', 'اخبار و اطلاعیه‌های کتابخانه', 'system', '', 'main', 2, 1),
    array('chart', 'نمودار رشد منابع کتابخانه (ماه‌به‌ماه)', 'system', '', 'main', 3, 1),
    array('services_new', 'خدمات کتابخانه و تازه‌های نشر', 'system', '', 'main', 4, 1),
    array('about_contact', 'درباره ما و تماس با ما', 'system', '', 'main', 5, 1),
    array('login', 'ورود کتابداران و مدیران', 'system', '', 'side', 1, 1),
    array('stats', 'آمار مجموعه و اعضا', 'system', '', 'side', 2, 1),
    array('gallery', 'گالری تصاویر کتابخانه', 'system', '', 'side', 3, 1),
    array('links', 'پیوندهای مفید', 'system', '', 'side', 4, 1)
);
$ins_box = $db->prepare("INSERT OR IGNORE INTO portal_boxes(bkey,title,btype,content,pos,ord,active) VALUES(?,?,?,?,?,?,?)");
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
    $db->prepare("REPLACE INTO cms(k,v) VALUES(?,?)")->execute(array($k, $v));
}

// توابع تبدیل اعداد و تاریخ شمسی
function tr_num($str) {
    return str_replace(array('0','1','2','3','4','5','6','7','8','9'), array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), (string)$str);
}
function en_num($str) {
    return str_replace(array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), array('0','1','2','3','4','5','6','7','8','9'), (string)$str);
}
function jdate($str, $with_time = false) {
    if (!$str) return '-';
    $ts = ($str === 'now') ? time() : strtotime($str);
    if (!$ts) return tr_num($str);
    $parts = explode('-', date('Y-n-j', $ts));
    $gy = (int)$parts[0]; $gm = (int)$parts[1]; $gd = (int)$parts[2];
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    $out = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    if ($with_time) $out .= ' ساعت ' . date('H:i', $ts);
    return tr_num($out);
}

// مدیریت محل‌های نگهداری و پیش‌شماره اختصاصی هر مرکز (شروع از ۱۰، ۱۱، ۱۲ و...)
function get_locations_map() {
    $lines = array_values(array_filter(array_map('trim', explode("\n", get_cms('locations')))));
    $map = array();
    $auto_pref = 10;
    foreach ($lines as $line) {
        $pts = explode('|', $line);
        $name = trim($pts[0]);
        if ($name === '') continue;
        $pref = isset($pts[1]) && trim(en_num($pts[1])) !== '' ? (int)en_num(trim($pts[1])) : $auto_pref;
        if ($pref < 10) $pref = $auto_pref;
        $map[$name] = (string)$pref;
        if ($pref >= $auto_pref) $auto_pref = $pref + 1;
    }
    if (empty($map)) $map['مخزن اصلی'] = '10';
    return $map;
}
function save_locations_map($map) {
    $lines = array();
    foreach ($map as $name => $pref) {
        $lines[] = trim($name) . '|' . trim(en_num($pref));
    }
    set_cms('locations', implode("\n", $lines));
}
function get_locations() {
    return array_keys(get_locations_map());
}
function get_loc_prefix($loc_name) {
    $map = get_locations_map();
    if (isset($map[$loc_name])) return $map[$loc_name];
    $first = reset($map);
    return $first ? $first : '10';
}

// تولید شماره ثبت خودکار بر اساس پیش‌شماره محل نگهداری (مثلاً 100001 برای پیش‌شماره 10، 110001 برای 11)
function next_reg_no($loc_name = null) {
    global $db;
    $map = get_locations_map();
    if ($loc_name === null || !isset($map[$loc_name])) {
        $keys = array_keys($map);
        $loc_name = $keys[0];
    }
    $pref = (string)$map[$loc_name];
    $st = $db->prepare("SELECT reg_no FROM records WHERE loc=? OR reg_no LIKE ?");
    $st->execute(array($loc_name, $pref . '%'));
    $max_seq = 0;
    $plen = strlen($pref);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $rno) {
        $rno = trim(en_num($rno));
        if (strpos($rno, $pref) === 0 && strlen($rno) > $plen) {
            $rem = substr($rno, $plen);
            if (ctype_digit($rem)) {
                $val = (int)$rem;
                if ($val > $max_seq) $max_seq = $val;
            }
        }
    }
    return $pref . sprintf('%04d', $max_seq + 1);
}

$LocationsMap = get_locations_map();
$Locations = array_keys($LocationsMap);
// ذخیره فرمت استاندارد پیش‌شماره‌ها در دیتابیس و اصلاح رکوردهای بدون شماره ثبت
save_locations_map($LocationsMap);
foreach ($db->query("SELECT id, loc FROM records WHERE reg_no IS NULL OR reg_no = ''") as $empty_r) {
    $nr = next_reg_no($empty_r['loc']);
    $db->prepare("UPDATE records SET reg_no=? WHERE id=?")->execute(array($nr, $empty_r['id']));
}

// پاکسازی دقیق کاراکترهای کنترل یونیکد و علائم اضافی مارک کتابخانه ملی ایران
function clean_marc_str($s) {
    $bidi_bytes = array(
        "\xE2\x80\x8E", "\xE2\x80\x8F",
        "\xE2\x80\xAA", "\xE2\x80\xAB", "\xE2\x80\xAC", "\xE2\x80\xAD", "\xE2\x80\xAE",
        "\xE2\x81\xA6", "\xE2\x81\xA7", "\xE2\x81\xA8", "\xE2\x81\xA9", "\xEF\xBB\xBF"
    );
    $s = str_replace($bidi_bytes, '', (string)$s);
    $s = str_replace(array("\x1e", "\x1d", "\r", "\n"), ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = preg_replace('/^[\s:;\-\/،.؛«»\[\]()]+|[\s:;\-\/،.؛«»\[\]()]+$/u', '', $s);
    return trim($s);
}

// موتور دوگانه پردازشگر فایل ISO 2709 و selectedRecords.txt کتابخانه ملی ایران
function parse_iso2709_records($raw, $F_keys) {
    $parsed = array();
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    $raw_records = explode("\x1d", $raw);
    if (count($raw_records) <= 1 && preg_match_all('/\d{5}[a-z0-9\s]{19}(?=\d{12})/i', $raw, $ldr_m, PREG_OFFSET_CAPTURE)) {
        if (count($ldr_m[0]) > 1) {
            $raw_records = array();
            for ($k = 0; $k < count($ldr_m[0]); $k++) {
                $start = $ldr_m[0][$k][1];
                $end = isset($ldr_m[0][$k+1]) ? $ldr_m[0][$k+1][1] : strlen($raw);
                $raw_records[] = substr($raw, $start, $end - $start);
            }
        }
    }

    foreach ($raw_records as $rec) {
        $rec = ltrim($rec, "\r\n ");
        if (strlen($rec) < 24) continue;

        $acc = array();
        foreach ($F_keys as $k) $acc[$k] = array();
        $tag_values_pairs = array();

        $chunks = explode("\x1e", $rec);
        if (count($chunks) > 1) {
            $leader_and_dir = $chunks[0];
            if (strlen($leader_and_dir) >= 36) {
                $dir_str = substr($leader_and_dir, 24);
                if (preg_match_all('/(\d{3})\d{9}/', $dir_str, $tm)) {
                    $tags_list = $tm[1];
                    for ($idx = 0; $idx < count($tags_list); $idx++) {
                        if (isset($chunks[$idx + 1])) {
                            $tag_values_pairs[] = array($tags_list[$idx], $chunks[$idx + 1]);
                        }
                    }
                }
            }
        }

        if (empty($tag_values_pairs)) {
            $base = (int)substr($rec, 12, 5);
            if ($base > 24 && $base <= strlen($rec)) {
                $dir = substr($rec, 24, $base - 25);
                $data = substr($rec, $base);
                for ($i = 0; $i + 12 <= strlen($dir); $i += 12) {
                    $tag = substr($dir, $i, 3);
                    $len = (int)substr($dir, $i + 3, 4);
                    $pos = (int)substr($dir, $i + 7, 5);
                    $val = substr($data, $pos, $len);
                    $tag_values_pairs[] = array($tag, $val);
                }
            }
        }

        foreach ($tag_values_pairs as $pair) {
            $tag = $pair[0];
            $val = $pair[1];

            $subs = array();
            if (strpos($val, "\x1f") !== false) {
                $parts = explode("\x1f", $val);
                foreach ($parts as $idx => $part) {
                    if ($idx === 0 || $part === '') continue;
                    $code = $part[0];
                    $v_clean = clean_marc_str(substr($part, 1));
                    if ($v_clean !== '') {
                        if (!isset($subs[$code])) $subs[$code] = array();
                        $subs[$code][] = $v_clean;
                    }
                }
            } elseif (preg_match_all('/\$([a-z0-9])([^$]+)/i', $val, $dlm)) {
                foreach ($dlm[1] as $d_i => $code) {
                    $code = strtolower($code);
                    $v_clean = clean_marc_str($dlm[2][$d_i]);
                    if ($v_clean !== '') {
                        if (!isset($subs[$code])) $subs[$code] = array();
                        $subs[$code][] = $v_clean;
                    }
                }
            }

            if (($tag === '010' || $tag === '011' || $tag === '020') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f010'][] = $x;
            }
            if (($tag === '101' || $tag === '041') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f101'][] = $x;
            }
            if ($tag === '200' || $tag === '245') {
                if (isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f200a'][] = $x;
                if (isset($subs['e'])) foreach ($subs['e'] as $x) $acc['f200e'][] = $x;
                if ($tag === '245' && isset($subs['b'])) foreach ($subs['b'] as $x) $acc['f200e'][] = $x;
                if (isset($subs['f'])) foreach ($subs['f'] as $x) $acc['f200f'][] = $x;
                if ($tag === '245' && isset($subs['c'])) foreach ($subs['c'] as $x) $acc['f200f'][] = $x;
            }
            if (($tag === '205' || $tag === '250') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f205'][] = $x;
            }
            if ($tag === '210' || $tag === '260' || $tag === '264') {
                if (isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f210a'][] = $x;
                if (isset($subs['c']) && $tag === '210') foreach ($subs['c'] as $x) $acc['f210c'][] = $x;
                if (isset($subs['b']) && ($tag === '260' || $tag === '264')) foreach ($subs['b'] as $x) $acc['f210c'][] = $x;
                if (isset($subs['d']) && $tag === '210') foreach ($subs['d'] as $x) $acc['f210d'][] = $x;
                if (isset($subs['c']) && ($tag === '260' || $tag === '264')) foreach ($subs['c'] as $x) $acc['f210d'][] = $x;
            }
            if ($tag === '215' && isset($subs['a'])) {
                $p215 = implode(' - ', $subs['a']);
                if (isset($subs['c'])) $p215 .= ' : ' . implode('، ', $subs['c']);
                $acc['f215'][] = $p215;
            }
            if (($tag === '225' || $tag === '490') && isset($subs['a'])) {
                $p225 = implode(' - ', $subs['a']);
                if (isset($subs['v'])) $p225 .= ' ؛ ج. ' . implode('، ', $subs['v']);
                $acc['f225'][] = $p225;
            }
            if (($tag === '300' || $tag === '320' || $tag === '330' || $tag === '500' || $tag === '504') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f300'][] = $x;
            }
            if (($tag === '606' || $tag === '600' || $tag === '610' || $tag === '650') && isset($subs['a'])) {
                if (!isset($subs['7'])) {
                    $s_parts = $subs['a'];
                    if (isset($subs['x'])) $s_parts = array_merge($s_parts, $subs['x']);
                    if (isset($subs['y'])) $s_parts = array_merge($s_parts, $subs['y']);
                    if (isset($subs['z'])) $s_parts = array_merge($s_parts, $subs['z']);
                    $acc['f606'][] = implode(' -- ', $s_parts);
                }
            }
            if (($tag === '676' || $tag === '082') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f676'][] = $x;
            }
            if (($tag === '680' || $tag === '686' || $tag === '050') && isset($subs['a'])) {
                $lc_str = implode(' ', $subs['a']);
                if (isset($subs['b'])) $lc_str .= ' ' . implode(' ', $subs['b']);
                $acc['f686'][] = trim($lc_str);
            }
            if (($tag === '700' || $tag === '701' || $tag === '702') && isset($subs['a'])) {
                $pname = $subs['a'][0];
                if (isset($subs['b'][0]) && $subs['b'][0] !== '') {
                    $pname .= '، ' . $subs['b'][0];
                }
                $acc['f700'][] = $pname;
            }
            if (($tag === '710' || $tag === '711' || $tag === '712') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f710'][] = $x;
            }
        }

        $row = array();
        foreach ($F_keys as $k) {
            $unique_vals = array_values(array_unique(array_filter($acc[$k])));
            $row[$k] = implode(' ؛ ', $unique_vals);
        }
        if ($row['f101'] === '') $row['f101'] = 'per';
        if ($row['f200a'] !== '') {
            $parsed[] = $row;
        }
    }
    return $parsed;
}

// خواندن انواع فایل اکسل (.xlsx، جدول HTML .xls و .csv)
function parse_excel($file) {
    $rows = array();
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive;
        if ($zip->open($file) === TRUE) {
            $strings = array();
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $sx = @simplexml_load_string($xml);
                if ($sx) {
                    foreach ($sx->si as $si) {
                        $t = (string)$si->t;
                        if ($t === '' && isset($si->r)) { foreach ($si->r as $r) $t .= (string)$r->t; }
                        $strings[] = $t;
                    }
                }
            }
            if (($xml = $zip->getFromName('xl/worksheets/sheet1.xml')) !== false) {
                $sx = @simplexml_load_string($xml);
                if ($sx && isset($sx->sheetData->row)) {
                    foreach ($sx->sheetData->row as $r) {
                        $row = array();
                        foreach ($r->c as $c) {
                            $v = (string)$c->v;
                            if ((string)$c['t'] === 's') $v = isset($strings[(int)$v]) ? $strings[(int)$v] : '';
                            elseif ((string)$c['t'] === 'inlineStr') $v = (string)$c->is->t;
                            $row[] = trim($v);
                        }
                        if (array_filter($row)) $rows[] = $row;
                    }
                }
            }
            $zip->close();
            if ($rows) return $rows;
        }
    }
    $raw_content = @file_get_contents($file);
    if ($raw_content && stripos($raw_content, '<table') !== false && preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $raw_content, $tr_m)) {
        foreach ($tr_m[1] as $tr_html) {
            if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr_html, $td_m)) {
                $row = array();
                foreach ($td_m[1] as $cell) {
                    $row[] = trim(html_entity_decode(strip_tags($cell), ENT_QUOTES, 'UTF-8'));
                }
                if (array_filter($row)) $rows[] = $row;
            }
        }
        if ($rows) return $rows;
    }
    if (($fp = fopen($file, 'r')) !== false) {
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fp);
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $delim = ',';
            if (substr_count($line, "\t") > substr_count($line, ',')) $delim = "\t";
            elseif (substr_count($line, ';') > substr_count($line, ',')) $delim = ';';
            $d = str_getcsv($line, $delim);
            if (array_filter($d)) $rows[] = array_map('trim', $d);
        }
        fclose($fp);
    }
    return $rows;
}

function output_excel_xls($filename, $headers, $data_rows) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40" dir="rtl"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><style>
    table{border-collapse:collapse;direction:rtl;font-family:Tahoma,sans-serif;font-size:13px}
    th{background:#16336f;color:#ffffff;border:1px solid #999;padding:8px;font-weight:bold;text-align:center}
    td{border:1px solid #bbb;padding:6px;text-align:right;mso-number-format:"\@"}
    </style></head><body><table border="1"><thead><tr>';
    foreach ($headers as $th) {
        echo '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($data_rows as $r) {
        echo '<tr>';
        foreach ($r as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

$F = array(
    'f010'  => 'شابک / شاپا (010 $a)',
    'f101'  => 'زبان اثر (101 $a)',
    'f200a' => 'عنوان اصلی (200 $a)',
    'f200e' => 'عنوان فرعی (200 $e)',
    'f200f' => 'نخستین پدیدآور / مسئولیت (200 $f)',
    'f205'  => 'وضعیت ویراست (205 $a)',
    'f210a' => 'محل نشر (210 $a)',
    'f210c' => 'نام ناشر / دانشگاه (210 $c)',
    'f210d' => 'سال نشر (210 $d)',
    'f215'  => 'تعداد صفحات / مشخصات ظاهری (215 $a)',
    'f225'  => 'عنوان مجموعه / فروست (225 $a)',
    'f300'  => 'یادداشت کلی / کتابنامه (300 $a)',
    'f606'  => 'موضوعات (606 $a)',
    'f676'  => 'شماره رده دیویی (676 $a)',
    'f686'  => 'شماره رده کنگره (680/686 $a)',
    'f700'  => 'پدیدآوران شخصی (700/701 $a)',
    'f710'  => 'پدیدآور سازمانی (710 $a)'
);
$RTypes = array('book'=>'کتاب', 'article'=>'مقاله', 'thesis'=>'پایان‌نامه', 'other'=>'سایر منابع');

// ۲۰ طیف رنگی (۱۰ تم پررنگ و ۱۰ تم کم‌رنگ پاستلی)
$Themes = array(
    'royal_dark'     => array('name'=>'۱. آبی سلطنتی پررنگ (پیشنهادی)', 'navy'=>'#07193d', 'navy2'=>'#12316b', 'blue'=>'#2563eb', 'sky'=>'#dbeafe', 'ice'=>'#f0f6ff', 'line'=>'#bfdbfe'),
    'emerald_dark'   => array('name'=>'۲. سبز زمردی پررنگ', 'navy'=>'#052e20', 'navy2'=>'#0a4f37', 'blue'=>'#059669', 'sky'=>'#d1fae5', 'ice'=>'#ecfdf5', 'line'=>'#a7f3d0'),
    'crimson_dark'   => array('name'=>'۳. زرشکی و یاقوتی پررنگ', 'navy'=>'#3d0814', 'navy2'=>'#691224', 'blue'=>'#dc2626', 'sky'=>'#fee2e2', 'ice'=>'#fef2f2', 'line'=>'#fecaca'),
    'purple_dark'    => array('name'=>'۴. بنفش آمیتیست پررنگ', 'navy'=>'#230b42', 'navy2'=>'#3f1673', 'blue'=>'#7c3aed', 'sky'=>'#ede9fe', 'ice'=>'#f5f3ff', 'line'=>'#ddd6fe'),
    'teal_dark'      => array('name'=>'۵. فیروزه‌ای اقیانوسی پررنگ', 'navy'=>'#082f38', 'navy2'=>'#0f5261', 'blue'=>'#0891b2', 'sky'=>'#cffafe', 'ice'=>'#ecfeff', 'line'=>'#a5f3fc'),
    'amber_dark'     => array('name'=>'۶. کهربایی و طلایی پررنگ', 'navy'=>'#3b1d06', 'navy2'=>'#66340c', 'blue'=>'#d97706', 'sky'=>'#fef3c7', 'ice'=>'#fffbeb', 'line'=>'#fde68a'),
    'indigo_dark'    => array('name'=>'۷. سرمه‌ای نیلی پررنگ', 'navy'=>'#14153d', 'navy2'=>'#25276b', 'blue'=>'#4f46e5', 'sky'=>'#e0e7ff', 'ice'=>'#eef2ff', 'line'=>'#c7d2fe'),
    'rose_dark'      => array('name'=>'۸. ارغوانی رز پررنگ', 'navy'=>'#3b0720', 'navy2'=>'#69103a', 'blue'=>'#e11d48', 'sky'=>'#ffe4e6', 'ice'=>'#fff1f2', 'line'=>'#fecdd3'),
    'forest_dark'    => array('name'=>'۹. سبز جنگلی پررنگ', 'navy'=>'#112a15', 'navy2'=>'#1e4a25', 'blue'=>'#16a34a', 'sky'=>'#dcfce7', 'ice'=>'#f0fdf4', 'line'=>'#bbf7d0'),
    'slate_dark'     => array('name'=>'۱۰. ذغالی و نقره‌ای مدرن پررنگ', 'navy'=>'#0f172a', 'navy2'=>'#1e293b', 'blue'=>'#475569', 'sky'=>'#e2e8f0', 'ice'=>'#f8fafc', 'line'=>'#cbd5e1'),
    'sky_light'      => array('name'=>'۱۱. آبی آسمانی کم‌رنگ (ملایم)', 'navy'=>'#1e3a5f', 'navy2'=>'#2b547e', 'blue'=>'#38bdf8', 'sky'=>'#e0f2fe', 'ice'=>'#f8fcff', 'line'=>'#bae6fd'),
    'mint_light'     => array('name'=>'۱۲. سبز نعنایی کم‌رنگ (ملایم)', 'navy'=>'#1b4332', 'navy2'=>'#2d6a4f', 'blue'=>'#34d399', 'sky'=>'#d8f3dc', 'ice'=>'#f7fff9', 'line'=>'#b7e4c7'),
    'lavender_light' => array('name'=>'۱۳. یاسی و اسطوخودوس کم‌رنگ', 'navy'=>'#3c1e5f', 'navy2'=>'#583187', 'blue'=>'#a78bfa', 'sky'=>'#f3e8ff', 'ice'=>'#fcfaff', 'line'=>'#e9d5ff'),
    'peach_light'    => array('name'=>'۱۴. هلویی و مرجانی کم‌رنگ', 'navy'=>'#5c2415', 'navy2'=>'#7c3622', 'blue'=>'#fb923c', 'sky'=>'#ffedd5', 'ice'=>'#fffaf5', 'line'=>'#fed7aa'),
    'pink_light'     => array('name'=>'۱۵. صورتی شکوفه‌ای کم‌رنگ', 'navy'=>'#591936', 'navy2'=>'#7a254b', 'blue'=>'#f472b6', 'sky'=>'#fce7f3', 'ice'=>'#fff8fc', 'line'=>'#fbcfe8'),
    'aqua_light'     => array('name'=>'۱۶. فیروزه‌ای یخی کم‌رنگ', 'navy'=>'#134e4a', 'navy2'=>'#115e59', 'blue'=>'#2dd4bf', 'sky'=>'#ccfbf1', 'ice'=>'#f5fffe', 'line'=>'#99f6e4'),
    'lemon_light'    => array('name'=>'۱۷. لیمویی و کرم روشن', 'navy'=>'#42360c', 'navy2'=>'#635114', 'blue'=>'#eab308', 'sky'=>'#fef9c3', 'ice'=>'#fffff7', 'line'=>'#fef08a'),
    'lilac_light'    => array('name'=>'۱۸. بنفش یاقوتی روشن', 'navy'=>'#4a154b', 'navy2'=>'#682169', 'blue'=>'#e879f9', 'sky'=>'#fae8ff', 'ice'=>'#fffaff', 'line'=>'#f5d0fe'),
    'pistachio_light'=> array('name'=>'۱۹. سبز پسته‌ای کم‌رنگ', 'navy'=>'#2a3d12', 'navy2'=>'#3f5c1c', 'blue'=>'#84cc16', 'sky'=>'#ecfccb', 'ice'=>'#fafff5', 'line'=>'#d9f99d'),
    'pearl_light'    => array('name'=>'۲۰. مرواریدی و آبی نقره‌ای کم‌رنگ', 'navy'=>'#273444', 'navy2'=>'#3c4d63', 'blue'=>'#60a5fa', 'sky'=>'#eff6ff', 'ice'=>'#fafcff', 'line'=>'#dbeafe')
);

$AllPerms = array(
    'dash'    => 'پیشخوان مدیریت',
    'rec'     => 'منابع و میز امانت',
    'form'    => 'ثبت و ویرایش منبع',
    'org'     => 'سازماندهی و چاپ برچسب عطف',
    'locs'    => 'مدیریت محل نگهداری',
    'orders'  => 'فهرست سفارش کتاب',
    'tickets' => 'سیستم تیکت و پشتیبانی',
    'nlai'    => 'جستجو در کتابخانه ملی',
    'imp_exp' => 'ورودی و خروجی (اکسل/ایزو)',
    'backup'  => 'پشتیبان‌گیری و بازیابی',
    'mem'     => 'مدیریت اعضا',
    'loan'    => 'امانت‌ها',
    'rep'     => 'گزارش‌ها',
    'cms'     => 'مدیریت پرتال',
    'users'   => 'مدیریت کاربران'
);

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$in = isset($_SESSION['u']);
$p = isset($_GET['p']) ? $_GET['p'] : ($in ? 'dash' : 'home');
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
        $_SESSION['perms'] = ($is_super || $cu['perms'] === 'all' || empty($cu['perms'])) ? array_keys($AllPerms) : explode(',', $cu['perms']);
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
function can($perm) {
    if (!isset($_SESSION['u'])) return false;
    if (is_super_admin()) return true;
    return in_array($perm, isset($_SESSION['perms']) ? $_SESSION['perms'] : array());
}

// ورود، خروج و سفارش کتاب از پرتال
if ($p === 'login' && $post) {
    $s = $db->prepare("SELECT * FROM users WHERE u=?");
    $s->execute(array(trim(isset($_POST['u']) ? $_POST['u'] : '')));
    $u = $s->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify(isset($_POST['pw']) ? $_POST['pw'] : '', $u['pw'])) {
        session_regenerate_id(true);
        $_SESSION['u'] = $u['u'];
        $is_sup = ($u['u'] === 'admin' || $u['u'] === 'admin1');
        $_SESSION['perms'] = ($is_sup || $u['perms'] === 'all' || empty($u['perms'])) ? array_keys($AllPerms) : explode(',', $u['perms']);
        $_SESSION['uloc'] = $is_sup ? 'all' : ($u['uloc'] ? $u['uloc'] : 'all');
        $_SESSION['t'] = bin2hex(random_bytes(16));
        header('Location: ?p=dash');
        exit;
    }
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
if ($p === 'logout') {
    session_destroy();
    header('Location: ?p=home');
    exit;
}
if (!$in && !in_array($p, array('home', 'news_list', 'login', 'add_order'))) {
    header('Location: ?p=home');
    exit;
}

// ==================== خروجی‌های استاندارد و پشتیبان‌گیری (Backup) ====================
if ($in && $p === 'dl_full_db' && can('backup') && is_super_admin()) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="aseman_full_backup_' . date('Y-m-d') . '.db"');
    readfile(__DIR__ . '/library.db');
    exit;
}
if ($in && $p === 'dl_json_bk' && can('backup')) {
    $req_loc = isset($_GET['loc']) ? trim($_GET['loc']) : 'all';
    if (is_loc_restricted()) $req_loc = user_loc();
    $data_bk = array('version' => '2.1', 'date' => date('Y-m-d H:i:s'), 'scope' => $req_loc);
    if ($req_loc === 'all' && is_super_admin()) {
        $data_bk['records'] = $db->query("SELECT * FROM records")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['members'] = $db->query("SELECT * FROM members")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['loans']   = $db->query("SELECT * FROM loans")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['orders']  = $db->query("SELECT * FROM orders")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['cms']     = $db->query("SELECT * FROM cms")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['boxes']   = $db->query("SELECT * FROM portal_boxes")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st1 = $db->prepare("SELECT * FROM records WHERE loc=?"); $st1->execute(array($req_loc));
        $data_bk['records'] = $st1->fetchAll(PDO::FETCH_ASSOC);
        $st2 = $db->prepare("SELECT * FROM members WHERE loc=?"); $st2->execute(array($req_loc));
        $data_bk['members'] = $st2->fetchAll(PDO::FETCH_ASSOC);
        $st3 = $db->prepare("SELECT * FROM orders WHERE loc=?"); $st3->execute(array($req_loc));
        $data_bk['orders']  = $st3->fetchAll(PDO::FETCH_ASSOC);
        $st4 = $db->prepare("SELECT l.* FROM loans l JOIN records r ON r.id=l.rid WHERE r.loc=?"); $st4->execute(array($req_loc));
        $data_bk['loans']   = $st4->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="aseman_backup_' . ($req_loc === 'all' ? 'full' : 'loc') . '_' . date('Y-m-d') . '.json"');
    echo json_encode($data_bk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($in && $p === 'export' && can('imp_exp')) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="aseman_iranmarc.mrc"');
    $w_exp = is_loc_restricted() ? "WHERE loc = " . $db->quote(user_loc()) : "";
    foreach ($db->query("SELECT * FROM records $w_exp") as $r) {
        $fl = array('001' => $r['reg_no'] ? $r['reg_no'] : $r['id']);
        $map = array(
            '010'=>array('a'=>'f010'),'101'=>array('a'=>'f101'),'200'=>array('a'=>'f200a','e'=>'f200e','f'=>'f200f'),
            '205'=>array('a'=>'f205'),'210'=>array('a'=>'f210a','c'=>'f210c','d'=>'f210d'),'215'=>array('a'=>'f215'),
            '225'=>array('a'=>'f225'),'300'=>array('a'=>'f300'),'606'=>array('a'=>'f606'),'676'=>array('a'=>'f676'),
            '680'=>array('a'=>'f686'),'700'=>array('a'=>'f700'),'710'=>array('a'=>'f710')
        );
        foreach ($map as $tag => $subs) {
            $sub_str = '';
            foreach ($subs as $c => $col) {
                if (!empty($r[$col])) $sub_str .= "\x1f" . $c . $r[$col];
            }
            if ($sub_str !== '') $fl[$tag] = '  ' . $sub_str;
        }
        $dir = ''; $data = '';
        foreach ($fl as $t => $v) {
            $v .= "\x1e";
            $dir .= $t . sprintf('%04d%05d', strlen($v), strlen($data));
            $data .= $v;
        }
        $base = 24 + strlen($dir) + 1;
        $len = $base + strlen($data) + 1;
        echo sprintf('%05d', $len) . 'nam  22' . sprintf('%05d', $base) . ' i 450 ' . $dir . "\x1e" . $data . "\x1d";
    }
    exit;
}

if ($in && $p === 'sample_rec') {
    $headers = array_merge(array_values($F), array('نوع منبع (کتاب/مقاله/پایان‌نامه)', 'تعداد نسخه', 'شماره ثبت (خالی = خودکار بر اساس پیش‌شماره محل)', 'محل نگهداری'));
    $def_l = is_loc_restricted() ? user_loc() : $Locations[0];
    $sample_reg = next_reg_no($def_l);
    $sample_rows = array(
        array('978-622-128-315-6', 'per', 'تحول دیجیتال در حسابداری: کاربرد هوش مصنوعی و بلاک‌چین در گزارشگری مالی', '', 'بهمن مولائی، ناصر مرادی، لیلا زهره‌وند', 'چاپ اول', 'اصفهان', 'برکه نور', '۱۴۰۵', '۱۰۳ ص', '', 'کتابنامه: ص. ۱۰۱ - ۱۰۳', 'حسابداری -- نوآوری ؛ هوش مصنوعی', '۶۵۷/۰۲۸۵', 'HF۵۶۷۹', 'مولائی، بهمن ؛ مرادی، ناصر ؛ زهره‌وند، لیلا', '', 'کتاب', '2', $sample_reg, $def_l)
    );
    output_excel_xls('sample_iranmarc_aseman.xls', $headers, $sample_rows);
}

if ($in && $p === 'export_excel_rec' && (can('imp_exp') || can('rec') || can('rep') || can('backup'))) {
    $headers = array_merge(array_values($F), array('نوع منبع', 'تعداد نسخه', 'شماره ثبت', 'محل نگهداری', 'تاریخ ثبت'));
    $w_exp = is_loc_restricted() ? "WHERE loc = " . $db->quote(user_loc()) : "";
    $rows = array();
    foreach ($db->query("SELECT * FROM records $w_exp ORDER BY CAST(reg_no AS INTEGER) ASC") as $r) {
        $row = array();
        foreach (array_keys($F) as $fk) $row[] = $r[$fk];
        $row[] = isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب';
        $row[] = $r['copies'];
        $row[] = $r['reg_no'];
        $row[] = $r['loc'];
        $row[] = jdate($r['created']);
        $rows[] = $row;
    }
    output_excel_xls('aseman_records_export.xls', $headers, $rows);
}

if ($in && $p === 'export_excel_mem' && (can('mem') || can('imp_exp') || can('backup'))) {
    $headers = array('کد عضویت / کد ملی', 'نام و نام خانوادگی', 'شماره تماس', 'محل نگهداری');
    $w_exp = is_loc_restricted() ? "WHERE loc = " . $db->quote(user_loc()) : "";
    $rows = array();
    foreach ($db->query("SELECT * FROM members $w_exp ORDER BY id DESC") as $m) {
        $rows[] = array($m['mcode'] ? $m['mcode'] : $m['id'], $m['name'], $m['phone'], $m['loc']);
    }
    output_excel_xls('aseman_members_export.xls', $headers, $rows);
}

if ($in && $p === 'sample_mem') {
    $headers = array('نام و نام خانوادگی', 'شماره تماس', 'کد عضویت');
    $rows = array(array('علی رضایی', '09120000000', '1001'));
    output_excel_xls('sample_members_aseman.xls', $headers, $rows);
}

// ==================== پردازش درخواست‌های POST ====================
if ($in && $post) {
    if ($p === 'save' && can('form')) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $cols = array_keys($F);
        $vals = array();
        foreach ($cols as $c) $vals[] = trim(isset($_POST[$c]) ? $_POST[$c] : '');
        $rtype = array_key_exists(isset($_POST['rtype']) ? $_POST['rtype'] : '', $RTypes) ? $_POST['rtype'] : 'book';
        $copies = max(1, (int)(isset($_POST['copies']) ? $_POST['copies'] : 1));
        $loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        $reg_no = trim(en_num(isset($_POST['reg_no']) ? $_POST['reg_no'] : ''));
        if ($reg_no === '') $reg_no = next_reg_no($loc);
        
        $file_path = isset($_POST['old_file']) ? $_POST['old_file'] : '';
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, array('pdf','doc','docx','jpg','jpeg','png','zip','rar','mp3','mp4','txt'))) {
                $new_name = 'att_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . '/uploads/' . $new_name)) {
                    $file_path = 'uploads/' . $new_name;
                }
            }
        }
        if ($id) {
            $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
            $set_parts = array();
            foreach ($cols as $c) $set_parts[] = "$c=?";
            $sql = "UPDATE records SET " . implode(',', $set_parts) . ",rtype=?,copies=?,file=?,reg_no=?,loc=? WHERE id=? $w_loc";
            $params = array_merge($vals, array($rtype, $copies, $file_path, $reg_no, $loc, $id));
            $db->prepare($sql)->execute($params);
        } else {
            $qs = implode(',', array_fill(0, count($vals) + 5, '?'));
            $sql = "INSERT INTO records(" . implode(',', $cols) . ",rtype,copies,file,reg_no,loc) VALUES($qs)";
            $params = array_merge($vals, array($rtype, $copies, $file_path, $reg_no, $loc));
            $db->prepare($sql)->execute($params);
            $id = $db->lastInsertId();
        }
        header("Location: ?p=marc&id=$id&msg=saved");
        exit;
    }
    if ($p === 'del' && (can('form') || can('rec'))) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM records WHERE id=? $w_loc")->execute(array((int)$_POST['id']));
        header('Location: ?p=rec');
        exit;
    }
    // گام اول ورود ایزو ۲۷۰۹: استخراج و ذخیره در نشست جهت نمایش جدول پیش‌نمایش و تست به کاربر
    if ($p === 'imp_iso_preview' && (can('imp_exp') || can('nlai') || can('form'))) {
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        $from_page = isset($_POST['from_page']) ? $_POST['from_page'] : 'nlai';
        $_SESSION['iso_preview'] = array();
        $_SESSION['iso_loc'] = $def_loc;
        $_SESSION['iso_from'] = $from_page;
        if (!empty($_FILES['f']['tmp_name'])) {
            $raw = file_get_contents($_FILES['f']['tmp_name']);
            $cols = array_keys($F);
            $_SESSION['iso_preview'] = parse_iso2709_records($raw, $cols);
        }
        header("Location: ?p=$from_page&show_preview=1");
        exit;
    }
    // گام دوم ورود ایزو ۲۷۰۹: تایید نهایی و ثبت رکوردهای تست‌شده در دیتابیس با پیش‌شماره محل نگهداری
    if ($p === 'imp_iso_confirm' && (can('imp_exp') || can('nlai') || can('form'))) {
        $cnt = 0;
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? $_POST['loc'] : (isset($_SESSION['iso_loc']) ? $_SESSION['iso_loc'] : $Locations[0]));
        $from_page = isset($_POST['from_page']) ? $_POST['from_page'] : 'nlai';
        $cols = array_keys($F);
        if (!empty($_POST['recs']) && is_array($_POST['recs'])) {
            foreach ($_POST['recs'] as $idx => $r_data) {
                if (empty($_POST['sel'][$idx])) continue;
                $vals = array();
                foreach ($cols as $c) {
                    $vals[] = isset($r_data[$c]) ? trim($r_data[$c]) : '';
                }
                if ($vals[2] === '') continue;
                $rno = !empty($r_data['reg_no']) ? trim(en_num($r_data['reg_no'])) : next_reg_no($def_loc);
                $cp = max(1, (int)(isset($r_data['copies']) ? $r_data['copies'] : 1));
                $qs = implode(',', array_fill(0, count($cols) + 4, '?'));
                $params = array_merge($vals, array('book', $cp, $rno, $def_loc));
                $db->prepare("INSERT INTO records(" . implode(',', $cols) . ",rtype,copies,reg_no,loc) VALUES($qs)")->execute($params);
                $cnt++;
            }
        }
        unset($_SESSION['iso_preview']);
        header("Location: ?p=$from_page&msg=iso_$cnt");
        exit;
    }
    // بازیابی پشتیبان (Restore JSON / DB)
    if ($p === 'restore_bk' && can('backup')) {
        $cnt_r = 0; $cnt_m = 0;
        $target_loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['target_loc']) ? $_POST['target_loc'] : '');
        if (!empty($_FILES['bk_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['bk_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'db' && is_super_admin()) {
                $db = null;
                @copy($_FILES['bk_file']['tmp_name'], __DIR__ . '/library.db');
                header('Location: ?p=backup&msg=db_restored');
                exit;
            } elseif ($ext === 'json') {
                $json_str = file_get_contents($_FILES['bk_file']['tmp_name']);
                $data_bk = json_decode($json_str, true);
                if (is_array($data_bk)) {
                    $cols = array_keys($F);
                    if (!empty($data_bk['records']) && is_array($data_bk['records'])) {
                        foreach ($data_bk['records'] as $rec) {
                            $vals = array();
                            foreach ($cols as $c) $vals[] = isset($rec[$c]) ? $rec[$c] : '';
                            if (empty($vals[2])) continue;
                            $rt = isset($rec['rtype']) ? $rec['rtype'] : 'book';
                            $cp = max(1, (int)(isset($rec['copies']) ? $rec['copies'] : 1));
                            $lc = ($target_loc !== '' && $target_loc !== 'keep') ? $target_loc : (isset($rec['loc']) ? $rec['loc'] : $Locations[0]);
                            $rno = !empty($rec['reg_no']) ? $rec['reg_no'] : next_reg_no($lc);
                            $qs = implode(',', array_fill(0, count($cols) + 4, '?'));
                            $db->prepare("INSERT INTO records(" . implode(',', $cols) . ",rtype,copies,reg_no,loc) VALUES($qs)")->execute(array_merge($vals, array($rt, $cp, $rno, $lc)));
                            $cnt_r++;
                        }
                    }
                    if (!empty($data_bk['members']) && is_array($data_bk['members'])) {
                        foreach ($data_bk['members'] as $mem) {
                            if (empty($mem['name'])) continue;
                            $lc = ($target_loc !== '' && $target_loc !== 'keep') ? $target_loc : (isset($mem['loc']) ? $mem['loc'] : $Locations[0]);
                            $db->prepare("INSERT INTO members(name,phone,mcode,loc) VALUES(?,?,?,?)")->execute(array($mem['name'], isset($mem['phone'])?$mem['phone']:'', isset($mem['mcode'])?$mem['mcode']:'', $lc));
                            $cnt_m++;
                        }
                    }
                }
            }
        }
        header("Location: ?p=backup&msg=json_{$cnt_r}_{$cnt_m}");
        exit;
    }
    // مدیریت محل‌های نگهداری و پیش‌شماره‌های اختصاصی (۱۰، ۱۱، ۱۲ و...)
    if ($p === 'loc_action' && can('locs') && !is_loc_restricted()) {
        $act = isset($_POST['act']) ? $_POST['act'] : '';
        $cur_map = get_locations_map();
        if ($act === 'add') {
            $new_l = str_replace('|', '', trim(isset($_POST['new_loc']) ? $_POST['new_loc'] : ''));
            $new_pref = trim(en_num(isset($_POST['new_pref']) ? $_POST['new_pref'] : ''));
            if ($new_l !== '' && !isset($cur_map[$new_l])) {
                if ($new_pref === '' || (int)$new_pref < 10) {
                    $max_p = 9;
                    foreach ($cur_map as $p_val) { if ((int)$p_val > $max_p) $max_p = (int)$p_val; }
                    $new_pref = (string)($max_p + 1);
                }
                $cur_map[$new_l] = $new_pref;
                save_locations_map($cur_map);
            }
        } elseif ($act === 'rename') {
            $old_l = trim(isset($_POST['old_loc']) ? $_POST['old_loc'] : '');
            $new_l = str_replace('|', '', trim(isset($_POST['new_loc']) ? $_POST['new_loc'] : ''));
            $new_pref = trim(en_num(isset($_POST['new_pref']) ? $_POST['new_pref'] : ''));
            if ($old_l !== '' && isset($cur_map[$old_l]) && $new_l !== '') {
                $new_map = array();
                foreach ($cur_map as $k => $v) {
                    if ($k === $old_l) {
                        $new_map[$new_l] = ($new_pref !== '' && (int)$new_pref >= 10) ? $new_pref : $v;
                    } else {
                        $new_map[$k] = $v;
                    }
                }
                save_locations_map($new_map);
                if ($old_l !== $new_l) {
                    $db->prepare("UPDATE records SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE members SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE orders SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE users SET uloc=? WHERE uloc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE tickets SET uloc=? WHERE uloc=?")->execute(array($new_l, $old_l));
                }
            }
        } elseif ($act === 'delete') {
            $del_l = trim(isset($_POST['del_loc']) ? $_POST['del_loc'] : '');
            $target_l = trim(isset($_POST['target_loc']) ? $_POST['target_loc'] : '');
            if ($del_l !== '' && $target_l !== '' && $del_l !== $target_l && count($cur_map) > 1) {
                unset($cur_map[$del_l]);
                save_locations_map($cur_map);
                $db->prepare("UPDATE records SET loc=? WHERE loc=?")->execute(array($target_l, $del_l));
                $db->prepare("UPDATE members SET loc=? WHERE loc=?")->execute(array($target_l, $del_l));
                $db->prepare("UPDATE orders SET loc=? WHERE loc=?")->execute(array($target_l, $del_l));
                $db->prepare("UPDATE users SET uloc=? WHERE uloc=?")->execute(array($target_l, $del_l));
            }
        }
        header('Location: ?p=locs&msg=ok');
        exit;
    }
    if ($p === 'order_status' && can('orders')) {
        $oid = (int)$_POST['id'];
        $st = in_array($_POST['status'], array('bought', 'rejected', 'pending')) ? $_POST['status'] : 'pending';
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("UPDATE orders SET status=? WHERE id=? $w_loc")->execute(array($st, $oid));
        header('Location: ?p=orders&msg=ok');
        exit;
    }
    if ($p === 'del_order' && can('orders')) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM orders WHERE id=? $w_loc")->execute(array((int)$_POST['id']));
        header('Location: ?p=orders');
        exit;
    }
    if ($p === 'send_ticket' && can('tickets')) {
        $target = in_array($_POST['target'], array('admin', 'admin1')) ? $_POST['target'] : 'admin';
        $subj = trim(isset($_POST['subj']) ? $_POST['subj'] : '');
        $msg = trim(isset($_POST['msg']) ? $_POST['msg'] : '');
        $ul = is_loc_restricted() ? user_loc() : 'مدیریت مرکزی';
        if ($subj !== '' && $msg !== '') {
            $db->prepare("INSERT INTO tickets(sender,uloc,target,subj,msg,status) VALUES(?,?,?,?,?,'open')")->execute(array($_SESSION['u'], $ul, $target, $subj, $msg));
        }
        header('Location: ?p=tickets&msg=sent');
        exit;
    }
    if ($p === 'reply_ticket' && can('tickets') && is_super_admin()) {
        $tid = (int)$_POST['id'];
        $rep = trim(isset($_POST['reply']) ? $_POST['reply'] : '');
        $st = isset($_POST['status']) ? $_POST['status'] : 'answered';
        $db->prepare("UPDATE tickets SET reply=?, status=? WHERE id=?")->execute(array($rep, $st, $tid));
        header('Location: ?p=tickets&msg=replied');
        exit;
    }
    if ($p === 'savemem' && can('mem')) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $mcode = trim(isset($_POST['mcode']) ? $_POST['mcode'] : '');
        $loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        if ($id) {
            $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
            $db->prepare("UPDATE members SET name=?,phone=?,mcode=?,loc=? WHERE id=? $w_loc")->execute(array($name, $phone, $mcode, $loc, $id));
        } else {
            $db->prepare("INSERT INTO members(name,phone,mcode,loc) VALUES(?,?,?,?)")->execute(array($name, $phone, $mcode, $loc));
        }
        header('Location: ?p=mem');
        exit;
    }
    if ($p === 'delmem' && can('mem')) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM members WHERE id=? $w_loc")->execute(array((int)$_POST['id']));
        header('Location: ?p=mem');
        exit;
    }
    if ($p === 'out' && (can('rec') || can('loan'))) {
        $rid = (int)$_POST['rid'];
        $mid = (int)$_POST['mid'];
        $days = max(1, (int)(isset($_POST['days']) ? $_POST['days'] : 14));
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $c = $db->prepare("SELECT copies-(SELECT COUNT(*) FROM loans WHERE rid=? AND ret IS NULL) FROM records WHERE id=? $w_loc");
        $c->execute(array($rid, $rid));
        if ((int)$c->fetchColumn() > 0) {
            $db->prepare("INSERT INTO loans(rid,mid,d_out,due) VALUES(?,?,date('now'),date('now','+$days day'))")->execute(array($rid, $mid));
        }
        $ret_url = isset($_POST['ret_url']) ? $_POST['ret_url'] : '?p=loan';
        header("Location: $ret_url");
        exit;
    }
    if ($p === 'back' && (can('rec') || can('loan'))) {
        $db->prepare("UPDATE loans SET ret=date('now') WHERE id=?")->execute(array((int)$_POST['id']));
        $ret_url = isset($_POST['ret_url']) ? $_POST['ret_url'] : '?p=loan';
        header("Location: $ret_url");
        exit;
    }
    if ($p === 'editloan' && (can('rec') || can('loan'))) {
        $id = (int)$_POST['id'];
        $days = (int)$_POST['add_days'];
        if ($days != 0) {
            $op = $days > 0 ? "+$days day" : "$days day";
            $db->prepare("UPDATE loans SET due=date(due, '$op') WHERE id=?")->execute(array($id));
        }
        $ret_url = isset($_POST['ret_url']) ? $_POST['ret_url'] : '?p=loan';
        header("Location: $ret_url");
        exit;
    }
    if ($p === 'savecms' && can('cms')) {
        $stmt = $db->prepare("REPLACE INTO cms(k,v) VALUES(?,?)");
        foreach (array_keys($cms_defaults) as $k) {
            if (isset($_POST[$k])) $stmt->execute(array($k, trim($_POST[$k])));
        }
        $box = isset($_POST['active_box']) ? $_POST['active_box'] : 'general';
        header("Location: ?p=cms&box=$box&msg=saved");
        exit;
    }
    if ($p === 'save_box_layout' && can('cms')) {
        if (isset($_POST['b_id']) && is_array($_POST['b_id'])) {
            $st_up = $db->prepare("UPDATE portal_boxes SET title=?, pos=?, ord=?, active=? WHERE id=?");
            foreach ($_POST['b_id'] as $idx => $bid) {
                $b_title = trim($_POST['b_title'][$idx]);
                $b_pos = ($_POST['b_pos'][$idx] === 'side') ? 'side' : 'main';
                $b_ord = (int)$_POST['b_ord'][$idx];
                $b_act = isset($_POST['b_active'][$bid]) ? 1 : 0;
                $st_up->execute(array($b_title, $b_pos, $b_ord, $b_act, (int)$bid));
            }
        }
        header('Location: ?p=cms&box=layout&msg=saved');
        exit;
    }
    if ($p === 'add_custom_box' && can('cms')) {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $pos = (isset($_POST['pos']) && $_POST['pos'] === 'side') ? 'side' : 'main';
        $ord = (int)(isset($_POST['ord']) ? $_POST['ord'] : 10);
        $content = trim(isset($_POST['content']) ? $_POST['content'] : '');
        $edit_id = (int)(isset($_POST['edit_id']) ? $_POST['edit_id'] : 0);
        if ($title !== '') {
            if ($edit_id) {
                $db->prepare("UPDATE portal_boxes SET title=?, pos=?, ord=?, content=? WHERE id=? AND btype='custom'")->execute(array($title, $pos, $ord, $content, $edit_id));
            } else {
                $bkey = 'custom_' . time() . '_' . rand(10,99);
                $db->prepare("INSERT INTO portal_boxes(bkey,title,btype,content,pos,ord,active) VALUES(?,?,'custom',?,?,?,1)")->execute(array($bkey, $title, $content, $pos, $ord));
            }
        }
        header('Location: ?p=cms&box=layout&msg=saved');
        exit;
    }
    if ($p === 'del_custom_box' && can('cms')) {
        $db->prepare("DELETE FROM portal_boxes WHERE id=? AND btype='custom'")->execute(array((int)$_POST['id']));
        header('Location: ?p=cms&box=layout');
        exit;
    }
    if ($p === 'add_gal' && can('cms')) {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        if (!empty($_FILES['img']['name']) && $_FILES['img']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg','jpeg','png','gif','webp'))) {
                $fn = 'gal_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['img']['tmp_name'], __DIR__ . '/uploads/' . $fn)) {
                    $db->prepare("INSERT INTO gallery(title,img) VALUES(?,?)")->execute(array($title, 'uploads/' . $fn));
                }
            }
        }
        header('Location: ?p=cms&box=gallery&msg=saved');
        exit;
    }
    if ($p === 'del_gal' && can('cms')) {
        $db->prepare("DELETE FROM gallery WHERE id=?")->execute(array((int)$_POST['id']));
        header('Location: ?p=cms&box=gallery');
        exit;
    }
    if ($p === 'imp_xl_rec' && can('imp_exp')) {
        $cnt = 0;
        if (!empty($_FILES['f']['tmp_name'])) {
            $rows = parse_excel($_FILES['f']['tmp_name']);
            $cols = array_keys($F);
            $rt_map = array('کتاب'=>'book', 'مقاله'=>'article', 'پایان‌نامه'=>'thesis', 'پایان نامه'=>'thesis', 'سایر'=>'other', 'سایر منابع'=>'other', 'book'=>'book', 'article'=>'article', 'thesis'=>'thesis', 'other'=>'other');
            foreach ($rows as $idx => $r) {
                $c0 = isset($r[0]) ? $r[0] : '';
                $c2 = isset($r[2]) ? $r[2] : '';
                if ($idx === 0 && (stripos($c0, 'f010') !== false || stripos($c2, 'عنوان') !== false || stripos($c0, 'شابک') !== false)) continue;
                $vals = array();
                foreach ($cols as $i => $col) {
                    $vals[] = isset($r[$i]) ? trim($r[$i]) : ($col === 'f101' ? 'per' : '');
                }
                if (empty($vals[2])) continue;
                $raw_rt = isset($r[17]) ? trim($r[17]) : 'book';
                $rt = isset($rt_map[$raw_rt]) ? $rt_map[$raw_rt] : 'book';
                $cp = max(1, (int)en_num(isset($r[18]) ? $r[18] : 1));
                $lc = is_loc_restricted() ? user_loc() : (!empty($r[20]) ? trim($r[20]) : $Locations[0]);
                $rno = !empty($r[19]) ? trim(en_num($r[19])) : next_reg_no($lc);
                $qs = implode(',', array_fill(0, count($cols) + 4, '?'));
                $params = array_merge($vals, array($rt, $cp, $rno, $lc));
                $db->prepare("INSERT INTO records(" . implode(',', $cols) . ",rtype,copies,reg_no,loc) VALUES($qs)")->execute($params);
                $cnt++;
            }
        }
        header("Location: ?p=imp_exp&msg=xl_$cnt");
        exit;
    }
    if ($p === 'imp_xl_mem' && can('mem')) {
        $cnt = 0;
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        if (!empty($_FILES['f']['tmp_name'])) {
            $rows = parse_excel($_FILES['f']['tmp_name']);
            foreach ($rows as $idx => $r) {
                $c0 = isset($r[0]) ? $r[0] : '';
                if ($idx === 0 && (stripos($c0, 'نام') !== false || stripos($c0, 'name') !== false)) continue;
                if (empty($c0)) continue;
                $db->prepare("INSERT INTO members(name,phone,mcode,loc) VALUES(?,?,?,?)")->execute(array($c0, isset($r[1]) ? $r[1] : '', isset($r[2]) ? $r[2] : '', $def_loc));
                $cnt++;
            }
        }
        header("Location: ?p=mem&msg=imp_$cnt");
        exit;
    }
    if ($p === 'saveuser' && can('users')) {
        $edit_uid = (int)(isset($_POST['edit_uid']) ? $_POST['edit_uid'] : 0);
        $u = trim(isset($_POST['u']) ? $_POST['u'] : '');
        $pw = isset($_POST['pw']) ? $_POST['pw'] : '';
        $uloc = isset($_POST['uloc']) ? trim($_POST['uloc']) : 'all';
        if ($u === 'admin' || $u === 'admin1') $uloc = 'all';
        $perms = (isset($_POST['perms']) && is_array($_POST['perms'])) ? implode(',', $_POST['perms']) : '';
        if ($u !== '') {
            if ($edit_uid > 0) {
                if ($pw !== '') {
                    $db->prepare("UPDATE users SET u=?,pw=?,perms=?,uloc=? WHERE id=?")->execute(array($u, password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc, $edit_uid));
                } else {
                    $db->prepare("UPDATE users SET u=?,perms=?,uloc=? WHERE id=?")->execute(array($u, $perms, $uloc, $edit_uid));
                }
            } else {
                $ex = $db->prepare("SELECT id FROM users WHERE u=?");
                $ex->execute(array($u));
                if ($id = $ex->fetchColumn()) {
                    if ($pw !== '') {
                        $db->prepare("UPDATE users SET pw=?,perms=?,uloc=? WHERE id=?")->execute(array(password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc, $id));
                    } else {
                        $db->prepare("UPDATE users SET perms=?,uloc=? WHERE id=?")->execute(array($perms, $uloc, $id));
                    }
                } elseif ($pw !== '') {
                    $db->prepare("INSERT INTO users(u,pw,perms,uloc) VALUES(?,?,?,?)")->execute(array($u, password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc));
                }
            }
        }
        header('Location: ?p=users&msg=saved');
        exit;
    }
    if ($p === 'deluser' && can('users')) {
        $u = isset($_POST['u']) ? $_POST['u'] : '';
        if ($u !== 'admin' && $u !== 'admin1') {
            $db->prepare("DELETE FROM users WHERE u=?")->execute(array($u));
        }
        header('Location: ?p=users');
        exit;
    }
}

// ==================== نمایش فرم پیش‌نمایش و تست رکوردهای ایزو ۲۷۰۹ قبل از ثبت ====================
function render_iso_preview_box($from_page) {
    global $F, $tok, $Locations;
    if (empty($_SESSION['iso_preview']) || !is_array($_SESSION['iso_preview'])) {
        if (isset($_GET['show_preview'])) {
            echo '<div class="card" style="border:2px solid var(--warn)"><p class="warn">⚠️ هیچ رکورد معتبری در فایل انتخاب‌شده یافت نشد. مطمئن شوید فایل خروجی استاندارد ایزو ۲۷۰۹ کتابخانه ملی باشد.</p></div>';
        }
        return;
    }
    $recs = $_SESSION['iso_preview'];
    $loc = isset($_SESSION['iso_loc']) ? $_SESSION['iso_loc'] : $Locations[0];
    $base_reg = (int)next_reg_no($loc);

    echo '<div class="card" style="border:2px solid var(--ok);background:linear-gradient(145deg,#ffffff,#ecfdf5)">
        <h2><span>🧪 پیش‌نمایش و تست داده‌های استخراج‌شده از فایل ایزو (' . tr_num(count($recs)) . ' رکورد)</span><span class="badge">محل نگهداری: ' . h($loc) . ' (پیش‌شماره: ' . tr_num(get_loc_prefix($loc)) . ')</span></h2>
        <p style="font-size:13px;margin:0 0 12px">تمامی فیلدها و زیرفیلدهای مارک استخراج شدند. می‌توانید مقادیر را بررسی یا ویرایش کرده و سپس روی دکمه <b>«✅ تایید نهایی و ثبت در مخزن»</b> کلیک کنید:</p>
        <form method="post" action="?p=imp_iso_confirm">
            <input type="hidden" name="t" value="' . $tok . '">
            <input type="hidden" name="from_page" value="' . h($from_page) . '">
            <input type="hidden" name="loc" value="' . h($loc) . '">';
    foreach ($recs as $idx => $r) {
        $cur_reg = $base_reg + $idx;
        echo '<div style="border:1px solid var(--line);border-radius:16px;padding:14px;margin-bottom:14px;background:rgba(255,255,255,.9)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;border-bottom:1px dashed var(--line);padding-bottom:8px">
                <label style="font-size:14px;color:var(--blue);margin:0"><input type="checkbox" name="sel[' . $idx . ']" value="1" checked> <b>رکورد شماره ' . tr_num($idx + 1) . ': ' . h($r['f200a']) . '</b></label>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <span style="font-size:12px">شماره ثبت (با پیش‌شماره مرکز):</span><input name="recs[' . $idx . '][reg_no]" value="' . $cur_reg . '" style="width:110px;padding:4px 8px">
                    <span style="font-size:12px">تعداد نسخه:</span><input type="number" name="recs[' . $idx . '][copies]" value="1" min="1" style="width:65px;padding:4px 8px">
                </div>
            </div>
            <div class="grid">';
            foreach ($F as $fk => $flabel) {
                $val = isset($r[$fk]) ? $r[$fk] : '';
                echo '<div><label style="font-size:11.5px">' . h($flabel) . '</label><input name="recs[' . $idx . '][' . $fk . ']" value="' . h($val) . '" style="padding:6px 10px;font-size:12.5px"></div>';
            }
        echo '</div></div>';
    }
    echo '<div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn ok" style="padding:10px 24px;font-size:14px">✅ تایید نهایی و ثبت رکوردها در مخزن</button>
          <a class="btn dng" href="?p=' . h($from_page) . '">انصراف</a></div>
        </form></div>';
}

// ==================== پوسته گرافیکی کریستالی + ریسپانسیو کامل موبایل + حذف نوار بالایی در پرتال ====================
function shell($title, $body, $p, $in) {
    global $AllPerms, $Themes, $db;
    $site_title = get_cms('site_title');
    $sel_font = get_cms('font');
    $sel_theme_key = get_cms('theme');
    $th = isset($Themes[$sel_theme_key]) ? $Themes[$sel_theme_key] : $Themes['royal_dark'];

    $font_urls = array(
        'Vazirmatn' => 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
        'Shabnam'   => 'https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/font-face.css',
        'Sahel'     => 'https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@v3.4.0/dist/font-face.css',
        'Samim'     => 'https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@v4.0.5/dist/font-face.css'
    );
    $font_link = isset($font_urls[$sel_font]) ? '<link href="' . $font_urls[$sel_font] . '" rel="stylesheet" type="text/css" />' : '';

    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5"><title>' . h($title) . ' | نرم‌افزار کتابخانه‌ای آسمان</title>' . $font_link . '<style>
:root{--navy:' . $th['navy'] . ';--navy2:' . $th['navy2'] . ';--blue:' . $th['blue'] . ';--sky:' . $th['sky'] . ';--ice:' . $th['ice'] . ';--line:' . $th['line'] . ';--ok:#10b981;--warn:#e11d48}
*{box-sizing:border-box}
body{margin:0;font-family:"' . h($sel_font) . '",Vazirmatn,"IRANSans",Tahoma,sans-serif;color:var(--navy);text-align:right;background:radial-gradient(1300px 600px at 85% -10%,var(--sky),transparent),radial-gradient(900px 500px at 10% 110%,var(--sky),transparent),linear-gradient(180deg,#ffffff,var(--ice));min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden}
header{position:sticky;top:0;z-index:30;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;background:linear-gradient(135deg,rgba(255,255,255,.88),rgba(255,255,255,.68));backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border-bottom:1px solid rgba(255,255,255,.85);box-shadow:0 8px 32px -12px rgba(0,0,0,.12)}
.brand{font-weight:800;font-size:17.5px;color:var(--navy);display:flex;align-items:center;gap:8px;text-decoration:none}
.app-body{display:flex;flex:1;min-height:calc(100vh - 62px);width:100%}
.sidebar{width:265px;flex-shrink:0;background:linear-gradient(180deg,rgba(255,255,255,.82),rgba(255,255,255,.55));backdrop-filter:blur(16px);border-left:1px solid var(--line);padding:16px 12px;display:flex;flex-direction:column;gap:5px;box-shadow:-6px 0 24px -14px rgba(0,0,0,.1)}
.sidebar a{display:flex;align-items:center;justify-content:space-between;gap:8px;text-decoration:none;color:var(--navy2);padding:8px 13px;border-radius:15px;font-size:12.8px;font-weight:600;transition:all .22s ease;border:1px solid rgba(255,255,255,.65);background:rgba(255,255,255,.5);white-space:nowrap}
.sidebar a:hover{background:rgba(255,255,255,.92);border-color:var(--line);transform:translateX(-3px);box-shadow:0 6px 16px -6px rgba(0,0,0,.1)}
.sidebar a.on{color:#fff;background:linear-gradient(135deg,var(--blue),var(--navy2));border-color:rgba(255,255,255,.35);box-shadow:inset 0 1px 1px rgba(255,255,255,.4),0 8px 20px -6px var(--blue)}
main{flex:1;padding:22px;max-width:1380px;margin:0 auto;width:100%;min-width:0}
/* دکمه‌های کریستالی عمومی */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;text-decoration:none;font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;color:var(--navy2);padding:7px 15px;border-radius:999px;border:1px solid rgba(255,255,255,.9);background:linear-gradient(135deg,rgba(255,255,255,.88),rgba(255,255,255,.48));backdrop-filter:blur(12px);box-shadow:inset 0 1px 2px rgba(255,255,255,.95),0 4px 14px -4px rgba(0,0,0,.12),0 0 0 1px var(--line);transition:all .2s ease;white-space:nowrap}
.btn:hover{background:linear-gradient(135deg,#fff,var(--sky));transform:translateY(-1px);box-shadow:inset 0 1px 2px #fff,0 6px 16px -4px rgba(0,0,0,.18),0 0 0 1px var(--blue)}
.btn.pri{color:#fff;background:linear-gradient(135deg,var(--blue),var(--navy2));border-color:rgba(255,255,255,.35);box-shadow:inset 0 1px 2px rgba(255,255,255,.45),0 6px 18px -4px var(--blue)}
.btn.dng{color:var(--warn);background:linear-gradient(135deg,rgba(255,255,255,.9),rgba(255,228,230,.65));box-shadow:inset 0 1px 1px #fff,0 4px 12px -4px rgba(225,29,72,.2),0 0 0 1px #fecdd3}
.btn.ok{color:#fff;background:linear-gradient(135deg,#10b981,#047857);border-color:rgba(255,255,255,.35);box-shadow:inset 0 1px 2px rgba(255,255,255,.45),0 6px 18px -4px rgba(16,185,129,.45)}
/* دکمه‌های فلت و کوچک‌تر مخصوص عملیات جلوی منابع و جداول */
.btn-sm{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font:inherit;font-size:11.2px;font-weight:600;cursor:pointer;padding:3px 9px;border-radius:7px;border:1px solid var(--line);background:#f8fafc;color:var(--navy2);box-shadow:none;transition:.15s;white-space:nowrap}
.btn-sm:hover{background:var(--sky);border-color:var(--blue);transform:none}
.btn-sm.pri{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-sm.pri:hover{opacity:.9}
.btn-sm.dng{background:#fff1f2;color:var(--warn);border-color:#fecdd3}
.btn-sm.dng:hover{background:var(--warn);color:#fff}
.btn-sm.ok{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
.btn-sm.ok:hover{background:#10b981;color:#fff}
h1{font-size:19.5px;margin:0 0 16px;color:var(--navy);font-weight:800;line-height:1.4}
h2{font-size:15px;margin:0 0 14px;color:var(--navy2);border-bottom:1px solid var(--line);padding-bottom:9px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.card{background:linear-gradient(145deg,rgba(255,255,255,.9),rgba(255,255,255,.68));backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.92);border-radius:22px;padding:18px 20px;box-shadow:inset 0 1px 2px #fff,0 14px 34px -18px rgba(0,0,0,.15),0 0 0 1px var(--line);margin-bottom:18px;max-width:100%;overflow:hidden}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:14px}.stats b{display:block;font-size:24px;color:var(--blue);margin-bottom:4px}
.tbl-scroll{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:14px}
table{width:100%;border-collapse:separate;border-spacing:0;font-size:12.8px;border-radius:14px;overflow:hidden}
th{background:linear-gradient(180deg,var(--sky),rgba(255,255,255,.75));color:var(--navy);text-align:right;padding:10px;border-bottom:2px solid var(--line);cursor:pointer;user-select:none;transition:.2s;white-space:nowrap}
th:hover{background:var(--line)}
td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:right;vertical-align:middle;background:rgba(255,255,255,.5)}
tr:hover td{background:rgba(255,255,255,.9)}
input,select,textarea{font:inherit;width:100%;padding:8px 12px;border:1px solid var(--line);border-radius:13px;background:rgba(255,255,255,.9);color:var(--navy);text-align:right;transition:.2s;max-width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px var(--sky)}
input[type=checkbox],input[type=radio]{width:auto}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
label{font-size:12.2px;display:block;margin-bottom:5px;font-weight:600;color:var(--navy2)}
.row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.tabs{display:flex;gap:7px;margin-bottom:16px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:10px}
.tabs a{text-decoration:none;padding:6px 14px;border-radius:999px;color:var(--navy2);background:rgba(255,255,255,.8);border:1px solid var(--line);font-size:12.5px;font-weight:600;transition:.2s;white-space:nowrap}
.tabs a.on{background:linear-gradient(135deg,var(--blue),var(--navy2));color:#fff;border-color:transparent;box-shadow:0 4px 12px -3px var(--blue)}
.badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;background:var(--sky);color:var(--navy2);font-weight:600;border:1px solid rgba(255,255,255,.8);white-space:nowrap}
.warn{color:var(--warn);font-weight:600}
.ok-msg{color:#065f46;background:rgba(209,250,229,.9);border:1px solid #6ee7b7;padding:12px 16px;border-radius:16px;margin-bottom:16px;font-weight:600}
.portal-hero{background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;padding:24px 26px;border-radius:24px;margin-bottom:20px;box-shadow:inset 0 1px 2px rgba(255,255,255,.35),0 16px 36px -14px rgba(0,0,0,.35);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px}
.portal-layout{display:grid;grid-template-columns:1fr 330px;gap:20px;align-items:start}
.list-clean{list-style:none;padding:0;margin:0}.list-clean li{padding:8px 0;border-bottom:1px dashed var(--line);font-size:13px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}.list-clean li:last-child{border-bottom:none}
.chart-wrap{display:flex;align-items:flex-end;gap:8px;height:185px;padding:22px 8px 6px;border-bottom:2px solid var(--line);overflow-x:auto;-webkit-overflow-scrolling:touch}
.chart-col{flex:1;min-width:42px;display:flex;flex-direction:column;align-items:center;gap:5px}
.chart-bar{width:100%;max-width:36px;background:linear-gradient(180deg,var(--blue),var(--navy));border-radius:9px 9px 0 0;transition:.3s;min-height:6px}
.gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px}
.gal-item{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:rgba(255,255,255,.85)}
.gal-item img{width:100%;height:100px;object-fit:cover;display:block}
.gal-item div{padding:6px 8px;font-size:11.2px;font-weight:600;text-align:center}
.rte-bar{display:flex;gap:5px;flex-wrap:wrap;background:var(--sky);padding:7px 10px;border:1px solid var(--line);border-bottom:none;border-radius:14px 14px 0 0}
.rte-bar button{background:rgba(255,255,255,.92);border:1px solid var(--line);border-radius:999px;padding:4px 10px;cursor:pointer;font:inherit;font-size:11.5px;font-weight:700;color:var(--navy)}
.rte-area{border:1px solid var(--line);border-radius:0 0 14px 14px;background:rgba(255,255,255,.95);min-height:130px;padding:12px;outline:none;line-height:1.7;font-size:13.2px}
.tbl-filter-bar{margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.tbl-filter-bar input{max-width:280px;padding:6px 12px;font-size:12.2px;border-radius:999px}
/* بهینه‌سازی کامل و اختصاصی برای موبایل و تبلت */
@media(max-width:920px){
    header{padding:10px 14px}
    .brand{font-size:15px}
    .app-body{flex-direction:column}
    .sidebar{width:100%;border-left:none;border-bottom:1px solid var(--line);flex-direction:row;overflow-x:auto;padding:10px 12px;gap:6px;-webkit-overflow-scrolling:touch}
    .sidebar a{padding:7px 12px;font-size:12px;flex-shrink:0}
    .sidebar a:hover{transform:none}
    main{padding:14px 10px}
    .portal-layout{grid-template-columns:1fr;gap:14px}
    .portal-hero{padding:18px 16px;border-radius:18px;text-align:center;justify-content:center}
    .portal-hero h1{font-size:19px!important}
    .card{padding:14px 12px;border-radius:18px;margin-bottom:14px}
    .grid{grid-template-columns:1fr;gap:10px}
    .stats{grid-template-columns:repeat(2,1fr);gap:10px}
    .stats b{font-size:20px}
    .row{flex-direction:column;align-items:stretch}
    .row > div{width:100%!important}
    .tabs{overflow-x:auto;flex-wrap:nowrap;padding-bottom:8px;-webkit-overflow-scrolling:touch}
    .tbl-filter-bar{flex-direction:column;align-items:stretch}
    .tbl-filter-bar input{max-width:100%}
    h1{font-size:17px}
    h2{font-size:14px}
}
@media print{header,.sidebar,.no-print,.tbl-filter-bar{display:none!important}main{max-width:100%;padding:0;margin:0}body{background:#fff}.card{box-shadow:none;border:none;padding:0;margin:0;background:transparent}}
</style></head><body>';

    // نوار عنوان بالایی، تاریخ و ساعت فقط زمانی نمایش داده شود که کاربر وارد شده باشد ($in === true)
    if ($in) {
        $ul_badge = is_loc_restricted() ? 'محل: ' . user_loc() . ' (پیش‌شماره ' . tr_num(get_loc_prefix(user_loc())) . ')' : 'دسترسی کل کتابخانه';
        echo '<header><a href="?p=home" class="brand">📚 نرم‌افزار کتابخانه‌ای آسمان (' . h($site_title) . ')</a>';
        echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
        echo '<span class="badge" style="background:#fff;font-size:12px">📅 ' . jdate('now') . ' | 🕒 <span id="live_clock">' . tr_num(date('H:i:s')) . '</span></span>';
        echo '<span class="badge">' . h($ul_badge) . '</span>';
        if ($p === 'home' || $p === 'news_list') echo '<a class="btn-sm pri" href="?p=dash">پنل مدیریت</a>';
        else echo '<a class="btn-sm" href="?p=home">مشاهده پرتال</a>';
        echo '<span style="font-size:12px">کاربر: <b>' . h($_SESSION['u']) . '</b></span><a class="btn-sm dng" href="?p=logout">خروج</a>';
        echo '</div></header>';
    }

    echo '<div class="app-body">';
    if ($in && !in_array($p, array('home', 'news_list'))) {
        $pend_orders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""))->fetchColumn();
        $open_tickets = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status='open'" . (is_super_admin() ? "" : " AND sender=" . $db->quote($_SESSION['u'])))->fetchColumn();
        echo '<aside class="sidebar">';
        $icons = array('dash'=>'📊','rec'=>'📖','form'=>'➕','org'=>'🏷️','locs'=>'📍','orders'=>'🛒','tickets'=>'🎫','nlai'=>'🏛️','imp_exp'=>'🔄','backup'=>'💾','mem'=>'👥','loan'=>'📅','rep'=>'📈','cms'=>'🌐','users'=>'🔐');
        foreach ($AllPerms as $k => $v) {
            if (can($k)) {
                $cnt_b = '';
                if ($k === 'orders' && $pend_orders > 0) $cnt_b = '<span class="badge" style="background:var(--warn);color:#fff">' . tr_num($pend_orders) . '</span>';
                if ($k === 'tickets' && $open_tickets > 0) $cnt_b = '<span class="badge" style="background:var(--ok);color:#fff">' . tr_num($open_tickets) . '</span>';
                echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span>' . (isset($icons[$k]) ? $icons[$k] : '🔹') . ' ' . $v . '</span>' . $cnt_b . '</a>';
            }
        }
        echo '</aside>';
    }
    echo '<main>' . $body . '</main></div>';
    echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // ساعت زنده فارسی برای کاربران واردشده
    var clk = document.getElementById("live_clock");
    if (clk) {
        var e2p = function(s) { return String(s).replace(/[0-9]/g, function(d) { return "۰۱۲۳۴۵۶۷۸۹"[d]; }); };
        setInterval(function() {
            var d = new Date();
            var hh = String(d.getHours()).padStart(2, "0");
            var mm = String(d.getMinutes()).padStart(2, "0");
            var ss = String(d.getSeconds()).padStart(2, "0");
            clk.innerText = e2p(hh + ":" + mm + ":" + ss);
        }, 1000);
    }

    // قرار دادن خودکار تمام جداول در محفظه اسکرول افقی موبایل + افزودن سورت و فیلتر
    var tables = document.querySelectorAll("table:not(.marc)");
    tables.forEach(function(tbl) {
        if (!tbl.parentElement.classList.contains("tbl-scroll")) {
            var wrap = document.createElement("div");
            wrap.className = "tbl-scroll";
            tbl.parentNode.insertBefore(wrap, tbl);
            wrap.appendChild(tbl);
        }
        var trs = tbl.querySelectorAll("tr");
        if (trs.length < 2) return;
        var headerRow = trs[0];
        var ths = headerRow.querySelectorAll("th");
        if (ths.length === 0) return;

        if (trs.length > 2) {
            var bar = document.createElement("div");
            bar.className = "tbl-filter-bar no-print";
            var inp = document.createElement("input");
            inp.type = "text";
            inp.placeholder = "🔍 فیلتر سریع و جستجو در این جدول...";
            var hint = document.createElement("small");
            hint.style.color = "var(--navy2)";
            hint.innerHTML = "💡 برای مرتب‌سازی روی سرستون کلیک کنید";
            bar.appendChild(inp);
            bar.appendChild(hint);
            tbl.parentElement.parentNode.insertBefore(bar, tbl.parentElement);

            inp.addEventListener("input", function() {
                var val = inp.value.trim().toLowerCase();
                var allRows = Array.from(tbl.querySelectorAll("tr")).slice(1);
                allRows.forEach(function(r) {
                    r.style.display = r.innerText.toLowerCase().indexOf(val) > -1 ? "" : "none";
                });
            });
        }

        ths.forEach(function(th, colIdx) {
            if (th.innerText.trim() === "") return;
            th.title = "کلیک جهت مرتب‌سازی بر اساس این ستون";
            var icon = document.createElement("span");
            icon.style.fontSize = "10px";
            icon.style.marginRight = "4px";
            icon.style.opacity = "0.55";
            icon.innerText = "⇅";
            th.appendChild(icon);

            var asc = true;
            th.addEventListener("click", function() {
                var rows = Array.from(tbl.querySelectorAll("tr")).slice(1);
                rows.sort(function(a, b) {
                    var cellA = a.children[colIdx] ? a.children[colIdx].innerText.trim() : "";
                    var cellB = b.children[colIdx] ? b.children[colIdx].innerText.trim() : "";
                    var p2e = function(s) { return s.replace(/[۰-۹]/g, function(d) { return "۰۱۲۳۴۵۶۷۸۹".indexOf(d); }); };
                    var nA = parseFloat(p2e(cellA).replace(/[^0-9.-]/g, ""));
                    var nB = parseFloat(p2e(cellB).replace(/[^0-9.-]/g, ""));
                    if (!isNaN(nA) && !isNaN(nB) && p2e(cellA).match(/^[0-9\s\/.-]+$/)) {
                        return asc ? nA - nB : nB - nA;
                    }
                    return asc ? cellA.localeCompare(cellB, "fa") : cellB.localeCompare(cellA, "fa");
                });
                asc = !asc;
                icon.innerText = asc ? "▲" : "▼";
                rows.forEach(function(r) { tbl.appendChild(r); });
            });
        });
    });
});
</script></body></html>';
}

function post_btn($act, $label, $fields, $cls = 'btn') {
    global $tok;
    $o = '<form method="post" action="?p=' . $act . '" style="display:inline" onsubmit="return confirm(\'آیا از انجام این عملیات مطمئن هستید؟\')">';
    foreach ($fields as $k => $v) $o .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $o . '<input type="hidden" name="t" value="' . $tok . '"><button class="' . $cls . '">' . $label . '</button></form>';
}

function render_portal_box($bx) {
    global $db, $RTypes, $Locations, $tok, $in, $err;
    $bkey = $bx['bkey'];
    $btitle = $bx['title'];

    if ($bx['btype'] === 'custom') {
        echo '<div class="card"><h2>✨ ' . h($btitle) . '</h2><div style="font-size:13.5px;line-height:1.8">' . $bx['content'] . '</div></div>';
        return;
    }

    if ($bkey === 'search') {
        $smode = isset($_GET['smode']) ? $_GET['smode'] : 'simple';
        $sq = trim(isset($_GET['sq']) ? $_GET['sq'] : '');
        $stitle = trim(isset($_GET['stitle']) ? $_GET['stitle'] : '');
        $sauthor = trim(isset($_GET['sauthor']) ? $_GET['sauthor'] : '');
        $spub = trim(isset($_GET['spub']) ? $_GET['spub'] : '');
        $ssubj = trim(isset($_GET['ssubj']) ? $_GET['ssubj'] : '');
        $sisbn = trim(isset($_GET['sisbn']) ? $_GET['sisbn'] : '');
        $sloc = trim(isset($_GET['sloc']) ? $_GET['sloc'] : '');
        $srtype = trim(isset($_GET['srtype']) ? $_GET['srtype'] : '');

        echo '<div class="card" style="border:2px solid var(--blue)">
            <div class="tabs" style="margin-bottom:14px">
                <a class="' . ($smode === 'simple' ? 'on' : '') . '" href="?p=home&smode=simple">🔍 جستجوی ساده منابع</a>
                <a class="' . ($smode === 'adv' ? 'on' : '') . '" href="?p=home&smode=adv">⚙️ جستجوی پیشرفته</a>
                <a class="' . ($smode === 'order' ? 'on' : '') . '" href="?p=home&smode=order">🛒 سفارش و پیشنهاد خرید منبع</a>
            </div>';
        if ($smode === 'simple') {
            echo '<form method="get" class="row"><input type="hidden" name="p" value="home"><input type="hidden" name="smode" value="simple">
                  <div style="flex:1"><label>کلیدواژه (عنوان، نویسنده، موضوع، ناشر، شابک یا شماره ثبت)</label><input name="sq" value="' . h($sq) . '" placeholder="عبارت مورد نظر را وارد نمایید..."></div>
                  <div style="width:150px"><label>محل نگهداری</label><select name="sloc"><option value="">همه بخش‌ها</option>';
                  foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($sloc === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
            echo '</select></div>
                  <div style="width:140px"><label>نوع منبع</label><select name="srtype"><option value="">همه منابع</option>';
                  foreach ($RTypes as $k => $v) echo '<option value="' . $k . '"' . ($srtype === $k ? ' selected' : '') . '>' . $v . '</option>';
            echo '</select></div><button class="btn pri" style="padding:9px 22px">جستجو</button></form>';
        } elseif ($smode === 'adv') {
            echo '<form method="get"><input type="hidden" name="p" value="home"><input type="hidden" name="smode" value="adv"><div class="grid">
                  <div><label>عنوان منبع</label><input name="stitle" value="' . h($stitle) . '"></div>
                  <div><label>پدیدآور / نویسنده</label><input name="sauthor" value="' . h($sauthor) . '"></div>
                  <div><label>موضوع</label><input name="ssubj" value="' . h($ssubj) . '"></div>
                  <div><label>ناشر</label><input name="spub" value="' . h($spub) . '"></div>
                  <div><label>شابک / شاپا</label><input name="sisbn" value="' . h($sisbn) . '"></div>
                  <div><label>محل نگهداری</label><select name="sloc"><option value="">همه بخش‌ها</option>';
                  foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($sloc === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
            echo '</select></div><div><label>نوع منبع</label><select name="srtype"><option value="">همه</option>';
                  foreach ($RTypes as $k => $v) echo '<option value="' . $k . '"' . ($srtype === $k ? ' selected' : '') . '>' . $v . '</option>';
            echo '</select></div></div><br><button class="btn pri">اجرای جستجوی پیشرفته</button> <a class="btn" href="?p=home&smode=adv">پاکسازی</a></form>';
        } else {
            echo '<form method="post" action="?p=add_order"><input type="hidden" name="t" value="' . $tok . '">
                  <p style="margin:0 0 12px;font-size:13px">چنانچه کتاب یا منبعی را در مخزن نیافتید، مشخصات آن را وارد کرده و <b>محل نگهداری</b> مورد نظر خود را جهت تهیه انتخاب نمایید:</p>
                  <div class="grid">
                      <div><label>عنوان کتاب / منبع *</label><input name="title" required placeholder="نام دقیق کتاب..."></div>
                      <div><label>نویسنده / پدیدآور</label><input name="author" placeholder="نام نویسنده یا مترجم..."></div>
                      <div><label>ناشر / سال نشر</label><input name="pub"></div>
                      <div><label>شابک (ISBN)</label><input name="isbn"></div>
                      <div><label style="color:var(--blue)">انتخاب محل نگهداری جهت ارسال سفارش *</label><select name="loc" required>';
                      foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
            echo '    </select></div>
                      <div><label>نام درخواست‌کننده *</label><input name="requester" required placeholder="نام و نام خانوادگی شما"></div>
                      <div><label>شماره تماس (جهت اطلاع‌رسانی)</label><input name="phone" placeholder="09..."></div>
                  </div><br><button class="btn ok">📤 ثبت و ارسال سفارش منبع</button></form>';
        }

        $w = array(); $par = array();
        if ($smode === 'simple' && ($sq !== '' || $sloc !== '' || $srtype !== '')) {
            if ($sq !== '') {
                $w[] = "(f200a LIKE :q OR f700 LIKE :q OR f200f LIKE :q OR f606 LIKE :q OR f210c LIKE :q OR f010 LIKE :q OR reg_no LIKE :q)";
                $par[':q'] = "%$sq%";
            }
            if ($sloc !== '') { $w[] = "loc = :l"; $par[':l'] = $sloc; }
        } elseif ($smode === 'adv') {
            if ($stitle !== '') { $w[] = "f200a LIKE :t"; $par[':t'] = "%$stitle%"; }
            if ($sauthor !== '') { $w[] = "(f700 LIKE :a OR f200f LIKE :a)"; $par[':a'] = "%$sauthor%"; }
            if ($ssubj !== '') { $w[] = "f606 LIKE :s"; $par[':s'] = "%$ssubj%"; }
            if ($spub !== '') { $w[] = "f210c LIKE :p"; $par[':p'] = "%$spub%"; }
            if ($sisbn !== '') { $w[] = "f010 LIKE :i"; $par[':i'] = "%$sisbn%"; }
            if ($sloc !== '') { $w[] = "loc = :l"; $par[':l'] = $sloc; }
        }
        if ($srtype !== '' && $smode !== 'order') { $w[] = "rtype = :rt"; $par[':rt'] = $srtype; }

        if ($w) {
            $sql = "SELECT r.*, (r.copies - (SELECT COUNT(*) FROM loans l WHERE l.rid=r.id AND l.ret IS NULL)) AS avail FROM records r WHERE " . implode(' AND ', $w) . " ORDER BY id DESC LIMIT 40";
            $st = $db->prepare($sql); $st->execute($par); $res = $st->fetchAll(PDO::FETCH_ASSOC);
            echo '<div style="margin-top:18px;padding-top:14px;border-top:2px dashed var(--line)"><h3>نتایج جستجو (' . tr_num(count($res)) . ' مورد):</h3>';
            if ($res) {
                echo '<table><tr><th>شماره ثبت</th><th>عنوان</th><th>پدیدآور</th><th>ناشر / سال</th><th>محل نگهداری</th><th>رده</th><th>وضعیت</th><th>پیوست</th></tr>';
                foreach ($res as $r) {
                    echo '<tr><td>' . tr_num(h($r['reg_no'])) . '</td><td><b>' . h($r['f200a']) . '</b> <span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></td>
                    <td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td><td>' . h($r['f210c']) . ' (' . tr_num(h($r['f210d'])) . ')</td>
                    <td><span class="badge">' . h($r['loc']) . '</span></td><td>' . h($r['f676'] ? $r['f676'] : $r['f686']) . '</td>
                    <td>' . ($r['avail'] > 0 ? '<span style="color:var(--ok);font-weight:700">موجود (' . tr_num($r['avail']) . ')</span>' : '<span class="warn">امانت</span>') . '</td>
                    <td>' . (!empty($r['file']) ? '<a class="btn-sm" href="' . h($r['file']) . '" target="_blank">📎 فایل</a>' : '-') . '</td></tr>';
                }
                echo '</table>';
            } else echo '<p class="warn">هیچ منبعی یافت نشد. می‌توانید از تب «سفارش و پیشنهاد خرید منبع» درخواست تهیه آن را ثبت کنید.</p>';
            echo '</div>';
        }
        echo '</div>';

    } elseif ($bkey === 'news') {
        echo '<div class="card"><h2><span>📰 ' . h($btitle) . '</span><a class="btn-sm pri" href="?p=news_list">مشروح اخبار ⬅</a></h2><ul class="list-clean">';
        $news_arr = array_values(array_filter(explode("\n", get_cms('news'))));
        foreach ($news_arr as $idx => $line) {
            $parts = explode('|', $line);
            $ntitle = trim($parts[0]);
            $ndate = isset($parts[1]) ? trim($parts[1]) : '';
            echo '<li><a href="?p=news_list&id=' . $idx . '" style="text-decoration:none;color:var(--navy);font-weight:600">🔹 ' . h($ntitle) . '</a>
                  <div style="display:flex;gap:6px;align-items:center">' . ($ndate ? '<span class="badge">' . h($ndate) . '</span>' : '') . '<a class="btn-sm" href="?p=news_list&id=' . $idx . '">متن کامل</a></div></li>';
        }
        echo '</ul></div>';

    } elseif ($bkey === 'chart') {
        $g_lines = array_filter(array_map('trim', explode("\n", get_cms('growth_chart'))));
        if ($g_lines) {
            $g_data = array(); $max_v = 1;
            foreach ($g_lines as $gl) {
                $pts = explode('|', $gl);
                if (count($pts) >= 2) {
                    $v = (int)en_num(trim($pts[1]));
                    if ($v > $max_v) $max_v = $v;
                    $g_data[] = array('m' => trim($pts[0]), 'v' => $v);
                }
            }
            if ($g_data) {
                echo '<div class="card"><h2>📈 ' . h($btitle) . '</h2><div class="chart-wrap">';
                foreach ($g_data as $gd) {
                    $h_px = max(8, round(($gd['v'] / $max_v) * 140));
                    echo '<div class="chart-col"><span style="font-size:11.5px;font-weight:700;color:var(--blue)">' . tr_num($gd['v']) . '</span>
                          <div class="chart-bar" style="height:' . $h_px . 'px" title="' . h($gd['m']) . ': ' . tr_num($gd['v']) . '"></div>
                          <span style="font-size:11.5px;font-weight:600">' . h($gd['m']) . '</span></div>';
                }
                echo '</div></div>';
            }
        }

    } elseif ($bkey === 'services_new') {
        echo '<div class="grid">
            <div class="card"><h2>🛎️ خدمات کتابخانه</h2><ul class="list-clean">';
            foreach (array_filter(explode("\n", get_cms('services'))) as $srv) {
                echo '<li>✅ ' . h(trim($srv)) . '</li>';
            }
        echo '</ul></div>
            <div class="card"><h2>🆕 تازه‌های کتابخانه</h2><ul class="list-clean">';
            foreach ($db->query("SELECT f200a, f700, loc FROM records ORDER BY id DESC LIMIT 5") as $nb) {
                echo '<li><span><b>' . h($nb['f200a']) . '</b> (' . h($nb['f700']) . ')</span><span class="badge">' . h($nb['loc']) . '</span></li>';
            }
        echo '</ul></div></div>';

    } elseif ($bkey === 'about_contact') {
        echo '<div class="grid">
            <div class="card"><h2>ℹ️ درباره ما</h2><div style="font-size:13.5px;line-height:1.8">' . get_cms('about') . '</div></div>
            <div class="card"><h2>📞 تماس با ما</h2><div style="font-size:13.5px;line-height:1.8">' . get_cms('contact') . '</div></div>
        </div>';

    } elseif ($bkey === 'login') {
        echo '<div class="card" style="border-top:4px solid var(--navy)"><h2>🔐 ' . h($btitle) . '</h2>';
        if ($in) {
            echo '<p style="font-size:13.5px">شما با نام کاربری <b>' . h($_SESSION['u']) . '</b> وارد شده‌اید.</p>
                  <a class="btn pri" style="width:100%;margin-bottom:8px" href="?p=dash">ورود به پنل مدیریت</a>
                  <a class="btn dng" style="width:100%" href="?p=logout">خروج از حساب</a>';
        } else {
            if (isset($err)) echo '<p class="warn" style="font-size:13px">' . h($err) . '</p>';
            echo '<form method="post" action="?p=login"><input type="hidden" name="t" value="' . $tok . '">
                  <label>نام کاربری</label><input name="u" required><br><br>
                  <label>گذرواژه</label><input name="pw" type="password" required><br><br>
                  <button class="btn pri" style="width:100%;padding:10px">ورود به سامانه</button></form>';
        }
        echo '</div>';

    } elseif ($bkey === 'stats') {
        $n_rec = $db->query("SELECT COUNT(*),COALESCE(SUM(copies),0) FROM records")->fetch(PDO::FETCH_NUM);
        $n_mem = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $n_loan = $db->query("SELECT COUNT(*) FROM loans")->fetchColumn();
        echo '<div class="card"><h2>📊 ' . h($btitle) . '</h2><ul class="list-clean">
            <li><span>کل عناوین منابع:</span><b>' . tr_num($n_rec[0]) . ' عنوان</b></li>
            <li><span>مجموع نسخه‌های موجود:</span><b>' . tr_num($n_rec[1]) . ' نسخه</b></li>
            <li><span>اعضای فعال کتابخانه:</span><b>' . tr_num($n_mem) . ' نفر</b></li>
            <li><span>مجموع گردش امانت:</span><b>' . tr_num($n_loan) . ' مورد</b></li>';
            foreach ($RTypes as $k => $v) {
                $c = $db->prepare("SELECT COUNT(*) FROM records WHERE rtype=?"); $c->execute(array($k));
                echo '<li><span>تعداد ' . $v . ':</span><span>' . tr_num($c->fetchColumn()) . '</span></li>';
            }
        echo '</ul></div>';

    } elseif ($bkey === 'gallery') {
        $gals = $db->query("SELECT * FROM gallery ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
        echo '<div class="card"><h2>🖼️ ' . h($btitle) . '</h2>';
        if ($gals) {
            echo '<div class="gal-grid">';
            foreach ($gals as $g) {
                echo '<div class="gal-item"><a href="' . h($g['img']) . '" target="_blank"><img src="' . h($g['img']) . '" alt="' . h($g['title']) . '"></a><div>' . h($g['title']) . '</div></div>';
            }
            echo '</div>';
        } else {
            echo '<p style="font-size:12.5px;margin:0;opacity:.75">تصویری در گالری ثبت نشده است.</p>';
        }
        echo '</div>';

    } elseif ($bkey === 'links') {
        echo '<div class="card"><h2>🔗 ' . h($btitle) . '</h2><ul class="list-clean">';
        foreach (array_filter(explode("\n", get_cms('links'))) as $lnk) {
            $parts = explode('|', $lnk);
            $title_l = trim($parts[0]);
            $url_l = trim(isset($parts[1]) ? $parts[1] : '#');
            echo '<li><a href="' . h($url_l) . '" target="_blank" style="text-decoration:none;color:var(--blue);font-weight:600">🌐 ' . h($title_l) . '</a></li>';
        }
        echo '</ul></div>';
    }
}

ob_start();

// ==================== صفحه اول پرتال (بدون نوار عنوان بالایی برای کاربران مهمان) ====================
if ($p === 'home') {
    $site_title = get_cms('site_title');
    $site_sub = get_cms('site_sub');
    if (isset($_GET['order_ok'])) {
        echo '<div class="ok-msg">✅ درخواست سفارش منبع شما با موفقیت ثبت و به محل نگهداری مربوطه ارسال شد.</div>';
    }
    echo '<div class="portal-hero"><div><h1 style="color:#fff;margin:0 0 6px;font-size:23px">🏛️ ' . h($site_title) . '</h1><p style="margin:0;opacity:.92;font-size:13.5px">' . h($site_sub) . '</p></div>
          <div style="text-align:left;font-size:12.5px;background:rgba(255,255,255,.18);padding:8px 16px;border-radius:999px">پرتال جامع کتابخانه آسمان</div></div>';

    echo '<div class="portal-layout"><div>';
    foreach ($db->query("SELECT * FROM portal_boxes WHERE active=1 AND pos='main' ORDER BY ord ASC, id ASC") as $bx) {
        render_portal_box($bx);
    }
    echo '</div><div>';
    foreach ($db->query("SELECT * FROM portal_boxes WHERE active=1 AND pos='side' ORDER BY ord ASC, id ASC") as $bx) {
        render_portal_box($bx);
    }
    echo '</div></div>';

// ==================== صفحه مشروح اخبار ====================
} elseif ($p === 'news_list') {
    $news_arr = array_values(array_filter(explode("\n", get_cms('news'))));
    $sel_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
    echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h1 style="margin:0">📰 آرشیو و مشروح اخبار و اطلاعیه‌های کتابخانه</h1>
            <a class="btn pri" href="?p=home">🏠 بازگشت به پرتال</a>
          </div>';
    if ($sel_id >= 0 && isset($news_arr[$sel_id])) {
        $pts = explode('|', $news_arr[$sel_id]);
        $nt = trim($pts[0]);
        $nd = isset($pts[1]) ? trim($pts[1]) : jdate('now');
        $nb = isset($pts[2]) ? trim($pts[2]) : $nt;
        echo '<div class="card" style="border:2px solid var(--blue)">
            <h2><span>📌 ' . h($nt) . '</span><span class="badge">تاریخ انتشار: ' . h($nd) . '</span></h2>
            <p style="font-size:14px;line-height:2;text-align:justify">' . nl2br(h($nb)) . '</p>
            <a class="btn pri" href="?p=news_list">مشاهده همه اخبار</a> <a class="btn" href="?p=home">بازگشت به صفحه اول پرتال</a>
        </div>';
    }
    echo '<div class="card"><h2>فهرست کامل اخبار و اطلاعیه‌ها</h2>';
    foreach ($news_arr as $idx => $line) {
        $pts = explode('|', $line);
        $nt = trim($pts[0]);
        $nd = isset($pts[1]) ? trim($pts[1]) : '';
        $nb = isset($pts[2]) ? trim($pts[2]) : 'جهت مطالعه جزئیات کامل این اطلاعیه کلیک نمایید.';
        echo '<div style="padding:14px 0;border-bottom:1px dashed var(--line)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px">
                <a href="?p=news_list&id=' . $idx . '" style="font-size:14.5px;font-weight:800;color:var(--blue);text-decoration:none">🔹 ' . h($nt) . '</a>
                ' . ($nd ? '<span class="badge">' . h($nd) . '</span>' : '') . '
            </div>
            <p style="margin:0;font-size:13px;color:#334155;line-height:1.8">' . h($nb) . '</p>
        </div>';
    }
    echo '</div>';

} elseif (!can($p) && $p !== 'marc') {
    echo '<div class="card"><p class="warn">شما مجوز دسترسی به این بخش را ندارید.</p></div>';

// ==================== پیشخوان مدیریت ====================
} elseif ($p === 'dash') {
    $w_rec = is_loc_restricted() ? "WHERE loc=" . $db->quote(user_loc()) : "";
    $w_loan = is_loc_restricted() ? "AND r.loc=" . $db->quote(user_loc()) : "";
    $n = $db->query("SELECT COUNT(*),COALESCE(SUM(copies),0) FROM records $w_rec")->fetch(PDO::FETCH_NUM);
    $m = $db->query("SELECT COUNT(*) FROM members $w_rec")->fetchColumn();
    $o = $db->query("SELECT COUNT(*) FROM loans l JOIN records r ON r.id=l.rid WHERE l.ret IS NULL $w_loan")->fetchColumn();
    $late = $db->query("SELECT COUNT(*) FROM loans l JOIN records r ON r.id=l.rid WHERE l.ret IS NULL AND l.due<date('now') $w_loan")->fetchColumn();
    
    $scope_title = is_loc_restricted() ? ' (محل نگهداری: ' . user_loc() . ' — پیش‌شماره ثبت: ' . tr_num(get_loc_prefix(user_loc())) . ')' : ' (کل مجموعه کتابخانه)';
    echo "<h1>پیشخوان نرم‌افزار کتابخانه‌ای آسمان" . h($scope_title) . "</h1><div class='stats'>
        <div class='card'><b>" . tr_num($n[0]) . "</b>رکورد کتابشناختی</div>
        <div class='card'><b>" . tr_num($n[1]) . "</b>تعداد کل نسخه‌ها</div>
        <div class='card'><b>" . tr_num($m) . "</b>اعضای ثبت‌شده</div>
        <div class='card'><b>" . tr_num($o) . "</b>امانت‌های جاری</div>
        <div class='card'><b style='color:var(--warn)'>" . tr_num($late) . "</b>امانت‌های دارای دیرکرد</div>
    </div>";
    echo '<div class="grid"><div class="card"><h2>موجودی و پیش‌شماره ثبت مراکز</h2><table><tr><th>محل نگهداری</th><th>پیش‌شماره ثبت</th><th>تعداد عنوان</th><th>مجموع نسخه‌ها</th></tr>';
    foreach ($db->query("SELECT loc, COUNT(*) as c, COALESCE(SUM(copies),0) as s FROM records $w_rec GROUP BY loc") as $lr) {
        $l_name = $lr['loc'] ? $lr['loc'] : 'مخزن اصلی';
        echo '<tr><td><b>' . h($l_name) . '</b></td><td><span class="badge">' . tr_num(get_loc_prefix($l_name)) . '</span></td><td>' . tr_num($lr['c']) . '</td><td>' . tr_num($lr['s']) . '</td></tr>';
    }
    echo '</table></div><div class="card"><h2>آخرین منابع ثبت‌شده</h2><table><tr><th>شماره ثبت</th><th>عنوان</th><th>محل نگهداری</th><th>تاریخ ثبت</th></tr>';
    foreach ($db->query("SELECT id,reg_no,f200a,loc,created FROM records $w_rec ORDER BY id DESC LIMIT 6") as $r) {
        echo '<tr><td><b>' . tr_num(h($r['reg_no'])) . '</b></td><td><a href="?p=marc&id=' . $r['id'] . '">' . h($r['f200a']) . '</a></td><td><span class="badge">' . h($r['loc']) . '</span></td><td>' . jdate($r['created']) . '</td></tr>';
    }
    echo '</table></div></div>';

// ==================== مدیریت محل نگهداری و پیش‌شماره‌های ثبت (۱۰، ۱۱، ۱۲ و...) ====================
} elseif ($p === 'locs') {
    $map_locs = get_locations_map();
    $max_pref = 9;
    foreach ($map_locs as $pv) { if ((int)$pv > $max_pref) $max_pref = (int)$pv; }
    $next_pref_suggest = $max_pref + 1;

    echo '<h1>📍 مدیریت محل‌های نگهداری و پیش‌شماره‌های ثبت (۱۰، ۱۱، ۱۲ و...)</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">عملیات بروزرسانی محل نگهداری، پیش‌شماره ثبت و انتقال رکوردها با موفقیت انجام شد.</div>';
    if (is_loc_restricted()) {
        echo '<div class="card"><p class="warn">حساب کاربری شما محدود به محل نگهداری «' . h(user_loc()) . '» (پیش‌شماره ثبت: ' . tr_num(get_loc_prefix(user_loc())) . ') است و تغییر ساختار کل محل‌ها ویژه مدیر کل است.</p></div>';
    } else {
        echo '<div class="grid">
            <div class="card"><h2>➕ افزودن محل نگهداری جدید</h2>
                <form method="post" action="?p=loc_action"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="add">
                    <label>نام محل نگهداری / مرکز جدید *</label><input name="new_loc" required placeholder="مثلاً: شعبه ۲ یا بخش کتب نفیس"><br><br>
                    <label>پیش‌شماره ثبت اختصاصی این مرکز *</label><input type="number" name="new_pref" min="10" value="' . $next_pref_suggest . '" required><br><br>
                    <button class="btn pri">افزودن به فهرست محل‌ها</button>
                </form>
            </div>
            <div class="card"><h2>✏️ ویرایش نام یا پیش‌شماره محل نگهداری</h2>
                <form method="post" action="?p=loc_action"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="rename">
                    <label>انتخاب محل فعلی</label><select name="old_loc">';
                    foreach ($map_locs as $lc => $pf) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره فعلی: ' . tr_num($pf) . ')</option>';
        echo '      </select><br><br>
                    <label>نام جدید محل *</label><input name="new_loc" required placeholder="نام جدید یا همان نام قبلی..."><br><br>
                    <label>پیش‌شماره ثبت جدید (مثلاً 10، 11، 12)</label><input type="number" name="new_pref" min="10" placeholder="در صورت خالی بودن، پیش‌شماره قبلی حفظ می‌شود"><br><br>
                    <button class="btn ok">ذخیره تغییرات و بروزرسانی</button>
                </form>
            </div>
            <div class="card"><h2>🗑️ حذف محل و انتقال رکوردها به محل دیگر</h2>
                <form method="post" action="?p=loc_action" onsubmit="return confirm(\'آیا از حذف این محل و انتقال تمام منابع و اعضای آن به محل مقصد مطمئن هستید؟\')">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="delete">
                    <label>محل مورد نظر جهت حذف</label><select name="del_loc">';
                    foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
        echo '      </select><br><br>
                    <label>انتقال تمامی منابع، اعضا و سفارش‌ها به:</label><select name="target_loc">';
                    foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
        echo '      </select><br><br>
                    <button class="btn dng">حذف محل و انتقال رکوردها</button>
                </form>
            </div>
        </div>';
    }
    echo '<div class="card"><h2>فهرست محل‌های نگهداری، پیش‌شماره ثبت و آمار هر بخش</h2><table><tr><th>نام محل نگهداری</th><th>پیش‌شماره ثبت</th><th>شماره ثبت بعدی</th><th>تعداد عناوین منابع</th><th>مجموع نسخه‌ها</th><th>تعداد اعضا</th><th>سفارش‌های کتاب</th></tr>';
    foreach ($map_locs as $lc => $pf) {
        $st1 = $db->prepare("SELECT COUNT(*), COALESCE(SUM(copies),0) FROM records WHERE loc=?"); $st1->execute(array($lc)); $r1 = $st1->fetch(PDO::FETCH_NUM);
        $st2 = $db->prepare("SELECT COUNT(*) FROM members WHERE loc=?"); $st2->execute(array($lc)); $c_mem = $st2->fetchColumn();
        $st3 = $db->prepare("SELECT COUNT(*) FROM orders WHERE loc=?"); $st3->execute(array($lc)); $c_ord = $st3->fetchColumn();
        echo '<tr><td><b>📍 ' . h($lc) . '</b></td><td><span class="badge" style="background:var(--blue);color:#fff">پیش‌شماره ' . tr_num($pf) . '</span></td><td><code>' . tr_num(next_reg_no($lc)) . '</code></td><td>' . tr_num($r1[0]) . ' عنوان</td><td>' . tr_num($r1[1]) . ' نسخه</td><td>' . tr_num($c_mem) . ' نفر</td><td>' . tr_num($c_ord) . ' مورد</td></tr>';
    }
    echo '</table></div>';

// ==================== فهرست سفارش کتاب ====================
} elseif ($p === 'orders') {
    echo '<h1>🛒 فهرست سفارش‌های خرید منابع (ارسال‌شده از پرتال)</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">وضعیت سفارش با موفقیت بروزرسانی شد.</div>';
    $w_ord = is_loc_restricted() ? "WHERE loc=" . $db->quote(user_loc()) : "";
    $ords = $db->query("SELECT * FROM orders $w_ord ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><table><tr><th>تاریخ</th><th>عنوان کتاب / منبع</th><th>نویسنده / ناشر</th><th>شابک</th><th>محل نگهداری</th><th>درخواست‌کننده</th><th>وضعیت</th><th>تعیین وضعیت (خرید / عدم خرید)</th></tr>';
    if ($ords) {
        foreach ($ords as $o) {
            $st_badge = '<span class="badge">در انتظار بررسی</span>';
            if ($o['status'] === 'bought') $st_badge = '<span class="badge" style="background:#d1fae5;color:#065f46">✅ خریداری شد</span>';
            elseif ($o['status'] === 'rejected') $st_badge = '<span class="badge" style="background:#ffe4e6;color:var(--warn)">❌ عدم خرید</span>';

            echo '<tr><td>' . jdate($o['created']) . '</td><td><b>' . h($o['title']) . '</b></td><td>' . h($o['author']) . ($o['pub'] ? ' / ' . h($o['pub']) : '') . '</td>
            <td>' . h($o['isbn']) . '</td><td><span class="badge">' . h($o['loc']) . '</span></td>
            <td>' . h($o['requester']) . '<br><small>' . tr_num(h($o['phone'])) . '</small></td><td>' . $st_badge . '</td>
            <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
                <form method="post" action="?p=order_status" style="display:inline"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $o['id'] . '"><input type="hidden" name="status" value="bought"><button class="btn-sm ok">✔️ خرید</button></form>
                <form method="post" action="?p=order_status" style="display:inline"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $o['id'] . '"><input type="hidden" name="status" value="rejected"><button class="btn-sm dng">✖️ عدم خرید</button></form>
                ' . post_btn('del_order', 'حذف', array('id'=>$o['id']), 'btn-sm') . '
            </div></td></tr>';
        }
    } else {
        echo '<tr><td colspan="8" style="text-align:center">هیچ سفارش کتابی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';

// ==================== سیستم تیکتینگ ====================
} elseif ($p === 'tickets') {
    echo '<h1>🎫 سیستم تیکت و پشتیبانی داخلی کتابخانه</h1>';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'sent') echo '<div class="ok-msg">تیکت شما با موفقیت برای مدیریت ارسال شد.</div>';
        if ($_GET['msg'] === 'replied') echo '<div class="ok-msg">پاسخ تیکت با موفقیت ثبت شد.</div>';
    }
    echo '<div class="card"><h2>📨 ارسال تیکت جدید به مدیریت</h2>
        <form method="post" action="?p=send_ticket"><input type="hidden" name="t" value="' . $tok . '">
            <div class="grid">
                <div><label>گیرنده تیکت *</label><select name="target">
                    <option value="admin">مدیر کتابخانه (admin)</option>
                    <option value="admin1">مدیر فنی سامانه (admin1)</option>
                </select></div>
                <div><label>مبدا (کاربر / محل نگهداری)</label><input value="' . h($_SESSION['u'] . ' — ' . (is_loc_restricted() ? user_loc() : 'مدیریت مرکزی')) . '" disabled></div>
                <div><label>موضوع تیکت *</label><input name="subj" required placeholder="موضوع درخواست یا گزارش مشکل..."></div>
            </div><br>
            <label>متن پیام *</label><textarea name="msg" rows="3" required placeholder="شرح کامل پیام..."></textarea><br><br>
            <button class="btn pri">ارسال تیکت</button>
        </form>
    </div>';

    $w_t = is_super_admin() ? "" : "WHERE sender=" . $db->quote($_SESSION['u']) . " OR uloc=" . $db->quote(user_loc());
    $tks = $db->query("SELECT * FROM tickets $w_t ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><h2>فهرست تیکت‌ها و پیام‌ها</h2><table><tr><th>تاریخ</th><th>فرستنده (محل نگهداری)</th><th>گیرنده</th><th>موضوع و متن پیام</th><th>وضعیت / پاسخ مدیریت</th></tr>';
    if ($tks) {
        foreach ($tks as $tk) {
            $rec_label = ($tk['target'] === 'admin1') ? 'مدیر فنی (admin1)' : 'مدیر کتابخانه (admin)';
            echo '<tr><td>' . jdate($tk['created']) . '</td><td><b>' . h($tk['sender']) . '</b><br><span class="badge">' . h($tk['uloc']) . '</span></td>
            <td><span class="badge">' . $rec_label . '</span></td>
            <td><b>' . h($tk['subj']) . '</b><br><span style="color:#444">' . nl2br(h($tk['msg'])) . '</span></td><td>';
            if ($tk['reply']) {
                echo '<div style="background:#d1fae5;padding:8px 12px;border-radius:12px;margin-bottom:6px;border-right:3px solid var(--ok)"><b>پاسخ مدیریت:</b><br>' . nl2br(h($tk['reply'])) . '</div>';
            } else {
                echo '<span class="badge" style="background:#fef3c7;color:#92400e">در انتظار پاسخ</span> ';
            }
            if (is_super_admin()) {
                echo '<form method="post" action="?p=reply_ticket" style="margin-top:6px;display:flex;gap:6px">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $tk['id'] . '">
                    <input name="reply" value="' . h($tk['reply']) . '" placeholder="نوشتن پاسخ..." required>
                    <input type="hidden" name="status" value="answered">
                    <button class="btn-sm ok">ثبت پاسخ</button></form>';
            }
            echo '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5" style="text-align:center">هیچ تیکتی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';

// ==================== منابع و میز امانت ====================
} elseif ($p === 'rec') {
    $mq = trim(isset($_GET['mq']) ? $_GET['mq'] : '');
    $sel_mid = (int)(isset($_GET['sel_mid']) ? $_GET['sel_mid'] : 0);
    $bq = trim(isset($_GET['bq']) ? $_GET['bq'] : '');
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
    $q = trim(isset($_GET['q']) ? $_GET['q'] : '');
    $ret_url = "?p=rec&tab=" . urlencode($tab) . "&sel_mid=$sel_mid&mq=" . urlencode($mq) . "&bq=" . urlencode($bq);
    $w_loc_sql = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";

    echo '<h1>میز امانت سریع و مدیریت منابع' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1>';
    echo '<div class="card" style="border:2px solid var(--blue)"><h2>🔄 گام اول: جستجو و انتخاب عضو جهت امانت یا بازگشت</h2>';
    echo '<form method="get" class="row"><input type="hidden" name="p" value="rec"><input type="hidden" name="tab" value="' . h($tab) . '">
          <div style="flex:1"><label>نام عضو، شماره تماس یا کد عضویت</label><input name="mq" value="' . h($mq) . '" placeholder="نام عضو را وارد کنید..."></div>
          <button class="btn pri">جستجوی عضو</button>' . ($sel_mid || $mq ? '<a class="btn" href="?p=rec&tab=' . h($tab) . '">پاکسازی</a>' : '') . '</form>';

    if ($mq !== '' && !$sel_mid) {
        $ms = $db->prepare("SELECT * FROM members WHERE (name LIKE :q OR phone LIKE :q OR mcode LIKE :q) $w_loc_sql LIMIT 10");
        $ms->execute(array(':q' => "%$mq%"));
        $mrows = $ms->fetchAll(PDO::FETCH_ASSOC);
        if ($mrows) {
            echo '<table style="margin-top:12px"><tr><th>کد عضویت</th><th>نام عضو</th><th>محل</th><th>تلفن</th><th>عملیات</th></tr>';
            foreach ($mrows as $m) {
                echo '<tr><td>' . tr_num(h($m['mcode'] ? $m['mcode'] : $m['id'])) . '</td><td><b>' . h($m['name']) . '</b></td><td><span class="badge">' . h($m['loc']) . '</span></td><td>' . tr_num(h($m['phone'])) . '</td>
                <td><a class="btn-sm ok" href="?p=rec&tab=' . h($tab) . '&sel_mid=' . $m['id'] . '&mq=' . urlencode($mq) . '">انتخاب این عضو</a></td></tr>';
            }
            echo '</table>';
        } else echo '<p class="warn" style="margin-top:10px">عضوی در این بخش با این مشخصات یافت نشد.</p>';
    }

    if ($sel_mid) {
        $stm = $db->prepare("SELECT * FROM members WHERE id=? $w_loc_sql");
        $stm->execute(array($sel_mid));
        $mem = $stm->fetch(PDO::FETCH_ASSOC);
        if ($mem) {
            echo '<div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--line)">';
            echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                    <span>👤 عضو انتخاب‌شده: <b style="font-size:15px;color:var(--blue)">' . h($mem['name']) . '</b> (تلفن: ' . tr_num(h($mem['phone'])) . ' | ' . h($mem['loc']) . ')</span>
                    <a class="btn-sm" href="?p=mem&edit=' . $mem['id'] . '">ویرایش عضو</a>
                  </div>';

            $mloans = $db->prepare("SELECT l.*, r.f200a, r.reg_no FROM loans l JOIN records r ON r.id=l.rid WHERE l.mid=? AND l.ret IS NULL");
            $mloans->execute(array($sel_mid));
            $lrows = $mloans->fetchAll(PDO::FETCH_ASSOC);
            if ($lrows) {
                echo '<h3>امانت‌های جاری این عضو (بازگشت / ویرایش مهلت):</h3><table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>تاریخ امانت</th><th>موعد بازگشت</th><th>تمدید / تغییر مدت (روز)</th><th>بازگشت</th></tr>';
                foreach ($lrows as $lr) {
                    echo '<tr><td>' . tr_num(h($lr['reg_no'])) . '</td><td><b>' . h($lr['f200a']) . '</b></td><td>' . jdate($lr['d_out']) . '</td>
                    <td class="' . ($lr['due'] < date('Y-m-d') ? 'warn' : '') . '">' . jdate($lr['due']) . '</td>
                    <td><form method="post" action="?p=editloan" style="display:flex;gap:6px;max-width:170px">
                        <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $lr['id'] . '"><input type="hidden" name="ret_url" value="' . h($ret_url) . '">
                        <input type="number" name="add_days" value="7" style="width:65px;padding:4px 8px">
                        <button class="btn-sm">ویرایش مدت</button></form></td>
                    <td>' . post_btn('back', 'ثبت بازگشت', array('id'=>$lr['id'], 'ret_url'=>$ret_url), 'btn-sm ok') . '</td></tr>';
                }
                echo '</table><br>';
            }

            echo '<h3>📚 گام دوم: جستجوی منبع و ثبت امانت برای «' . h($mem['name']) . '»</h3>';
            echo '<form method="get" class="row"><input type="hidden" name="p" value="rec"><input type="hidden" name="sel_mid" value="' . $sel_mid . '"><input type="hidden" name="mq" value="' . h($mq) . '">
                  <div style="flex:1"><label>جستجوی کتاب/منبع (عنوان، شماره ثبت، پدیدآور، شابک)</label><input name="bq" value="' . h($bq) . '" placeholder="شماره ثبت یا عنوان کتاب را بنویسید..."></div>
                  <button class="btn pri">جستجوی کتاب</button></form>';

            if ($bq !== '') {
                $bs = $db->prepare("SELECT r.*, (r.copies - (SELECT COUNT(*) FROM loans l WHERE l.rid=r.id AND l.ret IS NULL)) AS avail FROM records r WHERE (f200a LIKE :q OR reg_no LIKE :q OR f700 LIKE :q OR f010 LIKE :q) $w_loc_sql LIMIT 10");
                $bs->execute(array(':q' => "%$bq%"));
                $brows = $bs->fetchAll(PDO::FETCH_ASSOC);
                if ($brows) {
                    echo '<table style="margin-top:12px"><tr><th>شماره ثبت</th><th>عنوان</th><th>محل نگهداری</th><th>پدیدآور</th><th>موجودی</th><th>تعیین مدت و امانت</th><th>عملیات</th></tr>';
                    foreach ($brows as $br) {
                        echo '<tr><td><b>' . tr_num(h($br['reg_no'])) . '</b></td><td><b>' . h($br['f200a']) . '</b></td><td><span class="badge">' . h($br['loc']) . '</span></td><td>' . h($br['f700']) . '</td><td>' . tr_num($br['avail']) . ' از ' . tr_num($br['copies']) . '</td><td>';
                        if ($br['avail'] > 0) {
                            echo '<form method="post" action="?p=out" style="display:flex;gap:6px;align-items:center">
                                  <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="rid" value="' . $br['id'] . '"><input type="hidden" name="mid" value="' . $sel_mid . '"><input type="hidden" name="ret_url" value="' . h($ret_url) . '">
                                  <input type="number" name="days" value="14" min="1" max="365" style="width:65px;padding:4px 8px"><span>روز</span>
                                  <button class="btn-sm pri">ثبت امانت</button></form>';
                        } else echo '<span class="warn">ناموجود</span>';
                        echo '</td><td><a class="btn-sm" href="?p=form&id=' . $br['id'] . '">ویرایش</a></td></tr>';
                    }
                    echo '</table>';
                } else echo '<p class="warn" style="margin-top:10px">منبعی در محل نگهداری شما یافت نشد.</p>';
            }
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<div class="tabs">';
    echo '<a class="' . ($tab === 'all' ? 'on' : '') . '" href="?p=rec&tab=all&q=' . urlencode($q) . '">همه منابع</a>';
    foreach ($RTypes as $k => $v) {
        echo '<a class="' . ($tab === $k ? 'on' : '') . '" href="?p=rec&tab=' . $k . '&q=' . urlencode($q) . '">' . $v . '</a>';
    }
    echo '</div>';

    $pg = max(1, (int)(isset($_GET['pg']) ? $_GET['pg'] : 1));
    $per = 30;
    $w = array(); $par = array();
    if (is_loc_restricted()) {
        $w[] = "loc = :uloc";
        $par[':uloc'] = user_loc();
    }
    if ($tab !== 'all' && isset($RTypes[$tab])) {
        $w[] = "rtype = :rt";
        $par[':rt'] = $tab;
    }
    if ($q !== '') {
        $w[] = "(f200a LIKE :q OR reg_no LIKE :q OR loc LIKE :q OR f700 LIKE :q OR f710 LIKE :q OR f010 LIKE :q OR f606 LIKE :q OR f676 LIKE :q OR f686 LIKE :q OR f210c LIKE :q)";
        $par[':q'] = "%$q%";
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $c = $db->prepare("SELECT COUNT(*) FROM records $where"); $c->execute($par); $tot = (int)$c->fetchColumn();
    $s = $db->prepare("SELECT * FROM records $where ORDER BY id DESC LIMIT $per OFFSET " . ($pg - 1) * $per); $s->execute($par);

    echo '<form class="card row" method="get"><input type="hidden" name="p" value="rec"><input type="hidden" name="tab" value="' . h($tab) . '">
          <div style="flex:1"><label>جستجو در کل دیتابیس منابع (شماره ثبت، عنوان، پدیدآور، شابک، رده دیویی/کنگره)</label><input name="q" value="' . h($q) . '"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn pri">جستجو</button><a class="btn ok" href="?p=form">+ ثبت منبع جدید</a><a class="btn" href="?p=export_excel_rec">📊 خروجی اکسل</a></div></form>';

    echo "<div class='card'><table><tr><th>شماره ثبت</th><th>نوع</th><th>عنوان</th><th>پدیدآور</th><th>ناشر</th><th>سال</th><th>محل نگهداری</th><th>رده</th><th>پیوست</th><th>عملیات</th></tr>";
    foreach ($s as $r) {
        echo '<tr><td><b>' . tr_num(h($r['reg_no'])) . '</b></td>
        <td><span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></td>
        <td><b>' . h($r['f200a']) . '</b></td><td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td>
        <td>' . h($r['f210c']) . '</td><td>' . tr_num(h($r['f210d'])) . '</td>
        <td><span class="badge">' . h($r['loc']) . '</span></td>
        <td>' . h($r['f676'] ? $r['f676'] : $r['f686']) . '</td>
        <td>' . (!empty($r['file']) ? '<a class="btn-sm" href="' . h($r['file']) . '" target="_blank">📎 فایل</a>' : '-') . '</td>
        <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
            <a class="btn-sm" href="?p=marc&id=' . $r['id'] . '">نمایش</a>
            <a class="btn-sm pri" href="?p=form&id=' . $r['id'] . '">ویرایش</a>
            ' . post_btn('del', 'حذف', array('id'=>$r['id']), 'btn-sm dng') . '
        </div></td></tr>';
    }
    echo '</table><p>مجموع: <b>' . tr_num($tot) . '</b> عنوان</p>';
    for ($i = 1; $i <= ceil($tot / $per) && $i <= 40; $i++) {
        echo '<a class="btn-sm' . ($i == $pg ? ' pri' : '') . '" href="?p=rec&tab=' . urlencode($tab) . '&q=' . urlencode($q) . '&pg=' . $i . '">' . tr_num($i) . '</a> ';
    }
    echo '</div>';

// ==================== ثبت و ویرایش منبع (با تولید خودکار شماره ثبت بر اساس پیش‌شماره محل نگهداری) ====================
} elseif ($p === 'form') {
    $r = array();
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    if ($id) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $s = $db->prepare("SELECT * FROM records WHERE id=? $w_loc");
        $s->execute(array($id));
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) $r = array();
    } else {
        foreach ($F as $k => $v) { if (isset($_GET[$k])) $r[$k] = $_GET[$k]; }
    }
    $allowed_locs = allowed_locations();
    $cur_loc = isset($r['loc']) ? $r['loc'] : $allowed_locs[0];
    $def_reg = isset($r['reg_no']) && $r['reg_no'] !== '' ? $r['reg_no'] : next_reg_no($cur_loc);

    // ساخت نقشه شماره ثبت بعدی برای هر محل نگهداری جهت تغییر خودکار در مرورگر
    $next_regs_by_loc = array();
    foreach ($allowed_locs as $lc) {
        $next_regs_by_loc[$lc] = next_reg_no($lc);
    }

    echo '<h1>' . ($id ? 'ویرایش منبع' : 'ثبت منبع جدید') . ' (بر پایه ایران‌مارک)</h1>
    <form class="card" method="post" action="?p=save" enctype="multipart/form-data">
    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $id . '">
    <input type="hidden" name="old_file" value="' . h(isset($r['file']) ? $r['file'] : '') . '">
    <div class="grid">
        <div><label style="color:var(--blue)">محل نگهداری در کتابخانه *</label><select name="loc" id="loc_select" onchange="updateRegByLoc(this.value)">';
        foreach ($allowed_locs as $lc) {
            $pf = get_loc_prefix($lc);
            echo '<option value="' . h($lc) . '"' . ($cur_loc === $lc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num($pf) . ')</option>';
        }
    echo '</select></div>
        <div><label style="color:var(--blue)">شماره ثبت منبع (با پیش‌شماره مرکز) *</label><input name="reg_no" id="reg_no_input" value="' . h($def_reg) . '" required></div>
        <div><label>نوع منبع</label><select name="rtype">';
        $cur_rt = isset($r['rtype']) ? $r['rtype'] : 'book';
        foreach ($RTypes as $k => $v) echo '<option value="' . $k . '"' . ($cur_rt === $k ? ' selected' : '') . '>' . $v . '</option>';
    echo '</select></div>';
    foreach ($F as $c => $l) {
        $val = isset($r[$c]) ? $r[$c] : ($c === 'f101' ? 'per' : '');
        echo '<div><label>' . h($l) . '</label><input name="' . $c . '" value="' . h($val) . '"' . ($c === 'f200a' ? ' required' : '') . '></div>';
    }
    echo '<div><label>تعداد نسخه</label><input type="number" min="1" name="copies" value="' . (int)(isset($r['copies']) ? $r['copies'] : 1) . '"></div>
          <div><label>فایل پیوست (PDF, Word, تصویر و...)</label><input type="file" name="file">' . (!empty($r['file']) ? '<small><a href="' . h($r['file']) . '" target="_blank">مشاهده فایل فعلی</a></small>' : '') . '</div>
    </div><br><button class="btn pri">💾 ذخیره اطلاعات منبع</button> <a class="btn" href="?p=rec">انصراف</a></form>';

    if (!$id) {
        echo '<script>
        var nextRegsMap = ' . json_encode($next_regs_by_loc, JSON_UNESCAPED_UNICODE) . ';
        function updateRegByLoc(locVal) {
            var inp = document.getElementById("reg_no_input");
            if (inp && nextRegsMap[locVal]) inp.value = nextRegsMap[locVal];
        }
        </script>';
    }

// ==================== نمایش شناسنامه مارک ====================
} elseif ($p === 'marc') {
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
    $s = $db->prepare("SELECT * FROM records WHERE id=? $w_loc");
    $s->execute(array($id));
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo '<div class="card"><p class="warn">رکورد پیدا نشد یا در محل نگهداری مجاز شما نیست.</p></div>';
    } else {
        if (isset($_GET['msg'])) echo '<div class="ok-msg">اطلاعات منبع با موفقیت ذخیره شد.</div>';
        echo '<h1>' . h($r['f200a']) . ' <span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></h1><div class="card"><table class="marc">';
        echo '<tr><td>شماره ثبت</td><td><b>' . tr_num(h($r['reg_no'])) . '</b></td></tr>';
        echo '<tr><td>محل نگهداری</td><td><span class="badge">' . h($r['loc']) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($r['loc'])) . ')</span></td></tr>';
        echo '<tr><td>تاریخ ثبت در سامانه</td><td>' . jdate($r['created']) . '</td></tr>';
        echo '<tr><td>تعداد نسخه‌ها</td><td>' . tr_num($r['copies']) . '</td></tr>';
        foreach ($F as $c => $l) {
            if ($r[$c] !== null && $r[$c] !== '') echo '<tr><td>' . h($l) . '</td><td>' . h($r[$c]) . '</td></tr>';
        }
        if (!empty($r['file'])) echo '<tr><td>فایل پیوست</td><td><a class="btn ok" href="' . h($r['file']) . '" target="_blank">📎 دانلود فایل پیوست</a></td></tr>';
        echo '</table><br><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn pri" href="?p=form&id=' . $id . '">ویرایش منبع</a> <a class="btn ok" href="?p=org&regs=' . urlencode($r['reg_no']) . '">چاپ برچسب عطف</a> ' . post_btn('del', 'حذف منبع', array('id' => $id), 'btn dng') . '</div></div>';
    }

// ==================== منوی سازماندهی: چاپ برچسب عطف با تمامی تنظیمات اندازه و حاشیه ====================
} elseif ($p === 'org') {
    $sys       = isset($_GET['sys']) ? $_GET['sys'] : 'dewey';
    $per_page  = max(1, min(100, (int)(isset($_GET['per']) ? $_GET['per'] : 24)));
    $cols_grid = max(1, min(8, (int)(isset($_GET['cols']) ? $_GET['cols'] : 4)));
    $lbl_w     = max(20, min(120, (int)(isset($_GET['lbl_w']) ? $_GET['lbl_w'] : 42)));
    $lbl_h     = max(15, min(100, (int)(isset($_GET['lbl_h']) ? $_GET['lbl_h'] : 34)));
    $gap_mm    = max(0, min(30, (int)(isset($_GET['gap']) ? $_GET['gap'] : 4)));
    $pad_mm    = max(0, min(30, (int)(isset($_GET['pad']) ? $_GET['pad'] : 8)));
    $fsize     = max(8, min(28, (int)(isset($_GET['fsize']) ? $_GET['fsize'] : 14)));
    $bdr_w     = max(0, min(6, (int)(isset($_GET['bdr']) ? $_GET['bdr'] : 2)));
    $reg_from  = trim(en_num(isset($_GET['reg_from']) ? $_GET['reg_from'] : ''));
    $reg_to    = trim(en_num(isset($_GET['reg_to']) ? $_GET['reg_to'] : ''));
    $regs_list = trim(en_num(isset($_GET['regs']) ? $_GET['regs'] : ''));
    $loc_f     = is_loc_restricted() ? user_loc() : trim(isset($_GET['loc']) ? $_GET['loc'] : '');
    $custom_hdr= isset($_GET['hdr']) ? trim($_GET['hdr']) : get_cms('site_title');
    $allowed_locs = allowed_locations();

    echo '<div class="no-print"><h1>🏷️ سازماندهی و چاپ برچسب عطف (کنگره و دیویی — با قابلیت تنظیم کامل ابعاد و حاشیه‌ها)</h1>';
    echo '<form class="card" method="get"><input type="hidden" name="p" value="org"><div class="grid">
        <div><label>نظام رده‌بندی برچسب</label><select name="sys">
            <option value="dewey"' . ($sys === 'dewey' ? ' selected' : '') . '>رده‌بندی دیویی (۶۷۶)</option>
            <option value="congress"' . ($sys === 'congress' ? ' selected' : '') . '>رده‌بندی کنگره (۶۸۰/۶۸۶)</option>
        </select></div>
        <div><label>عنوان بالای برچسب (نام کتابخانه)</label><input name="hdr" value="' . h($custom_hdr) . '"></div>
        <div><label>تعداد کل برچسب در صفحه</label><input type="number" name="per" min="1" max="100" value="' . $per_page . '"></div>
        <div><label>تعداد ستون در هر ردیف</label><input type="number" name="cols" min="1" max="8" value="' . $cols_grid . '"></div>
        <div><label>عرض هر برچسب (میلی‌متر mm)</label><input type="number" name="lbl_w" min="20" max="120" value="' . $lbl_w . '"></div>
        <div><label>ارتفاع هر برچسب (میلی‌متر mm)</label><input type="number" name="lbl_h" min="15" max="100" value="' . $lbl_h . '"></div>
        <div><label>فاصله بین برچسب‌ها (میلی‌متر mm)</label><input type="number" name="gap" min="0" max="30" value="' . $gap_mm . '"></div>
        <div><label>حاشیه دور صفحه (میلی‌متر mm)</label><input type="number" name="pad" min="0" max="30" value="' . $pad_mm . '"></div>
        <div><label>اندازه فونت رده (پیکسل px)</label><input type="number" name="fsize" min="8" max="28" value="' . $fsize . '"></div>
        <div><label>ضخامت خط کادر (px)</label><input type="number" name="bdr" min="0" max="6" value="' . $bdr_w . '"></div>
        <div><label>از شماره ثبت</label><input name="reg_from" value="' . h($reg_from) . '" placeholder="مثلاً 100001"></div>
        <div><label>تا شماره ثبت</label><input name="reg_to" value="' . h($reg_to) . '" placeholder="مثلاً 100050"></div>
        <div><label>یا شماره ثبت‌های انتخابی (با کاما ,)</label><input name="regs" value="' . h($regs_list) . '" placeholder="100001,100005"></div>
        <div><label>فیلتر محل نگهداری</label><select name="loc">' . (!is_loc_restricted() ? '<option value="">همه بخش‌ها</option>' : '');
        foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($loc_f === $lc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '</select></div>
    </div><br><div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn pri">🔄 اعمال تنظیمات و پیش‌نمایش</button> <button type="button" class="btn ok" onclick="window.print()">🖨️ چاپ برچسب‌های عطف</button></div></form></div>';

    $w = array(); $par = array();
    if ($regs_list !== '') {
        $arr = array_filter(array_map('trim', explode(',', str_replace('،', ',', $regs_list))));
        if ($arr) {
            $ph = implode(',', array_fill(0, count($arr), '?'));
            $w[] = "reg_no IN ($ph)";
            $par = array_merge($par, $arr);
        }
    } else {
        if ($reg_from !== '') { $w[] = "CAST(reg_no AS INTEGER) >= ?"; $par[] = (int)$reg_from; }
        if ($reg_to !== '') { $w[] = "CAST(reg_no AS INTEGER) <= ?"; $par[] = (int)$reg_to; }
    }
    if ($loc_f !== '') { $w[] = "loc = ?"; $par[] = $loc_f; }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $st = $db->prepare("SELECT * FROM records $where ORDER BY CAST(reg_no AS INTEGER) ASC, id DESC LIMIT " . (int)$per_page);
    $st->execute($par);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    echo '<style>
    .label-sheet-wrap{overflow-x:auto;width:100%}
    .label-sheet{display:grid;grid-template-columns:repeat(' . $cols_grid . ',' . $lbl_w . 'mm);gap:' . $gap_mm . 'mm;padding:' . $pad_mm . 'mm;justify-content:center;background:#fff;border-radius:16px;min-width:fit-content}
    .spine-label{width:' . $lbl_w . 'mm;height:' . $lbl_h . 'mm;border:' . $bdr_w . 'px solid #000;border-radius:6px;padding:2mm 2.5mm;background:#fff;color:#000;text-align:center;display:flex;flex-direction:column;justify-content:space-between;page-break-inside:avoid;overflow:hidden}
    .spine-hdr{font-size:10.5px;font-weight:800;border-bottom:1.5px solid #000;padding-bottom:1mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .spine-call{font-size:' . $fsize . 'px;font-weight:800;line-height:1.25;direction:ltr;margin:auto 0}
    .spine-ftr{font-size:10px;font-weight:700;border-top:1px dashed #000;padding-top:1mm;display:flex;justify-content:space-between;align-items:center}
    </style>';

    if ($items) {
        echo '<div class="label-sheet-wrap"><div class="label-sheet">';
        foreach ($items as $it) {
            $call_raw = ($sys === 'congress') ? ($it['f686'] ? $it['f686'] : $it['f676']) : ($it['f676'] ? $it['f676'] : $it['f686']);
            $auth_str = trim($it['f700'] ? $it['f700'] : $it['f200f']);
            $author_cutter = mb_substr($auth_str, 0, 2, 'UTF-8') . ' ' . mb_substr(trim($it['f200a']), 0, 1, 'UTF-8');
            $parts = preg_split('/[\s\/]+/', trim($call_raw));
            echo '<div class="spine-label">
                <div class="spine-hdr">' . h($custom_hdr) . '</div>
                <div class="spine-call">';
                if ($call_raw) { foreach ($parts as $pt) echo '<div>' . h($pt) . '</div>'; } else echo '<div>---</div>';
                if (trim($author_cutter) !== '') echo '<div style="font-size:' . max(9, $fsize - 2) . 'px;direction:rtl;margin-top:1px">' . h($author_cutter) . '</div>';
                if (!empty($it['f210d'])) echo '<div style="font-size:' . max(9, $fsize - 2) . 'px">' . tr_num(h($it['f210d'])) . '</div>';
            echo '</div>
                <div class="spine-ftr"><span>ثبت: ' . tr_num(h($it['reg_no'])) . '</span><span>' . h($it['loc']) . '</span></div>
            </div>';
        }
        echo '</div></div>';
    } else echo '<div class="card no-print"><p class="warn">رکوردی در این بازه یافت نشد.</p></div>';

// ==================== مدیریت پرتال ====================
} elseif ($p === 'cms') {
    $box = isset($_GET['box']) ? $_GET['box'] : 'general';
    echo '<h1>🌐 مدیریت پرتال (۲۰ طیف رنگ، چیدمان و افزودن باکس، ویرایشگر و گالری)</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">تغییرات با موفقیت ذخیره شد.</div>';

    echo '<div class="tabs">
        <a class="' . ($box === 'general' ? 'on' : '') . '" href="?p=cms&box=general">🎨 ۲۰ طیف رنگ و فونت</a>
        <a class="' . ($box === 'layout' ? 'on' : '') . '" href="?p=cms&box=layout">🧩 جابه‌جایی موقعیت و افزودن باکس</a>
        <a class="' . ($box === 'boxes' ? 'on' : '') . '" href="?p=cms&box=boxes">📝 ویرایش محتوای باکس‌ها</a>
        <a class="' . ($box === 'chart' ? 'on' : '') . '" href="?p=cms&box=chart">📈 نمودار رشد منابع</a>
        <a class="' . ($box === 'gallery' ? 'on' : '') . '" href="?p=cms&box=gallery">🖼️ گالری تصاویر</a>
        <a class="btn ok" href="?p=home" target="_blank" style="margin-right:auto">👁️ مشاهده پرتال</a>
    </div>';

    if ($box === 'general') {
        $cur_font = get_cms('font');
        $cur_theme = get_cms('theme');
        $fonts_list = array(
            'Vazirmatn' => 'فونت وزیرمتن (Vazirmatn - پیشنهادی)',
            'Shabnam'   => 'فونت شبنم (Shabnam)',
            'Sahel'     => 'فونت ساحل (Sahel)',
            'Samim'     => 'فونت صمیم (Samim)',
            'Tahoma'    => 'فونت پیش‌فرض سیستم (Tahoma)'
        );
        echo '<form class="card" method="post" action="?p=savecms"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="general">
            <h2>انتخاب استایل از میان ۲۰ طیف رنگ (۱۰ پررنگ و ۱۰ کم‌رنگ پاستلی) و تنظیم فونت</h2>
            <div class="grid">
                <div><label>عنوان کتابخانه / پرتال</label><input name="site_title" value="' . h(get_cms('site_title')) . '" required></div>
                <div><label>زیرعنوان / شعار پرتال</label><input name="site_sub" value="' . h(get_cms('site_sub')) . '"></div>
                <div><label>فونت نمایشی سامانه</label><select name="font">';
                foreach ($fonts_list as $fk => $fv) echo '<option value="' . h($fk) . '"' . ($cur_font === $fk ? ' selected' : '') . '>' . h($fv) . '</option>';
        echo '  </select></div>
            </div><br>
            <label>انتخاب طیف رنگی سامانه:</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:8px">';
            foreach ($Themes as $tk => $tv) {
                $chk = ($cur_theme === $tk) ? 'checked' : '';
                echo '<label style="display:flex;align-items:center;gap:8px;padding:10px;border:2px solid ' . ($chk ? $tv['blue'] : 'var(--line)') . ';border-radius:14px;background:' . $tv['ice'] . ';cursor:pointer">
                    <input type="radio" name="theme" value="' . h($tk) . '" ' . $chk . '>
                    <span style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,' . $tv['navy'] . ',' . $tv['blue'] . ');display:inline-block;border:1px solid #fff;flex-shrink:0"></span>
                    <span style="font-size:12px;color:' . $tv['navy'] . '">' . h($tv['name']) . '</span>
                </label>';
            }
        echo '</div><br><button class="btn pri">💾 ذخیره استایل رنگ و تنظیمات عمومی</button></form>';

    } elseif ($box === 'layout') {
        $edit_bid = (int)(isset($_GET['edit_b']) ? $_GET['edit_b'] : 0);
        $eb = array('id'=>0, 'title'=>'', 'pos'=>'main', 'ord'=>10, 'content'=>'');
        if ($edit_bid) {
            $st_eb = $db->prepare("SELECT * FROM portal_boxes WHERE id=? AND btype='custom'");
            $st_eb->execute(array($edit_bid));
            $f_eb = $st_eb->fetch(PDO::FETCH_ASSOC);
            if ($f_eb) $eb = $f_eb;
        }

        echo '<div class="card"><h2>🧩 مدیریت چیدمان، موقعیت (ستون اصلی راست / ستون کناری چپ) و ترتیب باکس‌های پرتال</h2>
            <form method="post" action="?p=save_box_layout"><input type="hidden" name="t" value="' . $tok . '">
                <table><tr><th>فعال</th><th>عنوان نمایشی باکس</th><th>نوع باکس</th><th>موقعیت در پرتال</th><th>شماره ترتیب نمایش</th><th>عملیات</th></tr>';
                foreach ($db->query("SELECT * FROM portal_boxes ORDER BY pos ASC, ord ASC, id ASC") as $bx) {
                    echo '<tr>
                        <td><input type="hidden" name="b_id[]" value="' . $bx['id'] . '"><input type="checkbox" name="b_active[' . $bx['id'] . ']" value="1"' . ($bx['active'] ? ' checked' : '') . '></td>
                        <td><input name="b_title[]" value="' . h($bx['title']) . '" style="min-width:160px"></td>
                        <td><span class="badge">' . ($bx['btype'] === 'custom' ? 'باکس سفارشی' : 'سیستمی (' . h($bx['bkey']) . ')') . '</span></td>
                        <td><select name="b_pos[]" style="min-width:140px">
                            <option value="main"' . ($bx['pos'] === 'main' ? ' selected' : '') . '>ستون اصلی (راست / مرکز)</option>
                            <option value="side"' . ($bx['pos'] === 'side' ? ' selected' : '') . '>نوار کناری (سمت چپ)</option>
                        </select></td>
                        <td><input type="number" name="b_ord[]" value="' . (int)$bx['ord'] . '" style="width:75px"></td>
                        <td>';
                        if ($bx['btype'] === 'custom') {
                            echo '<a class="btn-sm pri" href="?p=cms&box=layout&edit_b=' . $bx['id'] . '">ویرایش متن</a> ';
                        } else {
                            echo '<a class="btn-sm" href="?p=cms&box=boxes">ویرایش محتوا</a>';
                        }
                    echo '</td></tr>';
                }
        echo '  </table><br><button class="btn pri">💾 ذخیره چیدمان و موقعیت باکس‌ها</button>
            </form>
        </div>';

        echo '<div class="card"><h2>' . ($eb['id'] ? '✏️ ویرایش باکس سفارشی' : '➕ افزودن باکس جدید به پرتال') . '</h2>
            <form method="post" action="?p=add_custom_box" onsubmit="document.getElementById(\'cbox_input\').value=document.getElementById(\'cbox_rte\').innerHTML;">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="edit_id" value="' . $eb['id'] . '">
                <div class="grid">
                    <div><label>عنوان باکس جدید *</label><input name="title" value="' . h($eb['title']) . '" required placeholder="مثلاً: قوانین امانت یا سخن روز"></div>
                    <div><label>موقعیت نمایش در پرتال</label><select name="pos">
                        <option value="main"' . ($eb['pos'] === 'main' ? ' selected' : '') . '>ستون اصلی (راست / مرکز)</option>
                        <option value="side"' . ($eb['pos'] === 'side' ? ' selected' : '') . '>نوار کناری (سمت چپ)</option>
                    </select></div>
                    <div><label>ترتیب نمایش (عدد کمتر = بالاتر)</label><input type="number" name="ord" value="' . (int)$eb['ord'] . '"></div>
                </div><br>
                <label>محتوای باکس (ویرایشگر پیشرفته):</label>
                <div class="rte-bar">
                    <button type="button" onclick="document.execCommand(\'bold\',false,null)"><b>B بولد</b></button>
                    <button type="button" onclick="document.execCommand(\'italic\',false,null)"><i>I ایتالیک</i></button>
                    <button type="button" onclick="document.execCommand(\'underline\',false,null)"><u>U زیرخط</u></button>
                    <button type="button" onclick="document.execCommand(\'insertUnorderedList\',false,null)">• لیست نقطه‌ای</button>
                    <button type="button" onclick="document.execCommand(\'foreColor\',false,\'#2563eb\')">🎨 آبی</button>
                    <button type="button" onclick="document.execCommand(\'foreColor\',false,\'#e11d48\')">🎨 قرمز</button>
                </div>
                <div id="cbox_rte" class="rte-area" contenteditable="true">' . ($eb['content'] ? $eb['content'] : '<p>متن یا اطلاعیه دلخواه خود را اینجا وارد نمایید...</p>') . '</div>
                <input type="hidden" id="cbox_input" name="content"><br>
                <button class="btn ok">' . ($eb['id'] ? 'ذخیره تغییرات باکس' : '➕ افزودن باکس به پرتال') . '</button>
                ' . ($eb['id'] ? '<a class="btn" href="?p=cms&box=layout">انصراف</a> ' . post_btn('del_custom_box', 'حذف این باکس', array('id'=>$eb['id']), 'btn dng') : '') . '
            </form>
        </div>';

    } elseif ($box === 'boxes') {
        $sel_b = isset($_GET['sel_b']) ? $_GET['sel_b'] : 'news';
        $box_titles = array(
            'news'     => 'باکس اخبار و اطلاعیه‌های کتابخانه (با قابلیت مشروح خبر)',
            'about'    => 'باکس درباره ما (ویرایشگر پیشرفته HTML)',
            'contact'  => 'باکس تماس با ما (ویرایشگر پیشرفته HTML)',
            'services' => 'باکس خدمات کتابخانه',
            'links'    => 'باکس پیوندهای مفید'
        );
        echo '<div class="card"><h2>انتخاب باکس سیستمی جهت ویرایش محتوا</h2>
            <form method="get" class="row" style="margin-bottom:18px"><input type="hidden" name="p" value="cms"><input type="hidden" name="box" value="boxes">
                <div style="flex:1"><label>باکس مورد نظر را انتخاب کنید:</label><select name="sel_b" onchange="this.form.submit()">';
                foreach ($box_titles as $bk => $bv) echo '<option value="' . $bk . '"' . ($sel_b === $bk ? ' selected' : '') . '>' . $bv . '</option>';
        echo '  </select></div><button class="btn">بارگذاری باکس</button></form>';

        echo '<form method="post" action="?p=savecms" onsubmit="if(document.getElementById(\'rte_box\')){document.getElementById(\'rte_input\').value=document.getElementById(\'rte_box\').innerHTML;}">
              <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="boxes&sel_b=' . h($sel_b) . '">';
        
        if ($sel_b === 'about' || $sel_b === 'contact') {
            echo '<label>' . h($box_titles[$sel_b]) . '</label>
            <div class="rte-bar">
                <button type="button" onclick="document.execCommand(\'bold\',false,null)"><b>B بولد</b></button>
                <button type="button" onclick="document.execCommand(\'italic\',false,null)"><i>I ایتالیک</i></button>
                <button type="button" onclick="document.execCommand(\'underline\',false,null)"><u>U زیرخط</u></button>
                <button type="button" onclick="document.execCommand(\'insertUnorderedList\',false,null)">• لیست نقطه‌ای</button>
                <button type="button" onclick="document.execCommand(\'foreColor\',false,\'#2563eb\')">🎨 رنگ آبی</button>
                <button type="button" onclick="document.execCommand(\'foreColor\',false,\'#e11d48\')">🎨 رنگ قرمز</button>
            </div>
            <div id="rte_box" class="rte-area" contenteditable="true">' . get_cms($sel_b) . '</div>
            <input type="hidden" id="rte_input" name="' . h($sel_b) . '">';
        } else {
            $hints = array(
                'news'     => 'فرمت هر خط: عنوان خبر | تاریخ | متن کامل مشروح خبر',
                'services' => 'هر خدمت را در یک خط جداگانه بنویسید.',
                'links'    => 'فرمت هر خط: عنوان پیوند | آدرس اینترنتی'
            );
            echo '<label>' . h($box_titles[$sel_b]) . ' — <small>' . h($hints[$sel_b]) . '</small></label>
                  <textarea name="' . h($sel_b) . '" rows="8">' . h(get_cms($sel_b)) . '</textarea>';
        }
        echo '<br><br><button class="btn pri">💾 ذخیره محتوای این باکس</button></form></div>';

    } elseif ($box === 'chart') {
        echo '<form class="card" method="post" action="?p=savecms"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="chart">
            <h2>📈 مدیریت داده‌های نمودار رشد منابع (ماه‌به‌ماه)</h2>
            <p style="font-size:13px">در هر خط نام ماه و تعداد کل منابع را با علامت <code>|</code> جدا کنید (مثال: <code>فروردین|120</code>):</p>
            <textarea name="growth_chart" rows="12" style="direction:rtl;font-family:monospace;font-size:14px">' . h(get_cms('growth_chart')) . '</textarea><br><br>
            <button class="btn pri">ذخیره و بروزرسانی نمودار پرتال</button>
        </form>';

    } elseif ($box === 'gallery') {
        echo '<div class="card"><h2>➕ افزودن تصویر جدید به گالری پرتال (نمایش در نوار کناری سمت چپ زیر باکس آمار)</h2>
            <form method="post" action="?p=add_gal" enctype="multipart/form-data" class="row"><input type="hidden" name="t" value="' . $tok . '">
                <div style="flex:1"><label>عنوان یا توضیح تصویر *</label><input name="title" required placeholder="مثلاً: سالن مطالعه بخش مرجع"></div>
                <div style="flex:1"><label>انتخاب فایل تصویر (JPG, PNG, WebP) *</label><input type="file" name="img" accept="image/*" required></div>
                <button class="btn pri">آپلود و نمایش در گالری</button>
            </form>
        </div>';
        $gals = $db->query("SELECT * FROM gallery ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo '<div class="card"><h2>تصاویر فعلی گالری</h2><div class="gal-grid">';
        foreach ($gals as $g) {
            echo '<div class="gal-item"><img src="' . h($g['img']) . '"><div>' . h($g['title']) . '<br><br>' . post_btn('del_gal', 'حذف', array('id'=>$g['id']), 'btn-sm dng') . '</div></div>';
        }
        echo '</div></div>';
    }

// ==================== جستجو در کتابخانه ملی ایران (آی‌فریم مستقیم + پیش‌نمایش و تست فایل ایزو) ====================
} elseif ($p === 'nlai') {
    $allowed_locs = allowed_locations();
    echo '<h1>🏛️ درگاه مستقیم کتابخانه ملی ایران (OPAC) و ورود رکورد با پیش‌نمایش</h1>';
    if (isset($_GET['msg']) && strpos($_GET['msg'], 'iso_') === 0) {
        echo '<div class="ok-msg">✅ تعداد ' . tr_num(substr($_GET['msg'], 4)) . ' رکورد تاییدشده با موفقیت در مخزن کتابخانه ثبت شد.</div>';
    }

    render_iso_preview_box('nlai');

    echo '<div class="card" style="border:2px solid var(--ok)">
        <h2>📥 گام دوم: بارگذاری فایل ایزو دریافتی از کتابخانه ملی (selectedRecords.txt) جهت تست و پیش‌نمایش</h2>
        <p style="font-size:12.8px;margin:0 0 12px">پس از جستجو در کادر کتابخانه ملی در پایین همین صفحه، رکوردهای مورد نظر را با فرمت <b>ISO 2709</b> ذخیره کرده (فایل <code>selectedRecords.txt</code>) و در کادر زیر انتخاب کنید تا ابتدا <b>تمامی فیلدها با پیش‌شماره ثبت مرکز انتخابی جهت تست نمایش داده شوند</b> و سپس ثبت گردند:</p>
        <form method="post" action="?p=imp_iso_preview" enctype="multipart/form-data" class="row">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="from_page" value="nlai">
            <div style="width:240px"><label>محل نگهداری منبع</label><select name="loc">';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '  </select></div>
            <div style="flex:1"><label>انتخاب فایل خروجی کتابخانه ملی (selectedRecords.txt / .mrc / .iso) *</label><input type="file" name="f" accept=".txt,.mrc,.iso,.dat" required></div>
            <button class="btn ok">🧪 استخراج فیلدها و نمایش پیش‌نمایش (تست)</button>
        </form>
    </div>';

    echo '<div class="card" style="padding:14px">
        <h2><span>🌐 گام اول: فرم جستجوی مستقیم سازمان اسناد و کتابخانه ملی ایران (searchBF.do)</span>
            <a class="btn-sm pri" href="https://opac.nlai.ir/opac-prod/search/searchBF.do" target="_blank">باز کردن در تب مستقل (در صورت عدم نمایش مرورگر) ↗</a>
        </h2>
        <iframe src="https://opac.nlai.ir/opac-prod/search/searchBF.do" style="width:100%;height:620px;border:1px solid var(--line);border-radius:16px;background:#fff"></iframe>
    </div>';

// ==================== ورودی و خروجی (همراه با پیش‌نمایش ایزو) ====================
} elseif ($p === 'imp_exp') {
    $allowed_locs = allowed_locations();
    echo '<h1>🔄 ورودی و خروجی استاندارد (اکسل فارسی ایران‌مارک و فایل ایزو ۲۷۰۹ / .txt کتابخانه ملی)</h1>';
    if (isset($_GET['msg'])) {
        if (strpos($_GET['msg'], 'iso_') === 0) echo '<div class="ok-msg">✅ تعداد ' . tr_num(substr($_GET['msg'], 4)) . ' رکورد تاییدشده از فایل ایزو ۲۷۰۹ با موفقیت ثبت شد.</div>';
        if (strpos($_GET['msg'], 'xl_') === 0) echo '<div class="ok-msg">✅ تعداد ' . tr_num(substr($_GET['msg'], 3)) . ' رکورد از فایل اکسل با موفقیت ثبت شد.</div>';
    }

    render_iso_preview_box('imp_exp');

    echo '<div class="grid">
        <div class="card">
            <h2>📥 ورودی منابع از فایل اکسل (با سرستون‌های فارسی ایران‌مارک)</h2>
            <p style="font-size:12.8px;line-height:1.7">فایل نمونه زیر به صورت جدول‌بندی‌شده استاندارد اکسل (`.xls`) و کاملاً راست‌چین با سرستون‌های فارسی مارک ایران آماده شده است:</p>
            <p><a class="btn ok" href="?p=sample_rec">📄 دانلود فایل اکسل نمونه فارسی (ایران‌مارک)</a></p>
            <form method="post" action="?p=imp_xl_rec" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '">
                <label>انتخاب فایل اکسل (.xls, .xlsx, .csv)</label><input type="file" name="f" accept=".xls,.xlsx,.csv" required><br><br>
                <button class="btn pri">بارگذاری و ثبت گروهی منابع</button>
            </form>
        </div>
        <div class="card">
            <h2>📥 ورودی از استاندارد ISO 2709 و فایل .txt کتابخانه ملی (با پیش‌نمایش)</h2>
            <p style="font-size:12.8px;line-height:1.7">فایل <code>selectedRecords.txt</code> دریافت‌شده از کتابخانه ملی ایران یا فایل‌های `.mrc` و `.iso` را انتخاب کنید تا ابتدا پیش‌نمایش فیلدها نمایش داده شود:</p>
            <form method="post" action="?p=imp_iso_preview" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="from_page" value="imp_exp">
                <label>محل نگهداری رکوردهای ورودی</label><select name="loc">';
                foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '      </select><br><br>
                <label>انتخاب فایل ایزو (.txt, .mrc, .iso, .dat)</label><input type="file" name="f" accept=".txt,.mrc,.iso,.dat" required><br><br>
                <button class="btn pri">🧪 استخراج و پیش‌نمایش رکوردهای ایزو ۲۷۰۹</button>
            </form>
        </div>
    </div>
    <div class="card">
        <h2>📤 مرکز خروجی‌ها (اکسل و ایزو ۲۷۰۹)</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a class="btn ok" href="?p=export_excel_rec">📊 دریافت خروجی اکسل کامل منابع (Excel .xls)</a>
            <a class="btn ok" href="?p=export_excel_mem">👥 دریافت خروجی اکسل اعضا (Excel .xls)</a>
            <a class="btn pri" href="?p=export">📦 دریافت خروجی استاندارد ایران‌مارک (ISO 2709 .mrc)</a>
        </div>
    </div>';

// ==================== پشتیبان‌گیری و بازیابی ====================
} elseif ($p === 'backup') {
    $allowed_locs = allowed_locations();
    echo '<h1>💾 پشتیبان‌گیری و بازیابی اطلاعات (ویژه هر محل نگهداری و کل سیستم)</h1>';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'db_restored') echo '<div class="ok-msg">✅ فایل دیتابیس کامل سیستم با موفقیت بازیابی و جایگزین شد.</div>';
        elseif (strpos($_GET['msg'], 'json_') === 0) {
            $pts = explode('_', $_GET['msg']);
            echo '<div class="ok-msg">✅ بازیابی پشتیبان انجام شد: تعداد ' . tr_num($pts[1]) . ' منبع و ' . tr_num($pts[2]) . ' عضو با موفقیت بازیابی شدند.</div>';
        }
    }

    echo '<div class="grid">';
    if (is_super_admin()) {
        echo '<div class="card" style="border:2px solid var(--blue)">
            <h2>📦 پشتیبان‌گیری کامل از کل سیستم و تمام محل‌ها (ویژه مدیر کل)</h2>
            <p style="font-size:13px;line-height:1.8">شامل تمامی منابع، اعضا، گردش امانات، تنظیمات پرتال، کاربران و تمام محل‌های نگهداری:</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
                <a class="btn pri" href="?p=dl_full_db">🗄️ دانلود فایل دیتابیس کامل (library.db)</a>
                <a class="btn ok" href="?p=dl_json_bk&loc=all">📄 دانلود فایل پشتیبان جامع کل سیستم (.json)</a>
            </div>
        </div>';
    }

    echo '<div class="card" style="border:2px solid var(--ok)">
        <h2>📍 پشتیبان‌گیری اختصاصی از یک محل نگهداری</h2>
        <p style="font-size:13px;line-height:1.8">دریافت فایل پشتیبان (شامل منابع، اعضا، امانات و سفارش‌ها) مربوط به محل نگهداری انتخابی:</p>
        <form method="get" class="row"><input type="hidden" name="p" value="dl_json_bk">
            <div style="flex:1"><label>انتخاب محل نگهداری جهت پشتیبان‌گیری</label><select name="loc">';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
    echo '  </select></div>
            <button class="btn ok">📥 دانلود پشتیبان این محل (.json)</button>
        </form>
    </div></div>';

    echo '<div class="card">
        <h2>🔄 بازیابی اطلاعات (Restore) از فایل پشتیبان</h2>
        <p style="font-size:13px;line-height:1.8">می‌توانید فایل پشتیبان <code>.json</code> (مربوط به یک محل نگهداری یا کل سیستم) و یا فایل دیتابیس <code>.db</code> (ویژه مدیر کل) را بارگذاری و بازیابی نمایید:</p>
        <form method="post" action="?p=restore_bk" enctype="multipart/form-data" class="row" onsubmit="return confirm(\'آیا از بازیابی اطلاعات این فایل پشتیبان مطمئن هستید؟\')">
            <input type="hidden" name="t" value="' . $tok . '">
            <div style="flex:1"><label>انتخاب فایل پشتیبان (.json' . (is_super_admin() ? ' یا .db' : '') . ') *</label><input type="file" name="bk_file" accept=".json' . (is_super_admin() ? ',.db' : '') . '" required></div>
            <div style="width:260px"><label>محل نگهداری مقصد برای رکوردهای بازیابی‌شده</label><select name="target_loc">';
            if (!is_loc_restricted()) echo '<option value="keep">حفظ محل نگهداری ثبت‌شده در فایل پشتیبان</option>';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">انتقال به محل: ' . h($lc) . '</option>';
    echo '  </select></div>
            <button class="btn pri">⚡ شروع بازیابی اطلاعات</button>
        </form>
    </div>';

// ==================== مدیریت اعضا ====================
} elseif ($p === 'mem') {
    $edit_id = (int)(isset($_GET['edit']) ? $_GET['edit'] : 0);
    $allowed_locs = allowed_locations();
    $em = array('id'=>0, 'name'=>'', 'phone'=>'', 'mcode'=>'', 'loc'=>$allowed_locs[0]);
    $w_loc = is_loc_restricted() ? " WHERE loc=" . $db->quote(user_loc()) : "";
    if ($edit_id) {
        $s = $db->prepare("SELECT * FROM members WHERE id=?" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""));
        $s->execute(array($edit_id));
        $row_m = $s->fetch(PDO::FETCH_ASSOC);
        if ($row_m) $em = $row_m;
    }
    echo '<h1>👥 مدیریت اعضا و ورودی/خروجی اکسل</h1>';
    if (isset($_GET['msg']) && strpos($_GET['msg'], 'imp_') === 0) {
        echo '<div class="ok-msg">تعداد ' . tr_num(substr($_GET['msg'], 4)) . ' عضو جدید با موفقیت ثبت شد.</div>';
    }
    echo '<div class="grid">
        <div class="card">
            <h2>' . ($em['id'] ? '✏️ ویرایش اطلاعات عضو' : '➕ افزودن عضو جدید') . '</h2>
            <form method="post" action="?p=savemem">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $em['id'] . '">
                <label>نام و نام خانوادگی *</label><input name="name" value="' . h($em['name']) . '" required><br><br>
                <label>شماره تماس</label><input name="phone" value="' . h($em['phone']) . '"><br><br>
                <label>کد عضویت / کد ملی</label><input name="mcode" value="' . h($em['mcode']) . '"><br><br>
                <label>محل نگهداری / بخش عضویت</label><select name="loc">';
                foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($em['loc'] === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
    echo '      </select><br><br>
                <button class="btn pri">' . ($em['id'] ? 'ذخیره تغییرات' : 'ثبت عضو') . '</button>
                ' . ($em['id'] ? '<a class="btn" href="?p=mem">انصراف</a>' : '') . '
            </form>
        </div>
        <div class="card">
            <h2>📥 ورودی و خروجی اکسل اعضا</h2>
            <p style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn" href="?p=sample_mem">📄 دانلود فایل نمونه اکسل اعضا</a>
                <a class="btn ok" href="?p=export_excel_mem">📊 خروجی اکسل کل اعضا</a>
            </p>
            <form method="post" action="?p=imp_xl_mem" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '">
                <label>محل نگهداری اعضای ورودی</label><select name="loc">';
                foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
    echo '      </select><br><br>
                <label>انتخاب فایل اکسل (.xls, .xlsx, .csv)</label><input type="file" name="f" accept=".xls,.xlsx,.csv" required><br><br>
                <button class="btn pri">بارگذاری لیست اعضا</button>
            </form>
        </div>
    </div>';

    echo '<div class="card"><h2>فهرست اعضای کتابخانه</h2><table><tr><th>کد عضویت</th><th>نام و نام خانوادگی</th><th>محل نگهداری</th><th>تلفن</th><th>عملیات</th></tr>';
    foreach ($db->query("SELECT * FROM members $w_loc ORDER BY id DESC LIMIT 300") as $x) {
        echo '<tr><td>' . tr_num(h($x['mcode'] ? $x['mcode'] : $x['id'])) . '</td><td><b>' . h($x['name']) . '</b></td><td><span class="badge">' . h($x['loc']) . '</span></td><td>' . tr_num(h($x['phone'])) . '</td>
        <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
            <a class="btn-sm ok" href="?p=rec&sel_mid=' . $x['id'] . '">میز امانت</a>
            <a class="btn-sm pri" href="?p=mem&edit=' . $x['id'] . '">ویرایش</a>
            ' . post_btn('delmem', 'حذف', array('id'=>$x['id']), 'btn-sm dng') . '
        </div></td></tr>';
    }
    echo '</table></div>';

// ==================== امانت‌های جاری ====================
} elseif ($p === 'loan') {
    $w_loan = is_loc_restricted() ? " AND r.loc=" . $db->quote(user_loc()) : "";
    echo '<h1>📅 فهرست امانت‌های جاری' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1><div class="card"><table><tr><th>شماره ثبت</th><th>منبع</th><th>محل</th><th>عضو</th><th>تاریخ امانت</th><th>موعد بازگشت</th><th>تمدید / تغییر مهلت</th><th>عملیات</th></tr>';
    foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name FROM loans l JOIN records r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE l.ret IS NULL $w_loan ORDER BY l.due") as $x) {
        echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td><span class="badge">' . h($x['loc']) . '</span></td><td>' . h($x['name']) . '</td><td>' . jdate($x['d_out']) . '</td>
        <td class="' . ($x['due'] < date('Y-m-d') ? 'warn' : '') . '">' . jdate($x['due']) . '</td>
        <td><form method="post" action="?p=editloan" style="display:flex;gap:6px;max-width:170px">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $x['id'] . '">
            <input type="number" name="add_days" value="7" style="width:65px;padding:4px 8px"><button class="btn-sm">اعمال (روز)</button></form></td>
        <td>' . post_btn('back', 'ثبت بازگشت', array('id' => $x['id']), 'btn-sm ok') . '</td></tr>';
    }
    echo '</table></div>';

// ==================== گزارش‌ها ====================
} elseif ($p === 'rep') {
    $type = isset($_GET['type']) ? $_GET['type'] : 'overdue';
    $w_r = is_loc_restricted() ? " AND r.loc=" . $db->quote(user_loc()) : "";
    $w_rec_only = is_loc_restricted() ? " WHERE loc=" . $db->quote(user_loc()) : "";

    echo '<h1>📈 گزارش‌های جامع کتابخانه آسمان' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1>';
    echo '<div class="tabs no-print">
        <a class="' . ($type === 'overdue' ? 'on' : '') . '" href="?p=rep&type=overdue">امانت‌های دارای دیرکرد</a>
        <a class="' . ($type === 'top_books' ? 'on' : '') . '" href="?p=rep&type=top_books">پرامانت‌ترین منابع</a>
        <a class="' . ($type === 'top_mems' ? 'on' : '') . '" href="?p=rep&type=top_mems">فعال‌ترین اعضا</a>
        <a class="' . ($type === 'by_loc' ? 'on' : '') . '" href="?p=rep&type=by_loc">موجودی به تفکیک محل نگهداری</a>
        <a class="' . ($type === 'by_type' ? 'on' : '') . '" href="?p=rep&type=by_type">آمار تفکیکی انواع منابع</a>
        <a class="' . ($type === 'history' ? 'on' : '') . '" href="?p=rep&type=history">تاریخچه کل گردش امانات</a>
        <a class="btn ok" href="?p=export_excel_rec" style="margin-right:auto">📊 خروجی اکسل منابع</a>
        <button class="btn pri" onclick="window.print()">🖨️ چاپ گزارش</button>
    </div><div class="card">';

    if ($type === 'overdue') {
        echo '<h2>گزارش امانت‌های دارای تأخیر (تا تاریخ ' . jdate('now') . ')</h2><table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>محل</th><th>نام عضو</th><th>تلفن عضو</th><th>تاریخ امانت</th><th>موعد بازگشت</th></tr>';
        foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name, m.phone FROM loans l JOIN records r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE l.ret IS NULL AND l.due < date('now') $w_r ORDER BY l.due") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td>' . h($x['loc']) . '</td><td>' . h($x['name']) . '</td><td>' . tr_num(h($x['phone'])) . '</td><td>' . jdate($x['d_out']) . '</td><td class="warn">' . jdate($x['due']) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'top_books') {
        echo '<h2>گزارش پرامانت‌ترین منابع کتابخانه</h2><table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>پدیدآور</th><th>محل نگهداری</th><th>تعداد دفعات امانت</th></tr>';
        foreach ($db->query("SELECT r.reg_no, r.f200a, r.f700, r.loc, COUNT(l.id) as cnt FROM records r JOIN loans l ON r.id=l.rid WHERE 1=1 $w_r GROUP BY r.id ORDER BY cnt DESC LIMIT 50") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td>' . h($x['f700']) . '</td><td>' . h($x['loc']) . '</td><td><b>' . tr_num($x['cnt']) . '</b> مرتبه</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'top_mems') {
        echo '<h2>گزارش فعال‌ترین اعضای کتابخانه</h2><table><tr><th>نام عضو</th><th>کد عضویت</th><th>محل</th><th>تلفن</th><th>تعداد امانت</th></tr>';
        foreach ($db->query("SELECT m.name, m.mcode, m.loc, m.phone, COUNT(l.id) as cnt FROM members m JOIN loans l ON m.id=l.mid JOIN records r ON r.id=l.rid WHERE 1=1 $w_r GROUP BY m.id ORDER BY cnt DESC LIMIT 50") as $x) {
            echo '<tr><td><b>' . h($x['name']) . '</b></td><td>' . tr_num(h($x['mcode'])) . '</td><td>' . h($x['loc']) . '</td><td>' . tr_num(h($x['phone'])) . '</td><td><b>' . tr_num($x['cnt']) . '</b> کتاب</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'by_loc') {
        echo '<h2>گزارش آماری موجودی بر اساس محل نگهداری</h2><table><tr><th>محل نگهداری</th><th>پیش‌شماره ثبت</th><th>تعداد عناوین</th><th>مجموع نسخه‌ها</th></tr>';
        foreach ($db->query("SELECT loc, COUNT(*) as c1, COALESCE(SUM(copies),0) as c2 FROM records $w_rec_only GROUP BY loc") as $x) {
            $ln = $x['loc'] ? $x['loc'] : 'مخزن اصلی';
            echo '<tr><td><b>' . h($ln) . '</b></td><td><span class="badge">' . tr_num(get_loc_prefix($ln)) . '</span></td><td>' . tr_num($x['c1']) . '</td><td>' . tr_num($x['c2']) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'by_type') {
        echo '<h2>گزارش آماری موجودی بر اساس نوع منبع</h2><table><tr><th>نوع منبع</th><th>تعداد عناوین</th><th>مجموع نسخه‌ها</th></tr>';
        foreach ($RTypes as $k => $v) {
            $s = $db->prepare("SELECT COUNT(*), COALESCE(SUM(copies),0) FROM records WHERE rtype=?" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""));
            $s->execute(array($k));
            $row_t = $s->fetch(PDO::FETCH_NUM);
            echo '<tr><td><b>' . $v . '</b></td><td>' . tr_num($row_t[0]) . '</td><td>' . tr_num($row_t[1]) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'history') {
        echo '<h2>تاریخچه کامل گردش امانت‌ها</h2><table><tr><th>شماره ثبت</th><th>منبع</th><th>محل</th><th>عضو</th><th>تاریخ تحویل</th><th>موعد</th><th>تاریخ بازگشت</th></tr>';
        foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name FROM loans l JOIN records r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE 1=1 $w_r ORDER BY l.id DESC LIMIT 200") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td>' . h($x['f200a']) . '</td><td>' . h($x['loc']) . '</td><td>' . h($x['name']) . '</td><td>' . jdate($x['d_out']) . '</td><td>' . jdate($x['due']) . '</td><td>' . ($x['ret'] ? jdate($x['ret']) : '<span class="warn">نزد عضو</span>') . '</td></tr>';
        }
        echo '</table>';
    }
    echo '</div>';

// ==================== مدیریت کاربران ====================
} elseif ($p === 'users') {
    $edit_uid = (int)(isset($_GET['edit']) ? $_GET['edit'] : 0);
    $eu = array('id'=>0, 'u'=>'', 'perms'=>'all', 'uloc'=>'all');
    if ($edit_uid) {
        $st_eu = $db->prepare("SELECT * FROM users WHERE id=?");
        $st_eu->execute(array($edit_uid));
        $f_eu = $st_eu->fetch(PDO::FETCH_ASSOC);
        if ($f_eu) $eu = $f_eu;
    }
    $eu_perms = ($eu['perms'] === 'all' || empty($eu['perms'])) ? array_keys($AllPerms) : explode(',', $eu['perms']);

    echo '<h1>🔐 مدیریت کاربران، ویرایش سطوح دسترسی و اختصاص محل نگهداری</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">اطلاعات کاربر، دسترسی‌ها و محل نگهداری با موفقیت ذخیره شد.</div>';
    echo '<div class="grid"><div class="card"><h2>' . ($eu['id'] ? '✏️ ویرایش کاربر «' . h($eu['u']) . '»' : '➕ افزودن کاربر جدید') . '</h2>
    <form method="post" action="?p=saveuser">
        <input type="hidden" name="t" value="' . $tok . '">
        <input type="hidden" name="edit_uid" value="' . $eu['id'] . '">
        <label>نام کاربری *</label><input name="u" value="' . h($eu['u']) . '" required placeholder="مثلاً: user_koodak"><br><br>
        <label>گذرواژه ' . ($eu['id'] ? '(اگر خالی بماند، رمز قبلی حفظ می‌شود)' : '*') . '</label><input type="password" name="pw"' . ($eu['id'] ? '' : ' required') . '><br><br>
        <label style="color:var(--blue)">محدودسازی دسترسی کاربر به محل نگهداری *</label>
        <select name="uloc">
            <option value="all"' . ($eu['uloc'] === 'all' ? ' selected' : '') . '>همه محل‌های نگهداری (دسترسی سراسری مدیران)</option>';
            foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($eu['uloc'] === $lc ? ' selected' : '') . '>فقط محل: ' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '</select><br><br>
        <label>اختصاص دسترسی به منوها:</label><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:8px">';
        foreach ($AllPerms as $k => $v) {
            $chk = in_array($k, $eu_perms) ? ' checked' : '';
            echo '<label style="font-weight:normal"><input type="checkbox" name="perms[]" value="' . $k . '"' . $chk . '> ' . $v . '</label>';
        }
    echo '</div><br>
        <button class="btn pri">' . ($eu['id'] ? '💾 ذخیره تغییرات کاربر' : '➕ ثبت کاربر جدید') . '</button>
        ' . ($eu['id'] ? '<a class="btn" href="?p=users">انصراف</a>' : '') . '
    </form></div>';

    echo '<div class="card"><h2>فهرست کاربران سامانه</h2>
    <p style="font-size:12.5px">💡 کاربران پیش‌فرض <b>admin</b> (مدیر کتابخانه) و <b>admin1</b> (مدیر فنی) دارای دسترسی کل هستند.</p>
    <table><tr><th>نام کاربری</th><th>محل نگهداری مجاز</th><th>بخش‌های مجاز</th><th>عملیات</th></tr>';
    foreach ($db->query("SELECT * FROM users") as $u) {
        $p_arr = array();
        foreach (explode(',', $u['perms']) as $pk) {
            $p_arr[] = isset($AllPerms[$pk]) ? $AllPerms[$pk] : $pk;
        }
        $is_sup = ($u['u'] === 'admin' || $u['u'] === 'admin1');
        $p_list = ($is_sup || $u['perms'] === 'all') ? 'دسترسی کامل (مدیر کل)' : implode('، ', $p_arr);
        $loc_label = ($is_sup || $u['uloc'] === 'all' || empty($u['uloc'])) ? 'همه محل‌ها' : $u['uloc'] . ' (پیش‌شماره ' . tr_num(get_loc_prefix($u['uloc'])) . ')';
        echo '<tr><td><b>' . h($u['u']) . '</b></td><td><span class="badge">' . h($loc_label) . '</span></td><td>' . h($p_list) . '</td>
        <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
            <a class="btn-sm pri" href="?p=users&edit=' . $u['id'] . '">ویرایش</a>
            ' . (!$is_sup ? post_btn('deluser', 'حذف', array('u'=>$u['u']), 'btn-sm dng') : '') . '
        </div></td></tr>';
    }
    echo '</table></div></div>';
}

shell(isset($AllPerms[$p]) ? $AllPerms[$p] : 'پرتال کتابخانه آسمان', ob_get_clean(), $p, $in);