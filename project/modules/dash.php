<?php
// ماژول پیشخوان مدیریت و وضعیت لایسنس مرکز
if (!defined('ASEMAN_ROOT')) die('Access Denied');

    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    $lic = get_license();
    $tot_recs = (int)$db->query("SELECT COUNT(*) FROM records")->fetchColumn();
    $tot_items = (int)$db->query("SELECT COUNT(*) FROM items")->fetchColumn();
    $tot_mems = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $tot_locs = count(get_locations_map());

    $st_colors = array(
        'commercial'    => array('#ecfdf5', '#10b981', 'مجوز تجاری فعال'),
        'demo'          => array('#fffbeb', '#f59e0b', 'مجوز آزمایشی (دمو)'),
        'expired'       => array('#fff1f2', '#e11d48', 'مجوز منقضی‌شده'),
        'tampered'      => array('#fff1f2', '#e11d48', 'خطای امنیتی: دستکاری مجوز'),
        'host_mismatch' => array('#fff7ed', '#ea580c', 'عدم تطابق دامنه/هاست')
    );
    $sc = isset($st_colors[$lic['status']]) ? $st_colors[$lic['status']] : $st_colors['demo'];
    $bar_fn = function($cur, $max, $label) {
        $pct = ($max > 0) ? min(100, round(($cur / $max) * 100)) : 15;
        $col = ($max > 0 && $pct >= 90) ? 'var(--warn)' : (($max > 0 && $pct >= 75) ? '#f59e0b' : 'var(--blue)');
        $limit_txt = ($max > 0) ? tr_num($cur) . ' از ' . tr_num($max) . ' (' . tr_num($pct) . '٪)' : tr_num($cur) . ' (نامحدود)';
        return '<div style="background:#fff;padding:10px 14px;border-radius:14px;border:1px solid var(--line)">
            <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px"><b>' . $label . '</b><span>' . $limit_txt . '</span></div>
            <div style="height:8px;background:var(--sky);border-radius:99px;overflow:hidden"><div style="width:' . $pct . '%;height:100%;background:' . $col . '"></div></div>
        </div>';
    };

    $mod_labels = array(
        'mod_rec'       => 'منابع و میز امانت',
        'mod_form'      => 'ثبت و ویرایش منبع',
        'mod_org'       => 'چاپ برچسب عطف',
        'mod_locs'      => 'مدیریت کتابخانه',
        'mod_orders'    => 'سفارش کتاب',
        'mod_tickets'   => 'تیکت و پشتیبانی',
        'mod_imp_exp'   => 'تبادل فراداده (مارک/اکسل)',
        'mod_stats'     => 'آمار سامانه',
        'mod_authority' => 'مستندات فهرست‌نویسی',
        'mod_bulk'      => 'کنترل گروهی',
        'mod_backup'    => 'پشتیبان‌گیری',
        'mod_mem'       => 'مدیریت اعضا',
        'mod_loan'      => 'گردش امانت',
        'mod_rep'       => 'گزارش‌ها',
        'mod_cms'       => 'مدیریت پرتال',
        'mod_users'     => 'مدیریت کاربران',
        'mod_chat'      => 'چت‌بات هوشمند پرتال'
    );
    $mods_html = '';
    foreach ($mod_labels as $mk => $mlbl) {
        $on = !empty($lic[$mk]);
        $mods_html .= '<span class="badge" style="padding:5px 10px;background:' . ($on ? '#d1fae5;color:#065f46' : '#f1f5f9;color:#94a3b8;text-decoration:line-through') . '">' . ($on ? '' : '') . h($mlbl) . '</span> ';
    }

    echo '<div class="card" style="background:' . $sc[0] . ';border:2px solid ' . $sc[1] . ';padding:14px 18px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <span class="badge" style="background:' . $sc[1] . ';color:#fff;font-size:12.5px">' . $sc[2] . '</span>
                <b style="margin-right:8px;font-size:14px">مشتری: ' . h($lic['client_name']) . '</b>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <span class="badge">انقضای نرم‌افزار: ' . ($lic['exp_date'] !== '' ? jdate($lic['exp_date']) : 'دائمی') . '</span>
                <span class="badge">اعتبار پشتیبانی: ' . ($lic['support_exp_date'] !== '' ? jdate($lic['support_exp_date']) : 'ثبت‌نشده') . '</span>
                ' . (is_super_admin() ? '<a href="?p=settings" class="btn-sm pri">مدیریت مجوز و ارتقا</a>' : '') . '
            </div>
        </div>
    </div>';

    $w_rec = is_loc_restricted() ? "WHERE loc=" . $db->quote(user_loc()) : "";
    $w_loan = is_loc_restricted() ? "AND r.loc=" . $db->quote(user_loc()) : "";
    $n = $db->query("SELECT COUNT(DISTINCT rid),COUNT(*) FROM items $w_rec")->fetch(PDO::FETCH_NUM);
    $m = $db->query("SELECT COUNT(*) FROM members $w_rec")->fetchColumn();
    $o = $db->query("SELECT COUNT(*) FROM loans l JOIN v_items r ON r.id=l.rid WHERE l.ret IS NULL $w_loan")->fetchColumn();
    $late = $db->query("SELECT COUNT(*) FROM loans l JOIN v_items r ON r.id=l.rid WHERE l.ret IS NULL AND l.due < CURRENT_DATE::text $w_loan")->fetchColumn();
    
    $scope_title = is_loc_restricted() ? ' (کتابخانه: ' . user_loc() . ' — پیش‌شماره ثبت: ' . tr_num(get_loc_prefix(user_loc())) . ')' : ' (کل مجموعه کتابخانه)';
    echo "<h1>پیشخوان نرم‌افزار کتابخانه‌ای آسمان" . h($scope_title) . "</h1><div class='stats'>
        <div class='card'><b>" . tr_num($n[0]) . "</b>رکورد کتابشناختی</div>
        <div class='card'><b>" . tr_num($n[1]) . "</b>تعداد کل نسخه‌ها</div>
        <div class='card'><b>" . tr_num($m) . "</b>اعضای ثبت‌شده</div>
        <div class='card'><b>" . tr_num($o) . "</b>امانت‌های جاری</div>
        <div class='card'><b style='color:var(--warn)'>" . tr_num($late) . "</b>امانت‌های دارای دیرکرد</div>
    </div>";
    echo '<div class="grid"><div class="card"><h2>موجودی و پیش‌شماره ثبت مراکز</h2><table><tr><th>کتابخانه</th><th>پیش‌شماره ثبت</th><th>تعداد عنوان</th><th>مجموع نسخه‌ها</th></tr>';
    foreach ($db->query("SELECT loc, COUNT(DISTINCT rid) as c, COUNT(*) as s FROM items $w_rec GROUP BY loc") as $lr) {
        $l_name = $lr['loc'] ? $lr['loc'] : 'مخزن اصلی';
        echo '<tr><td><b>' . h($l_name) . '</b></td><td><span class="badge">' . tr_num(get_loc_prefix($l_name)) . '</span></td><td>' . tr_num($lr['c']) . '</td><td>' . tr_num($lr['s']) . '</td></tr>';
    }
    echo '</table></div><div class="card"><h2>آخرین منابع ثبت‌شده</h2><table><tr><th>شماره ثبت</th><th>عنوان</th><th>کتابخانه</th><th>تاریخ ثبت</th></tr>';
    foreach ($db->query("SELECT rid AS id, reg_no, f200a, loc, icreated AS created FROM v_items $w_rec ORDER BY v_items.id DESC LIMIT 6") as $r) {
        echo '<tr><td><b>' . tr_num(h($r['reg_no'])) . '</b></td><td><a href="?p=marc&id=' . $r['id'] . '">' . h($r['f200a']) . '</a></td><td><span class="badge">' . h($r['loc']) . '</span></td><td>' . jdate($r['created']) . '</td></tr>';
    }
    echo '</table></div></div>';
