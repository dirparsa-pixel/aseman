<?php
// پوسته گرافیکی تخصصی (الهام‌گرفته از کتابخانه تخصصی هنر اسلامی - itaihe.ac.ir)، هدر، فوتر و سایدبار حرفه‌ای بدون ایموجی
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ==================== آیکون‌های برداری (SVG) اختصاصی منوها و لوگو ====================
function aseman_svg_logo($size = 42) {
    $custom_logo = trim(get_cms('header_logo'));
    if ($custom_logo !== '') {
        return '<img src="' . h($custom_logo) . '" alt="Logo" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;object-fit:contain;border-radius:10px;background:#fff;padding:3px;border:1px solid rgba(197,160,89,.45)">';
    }
    return '<svg width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
        <polygon points="32,2 41,11 54,10 53,23 62,32 53,41 54,54 41,53 32,62 23,53 10,54 11,41 2,32 11,23 10,10 23,11" fill="url(#isl_g1)" stroke="#c5a059" stroke-width="2"/>
        <circle cx="32" cy="32" r="20" fill="#092c35" stroke="#e2c275" stroke-width="1.4"/>
        <path d="M20 39C20 35 26 33 32 36C38 33 44 35 44 39V25C44 21 38 19 32 22C26 19 20 21 20 25V39Z" fill="none" stroke="#f3dfb2" stroke-width="2" stroke-linejoin="round"/>
        <line x1="32" y1="22" x2="32" y2="36" stroke="#c5a059" stroke-width="1.8"/>
        <circle cx="32" cy="16" r="2" fill="#e2c275"/>
        <defs><linearGradient id="isl_g1" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse"><stop stop-color="#135d66"/><stop offset="1" stop-color="#07222b"/></linearGradient></defs>
    </svg>';
}

function menu_svg_icon($k) {
    $icons = array(
        'dash'      => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'rec'       => '<path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/>',
        'form'      => '<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>',
        'org'       => '<path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/>',
        'locs'      => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
        'orders'    => '<path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>',
        'tickets'   => '<path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>',
        'imp_exp'   => '<path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>',
        'backup'    => '<path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>',
        'mem'       => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'loan'      => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>',
        'rep'       => '<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>',
        'cms'       => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>',
        'users'     => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>',
        'stats'     => '<path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>',
        'authority' => '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>',
        'bulk'      => '<path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/>',
        'settings'  => '<path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>'
    );
    $path = isset($icons[$k]) ? $icons[$k] : '<circle cx="12" cy="12" r="5"/>';
    return '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;opacity:.9">' . $path . '</svg>';
}

// ==================== نمایش فرم پیش‌نمایش و تست رکوردهای ایزو ۲۷۰۹ قبل از ثبت ====================
function render_iso_preview_box($from_page) {
    global $F, $RTypes, $tok, $Locations;
    if (empty($_SESSION['iso_preview']) || !is_array($_SESSION['iso_preview'])) {
        if (isset($_GET['show_preview'])) {
            echo '<div class="card" style="border:2px solid var(--warn)"><p class="warn">هیچ رکورد معتبری در فایل یا جستجوی انتخاب‌شده یافت نشد. لطفاً ساختار فایل ورودی (اکسل / ایزو / XML) را بررسی نمایید.</p></div>';
        }
        return;
    }
    $recs = $_SESSION['iso_preview'];
    $loc = isset($_SESSION['iso_loc']) ? $_SESSION['iso_loc'] : $Locations[0];
    $ws_key = isset($_SESSION['iso_ws']) ? $_SESSION['iso_ws'] : 'book';
    $src_type = isset($_SESSION['iso_src_type']) ? $_SESSION['iso_src_type'] : 'ایزو / مارک / Z39.50';
    $allowed_locs = allowed_locations();
    $ws_fields = get_worksheet_active_fields($loc, $ws_key);
    $cfg_lib = get_lib_config($loc);
    $ws_title = isset($cfg_lib['worksheets'][$ws_key]['title']) ? $cfg_lib['worksheets'][$ws_key]['title'] : 'کاربرگه عمومی';

    echo '<div class="card" id="preview_box_container" style="border:2px solid var(--ok);background:linear-gradient(145deg,#ffffff,#ecfdf5)">
        <h2>
            <span>پیش‌نمایش، بررسی و تایید نهایی منابع ورودی (' . tr_num(count($recs)) . ' عنوان — منبع: ' . h($src_type) . ')</span>
            <span class="badge" style="background:var(--blue);color:#fff">کاربرگه: ' . h($ws_title) . '</span>
        </h2>
        <p style="font-size:13px;margin:0 0 12px">تمام فیلدها دقیقاً در جایگاه استاندارد خود قرار گرفتند. می‌توانید کتابخانه مقصد، تعداد مجلدات یا مشخصات هر رکورد را بررسی و ویرایش کرده و سپس روی دکمه <b>«تایید نهایی و ثبت در مخزن»</b> کلیک کنید:</p>
        <form method="post" action="?p=imp_iso_confirm" id="preview_confirm_form">
            <input type="hidden" name="t" value="' . $tok . '">
            <input type="hidden" name="from_page" value="' . h($from_page) . '">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;background:#fff;padding:12px 16px;border-radius:12px;border:1px solid var(--line);margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <label style="margin:0;color:var(--blue);font-size:13px">کتابخانه مقصد جهت ثبت:</label>
                    <select name="loc" style="width:auto;min-width:220px;padding:6px 12px;font-weight:700">';
                    foreach ($allowed_locs as $lc) {
                        echo '<option value="' . h($lc) . '"' . ($lc === $loc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ثبت ' . tr_num(get_loc_prefix($lc)) . ')</option>';
                    }
    echo '          </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="font-size:12.5px;font-weight:700;color:var(--navy2)">عملیات انتخاب رکوردها:</span>
                    <button type="button" class="btn-sm ok" onclick="asemanTogglePreviewChecks(\'all\')">✓ انتخاب همه</button>
                    <button type="button" class="btn-sm dng" onclick="asemanTogglePreviewChecks(\'none\')">✕ خالی کردن (لغو همه)</button>
                    <button type="button" class="btn-sm pri" onclick="asemanTogglePreviewChecks(\'invert\')">⇄ معکوس کردن انتخاب</button>
                    <span class="badge" id="preview_sel_counter" style="background:var(--sky);font-size:12px">' . tr_num(count($recs)) . ' مورد انتخاب شده</span>
                </div>
            </div>';

    foreach ($recs as $idx => $r) {
        $fid_t = find_title($r);
        $dup_badge = $fid_t ? '<span class="badge" style="background:#d1fae5;color:#065f46">این عنوان قبلاً فهرست شده؛ فقط موجودی محل شما افزوده می‌شود</span>' : '<span class="badge" style="background:#dbeafe;color:#1e40af">عنوان جدید</span>';
        $cur_rt = isset($r['rtype']) && isset($RTypes[$r['rtype']]) ? $r['rtype'] : 'book';
        $cur_reg = isset($r['reg_no']) ? $r['reg_no'] : '';
        echo '<div class="preview-rec-card" style="border:1px solid var(--line);border-radius:14px;padding:14px;margin-bottom:14px;background:rgba(255,255,255,.95)">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;border-bottom:1px dashed var(--line);padding-bottom:8px">
                <label style="font-size:14px;color:var(--blue);margin:0;cursor:pointer;display:flex;align-items:center;gap:6px">
                    <input type="checkbox" class="prev-item-chk" name="sel[' . $idx . ']" value="1" checked onchange="asemanUpdatePreviewCount()">
                    <b>رکورد شماره ' . tr_num($idx + 1) . ': ' . h($r['f200a']) . '</b>
                </label>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    ' . $dup_badge . '
                    <span style="font-size:12px">نوع منبع:</span>
                    <select name="recs[' . $idx . '][rtype]" style="width:auto;padding:4px 8px;font-size:12px">';
                    foreach ($RTypes as $rtk => $rtv) {
                        echo '<option value="' . h($rtk) . '"' . ($cur_rt === $rtk ? ' selected' : '') . '>' . h($rtv) . '</option>';
                    }
        echo '      </select>
                    <span style="font-size:12px">تعداد جلد:</span>
                    <input type="number" name="recs[' . $idx . '][copies]" value="' . (isset($r['copies']) && (int)$r['copies'] >= 1 ? (int)$r['copies'] : 1) . '" min="1" max="5000" style="width:70px;padding:4px 8px">
                    <span style="font-size:12px">شماره ثبت اول (اختیاری):</span>
                    <input name="recs[' . $idx . '][reg_no]" value="' . h($cur_reg) . '" placeholder="خودکار" style="width:105px;padding:4px 8px;font-size:12px">
                </div>
            </div>
            <div class="grid">';
            foreach ($F as $fk => $flabel) {
                $val = isset($r[$fk]) ? $r[$fk] : '';
                $disp_lbl = isset($ws_fields[$fk]) ? $ws_fields[$fk]['full'] : $flabel;
                $is_hidden_by_ws = !isset($ws_fields[$fk]) && $val === '';
                if ($is_hidden_by_ws) {
                    echo '<input type="hidden" name="recs[' . $idx . '][' . $fk . ']" value="">';
                } else {
                    echo '<div><label style="font-size:11.5px">' . h($disp_lbl) . '</label><input name="recs[' . $idx . '][' . $fk . ']" value="' . h($val) . '" style="padding:6px 10px;font-size:12.5px"></div>';
                }
            }
        echo '</div></div>';
    }
    echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px">
            <button class="btn ok" style="padding:10px 26px;font-size:14px">تایید نهایی و ثبت رکوردهای انتخاب‌شده در مخزن</button>
            <button type="button" class="btn-sm ok" onclick="asemanTogglePreviewChecks(\'all\')">✓ انتخاب همه</button>
            <button type="button" class="btn-sm dng" onclick="asemanTogglePreviewChecks(\'none\')">✕ خالی کردن</button>
            <button type="button" class="btn-sm pri" onclick="asemanTogglePreviewChecks(\'invert\')">⇄ برعکس کردن انتخاب</button>
            <a class="btn dng" href="?p=cancel_iso_preview&from=' . h($from_page) . '">انصراف و بستن پیش‌نمایش</a>
          </div>
        </form>
        <script>
        function asemanUpdatePreviewCount() {
            var chks = document.querySelectorAll("#preview_confirm_form .prev-item-chk");
            var c = 0;
            chks.forEach(function(el){ if (el.checked) c++; });
            var b = document.getElementById("preview_sel_counter");
            if (b) {
                var D = "۰۱۲۳۴۵۶۷۸۹";
                b.textContent = String(c).replace(/[0-9]/g, function(d){ return D[d]; }) + " مورد انتخاب شده";
            }
        }
        function asemanTogglePreviewChecks(mode) {
            var chks = document.querySelectorAll("#preview_confirm_form .prev-item-chk");
            chks.forEach(function(el){
                if (mode === "all") el.checked = true;
                else if (mode === "none") el.checked = false;
                else if (mode === "invert") el.checked = !el.checked;
            });
            asemanUpdatePreviewCount();
        }
        </script>
    </div>';
}

