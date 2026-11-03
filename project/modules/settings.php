<?php
// ماژول تنظیمات و مدیریت: کتابخانه‌ها (locs)، سفارش‌ها (orders)، تیکتینگ (tickets)، مدیریت پرتال (cms)، پشتیبان‌گیری (backup) و کاربران (users)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

if ($p === 'settings') {
    if (!is_super_admin()) { echo '<div class="card"><p class="warn">این بخش فقط برای مدیر کل مجاز است.</p></div>'; return; }
    
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

    $mod_labels = array('mod_rec'=>'منابع و میز امانت','mod_form'=>'ثبت و ویرایش منبع','mod_org'=>'چاپ برچسب عطف','mod_locs'=>'مدیریت کتابخانه‌ها','mod_orders'=>'سفارش کتاب','mod_tickets'=>'تیکت و پشتیبانی','mod_imp_exp'=>'تبادل فراداده','mod_stats'=>'آمار سامانه','mod_authority'=>'مستندات فهرست‌نویسی','mod_bulk'=>'کنترل گروهی','mod_backup'=>'پشتیبان‌گیری','mod_mem'=>'مدیریت اعضا','mod_loan'=>'گردش امانت','mod_rep'=>'گزارش‌ها','mod_cms'=>'مدیریت پرتال','mod_users'=>'مدیریت کاربران','mod_chat'=>'چت‌بات هوشمند پرتال');
    $mods_html = '';
    foreach ($mod_labels as $mk => $mlbl) {
        $on = !empty($lic[$mk]);
        $mods_html .= '<span class="badge" style="padding:5px 10px;background:' . ($on ? '#d1fae5;color:#065f46' : '#f1f5f9;color:#94a3b8;text-decoration:line-through') . '">' . ($on ? '' : '') . h($mlbl) . '</span> ';
    }
    
    echo '<h1>تنظیمات مجوز و ارتقا</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    
    echo '<div class="card" style="background:' . $sc[0] . ';border:2px solid ' . $sc[1] . '">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
            <div>
                <span class="badge" style="background:' . $sc[1] . ';color:#fff;font-size:12.5px">' . $sc[2] . '</span>
                <b style="margin-right:8px;font-size:14px">کتابخانه: ' . h($lic['client_name']) . '</b>
                <span class="badge">نوع مجوز: ' . h(isset($lic['plan_title']) ? $lic['plan_title'] : $sc[2]) . '</span>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <span class="badge">شماره قرارداد: ' . tr_num(h($lic['contract_no'])) . '</span>
                <span class="badge">کد نصب سرور: <span class="no-num">' . h(install_fingerprint('strict')) . '</span></span>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:12px">
            <span class="badge" style="font-size:13px;padding:6px 12px">تاریخ انقضای نرم‌افزار: <b>' . ($lic['exp_date'] !== '' ? jdate($lic['exp_date']) : 'دائمی') . '</b></span>
            <span class="badge" style="font-size:13px;padding:6px 12px;background:' . ($lic['support_valid'] ? '#d1fae5' : '#fee2e2') . ';color:' . ($lic['support_valid'] ? '#065f46' : '#991b1b') . '">تاریخ اعتبار پشتیبانی: <b>' . ($lic['support_exp_date'] !== '' ? jdate($lic['support_exp_date']) : 'بدون پشتیبانی') . '</b></span>
        </div>
        <p style="margin:0 0 12px;font-size:12.8px">' . h($lic['status_msg']) . '</p>
        <div class="grid" style="margin-bottom:12px">' .
            $bar_fn($tot_locs, (int)$lic['max_locs'], 'ظرفیت کتابخانه') .
            $bar_fn($tot_recs, (int)$lic['max_records'], 'ظرفیت رکورد کتابشناختی') .
            $bar_fn($tot_items, (int)$lic['max_items'], 'ظرفیت کل مجلدات') .
            $bar_fn($tot_mems, (int)$lic['max_members'], 'ظرفیت اعضای کتابخانه') .
        '</div>
        <div style="background:rgba(255,255,255,.75);padding:10px 12px;border-radius:12px;border:1px dashed var(--line)">
            <div style="font-size:12px;font-weight:700;margin-bottom:6px;color:var(--navy2)">ماژول‌ها و مجوزهای اختصاص‌یافته به این مرکز:</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">' . $mods_html . '</div>
        </div>
        
        <div style="margin-top:16px;background:#fff;padding:14px 18px;border-radius:12px;border:1px solid var(--blue)">
            <h3 style="margin:0 0 10px;font-size:14px;color:var(--blue)">تمدید پشتیبانی / ثبت لایسنس ارتقایافته (نسخه جدید)</h3>
            <p style="font-size:12.5px;margin:0 0 12px">جهت دریافت پشتیبانی و نصب آپدیت‌های سامانه، و یا ارتقای ظرفیت و افزودن ماژول‌های جدید، کد فعال‌سازی دریافتی از فروشنده (`ASM2-...` یا `ASM1-...`) را اینجا جای‌گذاری کنید:</p>
            <form method="post" action="?p=activate_lic_key" style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="hidden" name="t" value="' . $tok . '">
                <input name="lic_key" dir="ltr" required placeholder="ASM2-... یا ASM1-..." class="no-num" style="flex:1;font-family:monospace;font-size:12.5px">
                <button class="btn ok" style="padding:8px 24px">اعمال و ثبت کلید</button>
            </form>
        </div>
    </div>';
} elseif ($p === 'locs') {
    $map_locs = get_locations_map();
    $max_pref = 9;
    foreach ($map_locs as $pv) { if ((int)$pv > $max_pref) $max_pref = (int)$pv; }
    $next_pref_suggest = $max_pref + 1;
    $ltab = isset($_GET['tab']) ? $_GET['tab'] : 'manage';

    echo '<h1>مدیریت کتابخانه‌ها، کاربرگه‌های ثبت و استانداردهای فهرست‌نویسی</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    if (isset($_GET['msg'])) echo '<div class="ok-msg">عملیات بروزرسانی کتابخانه، پیش‌شماره ثبت و انتقال رکوردها با موفقیت انجام شد.</div>';

    echo '<div class="tabs">
        <a class="' . ($ltab === 'manage' ? 'on' : '') . '" href="?p=locs&tab=manage">مدیریت کتابخانه‌ها و پیش‌شماره‌های ثبت</a>
        <a class="' . ($ltab === 'worksheets' ? 'on' : '') . '" href="?p=locs&tab=worksheets">تنظیمات کاربرگه و استاندارد فهرست‌نویسی و رده‌بندی</a>
    </div>';

    if ($ltab === 'worksheets') {
        $allowed_locs = allowed_locations();
        $wloc = isset($_GET['wloc']) && in_array($_GET['wloc'], $allowed_locs) ? $_GET['wloc'] : $allowed_locs[0];
        $lib_cfg = get_lib_config($wloc);
        $ws_list = $lib_cfg['worksheets'];
        $cur_ws_key = isset($_GET['ws']) && isset($ws_list[$_GET['ws']]) ? $_GET['ws'] : key($ws_list);
        $cur_ws = $ws_list[$cur_ws_key];

        echo '<div class="card" style="border:2px solid var(--blue)">
            <h2><span>۱. انتخاب کتابخانه و تعیین استاندارد فهرست‌نویسی و روش رده‌بندی</span><span class="badge" style="background:var(--blue);color:#fff">کتابخانه فعال: ' . h($wloc) . '</span></h2>
            <div class="grid" style="align-items:end">
                <div>
                    <label style="color:var(--blue)">انتخاب کتابخانه جهت تنظیم کاربرگه‌ها</label>
                    <select onchange="location.href=\'?p=locs&tab=worksheets&wloc=\'+encodeURIComponent(this.value)">';
                    foreach ($allowed_locs as $lc) {
                        echo '<option value="' . h($lc) . '"' . ($lc === $wloc ? ' selected' : '') . '>' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
                    }
        echo '      </select>
                </div>
                <form method="post" action="?p=save_lib_ws" style="display:contents">
                    <input type="hidden" name="t" value="' . $tok . '">
                    <input type="hidden" name="loc" value="' . h($wloc) . '">
                    <input type="hidden" name="ws_act" value="save_std">
                    <div>
                        <label>استاندارد فهرست‌نویسی کتابخانه</label>
                        <select name="cat_std">
                            <option value="iranmarc"' . ($lib_cfg['cat_std'] === 'iranmarc' ? ' selected' : '') . '>ایران‌مارک / UNIMARC (استاندارد ملی)</option>
                            <option value="marc21"' . ($lib_cfg['cat_std'] === 'marc21' ? ' selected' : '') . '>مارک ۲۱ / MARC21 (کنگره آمریکا)</option>
                        </select>
                    </div>
                    <div>
                        <label>روش رده‌بندی مجاز در این کتابخانه</label>
                        <select name="class_sys">
                            <option value="both"' . ($lib_cfg['class_sys'] === 'both' ? ' selected' : '') . '>هر دو روش (رده‌بندی دیویی DDC و کنگره LCC)</option>
                            <option value="dewey"' . ($lib_cfg['class_sys'] === 'dewey' ? ' selected' : '') . '>فقط رده‌بندی دیویی (Dewey - 676)</option>
                            <option value="congress"' . ($lib_cfg['class_sys'] === 'congress' ? ' selected' : '') . '>فقط رده‌بندی کنگره (LC - 680/686)</option>
                        </select>
                    </div>
                    <div><button class="btn pri">ذخیره استاندارد و رده‌بندی این کتابخانه</button></div>
                </form>
            </div>
        </div>';

        echo '<div class="grid">
            <div class="card">
                <h2>۲. کاربرگه‌های ثبت منابع در «' . h($wloc) . '»</h2>
                <p style="font-size:12.5px;margin:0 0 10px">هر کتابخانه می‌تواند چندین کاربرگه (مثلاً کتاب، نقشه، پایان‌نامه، نسخه خطی و...) با فیلدها و کدهای اختصاصی داشته باشد:</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">';
                foreach ($ws_list as $wk => $wv) {
                    $is_on = ($wk === $cur_ws_key);
                    echo '<a class="btn' . ($is_on ? ' pri' : '') . '" href="?p=locs&tab=worksheets&wloc=' . urlencode($wloc) . '&ws=' . urlencode($wk) . '">' . h($wv['title']) . ' <span class="badge">' . h($RTypes[$wv['rtype']] ?? 'کتاب') . '</span></a>';
                }
        echo '  </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-top:10px;border-top:1px dashed var(--line)">
                    <a class="btn ok" href="?p=sample_rec&loc=' . urlencode($wloc) . '&ws=' . urlencode($cur_ws_key) . '">دانلود فایل اکسل نمونه برای «' . h($cur_ws['title']) . '»</a>
                    <a class="btn-sm pri" href="?p=form&loc=' . urlencode($wloc) . '&ws=' . urlencode($cur_ws_key) . '">ثبت تکی منبع با این کاربرگه</a>';
                    if (count($ws_list) > 1) {
                        echo '<form method="post" action="?p=save_lib_ws" style="display:inline" onsubmit="return confirm(\'آیا از حذف این کاربرگه مطمئن هستید؟\')">
                            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="loc" value="' . h($wloc) . '">
                            <input type="hidden" name="ws_act" value="del_ws"><input type="hidden" name="ws_key" value="' . h($cur_ws_key) . '">
                            <button class="btn-sm dng">حذف این کاربرگه</button>
                        </form>';
                    }
        echo '  </div>
            </div>
            <div class="card">
                <h2>افزودن کاربرگه ثبت جدید (مثلاً نقشه، نشریه، لوح فشرده)</h2>
                <form method="post" action="?p=save_lib_ws">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="loc" value="' . h($wloc) . '"><input type="hidden" name="ws_act" value="add_ws">
                    <div class="grid">
                        <div><label>عنوان کاربرگه جدید *</label><input name="ws_title" required placeholder="مثلاً: کاربرگه نقشه یا کاربرگه پایان‌نامه"></div>
                        <div><label>کد انگلیسی کاربرگه (اختیاری)</label><input name="ws_key" dir="ltr" placeholder="map, thesis, serial..."></div>
                        <div><label>نوع منبع پیش‌فرض</label><select name="ws_rtype">';
                        foreach ($RTypes as $rk => $rv) echo '<option value="' . h($rk) . '">' . h($rv) . '</option>';
        echo '          </select></div>
                    </div><br>
                    <button class="btn ok">ایجاد کاربرگه جدید برای «' . h($wloc) . '»</button>
                </form>
            </div>
        </div>';

        echo '<div class="card">
            <h2><span>۳. ویرایش فیلدها، کد مارک و برچسب‌های «' . h($cur_ws['title']) . '» در کتابخانه «' . h($wloc) . '»</span><span class="badge">قابل استفاده در ثبت تکی و اکسل نمونه</span></h2>
            <p style="font-size:12.8px;margin:0 0 12px">می‌توانید برای این کاربرگه مشخص کنید کدام فیلدها فعال (قابل نمایش در فرم ثبت و فایل اکسل نمونه) باشند، نام نمایشی هر فیلد و کد تگ مارک آن (مثلاً <code>206 $a</code> برای مقیاس نقشه) چه باشد:</p>
            <form method="post" action="?p=save_lib_ws">
                <input type="hidden" name="t" value="' . $tok . '">
                <input type="hidden" name="loc" value="' . h($wloc) . '">
                <input type="hidden" name="ws_act" value="save_fields">
                <input type="hidden" name="ws_key" value="' . h($cur_ws_key) . '">
                <div class="grid" style="margin-bottom:14px">
                    <div><label>عنوان کاربرگه *</label><input name="ws_title" value="' . h($cur_ws['title']) . '" required></div>
                    <div><label>نوع منبع مرتبط</label><select name="ws_rtype">';
                    foreach ($RTypes as $rk => $rv) echo '<option value="' . h($rk) . '"' . ($cur_ws['rtype'] === $rk ? ' selected' : '') . '>' . h($rv) . '</option>';
        echo '      </select></div>
                </div>
                <table>
                    <tr>
                        <th style="width:90px">فعال در برگه</th>
                        <th style="width:90px">اجباری (*)</th>
                        <th style="width:130px">کلید سیستمی</th>
                        <th style="width:190px">کد فیلد / تگ مارک</th>
                        <th>عنوان نمایشی فیلد در کاربرگه و سرستون اکسل</th>
                    </tr>';
                    foreach ($F as $fk => $flbl) {
                        $fd = isset($cur_ws['fields'][$fk]) ? $cur_ws['fields'][$fk] : array('code'=>$fk, 'label'=>$flbl, 'active'=>1, 'req'=>($fk==='f200a'?1:0));
                        $is_title = ($fk === 'f200a');
                        echo '<tr>
                            <td><input type="checkbox" name="f_active[' . $fk . ']" value="1"' . (!empty($fd['active']) || $is_title ? ' checked' : '') . ($is_title ? ' disabled' : '') . '></td>
                            <td><input type="checkbox" name="f_req[' . $fk . ']" value="1"' . (!empty($fd['req']) || $is_title ? ' checked' : '') . ($is_title ? ' disabled' : '') . '></td>
                            <td><code>' . h($fk) . '</code></td>
                            <td><input name="f_code[' . $fk . ']" dir="ltr" value="' . h($fd['code']) . '" style="text-align:left;padding:5px 8px"></td>
                            <td><input name="f_label[' . $fk . ']" value="' . h($fd['label']) . '" style="padding:5px 10px"></td>
                        </tr>';
                    }
        echo '  </table><br>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button class="btn pri" style="padding:10px 24px">ذخیره تغییرات کاربرگه «' . h($cur_ws['title']) . '»</button>
                    <a class="btn ok" href="?p=sample_rec&loc=' . urlencode($wloc) . '&ws=' . urlencode($cur_ws_key) . '">دانلود اکسل نمونه این کاربرگه</a>
                </div>
            </form>
        </div>';
    } else {
        if (is_loc_restricted()) {
            echo '<div class="card"><p class="warn">حساب کاربری شما محدود به کتابخانه «' . h(user_loc()) . '» (پیش‌شماره ثبت: ' . tr_num(get_loc_prefix(user_loc())) . ') است و تغییر ساختار کل محل‌ها ویژه مدیر کل است.</p></div>';
        } else {
            echo '<div class="grid">
                <div class="card"><h2>افزودن کتابخانه جدید</h2>
                    <form method="post" action="?p=loc_action"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="add">
                        <label>نام کتابخانه / مرکز جدید *</label><input name="new_loc" required placeholder="مثلاً: شعبه ۲ یا بخش کتب نفیس"><br><br>
                        <label>پیش‌شماره ثبت اختصاصی این مرکز *</label><input type="number" name="new_pref" min="10" value="' . $next_pref_suggest . '" required><br><br>
                        <button class="btn pri">افزودن به فهرست محل‌ها</button>
                    </form>
                </div>
                <div class="card"><h2>ویرایش نام یا پیش‌شماره کتابخانه</h2>
                    <form method="post" action="?p=loc_action"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="rename">
                        <label>انتخاب کتابخانه فعلی</label><select name="old_loc">';
                        foreach ($map_locs as $lc => $pf) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره فعلی: ' . tr_num($pf) . ')</option>';
            echo '      </select><br><br>
                        <label>نام جدید محل *</label><input name="new_loc" required placeholder="نام جدید یا همان نام قبلی..."><br><br>
                        <label>پیش‌شماره ثبت جدید (مثلاً 10، 11، 12)</label><input type="number" name="new_pref" min="10" placeholder="در صورت خالی بودن، پیش‌شماره قبلی حفظ می‌شود"><br><br>
                        <button class="btn ok">ذخیره تغییرات و بروزرسانی</button>
                    </form>
                </div>
                <div class="card"><h2>حذف محل و انتقال رکوردها به محل دیگر</h2>
                    <form method="post" action="?p=loc_action" onsubmit="return confirm(\'آیا از حذف این محل و انتقال تمام منابع و اعضای آن به کتابخانه مقصد مطمئن هستید؟\')">
                        <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="delete">
                        <label>محل مورد نظر جهت حذف</label><select name="del_loc">';
                        foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
            echo '      </select><br><br>
                        <label>انتقال تمامی منابع، اعضا و سفارش‌ها به:</label><select name="target_loc">';
                        foreach ($Locations as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
            echo '      </select><br><br>
                        <button class="btn dng">حذف محل و انتقال رکوردها</button>
                    </form>
                </div>
            </div>';
        }
        echo '<div class="card"><h2>فهرست کتابخانه‌ها، پیش‌شماره ثبت، کاربرگه‌ها و آمار هر بخش</h2><table><tr><th>نام کتابخانه</th><th>پیش‌شماره ثبت</th><th>استاندارد / رده‌بندی</th><th>کاربرگه‌های تعریف‌شده</th><th>شماره ثبت بعدی</th><th>تعداد عناوین</th><th>مجموع نسخه‌ها</th><th>اعضا</th></tr>';
        foreach ($map_locs as $lc => $pf) {
            $st1 = $db->prepare("SELECT COUNT(DISTINCT rid), COUNT(*) FROM items WHERE loc=?"); $st1->execute(array($lc)); $r1 = $st1->fetch(PDO::FETCH_NUM);
            $st2 = $db->prepare("SELECT COUNT(*) FROM members WHERE loc=?"); $st2->execute(array($lc)); $c_mem = $st2->fetchColumn();
            $lcfg = get_lib_config($lc);
            $std_lbl = ($lcfg['cat_std'] === 'marc21' ? 'MARC21' : 'ایران‌مارک') . ' / ' . ($lcfg['class_sys'] === 'dewey' ? 'دیویی' : ($lcfg['class_sys'] === 'congress' ? 'کنگره' : 'دیویی+کنگره'));
            $ws_names = array();
            foreach ($lcfg['worksheets'] as $wk => $wv) $ws_names[] = $wv['title'];
            echo '<tr><td><b>' . h($lc) . '</b></td><td><span class="badge" style="background:var(--blue);color:#fff">پیش‌شماره ' . tr_num($pf) . '</span></td><td><span class="badge">' . h($std_lbl) . '</span></td><td><a href="?p=locs&tab=worksheets&wloc=' . urlencode($lc) . '" class="btn-sm">' . h(implode(' ، ', $ws_names)) . '</a></td><td><code>' . tr_num(next_reg_no($lc)) . '</code></td><td>' . tr_num($r1[0]) . ' عنوان</td><td>' . tr_num($r1[1]) . ' نسخه</td><td>' . tr_num($c_mem) . ' نفر</td></tr>';
        }
        echo '</table></div>';
    }

// ==================== فهرست سفارش کتاب ====================
} elseif ($p === 'orders') {
    echo '<h1>فهرست سفارش‌های خرید منابع (ارسال‌شده از پرتال)</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">وضعیت سفارش با موفقیت بروزرسانی شد.</div>';
    $w_ord = is_loc_restricted() ? "WHERE loc=" . $db->quote(user_loc()) : "";
    $ords = $db->query("SELECT * FROM orders $w_ord ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><table><tr><th>تاریخ</th><th>عنوان کتاب / منبع</th><th>نویسنده / ناشر</th><th>شابک</th><th>کتابخانه</th><th>درخواست‌کننده</th><th>وضعیت</th><th>تعیین وضعیت (خرید / عدم خرید)</th></tr>';
    if ($ords) {
        foreach ($ords as $o) {
            $st_badge = '<span class="badge">در انتظار بررسی</span>';
            if ($o['status'] === 'bought') $st_badge = '<span class="badge" style="background:#d1fae5;color:#065f46">خریداری شد</span>';
            elseif ($o['status'] === 'rejected') $st_badge = '<span class="badge" style="background:#ffe4e6;color:var(--warn)">عدم خرید</span>';

            echo '<tr><td>' . jdate($o['created']) . '</td><td><b>' . h($o['title']) . '</b></td><td>' . h($o['author']) . ($o['pub'] ? ' / ' . h($o['pub']) : '') . '</td>
            <td>' . h($o['isbn']) . '</td><td><span class="badge">' . h($o['loc']) . '</span></td>
            <td>' . h($o['requester']) . '<br><small>' . tr_num(h($o['phone'])) . '</small></td><td>' . $st_badge . '</td>
            <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
                <form method="post" action="?p=order_status" style="display:inline"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $o['id'] . '"><input type="hidden" name="status" value="bought"><button class="btn-sm ok">خرید</button></form>
                <form method="post" action="?p=order_status" style="display:inline"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $o['id'] . '"><input type="hidden" name="status" value="rejected"><button class="btn-sm dng">عدم خرید</button></form>
                ' . post_btn('del_order', 'حذف', array('id'=>$o['id']), 'btn-sm') . '
            </div></td></tr>';
        }
    } else {
        echo '<tr><td colspan="8" style="text-align:center">هیچ سفارش کتابی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';

// ==================== سیستم تیکتینگ ====================
} elseif ($p === 'tickets') {
    echo '<h1>سیستم تیکت و پشتیبانی داخلی کتابخانه</h1>';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'sent') echo '<div class="ok-msg">تیکت شما با موفقیت برای مدیریت ارسال شد.</div>';
        if ($_GET['msg'] === 'replied') echo '<div class="ok-msg">پاسخ تیکت با موفقیت ثبت شد.</div>';
    }
    echo '<div class="card"><h2>ارسال تیکت جدید به مدیریت</h2>
        <form method="post" action="?p=send_ticket"><input type="hidden" name="t" value="' . $tok . '">
            <div class="grid">
                <div><label>گیرنده تیکت *</label><select name="target">
                    <option value="admin">مدیر کتابخانه (admin)</option>
                    <option value="admin1">مدیر فنی سامانه (admin1)</option>
                </select></div>
                <div><label>مبدا (کاربر / کتابخانه)</label><input value="' . h($_SESSION['u'] . ' — ' . (is_loc_restricted() ? user_loc() : 'مدیریت مرکزی')) . '" disabled></div>
                <div><label>موضوع تیکت *</label><input name="subj" required placeholder="موضوع درخواست یا گزارش مشکل..."></div>
            </div><br>
            <label>متن پیام *</label><textarea name="msg" rows="3" required placeholder="شرح کامل پیام..."></textarea><br><br>
            <button class="btn pri">ارسال تیکت</button>
        </form>
    </div>';

    $w_t = is_super_admin() ? "" : "WHERE sender=" . $db->quote($_SESSION['u']) . " OR uloc=" . $db->quote(user_loc());
    $tks = $db->query("SELECT * FROM tickets $w_t ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo '<div class="card"><h2>فهرست تیکت‌ها و پیام‌ها</h2><table><tr><th>تاریخ</th><th>فرستنده (کتابخانه)</th><th>گیرنده</th><th>موضوع و متن پیام</th><th>وضعیت / پاسخ مدیریت</th></tr>';
    if ($tks) {
        foreach ($tks as $tk) {
            $rec_label = ($tk['target'] === 'admin1') ? 'مدیر فنی (admin1)' : 'مدیر کتابخانه (admin)';
            echo '<tr><td>' . jdate($tk['created']) . '</td><td><b>' . h($tk['sender']) . '</b><br><span class="badge">' . h($tk['uloc']) . '</span></td>
            <td><span class="badge">' . $rec_label . '</span></td>
            <td><b>' . h($tk['subj']) . '</b><br><span style="color:#444">' . nl2br(h($tk['msg'])) . '</span></td><td>';
            if ($tk['reply']) {
                echo '<div style="background:#d1fae5;padding:8px 12px;border-radius:12px;margin-bottom:6px;border-right:3px solid var(--ok)"><b>پاسخ مدیریت:</b><br>' . nl2br(h($tk['reply'])) . '</div>';
            } else {
                echo '<span class="badge" style="background:#fef3c7;color:#92400e">در انتظار پاسخ</span> ';
            }
            if (is_super_admin()) {
                echo '<form method="post" action="?p=reply_ticket" style="margin-top:6px;display:flex;gap:6px">
                    <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $tk['id'] . '">
                    <input name="reply" value="' . h($tk['reply']) . '" placeholder="نوشتن پاسخ..." required>
                    <input type="hidden" name="status" value="answered">
                    <button class="btn-sm ok">ثبت پاسخ</button></form>';
            }
            echo '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5" style="text-align:center">هیچ تیکتی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';
} elseif ($p === 'cms') {
    $box = isset($_GET['box']) ? $_GET['box'] : 'general';
    echo '<h1>مدیریت پرتال (۲۰ طیف رنگ، چیدمان و افزودن باکس، ویرایشگر و گالری)</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">تغییرات با موفقیت ذخیره شد.</div>';

    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    echo '<div class="tabs">
        <a class="' . ($box === 'general' ? 'on' : '') . '" href="?p=cms&box=general">تغییر تم پرتال و طیف رنگ</a>
        <a class="' . ($box === 'slider' ? 'on' : '') . '" href="?p=cms&box=slider">اسلایدر عریض (تعداد دلخواه + مات/شفاف)</a>
        <a class="' . ($box === 'hdr_ftr' ? 'on' : '') . '" href="?p=cms&box=hdr_ftr">تنظیمات هدر، لوگو و ستون‌های فوتر</a>
        <a class="' . ($box === 'menus' ? 'on' : '') . '" href="?p=cms&box=menus">منوی فوتر و منوی آبشاری</a>
        <a class="' . ($box === 'pages' ? 'on' : '') . '" href="?p=cms&box=pages">ساخت صفحه تکی (لینک یکتا)</a>
        <a class="' . ($box === 'layout' ? 'on' : '') . '" href="?p=cms&box=layout">چیدمان و باکس آمار</a>
        <a class="' . ($box === 'boxes' ? 'on' : '') . '" href="?p=cms&box=boxes">ویرایش محتوای باکس‌ها</a>
        <a class="' . ($box === 'chart' ? 'on' : '') . '" href="?p=cms&box=chart">نمودار رشد منابع</a>
        <a class="' . ($box === 'chat' ? 'on' : '') . '" href="?p=cms&box=chat">چت پرتال</a>
        <a class="' . ($box === 'news' ? 'on' : '') . '" href="?p=cms&box=news">مدیریت اخبار</a>
        <a class="' . ($box === 'gallery' ? 'on' : '') . '" href="?p=cms&box=gallery">گالری تصاویر</a>
        <a class="btn ok" href="?p=home" target="_blank">مشاهده پرتال</a>
    </div>';

    if ($box === 'general') {
        $cur_font  = get_cms('font');
        $cur_theme = get_cms('theme') ?: 'itaihe_art';
        $cur_pstyle= get_cms('portal_style') ?: 'itaihe_art';
        $fonts_list = array(
            'Vazirmatn' => 'فونت وزیرمتن (Vazirmatn - پیشنهادی)',
            'Shabnam'   => 'فونت شبنم (Shabnam)',
            'Sahel'     => 'فونت ساحل (Sahel)',
            'Samim'     => 'فونت صمیم (Samim)',
            'Tahoma'    => 'فونت پیش‌فرض سیستم (Tahoma)'
        );
        $portal_styles = array(
            'itaihe_art'       => 'تم تخصصی هنر و تمدن اسلامی (الهام‌گرفته از کتابخانه تخصصی هنر itaihe.ac.ir — کادرهای طلایی و فیروزه‌ای)',
            'classic_academic' => 'تم دانشگاهی و پژوهشی کلاسیک (رسمی با کادرهای زاویه‌دار سازمانی)',
            'modern_minimal'   => 'تم مدرن مینیمال (باکس‌های نرم و معاصر)'
        );
        echo '<form class="card" method="post" action="?p=savecms"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="general">
            <h2>تغییر تم پرتال، الگوی نمایش باکس‌ها، طیف رنگ و فونت</h2>
            <div class="grid">
                <div><label>قالب و تم ساختاری پرتال (Portal Theme)</label><select name="portal_style">';
                foreach ($portal_styles as $psk => $psv) echo '<option value="' . h($psk) . '"' . ($cur_pstyle === $psk ? ' selected' : '') . '>' . h($psv) . '</option>';
        echo '  </select></div>
                <div><label>فونت نمایشی سامانه</label><select name="font">';
                foreach ($fonts_list as $fk => $fv) echo '<option value="' . h($fk) . '"' . ($cur_font === $fk ? ' selected' : '') . '>' . h($fv) . '</option>';
        echo '  </select></div>
            </div><br>
            <label>انتخاب طیف رنگی سامانه و پرتال:</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:10px;margin-top:8px">';
            foreach ($Themes as $tk => $tv) {
                $chk = ($cur_theme === $tk) ? 'checked' : '';
                echo '<label style="display:flex;align-items:center;gap:8px;padding:10px;border:2px solid ' . ($chk ? $tv['blue'] : 'var(--line)') . ';border-radius:10px;background:' . $tv['ice'] . ';cursor:pointer">
                    <input type="radio" name="theme" value="' . h($tk) . '" ' . $chk . '>
                    <span style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,' . $tv['navy'] . ',' . $tv['blue'] . ');display:inline-block;border:1px solid #fff;flex-shrink:0"></span>
                    <span style="font-size:12px;color:' . $tv['navy'] . '">' . h($tv['name']) . '</span>
                </label>';
            }
        echo '</div>' . color_studio() . '<br><button class="btn pri">ذخیره تم پرتال و استایل رنگ</button></form>';

    } elseif ($box === 'hdr_ftr') {
        echo '<form class="card" method="post" action="?p=savecms" enctype="multipart/form-data">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="hdr_ftr">
            <h2>تنظیمات هدر حرفه‌ای، لوگو و منوی ناوبری بالای پرتال</h2>
            <div class="grid">
                <div><label>عنوان اصلی کتابخانه / پرتال *</label><input name="site_title" value="' . h(get_cms('site_title')) . '" required></div>
                <div><label>زیرعنوان سازمانی بالای نام کتابخانه (در هدر)</label><input name="header_org_sub" value="' . h(get_cms('header_org_sub')) . '" placeholder="مثلاً: پژوهشکده هنر و تمدن اسلامی"></div>
                <div><label>شعار یا توضیح زیر عنوان پرتال (در بنر صفحه اول)</label><input name="site_sub" value="' . h(get_cms('site_sub')) . '"></div>
                <div><label>تلفن تماس نمایشی در نوار بالای هدر</label><input name="header_phone" value="' . h(get_cms('header_phone')) . '"></div>
                <div><label>ساعات کاری نمایشی در نوار بالای هدر</label><input name="header_hours" value="' . h(get_cms('header_hours')) . '"></div>
                <div><label>آدرس تصویر لوگوی سفارشی (خالی = نشان اسلیمی پیش‌فرض)</label><input name="header_logo" dir="ltr" value="' . h(get_cms('header_logo')) . '" placeholder="uploads/logo.png"></div>
                <div><label>یا آپلود فایل لوگوی جدید (PNG/JPG/SVG)</label><input type="file" name="logo_file" accept="image/*"></div>
            </div><br>
            <label>آیتم‌های منوی ناوبری هدر (هر لینک در یک خط با فرمت: <code>عنوان منو | آدرس لینک</code>):</label>
            <textarea name="header_nav" rows="5" style="font-family:inherit">' . h(get_cms('header_nav')) . '</textarea>
            <br><br>
            <h2>تنظیمات ستون‌های فوتر و فراخوانی باکس‌های محتوایی در فوتر</h2>
            <p style="font-size:12.5px;margin:0 0 12px">می‌توانید تعداد ستون‌های فوتر (۱ تا ۴ ستون) را مشخص کرده و تعیین کنید در هر ستون کدام باکس محتوایی، منوی فوتر یا صفحه تکی فراخوانی و نمایش داده شود:</p>';
            $f_cols_cur = (int)(get_cms('footer_cols') ?: 3);
            $src_opts = array(
                'footer_about' => 'متن معرفی کوتاه فوتر',
                'about'        => 'باکس درباره ما (HTML)',
                'menu'         => 'منوی فوتر / دسترسی سریع (آبشاری)',
                'contact'      => 'باکس اطلاعات تماس با ما',
                'services'     => 'باکس خدمات کتابخانه',
                'links'        => 'باکس پیوندهای مفید',
                'stats'        => 'باکس خلاصه آمار مجموعه'
            );
            foreach ($db->query("SELECT id, title FROM portal_boxes WHERE btype='custom' ORDER BY id") as $cbx) {
                $src_opts['box_' . $cbx['id']] = 'باکس سفارشی: ' . $cbx['title'];
            }
            foreach ($db->query("SELECT id, title FROM pages ORDER BY id DESC") as $pg_o) {
                $src_opts['page_' . $pg_o['id']] = 'صفحه تکی: ' . $pg_o['title'];
            }
            echo '<div class="grid">
                <div><label style="color:var(--blue)">تعداد ستون‌های فوتر</label><select name="footer_cols">';
                for ($fc = 1; $fc <= 4; $fc++) echo '<option value="' . $fc . '"' . ($f_cols_cur === $fc ? ' selected' : '') . '>' . tr_num($fc) . ' ستونه</option>';
            echo '</select></div>';
            for ($fc = 1; $fc <= 4; $fc++) {
                $cur_s = get_cms('footer_col' . $fc . '_source');
                echo '<div><label>محتوای ستون ' . tr_num($fc) . ' فوتر</label><select name="footer_col' . $fc . '_source">';
                foreach ($src_opts as $sk => $sl) echo '<option value="' . h($sk) . '"' . ($cur_s === $sk ? ' selected' : '') . '>' . h($sl) . '</option>';
                echo '</select></div>';
            }
            echo '<div><label>عنوان ستون دوم فوتر</label><input name="footer_col2_title" value="' . h(get_cms('footer_col2_title')) . '"></div>
                <div><label>عنوان ستون سوم فوتر</label><input name="footer_col3_title" value="' . h(get_cms('footer_col3_title')) . '"></div>
                <div><label>عنوان ستون چهارم فوتر</label><input name="footer_col4_title" value="' . h(get_cms('footer_col4_title')) . '"></div>
                <div><label>متن کپی‌رایت پایین فوتر</label><input name="footer_copyright" value="' . h(get_cms('footer_copyright')) . '"></div>
            </div><br>
            <div class="grid">
                <div>
                    <label>متن معرفی کوتاه کتابخانه در فوتر:</label>
                    <textarea name="footer_about" rows="5">' . h(get_cms('footer_about')) . '</textarea>
                </div>
                <div>
                    <label>لینک‌های پیش‌فرض فوتر (در صورت عدم ثبت آیتم در بخش منوی فوتر — هر خط: <code>عنوان | آدرس</code>):</label>
                    <textarea name="footer_col2_links" rows="5">' . h(get_cms('footer_col2_links')) . '</textarea>
                </div>
            </div><br>
            <button class="btn pri">ذخیره تنظیمات هدر، لوگو و ستون‌های فوتر</button>
        </form>';

    } elseif ($box === 'layout') {
        $cur_st_tabs = explode(',', get_cms('stats_tabs_cfg') ?: 'overview,locs,types');
        $cur_sb_style = get_cms('stats_box_style') ?: 'tabs';
        $sb_styles = array(
            'tabs'    => 'تب‌بندی (حالت فعلی — سرصفحه + تب‌های متنی)',
            'tiles'   => 'کاشی رنگی بزرگ (آمار کلی به‌صورت کارت‌های عددی چشمگیر + جزئیات در تب)',
            'minimal' => 'حداقلی / اینلاین (اعداد مهم در یک خط با جداکننده)',
            'dark'    => 'تیره سازمانی (پس‌زمینه سرمه‌ای با اعداد طلایی، مناسب هدر/فوتر)'
        );
        echo '<div class="card" style="border:2px solid var(--blue)"><h2>تنظیمات باکس مشاهده آمار تب‌بندی‌شده (خودکار از آمار پایگاه)</h2>
            <form method="post" action="?p=savecms">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="layout">
                <p style="font-size:12.8px;margin:0 0 10px">تعیین کنید کدام تب‌های آماری به صورت زنده و خودکار از آمار سامانه در باکس آمار صفحه اصلی پرتال نمایش داده شوند:</p>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="stats_tabs_arr[]" value="overview"' . (in_array('overview', $cur_st_tabs) ? ' checked' : '') . '> تب آمار کلی (عناوین، نسخه‌ها، اعضا و امانات)</label>
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="stats_tabs_arr[]" value="locs"' . (in_array('locs', $cur_st_tabs) ? ' checked' : '') . '> تب آمار موجودی به تفکیک هر کتابخانه</label>
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="stats_tabs_arr[]" value="types"' . (in_array('types', $cur_st_tabs) ? ' checked' : '') . '> تب آمار به تفکیک نوع منبع (کتاب، مقاله، پایان‌نامه)</label>
                </div>
                <div style="margin:14px 0 12px">
                    <label>شیوه نمایش باکس آمار در صفحه اصلی پرتال:</label>
                    <select name="stats_box_style" style="min-width:320px">';
                    foreach ($sb_styles as $ssk => $ssv) {
                        echo '<option value="' . h($ssk) . '"' . ($cur_sb_style === $ssk ? ' selected' : '') . '>' . h($ssv) . '</option>';
                    }
        echo '          </select>
                    <p style="font-size:12px;margin:6px 0 0;opacity:.8">همه شیوه‌ها داده‌ها را به‌صورت زنده و خودکار از آمار پایگاه (جداول منابع، نسخه‌ها، اعضا و امانات) می‌خوانند و با تغییر محتوای پایگاه بلافاصله بروزرسانی می‌شوند.</p>
                </div>
                <button class="btn ok">ذخیره تنظیمات تب‌ها و شیوه نمایش باکس آمار</button>
            </form>
        </div>';

        $edit_bid = (int)(isset($_GET['edit_b']) ? $_GET['edit_b'] : 0);
        $eb = array('id'=>0, 'title'=>'', 'pos'=>'main', 'ord'=>10, 'content'=>'');
        if ($edit_bid) {
            $st_eb = $db->prepare("SELECT * FROM portal_boxes WHERE id=? AND btype='custom'");
            $st_eb->execute(array($edit_bid));
            $f_eb = $st_eb->fetch(PDO::FETCH_ASSOC);
            if ($f_eb) $eb = $f_eb;
        }

        echo '<div class="card"><h2>مدیریت چیدمان، موقعیت (ستون اصلی راست / ستون کناری چپ) و ترتیب باکس‌های پرتال</h2>
            <form method="post" action="?p=save_box_layout"><input type="hidden" name="t" value="' . $tok . '">
                <table><tr><th>فعال</th><th>عنوان نمایشی باکس</th><th>نوع باکس</th><th>موقعیت در پرتال</th><th>شماره ترتیب نمایش</th><th>عملیات</th></tr>';
                foreach ($db->query("SELECT * FROM portal_boxes ORDER BY pos ASC, ord ASC, id ASC") as $bx) {
                    echo '<tr>
                        <td><input type="hidden" name="b_id[]" value="' . $bx['id'] . '"><input type="checkbox" name="b_active[' . $bx['id'] . ']" value="1"' . ($bx['active'] ? ' checked' : '') . '></td>
                        <td><input name="b_title[]" value="' . h($bx['title']) . '" style="min-width:160px"></td>
                        <td><span class="badge">' . ($bx['btype'] === 'custom' ? 'باکس سفارشی' : 'سیستمی (' . h($bx['bkey']) . ')') . '</span></td>
                        <td><select name="b_pos[]" style="min-width:140px">
                            <option value="main"' . ($bx['pos'] === 'main' ? ' selected' : '') . '>ستون اصلی (راست / مرکز)</option>
                            <option value="side"' . ($bx['pos'] === 'side' ? ' selected' : '') . '>نوار کناری (سمت چپ)</option>
                        </select></td>
                        <td><input type="number" name="b_ord[]" value="' . (int)$bx['ord'] . '" style="width:75px"></td>
                        <td>';
                        if ($bx['btype'] === 'custom') {
                            echo '<a class="btn-sm pri" href="?p=cms&box=layout&edit_b=' . $bx['id'] . '">ویرایش متن</a> ';
                        } else {
                            echo '<a class="btn-sm" href="?p=cms&box=boxes">ویرایش محتوا</a>';
                        }
                    echo '</td></tr>';
                }
        echo '  </table><br><button class="btn pri">ذخیره چیدمان و موقعیت باکس‌ها</button>
            </form>
        </div>';

        echo '<div class="card"><h2>' . ($eb['id'] ? 'ویرایش باکس سفارشی' : 'افزودن باکس جدید به پرتال') . '</h2>
            <form method="post" action="?p=add_custom_box">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="edit_id" value="' . $eb['id'] . '">
                <div class="grid">
                    <div><label>عنوان باکس جدید *</label><input name="title" value="' . h($eb['title']) . '" required placeholder="مثلاً: قوانین امانت یا سخن روز"></div>
                    <div><label>موقعیت نمایش در پرتال</label><select name="pos">
                        <option value="main"' . ($eb['pos'] === 'main' ? ' selected' : '') . '>ستون اصلی (راست / مرکز)</option>
                        <option value="side"' . ($eb['pos'] === 'side' ? ' selected' : '') . '>نوار کناری (سمت چپ)</option>
                    </select></div>
                    <div><label>ترتیب نمایش (عدد کمتر = بالاتر)</label><input type="number" name="ord" value="' . (int)$eb['ord'] . '"></div>
                </div><br>
                <label>محتوای باکس (ویرایشگر کامل):</label>
                ' . rich_editor('cbox_rte', 'content', $eb['content']) . '<br>
                <button class="btn ok">' . ($eb['id'] ? 'ذخیره تغییرات باکس' : 'افزودن باکس به پرتال') . '</button>
                ' . ($eb['id'] ? '<a class="btn" href="?p=cms&box=layout">انصراف</a> ' . post_btn('del_custom_box', 'حذف این باکس', array('id'=>$eb['id']), 'btn dng') : '') . '
            </form>
        </div>';


    } elseif ($box === 'boxes') {
        $sel_b = isset($_GET['sel_b']) ? $_GET['sel_b'] : 'about';
        $box_titles = array(
            'about'    => 'باکس درباره ما (ویرایشگر پیشرفته HTML)',
            'contact'  => 'باکس تماس با ما (ویرایشگر پیشرفته HTML)',
            'services' => 'باکس خدمات کتابخانه',
            'links'    => 'باکس پیوندهای مفید'
        );
        foreach ($db->query("SELECT id, title FROM portal_boxes WHERE btype='custom' ORDER BY id") as $cbx) $box_titles['cust_' . $cbx['id']] = 'باکس سفارشی: ' . $cbx['title'];
        if (!isset($box_titles[$sel_b])) $sel_b = 'about';
        echo '<div class="card"><h2>انتخاب باکس سیستمی جهت ویرایش محتوا</h2>
            <form method="get" class="row" style="margin-bottom:18px"><input type="hidden" name="p" value="cms"><input type="hidden" name="box" value="boxes">
                <div style="flex:1"><label>باکس مورد نظر را انتخاب کنید:</label><select name="sel_b" onchange="this.form.submit()">';
                foreach ($box_titles as $bk => $bv) echo '<option value="' . $bk . '"' . ($sel_b === $bk ? ' selected' : '') . '>' . $bv . '</option>';
        echo '  </select></div><button class="btn">بارگذاری باکس</button></form>';

        if (strpos($sel_b, 'cust_') === 0) {
            $cbr = $db->prepare("SELECT * FROM portal_boxes WHERE id=? AND btype='custom'");
            $cbr->execute(array((int)substr($sel_b, 5)));
            $cb = $cbr->fetch(PDO::FETCH_ASSOC);
            if (!$cb) $cb = array('id' => 0, 'title' => '', 'pos' => 'main', 'ord' => 10, 'content' => '');
            echo '<form method="post" action="?p=add_custom_box"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="edit_id" value="' . $cb['id'] . '"><input type="hidden" name="back" value="boxes"><div class="grid"><div><label>عنوان باکس</label><input name="title" value="' . h($cb['title']) . '" required></div><div><label>موقعیت</label><select name="pos"><option value="main"' . ($cb['pos'] === 'main' ? ' selected' : '') . '>ستون اصلی</option><option value="side"' . ($cb['pos'] === 'side' ? ' selected' : '') . '>نوار کناری</option></select></div><div><label>ترتیب</label><input type="number" name="ord" value="' . (int)$cb['ord'] . '"></div></div><br><label>محتوای باکس (ویرایشگر کامل):</label>' . rich_editor('cbox_' . $cb['id'], 'content', $cb['content']);
        } else {
    echo '<form method="post" action="?p=savecms">
                  <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="boxes&sel_b=' . h($sel_b) . '">';
    
            if ($sel_b === 'about' || $sel_b === 'contact') {
                echo '<label>' . h($box_titles[$sel_b]) . '</label>' . rich_editor('rte_' . $sel_b, $sel_b, get_cms($sel_b));
            } else {
                $hints = array(
                    'services' => 'هر خدمت را در یک خط جداگانه بنویسید.',
                    'links'    => 'فرمت هر خط: عنوان پیوند | آدرس اینترنتی'
                );
                echo '<label>' . h($box_titles[$sel_b]) . ' — <small>' . h($hints[$sel_b]) . '</small></label>
                      <textarea name="' . h($sel_b) . '" rows="8">' . h(get_cms($sel_b)) . '</textarea>';
            }
            }
        echo '<br><br><button class="btn pri">ذخیره محتوای این باکس</button></form></div>';

    } elseif ($box === 'chat') {
        $cm = get_cms('chat_mode'); if ($cm === '') $cm = 'plain';
        $cp = chat_cfg('chat_provider', 'anthropic');
        $has_env = (bool)getenv('ASEMAN_AI_KEY');
        $has_key = $has_env || get_cms('chat_key') !== '';
        if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
        echo '<div class="card"><h2>گفتگو با منابع در پرتال</h2><form method="post" action="?p=save_chat"><input type="hidden" name="t" value="' . $tok . '"><div class="grid">
            <div><label>حالت گفتگو</label><select name="chat_mode"><option value="off"' . ($cm === 'off' ? ' selected' : '') . '>غیرفعال (دکمهٔ گفتگو نمایش داده نمی‌شود)</option><option value="plain"' . ($cm === 'plain' ? ' selected' : '') . '>بدون هوش مصنوعی (جستجوی هوشمند در فهرست + پاسخ‌های آماده)</option><option value="ai"' . ($cm === 'ai' ? ' selected' : '') . '>با هوش مصنوعی (پاسخ طبیعی بر پایهٔ فهرست منابع)</option></select></div>
            <div><label>پیام خوش‌آمد</label><input name="chat_welcome" value="' . h(chat_cfg('chat_welcome', 'سلام! نام کتاب، نویسنده یا موضوع مورد نظرتان را بنویسید تا در فهرست کتابخانه جستجو کنم.')) . '"></div>
            <div><label>سقف روزانهٔ پاسخ‌های هوش مصنوعی (کل پرتال)</label><input type="number" name="chat_daily" value="' . h(chat_cfg('chat_daily', '300')) . '"></div>
            <div><label>سقف پیام هر بازدیدکننده در ۱۰ دقیقه</label><input type="number" name="chat_limit" value="' . h(chat_cfg('chat_limit', '15')) . '"></div></div>
            <h3 style="margin-top:16px">تنظیمات هوش مصنوعی (فقط برای حالت «با هوش مصنوعی»)</h3><div class="grid">
            <div><label>ارائه‌دهنده</label><select name="chat_provider"><option value="anthropic"' . ($cp === 'anthropic' ? ' selected' : '') . '>Anthropic (Claude)</option><option value="openai"' . ($cp !== 'anthropic' ? ' selected' : '') . '>سازگار با OpenAI (OpenAI، Ollama، LM Studio، سرویس‌های داخلی...)</option></select></div>
            <div><label>نشانی سرویس (خالی = پیش‌فرض ارائه‌دهنده)</label><input dir="ltr" name="chat_endpoint" value="' . h(get_cms('chat_endpoint')) . '" placeholder="https://api.anthropic.com/v1/messages  یا  http://localhost:11434/v1"></div>
            <div><label>نام مدل</label><input dir="ltr" name="chat_model" value="' . h(get_cms('chat_model')) . '" placeholder="claude-haiku-5-5"></div>
            <div><label>کلید API ' . ($has_key ? '(ذخیره شده )' : '(تنظیم نشده)') . '</label><input dir="ltr" type="password" name="chat_key" placeholder="' . ($has_key ? '•••••••• (برای تغییر، کلید جدید را وارد کنید)' : 'sk-...') . '"' . ($has_env ? ' disabled' : '') . '>' . ($has_env ? '<small>کلید از متغیر محیطی ASEMAN_AI_KEY خوانده می‌شود.</small>' : '') . '</div></div>
            <label style="margin-top:10px">دستور سیستمی (اختیاری؛ خالی = متن پیش‌فرض امن)</label><textarea name="chat_system" rows="4">' . h(get_cms('chat_system')) . '</textarea>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button class="btn ok">ذخیره</button><button class="btn" name="test" value="1">ذخیره و آزمایش اتصال</button><label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="clear_key" value="1"> حذف کلید ذخیره‌شده</label></div></form>
            <p style="font-size:12.5px;line-height:1.9;margin-top:12px"><b>نکات امنیتی:</b> کلید در پایگاه‌داده ذخیره می‌شود؛ ترجیحاً آن را با متغیر محیطی <code>ASEMAN_AI_KEY</code> تنظیم کنید و فایل <code>library.db</code> را بیرون از ریشهٔ وب بگذارید. در حالت هوش مصنوعی، فقط پرسش کاربر و چند رکورد بازیابی‌شده از فهرست به سرویس ارسال می‌شود (نه اطلاعات اعضا). هزینهٔ هر پاسخ را با «سقف روزانه» کنترل کنید. اگر سرویس در دسترس نباشد، چت خودکار به حالت بدون هوش مصنوعی برمی‌گردد.</p></div>';
        echo '<div class="card"><h2>آخرین پرسش‌های کاربران</h2><table><tr><th>زمان</th><th>پرسش</th><th>حالت</th><th>منابع یافت‌شده</th></tr>';
        foreach ($db->query("SELECT * FROM chat_log ORDER BY id DESC LIMIT 25") as $cl) echo '<tr><td>' . jdate(date('Y-m-d H:i:s', $cl['ts']), true) . '</td><td>' . h($cl['q']) . '</td><td>' . ($cl['mode'] === 'ai' ? 'هوش مصنوعی' : 'بدون AI') . '</td><td>' . tr_num($cl['nres']) . '</td></tr>';
        echo '</table></div>';

    } elseif ($box === 'news') {
        $edit_n = (int)(isset($_GET['edit_n']) ? $_GET['edit_n'] : 0);
        $en = array('id'=>0, 'title'=>'', 'subtitle'=>'', 'body'=>'', 'img'=>'', 'pub'=>jdate('now'), 'active'=>1);
        if ($edit_n) {
            $sn = $db->prepare("SELECT * FROM news WHERE id=?");
            $sn->execute(array($edit_n));
            $fn = $sn->fetch(PDO::FETCH_ASSOC);
            if ($fn) $en = $fn;
        }
        echo '<div class="card"><h2>' . ($en['id'] ? 'ویرایش خبر' : 'افزودن خبر جدید') . '</h2>
            <form method="post" action="?p=save_news" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $en['id'] . '"><input type="hidden" name="old_img" value="' . h($en['img']) . '">
                <div class="grid">
                    <div><label>تیتر خبر *</label><input name="title" value="' . h($en['title']) . '" required></div>
                    <div><label>سوتیتر</label><input name="subtitle" value="' . h($en['subtitle']) . '"></div>
                    <div><label>تاریخ انتشار (شمسی)</label><input name="pub" value="' . h(tr_num($en['pub'])) . '"></div>
                    <div><label>عکس خبر (نمایش کوچک کنار خبر)</label><input type="file" name="img" accept="image/*">' . ($en['img'] ? '<div style="margin-top:6px"><img src="' . h($en['img']) . '" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:12px"> <label style="display:inline"><input type="checkbox" name="rm_img" value="1"> حذف عکس</label></div>' : '') . '</div>
                    <div><label>وضعیت</label><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="active" value="1"' . ($en['active'] ? ' checked' : '') . '> نمایش در پرتال</label></div>
                </div><br>
                <label>متن خبر (ویرایشگر کامل):</label>' . rich_editor('news_rte', 'body', $en['body']) . '<br>
                <button class="btn ok">' . ($en['id'] ? 'ذخیره تغییرات خبر' : 'انتشار خبر') . '</button>' . ($en['id'] ? ' <a class="btn" href="?p=cms&box=news">انصراف</a>' : '') . '
            </form></div>';
        echo '<div class="card"><h2>فهرست اخبار</h2><table><tr><th>عکس</th><th>تیتر</th><th>سوتیتر</th><th>تاریخ</th><th>وضعیت</th><th>عملیات</th></tr>';
        foreach ($db->query("SELECT * FROM news ORDER BY id DESC") as $nw) {
            $dt = ($nw['pub'] !== null && $nw['pub'] !== '') ? $nw['pub'] : jdate($nw['created']);
            echo '<tr><td>' . ($nw['img'] ? '<img src="' . h($nw['img']) . '" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:10px">' : '-') . '</td><td><b>' . h($nw['title']) . '</b></td><td>' . h($nw['subtitle']) . '</td><td>' . h(tr_num($dt)) . '</td><td>' . ($nw['active'] ? 'فعال' : 'غیرفعال') . '</td>
                <td style="white-space:nowrap"><a class="btn-sm pri" href="?p=cms&box=news&edit_n=' . $nw['id'] . '">ویرایش</a> ' . post_btn('del_news', 'حذف', array('id'=>$nw['id']), 'btn-sm dng') . '</td></tr>';
        }
        echo '</table></div>';

    } elseif ($box === 'chart') {
        echo '<form class="card" method="post" action="?p=savecms"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="chart">
            <h2>مدیریت داده‌های نمودار رشد منابع (ماه‌به‌ماه)</h2>
            <p style="font-size:13px">در هر خط نام ماه و تعداد کل منابع را با علامت <code>|</code> جدا کنید (مثال: <code>فروردین|120</code>):</p>
            <textarea name="growth_chart" rows="12" style="direction:rtl;font-family:monospace;font-size:14px">' . h(get_cms('growth_chart')) . '</textarea><br><br>
            <button class="btn pri">ذخیره و بروزرسانی نمودار پرتال</button>
        </form>';

        } elseif ($box === 'slider') {
        $sl_act = get_cms('slider_active') !== '0';
        $sl_cnt_cur = (int)get_cms('slider_count');
        if ($sl_cnt_cur < 1 || $sl_cnt_cur > 8) $sl_cnt_cur = 3;
        $sl_style_cur = get_cms('slider_style') === 'transparent' ? 'transparent' : 'frosted';
        echo '<form class="card" method="post" action="?p=savecms" enctype="multipart/form-data">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="active_box" value="slider">
            <h2>تنظیمات اسلایدر عریض بالای صفحه اصلی پرتال (تعداد دلخواه اسلاید + حالت مات/شفاف)</h2>
            <div style="margin-bottom:16px;padding:12px;background:var(--ice);border:1px solid var(--line);border-radius:10px">
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;color:var(--blue);margin:0;cursor:pointer">
                    <input type="checkbox" name="slider_active" value="1"' . ($sl_act ? ' checked' : '') . '>
                    <b>فعال‌سازی نمایش اسلایدر عریض در بالای صفحه اصلی پرتال (جایگزین باکس آبی قبلی)</b>
                </label>
            </div>
            <div class="grid" style="margin-bottom:16px">
                <div><label>تعداد اسلایدهای قابل نمایش (۱ تا ۸)</label>
                    <select name="slider_count" onchange="var n=parseInt(this.value)||3;for(var i=1;i<=8;i++){var el=document.getElementById(\'sl_box\'+i);if(el)el.style.display=(i<=n)?\'\':\'none\';}">';
                    for ($si = 1; $si <= 8; $si++) {
                        echo '<option value="' . $si . '"' . ($sl_cnt_cur === $si ? ' selected' : '') . '>' . tr_num($si) . ' اسلاید</option>';
                    }
        echo '      </select></div>
                <div><label>حالت نمایش اسلایدها</label><select name="slider_style">
                    <option value="frosted"' . ($sl_style_cur === 'frosted' ? ' selected' : '') . '>مات با کلید (پوشش رنگی نیمه‌مات + عنوان، توضیح و دکمه روی هر اسلاید)</option>
                    <option value="transparent"' . ($sl_style_cur === 'transparent' ? ' selected' : '') . '>شفاف (فقط تصویر، بدون هیچ متن و محتوایی)</option>
                </select></div>
            </div>
            <p style="font-size:12.5px;margin:0 0 14px;color:var(--navy2)">نکته: در حالت «شفاف» هیچ عنوان یا دکمه‌ای روی اسلایدها نمایش داده نمی‌شود و فقط خودِ تصویر (با لینک اختیاری کل اسلاید) چرخش می‌کند.</p>';
        for ($si = 1; $si <= 8; $si++) {
            $cur_img = get_cms('slider_img' . $si);
            echo '<div id="sl_box' . $si . '" style="border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:14px;background:#f8fafc' . ($si > $sl_cnt_cur ? ';display:none' : '') . '">
                <h3 style="margin:0 0 10px;font-size:14px;color:var(--navy2)">اسلاید شماره ' . tr_num($si) . '</h3>
                <div class="grid">
                    <div><label>عنوان اسلاید ' . tr_num($si) . '</label><input name="slider_title' . $si . '" value="' . h(get_cms('slider_title' . $si)) . '"></div>
                    <div><label>زیرعنوان / توضیح کوتاه</label><input name="slider_sub' . $si . '" value="' . h(get_cms('slider_sub' . $si)) . '"></div>
                    <div><label>لینک دکمه/اسلاید (اختیاری)</label><input name="slider_link' . $si . '" dir="ltr" value="' . h(get_cms('slider_link' . $si)) . '" placeholder="?p=home&smode=simple"></div>
                    <div><label>آدرس تصویر اسلاید ' . tr_num($si) . '</label><input name="slider_img' . $si . '" dir="ltr" value="' . h($cur_img) . '" placeholder="uploads/slide' . $si . '.jpg"></div>
                    <div><label>یا آپلود تصویر جدید (JPG/PNG/WebP)</label><input type="file" name="slider_file' . $si . '" accept="image/*"></div>
                </div>
                ' . ($cur_img !== '' ? '<div style="margin-top:8px"><img src="' . h($cur_img) . '" style="height:65px;border-radius:8px;object-fit:cover;border:1px solid var(--line)"></div>' : '') . '
            </div>';
        }
        echo '<button class="btn pri">ذخیره تنظیمات اسلایدر عریض</button></form>';

    } elseif ($box === 'pages') {
        $edit_pid = (int)($_GET['edit_p'] ?? 0);
        $epg = array('id' => 0, 'slug' => '', 'title' => '', 'body' => '', 'active' => 1);
        if ($edit_pid > 0) {
            $st_ep = $db->prepare("SELECT * FROM pages WHERE id=?");
            $st_ep->execute(array($edit_pid));
            $f_ep = $st_ep->fetch(PDO::FETCH_ASSOC);
            if ($f_ep) $epg = $f_ep;
        }
        echo '<div class="card"><h2>' . ($epg['id'] ? 'ویرایش صفحه تکی «' . h($epg['title']) . '»' : 'ساخت صفحه تکی جدید با لینک یکتا و ویرایشگر حرفه‌ای') . '</h2>
            <p style="font-size:12.8px;margin:0 0 12px">هر صفحه‌ای که اینجا می‌سازید دارای یک لینک یکتا (<code>?p=page&slug=نامک</code>) است که می‌توانید آن را در منوی هدر، منوی فوتر، اسلایدر یا هر جای دیگر متصل کنید.</p>
            <form method="post" action="?p=save_page">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $epg['id'] . '">
                <div class="grid">
                    <div><label>عنوان صفحه *</label><input name="title" value="' . h($epg['title']) . '" required placeholder="مثلاً: راهنمای عضویت و امانت"></div>
                    <div><label>شناسه / نامک لینک یکتا (Slug - انگلیسی یا فارسی)</label><input name="slug" dir="ltr" class="no-num" value="' . h($epg['slug']) . '" placeholder="مثلاً: rules یا راهنما"></div>
                    <div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="active" value="1"' . ($epg['active'] ? ' checked' : '') . '> صفحه فعال و قابل مشاهده باشد</label></div>
                </div><br>
                <label>محتوای کامل صفحه (ویرایشگر حرفه‌ای HTML):</label>
                ' . rich_editor('page_rte', 'body', $epg['body'], 320) . '<br>
                <button class="btn ok">' . ($epg['id'] ? 'ذخیره تغییرات صفحه' : 'ایجاد صفحه و تولید لینک یکتا') . '</button>
                ' . ($epg['id'] ? ' <a class="btn" href="?p=cms&box=pages">انصراف</a>' : '') . '
            </form>
        </div>';

        echo '<div class="card"><h2>فهرست صفحات تکی ساخته‌شده و لینک یکتای هر صفحه</h2>
            <table><tr><th>عنوان صفحه</th><th>لینک یکتا (جهت اتصال به منو یا سایت)</th><th>وضعیت</th><th>تاریخ ایجاد</th><th>عملیات</th></tr>';
        foreach ($db->query("SELECT * FROM pages ORDER BY id DESC") as $pr) {
            $u_lnk = '?p=page&slug=' . urlencode($pr['slug']);
            echo '<tr>
                <td><b>' . h($pr['title']) . '</b></td>
                <td dir="ltr"><code class="no-num">' . h($u_lnk) . '</code> <a class="btn-sm ok" href="' . h($u_lnk) . '" target="_blank">مشاهده صفحه</a></td>
                <td>' . ($pr['active'] ? '<span class="badge" style="background:#d1fae5;color:#065f46">فعال</span>' : '<span class="badge">غیرفعال</span>') . '</td>
                <td>' . jdate($pr['created']) . '</td>
                <td style="white-space:nowrap"><a class="btn-sm pri" href="?p=cms&box=pages&edit_p=' . $pr['id'] . '">ویرایش</a> ' . post_btn('del_page', 'حذف', array('id' => $pr['id']), 'btn-sm dng') . '</td>
            </tr>';
        }
        echo '</table></div>';

    } elseif ($box === 'menus') {
        $edit_mid = (int)($_GET['edit_m'] ?? 0);
        $em = array('id' => 0, 'parent_id' => 0, 'pos' => 'footer', 'title' => '', 'target_type' => 'url', 'target_val' => '', 'ord' => 10, 'active' => 1);
        if ($edit_mid > 0) {
            $st_em = $db->prepare("SELECT * FROM portal_menus WHERE id=?");
            $st_em->execute(array($edit_mid));
            $f_em = $st_em->fetch(PDO::FETCH_ASSOC);
            if ($f_em) $em = $f_em;
        }
        $all_pages = $db->query("SELECT id, slug, title FROM pages ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $all_boxes = $db->query("SELECT id, title, btype FROM portal_boxes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $parent_candidates = $db->query("SELECT id, title, pos FROM portal_menus WHERE parent_id=0 ORDER BY pos, ord, id")->fetchAll(PDO::FETCH_ASSOC);

        echo '<div class="card"><h2>' . ($em['id'] ? 'ویرایش آیتم منو «' . h($em['title']) . '»' : 'افزودن آیتم به منوی فوتر یا منوی آبشاری هدر (اتصال صفحه تکی، باکس یا لینک)') . '</h2>
            <p style="font-size:12.8px;margin:0 0 12px">در این بخش می‌توانید آیتم‌های منوی فوتر و منوی بالای سایت را بسازید، آن‌ها را به <b>صفحات تکی</b> یا <b>باکس‌های پرتال</b> متصل کنید و با انتخاب «منوی والد»، <b>زیرمنوی آبشاری</b> ایجاد نمایید:</p>
            <form method="post" action="?p=save_menu_item">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="id" value="' . $em['id'] . '">
                <div class="grid">
                    <div><label>عنوان آیتم منو *</label><input name="title" value="' . h($em['title']) . '" required placeholder="مثلاً: راهنمای بخش مرجع"></div>
                    <div><label>جایگاه نمایش منو</label><select name="pos">
                        <option value="footer"' . ($em['pos'] === 'footer' ? ' selected' : '') . '>منوی فوتر (پایین پرتال)</option>
                        <option value="header"' . ($em['pos'] === 'header' ? ' selected' : '') . '>منوی آبشاری هدر (بالای پرتال)</option>
                    </select></div>
                    <div><label>منوی والد (برای ساخت منوی آبشاری / زیرمنو)</label><select name="parent_id">
                        <option value="0">— سرشاخه اصلی (بدون والد) —</option>';
                        foreach ($parent_candidates as $pc) {
                            if ($pc['id'] == $em['id']) continue;
                            echo '<option value="' . $pc['id'] . '"' . ($em['parent_id'] == $pc['id'] ? ' selected' : '') . '>زیرمجموعهٔ: ' . h($pc['title']) . ' (' . ($pc['pos'] === 'header' ? 'هدر' : 'فوتر') . ')</option>';
                        }
        echo '      </select></div>
                    <div><label style="color:var(--blue)">نوع اتصال مقصد</label><select name="target_type" id="menu_target_type" onchange="swMenuTargetType(this.value)">
                        <option value="page"' . ($em['target_type'] === 'page' ? ' selected' : '') . '>اتصال به صفحه تکی</option>
                        <option value="box"' . ($em['target_type'] === 'box' ? ' selected' : '') . '>اتصال به باکس پرتال</option>
                        <option value="url"' . ($em['target_type'] === 'url' ? ' selected' : '') . '>لینک مستقیم / آدرس دلخواه</option>
                    </select></div>
                    <div id="mt_wrap_page" style="' . ($em['target_type'] === 'page' ? '' : 'display:none') . '"><label>انتخاب صفحه تکی</label><select name="target_page">';
                        foreach ($all_pages as $ap) {
                            echo '<option value="' . h($ap['slug']) . '"' . ($em['target_val'] === $ap['slug'] ? ' selected' : '') . '>' . h($ap['title']) . ' (' . h($ap['slug']) . ')</option>';
                        }
        echo '      </select></div>
                    <div id="mt_wrap_box" style="' . ($em['target_type'] === 'box' ? '' : 'display:none') . '"><label>انتخاب باکس پرتال</label><select name="target_box">';
                        foreach ($all_boxes as $ab) {
                            echo '<option value="' . $ab['id'] . '"' . ($em['target_val'] == $ab['id'] ? ' selected' : '') . '>' . h($ab['title']) . '</option>';
                        }
        echo '      </select></div>
                    <div id="mt_wrap_url" style="' . ($em['target_type'] === 'url' ? '' : 'display:none') . '"><label>آدرس لینک مستقیم</label><input name="target_url" dir="ltr" class="no-num" value="' . h($em['target_val']) . '" placeholder="?p=home&smode=adv یا https://..."></div>
                    <div><label>ترتیب نمایش</label><input type="number" name="ord" value="' . (int)$em['ord'] . '"></div>
                    <div style="display:flex;align-items:end"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="active" value="1"' . ($em['active'] ? ' checked' : '') . '> فعال</label></div>
                </div><br>
                <button class="btn ok">' . ($em['id'] ? 'ذخیره تغییرات منو' : 'افزودن به منو') . '</button>
                ' . ($em['id'] ? ' <a class="btn" href="?p=cms&box=menus">انصراف</a>' : '') . '
            </form>
            <script>
            function swMenuTargetType(v){
                document.getElementById("mt_wrap_page").style.display = (v==="page")?"block":"none";
                document.getElementById("mt_wrap_box").style.display = (v==="box")?"block":"none";
                document.getElementById("mt_wrap_url").style.display = (v==="url")?"block":"none";
            }
            </script>
        </div>';

        echo '<div class="card"><h2>فهرست آیتم‌های منوی فوتر و هدر (ساختار درختی و آبشاری)</h2>
            <table><tr><th>عنوان منو</th><th>جایگاه</th><th>سطح / والد</th><th>مقصد اتصال</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr>';
        $pm_all = $db->query("SELECT m.*, p.title AS parent_title FROM portal_menus m LEFT JOIN portal_menus p ON p.id=m.parent_id ORDER BY m.pos ASC, COALESCE(NULLIF(m.parent_id,0), m.id) ASC, m.parent_id ASC, m.ord ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pm_all as $pm) {
            $href_m = resolve_portal_menu_href($pm);
            echo '<tr>
                <td><b>' . ($pm['parent_id'] > 0 ? '↳ ' : '') . h($pm['title']) . '</b></td>
                <td><span class="badge">' . ($pm['pos'] === 'header' ? 'منوی هدر' : 'منوی فوتر') . '</span></td>
                <td>' . ($pm['parent_id'] > 0 ? 'زیرمنوی «' . h($pm['parent_title']) . '»' : '<b>سرشاخه اصلی</b>') . '</td>
                <td dir="ltr"><a href="' . h($href_m) . '" target="_blank"><code class="no-num">' . h($href_m) . '</code></a></td>
                <td>' . tr_num($pm['ord']) . '</td>
                <td>' . ($pm['active'] ? 'فعال' : 'غیرفعال') . '</td>
                <td style="white-space:nowrap"><a class="btn-sm pri" href="?p=cms&box=menus&edit_m=' . $pm['id'] . '">ویرایش</a> ' . post_btn('del_menu_item', 'حذف', array('id' => $pm['id']), 'btn-sm dng') . '</td>
            </tr>';
        }
        echo '</table></div>';

    } elseif ($box === 'gallery') {
        echo '<div class="card"><h2>افزودن تصویر جدید به گالری پرتال (نمایش در نوار کناری سمت چپ زیر باکس آمار)</h2>
            <form method="post" action="?p=add_gal" enctype="multipart/form-data" class="row"><input type="hidden" name="t" value="' . $tok . '">
                <div style="flex:1"><label>عنوان یا توضیح تصویر *</label><input name="title" required placeholder="مثلاً: سالن مطالعه بخش مرجع"></div>
                <div style="flex:1"><label>انتخاب فایل تصویر (JPG, PNG, WebP) *</label><input type="file" name="img" accept="image/*" required></div>
                <button class="btn pri">آپلود و نمایش در گالری</button>
            </form>
        </div>';
        $gals = $db->query("SELECT * FROM gallery ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo '<div class="card"><h2>تصاویر فعلی گالری</h2><div class="gal-grid">';
        foreach ($gals as $g) {
            echo '<div class="gal-item"><img src="' . h($g['img']) . '"><div>' . h($g['title']) . '<br><br>' . post_btn('del_gal', 'حذف', array('id'=>$g['id']), 'btn-sm dng') . '</div></div>';
        }
        echo '</div></div>';
    }
} elseif ($p === 'backup') {
    $allowed_locs = allowed_locations();
    echo '<h1>پشتیبان‌گیری و بازیابی اطلاعات (ویژه هر کتابخانه و کل سیستم)</h1>';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'db_restored') echo '<div class="ok-msg">فایل دیتابیس کامل سیستم با موفقیت بازیابی و جایگزین شد.</div>';
        elseif (strpos($_GET['msg'], 'json_') === 0) {
            $pts = explode('_', $_GET['msg']);
            echo '<div class="ok-msg">بازیابی پشتیبان انجام شد: تعداد ' . tr_num($pts[1]) . ' منبع و ' . tr_num($pts[2]) . ' عضو با موفقیت بازیابی شدند.</div>';
        }
    }

    echo '<div class="grid">';
    if (is_super_admin()) {
        echo '<div class="card" style="border:2px solid var(--blue)">
            <h2>پشتیبان‌گیری کامل از کل سیستم و تمام محل‌ها (ویژه مدیر کل)</h2>
            <p style="font-size:13px;line-height:1.8">شامل تمامی منابع، اعضا، گردش امانات، تنظیمات پرتال، کاربران و تمام کتابخانه‌ها:</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
                <a class="btn pri" href="?p=dl_full_db">دانلود فایل دیتابیس کامل (library.db)</a>
                <a class="btn ok" href="?p=dl_json_bk&loc=all">دانلود فایل پشتیبان جامع کل سیستم (.json)</a>
            </div>
        </div>';
    }

    echo '<div class="card" style="border:2px solid var(--ok)">
        <h2>پشتیبان‌گیری اختصاصی از یک کتابخانه</h2>
        <p style="font-size:13px;line-height:1.8">دریافت فایل پشتیبان (شامل منابع، اعضا، امانات و سفارش‌ها) مربوط به کتابخانه انتخابی:</p>
        <form method="get" class="row"><input type="hidden" name="p" value="dl_json_bk">
            <div style="flex:1"><label>انتخاب کتابخانه جهت پشتیبان‌گیری</label><select name="loc">';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
    echo '  </select></div>
            <button class="btn ok">دانلود پشتیبان این محل (.json)</button>
        </form>
    </div></div>';

    echo '<div class="card">
        <h2>بازیابی اطلاعات (Restore) از فایل پشتیبان</h2>
        <p style="font-size:13px;line-height:1.8">می‌توانید فایل پشتیبان <code>.json</code> (مربوط به یک کتابخانه یا کل سیستم) و یا فایل دیتابیس <code>.db</code> (ویژه مدیر کل) را بارگذاری و بازیابی نمایید:</p>
        <form method="post" action="?p=restore_bk" enctype="multipart/form-data" class="row" onsubmit="return confirm(\'آیا از بازیابی اطلاعات این فایل پشتیبان مطمئن هستید؟\')">
            <input type="hidden" name="t" value="' . $tok . '">
            <div style="flex:1"><label>انتخاب فایل پشتیبان (.json' . (is_super_admin() ? ' یا .db' : '') . ') *</label><input type="file" name="bk_file" accept=".json' . (is_super_admin() ? ',.db' : '') . '" required></div>
            <div style="width:260px"><label>کتابخانه مقصد برای رکوردهای بازیابی‌شده</label><select name="target_loc">';
            if (!is_loc_restricted()) echo '<option value="keep">حفظ کتابخانه ثبت‌شده در فایل پشتیبان</option>';
            foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">انتقال به کتابخانه: ' . h($lc) . '</option>';
    echo '  </select></div>
            <button class="btn pri">شروع بازیابی اطلاعات</button>
        </form>
    </div>';
} elseif ($p === 'users') {
    $edit_uid = (int)(isset($_GET['edit']) ? $_GET['edit'] : 0);
    $eu = array('id'=>0, 'u'=>'', 'perms'=>'all', 'uloc'=>'all');
    if ($edit_uid) {
        $st_eu = $db->prepare("SELECT * FROM users WHERE id=?");
        $st_eu->execute(array($edit_uid));
        $f_eu = $st_eu->fetch(PDO::FETCH_ASSOC);
        if ($f_eu) $eu = $f_eu;
    }
    $eu_perms = ($eu['perms'] === 'all' || empty($eu['perms'])) ? array_keys($AllPerms) : explode(',', $eu['perms']);

    echo '<h1>مدیریت کاربران، ویرایش سطوح دسترسی و اختصاص کتابخانه</h1>';
    if (isset($_GET['msg'])) echo '<div class="ok-msg">اطلاعات کاربر، دسترسی‌ها و کتابخانه با موفقیت ذخیره شد.</div>';
    echo '<div class="grid"><div class="card"><h2>' . ($eu['id'] ? 'ویرایش کاربر «' . h($eu['u']) . '»' : 'افزودن کاربر جدید') . '</h2>
    <form method="post" action="?p=saveuser">
        <input type="hidden" name="t" value="' . $tok . '">
        <input type="hidden" name="edit_uid" value="' . $eu['id'] . '">
        <label>نام کاربری *</label><input name="u" value="' . h($eu['u']) . '" required placeholder="مثلاً: user_koodak"><br><br>
        <label>گذرواژه ' . ($eu['id'] ? '(اگر خالی بماند، رمز قبلی حفظ می‌شود)' : '*') . '</label><input type="password" name="pw"' . ($eu['id'] ? '' : ' required') . '><br><br>
        <label style="color:var(--blue)">محدودسازی دسترسی کاربر به کتابخانه *</label>
        <select name="uloc">
            <option value="all"' . ($eu['uloc'] === 'all' ? ' selected' : '') . '>همه کتابخانه‌ها (دسترسی سراسری مدیران)</option>';
            foreach ($Locations as $lc) echo '<option value="' . h($lc) . '"' . ($eu['uloc'] === $lc ? ' selected' : '') . '>فقط کتابخانه: ' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '</select><br><br>
        <label>اختصاص دسترسی به منوها:</label><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-top:8px">';
        foreach ($AllPerms as $k => $v) {
            $chk = in_array($k, $eu_perms) ? ' checked' : '';
            echo '<label style="font-weight:normal"><input type="checkbox" name="perms[]" value="' . $k . '"' . $chk . '> ' . $v . '</label>';
        }
    echo '</div><br>
        <button class="btn pri">' . ($eu['id'] ? 'ذخیره تغییرات کاربر' : 'ثبت کاربر جدید') . '</button>
        ' . ($eu['id'] ? '<a class="btn" href="?p=users">انصراف</a>' : '') . '
    </form></div>';

    echo '<div class="card"><h2>فهرست کاربران سامانه</h2>
    <p style="font-size:12.5px">کاربران پیش‌فرض <b>admin</b> (مدیر کتابخانه) و <b>admin1</b> (مدیر فنی) دارای دسترسی کل هستند.</p>
    <table><tr><th>نام کاربری</th><th>کتابخانه مجاز</th><th>بخش‌های مجاز</th><th>عملیات</th></tr>';
    foreach ($db->query("SELECT * FROM users") as $u) {
        $p_arr = array();
        foreach (explode(',', $u['perms']) as $pk) {
            $p_arr[] = isset($AllPerms[$pk]) ? $AllPerms[$pk] : $pk;
        }
        $is_sup = ($u['u'] === 'admin' || $u['u'] === 'admin1');
        $p_list = ($is_sup || $u['perms'] === 'all') ? 'دسترسی کامل (مدیر کل)' : implode('، ', $p_arr);
        $loc_label = ($is_sup || $u['uloc'] === 'all' || empty($u['uloc'])) ? 'همه کتابخانه‌ها' : $u['uloc'] . ' (پیش‌شماره ' . tr_num(get_loc_prefix($u['uloc'])) . ')';
        echo '<tr><td><b>' . h($u['u']) . '</b></td><td><span class="badge">' . h($loc_label) . '</span></td><td>' . h($p_list) . '</td>
        <td style="white-space:nowrap"><div style="display:inline-flex;gap:4px">
            <a class="btn-sm pri" href="?p=users&edit=' . $u['id'] . '">ویرایش</a>
            ' . (!$is_sup ? post_btn('deluser', 'حذف', array('u'=>$u['u']), 'btn-sm dng') : '') . '
        </div></td></tr>';
    }
    echo '</table></div></div>';
}
