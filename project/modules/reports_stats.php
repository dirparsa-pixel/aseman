<?php
// ماژول گزارش‌های جامع کتابخانه (rep) و آمار تحلیلی سامانه و کاربران آنلاین (stats)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

if ($p === 'rep') {
    $type = isset($_GET['type']) ? $_GET['type'] : 'overdue';
    $w_r = is_loc_restricted() ? " AND r.loc=" . $db->quote(user_loc()) : "";
    $w_rec_only = is_loc_restricted() ? " WHERE loc=" . $db->quote(user_loc()) : "";

    echo '<h1>گزارش‌های جامع کتابخانه آسمان' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1>';
    echo '<div class="tabs no-print">
        <a class="' . ($type === 'overdue' ? 'on' : '') . '" href="?p=rep&type=overdue">امانت‌های دارای دیرکرد</a>
        <a class="' . ($type === 'top_books' ? 'on' : '') . '" href="?p=rep&type=top_books">پرامانت‌ترین منابع</a>
        <a class="' . ($type === 'top_mems' ? 'on' : '') . '" href="?p=rep&type=top_mems">فعال‌ترین اعضا</a>
        <a class="' . ($type === 'by_loc' ? 'on' : '') . '" href="?p=rep&type=by_loc">موجودی به تفکیک کتابخانه</a>
        <a class="' . ($type === 'loc_items' ? 'on' : '') . '" href="?p=rep&type=loc_items">منابع موجود یک محل (جدولی)</a>
        <a class="' . ($type === 'by_type' ? 'on' : '') . '" href="?p=rep&type=by_type">آمار تفکیکی انواع منابع</a>
        <a class="' . ($type === 'history' ? 'on' : '') . '" href="?p=rep&type=history">تاریخچه کل گردش امانات</a>
        <a class="btn ok" href="?p=export_excel_rec">خروجی اکسل منابع</a>
        <button class="btn pri" onclick="window.print()">چاپ گزارش</button>
    </div><div class="card">';

    if ($type === 'overdue') {
        echo '<h2>گزارش امانت‌های دارای تأخیر (تا تاریخ ' . jdate('now') . ')</h2><table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>محل</th><th>نام عضو</th><th>تلفن عضو</th><th>تاریخ امانت</th><th>موعد بازگشت</th></tr>';
        foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name, m.phone FROM loans l JOIN v_items r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE l.ret IS NULL AND l.due < CURRENT_DATE::text $w_r ORDER BY l.due") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td>' . h($x['loc']) . '</td><td>' . h($x['name']) . '</td><td>' . tr_num(h($x['phone'])) . '</td><td>' . jdate($x['d_out']) . '</td><td class="warn">' . jdate($x['due']) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'top_books') {
        echo '<h2>گزارش پرامانت‌ترین منابع کتابخانه</h2><table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>پدیدآور</th><th>کتابخانه</th><th>تعداد دفعات امانت</th></tr>';
        foreach ($db->query("SELECT MIN(r.reg_no) AS reg_no, MAX(r.f200a) AS f200a, MAX(r.f700) AS f700, r.loc, COUNT(l.id) as cnt FROM v_items r JOIN loans l ON r.id=l.rid WHERE 1=1 $w_r GROUP BY r.rid, r.loc ORDER BY cnt DESC LIMIT 50") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td>' . h($x['f700']) . '</td><td>' . h($x['loc']) . '</td><td><b>' . tr_num($x['cnt']) . '</b> مرتبه</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'top_mems') {
        echo '<h2>گزارش فعال‌ترین اعضای کتابخانه</h2><table><tr><th>نام عضو</th><th>کد عضویت</th><th>محل</th><th>تلفن</th><th>تعداد امانت</th></tr>';
        foreach ($db->query("SELECT m.name, m.mcode, m.loc, m.phone, COUNT(l.id) as cnt FROM members m JOIN loans l ON m.id=l.mid JOIN v_items r ON r.id=l.rid WHERE 1=1 $w_r GROUP BY m.id ORDER BY cnt DESC LIMIT 50") as $x) {
            echo '<tr><td><b>' . h($x['name']) . '</b></td><td>' . tr_num(h($x['mcode'])) . '</td><td>' . h($x['loc']) . '</td><td>' . tr_num(h($x['phone'])) . '</td><td><b>' . tr_num($x['cnt']) . '</b> کتاب</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'by_loc') {
        echo '<h2>گزارش آماری موجودی بر اساس کتابخانه</h2><table><tr><th>کتابخانه</th><th>پیش‌شماره ثبت</th><th>تعداد عناوین</th><th>مجموع نسخه‌ها</th></tr>';
        foreach ($db->query("SELECT loc, COUNT(DISTINCT rid) as c1, COUNT(*) as c2 FROM items $w_rec_only GROUP BY loc") as $x) {
            $ln = $x['loc'] ? $x['loc'] : 'مخزن اصلی';
            echo '<tr><td><b>' . h($ln) . '</b></td><td><span class="badge">' . tr_num(get_loc_prefix($ln)) . '</span></td><td>' . tr_num($x['c1']) . '</td><td>' . tr_num($x['c2']) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'loc_items') {
        $allowed_locs = allowed_locations();
        $rl = is_loc_restricted() ? user_loc() : trim(isset($_GET['rloc']) ? $_GET['rloc'] : $allowed_locs[0]);
        if (!in_array($rl, $Locations)) $rl = $allowed_locs[0];
        $only = isset($_GET['only']) && $_GET['only'] === 'all' ? 'all' : 'avail';
        $pg = max(1, (int)(isset($_GET['pg']) ? $_GET['pg'] : 1));
        $per = 200;
        $w_av = $only === 'avail' ? " AND NOT EXISTS (SELECT 1 FROM loans l WHERE l.rid=i.id AND l.ret IS NULL)" : "";
        echo '<h2>گزارش جدولی منابع ' . ($only === 'avail' ? 'موجود' : '(موجود و امانت‌رفته)') . ' کتابخانه «' . h($rl) . '»</h2>
            <form method="get" class="row no-print"><input type="hidden" name="p" value="rep"><input type="hidden" name="type" value="loc_items">
                ' . (is_loc_restricted() ? '' : '<div style="width:220px"><label>کتابخانه</label><select name="rloc">' . implode('', array_map(function ($lc) use ($rl) { return '<option value="' . h($lc) . '"' . ($lc === $rl ? ' selected' : '') . '>' . h($lc) . '</option>'; }, $allowed_locs)) . '</select></div>') . '
                <div style="width:240px"><label>نمایش</label><select name="only"><option value="avail"' . ($only === 'avail' ? ' selected' : '') . '>فقط منابع موجود (روی قفسه)</option><option value="all"' . ($only === 'all' ? ' selected' : '') . '>همهٔ مجلدات (با وضعیت امانت)</option></select></div>
                <button class="btn pri">نمایش گزارش</button><a class="btn ok" href="?p=export_loc_items&rloc=' . urlencode($rl) . '&only=' . $only . '">اکسل این گزارش</a></form>';
        $tt = $db->prepare("SELECT COUNT(*), SUM(CASE WHEN EXISTS (SELECT 1 FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) THEN 1 ELSE 0 END) FROM items i WHERE i.loc=?");
        $tt->execute(array($rl));
        $tr = $tt->fetch(PDO::FETCH_NUM);
        echo '<p>مجموع مجلدات این کتابخانه: <b>' . tr_num($tr[0]) . '</b> | در امانت: <b>' . tr_num((int)$tr[1]) . '</b> | موجود روی قفسه: <b>' . tr_num((int)$tr[0] - (int)$tr[1]) . '</b></p>';
        $cs = $db->prepare("SELECT COUNT(*) FROM items i WHERE i.loc=? $w_av");
        $cs->execute(array($rl));
        $tot_rows = (int)$cs->fetchColumn();
        $st = $db->prepare("SELECT i.reg_no, i.vol, r.f200a, r.f700, r.f200f, r.f210c, r.f210d, r.f676, r.f686, (SELECT rec_no FROM loc_rec lr WHERE lr.rid=i.rid AND lr.loc=i.loc) AS rn, (SELECT COUNT(*) FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) AS onloan FROM items i JOIN records r ON r.id=i.rid WHERE i.loc=? $w_av ORDER BY COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0) LIMIT $per OFFSET " . (($pg - 1) * $per));
        $st->execute(array($rl));
        echo '<table><tr><th>ردیف</th><th>شماره ثبت</th><th>سریال رکورد</th><th>عنوان</th><th>جلد</th><th>پدیدآور</th><th>ناشر</th><th>سال</th><th>رده</th><th>وضعیت</th></tr>';
        $n = ($pg - 1) * $per;
        foreach ($st as $x) {
            $n++;
            echo '<tr><td>' . tr_num($n) . '</td><td><b>' . tr_num(h($x['reg_no'])) . '</b></td><td>' . tr_num($x['rn']) . '</td><td><b>' . h($x['f200a']) . '</b></td><td>' . tr_num(h($x['vol'])) . '</td><td>' . h($x['f700'] ? $x['f700'] : $x['f200f']) . '</td><td>' . h($x['f210c']) . '</td><td>' . tr_num(h($x['f210d'])) . '</td><td>' . h($x['f676'] ? $x['f676'] : $x['f686']) . '</td><td>' . ($x['onloan'] ? '<span class="warn">امانت</span>' : '<span style="color:var(--ok);font-weight:700">موجود</span>') . '</td></tr>';
        }
        echo '</table><p class="no-print">نمایش ' . tr_num(min($tot_rows, $n)) . ' از ' . tr_num($tot_rows) . ' ردیف</p><div class="no-print">';
        $last_pg = max(1, (int)ceil($tot_rows / $per));
        for ($i = max(1, $pg - 6); $i <= min($last_pg, $pg + 6); $i++) {
            echo '<a class="btn-sm' . ($i == $pg ? ' pri' : '') . '" href="?p=rep&type=loc_items&rloc=' . urlencode($rl) . '&only=' . $only . '&pg=' . $i . '">' . tr_num($i) . '</a> ';
        }
        echo '</div>';
    } elseif ($type === 'by_type') {
        echo '<h2>گزارش آماری موجودی بر اساس نوع منبع</h2><table><tr><th>نوع منبع</th><th>تعداد عناوین</th><th>مجموع نسخه‌ها</th></tr>';
        foreach ($RTypes as $k => $v) {
            $s = $db->prepare("SELECT COUNT(DISTINCT rid), COUNT(*) FROM v_items WHERE rtype=?" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""));
            $s->execute(array($k));
            $row_t = $s->fetch(PDO::FETCH_NUM);
            echo '<tr><td><b>' . $v . '</b></td><td>' . tr_num($row_t[0]) . '</td><td>' . tr_num($row_t[1]) . '</td></tr>';
        }
        echo '</table>';
    } elseif ($type === 'history') {
        echo '<h2>تاریخچه کامل گردش امانت‌ها</h2><table><tr><th>شماره ثبت</th><th>منبع</th><th>محل</th><th>عضو</th><th>تاریخ تحویل</th><th>موعد</th><th>تاریخ بازگشت</th></tr>';
        foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name FROM loans l JOIN v_items r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE 1=1 $w_r ORDER BY l.id DESC LIMIT 200") as $x) {
            echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td>' . h($x['f200a']) . '</td><td>' . h($x['loc']) . '</td><td>' . h($x['name']) . '</td><td>' . jdate($x['d_out']) . '</td><td>' . jdate($x['due']) . '</td><td>' . ($x['ret'] ? jdate($x['ret']) : '<span class="warn">نزد عضو</span>') . '</td></tr>';
        }
        echo '</table>';
    }
    echo '</div>';

// ==================== مدیریت کاربران ====================
} elseif ($p === 'stats') {
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'portal';
    $rl = is_loc_restricted() ? user_loc() : '';
    $dsel = (int)(isset($_GET['days']) ? $_GET['days'] : 30);
    if (!in_array($dsel, array(7, 30, 90, 365, 0))) $dsel = 30;
    $since = $dsel ? date('Y-m-d', time() - $dsel * 86400) : '2000-01-01';
    $today = date('Y-m-d');
    $tabs = array('portal' => 'بازدید از پرتال', 'loc' => 'بازدید به تفکیک محل و منابع', 'online' => 'کاربران آنلاین و منطقهٔ جغرافیایی', 'presence' => 'حضور کاربران هر محل', 'res' => 'بازدید منابع (کلی و تفکیکی)', 'conc' => 'استفادهٔ هم‌زمان');
    echo '<h1>آمار سامانه' . ($rl ? ' (' . h($rl) . ')' : '') . '</h1>';
    if (!empty($_GET['msg'])) echo '<div class="ok-msg">ذخیره شد.</div>';
    echo '<div class="tabs">';
    foreach ($tabs as $k => $v) echo '<a class="' . ($tab === $k ? 'on' : '') . '" href="?p=stats&tab=' . $k . '&days=' . $dsel . '">' . $v . '</a>';
    echo '</div><form method="get" class="row" style="margin-bottom:12px"><input type="hidden" name="p" value="stats"><input type="hidden" name="tab" value="' . h($tab) . '">';
    foreach (array('hloc', 'src', 'rid') as $kk) if (isset($_GET[$kk])) echo '<input type="hidden" name="' . $kk . '" value="' . h($_GET[$kk]) . '">';
    echo '<div style="width:200px"><label>بازهٔ زمانی</label><select name="days" onchange="this.form.submit()">';
    foreach (array(7 => '۷ روز اخیر', 30 => '۳۰ روز اخیر', 90 => '۹۰ روز اخیر', 365 => 'یک سال اخیر', 0 => 'کل دوره') as $dv => $dl) echo '<option value="' . $dv . '"' . ($dsel === $dv ? ' selected' : '') . '>' . $dl . '</option>';
    echo '</select></div></form>';
    $note_priv = '<p style="font-size:12px;opacity:.8">برای حفظ حریم خصوصی، آدرس IP ذخیره نمی‌شود؛ فقط یک شناسهٔ رمزگذاری‌شدهٔ یک‌طرفه برای شمارش بازدیدکنندهٔ یکتا نگهداری می‌شود.</p>';

    if ($tab === 'portal') {
        $q1 = function ($from) use ($db) { $st = $db->prepare("SELECT COUNT(*), COUNT(DISTINCT vh) FROM visits WHERE kind='portal' AND day>=?"); $st->execute(array($from)); return $st->fetch(PDO::FETCH_NUM); };
        $a = $q1($today); $b = $q1(date('Y-m-d', time() - 6 * 86400)); $c = $q1(date('Y-m-d', time() - 29 * 86400)); $d = $q1('2000-01-01');
        echo '<div class="stats">';
        foreach (array(array('امروز', $a), array('۷ روز اخیر', $b), array('۳۰ روز اخیر', $c), array('کل دوره', $d)) as $cd) echo '<div class="card"><b>' . tr_num($cd[1][0]) . '</b>' . $cd[0] . ' — بازدید<br><small>' . tr_num($cd[1][1]) . ' بازدیدکنندهٔ یکتا</small></div>';
        echo '</div>';
        $map = array();
        $st = $db->prepare("SELECT day, COUNT(*) c FROM visits WHERE kind='portal' AND day>=? GROUP BY day");
        $st->execute(array(date('Y-m-d', time() - 29 * 86400)));
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $dk => $cv) $map[$dk] = (int)$cv;
        $rows = array();
        for ($i = 29; $i >= 0; $i--) { $dk = date('Y-m-d', time() - $i * 86400); $rows[mb_substr(jdate($dk), 5)] = isset($map[$dk]) ? $map[$dk] : 0; }
        echo '<div class="card"><h2>بازدید روزانهٔ پرتال (۳۰ روز اخیر)</h2>' . bar_chart($rows) . '</div>';
        $st = $db->prepare("SELECT substr(ts,12,2) h, COUNT(*) c FROM visits WHERE kind='portal' AND day>=? GROUP BY h");
        $st->execute(array($since));
        $hm = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = array();
        for ($i = 0; $i < 24; $i++) { $k = sprintf('%02d', $i); $rows[tr_num($k)] = isset($hm[$k]) ? (int)$hm[$k] : 0; }
        echo '<div class="grid"><div class="card"><h2>پراکندگی بازدید در ساعات شبانه‌روز</h2>' . bar_chart($rows) . '</div><div class="card"><h2>صفحات پربازدید پرتال</h2><table><tr><th>صفحه</th><th>بازدید</th></tr>';
        $pn = array('home' => 'صفحهٔ اول پرتال', 'news_list' => 'اخبار', 'res' => 'مشخصات منبع');
        $st = $db->prepare("SELECT page, COUNT(*) c FROM visits WHERE kind='portal' AND day>=? GROUP BY page ORDER BY c DESC");
        $st->execute(array($since));
        foreach ($st as $x) echo '<tr><td>' . h(isset($pn[$x['page']]) ? $pn[$x['page']] : $x['page']) . '</td><td>' . tr_num($x['c']) . '</td></tr>';
        echo '</table></div></div>' . $note_priv;

    } elseif ($tab === 'loc') {
        $w = $rl ? " AND loc=" . $db->quote($rl) : "";
        echo '<div class="card"><h2>بازدید به تفکیک منشأ بازدیدکننده (محل کاربر یا پرتال)</h2><table><tr><th>منشأ</th><th>تعداد بازدید</th><th>بازدیدکنندهٔ یکتا</th><th>منابع متفاوت بازدیدشده</th></tr>';
        $st = $db->prepare("SELECT loc, COUNT(*) v, COUNT(DISTINCT vh) u, COUNT(DISTINCT CASE WHEN rid>0 THEN rid END) rs FROM visits WHERE day>=? $w GROUP BY loc ORDER BY v DESC");
        $st->execute(array($since));
        foreach ($st as $x) echo '<tr><td><b>' . h($x['loc']) . '</b></td><td>' . tr_num($x['v']) . '</td><td>' . tr_num($x['u']) . '</td><td>' . tr_num($x['rs']) . '</td></tr>';
        echo '</table></div>';
        $hl = $rl ? $rl : (isset($_GET['hloc']) && in_array($_GET['hloc'], $Locations) ? $_GET['hloc'] : $Locations[0]);
        echo '<div class="card"><h2>پربازدیدترین منابع نگهداری‌شده در یک محل</h2><form method="get" class="row"><input type="hidden" name="p" value="stats"><input type="hidden" name="tab" value="loc"><input type="hidden" name="days" value="' . $dsel . '">';
        if (!$rl) { echo '<div style="width:260px"><label>کتابخانه منبع</label><select name="hloc" onchange="this.form.submit()">'; foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($hl === $lc ? ' selected' : '') . '>' . h($lc) . '</option>'; echo '</select></div>'; }
        echo '</form><table style="margin-top:10px"><tr><th>عنوان</th><th>پدیدآور</th><th>بازدید از پرتال</th><th>بازدید کاربران سامانه</th><th>جمع</th></tr>';
        $st = $db->prepare("SELECT r.id, r.f200a, r.f700, r.f200f, SUM(CASE WHEN v.kind='portal' THEN 1 ELSE 0 END) pv, SUM(CASE WHEN v.kind='rec' THEN 1 ELSE 0 END) sv, COUNT(*) c FROM visits v JOIN records r ON r.id=v.rid WHERE v.rid>0 AND v.day>=? AND EXISTS (SELECT 1 FROM items i WHERE i.rid=r.id AND i.loc=?) GROUP BY r.id ORDER BY c DESC LIMIT 30");
        $st->execute(array($since, $hl));
        $any = false;
        foreach ($st as $x) { $any = true; echo '<tr><td><a href="?p=marc&id=' . $x['id'] . '">' . h($x['f200a']) . '</a></td><td>' . h($x['f700'] ? $x['f700'] : $x['f200f']) . '</td><td>' . tr_num($x['pv']) . '</td><td>' . tr_num($x['sv']) . '</td><td><b>' . tr_num($x['c']) . '</b></td></tr>'; }
        if (!$any) echo '<tr><td colspan="5" class="warn">در این بازه بازدیدی از منابع این محل ثبت نشده است.</td></tr>';
        echo '</table></div>' . $note_priv;

    } elseif ($tab === 'online') {
        $o = online_now();
        echo '<div class="stats"><div class="card"><b>' . tr_num($o[0]) . '</b>کاربر سامانه (کتابدار) آنلاین</div><div class="card"><b>' . tr_num($o[1]) . '</b>بازدیدکنندهٔ پرتال (۵ دقیقهٔ اخیر)</div></div>';
        $w = $rl ? " AND loc=" . $db->quote($rl) : "";
        echo '<div class="card"><h2>کاربران سامانه که اکنون آنلاین‌اند</h2><table><tr><th>کاربر</th><th>محل</th><th>منطقه</th><th>ورود</th><th>آخرین فعالیت</th></tr>';
        $st = $db->prepare("SELECT * FROM sessions_log WHERE ended=0 AND last_seen>? $w ORDER BY last_seen DESC");
        $st->execute(array(time() - 300));
        $any = false;
        foreach ($st as $x) { $any = true; echo '<tr><td><b>' . h($x['uname']) . '</b></td><td>' . h($x['loc']) . '</td><td>' . h($x['region']) . '</td><td>' . jdate(date('Y-m-d H:i:s', $x['login_ts']), true) . '</td><td>' . tr_num(max(0, time() - $x['last_seen'])) . ' ثانیه پیش</td></tr>'; }
        if (!$any) echo '<tr><td colspan="5">کاربر آنلاینی نیست.</td></tr>';
        echo '</table></div><div class="grid"><div class="card"><h2>منطقهٔ جغرافیایی کاربران سامانه (ورودها)</h2><table><tr><th>منطقه</th><th>ورود</th><th>کاربر یکتا</th></tr>';
        $st = $db->prepare("SELECT region, COUNT(*) c, COUNT(DISTINCT uname) u FROM sessions_log WHERE login_ts>=? $w GROUP BY region ORDER BY c DESC LIMIT 20");
        $st->execute(array(strtotime($since)));
        foreach ($st as $x) echo '<tr><td>' . h($x['region']) . '</td><td>' . tr_num($x['c']) . '</td><td>' . tr_num($x['u']) . '</td></tr>';
        echo '</table></div><div class="card"><h2>منطقهٔ جغرافیایی بازدیدکنندگان پرتال</h2><table><tr><th>منطقه</th><th>بازدیدکنندهٔ یکتا</th><th>بازدید</th></tr>';
        $st = $db->prepare("SELECT region, COUNT(DISTINCT vh) u, COUNT(*) c FROM visits WHERE kind='portal' AND day>=? GROUP BY region ORDER BY u DESC LIMIT 20");
        $st->execute(array($since));
        foreach ($st as $x) echo '<tr><td>' . h($x['region']) . '</td><td>' . tr_num($x['u']) . '</td><td>' . tr_num($x['c']) . '</td></tr>';
        echo '</table></div></div>';
        $gm = get_cms('geo_mode');
        echo '<div class="card"><h2>روش تشخیص منطقهٔ جغرافیایی</h2><p style="font-size:12.8px;line-height:1.8">اگر سایت پشت Cloudflare باشد، کشور بازدیدکننده خودکار از سرآیند آن خوانده می‌شود. در غیر این صورت، منطقه «نامشخص» می‌ماند، مگر اینکه مدیر کل جست‌وجوی برخط را فعال کند؛ در آن حالت آدرس IP بازدیدکنندهٔ جدید به سرویس خارجی <code>ip-api.com</code> فرستاده می‌شود (نتیجه ۳۰ روز در پایگاه ذخیره می‌شود). آدرس‌های شبکهٔ داخلی «شبکه داخلی» ثبت می‌شوند.</p>';
        if (is_super_admin()) echo '<form method="post" action="?p=save_geo" class="row"><input type="hidden" name="t" value="' . $tok . '"><div style="width:360px"><select name="geo_mode"><option value="off"' . ($gm !== 'ipapi' ? ' selected' : '') . '>غیرفعال (فقط Cloudflare / شبکهٔ داخلی)</option><option value="ipapi"' . ($gm === 'ipapi' ? ' selected' : '') . '>فعال: جست‌وجوی برخط با ip-api.com</option></select></div><button class="btn pri">ذخیره</button></form>';
        echo '</div>' . $note_priv;

    } elseif ($tab === 'presence') {
        $w = $rl ? " AND loc=" . $db->quote($rl) : "";
        $from = strtotime($since);
        echo '<div class="card"><h2>حضور کاربران به تفکیک کتابخانه</h2><table><tr><th>محل</th><th>نشست‌ها (ورود)</th><th>کاربر یکتا</th><th>مجموع زمان حضور</th><th>میانگین هر نشست</th><th>آخرین فعالیت</th><th>اکنون آنلاین</th></tr>';
        $st = $db->prepare("SELECT loc, COUNT(*) n, COUNT(DISTINCT uname) u, SUM(last_seen-login_ts) secs, MAX(last_seen) ls, SUM(CASE WHEN ended=0 AND last_seen>? THEN 1 ELSE 0 END) onl FROM sessions_log WHERE login_ts>=? $w GROUP BY loc ORDER BY secs DESC");
        $st->execute(array(time() - 300, $from));
        foreach ($st as $x) echo '<tr><td><b>' . h($x['loc']) . '</b></td><td>' . tr_num($x['n']) . '</td><td>' . tr_num($x['u']) . '</td><td>' . fmt_dur($x['secs']) . '</td><td>' . fmt_dur($x['n'] ? $x['secs'] / $x['n'] : 0) . '</td><td>' . jdate(date('Y-m-d H:i:s', $x['ls']), true) . '</td><td>' . tr_num($x['onl']) . '</td></tr>';
        echo '</table></div><div class="card"><h2>حضور هر کاربر</h2><table><tr><th>کاربر</th><th>محل</th><th>تعداد ورود</th><th>مجموع زمان</th><th>آخرین ورود</th><th>منطقه (آخرین)</th></tr>';
        $st = $db->prepare("SELECT uname, loc, COUNT(*) n, SUM(last_seen-login_ts) secs, MAX(login_ts) lt, (SELECT region FROM sessions_log s2 WHERE s2.uname=s1.uname ORDER BY login_ts DESC LIMIT 1) reg FROM sessions_log s1 WHERE login_ts>=? $w GROUP BY uname, loc ORDER BY secs DESC LIMIT 100");
        $st->execute(array($from));
        foreach ($st as $x) echo '<tr><td><b>' . h($x['uname']) . '</b></td><td>' . h($x['loc']) . '</td><td>' . tr_num($x['n']) . '</td><td>' . fmt_dur($x['secs']) . '</td><td>' . jdate(date('Y-m-d H:i:s', $x['lt']), true) . '</td><td>' . h($x['reg']) . '</td></tr>';
        echo '</table></div>';
        $map = array();
        $st = $db->prepare("SELECT to_char(to_timestamp(login_ts), 'YYYY-MM-DD') d, COUNT(DISTINCT uname) u FROM sessions_log WHERE login_ts>=? $w GROUP BY d");
        $st->execute(array(time() - 29 * 86400));
        $map = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = array();
        for ($i = 29; $i >= 0; $i--) { $dk = date('Y-m-d', time() - $i * 86400); $rows[mb_substr(jdate($dk), 5)] = isset($map[$dk]) ? (int)$map[$dk] : 0; }
        echo '<div class="card"><h2>کاربران یکتای حاضر در هر روز (۳۰ روز اخیر)</h2>' . bar_chart($rows) . '</div>';

    } elseif ($tab === 'res') {
        $src = isset($_GET['src']) && in_array($_GET['src'], array('portal', 'rec')) ? $_GET['src'] : 'all';
        $hl = $rl ? $rl : (isset($_GET['hloc']) && in_array($_GET['hloc'], $Locations) ? $_GET['hloc'] : '');
        $detail = (int)(isset($_GET['rid']) ? $_GET['rid'] : 0);
        if ($detail) {
            $sr = $db->prepare("SELECT f200a, f700 FROM records WHERE id=?");
            $sr->execute(array($detail));
            $r = $sr->fetch(PDO::FETCH_ASSOC);
            echo '<div class="card"><h2>جزئیات بازدید: ' . h($r ? $r['f200a'] : '-') . ' <a class="btn-sm" href="?p=stats&tab=res&days=' . $dsel . '">← بازگشت</a></h2>';
            $st = $db->prepare("SELECT day, COUNT(*) c FROM visits WHERE rid=? AND day>=? GROUP BY day");
            $st->execute(array($detail, date('Y-m-d', time() - 29 * 86400)));
            $map = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            $rows = array();
            for ($i = 29; $i >= 0; $i--) { $dk = date('Y-m-d', time() - $i * 86400); $rows[mb_substr(jdate($dk), 5)] = isset($map[$dk]) ? (int)$map[$dk] : 0; }
            echo bar_chart($rows) . '<div class="grid"><div><h3>به تفکیک منشأ بازدیدکننده</h3><table><tr><th>منشأ</th><th>بازدید</th></tr>';
            $st = $db->prepare("SELECT loc, COUNT(*) c FROM visits WHERE rid=? AND day>=? GROUP BY loc ORDER BY c DESC");
            $st->execute(array($detail, $since));
            foreach ($st as $x) echo '<tr><td>' . h($x['loc']) . '</td><td>' . tr_num($x['c']) . '</td></tr>';
            echo '</table></div><div><h3>به تفکیک منطقه</h3><table><tr><th>منطقه</th><th>بازدید</th></tr>';
            $st = $db->prepare("SELECT region, COUNT(*) c FROM visits WHERE rid=? AND day>=? GROUP BY region ORDER BY c DESC LIMIT 15");
            $st->execute(array($detail, $since));
            foreach ($st as $x) echo '<tr><td>' . h($x['region']) . '</td><td>' . tr_num($x['c']) . '</td></tr>';
            echo '</table></div></div></div>';
        } else {
            $tot = $db->prepare("SELECT COUNT(*), COUNT(DISTINCT rid) FROM visits WHERE rid>0 AND day>=?");
            $tot->execute(array($since));
            $tv = $tot->fetch(PDO::FETCH_NUM);
            echo '<div class="stats"><div class="card"><b>' . tr_num($tv[0]) . '</b>کل بازدید منابع</div><div class="card"><b>' . tr_num($tv[1]) . '</b>منبع متفاوت بازدیدشده</div></div>';
            echo '<div class="card"><h2>پربازدیدترین منابع</h2><form method="get" class="row"><input type="hidden" name="p" value="stats"><input type="hidden" name="tab" value="res"><input type="hidden" name="days" value="' . $dsel . '"><div style="width:200px"><label>منبع بازدید</label><select name="src" onchange="this.form.submit()"><option value="all"' . ($src === 'all' ? ' selected' : '') . '>همه</option><option value="portal"' . ($src === 'portal' ? ' selected' : '') . '>فقط پرتال</option><option value="rec"' . ($src === 'rec' ? ' selected' : '') . '>فقط کاربران سامانه</option></select></div>';
            if (!$rl) { echo '<div style="width:260px"><label>کتابخانه منبع</label><select name="hloc" onchange="this.form.submit()"><option value="">همهٔ محل‌ها</option>'; foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($hl === $lc ? ' selected' : '') . '>' . h($lc) . '</option>'; echo '</select></div>'; }
            echo '</form><table style="margin-top:10px"><tr><th>عنوان</th><th>پدیدآور</th><th>پرتال</th><th>کاربران سامانه</th><th>جمع بازدید</th><th>تعداد امانت</th><th></th></tr>';
            $w = ''; $par = array($since);
            if ($src !== 'all') { $w .= " AND v.kind=?"; $par[] = $src; }
            if ($hl !== '') { $w .= " AND EXISTS (SELECT 1 FROM items i WHERE i.rid=r.id AND i.loc=?)"; $par[] = $hl; }
            $st = $db->prepare("SELECT r.id, r.f200a, r.f700, r.f200f, SUM(CASE WHEN v.kind='portal' THEN 1 ELSE 0 END) pv, SUM(CASE WHEN v.kind='rec' THEN 1 ELSE 0 END) sv, COUNT(*) c, (SELECT COUNT(*) FROM loans l JOIN items i2 ON i2.id=l.rid WHERE i2.rid=r.id) ln FROM visits v JOIN records r ON r.id=v.rid WHERE v.rid>0 AND v.day>=? $w GROUP BY r.id ORDER BY c DESC LIMIT 50");
            $st->execute($par);
            $any = false;
            foreach ($st as $x) { $any = true; echo '<tr><td><a href="?p=marc&id=' . $x['id'] . '">' . h($x['f200a']) . '</a></td><td>' . h($x['f700'] ? $x['f700'] : $x['f200f']) . '</td><td>' . tr_num($x['pv']) . '</td><td>' . tr_num($x['sv']) . '</td><td><b>' . tr_num($x['c']) . '</b></td><td>' . tr_num($x['ln']) . '</td><td><a class="btn-sm" href="?p=stats&tab=res&days=' . $dsel . '&rid=' . $x['id'] . '">جزئیات</a></td></tr>'; }
            if (!$any) echo '<tr><td colspan="7" class="warn">بازدیدی ثبت نشده است. بازدید منابع هنگام مشاهدهٔ صفحهٔ مشخصات منبع در پرتال یا شناسنامهٔ مارک در سامانه ثبت می‌شود.</td></tr>';
            echo '</table></div>';
        }

    } elseif ($tab === 'conc') {
        $o = online_now();
        $mx = function ($like) use ($db) { $st = $db->prepare("SELECT COALESCE(MAX(mx),0), COALESCE(SUM(sm),0), COALESCE(SUM(n),0) FROM conc WHERE hr>=?"); $st->execute(array($like)); return $st->fetch(PDO::FETCH_NUM); };
        $t = $mx($today . ' 00'); $w7 = $mx(date('Y-m-d', time() - 6 * 86400) . ' 00'); $al = $mx('2000');
        echo '<div class="stats"><div class="card"><b>' . tr_num($o[0] + $o[1]) . '</b>هم‌اکنون هم‌زمان (۵ دقیقه)</div><div class="card"><b>' . tr_num($t[0]) . '</b>اوج امروز</div><div class="card"><b>' . tr_num($w7[0]) . '</b>اوج ۷ روز اخیر</div><div class="card"><b>' . tr_num($al[0]) . '</b>اوج کل دوره</div><div class="card"><b>' . tr_num($t[2] ? round($t[1] / $t[2], 1) : 0) . '</b>میانگین امروز</div></div>';
        $st = $db->prepare("SELECT hr, mx FROM conc WHERE hr>=?");
        $st->execute(array(date('Y-m-d H', time() - 47 * 3600)));
        $map = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = array();
        for ($i = 47; $i >= 0; $i--) { $k = date('Y-m-d H', time() - $i * 3600); $rows[tr_num(substr($k, 11)) . ($i % 24 === 0 ? '*' : '')] = isset($map[$k]) ? (int)$map[$k] : 0; }
        echo '<div class="card"><h2>اوج استفادهٔ هم‌زمان در هر ساعت (۴۸ ساعت اخیر)</h2>' . bar_chart($rows) . '<p style="font-size:12px">ستاره (*) ساعت ۰۰ را نشان می‌دهد. «هم‌زمان» = کاربران سامانه + بازدیدکنندگان پرتال در ۵ دقیقهٔ اخیر.</p></div>';
        $st = $db->prepare("SELECT substr(hr,1,10) d, MAX(mx) m FROM conc WHERE hr>=? GROUP BY d");
        $st->execute(array(date('Y-m-d', time() - 29 * 86400) . ' 00'));
        $map = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $rows = array();
        for ($i = 29; $i >= 0; $i--) { $dk = date('Y-m-d', time() - $i * 86400); $rows[mb_substr(jdate($dk), 5)] = isset($map[$dk]) ? (int)$map[$dk] : 0; }
        echo '<div class="card"><h2>اوج روزانهٔ استفادهٔ هم‌زمان (۳۰ روز اخیر)</h2>' . bar_chart($rows) . '</div>';
    }
}