// ==================== پوسته گرافیکی دانشگاهی و تخصصی (الهام‌گرفته از itaihe.ac.ir) ====================

function resolve_portal_menu_href($m) {
    $tt = $m['target_type'] ?? 'url';
    $tv = trim($m['target_val'] ?? '');
    if ($tt === 'page') return '?p=page&slug=' . urlencode($tv);
    if ($tt === 'box')  return '?p=page&box_id=' . urlencode($tv);
    return $tv !== '' ? $tv : '?p=home';
}

function render_portal_dropdown_menu($pos = 'header') {
    global $db;
    try {
        $st = $db->prepare("SELECT * FROM portal_menus WHERE pos=? AND active=1 ORDER BY ord ASC, id ASC");
        $st->execute(array($pos));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rows = array();
    }
    if (!$rows) return '';
    $tree = array();
    foreach ($rows as $r) {
        $pid = (int)$r['parent_id'];
        $tree[$pid][] = $r;
    }
    if ($pos === 'header') {
        $out = '';
        if (!empty($tree[0])) {
            foreach ($tree[0] as $root) {
                $rid = (int)$root['id'];
                $href = h(resolve_portal_menu_href($root));
                $title = h($root['title']);
                if (!empty($tree[$rid])) {
                    $out .= '<div class="nav-dd"><a href="' . $href . '">' . $title . ' ▾</a><div class="nav-dd-menu">';
                    foreach ($tree[$rid] as $ch) {
                        $out .= '<a href="' . h(resolve_portal_menu_href($ch)) . '">' . h($ch['title']) . '</a>';
                    }
                    $out .= '</div></div>';
                } else {
                    $out .= '<a href="' . $href . '">' . $title . '</a>';
                }
            }
        }
        return $out;
    } else {
        $out = '<ul class="ftr-tree">';
        if (!empty($tree[0])) {
            foreach ($tree[0] as $root) {
                $rid = (int)$root['id'];
                $out .= '<li class="ftr-tree-item"><a href="' . h(resolve_portal_menu_href($root)) . '">▪ ' . h($root['title']) . '</a>';
                if (!empty($tree[$rid])) {
                    $out .= '<ul class="ftr-sub">';
                    foreach ($tree[$rid] as $ch) {
                        $out .= '<li><a href="' . h(resolve_portal_menu_href($ch)) . '">▫ ' . h($ch['title']) . '</a></li>';
                    }
                    $out .= '</ul>';
                }
                $out .= '</li>';
            }
        }
        $out .= '</ul>';
        return $out;
    }
}

