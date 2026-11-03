<?php
// ماژول پرتال عمومی: صفحه اول (home)، نمایش مشخصات منبع در اپک (res) و آرشیو اخبار (news_list)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

if ($p === 'home') {
    $site_title = get_cms('site_title');
    $site_sub = get_cms('site_sub');
    if (isset($_GET['timeout'])) echo '<div class="ok-msg" style="background:#fff7ed;color:#9a3412;border-color:#fdba74">نشست شما به‌دلیل ۱۰ دقیقه عدم فعالیت پایان یافت؛ برای ادامه دوباره وارد شوید.</div>';
    if (isset($_GET['order_ok'])) {
        echo '<div class="ok-msg">درخواست سفارش منبع شما با موفقیت ثبت و به کتابخانه مربوطه ارسال شد.</div>';
    }
    if (get_cms('slider_active') !== '0') {
        $slides = array();
        for ($si = 1; $si <= 3; $si++) {
            $st_t = trim(get_cms('slider_title' . $si));
            $st_s = trim(get_cms('slider_sub' . $si));
            $st_i = trim(get_cms('slider_img' . $si));
            $st_l = trim(get_cms('slider_link' . $si));
            if ($st_t === '' && $si === 1) $st_t = $site_title;
            if ($st_s === '' && $si === 1) $st_s = $site_sub;
            if ($st_t !== '' || $st_i !== '') {
                $slides[] = array('title' => $st_t, 'sub' => $st_s, 'img' => $st_i, 'link' => $st_l);
            }
        }
        if ($slides) {
            echo '<div class="wide-slider" id="aseman_wide_slider">';
            foreach ($slides as $idx => $sl) {
                $bg_style = $sl['img'] !== '' ? "background-image:url('" . h($sl['img']) . "');" : "background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,var(--blue) 100%);";
                echo '<div class="wide-slide' . ($idx === 0 ? ' active' : '') . '" style="' . $bg_style . '">
                    <div class="wide-slide-overlay"></div>
                    <div class="wide-slide-content">
                        <h2>' . h($sl['title']) . '</h2>
                        ' . ($sl['sub'] !== '' ? '<p>' . h($sl['sub']) . '</p>' : '') . '
                        ' . ($sl['link'] !== '' ? '<a class="btn ok" href="' . h($sl['link']) . '" style="border:1px solid var(--gold)">مشاهده و دسترسی سریع</a>' : '') . '
                    </div>
                </div>';
            }
            if (count($slides) > 1) {
                echo '<button type="button" class="slider-nav-btn" style="right:14px" onclick="asemanSlideMove(-1)" title="قبلی">&#10094;</button>
                      <button type="button" class="slider-nav-btn" style="left:14px" onclick="asemanSlideMove(1)" title="بعدی">&#10095;</button>
                      <div class="slider-dots">';
                foreach ($slides as $idx => $sl) {
                    echo '<button type="button" class="slider-dot' . ($idx === 0 ? ' active' : '') . '" onclick="asemanSlideTo(' . $idx . ')"></button>';
                }
                echo '</div>
                <script>
                (function(){
                    var cur = 0, slides = document.querySelectorAll("#aseman_wide_slider .wide-slide"), dots = document.querySelectorAll("#aseman_wide_slider .slider-dot"), tmr = null;
                    if (slides.length < 2) return;
                    window.asemanSlideTo = function(n) {
                        slides[cur].classList.remove("active");
                        if (dots[cur]) dots[cur].classList.remove("active");
                        cur = (n + slides.length) % slides.length;
                        slides[cur].classList.add("active");
                        if (dots[cur]) dots[cur].classList.add("active");
                    };
                    window.asemanSlideMove = function(d) {
                        window.asemanSlideTo(cur + d);
                        resetTmr();
                    };
                    function resetTmr() {
                        if (tmr) clearInterval(tmr);
                        tmr = setInterval(function(){ window.asemanSlideTo(cur + 1); }, 5500);
                    }
                    resetTmr();
                })();
                </script>';
            }
            echo '</div>';
        }
    }

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
} elseif ($p === 'res') {
    $rid = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $sr = $db->prepare("SELECT * FROM records WHERE id=?");
    $sr->execute(array($rid));
    $r = $sr->fetch(PDO::FETCH_ASSOC);
    echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px"><h1 style="margin:0">مشخصات منبع</h1><a class="btn pri" href="?p=home">بازگشت به پرتال</a></div>';
    if (!$r) {
        echo '<div class="card"><p class="warn">منبع پیدا نشد.</p></div>';
    } else {
        echo '<div class="card" style="border:2px solid var(--blue)"><h2>' . h($r['f200a']) . ' <span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></h2><table class="marc">';
        foreach (array('f200e','f200f','f700','f710','f205','f210a','f210c','f210d','f215','f225','f606','f676','f686','f010','f300') as $c) {
            if ($r[$c] !== null && $r[$c] !== '') echo '<tr><td>' . h($F[$c]) . '</td><td>' . h($r[$c]) . '</td></tr>';
        }
        echo '</table>';
        if (!empty($r['file'])) {
            $facc = $r['file_access'] ?? 'public';
            if ($facc === 'public' || ($facc === 'member' && (isset($_SESSION['u']) || isset($_SESSION['mid']))) || ($facc === 'private' && isset($_SESSION['u']))) {
                echo '<p><a class="btn ok" href="' . h($r['file']) . '" target="_blank">دریافت فایل پیوست</a></p>';
            } else {
                echo '<p><span class="badge" style="background:#fef3c7;color:#92400e">فایل پیوست: ' . ($facc === 'member' ? 'فقط ویژه اعضا' : 'محرمانه') . ' (نیازمند ورود)</span></p>';
            }
        }
        echo '</div><div class="card"><h2>کتابخانه‌ها و موجودی</h2><table><tr><th>کتابخانه</th><th>تعداد مجلد</th><th>موجود روی قفسه</th></tr>';
        $hd = $db->prepare("SELECT loc, COUNT(*) AS c, SUM(CASE WHEN EXISTS (SELECT 1 FROM loans l WHERE l.rid=items.id AND l.ret IS NULL) THEN 0 ELSE 1 END) AS av FROM items WHERE rid=? GROUP BY loc ORDER BY loc");
        $hd->execute(array($rid));
        $any = false;
        foreach ($hd->fetchAll(PDO::FETCH_ASSOC) as $x) { $any = true; echo '<tr><td><b>' . h($x['loc']) . '</b></td><td>' . tr_num($x['c']) . '</td><td>' . ($x['av'] > 0 ? '<span style="color:var(--ok);font-weight:700">' . tr_num($x['av']) . ' موجود</span>' : '<span class="warn">در امانت</span>') . '</td></tr>'; }
        if (!$any) echo '<tr><td colspan="3" class="warn">این منبع در حال حاضر موجودی ندارد.</td></tr>';
        echo '</table></div>';
    }

} elseif ($p === 'news_list') {
    $sel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h1 style="margin:0">آرشیو و مشروح اخبار و اطلاعیه‌های کتابخانه</h1>
            <a class="btn pri" href="?p=home">بازگشت به پرتال</a>
          </div>';
    if ($sel_id > 0) {
        $sn = $db->prepare("SELECT * FROM news WHERE id=? AND active=1");
        $sn->execute(array($sel_id));
        $nw = $sn->fetch(PDO::FETCH_ASSOC);
        if ($nw) {
            $dt = ($nw['pub'] !== null && $nw['pub'] !== '') ? $nw['pub'] : jdate($nw['created']);
            echo '<div class="card" style="border:2px solid var(--blue)">
                <h2><span>' . h($nw['title']) . '</span><span class="badge">تاریخ انتشار: ' . h(tr_num($dt)) . '</span></h2>' .
                ($nw['subtitle'] ? '<p style="font-size:14px;font-weight:700;color:var(--blue);margin:0 0 12px">' . h($nw['subtitle']) . '</p>' : '') .
                ($nw['img'] ? '<img src="' . h($nw['img']) . '" alt="" style="max-width:100%;max-height:340px;object-fit:cover;border-radius:16px;margin-bottom:14px">' : '') .
                '<div style="font-size:14px;line-height:2;text-align:justify">' . clean_html($nw['body']) . '</div>
                <br><a class="btn pri" href="?p=news_list">مشاهده همه اخبار</a> <a class="btn" href="?p=home">بازگشت به صفحه اول پرتال</a>
            </div>';
        }
    }
    echo '<div class="card"><h2>فهرست کامل اخبار و اطلاعیه‌ها</h2>';
    foreach ($db->query("SELECT * FROM news WHERE active=1 ORDER BY id DESC") as $nw) {
        $dt = ($nw['pub'] !== null && $nw['pub'] !== '') ? $nw['pub'] : jdate($nw['created']);
        echo '<a class="news-item" href="?p=news_list&id=' . $nw['id'] . '">' . ($nw['img'] ? '<img class="news-thumb" src="' . h($nw['img']) . '" alt="">' : '<span class="news-thumb ph">خبر</span>') .
             '<span class="news-txt"><b>' . h($nw['title']) . '</b>' . ($nw['subtitle'] ? '<small>' . h($nw['subtitle']) . '</small>' : '') . '<em class="badge">' . h(tr_num($dt)) . '</em></span></a>';
    }
    echo '</div>';

} elseif ($p === 'mem_login') {
    echo '<div class="card" style="max-width:400px;margin:30px auto;border-top:4px solid var(--blue)">
        <h2 style="text-align:center;margin-bottom:20px">ورود اعضای کتابخانه</h2>';
    if (isset($_GET['err'])) echo '<div class="warn" style="margin-bottom:12px;text-align:center">نام کاربری یا گذرواژه نادرست است.</div>';
    if (isset($_GET['reg'])) echo '<div class="ok-msg" style="text-align:center">ثبت‌نام با موفقیت انجام شد. اکنون وارد شوید.</div>';
    echo '<form method="post" action="?p=do_mem_login"><input type="hidden" name="t" value="' . $tok . '">
        <label>کد عضویت یا نام کاربری</label><input name="u" required dir="ltr"><br><br>
        <label>گذرواژه</label><input type="password" name="pw" required dir="ltr"><br><br>
        <button class="btn pri" style="width:100%">ورود به پروفایل کاربری</button>
        <div style="margin-top:16px;text-align:center;font-size:13px"><a href="?p=mem_register" style="color:var(--blue);text-decoration:none">عضو نیستید؟ ثبت‌نام کنید</a></div>
    </form></div>';

} elseif ($p === 'mem_register') {
    echo '<div class="card" style="max-width:500px;margin:30px auto"><h2>ثبت‌نام در کتابخانه</h2>';
    if (isset($_GET['err'])) echo '<div class="warn" style="margin-bottom:12px">کد ملی / کاربری تکراری است.</div>';
    echo '<form method="post" action="?p=do_mem_register"><input type="hidden" name="t" value="' . $tok . '">
        <div class="grid">
            <div><label>نام و نام خانوادگی *</label><input name="name" required></div>
            <div><label>کد ملی (نام کاربری) *</label><input name="mcode" dir="ltr" required></div>
            <div><label>شماره تماس</label><input name="phone" dir="ltr"></div>
            <div><label>انتخاب کتابخانه عضویت</label><select name="loc">';
            foreach (get_locations() as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
    echo '  </select></div>
            <div><label>گذرواژه *</label><input type="password" name="pw" dir="ltr" required></div>
        </div><br><button class="btn ok">ثبت‌نام</button></form></div>';
} elseif ($p === 'page') {
    $slug = trim($_GET['slug'] ?? '');
    $box_id = (int)($_GET['box_id'] ?? 0);
    echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h1 style="margin:0">صفحه اختصاصی پرتال</h1>
            <a class="btn pri" href="?p=home">بازگشت به صفحه اصلی پرتال</a>
          </div>';
    if ($box_id > 0) {
        $st_b = $db->prepare("SELECT * FROM portal_boxes WHERE id=?");
        $st_b->execute(array($box_id));
        $bx = $st_b->fetch(PDO::FETCH_ASSOC);
        if ($bx) {
            render_portal_box($bx);
        } else {
            echo '<div class="card"><p class="warn">باکس مورد نظر یافت نشد.</p></div>';
        }
    } else {
        $st_pg = $db->prepare("SELECT * FROM pages WHERE slug=? AND active=1 LIMIT 1");
        $st_pg->execute(array($slug));
        $pg_row = $st_pg->fetch(PDO::FETCH_ASSOC);
        if (!$pg_row && is_numeric($slug)) {
            $st_pg2 = $db->prepare("SELECT * FROM pages WHERE id=? AND active=1 LIMIT 1");
            $st_pg2->execute(array((int)$slug));
            $pg_row = $st_pg2->fetch(PDO::FETCH_ASSOC);
        }
        if ($pg_row) {
            echo '<div class="card" style="border-top:4px solid var(--gold)">
                <h2><span>' . h($pg_row['title']) . '</span><span class="badge">تاریخ بروزرسانی: ' . jdate($pg_row['created']) . '</span></h2>
                <div style="font-size:14px;line-height:2.1;text-align:justify">' . clean_html($pg_row['body']) . '</div>
            </div>';
        } else {
            echo '<div class="card"><p class="warn">صفحه مورد نظر یافت نشد یا غیرفعال شده است.</p></div>';
        }
    }
}
