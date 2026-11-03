<?php
// پنل کاربری اعضا (My Panel)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

if (!isset($_SESSION['mid'])) {
    header('Location: ?p=home&ltab=mem_in');
    exit;
}
$mid = (int)$_SESSION['mid'];
$st_m = $db->prepare("SELECT * FROM members WHERE id=?");
$st_m->execute(array($mid));
$mem = $st_m->fetch(PDO::FETCH_ASSOC);

if (!$mem || (isset($mem['status']) && $mem['status'] === 'pending')) {
    unset($_SESSION['mid'], $_SESSION['mname']);
    header('Location: ?p=home&mem_msg=pending');
    exit;
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'loans';
$fine_per_day = 1000;

echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
    <div>
        <h1 style="margin:0">پنل کاربری عضو: ' . h($mem['name']) . '</h1>
        <span class="badge">کد عضویت / نام کاربری: ' . tr_num(h($mem['u'] ?: $mem['mcode'])) . '</span>
        <span class="badge" style="background:var(--blue);color:#fff">کتابخانه عضویت: ' . h($mem['loc']) . '</span>
    </div>
    <div style="display:flex;gap:8px">
        <a class="btn" href="?p=home">بازگشت به پرتال</a>
        <a class="btn dng" href="?p=mem_logout">خروج از حساب</a>
    </div>
</div>';

if (!empty($_SESSION['flash'])) {
    echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>';
    unset($_SESSION['flash']);
}

echo '<div class="tabs">
    <a class="' . ($tab === 'loans' ? 'on' : '') . '" href="?p=my_panel&tab=loans">تب امانات من و جریمه‌ها</a>
    <a class="' . ($tab === 'reservations' ? 'on' : '') . '" href="?p=my_panel&tab=reservations">تب رزرو منابع</a>
    <a class="' . ($tab === 'tickets' ? 'on' : '') . '" href="?p=my_panel&tab=tickets">ارسال تیکت به مدیریت کتابخانه</a>
    <a class="' . ($tab === 'profile' ? 'on' : '') . '" href="?p=my_panel&tab=profile">ویرایش اطلاعات و تغییر رمز عبور</a>
</div>';

if ($tab === 'loans') {
    $st_l = $db->prepare("SELECT l.*, r.f200a, r.f700, r.reg_no, r.loc FROM loans l JOIN v_items r ON r.id=l.rid WHERE l.mid=? ORDER BY l.id DESC");
    $st_l->execute(array($mid));
    $loans = $st_l->fetchAll(PDO::FETCH_ASSOC);
    $total_fine = 0;

    echo '<div class="card"><h2>فهرست امانات من (مشاهده تاریخ تحویل، موعد بازگشت و جریمه‌ها)</h2>
    <table><tr><th>شماره ثبت</th><th>عنوان منبع</th><th>کتابخانه</th><th>تاریخ امانت</th><th>موعد بازگشت</th><th>وضعیت</th><th>دیرکرد / جریمه</th></tr>';
    if ($loans) {
        foreach ($loans as $x) {
            $ref_date = !empty($x['ret']) ? $x['ret'] : date('Y-m-d');
            $days_late = 0;
            if ($ref_date > $x['due']) {
                $days_late = max(0, (int)round((strtotime($ref_date) - strtotime($x['due'])) / 86400));
            }
            $fine = $days_late * $fine_per_day;
            if (empty($x['ret'])) $total_fine += $fine;

            if (!empty($x['ret'])) {
                $st_badge = '<span class="badge" style="background:#d1fae5;color:#065f46">بازگشت داده شده در ' . jdate($x['ret']) . '</span>';
            } elseif ($days_late > 0) {
                $st_badge = '<span class="badge" style="background:#ffe4e6;color:var(--warn)">تأخیر (' . tr_num($days_late) . ' روز)</span>';
            } else {
                $st_badge = '<span class="badge" style="background:#fef3c7;color:#92400e">در امانت</span>';
            }

            echo '<tr>
                <td><b>' . tr_num(h($x['reg_no'])) . '</b></td>
                <td><b>' . h($x['f200a']) . '</b></td>
                <td><span class="badge">' . h($x['loc']) . '</span></td>
                <td>' . jdate($x['d_out']) . '</td>
                <td>' . jdate($x['due']) . '</td>
                <td>' . $st_badge . '</td>
                <td>' . ($fine > 0 ? '<b class="warn">' . tr_num(number_format($fine)) . ' تومان</b>' : '<span style="color:var(--ok)">بدون جریمه</span>') . '</td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center">هیچ سابقه امانتی برای شما ثبت نشده است.</td></tr>';
    }
    echo '</table>';
    if ($total_fine > 0) {
        echo '<p class="warn" style="margin-top:12px">مجموع جریمه امانات جاری دارای تأخیر: <b>' . tr_num(number_format($total_fine)) . ' تومان</b></p>';
    }
    echo '</div>';

} elseif ($tab === 'reservations') {
    $rq = trim(isset($_GET['rq']) ? $_GET['rq'] : '');
    echo '<div class="card"><h2>جستجو و ثبت رزرو منبع</h2>
        <form method="get" class="row">
            <input type="hidden" name="p" value="my_panel"><input type="hidden" name="tab" value="reservations">
            <div style="flex:1"><label>عنوان کتاب یا نویسنده جهت رزرو</label><input name="rq" value="' . h($rq) . '" placeholder="عبارت مورد نظر را وارد کنید..."></div>
            <button class="btn pri">جستجوی منبع</button>
        </form>';
    if ($rq !== '') {
        $st_s = $db->prepare("SELECT * FROM records WHERE f200a LIKE :q OR f700 LIKE :q OR f606 LIKE :q ORDER BY id DESC LIMIT 20");
        $st_s->execute(array(':q' => "%$rq%"));
        $srows = $st_s->fetchAll(PDO::FETCH_ASSOC);
        if ($srows) {
            echo '<table style="margin-top:12px"><tr><th>عنوان منبع</th><th>پدیدآور</th><th>ناشر</th><th>انتخاب کتابخانه و ثبت رزرو</th></tr>';
            foreach ($srows as $sr) {
                echo '<tr><td><b>' . h($sr['f200a']) . '</b></td><td>' . h($sr['f700'] ?: $sr['f200f']) . '</td><td>' . h($sr['f210c']) . '</td>
                <td><form method="post" action="?p=reserve_book" style="display:flex;gap:6px">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="rid" value="' . $sr['id'] . '">
                    <select name="loc" style="width:160px;padding:4px 8px">';
                    foreach (get_locations() as $lc) echo '<option value="' . h($lc) . '"' . ($mem['loc'] === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
                echo '</select><button class="btn-sm ok">رزرو منبع</button></form></td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="warn" style="margin-top:10px">منبعی یافت نشد.</p>';
        }
    }
    echo '</div>';

    $db->exec("DELETE FROM reservations WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP");
    $st_r = $db->prepare("SELECT res.*, r.f200a FROM reservations res JOIN records r ON r.id=res.rid WHERE res.mid=? AND (res.expires_at IS NULL OR res.expires_at > CURRENT_TIMESTAMP) ORDER BY res.id DESC");
    $st_r->execute(array($mid));
    $rrows = $st_r->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><h2><span>فهرست منابع رزروشده من</span><span class="badge" style="background:#fef3c7;color:#92400e">مهلت اعتبار هر رزرو: ۴۸ ساعت پس از ثبت</span></h2>
    <table><tr><th>تاریخ ثبت</th><th>مهلت مراجعه (۴۸ ساعت)</th><th>عنوان منبع</th><th>کتابخانه</th><th>وضعیت</th><th>عملیات</th></tr>';
    if ($rrows) {
        foreach ($rrows as $x) {
            $exp_str = !empty($x['expires_at']) ? $x['expires_at'] : date('Y-m-d H:i:s', strtotime($x['created']) + 48 * 3600);
            $rem_hrs = max(0, round((strtotime($exp_str) - time()) / 3600, 1));
            echo '<tr><td>' . jdate($x['created'], true) . '</td>
            <td><b>' . jdate($exp_str, true) . '</b> <span class="badge">(' . tr_num($rem_hrs) . ' ساعت مانده)</span></td>
            <td><b>' . h($x['f200a']) . '</b></td><td><span class="badge">' . h($x['loc']) . '</span></td>
            <td><span class="badge" style="background:#d1fae5;color:#065f46">فعال (در انتظار مراجعه به کتابخانه)</span></td>
            <td>' . post_btn('cancel_res', 'لغو رزرو', array('id' => $x['id']), 'btn-sm dng') . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align:center">هیچ رزرو فعالی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';

} elseif ($tab === 'tickets') {
    echo '<div class="card"><h2>ارسال تیکت مستقیم برای مدیریت کتابخانه (مدیر مرکزی)</h2>
        <form method="post" action="?p=mem_ticket"><input type="hidden" name="t" value="' . $tok . '">
            <div class="grid">
                <div><label>موضوع تیکت *</label><input name="subj" required placeholder="موضوع پیام یا درخواست..."></div>
                <div><label>گیرنده</label><input value="مدیریت مرکزی کتابخانه (' . h($mem['loc']) . ')" disabled></div>
            </div><br>
            <label>متن پیام *</label><textarea name="msg" rows="4" required placeholder="پیام خود را بنویسید..."></textarea><br><br>
            <button class="btn pri">ارسال تیکت به مدیریت</button>
        </form>
    </div>';

    $sender_key = 'عضو: ' . $mem['name'] . ' (#' . $mid . ')';
    $st_tk = $db->prepare("SELECT * FROM tickets WHERE sender=? OR sender=? ORDER BY id DESC");
    $st_tk->execute(array('M-' . $mid, $sender_key));
    $tks = $st_tk->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><h2>سوابق تیکت‌ها و پاسخ مدیریت</h2><table><tr><th>تاریخ</th><th>موضوع و متن پیام</th><th>وضعیت و پاسخ مدیریت</th></tr>';
    if ($tks) {
        foreach ($tks as $tk) {
            echo '<tr><td>' . jdate($tk['created']) . '</td><td><b>' . h($tk['subj']) . '</b><br><span style="color:#444">' . nl2br(h($tk['msg'])) . '</span></td>
            <td>' . ($tk['reply'] ? '<div style="background:#d1fae5;color:#065f46;padding:8px 12px;border-radius:10px"><b>پاسخ مدیریت:</b><br>' . nl2br(h($tk['reply'])) . '</div>' : '<span class="badge" style="background:#fef3c7;color:#92400e">در انتظار پاسخ</span>') . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="3" style="text-align:center">هیچ تیکتی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';

} elseif ($tab === 'profile') {
    echo '<div class="card"><h2>ویرایش اطلاعات کاربری و تغییر رمز عبور</h2>
        <form method="post" action="?p=mem_profile"><input type="hidden" name="t" value="' . $tok . '">
            <div class="grid">
                <div><label>نام و نام خانوادگی *</label><input name="name" value="' . h($mem['name']) . '" required></div>
                <div><label>نام پدر</label><input name="father" value="' . h($mem['father'] ?? '') . '"></div>
                <div><label>کد ملی / نام کاربری</label><input value="' . h($mem['u'] ?: $mem['mcode']) . '" disabled dir="ltr"></div>
                <div><label>شماره تماس *</label><input name="phone" value="' . h($mem['phone']) . '" dir="ltr" class="no-num" required></div>
                <div><label>تاریخ تولد (شمسی)</label><input name="bdate" value="' . h($mem['bdate'] ?? '') . '"></div>
                <div><label>گذرواژه جدید (در صورت تمایل به تغییر رمز وارد کنید)</label><input type="password" name="pw" dir="ltr" class="no-num" placeholder="خالی = بدون تغییر رمز"></div>
            </div><br>
            <button class="btn ok">ذخیره تغییرات و بروزرسانی</button>
        </form>
    </div>';
}
