<?php
// ماژول بانک مستندات فهرست‌نویسی (سرشناسه‌های موضوعی و مشاهیر/اشخاص و سازمان‌ها + حذف گروهی)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'subject';
    if (!in_array($tab, array('subject', 'names', 'tools'))) $tab = 'subject';
    echo '<h1>مستندات فهرست‌نویسی</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    if (isset($_GET['msg'])) echo '<div class="ok-msg">' . ($_GET['msg'] === 'dup' ? 'این سرشناسه از قبل در بانک وجود دارد.' : 'انجام شد.') . '</div>';
    echo '<div class="tabs"><a class="' . ($tab === 'subject' ? 'on' : '') . '" href="?p=authority&tab=subject">بانک سرشناسهٔ موضوعی مستند فارسی</a><a class="' . ($tab === 'names' ? 'on' : '') . '" href="?p=authority&tab=names">بانک مستند مشاهیر (اشخاص و سازمان‌ها)</a><a class="' . ($tab === 'tools' ? 'on' : '') . '" href="?p=authority&tab=tools">ابزارها (ساخت از رکوردها، اعمال، راهنما)</a></div>';
    if ($tab === 'tools') {
        $c1 = (int)$db->query("SELECT COUNT(*) FROM auth_subject")->fetchColumn();
        $c2 = (int)$db->query("SELECT COUNT(*) FROM auth_names")->fetchColumn();
        echo '<div class="stats"><div class="card"><b>' . tr_num($c1) . '</b>سرشناسهٔ موضوعی</div><div class="card"><b>' . tr_num($c2) . '</b>مستند مشاهیر</div></div>
        <div class="grid"><div class="card"><h2>ساخت بانک از رکوردهای موجود</h2><p style="font-size:12.8px;line-height:1.8">همهٔ نام‌های پدیدآور (فیلد ۷۰۰ و ۷۱۰) و سرشناسه‌های موضوعی (فیلد ۶۰۶، جداشده با «؛») که تا امروز در رکوردها ثبت شده، به‌عنوان مستند اولیه ساخته می‌شود. سپس می‌توانید شکل‌های مرجع (ارجاع) را برای هر مورد ثبت کنید.</p>' . post_btn('auth_harvest', 'ساخت مستند از رکوردها', array(), 'btn pri') . '</div>';
        if (is_super_admin()) echo '<div class="card"><h2>اعمال شکل معتبر روی رکوردها</h2><p style="font-size:12.8px;line-height:1.8">هر رکوردی که یکی از «شکل‌های مرجع» را دارد، با سرشناسهٔ معتبر جایگزین می‌شود. پیش از اجرا پشتیبان خودکار گرفته می‌شود.</p>' . post_btn('auth_apply', 'اعمال بر همهٔ رکوردها', array(), 'btn dng') . '</div>';
        echo '</div><div class="card"><h2>راهنما</h2><ul style="line-height:2;font-size:13px"><li>هنگام ثبت دستی منبع، با تایپ دو حرف در فیلدهای «پدیدآور شخصی/سازمانی/نخستین پدیدآور» و «موضوع»، پیشنهادهای مشابه از همین بانک‌ها و رکوردهای موجود نمایش داده می‌شود.</li><li>اگر عبارت تایپ‌شده یکی از «شکل‌های مرجع» باشد، شکل معتبر پیشنهاد می‌شود (برچسب «ارجاع»).</li><li>در فیلد موضوع می‌توانید چند موضوع را با «؛» جدا کنید؛ پیشنهاد برای آخرین بخش داده می‌شود.</li><li>فایل ورودی اکسل/CSV: موضوعی: <code>سرشناسه | شکل‌های مرجع | عام‌تر | وابسته | یادداشت</code> — مشاهیر: <code>سرشناسه | نوع(شخص/سازمان) | شکل‌های مرجع | سال‌ها | حوزه | یادداشت</code>؛ چند شکل مرجع را با «؛» یا «|» جدا کنید.</li></ul></div>';
    } else {
        $subj = ($tab === 'subject');
        $tbl = $subj ? 'auth_subject' : 'auth_names';
        $eid = (int)(isset($_GET['edit']) ? $_GET['edit'] : 0);
        $e = array('id' => 0, 'heading' => '', 'variants' => '', 'broader' => '', 'related' => '', 'note' => '', 'ntype' => 'person', 'years' => '', 'field' => '');
        if ($eid) { $se = $db->prepare("SELECT * FROM $tbl WHERE id=?"); $se->execute(array($eid)); $fe = $se->fetch(PDO::FETCH_ASSOC); if ($fe) $e = array_merge($e, $fe); }
        echo '<div class="card"><h2>' . ($e['id'] ? 'ویرایش' : 'افزودن') . ($subj ? ' سرشناسهٔ موضوعی' : ' مستند شخص/سازمان') . '</h2><form method="post" action="?p=auth_save"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="tbl" value="' . $tab . '"><input type="hidden" name="id" value="' . $e['id'] . '"><div class="grid">
            <div><label>' . ($subj ? 'سرشناسهٔ موضوعی معتبر *' : 'سرشناسهٔ معتبر (نام خانوادگی، نام) *') . '</label><input name="heading" value="' . h($e['heading']) . '" required></div>
            <div><label>شکل‌های مرجع (ارجاع «ببینید») — با «؛» جدا کنید</label><input name="variants" value="' . h(str_replace('|', '؛ ', $e['variants'])) . '"></div>';
        if ($subj) echo '<div><label>موضوع عام‌تر</label><input name="broader" value="' . h($e['broader']) . '"></div><div><label>موضوعات وابسته</label><input name="related" value="' . h($e['related']) . '"></div>';
        else echo '<div><label>نوع</label><select name="ntype"><option value="person"' . ($e['ntype'] !== 'corp' ? ' selected' : '') . '>شخص (مشاهیر)</option><option value="corp"' . ($e['ntype'] === 'corp' ? ' selected' : '') . '>سازمان / نهاد</option></select></div><div><label>سال تولد - وفات / فعالیت</label><input name="years" value="' . h($e['years']) . '"></div><div><label>حوزهٔ فعالیت</label><input name="field" value="' . h($e['field']) . '"></div>';
        echo '<div><label>یادداشت</label><input name="note" value="' . h($e['note']) . '"></div></div><br><button class="btn pri">' . ($e['id'] ? 'ذخیرهٔ ویرایش' : 'افزودن به بانک') . '</button> ' . ($e['id'] ? '<a class="btn" href="?p=authority&tab=' . $tab . '">انصراف</a>' : '') . '</form></div>';
        $q = trim(isset($_GET['q']) ? $_GET['q'] : '');
        $pg = max(1, (int)(isset($_GET['pg']) ? $_GET['pg'] : 1));
        $per = 40;
        $par = array(); $w = '';
        if ($q !== '') { $w = "WHERE norm LIKE :q OR heading LIKE :q2"; $par[':q'] = '%' . fts_norm($q) . '%'; $par[':q2'] = '%' . $q . '%'; }
        $ct = $db->prepare("SELECT COUNT(*) FROM $tbl $w"); $ct->execute($par); $tot = (int)$ct->fetchColumn();
        $st = $db->prepare("SELECT * FROM $tbl $w ORDER BY heading LIMIT $per OFFSET " . (($pg - 1) * $per)); $st->execute($par);
        echo '<form class="card row" method="get"><input type="hidden" name="p" value="authority"><input type="hidden" name="tab" value="' . $tab . '"><div style="flex:1"><label>جستجو در بانک</label><input name="q" value="' . h($q) . '"></div><button class="btn pri">جستجو</button><a class="btn" href="?p=authority&tab=' . $tab . '&export=1">اکسل</a></form>';
        echo '<form class="card" method="post" action="?p=auth_bulk_del" onsubmit="return confirm(\'آیا از حذف مستندات انتخاب‌شده یا کل لیست مطمئن هستید؟\')">
            <input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="tbl" value="' . $tab . '"><input type="hidden" name="q" value="' . h($q) . '">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                
                <div style="display:flex;gap:6px">
                    <button type="submit" class="btn-sm dng">حذف موارد انتخاب‌شده</button>
                    <button type="submit" name="del_all" value="1" class="btn-sm dng" style="background:#ef4444;color:#fff;border-color:#b91c1c" onclick="return prompt(\'این عملیات غیرقابل بازگشت است. برای تایید عبارت (حذف کامل) را تایپ کنید:\') === \'حذف کامل\'">حذف کل مستندات ' . ($q !== '' ? 'یافت‌شده در جستجو' : 'این بانک') . '</button>
                </div>
            </div>
            <table><tr><th style="width:30px"><input type="checkbox"></th><th>سرشناسه</th><th>' . ($subj ? 'شکل‌های مرجع' : 'نوع / سال‌ها') . '</th><th>' . ($subj ? 'عام‌تر' : 'حوزه') . '</th><th>کاربرد در رکوردها</th><th></th></tr>';
        foreach ($st as $x) {
            if ($subj) { $us = $db->prepare("SELECT COUNT(*) FROM records WHERE f606 LIKE ?"); $us->execute(array('%' . $x['heading'] . '%')); }
            else { $us = $db->prepare("SELECT COUNT(*) FROM records WHERE f700=? OR f710=? OR f200f=?"); $us->execute(array($x['heading'], $x['heading'], $x['heading'])); }
            echo '<tr><td><input type="checkbox" name="ids[]" value="' . $x['id'] . '"></td><td><b>' . h($x['heading']) . '</b></td><td>' . ($subj ? h(str_replace('|', '؛ ', $x['variants'])) : ($x['ntype'] === 'corp' ? 'سازمان' : 'شخص') . ' ' . h($x['years']) . ($x['variants'] ? '<br><small>ارجاع: ' . h(str_replace('|', '؛ ', $x['variants'])) . '</small>' : '')) . '</td><td>' . h($subj ? $x['broader'] : $x['field']) . '</td><td>' . tr_num($us->fetchColumn()) . '</td><td style="white-space:nowrap"><a class="btn-sm pri" href="?p=authority&tab=' . $tab . '&edit=' . $x['id'] . '">ویرایش</a> <a class="btn-sm dng" href="#" onclick="if(confirm(\'حذف شود؟\')){var f=document.createElement(\'form\');f.method=\'post\';f.action=\'?p=auth_del\';f.innerHTML=\'<input type=\x22hidden\x22 name=\x22t\x22 value=\x22' . $tok . '\x22><input type=\x22hidden\x22 name=\x22tbl\x22 value=\x22' . $tab . '\x22><input type=\x22hidden\x22 name=\x22id\x22 value=\x22' . $x['id'] . '\x22>\';document.body.appendChild(f);f.submit();}return false;">حذف</a></td></tr>';
        }
        echo '</table><p>مجموع: <b>' . tr_num($tot) . '</b></p></form>';
        $last = max(1, (int)ceil($tot / $per));
        for ($i = max(1, $pg - 6); $i <= min($last, $pg + 6); $i++) echo '<a class="btn-sm' . ($i == $pg ? ' pri' : '') . '" href="?p=authority&tab=' . $tab . '&q=' . urlencode($q) . '&pg=' . $i . '">' . tr_num($i) . '</a> ';
        echo '<div class="card"><h2>ورود گروهی (اکسل، CSV و فایل‌های مستند مارک / متنی MARC)</h2><p style="font-size:12.8px;line-height:1.7">علاوه بر فایل‌های اکسل و CSV، می‌توانید فایل‌های متنی یا ایزو حاوی سرشناسه‌های مستند مارک (مثل فیلدهای موضوعی ۶۰۶ یا نام‌های پدیدآور ۷۰۰/۷۱۰) را نیز مستقیماً بارگذاری کنید.</p><form method="post" action="?p=auth_import" enctype="multipart/form-data" class="row"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="tbl" value="' . $tab . '"><div style="flex:1"><label>انتخاب فایل (.xls, .xlsx, .csv, .mrc, .txt)</label><input type="file" name="f" accept=".xls,.xlsx,.csv,.mrc,.txt" required></div><button class="btn pri">بارگذاری و استخراج سرشناسه‌ها</button></form><p style="font-size:12px;margin-top:8px">' . ($subj ? 'فرمت اکسل: سرشناسه | شکل‌های مرجع | عام‌تر | وابسته | یادداشت (یا فایل متنی خط به خط سرشناسه‌ها)' : 'فرمت اکسل: سرشناسه | نوع (شخص/سازمان) | شکل‌های مرجع | سال‌ها | حوزه | یادداشت') . '</p></div>';
    }
