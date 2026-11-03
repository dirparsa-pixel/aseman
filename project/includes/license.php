<?php
// هسته بررسی لایسنس، امضای دیجیتال HMAC و کنترل ظرفیت‌های مرکز
if (!defined('ASEMAN_ROOT')) die('Access Denied');

require_once ASEMAN_ROOT . '/config/secrets.php';

function lic_en_digits($s) {
    return str_replace(
        array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'),
        array('0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'),
        (string)$s
    );
}

function current_host_clean() {
    $h = isset($_SERVER['HTTP_HOST']) ? strtolower(trim(lic_en_digits($_SERVER['HTTP_HOST']))) : 'localhost';
    $h = preg_replace('/:\d+$/', '', $h);
    if (strpos($h, 'www.') === 0) $h = substr($h, 4);
    return $h !== '' ? $h : 'localhost';
}

function install_fingerprint($mode = 'strict') {
    $host = current_host_clean();
    if ($mode === 'domain') {
        $raw = 'DOM|' . $host;
    } else {
        $dir = str_replace('\\', '/', strtolower(realpath(ASEMAN_ROOT) ?: ASEMAN_ROOT));
        $srv = strtolower(php_uname('n'));
        $raw = 'STRICT|' . $host . '|' . $dir . '|' . $srv;
    }
    $hash = strtoupper(substr(hash_hmac('sha256', $raw, ASEMAN_VENDOR_SECRET), 0, 12));
    return 'ASM-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
}

function lic_default_payload() {
    return array(
        'mode'            => 'demo',
        'plan_title'      => 'نسخه آزمایشی (دمو)',
        'client_name'     => 'مرکز آزمایشی',
        'contract_no'     => '-',
        'bind_mode'       => 'domain',
        'install_code'    => '',
        'allowed_domains' => '*',
        'exp_date'        => '',
        'support_exp_date'=> '',
        'max_locs'        => 1,
        'max_records'     => 50,
        'max_items'       => 100,
        'max_members'     => 50,
        'mod_dash'        => 1,
        'mod_rec'         => 1,
        'mod_form'        => 1,
        'mod_org'         => 1,
        'mod_locs'        => 1,
        'mod_orders'      => 1,
        'mod_tickets'     => 1,
        'mod_imp_exp'     => 1,
        'mod_stats'       => 1,
        'mod_authority'   => 1,
        'mod_bulk'        => 0,
        'mod_backup'      => 1,
        'mod_mem'         => 1,
        'mod_loan'        => 1,
        'mod_rep'         => 1,
        'mod_cms'         => 1,
        'mod_users'       => 1,
        'mod_chat'        => 1,
        'lock_theme'      => 0,
        'lock_raw_db'     => 1,
        'idle_limit'      => 600,
        'max_login_try'   => 5,
        'max_upload_mb'   => 20,
        'vendor_name'     => 'نرم‌افزار جامع کتابخانه‌ای آسمان',
        'vendor_url'      => 'https://asemanlib.ir',
        'show_footer'     => 1
    );
}

function lic_sign_data($arr) {
    ksort($arr);
    $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
    return hash_hmac('sha256', $json, ASEMAN_VENDOR_SECRET);
}

function lic_get_public_key() {
    $f = ASEMAN_ROOT . '/config/license_public.php';
    if (!is_file($f)) return '';
    if (!defined('APP_ROOT')) define('APP_ROOT', ASEMAN_ROOT);
    $pk_b64 = @include $f;
    if (!is_string($pk_b64) || trim($pk_b64) === '') return '';
    $pk = base64_decode(trim($pk_b64), true);
    return ($pk !== false && strlen($pk) === 32) ? $pk : '';
}

function lic_b64u_dec($s) {
    $s = strtr((string)$s, '-_', '+/');
    $p = strlen($s) % 4;
    if ($p) $s .= str_repeat('=', 4 - $p);
    return base64_decode($s, true);
}

function parse_vendor_key($key_str) {
    $key_str = preg_replace('/\s+/', '', trim(lic_en_digits($key_str)));
    if (strpos($key_str, 'ASM1-') === 0 || strpos($key_str, 'ASM2-') === 0) {
        $body = substr($key_str, 5);
        $pts = explode('.', $body);
        if (count($pts) !== 2) return false;
        $b64 = $pts[0];
        $sig = strtoupper($pts[1]);
        $expected = strtoupper(substr(hash_hmac('sha256', $b64, ASEMAN_VENDOR_SECRET), 0, 16));
        if (hash_equals($expected, $sig)) {
            $json = lic_b64u_dec($b64);
            $data = json_decode((string)$json, true);
            return is_array($data) ? $data : false;
        }
        // Fallback to Ed25519 if ASM2- with 64-byte signature
        if (strpos($key_str, 'ASM2-') === 0) {
            $json = lic_b64u_dec($b64);
            $sig_raw = lic_b64u_dec($pts[1]);
            $pk = lic_get_public_key();
            if ($json !== false && $sig_raw !== false && strlen($sig_raw) === 64 && $pk !== '' && function_exists('sodium_crypto_sign_verify_detached')) {
                if (sodium_crypto_sign_verify_detached($sig_raw, 'ASM2|' . $json, $pk)) {
                    $arr = json_decode($json, true);
                    return is_array($arr) ? $arr : false;
                }
            }
        }
    }
    return false;
}

function verify_asm2_key($key_str) {
    return parse_vendor_key($key_str);
}

