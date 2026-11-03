<?php
// ماژول تبادل فراداده: جستجوی کتابخانه ملی (Z39.50)، ورود و صدور ایزو ۲۷۰۹، ایران‌سیستم، XML و اکسل
if (!defined('ASEMAN_ROOT')) die('Access Denied');

    $allowed_locs = allowed_locations();
    $xq = trim(isset($_GET['xq']) ? $_GET['xq'] : '');
    echo '<h1>تبادل فراداده (ورود - صدور)</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }

    render_iso_preview_box('imp_exp');

    echo '<div class="card" style="border:2px solid var(--blue)"><h2>جستجوی عنوان در کل کتابخانه و ثبت موجودی بدون فهرست‌نویسی مجدد</h2>
        <p style="font-size:12.8px;margin:0 0 12px">عنوان یا شابک را جستجو کنید. اگر عنوان در کتابخانه دیگری فهرست شده باشد، همان رکورد دیده می‌شود و کافی است برای محل خودتان تعداد مجلد را وارد کنید تا شماره ثبت اختصاصی محل شما صادر شود.</p>
        <form method="get" class="row"><input type="hidden" name="p" value="imp_exp">
            <div style="flex:1"><label>عنوان، پدیدآور، ناشر یا شابک</label><input name="xq" value="' . h($xq) . '" placeholder="مثلاً: تحول دیجیتال در حسابداری"></div>
            <button class="btn pri">جستجو در همه کتابخانه‌ها</button></form>';
    if ($xq !== '') {
        $digits = preg_replace('/\D/', '', en_num($xq));
        $par = array(':q' => "%$xq%", ':d' => strlen($digits) >= 4 ? "%$digits%" : '%@@none@@%');
        $st = $db->prepare("SELECT * FROM records WHERE f200a LIKE :q OR f700 LIKE :q OR f200f LIKE :q OR f210c LIKE :q OR f010 LIKE :q OR REPLACE(REPLACE(f010,'-',''),' ','') LIKE :d ORDER BY id DESC LIMIT 30");
        $st->execute($par);
        $found = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$found) {
            echo '<p class="warn" style="margin-top:12px">عنوانی با این مشخصات در هیچ محلی یافت نشد. برای فهرست‌نویسی جدید از «ثبت منبع جدید» یا ورود از اکسل / فایل ISO استفاده کنید.</p>';
        } else {
            $hold = array();
            $ids_in = implode(',', array_map('intval', array_column($found, 'id')));
            foreach ($db->query("SELECT rid, loc, COUNT(*) c FROM items WHERE rid IN ($ids_in) GROUP BY rid, loc ORDER BY loc") as $hr) $hold[$hr['rid']][$hr['loc']] = (int)$hr['c'];
            echo '<table style="margin-top:14px"><tr><th>عنوان</th><th>پدیدآور</th><th>ناشر / سال</th><th>کتابخانه‌ها (تعداد مجلد)</th><th>ثبت موجودی در محل شما</th></tr>';
            foreach ($found as $f) {
                $hl = isset($hold[$f['id']]) ? $hold[$f['id']] : array();
                echo '<tr><td><b>' . h($f['f200a']) . '</b></td><td>' . h($f['f700'] ? $f['f700'] : $f['f200f']) . '</td><td>' . h($f['f210c']) . ' (' . tr_num(h($f['f210d'])) . ')</td><td>';
                if ($hl) { foreach ($hl as $ln => $cn) echo '<div><span class="badge"' . (in_array($ln, $allowed_locs) ? ' style="background:var(--blue);color:#fff"' : '') . '>' . h($ln) . '</span> ' . tr_num($cn) . ' جلد</div>'; }
                else echo '<span class="warn">بدون موجودی</span>';
                echo '</td><td><form method="post" action="?p=add_hold" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="rid" value="' . $f['id'] . '"><input type="hidden" name="xq" value="' . h($xq) . '">';
                if (count($allowed_locs) > 1) {
                    echo '<select name="loc" style="width:150px;padding:4px 8px">';
                    foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . '</option>';
                    echo '</select>';
                } else echo '<input type="hidden" name="loc" value="' . h($allowed_locs[0]) . '"><span class="badge">' . h($allowed_locs[0]) . '</span>';
                echo '<input type="number" name="vols" value="1" min="1" max="5000" style="width:70px;padding:4px 8px" title="تعداد مجلد"><button class="btn-sm ok">ثبت موجودی</button></form></td></tr>';
            }
            echo '</table>';
        }
    }
    echo '</div>';

    $z_host_cfg = get_cms('z3950_host') ?: 'z3950.nlai.ir';
    $z_port_cfg = get_cms('z3950_port') ?: '210';
    $z_db_cfg   = get_cms('z3950_db')   ?: 'Default';
    $z_syn_cfg  = get_cms('z3950_syn')  ?: 'unimarc';
    $z_use_attrs = array(
        '1016' => 'کلیدواژه عمومی — General Keyword (Use: ۱۰۱۶)',
        '4'    => 'عنوان — Title Keyword (Use: ۴)',
        '7'    => 'شابک — ISBN (Use: ۷)',
        '8'    => 'شاپا — ISSN (Use: ۸)',
        '21'   => 'موضوع — Subject Heading (Use: ۲۱)',
        '31'   => 'سال نشر — Date of Publication (Use: ۳۱)',
        '59'   => 'محل نشر — Place of Publication (Use: ۵۹)',
        '1018' => 'ناشر — Publisher (Use: ۱۰۱۸)',
        '1003' => 'پدیدآور / نویسنده — Author (Use: ۱۰۰۳)'
    );

    echo '<div class="card" style="border:2px solid var(--ok);background:linear-gradient(145deg,#ffffff,#f0fdf4)">
        <h2><span>کلاینت جستجو و بازیابی مستقیم با پروتکل Z39.50 (سازمان اسناد و کتابخانه ملی ایران — رسا)</span><span class="badge" style="background:var(--ok);color:#fff">پشتیبانی کامل Unicode / UTF-8</span></h2>
        <p style="font-size:12.8px;line-height:1.8;margin:0 0 12px">ارتباط مستقیم با سرور <b>ZServer کتابخانه ملی ایران (<code>z3950.nlai.ir:210</code> — پایگاه <code>Default</code>)</b> و سایر کتابخانه‌های استاندارد دنیا بر پایه ویژگی‌های <b>Bib-1 Use Attributes</b> (با پشتیبانی از گزینه‌های <code>search, present, scan, namedResultSets, resultCount</code> و کدینگ یونیکد):</p>
        <form method="post" action="?p=imp_iso_preview">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="from_page" value="imp_exp">
            <div class="grid">
                <div>
                    <label style="color:var(--blue)">عبارت جستجو (Unicode) *</label>
                    <input name="z_query" required placeholder="عنوان، شابک، موضوع یا نام نویسنده را وارد کنید...">
                </div>
                <div>
                    <label style="color:var(--blue)">نوع جستجو (Search Type / Use Attribute)</label>
                    <select name="z_attr">';
                    foreach ($z_use_attrs as $uk => $ul) echo '<option value="' . h($uk) . '">' . h($ul) . '</option>';
    echo '          </select>
                </div>
                <div>
                    <label>کتابخانه مقصد جهت ثبت</label>
                    <select name="loc">';
                    foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '          </select>
                </div>
            </div>
            <div class="grid" style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--line)">
                <div>
                    <label>آدرس سرور Z39.50 (Server Address)</label>
                    <input name="z_host" id="z_host_inp" dir="ltr" value="' . h($z_host_cfg) . '" placeholder="z3950.nlai.ir">
                </div>
                <div>
                    <label>پورت (Port)</label>
                    <input type="number" name="z_port" id="z_port_inp" dir="ltr" value="' . h($z_port_cfg) . '" placeholder="210">
                </div>
                <div>
                    <label>نام پایگاه داده (Database)</label>
                    <input name="z_db" id="z_db_inp" dir="ltr" value="' . h($z_db_cfg) . '" placeholder="Default">
                </div>
                <div>
                    <label>قالب رکورد (Record Syntax / Марک)</label>
                    <select name="z_syn" id="z_syn_inp">
                        <option value="unimarc"' . ($z_syn_cfg === 'unimarc' ? ' selected' : '') . '>ایران‌مارک / UNIMARC (یونیکد)</option>
                        <option value="marc21"' . ($z_syn_cfg === 'marc21' ? ' selected' : '') . '>MARC21 / USMARC (یونیکد)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <button class="btn ok" style="padding:9px 22px">اتصال به ZServer، جستجو و پیش‌نمایش رکوردها</button>
                    <label style="display:inline-flex;align-items:center;gap:6px;margin:0;font-size:12px"><input type="checkbox" name="save_z_cfg" value="1" checked> ذخیره این مشخصات سرور به عنوان پیش‌فرض</label>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                    <small>پیش‌فرض‌های آماده:</small>
                    <button type="button" class="btn-sm" onclick="document.getElementById(\'z_host_inp\').value=\'z3950.nlai.ir\';document.getElementById(\'z_port_inp\').value=\'210\';document.getElementById(\'z_db_inp\').value=\'Default\';document.getElementById(\'z_syn_inp\').value=\'unimarc\';">🇮🇷 کتابخانه ملی ایران (رسا)</button>
                    <button type="button" class="btn-sm" onclick="document.getElementById(\'z_host_inp\').value=\'lx2.loc.gov\';document.getElementById(\'z_port_inp\').value=\'210\';document.getElementById(\'z_db_inp\').value=\'LCDB\';document.getElementById(\'z_syn_inp\').value=\'marc21\';">کتابخانه کنگره (LC)</button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="card" style="border:2px solid var(--blue)">
            <h2>ورودی منابع از فایل اکسل (با انتخاب کتابخانه، کاربرگه و پیش‌نمایش قبل از ثبت)</h2>
            <p style="font-size:12.8px;line-height:1.7">نیازی به وجود ستون «کتابخانه» در فایل اکسل نیست؛ کتابخانه و کاربرگه مورد نظر را در زیر انتخاب کنید. پس از بارگذاری، ابتدا <b>پیش‌نمایش دقیق فیلدها</b> نمایش داده می‌شود تا پس از بررسی و تایید نهایی در مخزن ثبت گردند.</p>';
            $all_ws_js = array();
            foreach ($allowed_locs as $lc_w) {
                $c_w = get_lib_config($lc_w);
                foreach ($c_w['worksheets'] as $wk => $wv) {
                    $all_ws_js[$lc_w][] = array('key' => $wk, 'title' => $wv['title']);
                }
            }
            $first_lc_w = $allowed_locs[0];
            $first_ws_list = isset($all_ws_js[$first_lc_w]) ? $all_ws_js[$first_lc_w] : array(array('key'=>'book','title'=>'کاربرگه عمومی کتاب'));
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                <a class="btn ok" id="dl_sample_xl_btn" href="?p=sample_rec&loc=' . urlencode($first_lc_w) . '&ws=' . urlencode($first_ws_list[0]['key']) . '">دانلود فایل اکسل نمونه بر اساس کاربرگه انتخابی</a>
                <a class="btn-sm" href="?p=locs&tab=worksheets">تنظیمات کاربرگه‌ها و فیلدهای هر کتابخانه</a>
            </div>
            <form method="post" action="?p=imp_xl_rec" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '">
                <div class="grid">
                    <div>
                        <label style="color:var(--blue)">۱. انتخاب کتابخانه ورودی *</label>
                        <select name="loc" id="xl_loc_sel" onchange="updateXlWorksheets(this.value)">';
                        foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
            echo '      </select>
                    </div>
                    <div>
                        <label style="color:var(--blue)">۲. انتخاب کاربرگه ثبت منابع *</label>
                        <select name="ws_key" id="xl_ws_sel" onchange="updateXlSampleLink()">';
                        foreach ($first_ws_list as $w_it) echo '<option value="' . h($w_it['key']) . '">' . h($w_it['title']) . '</option>';
            echo '      </select>
                    </div>
                </div><br>
                <label>۳. انتخاب فایل اکسل (.xlsx, .csv) *</label>
                <input type="file" name="f" accept=".xlsx,.csv" required><br><br>
                <p style="font-size:12px;color:var(--mut);margin-top:6px">توجه: اگر فایل شما با فرمت قدیمی <b>.xls</b> است، ابتدا آن را در Excel باز کرده و با فرمت <b>xlsx</b> ذخیره کنید؛ در غیر این صورت داده‌ها به‌درستی خوانده نمی‌شوند.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <button class="btn pri" name="mode" value="preview">بارگذاری و نمایش پیش‌نمایش منابع (پیشنهادی)</button>
                    <button class="btn ok" name="mode" value="direct" onclick="return confirm(\'آیا مایلید بدون مرحله پیش‌نمایش، رکوردها مستقیماً در کتابخانه انتخابی ثبت شوند؟\')">ثبت مستقیم بدون پیش‌نمایش</button>
                </div>
            </form>
            <script>
            var asemanWsByLoc = ' . json_encode($all_ws_js, JSON_UNESCAPED_UNICODE) . ';
            function updateXlWorksheets(locVal) {
                var wsSel = document.getElementById("xl_ws_sel");
                if (!wsSel) return;
                wsSel.innerHTML = "";
                var list = asemanWsByLoc[locVal] || [{key:"book", title:"کاربرگه عمومی کتاب"}];
                list.forEach(function(item){
                    var opt = document.createElement("option");
                    opt.value = item.key;
                    opt.textContent = item.title;
                    wsSel.appendChild(opt);
                });
                updateXlSampleLink();
            }
            function updateXlSampleLink() {
                var locVal = document.getElementById("xl_loc_sel").value;
                var wsVal = document.getElementById("xl_ws_sel").value;
                var btn = document.getElementById("dl_sample_xl_btn");
                if (btn) btn.href = "?p=sample_rec&loc=" + encodeURIComponent(locVal) + "&ws=" + encodeURIComponent(wsVal);
            }
            </script>
        </div>
        <div class="card">
            <h2>ورودی جامع از فایل ایزو ۲۷۰۹، ایران‌سیستم و XML (با پیش‌نمایش)</h2>
            <p style="font-size:12.8px;line-height:1.7">پشتیبانی خودکار از: <b>ایزو ۲۷۰۹ استاندارد (UNIMARC/MARC21)</b>، <b>ایزو سیمرغ/پارس‌آذرخش (کدینگ ایران‌سیستم با <code>^a</code> و <code>#</code>)</b>، <b>خروجی متنی کتابخانه ملی (<code>selectedRecords.txt</code>)</b> و <b>فایل‌های XML (RDF/DublinCore و MARCXML)</b>.</p>
            <form method="post" action="?p=imp_iso_preview" enctype="multipart/form-data">
                <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="from_page" value="imp_exp">
                <label>کتابخانه رکوردهای ورودی</label><select name="loc">';
                foreach ($allowed_locs as $lc) echo '<option value="' . h($lc) . '">' . h($lc) . ' (پیش‌شماره ' . tr_num(get_loc_prefix($lc)) . ')</option>';
    echo '      </select><br><br>
                <label>۱. انتخاب فایل (.iso, .mrc, .txt, .xml, .dat)</label>
                <input type="file" name="f" accept=".txt,.mrc,.iso,.dat,.xml"><br><br>
                <label>۲. یا جای‌گذاری مستقیم متن رکورد (ایزو / ایران‌سیستم / XML / $a):</label>
                <textarea name="raw_text" rows="3" dir="ltr" placeholder="00825nam  2200169... یا متن ایران‌سیستم یا XML را اینجا Paste کنید"></textarea><br><br>
                <button class="btn pri">استخراج، تبدیل کدینگ و پیش‌نمایش رکوردها</button>
            </form>
        </div>
    </div>
    <div class="card">
        <h2>صدور اطلاعات (اکسل و ISO 2709)</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a class="btn ok" href="?p=export_excel_rec">خروجی اکسل منابع (هر عنوان/محل یک ردیف)</a>
            <a class="btn ok" href="?p=export_excel_mem">خروجی اکسل اعضا</a>
            <a class="btn pri" href="?p=export">خروجی ایران‌مارک (ISO 2709 .mrc)</a>
        </div>
    </div>';
