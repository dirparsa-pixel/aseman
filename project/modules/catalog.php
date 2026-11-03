<?php
// ماژول فهرست‌نویسی و سازماندهی: ثبت/ویرایش منبع (form)، نمایش شناسنامه مارک (marc) و چاپ برچسب عطف (org)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

if ($p === 'form') {
    $r = array();
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $denied = false;
    $locked_by = null;
    if ($id) {
        $s = $db->prepare("SELECT * FROM records WHERE id=?");
        $s->execute(array($id));
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) { $r = array(); $id = 0; }
        elseif (!can_edit_title($id)) $denied = true;
        else { $locked_by = lock_acquire($id, isset($_GET['force']) && is_super_admin()); }
    } else {
        foreach ($F as $k => $v) { if (isset($_GET[$k])) $r[$k] = $_GET[$k]; }
    }
    if ($locked_by) {
        echo '<div class="card"><p class="warn">این رکورد هم‌اکنون توسط «' . h($locked_by['uname']) . '» (' . h($locked_by['loc']) . ') در حال ویرایش است. برای جلوگیری از تداخل، چند دقیقهٔ دیگر تلاش کنید (قفل پس از ۱۰ دقیقه بی‌فعالیتی آزاد می‌شود).</p>' . (is_super_admin() ? '<a class="btn dng" href="?p=form&id=' . $id . '&force=1">گرفتن قفل (ادمین کل)</a> ' : '') . '<a class="btn" href="?p=marc&id=' . $id . '">مشاهدهٔ رکورد</a></div>';
    } elseif ($denied) {
        echo '<div class="card"><p class="warn">ویرایش مشخصات کتابشناختی فقط برای محل‌هایی مجاز است که این عنوان را نگهداری می‌کنند.</p></div>';
    } else {
    $allowed_locs = allowed_locations();
    $next_regs_by_loc = array();
    $ws_cfg_by_loc = array();
    foreach ($allowed_locs as $lc) {
        $next_regs_by_loc[$lc] = next_reg_no($lc);
        $ws_cfg_by_loc[$lc] = get_lib_config($lc);
    }
    $sel_form_loc = isset($_GET['loc']) && in_array($_GET['loc'], $allowed_locs) ? $_GET['loc'] : $allowed_locs[0];
    $cur_lib_cfg  = $ws_cfg_by_loc[$sel_form_loc];
    $sel_form_ws  = isset($_GET['ws']) && isset($cur_lib_cfg['worksheets'][$_GET['ws']]) ? $_GET['ws'] : key($cur_lib_cfg['worksheets']);
    $active_ws_fields = get_worksheet_active_fields($sel_form_loc, $sel_form_ws);
    $ws_obj = $cur_lib_cfg['worksheets'][$sel_form_ws];

    echo '<h1>' . ($id ? 'ویرایش مشخصات عنوان' : 'ثبت عنوان جدید') . ' (' . ($cur_lib_cfg['cat_std'] === 'marc21' ? 'استاندارد MARC21' : 'استاندارد ایران‌مارک') . ' — ' . h($ws_obj['title']) . ')</h1>
    <form class="card" method="post" action="?p=save" enctype="multipart/form-data">
    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $id . '">
    <input type="hidden" name="old_file" value="' . h(isset($r['file']) ? $r['file'] : '') . '">';
    if (!$id) echo '<p style="font-size:13px;margin:0 0 12px">برای هر عنوان فقط <b>یک رکورد</b> ساخته می‌شود. می‌توانید کتابخانه و <b>کاربرگه ثبت منابع</b> (کتاب، نقشه و...) را انتخاب کنید تا فیلدهای اختصاصی همان کاربرگه نمایش داده شوند.</p>';

    echo '<div style="background:var(--ice);padding:12px 16px;border-radius:12px;border:1px solid var(--line);margin-bottom:16px;display:flex;gap:14px;flex-wrap:wrap;align-items:end">
        <div style="min-width:220px"><label style="color:var(--blue)">انتخاب کتابخانه جهت بارگذاری کاربرگه</label>
            <select id="ws_loc_picker" onchange="location.href=\'?p=form' . ($id ? '&id=' . $id : '') . '&loc=\'+encodeURIComponent(this.value)">';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($lc === $sel_form_loc ? ' selected' : '') . '>' . h($lc) . '</option>';
    echo '  </select>
        </div>
        <div style="min-width:240px"><label style="color:var(--blue)">انتخاب کاربرگه ثبت منبع (Worksheet)</label>
            <select id="ws_key_picker" name="ws_key" onchange="location.href=\'?p=form' . ($id ? '&id=' . $id : '') . '&loc=' . urlencode($sel_form_loc) . '&ws=\'+encodeURIComponent(this.value)">';
            foreach ($cur_lib_cfg['worksheets'] as $wk => $wv) {
                echo '<option value="' . h($wk) . '"' . ($wk === $sel_form_ws ? ' selected' : '') . '>' . h($wv['title']) . '</option>';
            }
    echo '  </select>
        </div>
        <div><a class="btn-sm" href="?p=locs&tab=worksheets&wloc=' . urlencode($sel_form_loc) . '&ws=' . urlencode($sel_form_ws) . '">ویرایش فیلدهای این کاربرگه</a></div>
    </div>';

    echo '<div class="grid"><div><label>نوع منبع</label><select name="rtype">';
    $cur_rt = isset($r['rtype']) ? $r['rtype'] : (isset($ws_obj['rtype']) ? $ws_obj['rtype'] : 'book');
    foreach ($RTypes as $k => $v) echo '<option value="' . $k . '"' . ($cur_rt === $k ? ' selected' : '') . '>' . $v . '</option>';
    echo '</select></div>';
    foreach ($F as $c => $l) {
        $val = isset($r[$c]) ? $r[$c] : ($c === 'f101' ? 'per' : '');
        if (!$id && !isset($active_ws_fields[$c])) {
            echo '<input type="hidden" name="' . $c . '" value="' . h($val) . '">';
            continue;
        }
        $disp_label = isset($active_ws_fields[$c]) ? $active_ws_fields[$c]['full'] : $l;
        $is_req = ($c === 'f200a') || (!empty($active_ws_fields[$c]['req']));
        echo '<div><label>' . h($disp_label) . ($is_req ? ' <span style="color:var(--warn)">*</span>' : '') . '</label><input name="' . $c . '" value="' . h($val) . '"' . ($is_req ? ' required' : '') . (in_array($c, array('f700', 'f710', 'f200f', 'f606')) ? ' data-ac="' . $c . '" autocomplete="off"' : '') . '></div>';
    }
    echo '<div><label>فایل پیوست</label><input type="file" name="file">' . (!empty($r['file']) ? '<small><a href="' . h($r['file']) . '" target="_blank">فایل فعلی</a></small>' : '') . '</div>
<div><label>دسترسی منبع دیجیتال</label><select name="file_access">
<option value="public"' . (isset($r['file_access']) && $r['file_access'] === 'public' ? ' selected' : '') . '>عادی (برای همه)</option>
<option value="member"' . (isset($r['file_access']) && $r['file_access'] === 'member' ? ' selected' : '') . '>ویژه اعضای کتابخانه</option>
<option value="private"' . (isset($r['file_access']) && $r['file_access'] === 'private' ? ' selected' : '') . '>محرمانه / مسدود (فقط مدیر)</option>
</select></div></div>
    <div class="card" style="margin:16px 0 0;border:2px solid var(--blue)"><h2>' . ($id ? 'افزودن مجلد به یک کتابخانه (اختیاری)' : 'کتابخانه و مجلدات') . '</h2><div class="grid">
        <div><label style="color:var(--blue)">کتابخانه</label><select name="loc" id="loc_select" onchange="updateRegByLoc(this.value)">';
    foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . (isset($sel_form_loc) && $sel_form_loc === $lc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '</select></div>
        <div><label>تعداد مجلد (هر مجلد یک شماره ثبت)</label><input type="number" min="' . ($id ? 0 : 1) . '" max="5000" name="vols" value="' . ($id ? 0 : 1) . '"></div>
        <div><label>شمارهٔ ثبت اولین مجلد (خالی = خودکار)</label><input name="reg_no" id="reg_no_input" value="" placeholder="' . h(reset($next_regs_by_loc)) . '"></div>
    </div></div><br><button class="btn pri">ذخیره</button> <a class="btn" href="?p=rec">انصراف</a></form>';
    echo '<script>
        var nextRegsMap = ' . json_encode($next_regs_by_loc, JSON_UNESCAPED_UNICODE) . ';
        function updateRegByLoc(locVal) { var inp = document.getElementById("reg_no_input"); if (inp && nextRegsMap[locVal]) inp.placeholder = nextRegsMap[locVal]; }
        </script>';
    }

// ==================== نمایش شناسنامه مارک و مجلدات ====================
} elseif ($p === 'marc') {
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $s = $db->prepare("SELECT * FROM records WHERE id=?");
    $s->execute(array($id));
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        echo '<div class="card"><p class="warn">رکورد پیدا نشد.</p></div>';
    } else {
        if (isset($_GET['msg'])) {
            echo '<div class="ok-msg">' . ($_GET['msg'] === 'locked' ? 'این رکورد هم‌اکنون توسط کاربر دیگری در حال ویرایش است؛ ذخیره انجام نشد.' : ($_GET['msg'] === 'dup' ? 'این عنوان قبلاً در سامانه فهرست شده بود؛ رکورد تکراری ساخته نشد و مجلدات جدید به همان رکورد افزوده شد.' : 'اطلاعات منبع با موفقیت ذخیره شد.')) . '</div>';
        }
        if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
        $its_all = $db->prepare("SELECT i.*, (SELECT COUNT(*) FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) AS onloan, (SELECT rec_no FROM loc_rec lr WHERE lr.rid=i.rid AND lr.loc=i.loc) AS rn FROM items i WHERE i.rid=? ORDER BY i.loc, COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0)");
        $its_all->execute(array($id));
        $by_loc = array();
        foreach ($its_all->fetchAll(PDO::FETCH_ASSOC) as $it) $by_loc[$it['loc']][] = $it;
        $allowed_locs = allowed_locations();
        echo '<h1>' . h($r['f200a']) . ' <span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></h1><div class="card"><table class="marc">';
        echo '<tr><td>تاریخ ثبت عنوان در سامانه</td><td>' . jdate($r['created']) . '</td></tr>';
        echo '<tr><td>مجموع مجلدات در همه کتابخانه‌ها</td><td>' . tr_num(array_sum(array_map('count', $by_loc))) . ' مجلد در ' . tr_num(count($by_loc)) . ' کتابخانه</td></tr>';
        foreach ($F as $c => $l) {
            if ($r[$c] !== null && $r[$c] !== '') echo '<tr><td>' . h($l) . '</td><td>' . h($r[$c]) . '</td></tr>';
        }
        if (!empty($r['file'])) echo '<tr><td>فایل پیوست</td><td><a class="btn ok" href="' . h($r['file']) . '" target="_blank">دانلود فایل پیوست</a></td></tr>';
        echo '</table><br><div style="display:flex;gap:8px;flex-wrap:wrap">';
        if (can('form') && can_edit_title($id)) echo '<a class="btn pri" href="?p=form&id=' . $id . '">ویرایش مشخصات</a> ';
        if (can('form')) echo post_btn('del', is_loc_restricted() ? 'حذف موجودی محل من' : 'حذف عنوان و مجلدات', array('id' => $id), 'btn dng');
        echo '</div></div>';

        echo '<div class="card"><h2>کتابخانه‌ها، سریال رکورد و مجلدات</h2>';
        if (!$by_loc) echo '<p class="warn">این عنوان در هیچ محلی موجودی ندارد.</p>';
        foreach ($by_loc as $lc => $items_l) {
            $mine = in_array($lc, $allowed_locs);
            echo '<div style="border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:12px"><div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px"><b>' . h($lc) . '</b><span class="badge">سریال رکورد در این کتابخانه: ' . tr_num($items_l[0]['rn']) . '</span><span class="badge">' . tr_num(count($items_l)) . ' جلد</span></div>';
            if ($mine) {
                echo '<table><tr><th>شماره ثبت</th><th>جلد</th><th>وضعیت</th><th>ویرایش شماره ثبت / جلد</th><th></th></tr>';
                $regs_csv = array();
                foreach ($items_l as $it) {
                    $regs_csv[] = $it['reg_no'];
                    echo '<tr><td><b>' . tr_num(h($it['reg_no'])) . '</b></td><td>' . tr_num(h($it['vol'])) . '</td><td>' . ($it['onloan'] ? '<span class="warn">در امانت</span>' : '<span style="color:var(--ok);font-weight:700">موجود</span>') . '</td><td>';
                    if (can('form')) {
                        echo '<form method="post" action="?p=upditem" style="display:flex;gap:6px"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $it['id'] . '"><input name="reg_no" value="' . h($it['reg_no']) . '" style="width:110px;padding:4px 8px"><input name="vol" value="' . h($it['vol']) . '" style="width:60px;padding:4px 8px"><button class="btn-sm">ذخیره</button></form>';
                    }
                    echo '</td><td>' . (can('form') && !$it['onloan'] ? post_btn('delitem', 'حذف مجلد', array('id' => $it['id']), 'btn-sm dng') : '') . '</td></tr>';
                }
                echo '</table><p style="margin:10px 0 0"><a class="btn-sm ok" href="?p=org&regs=' . urlencode(implode(',', $regs_csv)) . '&loc=' . urlencode($lc) . '">چاپ برچسب عطف مجلدات این محل</a></p>';
            } else {
                echo '<p style="margin:0;font-size:13px">این کتابخانه متعلق به شما نیست؛ جزئیات شماره ثبت فقط برای همان محل نمایش داده می‌شود.</p>';
            }
            echo '</div>';
        }
        if (can('imp_exp') || can('form')) {
            echo '<form method="post" action="?p=add_hold" class="row" style="border-top:1px dashed var(--line);padding-top:14px"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="rid" value="' . $id . '"><input type="hidden" name="back" value="marc">
                <div style="width:240px"><label>افزودن مجلد به کتابخانه</label><select name="loc">';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . (isset($sel_form_loc) && $sel_form_loc === $lc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
            echo '</select></div><div style="width:120px"><label>تعداد مجلد</label><input type="number" name="vols" value="1" min="1" max="5000"></div>
                <div style="width:170px"><label>شمارهٔ ثبت اول (اختیاری)</label><input name="reg_no"></div><button class="btn ok">ثبت موجودی با شماره ثبت جدید</button></form>';
        }
        echo '</div>';
    }