function get_license() {
    if (isset($GLOBALS['_LIC_CACHE']) && $GLOBALS['_LIC_CACHE'] !== null) {
        return $GLOBALS['_LIC_CACHE'];
    }
    $def = lic_default_payload();
    $asm2_key = get_cms('lic_asm2_key');
    $raw = '';
    $sig = '';
    if ($asm2_key !== '') {
        $arr_asm2 = parse_vendor_key($asm2_key);
        if (is_array($arr_asm2)) {
            $raw = json_encode($arr_asm2, JSON_UNESCAPED_UNICODE);
            $sig = lic_sign_data($arr_asm2);
        }
    }
    if ($raw === '') {
        $raw = get_cms('lic_payload');
        $sig = get_cms('lic_sig');
    }
    if ($raw === '' || $sig === '') {
        $def['status'] = 'demo';
        $def['status_msg'] = 'سامانه در حالت آزمایشی (دمو) قرار دارد (حداکثر ۵۰ رکورد و ۱ کتابخانه).';
        $def['valid'] = true;
        $_SESSION['idle_lim'] = (int)$def['idle_limit'];
        $GLOBALS['_LIC_CACHE'] = $def;
        return $def;
    }
    $arr = json_decode($raw, true);
    if (!is_array($arr) || !hash_equals(lic_sign_data($arr), (string)$sig)) {
        $def['status'] = 'tampered';
        $def['status_msg'] = 'خطای امنیتی: امضای دیجیتال مجوز نامعتبر است!';
        $def['valid'] = false;
        $GLOBALS['_LIC_CACHE'] = $def;
        return $def;
    }

    $lic = array_merge($def, $arr);
    $lic['valid'] = true;
    $lic['status'] = $lic['mode'];
    $lic['status_msg'] = ($lic['mode'] === 'commercial') ? 'مجوز تجاری این مرکز معتبر و فعال است.' : 'سامانه در حالت آزمایشی (دمو) قرار دارد.';

    $cur_host = current_host_clean();
    $clean_dom_str = strtolower(lic_en_digits($lic['allowed_domains']));
    $allowed_doms = array_filter(array_map('trim', explode(',', $clean_dom_str)));
    $domain_ok = empty($allowed_doms) || in_array('*', $allowed_doms) || in_array($cur_host, $allowed_doms) || in_array('localhost', $allowed_doms) || in_array('127.0.0.1', $allowed_doms);

    $clean_inst = strtoupper(trim(lic_en_digits($lic['install_code'])));
    $fp_strict = install_fingerprint('strict');
    $fp_domain = install_fingerprint('domain');
    $fp_ok = ($clean_inst === '' || $clean_inst === '*' || $lic['bind_mode'] !== 'strict' || $clean_inst === $fp_strict || $clean_inst === $fp_domain);

    if ($lic['mode'] === 'commercial' && (!$domain_ok || !$fp_ok)) {
        $lic['status'] = 'host_mismatch';
        $lic['status_msg'] = 'عدم تطابق دامنه/هاست: لطفاً کد فعال‌سازی صادرشده توسط فروشنده را در بخش «تنظیمات مجوز و ارتقا» وارد نمایید.';
        $lic['max_locs'] = 1;
        $lic['max_records'] = 50;
        $lic['max_items'] = 100;
        $lic['max_members'] = 50;
    }

    $lic['support_valid'] = true;
    $sup_exp = trim(lic_en_digits($lic['support_exp_date']));
    if ($sup_exp !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sup_exp) && date('Y-m-d') > $sup_exp) {
        $lic['support_valid'] = false;
    }
    $exp_d = trim(lic_en_digits($lic['exp_date']));
    if ($exp_d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp_d) && date('Y-m-d') > $exp_d) {
        $lic['status'] = 'expired';
        $lic['status_msg'] = 'مهلت اعتبار مجوز این مرکز در تاریخ ' . jdate($exp_d) . ' به پایان رسیده است.';
        $lic['valid'] = false;
    }

    $_SESSION['idle_lim'] = (int)$lic['idle_limit'];
    $GLOBALS['_LIC_CACHE'] = $lic;
    return $lic;
}

function lic_check_record_quota($add_count = 1) {
    global $db;
    $lic = get_license();
    if (!$lic['valid']) { $GLOBALS['lic_err'] = $lic['status_msg']; return false; }
    $max = (int)$lic['max_records'];
    if ($max <= 0) return true;
    $cur = (int)$db->query("SELECT COUNT(*) FROM records")->fetchColumn();
    if (($cur + $add_count) > $max) {
        $GLOBALS['lic_err'] = 'سقف مجاز ثبت رکورد کتابشناختی در مجوز این مرکز (' . tr_num($max) . ' عنوان) تکمیل شده است.';
        return false;
    }
    return true;
}

function lic_avail_items_quota($requested = 1) {
    global $db;
    $lic = get_license();
    if (!$lic['valid']) { $GLOBALS['lic_err'] = $lic['status_msg']; return 0; }
    $max = (int)$lic['max_items'];
    if ($max <= 0) return $requested;
    $cur = (int)$db->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $rem = max(0, $max - $cur);
    if ($rem < $requested) {
        $GLOBALS['lic_err'] = 'سقف مجاز ثبت مجلدات در مجوز این مرکز (' . tr_num($max) . ' جلد) تکمیل شده است.';
    }
    return min($requested, $rem);
}

function lic_check_member_quota($add_count = 1) {
    global $db;
    $lic = get_license();
    if (!$lic['valid']) { $GLOBALS['lic_err'] = $lic['status_msg']; return false; }
    $max = (int)$lic['max_members'];
    if ($max <= 0) return true;
    $cur = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
    if (($cur + $add_count) > $max) {
        $GLOBALS['lic_err'] = 'سقف مجاز ثبت اعضا در مجوز این مرکز (' . tr_num($max) . ' عضو) تکمیل شده است.';
        return false;
    }
    return true;
}
