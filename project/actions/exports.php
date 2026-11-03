<?php
// خروجی‌های استاندارد اکسل، ایزو ۲۷۰۹ (.mrc) و فایل‌های پشتیبان (.json)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ==================== خروجی‌های استاندارد و پشتیبان‌گیری (Backup) ====================
if ($in && $p === 'dl_full_db' && can('backup') && is_super_admin()) {
    header('Location: ?p=dl_json_bk&loc=all');
    exit;
}
if ($in && $p === 'dl_json_bk' && can('backup')) {
    $req_loc = isset($_GET['loc']) ? trim($_GET['loc']) : 'all';
    if (is_loc_restricted()) $req_loc = user_loc();
    $data_bk = array('version' => '2.2', 'date' => date('Y-m-d H:i:s'), 'scope' => $req_loc);
    if ($req_loc === 'all' && is_super_admin()) {
        $data_bk['records'] = $db->query("SELECT * FROM records")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['items']   = $db->query("SELECT * FROM items")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['loc_rec'] = $db->query("SELECT * FROM loc_rec")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['members'] = $db->query("SELECT * FROM members")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['loans']   = $db->query("SELECT * FROM loans")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['orders']  = $db->query("SELECT * FROM orders")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['cms']     = $db->query("SELECT * FROM cms")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['boxes']   = $db->query("SELECT * FROM portal_boxes")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['news']    = $db->query("SELECT * FROM news")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['pages']   = $db->query("SELECT * FROM pages")->fetchAll(PDO::FETCH_ASSOC);
        $data_bk['menus']   = $db->query("SELECT * FROM portal_menus")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st1 = $db->prepare("SELECT * FROM records WHERE id IN (SELECT rid FROM items WHERE loc=?)"); $st1->execute(array($req_loc));
        $data_bk['records'] = $st1->fetchAll(PDO::FETCH_ASSOC);
        $st1b = $db->prepare("SELECT * FROM items WHERE loc=?"); $st1b->execute(array($req_loc));
        $data_bk['items'] = $st1b->fetchAll(PDO::FETCH_ASSOC);
        $st2 = $db->prepare("SELECT * FROM members WHERE loc=?"); $st2->execute(array($req_loc));
        $data_bk['members'] = $st2->fetchAll(PDO::FETCH_ASSOC);
        $st3 = $db->prepare("SELECT * FROM orders WHERE loc=?"); $st3->execute(array($req_loc));
        $data_bk['orders']  = $st3->fetchAll(PDO::FETCH_ASSOC);
        $st4 = $db->prepare("SELECT l.* FROM loans l JOIN items r ON r.id=l.rid WHERE r.loc=?"); $st4->execute(array($req_loc));
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
    $w_exp = is_loc_restricted() ? "WHERE id IN (SELECT rid FROM items WHERE loc = " . $db->quote(user_loc()) . ")" : "";
    $it_sql = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
    $map = array(
        '010'=>array('a'=>'f010'),'101'=>array('a'=>'f101'),'200'=>array('a'=>'f200a','e'=>'f200e','f'=>'f200f'),
        '205'=>array('a'=>'f205'),'210'=>array('a'=>'f210a','c'=>'f210c','d'=>'f210d'),'215'=>array('a'=>'f215'),
        '225'=>array('a'=>'f225'),'300'=>array('a'=>'f300'),'606'=>array('a'=>'f606'),'676'=>array('a'=>'f676'),
        '680'=>array('a'=>'f686'),'700'=>array('a'=>'f700'),'710'=>array('a'=>'f710')
    );
    $its = $db->prepare("SELECT loc, reg_no, vol FROM items WHERE rid=? $it_sql ORDER BY loc, id");
    foreach ($db->query("SELECT * FROM records $w_exp") as $r) {
        $fl = array(array('001', (string)$r['id']));
        foreach ($map as $tag => $subs) {
            $sub_str = '';
            foreach ($subs as $c => $col) {
                if (!empty($r[$col])) $sub_str .= "\x1f" . $c . $r[$col];
            }
            if ($sub_str !== '') $fl[] = array($tag, '  ' . $sub_str);
        }
        $its->execute(array($r['id']));
        foreach ($its->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $fl[] = array('995', '  ' . "\x1f" . 'a' . $it['loc'] . "\x1f" . 'f' . $it['reg_no'] . "\x1f" . 'v' . $it['vol']);
        }
        $dir = ''; $data = '';
        foreach ($fl as $fv) {
            $v = $fv[1] . "\x1e";
            $dir .= $fv[0] . sprintf('%04d%05d', strlen($v), strlen($data));
            $data .= $v;
        }
        $base = 24 + strlen($dir) + 1;
        $len = $base + strlen($data) + 1;
        echo sprintf('%05d', $len) . 'nam  22' . sprintf('%05d', $base) . ' i 450 ' . $dir . "\x1e" . $data . "\x1d";
    }
    exit;
}

if ($in && $p === 'sample_rec') {
    $def_l = is_loc_restricted() ? user_loc() : (isset($_GET['loc']) && in_array($_GET['loc'], $Locations) ? $_GET['loc'] : $Locations[0]);
    $ws_key = isset($_GET['ws']) ? trim($_GET['ws']) : 'book';
    $ws_fields = get_worksheet_active_fields($def_l, $ws_key);
    $cfg_l = get_lib_config($def_l);
    $ws_info = isset($cfg_l['worksheets'][$ws_key]) ? $cfg_l['worksheets'][$ws_key] : reset($cfg_l['worksheets']);
    $headers = array();
    foreach ($ws_fields as $fk => $fd) {
        $headers[] = $fd['full'];
    }
    $headers[] = 'نوع منبع (کتاب/مقاله/پایان‌نامه/سایر)';
    $headers[] = 'تعداد جلد (هر عنوان یک ردیف)';
    $headers[] = 'شماره ثبت اولین جلد (خالی = خودکار)';

    $sample_reg = next_reg_no($def_l);
    $sample_vals_map = array(
        'f010'  => '978-622-128-315-6',
        'f101'  => 'فارسی',
        'f200a' => ($ws_key === 'map' ? 'اطلس جامع جغرافیایی و تاریخی ایران' : 'شرح ابن عقیل بر سیوطی'),
        'f200e' => ($ws_key === 'map' ? 'مقیاس ۱:۱۰۰۰۰۰۰' : 'شرح سیوطی'),
        'f200f' => ($ws_key === 'map' ? 'سازمان جغرافیایی' : 'محمد محی الدین عبد الحمید'),
        'f205'  => ($ws_key === 'map' ? '۱:۱۰۰۰۰۰۰' : 'چاپ اول'),
        'f210a' => 'تهران',
        'f210c' => 'المکتبة العصریه',
        'f210d' => '۱۴۰۵',
        'f215'  => ($ws_key === 'map' ? '۱ نقشه رنگی : ۶۰×۹۰ سم' : '۳۰۰ ص'),
        'f225'  => '',
        'f300'  => 'کتابنامه و نمایه دارد',
        'f606'  => ($ws_key === 'map' ? 'ایران -- نقشه‌ها' : 'صرف و نحو'),
        'f676'  => '۴۹۲/۷۵',
        'f686'  => 'PJ۶۱۰۰',
        'f700'  => 'ابن عقیل، عبدالله بن عبدالرحمن',
        'f710'  => ''
    );
    $row1 = array();
    foreach ($ws_fields as $fk => $fd) {
        $row1[] = isset($sample_vals_map[$fk]) ? $sample_vals_map[$fk] : '';
    }
    $rt_label = isset($RTypes[$ws_info['rtype']]) ? $RTypes[$ws_info['rtype']] : 'کتاب';
    $row1[] = $rt_label;
    $row1[] = '2';
    $row1[] = $sample_reg;

    output_excel_xls('sample_worksheet_' . $ws_key . '.xls', $headers, array($row1));
}

if ($in && $p === 'export_excel_rec' && (can('imp_exp') || can('rec') || can('rep') || can('backup'))) {
    $headers = array_merge(array_values($F), array('نوع منبع', 'تعداد جلد', 'شماره‌های ثبت مجلدات', 'کتابخانه', 'سریال رکورد در محل', 'تاریخ ثبت'));
    $w_exp = is_loc_restricted() ? "WHERE i.loc = " . $db->quote(user_loc()) : "";
    $rows = array();
    foreach ($db->query("SELECT r.*, i.loc AS iloc, COUNT(*) AS vols, STRING_AGG(i.reg_no, '، ') AS regs, (SELECT rec_no FROM loc_rec lr WHERE lr.rid=r.id AND lr.loc=i.loc) AS rn FROM items i JOIN records r ON r.id=i.rid $w_exp GROUP BY r.id, i.rid, i.loc ORDER BY i.loc, MIN(COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0))") as $r) {
        $row = array();
        foreach (array_keys($F) as $fk) $row[] = $r[$fk];
        $row[] = isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب';
        $row[] = $r['vols'];
        $row[] = $r['regs'];
        $row[] = $r['iloc'];
        $row[] = $r['rn'];
        $row[] = jdate($r['created']);
        $rows[] = $row;
    }
    output_excel_xls('aseman_records_export.xls', $headers, $rows);
}

if ($in && $p === 'export_loc_items' && (can('rep') || can('imp_exp'))) {
    $rl = is_loc_restricted() ? user_loc() : trim(isset($_GET['rloc']) ? $_GET['rloc'] : $Locations[0]);
    if (!in_array($rl, $Locations)) $rl = $Locations[0];
    $only = isset($_GET['only']) && $_GET['only'] === 'all' ? 'all' : 'avail';
    $headers = array('ردیف', 'شماره ثبت', 'سریال رکورد در محل', 'عنوان', 'جلد', 'پدیدآور', 'ناشر', 'سال نشر', 'شابک', 'رده دیویی', 'رده کنگره', 'وضعیت');
    $w_av = $only === 'avail' ? " AND NOT EXISTS (SELECT 1 FROM loans l WHERE l.rid=i.id AND l.ret IS NULL)" : "";
    $st = $db->prepare("SELECT i.reg_no, i.vol, r.f200a, r.f700, r.f200f, r.f210c, r.f210d, r.f010, r.f676, r.f686, (SELECT rec_no FROM loc_rec lr WHERE lr.rid=i.rid AND lr.loc=i.loc) AS rn, (SELECT COUNT(*) FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) AS onloan FROM items i JOIN records r ON r.id=i.rid WHERE i.loc=? $w_av ORDER BY COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0)");
    $st->execute(array($rl));
    $rows = array(); $n = 0;
    foreach ($st as $r) {
        $n++;
        $rows[] = array($n, $r['reg_no'], $r['rn'], $r['f200a'], $r['vol'], $r['f700'] ? $r['f700'] : $r['f200f'], $r['f210c'], $r['f210d'], $r['f010'], $r['f676'], $r['f686'], $r['onloan'] ? 'امانت' : 'موجود');
    }
    output_excel_xls('aseman_location_items.xls', $headers, $rows);
}

if ($in && $p === 'export_excel_mem' && (can('mem') || can('imp_exp') || can('backup'))) {
    $headers = array('کد عضویت / کد ملی', 'نام و نام خانوادگی', 'شماره تماس', 'کتابخانه');
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

