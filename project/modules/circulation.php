<?php
// ماژول گردش امانت و اعضا: میز امانت سریع و فهرست منابع (rec)، مدیریت اعضا (mem) و امانت‌های جاری (loan)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

function render_active_reservations_admin($from_p = 'loan') {
    global $db, $tok;
    $db->exec("DELETE FROM reservations WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP");
    $w_res = is_loc_restricted() ? " AND res.loc=" . $db->quote(user_loc()) : "";
    $st_res = $db->query("SELECT res.*, r.f200a, r.f700, m.name AS mname, m.phone AS mphone, m.mcode AS mmcode,
        (SELECT COUNT(*) FROM items i WHERE i.rid=res.rid AND i.loc=res.loc AND NOT EXISTS (SELECT 1 FROM loans l WHERE l.rid=i.id AND l.ret IS NULL)) AS avail_cnt
        FROM reservations res
        JOIN records r ON r.id=res.rid
        JOIN members m ON m.id=res.mid
        WHERE res.status='pending' AND (res.expires_at IS NULL OR res.expires_at > CURRENT_TIMESTAMP) $w_res
        ORDER BY res.id DESC");
    $res_rows = $st_res->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card" style="border:2px solid var(--gold);background:#fffbeb">
        <h2><span>درخواست‌های رزرو منابع توسط اعضا (فعال به مدت ۴۸ ساعت — ' . tr_num(count($res_rows)) . ' مورد)</span><span class="badge" style="background:var(--gold);color:#fff">حذف خودکار پس از ۴۸ ساعت</span></h2>';
    if ($res_rows) {
        echo '<table><tr><th>عضو درخواست‌کننده</th><th>عنوان منبع رزروشده</th><th>کتابخانه</th><th>زمان ثبت رزرو</th><th>مهلت اعتبار (۴۸ ساعت)</th><th>موجودی قفسه</th><th>عملیات مدیر کتابخانه</th></tr>';
        foreach ($res_rows as $rr) {
            $exp_s = !empty($rr['expires_at']) ? $rr['expires_at'] : date('Y-m-d H:i:s', strtotime($rr['created']) + 48 * 3600);
            $rem_h = max(0, round((strtotime($exp_s) - time()) / 3600, 1));
            echo '<tr>
                <td><b>' . h($rr['mname']) . '</b><br><small>کد: ' . tr_num(h($rr['mmcode'])) . ' | تلفن: ' . tr_num(h($rr['mphone'])) . '</small></td>
                <td><b>' . h($rr['f200a']) . '</b>' . ($rr['f700'] ? '<br><small>' . h($rr['f700']) . '</small>' : '') . '</td>
                <td><span class="badge">' . h($rr['loc']) . '</span></td>
                <td>' . jdate($rr['created'], true) . '</td>
                <td><b>' . jdate($exp_s, true) . '</b><br><small style="color:#b45309">' . tr_num($rem_h) . ' ساعت باقی‌مانده</small></td>
                <td>' . ($rr['avail_cnt'] > 0 ? '<span style="color:var(--ok);font-weight:700">' . tr_num($rr['avail_cnt']) . ' جلد آماده امانت</span>' : '<span class="warn">در امانت</span>') . '</td>
                <td style="white-space:nowrap"><div style="display:inline-flex;gap:6px;align-items:center">';
                if ($rr['avail_cnt'] > 0) {
                    echo '<form method="post" action="?p=res_admin_act" style="display:inline-flex;gap:4px;align-items:center">
                        <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="res_id" value="' . $rr['id'] . '">
                        <input type="hidden" name="act" value="approve_loan"><input type="hidden" name="from_p" value="' . h($from_p) . '">
                        <input type="number" name="days" value="14" min="1" max="365" style="width:55px;padding:3px 6px" title="مدت امانت (روز)">
                        <button class="btn-sm ok">تحویل و ثبت امانت</button>
                    </form>';
                }
                echo '<form method="post" action="?p=res_admin_act" style="display:inline" onsubmit="return confirm(\'آیا از لغو و حذف این درخواست رزرو مطمئن هستید؟\')">
                        <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="res_id" value="' . $rr['id'] . '">
                        <input type="hidden" name="act" value="cancel"><input type="hidden" name="from_p" value="' . h($from_p) . '">
                        <button class="btn-sm dng">حذف رزرو</button>
                    </form>
                </div></td>
            </tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="margin:0;font-size:13px;color:#78350f">در حال حاضر هیچ درخواست رزرو فعالی (در بازه ۴۸ ساعت گذشته) برای این کتابخانه ثبت نشده است.</p>';
    }
    echo '</div>';
}

if ($p === 'rec') {
    $mq = trim(isset($_GET['mq']) ? $_GET['mq'] : '');
    $sel_mid = (int)(isset($_GET['sel_mid']) ? $_GET['sel_mid'] : 0);
    $bq = trim(isset($_GET['bq']) ? $_GET['bq'] : '');
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
    $q = trim(isset($_GET['q']) ? $_GET['q'] : '');
    $ret_url = "?p=rec&tab=" . urlencode($tab) . "&sel_mid=$sel_mid&mq=" . urlencode($mq) . "&bq=" . urlencode($bq);
    $w_loc_sql = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";

    echo '<h1>میز امانت سریع و مدیریت منابع' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    render_active_reservations_admin('rec');
    echo '<div class="card" style="border:2px solid var(--blue)"><h2>گام اول: جستجو و انتخاب عضو جهت امانت یا بازگشت</h2>';
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
                    <span>عضو انتخاب‌شده: <b style="font-size:15px;color:var(--blue)">' . h($mem['name']) . '</b> (تلفن: ' . tr_num(h($mem['phone'])) . ' | ' . h($mem['loc']) . ')</span>
                    <a class="btn-sm" href="?p=mem&edit=' . $mem['id'] . '">ویرایش عضو</a>
                  </div>';

            $mloans = $db->prepare("SELECT l.*, r.f200a, r.reg_no FROM loans l JOIN v_items r ON r.id=l.rid WHERE l.mid=? AND l.ret IS NULL");
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

            echo '<h3>گام دوم: جستجوی منبع و ثبت امانت برای «' . h($mem['name']) . '»</h3>';
            echo '<form method="get" class="row"><input type="hidden" name="p" value="rec"><input type="hidden" name="sel_mid" value="' . $sel_mid . '"><input type="hidden" name="mq" value="' . h($mq) . '">
                  <div style="flex:1"><label>جستجوی کتاب/منبع (عنوان، شماره ثبت، پدیدآور، شابک)</label><input name="bq" value="' . h($bq) . '" placeholder="شماره ثبت یا عنوان کتاب را بنویسید..."></div>
                  <button class="btn pri">جستجوی کتاب</button></form>';

            if ($bq !== '') {
                $bs = $db->prepare("SELECT r.*, (1 - (SELECT COUNT(*) FROM loans l WHERE l.rid=r.id AND l.ret IS NULL)) AS avail FROM v_items r WHERE (f200a LIKE :q OR reg_no LIKE :q OR f700 LIKE :q OR f010 LIKE :q) $w_loc_sql ORDER BY r.rid, COALESCE(NULLIF(regexp_replace(r.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0) LIMIT 30");
                $bs->execute(array(':q' => "%$bq%"));
                $brows = $bs->fetchAll(PDO::FETCH_ASSOC);
                if ($brows) {
                    echo '<table style="margin-top:12px"><tr><th>شماره ثبت</th><th>عنوان</th><th>کتابخانه</th><th>پدیدآور</th><th>موجودی</th><th>تعیین مدت و امانت</th><th>عملیات</th></tr>';
                    foreach ($brows as $br) {
                        echo '<tr><td><b>' . tr_num(h($br['reg_no'])) . '</b></td><td><b>' . h($br['f200a']) . '</b> <span class="badge">جلد ' . tr_num(h($br['vol'])) . '</span></td><td><span class="badge">' . h($br['loc']) . '</span></td><td>' . h($br['f700']) . '</td><td>' . tr_num($br['avail']) . ' از ' . tr_num($br['copies']) . '</td><td>';
                        if ($br['avail'] > 0) {
                            echo '<form method="post" action="?p=out" style="display:flex;gap:6px;align-items:center">
                                  <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="rid" value="' . $br['id'] . '"><input type="hidden" name="mid" value="' . $sel_mid . '"><input type="hidden" name="ret_url" value="' . h($ret_url) . '">
                                  <input type="number" name="days" value="14" min="1" max="365" style="width:65px;padding:4px 8px"><span>روز</span>
                                  <button class="btn-sm pri">ثبت امانت</button></form>';
                        } else echo '<span class="warn">ناموجود</span>';
                        echo '</td><td><a class="btn-sm" href="?p=form&id=' . $br['rid'] . '">ویرایش</a></td></tr>';
                    }
                    echo '</table>';
                } else echo '<p class="warn" style="margin-top:10px">منبعی در کتابخانه شما یافت نشد.</p>';
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
        $w[] = "EXISTS (SELECT 1 FROM items i WHERE i.rid=r.id AND i.loc=:uloc)";
        $par[':uloc'] = user_loc();
    }
    if ($tab !== 'all' && isset($RTypes[$tab])) {
        $w[] = "r.rtype = :rt";
        $par[':rt'] = $tab;
    }
    if ($q !== '') {
        $fc = title_cond($q, $par, 'r');
        if ($fc !== null) {
            $w[] = "($fc OR EXISTS (SELECT 1 FROM items i2 WHERE i2.rid=r.id AND (i2.reg_no LIKE :qr OR i2.loc LIKE :qr)))";
            $par[':qr'] = "%$q%";
        } else {
            $w[] = "(r.f200a LIKE :q OR r.f700 LIKE :q OR r.f710 LIKE :q OR r.f010 LIKE :q OR r.f606 LIKE :q OR r.f676 LIKE :q OR r.f686 LIKE :q OR r.f210c LIKE :q OR EXISTS (SELECT 1 FROM items i2 WHERE i2.rid=r.id AND (i2.reg_no LIKE :q OR i2.loc LIKE :q)))";
            $par[':q'] = "%$q%";
        }
    }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $c = $db->prepare("SELECT COUNT(*) FROM records r $where"); $c->execute($par); $tot = (int)$c->fetchColumn();
    $s = $db->prepare("SELECT r.* FROM records r $where ORDER BY r.id DESC LIMIT $per OFFSET " . ($pg - 1) * $per); $s->execute($par);
    $rows_t = $s->fetchAll(PDO::FETCH_ASSOC);
    $hold = array();
    if ($rows_t) {
        $ids_in = implode(',', array_map('intval', array_column($rows_t, 'id')));
        foreach ($db->query("SELECT i.rid, i.loc, COUNT(*) c, MIN(i.reg_no) a, MAX(i.reg_no) b, (SELECT rec_no FROM loc_rec lr WHERE lr.rid=i.rid AND lr.loc=i.loc) rn FROM items i WHERE i.rid IN ($ids_in) GROUP BY i.rid, i.loc ORDER BY i.loc") as $hr) {
            if (is_loc_restricted() && $hr['loc'] !== user_loc()) continue;
            $hold[$hr['rid']][] = $hr;
        }
    }

    echo '<form class="card row" method="get"><input type="hidden" name="p" value="rec"><input type="hidden" name="tab" value="' . h($tab) . '">
          <div style="flex:1"><label>جستجو در کل منابع (شماره ثبت، عنوان، پدیدآور، شابک، رده دیویی/کنگره)</label><input name="q" value="' . h($q) . '"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn pri">جستجو</button>' . (can('form') ? '<a class="btn ok" href="?p=form">+ ثبت منبع جدید</a>' : '') . '<a class="btn" href="?p=export_excel_rec">خروجی اکسل</a></div></form>';

    echo "<div class='card'><table><tr><th>نوع</th><th>عنوان</th><th>پدیدآور</th><th>ناشر</th><th>سال</th><th>کتابخانه، سریال رکورد و مجلدات (شماره ثبت)</th><th>رده</th><th>پیوست</th><th>عملیات</th></tr>";
    foreach ($rows_t as $r) {
        echo '<tr><td><span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></td>
        <td><b>' . h($r['f200a']) . '</b></td><td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td>
        <td>' . h($r['f210c']) . '</td><td>' . tr_num(h($r['f210d'])) . '</td><td>';
        if (!empty($hold[$r['id']])) {
            foreach ($hold[$r['id']] as $hr) {
                echo '<div style="margin-bottom:3px"><span class="badge">' . h($hr['loc']) . '</span> <small>رکورد ' . tr_num($hr['rn']) . ' · ' . tr_num($hr['c']) . ' جلد · ' . tr_num($hr['a']) . ($hr['c'] > 1 ? ' تا ' . tr_num($hr['b']) : '') . '</small></div>';
            }
        } else echo '<span class="warn">بدون موجودی</span>';
        echo '</td><td>' . h($r['f676'] ? $r['f676'] : $r['f686']) . '</td>
        <td>' . (!empty($r['file']) ? '<a class="btn-sm" href="' . h($r['file']) . '" target="_blank">فایل</a>' : '-') . '</td>
        <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
            <a class="btn-sm" href="?p=marc&id=' . $r['id'] . '">نمایش</a>
            ' . (can('form') ? '<a class="btn-sm pri" href="?p=form&id=' . $r['id'] . '">ویرایش</a>' : '') . '
            ' . (can('form') ? post_btn('del', 'حذف', array('id'=>$r['id']), 'btn-sm dng') : '') . '
        </div></td></tr>';
    }
    echo '</table><p>مجموع: <b>' . tr_num($tot) . '</b> عنوان</p>';
    $last_pg = max(1, (int)ceil($tot / $per));
    for ($i = max(1, $pg - 6); $i <= min($last_pg, $pg + 6); $i++) {
        echo '<a class="btn-sm' . ($i == $pg ? ' pri' : '') . '" href="?p=rec&tab=' . urlencode($tab) . '&q=' . urlencode($q) . '&pg=' . $i . '">' . tr_num($i) . '</a> ';
    }
    echo '</div>';
} elseif ($p === 'mem') {
    $sub = isset($_GET['sub']) ? $_GET['sub'] : 'list';
    $allowed_locs = allowed_locations();
    $w_loc = is_loc_restricted() ? " WHERE loc=" . $db->quote(user_loc()) : "";

    echo '<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <h1 style="margin:0">مدیریت اعضا، تایید عضویت، بازنشانی رمز و چاپ کارت عضویت</h1>
        <div class="tabs" style="width:auto;margin:0;border:0;padding:0">
            <a class="' . ($sub === 'list' ? 'on' : '') . '" href="?p=mem&sub=list">فهرست اعضا و تایید ثبت‌نام‌ها</a>
            <a class="' . ($sub === 'cards' ? 'on' : '') . '" href="?p=mem&sub=cards">چاپ کارت عضویت با تنظیمات دلخواه</a>
        </div>
    </div>';

    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg no-print">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    if (isset($_GET['msg']) && strpos($_GET['msg'], 'imp_') === 0) {
        echo '<div class="ok-msg no-print">تعداد ' . tr_num(substr($_GET['msg'], 4)) . ' عضو جدید با موفقیت ثبت شد.</div>';
    }

    if ($sub === 'cards') {
        $cw      = max(45, min(140, (int)(isset($_GET['cw']) ? $_GET['cw'] : 86)));
        $ch      = max(35, min(110, (int)(isset($_GET['ch']) ? $_GET['ch'] : 54)));
        $cols    = max(1, min(4, (int)(isset($_GET['cols']) ? $_GET['cols'] : 2)));
        $gap     = max(2, min(20, (int)(isset($_GET['gap']) ? $_GET['gap'] : 6)));
        $fsize   = max(8, min(18, (int)(isset($_GET['fsize']) ? $_GET['fsize'] : 11)));
        $bdr     = max(1, min(5, (int)(isset($_GET['bdr']) ? $_GET['bdr'] : 2)));
        $hcol    = isset($_GET['hcol']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_GET['hcol']) ? $_GET['hcol'] : '#12316b';
        $hdr_txt = isset($_GET['hdr']) ? trim($_GET['hdr']) : get_cms('site_title');
        $ftr_txt = isset($_GET['ftr']) ? trim($_GET['ftr']) : 'ارائه این کارت هنگام امانت کتاب الزامی است.';
        $loc_f   = is_loc_restricted() ? user_loc() : trim(isset($_GET['loc']) ? $_GET['loc'] : '');
        $ids_str = trim(en_num(isset($_GET['ids']) ? $_GET['ids'] : ''));

        $all_card_fields = array(
            'f_code'    => 'شماره عضویت / کد کاربری',
            'f_mcode'   => 'کد ملی',
            'f_father'  => 'نام پدر',
            'f_bdate'   => 'تاریخ تولد',
            'f_loc'     => 'نام کتابخانه عضویت',
            'f_phone'   => 'شماره تماس',
            'f_regdate' => 'تاریخ عضویت',
            'f_expdate' => 'تاریخ اعتبار کارت',
            'f_photo'   => 'کادر عکس عضو',
            'f_barcode' => 'بارکد اسکن‌پذیر عضویت',
            'f_sign'    => 'محل مهر و امضای کتابخانه'
        );
        $sel_fields = array();
        $has_custom_f = isset($_GET['apply_cfg']);
        foreach ($all_card_fields as $fk => $flbl) {
            if (!$has_custom_f || isset($_GET[$fk])) $sel_fields[$fk] = true;
        }

        echo '<form class="card no-print" method="get">
            <input type="hidden" name="p" value="mem"><input type="hidden" name="sub" value="cards"><input type="hidden" name="apply_cfg" value="1">
            <h2>تنظیمات دلخواه انواع فیلدها و اندازه‌های چاپ کارت عضویت</h2>
            <div class="grid">
                <div><label>عنوان سربرگ کارت</label><input name="hdr" value="' . h($hdr_txt) . '"></div>
                <div><label>متن پایین کارت</label><input name="ftr" value="' . h($ftr_txt) . '"></div>
                <div><label>فیلتر کتابخانه</label><select name="loc">' . (!is_loc_restricted() ? '<option value="">همه کتابخانه‌ها</option>' : '');
                foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($loc_f === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
        echo '  </select></div>
                <div><label>کدهای عضویت انتخابی (با کاما، خالی=همه)</label><input name="ids" dir="ltr" class="no-num" value="' . h($ids_str) . '" placeholder="مثلاً 1,5,12"></div>
                <div><label>عرض کارت (mm - استاندارد 86)</label><input type="number" name="cw" value="' . $cw . '" min="45" max="140"></div>
                <div><label>ارتفاع کارت (mm - استاندارد 54)</label><input type="number" name="ch" value="' . $ch . '" min="35" max="110"></div>
                <div><label>تعداد ستون در هر ردیف</label><input type="number" name="cols" value="' . $cols . '" min="1" max="4"></div>
                <div><label>فاصله بین کارت‌ها (mm)</label><input type="number" name="gap" value="' . $gap . '" min="2" max="20"></div>
                <div><label>اندازه فونت نوشته‌ها (px)</label><input type="number" name="fsize" value="' . $fsize . '" min="8" max="18"></div>
                <div><label>ضخامت کادر دور کارت (px)</label><input type="number" name="bdr" value="' . $bdr . '" min="1" max="5"></div>
                <div><label>رنگ سربرگ کارت</label><input type="color" name="hcol" value="' . h($hcol) . '" style="height:40px;padding:2px"></div>
            </div>
            <label style="margin:14px 0 8px">انتخاب فیلدهای دلخواه جهت درج روی کارت عضویت:</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px;margin-bottom:14px">';
            foreach ($all_card_fields as $fk => $flbl) {
                echo '<label style="display:flex;align-items:center;gap:6px;background:var(--ice);padding:7px 10px;border-radius:10px;border:1px solid var(--line);cursor:pointer;margin:0">
                    <input type="checkbox" name="' . $fk . '" value="1"' . (!empty($sel_fields[$fk]) ? ' checked' : '') . '> ' . h($flbl) . '
                </label>';
            }
        echo '</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn pri">اعمال تنظیمات و پیش‌نمایش کارت‌ها</button>
                <button type="button" class="btn ok" onclick="window.print()">چاپ کارت‌های عضویت</button>
            </div>
        </form>';

        $w_c = array("(status IS NULL OR status='active')");
        $par_c = array();
        if ($loc_f !== '') { $w_c[] = "loc=?"; $par_c[] = $loc_f; }
        if ($ids_str !== '') {
            $id_arr = array_filter(array_map('intval', explode(',', str_replace('،', ',', $ids_str))));
            if ($id_arr) $w_c[] = "id IN (" . implode(',', $id_arr) . ")";
        }
        $st_mc = $db->prepare("SELECT * FROM members WHERE " . implode(' AND ', $w_c) . " ORDER BY id DESC LIMIT 100");
        $st_mc->execute($par_c);
        $mcards = $st_mc->fetchAll(PDO::FETCH_ASSOC);

        echo '<style>
        .mcard-sheet{display:grid;grid-template-columns:repeat(' . $cols . ',' . $cw . 'mm);gap:' . $gap . 'mm;justify-content:center;padding:6mm;background:#fff;border-radius:16px}
        .mcard{width:' . $cw . 'mm;height:' . $ch . 'mm;border:' . $bdr . 'px solid ' . $hcol . ';border-radius:3.5mm;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;background:#fff;color:#000;font-size:' . $fsize . 'px;page-break-inside:avoid}
        .mcard-hdr{background:' . $hcol . ';color:#fff;padding:1.8mm 3mm;font-weight:800;font-size:' . ($fsize + 1) . 'px;display:flex;justify-content:space-between;align-items:center}
        .mcard-body{display:flex;gap:2.5mm;padding:2mm 3mm;flex:1;align-items:center}
        .mcard-info{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:1mm 2.5mm;line-height:1.45}
        .mcard-photo{width:18mm;height:23mm;border:1px solid #94a3b8;border-radius:2mm;display:flex;align-items:center;justify-content:center;font-size:9px;color:#64748b;overflow:hidden;flex-shrink:0;background:#f8fafc}
        .mcard-photo img{width:100%;height:100%;object-fit:cover}
        .mcard-ftr{border-top:1px dashed #cbd5e1;padding:1mm 3mm;font-size:' . max(7, $fsize - 2) . 'px;display:flex;justify-content:space-between;align-items:center;background:#f8fafc}
        </style>';

        echo '<div class="mcard-sheet">';
        foreach ($mcards as $mc) {
            $reg_d = !empty($mc['created']) ? jdate($mc['created']) : jdate('now');
            $exp_d = !empty($mc['exp_date']) ? $mc['exp_date'] : jdate(date('Y-m-d', strtotime('+1 year')));
            $code_val = $mc['mcode'] ?: $mc['id'];
            echo '<div class="mcard">
                <div class="mcard-hdr"><span>' . h($hdr_txt) . '</span><span style="font-size:' . max(8, $fsize - 1) . 'px">کارت عضویت</span></div>
                <div class="mcard-body">
                    <div class="mcard-info">
                        <div style="grid-column:span 2;font-size:' . ($fsize + 1) . 'px">نام عضو: <b>' . h($mc['name']) . '</b></div>';
                        if (!empty($sel_fields['f_code']))    echo '<div>کد عضویت: <b>' . tr_num(h($mc['id'])) . '</b></div>';
                        if (!empty($sel_fields['f_mcode']))   echo '<div>کد ملی: <b>' . tr_num(h($mc['mcode'] ?: '-')) . '</b></div>';
                        if (!empty($sel_fields['f_father']) && !empty($mc['father'])) echo '<div>نام پدر: <b>' . h($mc['father']) . '</b></div>';
                        if (!empty($sel_fields['f_bdate']) && !empty($mc['bdate']))   echo '<div>تولد: <b>' . tr_num(h($mc['bdate'])) . '</b></div>';
                        if (!empty($sel_fields['f_loc']))     echo '<div>کتابخانه: <b>' . h($mc['loc']) . '</b></div>';
                        if (!empty($sel_fields['f_phone']))   echo '<div>تلفن: <b>' . tr_num(h($mc['phone'])) . '</b></div>';
                        if (!empty($sel_fields['f_regdate'])) echo '<div>عضویت: <b>' . $reg_d . '</b></div>';
                        if (!empty($sel_fields['f_expdate'])) echo '<div>اعتبار: <b>' . tr_num(h($exp_d)) . '</b></div>';
            echo '  </div>';
                    if (!empty($sel_fields['f_photo'])) {
                        echo '<div class="mcard-photo">' . (!empty($mc['photo']) ? '<img src="' . h($mc['photo']) . '">' : 'محل عکس') . '</div>';
                    }
            echo '</div>
                <div class="mcard-ftr">
                    <span>' . h($ftr_txt) . '</span>
                    <span style="display:flex;gap:6px;align-items:center">' .
                        (!empty($sel_fields['f_sign']) ? '<b>مهر و امضا</b>' : '') .
                        (!empty($sel_fields['f_barcode']) ? '<span style="font-family:monospace;font-weight:800;border:1px solid #000;padding:0 4px">||' . h($code_val) . '||</span>' : '') .
                    '</span>
                </div>
            </div>';
        }
        echo '</div>';

    } else {
        $edit_id = (int)(isset($_GET['edit']) ? $_GET['edit'] : 0);
        $em = array('id'=>0, 'name'=>'', 'father'=>'', 'bdate'=>'', 'exp_date'=>'', 'phone'=>'', 'mcode'=>'', 'u'=>'', 'loc'=>$allowed_locs[0], 'status'=>'active', 'photo'=>'');
        if ($edit_id) {
            $s = $db->prepare("SELECT * FROM members WHERE id=?" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : ""));
            $s->execute(array($edit_id));
            $row_m = $s->fetch(PDO::FETCH_ASSOC);
            if ($row_m) $em = array_merge($em, $row_m);
        }

        $pend_mems = $db->query("SELECT * FROM members WHERE status='pending'" . (is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "") . " ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        if ($pend_mems) {
            echo '<div class="card" style="border:2px solid #f59e0b;background:#fffbeb">
                <h2 style="color:#b45309">درخواست‌های عضویت ثبت‌شده از پرتال (منتظر تایید مدیر کتابخانه: ' . tr_num(count($pend_mems)) . ' نفر)</h2>
                <table><tr><th>نام و نام خانوادگی</th><th>نام پدر</th><th>کد ملی (نام کاربری)</th><th>تلفن</th><th>کتابخانه انتخابی</th><th>عملیات تایید</th></tr>';
                foreach ($pend_mems as $pm) {
                    echo '<tr><td><b>' . h($pm['name']) . '</b></td><td>' . h($pm['father'] ?? '') . '</td><td>' . tr_num(h($pm['mcode'])) . '</td><td>' . tr_num(h($pm['phone'])) . '</td><td><span class="badge">' . h($pm['loc']) . '</span></td>
                    <td>' . post_btn('approve_mem', 'تایید و فعال‌سازی عضویت', array('id'=>$pm['id']), 'btn-sm ok') . ' ' . post_btn('delmem', 'رد و حذف', array('id'=>$pm['id']), 'btn-sm dng') . '</td></tr>';
                }
            echo '</table></div>';
        }

        echo '<div class="grid">
            <div class="card">
                <h2>' . ($em['id'] ? 'ویرایش عضو و بازنشانی نام کاربری / رمز عبور' : 'افزودن عضو جدید') . '</h2>
                <form method="post" action="?p=savemem" enctype="multipart/form-data">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $em['id'] . '"><input type="hidden" name="old_photo" value="' . h($em['photo'] ?? '') . '">
                    <div class="grid">
                        <div><label>نام و نام خانوادگی *</label><input name="name" value="' . h($em['name']) . '" required></div>
                        <div><label>نام پدر</label><input name="father" value="' . h($em['father'] ?? '') . '"></div>
                        <div><label>کد عضویت / کد ملی *</label><input name="mcode" dir="ltr" class="no-num" value="' . h($em['mcode']) . '" required></div>
                        <div><label>نام کاربری ورود عضو</label><input name="u" dir="ltr" class="no-num" value="' . h(($em['u'] ?? '') ?: $em['mcode']) . '" placeholder="پیش‌فرض: همان کد ملی"></div>
                        <div><label style="color:var(--warn)">گذرواژه ورود (تعیین / بازنشانی رمز)</label><input type="password" name="pw" dir="ltr" class="no-num" placeholder="' . ($em['id'] ? 'خالی = حفظ رمز قبلی' : 'پیش‌فرض: همان کد ملی') . '"></div>
                        <div><label>شماره تماس</label><input name="phone" dir="ltr" class="no-num" value="' . h($em['phone']) . '"></div>
                        <div><label>تاریخ تولد (شمسی)</label><input name="bdate" value="' . h($em['bdate'] ?? '') . '" placeholder="1375/01/01"></div>
                        <div><label>تاریخ اعتبار کارت</label><input name="exp_date" value="' . h($em['exp_date'] ?? '') . '" placeholder="1406/12/29"></div>
                        <div><label>کتابخانه عضویت</label><select name="loc">';
                        foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '"' . ($em['loc'] === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
        echo '          </select></div>
                        <div><label>وضعیت عضویت</label><select name="status">
                            <option value="active"' . (($em['status'] ?? 'active') !== 'pending' ? ' selected' : '') . '>فعال و تاییدشده</option>
                            <option value="pending"' . (($em['status'] ?? '') === 'pending' ? ' selected' : '') . '>منتظر تایید</option>
                        </select></div>
                        <div><label>عکس عضو (اختیاری)</label><input type="file" name="photo" accept="image/*"></div>
                    </div><br>
                    <button class="btn pri">' . ($em['id'] ? 'ذخیره تغییرات و بازنشانی رمز' : 'ثبت عضو جدید') . '</button>
                    ' . ($em['id'] ? '<a class="btn" href="?p=mem">انصراف</a>' : '') . '
                </form>
            </div>
            <div class="card">
                <h2>ورودی و خروجی اکسل اعضا و چاپ کارت</h2>
                <p style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="?p=sample_mem">دانلود نمونه اکسل اعضا</a>
                    <a class="btn ok" href="?p=export_excel_mem">خروجی اکسل کل اعضا</a>
                    <a class="btn pri" href="?p=mem&sub=cards">چاپ سفارشی کارت عضویت</a>
                </p>
                <form method="post" action="?p=imp_xl_mem" enctype="multipart/form-data">
                    <input type="hidden" name="t" value="' . $tok . '">
                    <label>کتابخانه اعضای ورودی</label><select name="loc">';
                    foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
        echo '      </select><br><br>
                    <label>انتخاب فایل اکسل (.xlsx, .csv)</label><input type="file" name="f" accept=".xlsx,.csv" required>
                    <p style="font-size:12px;color:var(--mut);margin-top:6px">توجه: فرمت قدیمی <b>.xls</b> پشتیبانی نمی‌شود؛ ابتدا در Excel باز و با فرمت <b>xlsx</b> ذخیره کنید.</p><br>
                    <button class="btn pri">بارگذاری لیست اعضا</button>
                </form>
            </div>
        </div>';

        echo '<div class="card"><h2>فهرست اعضای کتابخانه</h2><table><tr><th>کد عضویت</th><th>نام و نام خانوادگی</th><th>نام کاربری / کد ملی</th><th>کتابخانه</th><th>تلفن</th><th>وضعیت</th><th>عملیات</th></tr>';
        foreach ($db->query("SELECT * FROM members $w_loc ORDER BY id DESC LIMIT 300") as $x) {
            $st_b = (isset($x['status']) && $x['status'] === 'pending')
                ? '<span class="badge" style="background:#fef3c7;color:#92400e">منتظر تایید</span>'
                : '<span class="badge" style="background:#d1fae5;color:#065f46">فعال</span>';
            echo '<tr>
                <td>' . tr_num($x['id']) . '</td>
                <td><b>' . h($x['name']) . '</b></td>
                <td><code>' . h(($x['u'] ?? '') ?: $x['mcode']) . '</code></td>
                <td><span class="badge">' . h($x['loc']) . '</span></td>
                <td>' . tr_num(h($x['phone'])) . '</td>
                <td>' . $st_b . '</td>
                <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
                    ' . ((isset($x['status']) && $x['status'] === 'pending') ? post_btn('approve_mem', 'تایید', array('id'=>$x['id']), 'btn-sm ok') : '') . '
                    <a class="btn-sm ok" href="?p=rec&sel_mid=' . $x['id'] . '">میز امانت</a>
                    <a class="btn-sm" href="?p=mem&sub=cards&ids=' . $x['id'] . '">چاپ کارت</a>
                    <a class="btn-sm pri" href="?p=mem&edit=' . $x['id'] . '">ویرایش / رمز</a>
                    ' . post_btn('delmem', 'حذف', array('id'=>$x['id']), 'btn-sm dng') . '
                </div></td>
            </tr>';
        }
        echo '</table></div>';
    }

// ==================== امانت‌های جاری ====================
} elseif ($p === 'loan') {
    $w_loan = is_loc_restricted() ? " AND r.loc=" . $db->quote(user_loc()) : "";
    echo '<h1>فهرست امانت‌های جاری و درخواست‌های رزرو منابع' . (is_loc_restricted() ? ' (' . h(user_loc()) . ')' : '') . '</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    render_active_reservations_admin('loan');
    echo '<div class="card"><h2>فهرست امانت‌های در جریان</h2><table><tr><th>شماره ثبت</th><th>منبع</th><th>محل</th><th>عضو</th><th>تاریخ امانت</th><th>موعد بازگشت</th><th>تمدید / تغییر مهلت</th><th>عملیات</th></tr>';
    foreach ($db->query("SELECT l.*, r.f200a, r.reg_no, r.loc, m.name FROM loans l JOIN v_items r ON r.id=l.rid JOIN members m ON m.id=l.mid WHERE l.ret IS NULL $w_loan ORDER BY l.due") as $x) {
        echo '<tr><td>' . tr_num(h($x['reg_no'])) . '</td><td><b>' . h($x['f200a']) . '</b></td><td><span class="badge">' . h($x['loc']) . '</span></td><td>' . h($x['name']) . '</td><td>' . jdate($x['d_out']) . '</td>
        <td class="' . ($x['due'] < date('Y-m-d') ? 'warn' : '') . '">' . jdate($x['due']) . '</td>
        <td><form method="post" action="?p=editloan" style="display:flex;gap:6px;max-width:170px">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $x['id'] . '">
            <input type="number" name="add_days" value="7" style="width:65px;padding:4px 8px"><button class="btn-sm">اعمال (روز)</button></form></td>
        <td>' . post_btn('back', 'ثبت بازگشت', array('id' => $x['id']), 'btn-sm ok') . '</td></tr>';
    }
    echo '</table></div>';
}
