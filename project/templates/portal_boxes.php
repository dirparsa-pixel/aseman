<?php
// رندر باکس‌های صفحه اول پرتال (جستجوی OPAC، اخبار، نمودار، آمار، گالری و ورود)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

function render_portal_box($bx) {
    global $db, $RTypes, $Locations, $tok, $in, $err;
    $bkey = $bx['bkey'];
    $btitle = $bx['title'];

    if ($bx['btype'] === 'custom') {
        echo '<div class="card"><h2>' . h($btitle) . '</h2><div style="font-size:13.5px;line-height:1.8">' . $bx['content'] . '</div></div>';
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
                <a class="' . ($smode === 'simple' ? 'on' : '') . '" href="?p=home&smode=simple">جستجوی ساده منابع</a>
                <a class="' . ($smode === 'adv' ? 'on' : '') . '" href="?p=home&smode=adv">جستجوی پیشرفته</a>
                <a class="' . ($smode === 'order' ? 'on' : '') . '" href="?p=home&smode=order">سفارش و پیشنهاد خرید منبع</a>
            </div>';
        if ($smode === 'simple') {
            echo '<form method="get" class="row"><input type="hidden" name="p" value="home"><input type="hidden" name="smode" value="simple">
                  <div style="flex:1"><label>کلیدواژه (عنوان، نویسنده، موضوع، ناشر، شابک یا شماره ثبت)</label><input name="sq" value="' . h($sq) . '" placeholder="عبارت مورد نظر را وارد نمایید..."></div>
                  <div style="width:150px"><label>کتابخانه</label><select name="sloc"><option value="">همه کتابخانه‌ها</option>';
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
                  <div><label>کتابخانه</label><select name="sloc"><option value="">همه کتابخانه‌ها</option>';
                  foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($sloc === $lc ? ' selected' : '') . '>' . h($lc) . '</option>';
            echo '</select></div><div><label>نوع منبع</label><select name="srtype"><option value="">همه</option>';
                  foreach ($RTypes as $k => $v) echo '<option value="' . $k . '"' . ($srtype === $k ? ' selected' : '') . '>' . $v . '</option>';
            echo '</select></div></div><br><button class="btn pri">اجرای جستجوی پیشرفته</button> <a class="btn" href="?p=home&smode=adv">پاکسازی</a></form>';
        } else {
            echo '<form method="post" action="?p=add_order"><input type="hidden" name="t" value="' . $tok . '">
                  <p style="margin:0 0 12px;font-size:13px">چنانچه کتاب یا منبعی را در مخزن نیافتید، مشخصات آن را وارد کرده و <b>کتابخانه</b> مورد نظر خود را جهت تهیه انتخاب نمایید:</p>
                  <div class="grid">
                      <div><label>عنوان کتاب / منبع *</label><input name="title" required placeholder="نام دقیق کتاب..."></div>
                      <div><label>نویسنده / پدیدآور</label><input name="author" placeholder="نام نویسنده یا مترجم..."></div>
                      <div><label>ناشر / سال نشر</label><input name="pub"></div>
                      <div><label>شابک (ISBN)</label><input name="isbn"></div>
                      <div><label style="color:var(--blue)">انتخاب کتابخانه جهت ارسال سفارش *</label><select name="loc" required>';
                      foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
            echo '    </select></div>
                      <div><label>نام درخواست‌کننده *</label><input name="requester" required placeholder="نام و نام خانوادگی شما"></div>
                      <div><label>شماره تماس (جهت اطلاع‌رسانی)</label><input name="phone" placeholder="09..."></div>
                  </div><br><button class="btn ok">ثبت و ارسال سفارش منبع</button></form>';
        }

        $w = array(); $par = array();
        if ($smode === 'simple' && ($sq !== '' || $sloc !== '' || $srtype !== '')) {
            if ($sq !== '') {
                $fc = title_cond($sq, $par, 'r');
                if ($fc !== null) {
                    $w[] = "($fc OR EXISTS (SELECT 1 FROM items ix WHERE ix.rid=r.id AND ix.reg_no LIKE :qr))";
                    $par[':qr'] = "%$sq%";
                } else {
                    $w[] = "(r.f200a LIKE :q OR r.f700 LIKE :q OR r.f200f LIKE :q OR r.f606 LIKE :q OR r.f210c LIKE :q OR r.f010 LIKE :q OR EXISTS (SELECT 1 FROM items ix WHERE ix.rid=r.id AND ix.reg_no LIKE :q))";
                    $par[':q'] = "%$sq%";
                }
            }
            if ($sloc !== '') { $w[] = "EXISTS (SELECT 1 FROM items i3 WHERE i3.rid=r.id AND i3.loc = :l)"; $par[':l'] = $sloc; }
        } elseif ($smode === 'adv') {
            if ($stitle !== '') { $w[] = "r.f200a LIKE :t"; $par[':t'] = "%$stitle%"; }
            if ($sauthor !== '') { $w[] = "(r.f700 LIKE :a OR r.f200f LIKE :a)"; $par[':a'] = "%$sauthor%"; }
            if ($ssubj !== '') { $w[] = "r.f606 LIKE :s"; $par[':s'] = "%$ssubj%"; }
            if ($spub !== '') { $w[] = "r.f210c LIKE :p"; $par[':p'] = "%$spub%"; }
            if ($sisbn !== '') { $w[] = "r.f010 LIKE :i"; $par[':i'] = "%$sisbn%"; }
            if ($sloc !== '') { $w[] = "EXISTS (SELECT 1 FROM items i3 WHERE i3.rid=r.id AND i3.loc = :l)"; $par[':l'] = $sloc; }
        }
        if ($srtype !== '' && $smode !== 'order') { $w[] = "r.rtype = :rt"; $par[':rt'] = $srtype; }

        if ($w) {
            $sql = "SELECT r.* FROM records r WHERE " . implode(' AND ', $w) . " ORDER BY r.id DESC LIMIT 40";
            $st = $db->prepare($sql); $st->execute($par); $res = $st->fetchAll(PDO::FETCH_ASSOC);
            $hd = $db->prepare("SELECT loc, COUNT(*) AS c, SUM(CASE WHEN EXISTS (SELECT 1 FROM loans l WHERE l.rid=items.id AND l.ret IS NULL) THEN 0 ELSE 1 END) AS av FROM items WHERE rid=? GROUP BY loc ORDER BY loc");
            echo '<div style="margin-top:18px;padding-top:14px;border-top:2px dashed var(--line)"><h3>نتایج جستجو (' . tr_num(count($res)) . ' عنوان):</h3>';
            if ($res) {
                echo '<table><tr><th>عنوان</th><th>پدیدآور</th><th>ناشر / سال</th><th>کتابخانه و موجودی</th><th>رده</th><th>پیوست</th></tr>';
                foreach ($res as $r) {
                    $hd->execute(array($r['id']));
                    $hrows = $hd->fetchAll(PDO::FETCH_ASSOC);
                    echo '<tr><td><a href="?p=res&id=' . $r['id'] . '"><b>' . h($r['f200a']) . '</b></a> <span class="badge">' . (isset($RTypes[$r['rtype']]) ? $RTypes[$r['rtype']] : 'کتاب') . '</span></td>
                    <td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td><td>' . h($r['f210c']) . ' (' . tr_num(h($r['f210d'])) . ')</td><td>';
                    if ($hrows) {
                        foreach ($hrows as $x) {
                            echo '<div><span class="badge">' . h($x['loc']) . '</span> ' . ($x['av'] > 0 ? '<span style="color:var(--ok);font-weight:700">موجود (' . tr_num($x['av']) . ' از ' . tr_num($x['c']) . ')</span>' : '<span class="warn">امانت</span>') . '</div>';
                        }
                    } else echo '<span class="warn">بدون موجودی</span>';
                    echo '</td><td>' . h($r['f676'] ? $r['f676'] : $r['f686']) . '</td>
                    <td>' . (!empty($r['file']) ? '<a class="btn-sm" href="' . h($r['file']) . '" target="_blank">فایل</a>' : '-') . '</td></tr>';
                }
                echo '</table>';
            } else echo '<p class="warn">هیچ منبعی یافت نشد. می‌توانید از تب «سفارش و پیشنهاد خرید منبع» درخواست تهیه آن را ثبت کنید.</p>';
            echo '</div>';
        }
        echo '</div>';

    } elseif ($bkey === 'news') {
        echo '<div class="card"><h2><span>' . h($btitle) . '</span><a class="btn-sm pri" href="?p=news_list">آرشیو و مشروح اخبار</a></h2><div>';
        foreach ($db->query("SELECT * FROM news WHERE active=1 ORDER BY id DESC LIMIT 8") as $nw) {
            $dt = ($nw['pub'] !== null && $nw['pub'] !== '') ? $nw['pub'] : jdate($nw['created']);
            echo '<a class="news-item" href="?p=news_list&id=' . $nw['id'] . '">' . ($nw['img'] ? '<img class="news-thumb" src="' . h($nw['img']) . '" alt="">' : '<span class="news-thumb ph">خبر</span>') .
                 '<span class="news-txt"><b>' . h($nw['title']) . '</b>' . ($nw['subtitle'] ? '<small>' . h($nw['subtitle']) . '</small>' : '') . '<em class="badge">' . h(tr_num($dt)) . '</em></span></a>';
        }
        echo '</div></div>';

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
                echo '<div class="card"><h2>' . h($btitle) . '</h2><div class="chart-wrap">';
                foreach ($g_data as $gd) {
                    $h_px = max(8, round(($gd['v'] / $max_v) * 135));
                    echo '<div class="chart-col"><span style="font-size:11px;font-weight:700;color:var(--blue)">' . tr_num($gd['v']) . '</span>
                          <div class="chart-bar" style="height:' . $h_px . 'px" title="' . h($gd['m']) . ': ' . tr_num($gd['v']) . '"></div>
                          <span style="font-size:11px;font-weight:600">' . h($gd['m']) . '</span></div>';
                }
                echo '</div></div>';
            }
        }

    } elseif ($bkey === 'services_new') {
        echo '<div class="grid">
            <div class="card"><h2>خدمات کتابخانه</h2><ul class="list-clean">';
            foreach (array_filter(explode("\n", get_cms('services'))) as $srv) {
                echo '<li><span>' . h(trim($srv)) . '</span></li>';
            }
        echo '</ul></div>
            <div class="card"><h2>تازه‌های کتابخانه</h2><ul class="list-clean">';
            foreach ($db->query("SELECT r.id, r.f200a, r.f700, (SELECT GROUP_CONCAT(DISTINCT loc) FROM items WHERE rid=r.id) AS loc FROM records r ORDER BY r.id DESC LIMIT 5") as $nb) {
                echo '<li style="cursor:pointer" title="مشاهده مشخصات منبع در OPAC" onclick="location.href=\'?p=res&id=' . (int)$nb['id'] . '\'"><span><b>' . h($nb['f200a']) . '</b> (' . h($nb['f700']) . ')</span><span class="badge">' . h(str_replace(',', '، ', $nb['loc'])) . '</span></li>';
            }
        echo '</ul><p style="font-size:11.5px;margin:8px 0 0;opacity:.75">برای مشاهده مشخصات کامل هر منبع، روی آن کلیک کنید.</p></div></div>';

    } elseif ($bkey === 'about_contact') {
        echo '<div class="grid">
            <div class="card"><h2>درباره ما</h2><div style="font-size:13.5px;line-height:1.8">' . get_cms('about') . '</div></div>
            <div class="card"><h2>تماس با ما</h2><div style="font-size:13.5px;line-height:1.8">' . get_cms('contact') . '</div></div>
        </div>';

    } elseif ($bkey === 'login') {
        $ltab = isset($_GET['ltab']) ? $_GET['ltab'] : 'mem_in';
        echo '<div class="card" style="border-top:4px solid var(--blue)"><h2>ورود اعضا و کتابداران</h2>';
        if (isset($_SESSION['mid'])) {
            echo '<div class="ok-msg" style="margin-bottom:10px;padding:10px">عضو محترم: <b>' . h($_SESSION['mname'] ?? '') . '</b></div>
                  <a class="btn pri" style="width:100%;margin-bottom:8px" href="?p=my_panel">ورود به پنل کاربری من (My Panel)</a>
                  <a class="btn dng" style="width:100%" href="?p=mem_logout">خروج از حساب کاربری</a>';
        } elseif ($in) {
            echo '<p style="font-size:13.5px">شما با نام کاربری <b>' . h($_SESSION['u']) . '</b> وارد شده‌اید.</p>
                  <a class="btn pri" style="width:100%;margin-bottom:8px" href="?p=dash">ورود به پنل مدیریت</a>
                  <a class="btn dng" style="width:100%" href="?p=logout">خروج از حساب</a>';
        } else {
            if (!empty($err)) echo '<p class="warn" style="font-size:12.5px">' . h($err) . '</p>';
            if (isset($_GET['mem_msg'])) {
                if ($_GET['mem_msg'] === 'reg_pending') echo '<div class="ok-msg" style="font-size:12.5px;padding:10px">ثبت‌نام شما انجام شد. پس از تایید توسط مدیر کتابخانه انتخابی می‌توانید وارد شوید.</div>';
                elseif ($_GET['mem_msg'] === 'pending') echo '<div class="warn" style="font-size:12.5px;margin-bottom:10px">عضویت شما هنوز توسط مدیر کتابخانه تایید نشده است.</div>';
                elseif ($_GET['mem_msg'] === 'err') echo '<div class="warn" style="font-size:12.5px;margin-bottom:10px">کد ملی/نام کاربری یا گذرواژه نادرست است.</div>';
                elseif ($_GET['mem_msg'] === 'dup') echo '<div class="warn" style="font-size:12.5px;margin-bottom:10px">این کد ملی قبلاً ثبت‌نام شده است.</div>';
            }
            echo '<div class="tabs" style="margin-bottom:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:4px">
                <a id="tb_m_in" class="' . ($ltab === 'mem_in' ? 'on' : '') . '" href="javascript:void(0)" onclick="swLoginBox(\'mem_in\')" style="padding:6px 4px;font-size:11.5px">ورود اعضا</a>
                <a id="tb_m_reg" class="' . ($ltab === 'mem_reg' ? 'on' : '') . '" href="javascript:void(0)" onclick="swLoginBox(\'mem_reg\')" style="padding:6px 4px;font-size:11.5px">ثبت‌نام</a>
                <a id="tb_st_in" class="' . ($ltab === 'staff' ? 'on' : '') . '" href="javascript:void(0)" onclick="swLoginBox(\'staff\')" style="padding:6px 4px;font-size:11.5px">کتابداران</a>
            </div>';

            echo '<form id="frm_mem_in" method="post" action="?p=do_mem_login" style="' . ($ltab === 'mem_in' ? '' : 'display:none') . '">
                <input type="hidden" name="t" value="' . $tok . '">
                <label>کد ملی / نام کاربری عضو</label><input name="u" dir="ltr" class="no-num" required placeholder="کد ملی خود را وارد کنید"><br><br>
                <label>گذرواژه</label><input name="pw" type="password" dir="ltr" class="no-num" required><br><br>
                <button class="btn pri" style="width:100%;padding:10px">ورود به پنل اعضا</button>
            </form>';

            echo '<form id="frm_mem_reg" method="post" action="?p=do_mem_register" style="' . ($ltab === 'mem_reg' ? '' : 'display:none') . '">
                <input type="hidden" name="t" value="' . $tok . '">
                <label>نام و نام خانوادگی *</label><input name="name" required placeholder="نام کامل"><br><br>
                <label>نام پدر</label><input name="father" placeholder="نام پدر"><br><br>
                <label>کد ملی (نام کاربری ورود) *</label><input name="mcode" dir="ltr" class="no-num" required placeholder="۱۰ رقم کد ملی"><br><br>
                <label>شماره تلفن همراه *</label><input name="phone" dir="ltr" class="no-num" required placeholder="09..."><br><br>
                <label style="color:var(--blue)">انتخاب کتابخانه جهت عضویت *</label><select name="loc" required>';
                foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
            echo '</select><br><br>
                <label>گذرواژه انتخابی *</label><input name="pw" type="password" dir="ltr" class="no-num" required placeholder="رمز عبور دلخواه"><br><br>
                <button class="btn ok" style="width:100%;padding:10px">ثبت‌نام (نیازمند تایید کتابخانه)</button>
            </form>';

            echo '<form id="frm_staff" method="post" action="?p=login" style="' . ($ltab === 'staff' ? '' : 'display:none') . '">
                <input type="hidden" name="t" value="' . $tok . '">
                <label>نام کاربری کتابدار / مدیر</label><input name="u" dir="ltr" class="no-num" required><br><br>
                <label>گذرواژه</label><input name="pw" type="password" dir="ltr" class="no-num" required><br><br>
                <button class="btn pri" style="width:100%;padding:10px">ورود مدیران و کتابداران</button>
            </form>
            <script>
            function swLoginBox(t){
                document.getElementById("frm_mem_in").style.display = (t==="mem_in")?"block":"none";
                document.getElementById("frm_mem_reg").style.display = (t==="mem_reg")?"block":"none";
                document.getElementById("frm_staff").style.display = (t==="staff")?"block":"none";
                document.getElementById("tb_m_in").className = (t==="mem_in")?"on":"";
                document.getElementById("tb_m_reg").className = (t==="mem_reg")?"on":"";
                document.getElementById("tb_st_in").className = (t==="staff")?"on":"";
            }
            </script>';
        }
        echo '</div>';

    } elseif ($bkey === 'stats') {
        $n_rec = $db->query("SELECT (SELECT COUNT(*) FROM records),(SELECT COUNT(*) FROM items)")->fetch(PDO::FETCH_NUM);
        $n_mem = (int)$db->query("SELECT COUNT(*) FROM members WHERE status IS NULL OR status='active'")->fetchColumn();
        $n_loan_act = (int)$db->query("SELECT COUNT(*) FROM loans WHERE ret IS NULL")->fetchColumn();
        $n_loan_all = (int)$db->query("SELECT COUNT(*) FROM loans")->fetchColumn();
        $tabs_cfg = array_filter(array_map('trim', explode(',', get_cms('stats_tabs_cfg') ?: 'overview,locs,types')));
        if (empty($tabs_cfg)) $tabs_cfg = array('overview', 'locs', 'types');
        $tab_labels = array(
            'overview' => 'آمار کلی',
            'locs'     => 'به تفکیک کتابخانه',
            'types'    => 'انواع منابع'
        );
        $first_t = $tabs_cfg[0];
        $sb_style = get_cms('stats_box_style') ?: 'tabs';
        if (!in_array($sb_style, array('tabs', 'tiles', 'minimal', 'dark'))) $sb_style = 'tabs';

        // پانل‌های جزئیات (مشترک بین شیوه‌ها)
        $panes_html = '';
        if (in_array('overview', $tabs_cfg)) {
            $panes_html .= '<div id="st_tb_pane_overview" style="' . ($first_t === 'overview' ? '' : 'display:none') . '"><ul class="list-clean">
                <li><span>کل عناوین منابع:</span><b>' . tr_num($n_rec[0]) . ' عنوان</b></li>
                <li><span>مجموع نسخه‌های موجود:</span><b>' . tr_num($n_rec[1]) . ' نسخه</b></li>
                <li><span>اعضای فعال کتابخانه:</span><b>' . tr_num($n_mem) . ' نفر</b></li>
                <li><span>امانت‌های در گردش فعلی:</span><b>' . tr_num($n_loan_act) . ' جلد</b></li>
                <li><span>کل گردش امانات:</span><b>' . tr_num($n_loan_all) . ' مورد</b></li>
            </ul></div>';
        }
        if (in_array('locs', $tabs_cfg)) {
            $panes_html .= '<div id="st_tb_pane_locs" style="' . ($first_t === 'locs' ? '' : 'display:none') . '"><ul class="list-clean">';
            foreach ($db->query("SELECT loc, COUNT(DISTINCT rid) AS c1, COUNT(*) AS c2 FROM items GROUP BY loc ORDER BY c2 DESC") as $lr) {
                $panes_html .= '<li><span><b>' . h($lr['loc']) . '</b></span><span>' . tr_num($lr['c1']) . ' عنوان (' . tr_num($lr['c2']) . ' جلد)</span></li>';
            }
            $panes_html .= '</ul></div>';
        }
        if (in_array('types', $tabs_cfg)) {
            $panes_html .= '<div id="st_tb_pane_types" style="' . ($first_t === 'types' ? '' : 'display:none') . '"><ul class="list-clean">';
            foreach ($RTypes as $k => $v) {
                $c = $db->prepare("SELECT COUNT(*) FROM records WHERE rtype=?"); $c->execute(array($k));
                $panes_html .= '<li><span>تعداد ' . h($v) . ':</span><b>' . tr_num($c->fetchColumn()) . ' عنوان</b></li>';
            }
            $panes_html .= '</ul></div>';
        }

        $detail_tabs_html = '';
        if (count($tabs_cfg) > 1) {
            $detail_tabs_html .= '<div class="tabs" style="margin-bottom:10px;display:grid;grid-template-columns:repeat(' . count($tabs_cfg) . ',1fr);gap:4px">';
            foreach ($tabs_cfg as $tk) {
                if (!isset($tab_labels[$tk])) continue;
                $detail_tabs_html .= '<a id="st_tb_btn_' . $tk . '" class="' . ($tk === $first_t ? 'on' : '') . '" href="javascript:void(0)" onclick="swPortalStatsTab(\'' . $tk . '\')" style="padding:5px 4px;font-size:11.5px">' . h($tab_labels[$tk]) . '</a>';
            }
            $detail_tabs_html .= '</div>';
        } else {
            // تک‌تب: بدون دکمه، فقط خود پانل نمایش داده می‌شود
            $detail_tabs_html = '';
        }

        if ($sb_style === 'minimal') {
            echo '<div class="card stats-minimal"><h2>' . h($btitle) . '</h2>
                <div class="sm-inline">
                    <span><b>' . tr_num($n_rec[0]) . '</b> عنوان</span><i>|</i>
                    <span><b>' . tr_num($n_rec[1]) . '</b> نسخه</span><i>|</i>
                    <span><b>' . tr_num($n_mem) . '</b> عضو فعال</span><i>|</i>
                    <span><b>' . tr_num($n_loan_act) . '</b> امانت در گردش</span><i>|</i>
                    <span><b>' . tr_num($n_loan_all) . '</b> کل گردش امانات</span>
                </div>';
            if (count($tabs_cfg) > 1 || !in_array('overview', $tabs_cfg)) {
                echo $detail_tabs_html . $panes_html;
            }
            echo '</div>';
        } elseif ($sb_style === 'dark') {
            echo '<div class="card stats-dark"><h2>' . h($btitle) . '</h2>
                <div class="sd-row">
                    <div class="sd-item"><b>' . tr_num($n_rec[0]) . '</b><span>عنوان منبع</span></div>
                    <div class="sd-item"><b>' . tr_num($n_rec[1]) . '</b><span>نسخه / جلد</span></div>
                    <div class="sd-item"><b>' . tr_num($n_mem) . '</b><span>عضو فعال</span></div>
                    <div class="sd-item"><b>' . tr_num($n_loan_act) . '</b><span>در گردش</span></div>
                </div>';
            echo $detail_tabs_html . $panes_html;
            echo '</div>';
        } elseif ($sb_style === 'tiles') {
            echo '<div class="card"><h2>' . h($btitle) . '</h2>
                <div class="stat-tiles">
                    <div class="st-tile t1"><b>' . tr_num($n_rec[0]) . '</b><span>کل عناوین منابع</span></div>
                    <div class="st-tile t2"><b>' . tr_num($n_rec[1]) . '</b><span>مجموع نسخه‌ها</span></div>
                    <div class="st-tile t3"><b>' . tr_num($n_mem) . '</b><span>اعضای فعال</span></div>
                    <div class="st-tile t4"><b>' . tr_num($n_loan_act) . '</b><span>امانت در گردش</span></div>
                    <div class="st-tile t5"><b>' . tr_num($n_loan_all) . '</b><span>کل گردش امانات</span></div>
                </div>';
            echo $detail_tabs_html . $panes_html;
            echo '</div>';
        } else { // tabs — حالت پیش‌فرض قبلی
            echo '<div class="card"><h2>' . h($btitle) . '</h2>' . $detail_tabs_html . $panes_html . '</div>';
        }

        echo '<script>
        function swPortalStatsTab(k){
            ["overview","locs","types"].forEach(function(x){
                var p=document.getElementById("st_tb_pane_"+x), b=document.getElementById("st_tb_btn_"+x);
                if(p) p.style.display = (x===k)?"block":"none";
                if(b) b.className = (x===k)?"on":"";
            });
        }
        </script>';

    } elseif ($bkey === 'gallery') {
        $gals = $db->query("SELECT * FROM gallery ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
        echo '<div class="card"><h2>' . h($btitle) . '</h2>';
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
        echo '<div class="card"><h2>' . h($btitle) . '</h2><ul class="list-clean">';
        foreach (array_filter(explode("\n", get_cms('links'))) as $lnk) {
            $parts = explode('|', $lnk);
            $title_l = trim($parts[0]);
            $url_l = trim(isset($parts[1]) ? $parts[1] : '#');
            echo '<li><a href="' . h($url_l) . '" target="_blank" style="text-decoration:none;color:var(--blue);font-weight:600">' . h($title_l) . '</a></li>';
        }
        echo '</ul></div>';
    }
}