// ==================== منوی سازماندهی: چاپ برچسب عطف ====================
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

    echo '<div class="no-print"><h1>سازماندهی و چاپ برچسب عطف (کنگره و دیویی — با قابلیت تنظیم کامل ابعاد و حاشیه‌ها)</h1>';
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
        <div><label>فیلتر کتابخانه</label><select name="loc">' . (!is_loc_restricted() ? '<option value="">همه کتابخانه‌ها</option>' : '');
        foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($loc_f === $lc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '</select></div>
    </div><br><div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn pri">اعمال تنظیمات و پیش‌نمایش</button> <button type="button" class="btn ok" onclick="window.print()">چاپ برچسب‌های عطف</button></div></form></div>';

    $w = array(); $par = array();
    if ($regs_list !== '') {
        $arr = array_filter(array_map('trim', explode(',', str_replace('،', ',', $regs_list))));
        if ($arr) {
            $ph = implode(',', array_fill(0, count($arr), '?'));
            $w[] = "reg_no IN ($ph)";
            $par = array_merge($par, $arr);
        }
    } else {
        if ($reg_from !== '') { $w[] = "COALESCE(NULLIF(regexp_replace(reg_no, '[^0-9]', '', 'g'), '')::bigint, 0) >= ?"; $par[] = (int)$reg_from; }
        if ($reg_to !== '') { $w[] = "COALESCE(NULLIF(regexp_replace(reg_no, '[^0-9]', '', 'g'), '')::bigint, 0) <= ?"; $par[] = (int)$reg_to; }
    }
    if ($loc_f !== '') { $w[] = "loc = ?"; $par[] = $loc_f; }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $st = $db->prepare("SELECT * FROM v_items $where ORDER BY COALESCE(NULLIF(regexp_replace(reg_no, '[^0-9]', '', 'g'), '')::bigint, 0) ASC, id DESC LIMIT " . (int)$per_page);
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
}