function render_footer_column_source($src_key, $col_title_override = '') {
    global $db, $RTypes;
    $site_title = get_cms('site_title');
    if ($src_key === 'about') {
        $t = $col_title_override !== '' ? $col_title_override : $site_title;
        return '<h3>' . h($t) . '</h3><div style="line-height:1.9;margin:0;opacity:.92;text-align:justify">' . get_cms('about') . '</div>';
    }
    if ($src_key === 'footer_about') {
        $t = $col_title_override !== '' ? $col_title_override : $site_title;
        return '<h3>' . h($t) . '</h3><p style="line-height:1.9;margin:0;opacity:.92;text-align:justify">' . nl2br(h(get_cms('footer_about'))) . '</p>';
    }
    if ($src_key === 'contact') {
        $t = $col_title_override !== '' ? $col_title_override : (get_cms('footer_col3_title') ?: 'اطلاعات تماس');
        return '<h3>' . h($t) . '</h3><div style="line-height:1.95;opacity:.92">' . get_cms('contact') . '</div>';
    }
    if ($src_key === 'menu') {
        $t = $col_title_override !== '' ? $col_title_override : (get_cms('footer_col2_title') ?: 'دسترسی سریع');
        $dd = render_portal_dropdown_menu('footer');
        if ($dd !== '' && $dd !== '<ul class="ftr-tree"></ul>') {
            return '<h3>' . h($t) . '</h3>' . $dd;
        }
        $f_c2_l = array_filter(array_map('trim', explode("\n", get_cms('footer_col2_links'))));
        $h = '<h3>' . h($t) . '</h3><ul style="list-style:none;padding:0;margin:0;line-height:2.1">';
        foreach ($f_c2_l as $fl) {
            $fpts = explode('|', $fl);
            $ft = trim($fpts[0]);
            $fu = trim(isset($fpts[1]) ? $fpts[1] : '#');
            if ($ft !== '') $h .= '<li><a href="' . h($fu) . '">▪ ' . h($ft) . '</a></li>';
        }
        return $h . '</ul>';
    }
    if ($src_key === 'services') {
        $t = $col_title_override !== '' ? $col_title_override : 'خدمات کتابخانه';
        $h = '<h3>' . h($t) . '</h3><ul style="list-style:none;padding:0;margin:0;line-height:2">';
        foreach (array_filter(explode("\n", get_cms('services'))) as $srv) {
            $h .= '<li>▪ ' . h(trim($srv)) . '</li>';
        }
        return $h . '</ul>';
    }
    if ($src_key === 'links') {
        $t = $col_title_override !== '' ? $col_title_override : 'پیوندهای مفید';
        $h = '<h3>' . h($t) . '</h3><ul style="list-style:none;padding:0;margin:0;line-height:2">';
        foreach (array_filter(explode("\n", get_cms('links'))) as $lnk) {
            $pts = explode('|', $lnk);
            $tl = trim($pts[0]);
            $ul = trim($pts[1] ?? '#');
            if ($tl !== '') $h .= '<li><a href="' . h($ul) . '" target="_blank">▪ ' . h($tl) . '</a></li>';
        }
        return $h . '</ul>';
    }
    if ($src_key === 'stats') {
        $t = $col_title_override !== '' ? $col_title_override : 'آمار مجموعه';
        $n_rec = $db->query("SELECT (SELECT COUNT(*) FROM records),(SELECT COUNT(*) FROM items)")->fetch(PDO::FETCH_NUM);
        $n_mem = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        return '<h3>' . h($t) . '</h3><ul style="list-style:none;padding:0;margin:0;line-height:2">
            <li>کل عناوین منابع: <b>' . tr_num($n_rec[0]) . '</b></li>
            <li>مجموع نسخه‌ها: <b>' . tr_num($n_rec[1]) . '</b></li>
            <li>اعضای فعال: <b>' . tr_num($n_mem) . '</b></li>
        </ul>';
    }
    if (strpos($src_key, 'box_') === 0) {
        $bid = (int)substr($src_key, 4);
        $st = $db->prepare("SELECT * FROM portal_boxes WHERE id=?");
        $st->execute(array($bid));
        $bx = $st->fetch(PDO::FETCH_ASSOC);
        if ($bx) {
            $t = $col_title_override !== '' ? $col_title_override : $bx['title'];
            return '<h3>' . h($t) . '</h3><div style="line-height:1.9;opacity:.92">' . $bx['content'] . '</div>';
        }
    }
    if (strpos($src_key, 'page_') === 0) {
        $pid = (int)substr($src_key, 5);
        $st = $db->prepare("SELECT * FROM pages WHERE id=?");
        $st->execute(array($pid));
        $pg = $st->fetch(PDO::FETCH_ASSOC);
        if ($pg) {
            $t = $col_title_override !== '' ? $col_title_override : $pg['title'];
            return '<h3><a href="?p=page&slug=' . h($pg['slug']) . '">' . h($t) . '</a></h3><div style="line-height:1.9;opacity:.92">' . mb_substr(strip_tags($pg['body']), 0, 260, 'UTF-8') . '...</div>';
        }
    }
    return '';
}

function shell($title, $body, $p, $in) {
    global $AllPerms, $Themes, $db, $tok;
    $site_title   = get_cms('site_title');
    $site_sub     = get_cms('site_sub');
    $org_sub      = get_cms('header_org_sub');
    $hdr_phone    = get_cms('header_phone');
    $hdr_hours    = get_cms('header_hours');
    $sel_font     = get_cms('font');
    $sel_theme_key= get_cms('theme') ?: 'itaihe_art';
    $portal_style = get_cms('portal_style') ?: 'itaihe_art';

    $th = isset($Themes[$sel_theme_key]) ? $Themes[$sel_theme_key] : $Themes['itaihe_art'];
    if ($sel_theme_key === 'custom') {
        $ccj = json_decode(get_cms('theme_custom'), true);
        if (is_array($ccj)) foreach (array('navy', 'navy2', 'blue', 'sky', 'ice', 'line') as $ck) if (isset($ccj[$ck]) && preg_match('/^#[0-9a-f]{6}$/i', $ccj[$ck])) $th[$ck] = $ccj[$ck];
    }

    $font_urls = array(
        'Vazirmatn' => 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
        'Shabnam'   => 'https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/font-face.css',
        'Sahel'     => 'https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@v3.4.0/dist/font-face.css',
        'Samim'     => 'https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@v4.0.5/dist/font-face.css'
    );
    $font_link = isset($font_urls[$sel_font]) ? '<link href="' . $font_urls[$sel_font] . '" rel="stylesheet" type="text/css" />' : '';

    // تعیین استایل قالب پرتال (۱. هنر اسلامی itaihe_art، ۲. دانشگاهی کلاسیک، ۳. مدرن مینیمال)
    $card_radius = ($portal_style === 'classic_academic') ? '8px' : (($portal_style === 'modern_minimal') ? '20px' : '14px');
    $gold_accent = '#c5a059';

    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5"><title>' . h($title) . ' | ' . h($site_title) . '</title>' . $font_link . '<style>
:root{--navy:' . $th['navy'] . ';--navy2:' . $th['navy2'] . ';--blue:' . $th['blue'] . ';--sky:' . $th['sky'] . ';--ice:' . $th['ice'] . ';--line:' . $th['line'] . ';--gold:' . $gold_accent . ';--ok:#0d9488;--warn:#be123c}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0;width:100%;max-width:100vw;overflow-x:hidden;font-family:"VazirDigits","' . h($sel_font) . '",Vazirmatn,"IRANSans",Tahoma,sans-serif;color:var(--navy);text-align:right;background-color:var(--ice);background-image:radial-gradient(circle at 15% 15%, var(--sky) 0%, transparent 45%), radial-gradient(rgba(197,160,89,0.08) 1px, transparent 1px);background-size:100% 100%, 24px 24px;min-height:100vh;display:flex;flex-direction:column;-webkit-text-size-adjust:100%}

/* ==================== هدر حرفه‌ای دو لایه با الهام از کتابخانه تخصصی هنر اسلامی ==================== */
.top-utility-bar{background:#06232b;color:rgba(255,255,255,.88);font-size:12px;padding:7px 24px;border-bottom:1px solid rgba(197,160,89,.35);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.top-utility-bar .util-items{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.top-utility-bar .util-item{display:inline-flex;align-items:center;gap:6px}
.site-header{background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 100%);color:#fff;padding:16px 24px;border-bottom:3px solid var(--gold);box-shadow:0 10px 28px -10px rgba(7,25,61,.28);position:relative}
.site-header-inner{max-width:1360px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.brand-wrap{display:flex;align-items:center;gap:14px;text-decoration:none;color:#fff}
.brand-titles{display:flex;flex-direction:column;gap:3px}
.brand-org{font-size:11.5px;color:#e2c275;font-weight:600;letter-spacing:.2px}
.brand-main{font-size:19px;font-weight:800;color:#fff;line-height:1.3}
.header-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.header-nav a{color:rgba(255,255,255,.92);text-decoration:none;font-size:13px;font-weight:600;padding:7px 14px;border-radius:8px;transition:.2s;border:1px solid transparent}
.header-nav a:hover,.header-nav a.active{background:rgba(255,255,255,.12);border-color:rgba(197,160,89,.55);color:#fff}
.header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* ==================== چیدمان بدنه و منوی مدیریت ==================== */
.app-body{display:flex;flex:1;width:100%;max-width:100vw}
.sidebar{width:265px;flex-shrink:0;background:#fff;border-left:1px solid var(--line);padding:18px 12px;display:flex;flex-direction:column;gap:5px;box-shadow:4px 0 20px -10px rgba(0,0,0,.06)}
.sidebar-hdr{font-size:11.5px;font-weight:800;color:var(--gold);padding:4px 12px 8px;border-bottom:1px solid var(--line);margin-bottom:4px;letter-spacing:.3px}
.sidebar a{display:flex;align-items:center;justify-content:space-between;gap:8px;text-decoration:none;color:var(--navy);padding:9px 12px;border-radius:10px;font-size:13px;font-weight:600;transition:.18s;border:1px solid transparent;white-space:nowrap}
.sidebar a .m-lbl{display:inline-flex;align-items:center;gap:9px}
.sidebar a:hover{background:var(--sky);color:var(--navy2);border-color:var(--line)}
.sidebar a.on{color:#fff;background:linear-gradient(135deg,var(--navy),var(--blue));border-right:4px solid var(--gold);box-shadow:0 6px 14px -5px var(--navy2)}
main{flex:1;padding:24px 20px;max-width:1360px;margin:0 auto;width:100%;min-width:0}

/* ==================== دکمه‌ها و المان‌های فرم بدون ایموجی ==================== */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;font:inherit;font-size:13px;font-weight:700;cursor:pointer;color:var(--navy);padding:8px 18px;border-radius:8px;border:1px solid var(--line);background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.04);transition:.2s;white-space:nowrap}
.btn:hover{background:var(--sky);border-color:var(--blue);transform:translateY(-1px)}
.btn.pri{color:#fff;background:linear-gradient(135deg,var(--blue),var(--navy));border-color:var(--navy);box-shadow:0 4px 12px -3px rgba(13,122,138,.4)}
.btn.pri:hover{background:linear-gradient(135deg,var(--navy2),var(--navy))}
.btn.dng{color:var(--warn);background:#fff1f2;border-color:#fecdd3}
.btn.ok{color:#fff;background:linear-gradient(135deg,#0d9488,#0f766e);border-color:transparent}
.btn-sm{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font:inherit;font-size:11.5px;font-weight:700;cursor:pointer;padding:5px 11px;border-radius:6px;border:1px solid var(--line);background:#f8fafc;color:var(--navy2);white-space:nowrap;transition:.15s}
.btn-sm:hover{border-color:var(--blue);background:var(--sky)}
.btn-sm.pri{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-sm.dng{background:#fff1f2;color:var(--warn);border-color:#fecdd3}
.btn-sm.ok{background:#ecfdf5;color:#047857;border-color:#a7f3d0}

/* ==================== کارت‌ها و باکس‌های تخصصی (سبک پژوهشکده هنر) ==================== */
h1{font-size:19px;margin:0 0 18px;color:var(--navy);font-weight:800;line-height:1.4;padding-right:12px;border-right:4px solid var(--gold)}
h2{font-size:15px;margin:0 0 14px;color:var(--navy);border-bottom:2px solid var(--line);padding-bottom:9px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;position:relative}
h2::after{content:"";position:absolute;bottom:-2px;right:0;width:64px;height:2px;background:var(--gold)}
.card{width:100%;max-width:100%;background:#ffffff;border:1px solid var(--line);border-top:3px solid var(--navy2);border-radius:' . $card_radius . ';padding:22px;box-shadow:0 8px 24px -12px rgba(12,59,71,0.09);margin-bottom:20px;overflow:hidden}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px}
.stats .card{border-top:3px solid var(--gold);text-align:center;padding:18px 14px}
.stats b{display:block;font-size:25px;color:var(--blue);margin-bottom:4px;font-weight:800}
.tbl-scroll{width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:10px;border:1px solid var(--line)}
table{width:100%;border-collapse:separate;border-spacing:0;font-size:12.8px}
th{background:var(--navy);color:#fff;text-align:right;padding:11px 12px;border-bottom:2px solid var(--gold);cursor:pointer;white-space:nowrap;font-weight:700}
td{padding:9px 12px;border-bottom:1px solid var(--line);text-align:right;vertical-align:middle;background:#fff}
tr:nth-child(even) td{background:rgba(249,248,244,.65)}
tr:hover td{background:var(--sky)}
input,select,textarea{font:inherit;font-size:13.5px;width:100%;max-width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--navy);text-align:right;transition:.15s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px var(--sky)}
input[type=checkbox],input[type=radio]{width:auto}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(205px,1fr));gap:14px;width:100%}
.grid > *{min-width:0}
label{font-size:12.5px;display:block;margin-bottom:5px;font-weight:700;color:var(--navy2)}
.row{display:flex;gap:10px;align-items:end;flex-wrap:wrap;width:100%}
.tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:10px;width:100%}
.tabs a{text-decoration:none;padding:7px 16px;border-radius:8px;color:var(--navy2);background:#fff;border:1px solid var(--line);font-size:12.5px;font-weight:700;text-align:center;transition:.15s}
.tabs a:hover{border-color:var(--blue);background:var(--sky)}
.tabs a.on{background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;border-color:transparent;border-bottom:2px solid var(--gold)}
.badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;background:var(--sky);color:var(--navy);border:1px solid var(--line);font-weight:700;white-space:nowrap}
.warn{color:var(--warn);font-weight:700}
@font-face{font-family:"VazirDigits";src:url("fonts/Vazirmatn-FD-Regular.woff2") format("woff2");font-weight:400 600;unicode-range:U+0030-0039,U+0660-0669,U+06F0-06F9,U+066B-066C}
@font-face{font-family:"VazirDigits";src:url("fonts/Vazirmatn-FD-Bold.woff2") format("woff2");font-weight:700 900;unicode-range:U+0030-0039,U+0660-0669,U+06F0-06F9,U+066B-066C}
code,pre,kbd,samp,input,select,textarea,button,table,td,th,small,.badge{font-family:inherit}
.news-item{display:flex;gap:14px;align-items:center;padding:12px 4px;border-bottom:1px dashed var(--line);text-decoration:none;color:var(--navy);transition:.15s}
.news-item:hover{background:var(--ice);padding-right:8px}
.news-thumb{width:68px;height:68px;object-fit:cover;border-radius:10px;flex-shrink:0;background:var(--sky);border:1px solid var(--line)}
.news-thumb.ph{display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--navy2)}
.news-txt{display:flex;flex-direction:column;gap:4px;min-width:0}.news-txt small{opacity:.85;font-size:12px}.news-txt em{font-style:normal;align-self:flex-start}
.ok-msg{color:#065f46;background:#ecfdf5;border:1px solid #6ee7b7;border-right:4px solid #10b981;padding:12px 16px;border-radius:10px;margin-bottom:14px;font-weight:700}

/* ==================== بنر پرتال تخصصی و فوتر چندستونی قابل تنظیم ==================== */
.portal-hero{width:100%;background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,var(--blue) 100%);color:#fff;padding:34px 28px;border-radius:' . $card_radius . ';margin-bottom:22px;border-bottom:4px solid var(--gold);box-shadow:0 16px 36px -14px rgba(12,59,71,0.35);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;position:relative;overflow:hidden}
.portal-hero::before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(226,194,117,0.14) 1.5px, transparent 1.5px);background-size:22px 22px;pointer-events:none}
.portal-hero h1{font-size:24px!important;margin:0 0 8px;color:#fff;border:none;padding:0}
.portal-hero p{font-size:14px!important;opacity:0.92;margin:0;color:#f3dfb2}
.portal-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start;width:100%;max-width:100%}
.portal-layout > div{min-width:0;width:100%;max-width:100%}
.list-clean{list-style:none;padding:0;margin:0;width:100%}
.list-clean li{padding:10px 4px;border-bottom:1px dashed var(--line);font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.list-clean li:last-child{border-bottom:none}
.chart-wrap{display:flex;align-items:flex-end;justify-content:space-between;gap:6px;height:215px;padding:32px 4px 6px;border-bottom:2px solid var(--line);width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
.chart-col{flex:1;min-width:34px;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px;height:100%}
.chart-bar{width:100%;max-width:32px;background:linear-gradient(180deg,var(--blue),var(--navy));border-top:2px solid var(--gold);border-radius:6px 6px 0 0;min-height:6px}
.gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;width:100%}
.gal-item{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
.gal-item img{width:100%;height:95px;object-fit:cover;display:block}
.gal-item div{padding:6px;font-size:11px;font-weight:600;text-align:center}
.tbl-filter-bar{margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;width:100%}
.tbl-filter-bar input{max-width:260px;padding:6px 12px;font-size:12.5px;border-radius:8px}


.nav-dd{position:relative;display:inline-block}
.nav-dd-menu{display:none;position:absolute;top:100%;right:0;min-width:210px;background:var(--navy);border:1px solid var(--gold);border-radius:10px;padding:6px;box-shadow:0 12px 28px rgba(0,0,0,.35);z-index:999}
.nav-dd-menu a{display:block;padding:8px 12px;color:#fff;font-size:12.5px;border-radius:6px;white-space:nowrap}
.nav-dd-menu a:hover{background:rgba(255,255,255,.15);color:#e2c275}
.nav-dd:hover .nav-dd-menu{display:block}
.ftr-tree{list-style:none;padding:0;margin:0;line-height:2}
.ftr-sub{list-style:none;padding:2px 14px 4px 0;margin:2px 0 6px;border-right:2px solid rgba(226,194,117,.4);font-size:12px;opacity:.92}
.wide-slider{position:relative;width:100%;height:320px;border-radius:16px;overflow:hidden;margin-bottom:24px;border-bottom:4px solid var(--gold);box-shadow:0 16px 36px -14px rgba(12,59,71,0.35);background:var(--navy)}
.wide-slide{position:absolute;inset:0;opacity:0;transition:opacity .7s ease-in-out;display:flex;align-items:center;justify-content:space-between;padding:36px 48px;color:#fff;background-size:cover;background-position:center;pointer-events:none}
.wide-slide.active{opacity:1;pointer-events:auto}
.wide-slide-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(7,25,61,.85) 0%,rgba(19,93,102,.68) 60%,rgba(13,122,138,.55) 100%)}
.wide-slide-content{position:relative;z-index:2;max-width:720px}
.wide-slide-content h2{color:#fff;font-size:25px;margin:0 0 10px;border:none;padding:0}
.wide-slide-content h2::after{display:none}
.wide-slide-content p{color:#f3dfb2;font-size:14.5px;margin:0 0 18px;line-height:1.8}
.slider-nav-btn{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:40px;height:40px;border-radius:50%;border:1px solid rgba(226,194,117,.6);background:rgba(6,35,43,.65);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:.2s}
.slider-nav-btn:hover{background:var(--gold);color:var(--navy)}
.slider-dots{position:absolute;bottom:14px;left:50%;transform:translateX(-50%);z-index:5;display:flex;gap:8px}
.slider-dot{width:10px;height:10px;border-radius:99px;background:rgba(255,255,255,.45);border:none;cursor:pointer;padding:0;transition:.25s}
.slider-dot.active{width:26px;background:var(--gold)}

/* فوتر سازمانی */
.site-footer{background:linear-gradient(180deg,var(--navy) 0%,#051b22 100%);color:rgba(255,255,255,.88);border-top:4px solid var(--gold);margin-top:36px;padding:32px 24px 18px;font-size:13px}
.site-footer-inner{max-width:1360px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:26px;padding-bottom:22px;border-bottom:1px solid rgba(255,255,255,.14)}
.site-footer h3{color:#e2c275;font-size:14.5px;margin:0 0 12px;padding-bottom:7px;border-bottom:1px dashed rgba(226,194,117,.35)}
.site-footer a{color:rgba(255,255,255,.88);text-decoration:none;transition:.15s}
.site-footer a:hover{color:#e2c275;padding-right:4px}
.site-footer-bottom{max-width:1360px;margin:14px auto 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:rgba(255,255,255,.72)}

@media screen and (max-width: 900px) {
    main{padding:12px 10px;width:100%;max-width:100vw;overflow-x:hidden}
    .top-utility-bar,.site-header-inner{flex-direction:column;text-align:center;justify-content:center}
    .brand-wrap{flex-direction:column;text-align:center}
    .app-body{flex-direction:column;width:100%}
    .sidebar{width:100%;border-left:none;border-bottom:1px solid var(--line);flex-direction:row;overflow-x:auto;padding:8px 10px;gap:6px}
    .sidebar-hdr{display:none}
    .sidebar a{padding:7px 12px;font-size:12px;flex-shrink:0}
    .portal-layout{display:flex!important;flex-direction:column!important;width:100%!important;gap:14px}
    .portal-layout > div{width:100%!important;max-width:100%!important}
    .portal-hero{padding:22px 16px;text-align:center;justify-content:center}
    .portal-hero h1{font-size:19px!important}
    .tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px}
    .grid{grid-template-columns:1fr!important;gap:10px}
    .stats{grid-template-columns:1fr 1fr!important;gap:8px}
    .row{flex-direction:column!important;align-items:stretch!important;gap:10px}
    .row > div,.row > button,.row > a{width:100%!important}
}
@media print{header,.top-utility-bar,.site-header,.sidebar,.site-footer,.no-print,.tbl-filter-bar{display:none!important}main{max-width:100%;padding:0;margin:0}body{background:#fff}.card{box-shadow:none;border:none;padding:0;margin:0;background:transparent}}
</style></head><body>';

    // ۱. نوار ابزار بالایی (Utility Bar) و هدر حرفه‌ای قابل تنظیم (نمایش در پرتال و مدیریت)
    $ul_badge = $in ? (is_loc_restricted() ? 'کتابخانه: ' . user_loc() . ' (پیش‌شماره ' . tr_num(get_loc_prefix(user_loc())) . ')' : 'دسترسی کل کتابخانه ها') : '';
    echo '<div class="top-utility-bar no-print">
        <div class="util-items">
            <span class="util-item">امروز: <b>' . jdate('now') . '</b> | ساعت: <b id="live_clock">' . tr_num(date('H:i:s')) . '</b></span>';
            if ($hdr_hours !== '') echo '<span class="util-item">ساعات فعالیت: <b>' . h($hdr_hours) . '</b></span>';
            if ($hdr_phone !== '') echo '<span class="util-item">تلفن: <b>' . tr_num(h($hdr_phone)) . '</b></span>';
    echo '</div>
        <div class="util-items">';
            if ($in) {
                echo '<span class="badge" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.25)">' . h($ul_badge) . '</span>';
                echo '<span>کاربر: <b>' . h($_SESSION['u']) . '</b></span>';
            } elseif (isset($_SESSION['mid'])) {
                echo '<span>عضو: <b>' . h($_SESSION['mname'] ?? '') . '</b></span>';
            }
    echo '</div></div>';

    // ۲. هدر اصلی با لوگو، عنوان، زیرعنوان سازمانی و منوی ناوبری قابل تنظیم
    $nav_lines = array_filter(array_map('trim', explode("\n", get_cms('header_nav'))));
    echo '<header class="site-header no-print"><div class="site-header-inner">
        <a href="?p=home" class="brand-wrap">
            ' . aseman_svg_logo(46) . '
            <div class="brand-titles">
                ' . ($org_sub !== '' ? '<span class="brand-org">' . h($org_sub) . '</span>' : '') . '
                <span class="brand-main">' . h($site_title) . '</span>
            </div>
        </a>
        <nav class="header-nav">';
        $hdr_dd = render_portal_dropdown_menu('header');
        if ($hdr_dd !== '') {
            echo $hdr_dd;
        } else {
            foreach ($nav_lines as $nl) {
                $npts = explode('|', $nl);
                $nt = trim($npts[0]);
                $nu = trim(isset($npts[1]) ? $npts[1] : '?p=home');
                if ($nt !== '') echo '<a href="' . h($nu) . '">' . h($nt) . '</a>';
            }
        }
    echo '</nav>
        <div class="header-actions">';
        if (isset($_SESSION['mid'])) {
            echo '<a class="btn-sm ok" href="?p=my_panel">پنل کاربری من</a> <a class="btn-sm dng" href="?p=mem_logout">خروج</a>';
        } elseif ($in) {
            if ($p === 'home' || $p === 'news_list' || $p === 'res' || $p === 'page') echo '<a class="btn-sm ok" href="?p=dash">پیشخوان مدیریت</a>';
            else echo '<a class="btn-sm" href="?p=home">مشاهده پرتال عمومی</a>';
            echo '<a class="btn-sm dng" href="?p=logout">خروج</a>';
        } else {
            echo '<a class="btn-sm ok" href="?p=home&ltab=mem_in">ورود اعضا / ثبت‌نام</a>';
        }
    echo '</div></div></header>';

    // ۳. بدنه اصلی و منوی کناری با آیکون‌های برداری (بدون ایموجی)
    echo '<div class="app-body">';
    if ($in && !in_array($p, array('home', 'news_list', 'res', 'page'))) {
        $pend_orders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""))->fetchColumn();
        $open_tickets = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status='open'" . (is_super_admin() ? "" : " AND sender=" . $db->quote($_SESSION['u'])))->fetchColumn();
        echo '<aside class="sidebar no-print"><div class="sidebar-hdr">منوی مدیریت کتابخانه</div>';
        foreach ($AllPerms as $k => $v) {
            if (can($k)) {
                $cnt_b = '';
                if ($k === 'orders' && $pend_orders > 0) $cnt_b = '<span class="badge" style="background:var(--warn);color:#fff;border:none">' . tr_num($pend_orders) . '</span>';
                if ($k === 'tickets' && $open_tickets > 0) $cnt_b = '<span class="badge" style="background:var(--ok);color:#fff;border:none">' . tr_num($open_tickets) . '</span>';
                echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span class="m-lbl">' . menu_svg_icon($k) . '<span>' . h($v) . '</span></span>' . $cnt_b . '</a>';
            }
        }
        echo '</aside>';
    }

    echo '<main>' . $body . '</main></div>';

    // ۴. فوتر حرفه‌ای چندستونی قابل تنظیم از مدیریت پرتال (تعداد ستون، محتوای باکس‌ها و منوی آبشاری)
    $lic_f   = get_license();
    $f_cols  = max(1, min(4, (int)(get_cms('footer_cols') ?: 3)));
    $f_copy  = get_cms('footer_copyright');
    $col_sources = array(
        1 => array(get_cms('footer_col1_source') ?: 'footer_about', $site_title),
        2 => array(get_cms('footer_col2_source') ?: 'menu', get_cms('footer_col2_title') ?: 'دسترسی سریع'),
        3 => array(get_cms('footer_col3_source') ?: 'contact', get_cms('footer_col3_title') ?: 'اطلاعات تماس'),
        4 => array(get_cms('footer_col4_source') ?: 'stats', get_cms('footer_col4_title') ?: 'آمار و خدمات')
    );

    echo '<footer class="site-footer no-print">
        <div class="site-footer-inner" style="grid-template-columns:repeat(' . $f_cols . ',minmax(220px,1fr))">';
        for ($ci = 1; $ci <= $f_cols; $ci++) {
            echo '<div>' . render_footer_column_source($col_sources[$ci][0], $col_sources[$ci][1]) . '</div>';
        }
    echo '</div>
        <div class="site-footer-bottom">
            <div>' . h($f_copy) . '</div>
            <div>' . ((!empty($lic_f['show_footer']) && !empty($lic_f['vendor_name'])) ? 'طراحی و توسعه: <a href="' . h($lic_f['vendor_url']) . '" target="_blank" style="color:#e2c275;font-weight:700">' . h($lic_f['vendor_name']) . '</a> | ' : '') . 'کد نصب مرکز: <span class="no-num">' . h(install_fingerprint('strict')) . '</span></div>
        </div>
    </footer>';

    echo '<script>
(function(){var D="۰۱۲۳۴۵۶۷۸۹";function cv(n){var w=document.createTreeWalker(n,NodeFilter.SHOW_TEXT,{acceptNode:function(t){var p=t.parentNode;if(!p||/^(SCRIPT|STYLE|TEXTAREA|OPTION|CODE)$/.test(p.nodeName)||(p.closest&&p.closest("[contenteditable],.rt-wrap,.no-num")))return NodeFilter.FILTER_REJECT;return /[0-9]/.test(t.nodeValue)?NodeFilter.FILTER_ACCEPT:NodeFilter.FILTER_REJECT;}});var a=[],x;while(x=w.nextNode())a.push(x);a.forEach(function(t){t.nodeValue=t.nodeValue.replace(/[0-9]/g,function(d){return D[d];});});}
document.addEventListener("DOMContentLoaded",function(){cv(document.body);});})();
</script>';
    if ($in) {
        echo '<div id="idle-bar" style="display:none;position:fixed;bottom:16px;right:16px;z-index:9998;background:#fff7ed;border:2px solid #f59e0b;color:#7c2d12;border-radius:10px;padding:12px 16px;box-shadow:0 10px 30px rgba(0,0,0,.2);font-size:13px">نشست شما تا <b id="idle-sec">۶۰</b> ثانیهٔ دیگر به‌دلیل عدم فعالیت پایان می‌یابد. <button type="button" class="btn-sm pri" id="idle-keep">ادامه می‌دهم</button></div>
<script>(function(){var L=600,left=L,last=0,bar=document.getElementById("idle-bar"),sec=document.getElementById("idle-sec"),D="۰۱۲۳۴۵۶۷۸۹";
function fa(n){return String(n).replace(/[0-9]/g,function(d){return D[d];});}
function ping(){if(Date.now()-last<20000)return;last=Date.now();left=L;bar.style.display="none";fetch("?p=ping",{credentials:"same-origin"}).catch(function(){});}
["keydown","click","scroll","touchstart","mousemove"].forEach(function(ev){document.addEventListener(ev,function(){if(left>90||ev!=="mousemove"){var now=Date.now();if(now-last>30000){ping();}else{left=L;}}},{passive:true});});
document.getElementById("idle-keep").addEventListener("click",function(){last=0;ping();});
setInterval(function(){left--;if(left<=60){bar.style.display="block";sec.textContent=fa(Math.max(left,0));}if(left<=0){location.href="?p=home&timeout=1";}},1000);})();</script>';
    }
    $lic_cw = get_license();
    if ($p === 'home' && !empty($lic_cw['mod_chat']) && get_cms('chat_mode') !== '' && get_cms('chat_mode') !== 'off') {
        $cw = chat_cfg('chat_welcome', 'سلام! نام کتاب، نویسنده یا موضوع مورد نظرتان را بنویسید تا در فهرست کتابخانه جستجو کنم.');
        echo '<style>
#cw-btn{position:fixed;bottom:18px;left:18px;z-index:9990;border:1px solid var(--gold);border-radius:10px;padding:11px 18px;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;font:inherit;font-weight:700;cursor:pointer;box-shadow:0 10px 26px rgba(0,0,0,.28)}
#cw-box{position:fixed;bottom:74px;left:18px;z-index:9991;width:min(380px,calc(100vw - 28px));height:min(520px,calc(100vh - 110px));background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.3);display:none;flex-direction:column;overflow:hidden;color:var(--navy)}
#cw-box.on{display:flex}#cw-h{padding:12px 14px;background:linear-gradient(135deg,var(--navy),var(--navy2));color:#fff;font-weight:700;display:flex;justify-content:space-between;border-bottom:2px solid var(--gold)}
#cw-m{flex:1;overflow:auto;padding:12px;background:var(--ice)}.cw-b{max-width:88%;padding:8px 12px;border-radius:10px;margin-bottom:8px;font-size:13px;line-height:1.8;white-space:pre-wrap;word-break:break-word}
.cw-u{background:var(--blue);color:#fff;margin-right:auto;border-bottom-left-radius:3px}.cw-a{background:#fff;border:1px solid var(--line);border-bottom-right-radius:3px}
.cw-it{display:block;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px 10px;margin:6px 0;text-decoration:none;color:var(--navy);font-size:12.5px}.cw-it b{display:block}
#cw-f{display:flex;gap:6px;padding:8px;border-top:1px solid var(--line);background:#fff}#cw-f input{flex:1}#cw-f button{border:0;border-radius:8px;padding:0 14px;background:var(--blue);color:#fff;font:inherit;cursor:pointer}
</style><button id="cw-btn" type="button">گفتگو با دستیار منابع</button><div id="cw-box"><div id="cw-h"><span>دستیار هوشمند منابع کتابخانه</span><span id="cw-x" style="cursor:pointer">بستن</span></div><div id="cw-m"></div><form id="cw-f"><input id="cw-q" maxlength="300" autocomplete="off" placeholder="مثلاً: کتاب دربارهٔ معماری اسلامی"><button>ارسال</button></form></div>
<script>(function(){var T=' . json_encode($tok) . ',W=' . json_encode($cw, JSON_UNESCAPED_UNICODE) . ',m=document.getElementById("cw-m"),box=document.getElementById("cw-box"),hist=[],D="۰۱۲۳۴۵۶۷۸۹";
function fa(s){return String(s).replace(/[0-9]/g,function(d){return D[d];});}
function add(t,c){var d=document.createElement("div");d.className="cw-b "+c;d.textContent=t;m.appendChild(d);m.scrollTop=m.scrollHeight;return d;}
document.getElementById("cw-btn").onclick=function(){box.classList.toggle("on");if(!m.children.length)add(W,"cw-a");};document.getElementById("cw-x").onclick=function(){box.classList.remove("on");};
document.getElementById("cw-f").onsubmit=function(e){e.preventDefault();var qi=document.getElementById("cw-q"),q=qi.value.trim();if(!q)return;qi.value="";add(q,"cw-u");var w=add("…","cw-a");var fd=new FormData();fd.append("t",T);fd.append("q",q);fd.append("h",JSON.stringify(hist.slice(-6)));
fetch("?p=chat_api",{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){w.textContent=j.text||"";hist.push({r:"u",t:q},{r:"a",t:j.text||""});(j.items||[]).forEach(function(it){var a=document.createElement("a");a.className="cw-it";a.href=it.url;a.target="_blank";var b=document.createElement("b");b.textContent=it.title;a.appendChild(b);var s=document.createElement("span");s.textContent=[it.author,it.pub,it.type].filter(Boolean).join(" — ");a.appendChild(s);var l=document.createElement("div");l.textContent=it.locs.length?it.locs.map(function(x){return x.loc+": "+(x.av>0?fa(x.av)+" موجود":"امانت");}).join(" | "):"بدون موجودی";l.style.opacity=".85";a.appendChild(l);m.appendChild(a);});m.scrollTop=m.scrollHeight;}).catch(function(){w.textContent="خطا در ارتباط؛ دوباره تلاش کنید.";});};})();</script>';
    }
    if ($in && $p === 'form') {
        echo '<script>(function(){var els=document.querySelectorAll("input[data-ac]");if(!els.length)return;var box=document.createElement("div");box.style.cssText="position:absolute;z-index:9999;background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.2);max-height:300px;overflow:auto;display:none";document.body.appendChild(box);var cur=null,tm=null;
function hide(){box.style.display="none";}
var cl={auth:["مستند","#d1fae5","#065f46"],variant:["ارجاع","#fef3c7","#92400e"],rec:["در رکوردها","#e5e7eb","#374151"]};
els.forEach(function(el){el.addEventListener("input",function(){cur=el;clearTimeout(tm);tm=setTimeout(function(){var q=el.value;if(q.trim().length<2){hide();return;}fetch("?p=ac&f="+encodeURIComponent(el.getAttribute("data-ac"))+"&q="+encodeURIComponent(q),{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(a){box.innerHTML="";if(!a||!a.length){hide();return;}a.forEach(function(it){var d=document.createElement("div");d.style.cssText="padding:7px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px";var b=document.createElement("span");var c=cl[it.kind]||cl.rec;b.textContent=c[0];b.style.cssText="background:"+c[1]+";color:"+c[2]+";border-radius:6px;padding:1px 8px;font-size:11px;margin-left:8px";var t=document.createElement("span");t.textContent=it.label;d.appendChild(b);d.appendChild(t);if(it.note){var n=document.createElement("div");n.textContent=it.note;n.style.cssText="font-size:11px;opacity:.75";d.appendChild(n);}d.addEventListener("mousedown",function(e){e.preventDefault();cur.value=it.value;hide();cur.focus();});d.addEventListener("mouseenter",function(){d.style.background="#eff6ff";});d.addEventListener("mouseleave",function(){d.style.background="";});box.appendChild(d);});var r=el.getBoundingClientRect();box.style.top=(window.scrollY+r.bottom+2)+"px";box.style.left=(window.scrollX+r.left)+"px";box.style.width=Math.max(r.width,280)+"px";box.style.display="block";}).catch(hide);},200);});el.addEventListener("blur",function(){setTimeout(hide,150);});el.addEventListener("keydown",function(e){if(e.key==="Escape")hide();});});})();</script>';
    }

    echo <<<'ASEMAN_TABLE_JS'
<script>
document.addEventListener("DOMContentLoaded", function() {
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

    var tables = document.querySelectorAll("table:not(.marc)");
    tables.forEach(function(tbl) {
        if (!tbl.parentElement.classList.contains("tbl-scroll")) {
            var wrap = document.createElement("div");
            wrap.className = "tbl-scroll";
            tbl.parentNode.insertBefore(wrap, tbl);
            wrap.appendChild(tbl);
        }
        var trs = Array.from(tbl.querySelectorAll("tr"));
        if (trs.length < 2) return;
        var headerRow = trs[0];
        var ths = headerRow.querySelectorAll("th");
        if (ths.length === 0) return;

        var chkAll = headerRow.querySelector('input[type="checkbox"]');
        if (chkAll) {
            chkAll.addEventListener('change', function() {
                var rowChks = tbl.querySelectorAll('tr:not([style*="display: none"]) td input[type="checkbox"]');
                rowChks.forEach(function(c) { c.checked = chkAll.checked; });
            });
            var selTools = document.createElement("div");
            selTools.className = "no-print";
            selTools.style.cssText = "display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px";
            selTools.innerHTML = '<span style="font-size:12px;font-weight:700;color:var(--navy2)">انتخاب گروهی لیست:</span>'
                + '<button type="button" class="btn-sm ok" data-sel-act="all">✓ انتخاب همه</button>'
                + '<button type="button" class="btn-sm dng" data-sel-act="none">✕ خالی کردن</button>'
                + '<button type="button" class="btn-sm pri" data-sel-act="invert">⇄ برعکس</button>';
            selTools.querySelectorAll("button[data-sel-act]").forEach(function(b) {
                b.addEventListener("click", function() {
                    var act = b.getAttribute("data-sel-act");
                    var rowChks = tbl.querySelectorAll('tr:not([style*="display: none"]) td input[type="checkbox"]');
                    rowChks.forEach(function(c) {
                        if (act === "all") c.checked = true;
                        else if (act === "none") c.checked = false;
                        else if (act === "invert") c.checked = !c.checked;
                    });
                    if (chkAll) chkAll.checked = (act === "all");
                });
            });
            tbl.parentElement.parentNode.insertBefore(selTools, tbl.parentElement);
        }

        if (trs.length > 2) {
            var allRows = trs.slice(1);
            var bar = document.createElement("div");
            bar.className = "tbl-filter-bar no-print";

            var leftGroup = document.createElement("div");
            leftGroup.style.display = "flex";
            leftGroup.style.gap = "8px";
            leftGroup.style.alignItems = "center";

            var inp = document.createElement("input");
            inp.type = "text";
            inp.placeholder = "جستجو و فیلتر سریع در جدول...";

            var pgSelect = document.createElement("select");
            pgSelect.style.width = "auto";
            pgSelect.style.borderRadius = "8px";
            pgSelect.style.padding = "4px 10px";
            pgSelect.innerHTML = '<option value="0">نمایش همه</option><option value="50">۵۰ ردیف</option><option value="100">۱۰۰ ردیف</option><option value="200">۲۰۰ ردیف</option><option value="500">۵۰۰ ردیف</option>';
            pgSelect.value = "100";

            var hint = document.createElement("small");
            hint.style.color = "var(--navy2)";
            hint.innerHTML = "برای مرتب‌سازی روی سرستون کلیک کنید";

            leftGroup.appendChild(inp);
            leftGroup.appendChild(pgSelect);

            var pgControls = document.createElement("div");
            pgControls.className = "pg-ctrl";
            pgControls.style.display = "flex";
            pgControls.style.gap = "4px";

            bar.appendChild(leftGroup);
            bar.appendChild(pgControls);
            bar.appendChild(hint);
            tbl.parentElement.parentNode.insertBefore(bar, tbl.parentElement);

            var curPage = 1;
            function renderTable() {
                var val = inp.value.trim().toLowerCase();
                var pSize = parseInt(pgSelect.value, 10);
                var filteredRows = allRows.filter(function(r) {
                    return r.innerText.toLowerCase().indexOf(val) > -1;
                });
                var tPages = pSize === 0 ? 1 : Math.ceil(filteredRows.length / pSize);
                if (curPage > tPages) curPage = tPages || 1;

                allRows.forEach(function(r) { r.style.display = 'none'; });

                var start = pSize === 0 ? 0 : (curPage - 1) * pSize;
                var end = pSize === 0 ? filteredRows.length : start + pSize;
                filteredRows.slice(start, end).forEach(function(r) { r.style.display = ''; });

                pgControls.innerHTML = '';
                if (tPages > 1) {
                    for (var i = 1; i <= tPages; i++) {
                        var btn = document.createElement("button");
                        btn.type = "button";
                        btn.className = "btn-sm" + (i === curPage ? " pri" : "");
                        btn.innerText = i;
                        (function(pg){ btn.addEventListener('click', function(){ curPage = pg; renderTable(); }); })(i);
                        pgControls.appendChild(btn);
                    }
                }
            }

            inp.addEventListener("input", function() { curPage = 1; renderTable(); });
            pgSelect.addEventListener("change", function() { curPage = 1; renderTable(); });
            renderTable();
        }

        ths.forEach(function(th, colIdx) {
            if (th.innerText.trim() === "" || th.querySelector('input[type="checkbox"]')) return;
            th.title = "کلیک جهت مرتب‌سازی بر اساس این ستون";
            var icon = document.createElement("span");
            icon.style.fontSize = "10px";
            icon.style.marginRight = "4px";
            icon.style.opacity = "0.65";
            icon.innerText = "⇅";
            th.appendChild(icon);

            var asc = true;
            th.addEventListener("click", function() {
                if (window.getSelection().toString()) return;
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
                var pgSel = tbl.parentElement.parentNode.querySelector('select');
                if (pgSel) {
                   var ev = new Event('change');
                   pgSel.dispatchEvent(ev);
                }
            });
        });
    });
});
</script></body></html>
ASEMAN_TABLE_JS;
}

function post_btn($act, $label, $fields, $cls = 'btn') {
    global $tok;
    $o = '<form method="post" action="?p=' . $act . '" style="display:inline" onsubmit="return confirm(\'آیا از انجام این عملیات مطمئن هستید؟\')">';
    foreach ($fields as $k => $v) $o .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $o . '<input type="hidden" name="t" value="' . $tok . '"><button class="' . $cls . '">' . $label . '</button></form>';
}
