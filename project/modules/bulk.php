<?php
// ماژول کنترل گروهی، بررسی کیفیت داده‌ها، بازسازی ایندکس‌های متنی و سلامت پایگاه داده
if (!defined('ASEMAN_ROOT')) die('Access Denied');

    if (!is_super_admin()) { echo '<div class="card"><p class="warn">این بخش فقط برای ادمین کل است.</p></div>'; }
    else {
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'audit';
    if (!in_array($tab, array('audit', 'edit', 'index', 'health'))) $tab = 'audit';
    echo '<h1>کنترل گروهی</h1>';
    if (!empty($_SESSION['flash'])) { echo '<div class="ok-msg">' . h($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
    echo '<div class="tabs"><a class="' . ($tab === 'audit' ? 'on' : '') . '" href="?p=bulk&tab=audit">بررسی (ناقص و تکراری)</a><a class="' . ($tab === 'edit' ? 'on' : '') . '" href="?p=bulk&tab=edit">حذف و ویرایش گروهی</a><a class="' . ($tab === 'index' ? 'on' : '') . '" href="?p=bulk&tab=index">ایندکس جستجو</a><a class="' . ($tab === 'health' ? 'on' : '') . '" href="?p=bulk&tab=health">رکوردهای قفل و خراب</a></div>';

    if ($tab === 'audit') {
        $issue = isset($_GET['issue']) ? $_GET['issue'] : '';
        $sqlc = array(
            'no_title' => array('بدون عنوان', "records WHERE f200a IS NULL OR TRIM(f200a)=''"),
            'no_author' => array('بدون پدیدآور', "records WHERE COALESCE(TRIM(f700),'')='' AND COALESCE(TRIM(f710),'')='' AND COALESCE(TRIM(f200f),'')=''"),
            'no_pub' => array('بدون ناشر', "records WHERE COALESCE(TRIM(f210c),'')=''"),
            'no_year' => array('بدون سال نشر', "records WHERE COALESCE(TRIM(f210d),'')=''"),
            'no_isbn' => array('کتاب بدون شابک', "records WHERE rtype='book' AND COALESCE(TRIM(f010),'')=''"),
            'no_class' => array('بدون رده‌بندی (دیویی/کنگره)', "records WHERE COALESCE(TRIM(f676),'')='' AND COALESCE(TRIM(f686),'')=''"),
            'no_subject' => array('بدون موضوع', "records WHERE COALESCE(TRIM(f606),'')=''"),
            'no_pages' => array('بدون تعداد صفحات (کتاب)', "records WHERE rtype='book' AND COALESCE(TRIM(f215),'')=''"),
            'no_items' => array('عنوان بدون هیچ مجلد', "records WHERE id NOT IN (SELECT rid FROM items)"),
            'orphan_items' => array('مجلد بدون رکورد (یتیم)', "items WHERE rid NOT IN (SELECT id FROM records)"),
            'orphan_loans' => array('امانت بدون مجلد', "loans WHERE rid NOT IN (SELECT id FROM items)"),
            'dup_members' => array('اعضای تکراری (شماره عضویت/تلفن)', "members WHERE id NOT IN (SELECT MIN(id) FROM members GROUP BY COALESCE(NULLIF(TRIM(mcode),''), 'x'||id)) OR (phone<>'' AND id NOT IN (SELECT MIN(id) FROM members GROUP BY phone))")
        );
        $php = array('bad_isbn' => array('شابک نامعتبر (رقم کنترل)', array()), 'bad_year' => array('سال نشر نامعتبر', array()), 'dup_isbn' => array('رکوردهای هم‌شابک (تکراری)', array()), 'dup_title' => array('رکوردهای هم‌عنوان و هم‌پدیدآور (احتمالاً تکراری)', array()));
        $isbnmap = array(); $titlemap = array();
        foreach ($db->query("SELECT id,f010,f210d,f200a,f700,f200f FROM records") as $r) {
            $d = preg_replace('/\D/', '', en_num($r['f010']));
            if (trim($r['f010']) !== '' && !isbn_valid($r['f010'])) $php['bad_isbn'][1][] = $r['id'];
            if (trim($r['f210d']) !== '' && !year_valid($r['f210d'])) $php['bad_year'][1][] = $r['id'];
            if (strlen($d) >= 10) $isbnmap[$d][] = $r['id'];
            $k = norm_key($r['f200a']) . '|' . norm_key($r['f700'] ? $r['f700'] : $r['f200f']);
            if (trim($r['f200a']) !== '') $titlemap[$k][] = $r['id'];
        }
        foreach ($isbnmap as $k => $v) if (count($v) > 1) $php['dup_isbn'][1][] = $v;
        foreach ($titlemap as $k => $v) if (count($v) > 1) $php['dup_title'][1][] = $v;
        echo '<div class="card"><h2>خلاصهٔ بررسی کیفیت داده‌ها</h2><table><tr><th>مورد بررسی</th><th>تعداد</th><th></th></tr>';
        foreach ($sqlc as $k => $v) { $cn = (int)$db->query("SELECT COUNT(*) FROM " . $v[1])->fetchColumn(); echo '<tr><td>' . $v[0] . '</td><td>' . ($cn ? '<b class="warn">' . tr_num($cn) . '</b>' : '۰') . '</td><td>' . ($cn ? '<a class="btn-sm" href="?p=bulk&tab=audit&issue=' . $k . '">مشاهده</a>' : '') . '</td></tr>'; }
        foreach ($php as $k => $v) { $cn = count($v[1]); echo '<tr><td>' . $v[0] . '</td><td>' . ($cn ? '<b class="warn">' . tr_num($cn) . ($k[0] === 'd' ? ' گروه' : '') . '</b>' : '۰') . '</td><td>' . ($cn ? '<a class="btn-sm" href="?p=bulk&tab=audit&issue=' . $k . '">مشاهده</a>' : '') . '</td></tr>'; }
        echo '</table></div>';
        if ($issue !== '') {
            $lbl = isset($sqlc[$issue]) ? $sqlc[$issue][0] : (isset($php[$issue]) ? $php[$issue][0] : '');
            echo '<div class="card"><h2>' . h($lbl) . '</h2>';
            if ($issue === 'dup_isbn' || $issue === 'dup_title') {
                $groups = array_slice($php[$issue][1], 0, 60);
                foreach ($groups as $gi => $ids) {
                    echo '<form method="post" action="?p=merge_titles" style="border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:12px"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="ids" value="' . implode(',', $ids) . '"><input type="hidden" name="issue" value="' . h($issue) . '"><table><tr><th>نگه‌داشتن</th><th>شناسه</th><th>عنوان</th><th>پدیدآور</th><th>ناشر / سال</th><th>مجلدات (کتابخانه: تعداد)</th></tr>';
                    foreach ($ids as $k => $rid) {
                        $sr = $db->prepare("SELECT * FROM records WHERE id=?"); $sr->execute(array($rid)); $r = $sr->fetch(PDO::FETCH_ASSOC);
                        $hs = $db->prepare("SELECT loc, COUNT(*) c FROM items WHERE rid=? GROUP BY loc"); $hs->execute(array($rid)); $hl = array(); foreach ($hs as $x) $hl[] = $x['loc'] . ': ' . tr_num($x['c']);
                        echo '<tr><td><input type="radio" name="keep" value="' . $rid . '"' . ($k === 0 ? ' checked' : '') . '></td><td><a href="?p=marc&id=' . $rid . '">' . tr_num($rid) . '</a></td><td>' . h($r['f200a']) . '</td><td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td><td>' . h($r['f210c']) . ' ' . tr_num(h($r['f210d'])) . '</td><td>' . h(implode('، ', $hl)) . '</td></tr>';
                    }
                    echo '</table><button class="btn-sm dng" onclick="return confirm(\'رکوردهای انتخاب‌نشده در رکورد انتخاب‌شده ادغام و حذف می‌شوند. ادامه می‌دهید؟\')">ادغام این گروه</button></form>';
                }
                if (!$groups) echo '<p>موردی نیست.</p>';
            } else {
                if (isset($php[$issue])) $ids = array_slice($php[$issue][1], 0, 200);
                elseif ($issue === 'orphan_items') { $ids = array(); }
                if (isset($sqlc[$issue]) && !in_array($issue, array('orphan_items', 'orphan_loans', 'dup_members'))) $ids = array_map('intval', $db->query("SELECT id FROM " . $sqlc[$issue][1] . " LIMIT 200")->fetchAll(PDO::FETCH_COLUMN));
                if ($issue === 'orphan_items' || $issue === 'orphan_loans') echo '<p>این مورد را از تب «رکوردهای قفل و خراب» ترمیم کنید.</p>';
                elseif ($issue === 'dup_members') { echo '<table><tr><th>شناسه</th><th>نام</th><th>شماره عضویت</th><th>تلفن</th><th>محل</th></tr>'; foreach ($db->query("SELECT * FROM " . $sqlc['dup_members'][1] . " LIMIT 100") as $m) echo '<tr><td>' . tr_num($m['id']) . '</td><td>' . h($m['name']) . '</td><td>' . h($m['mcode']) . '</td><td>' . h($m['phone']) . '</td><td>' . h($m['loc']) . '</td></tr>'; echo '</table>'; }
                else {
                    echo '<table><tr><th>شناسه</th><th>عنوان</th><th>پدیدآور</th><th>شابک</th><th>سال</th><th></th></tr>';
                    foreach ($ids as $rid) { $sr = $db->prepare("SELECT * FROM records WHERE id=?"); $sr->execute(array($rid)); $r = $sr->fetch(PDO::FETCH_ASSOC); if (!$r) continue;
                        echo '<tr><td>' . tr_num($rid) . '</td><td>' . h($r['f200a']) . '</td><td>' . h($r['f700'] ? $r['f700'] : $r['f200f']) . '</td><td>' . h($r['f010']) . '</td><td>' . tr_num(h($r['f210d'])) . '</td><td><a class="btn-sm pri" href="?p=form&id=' . $rid . '">تکمیل / ویرایش</a></td></tr>'; }
                    echo '</table><p style="font-size:12px">حداکثر ۲۰۰ مورد اول نمایش داده می‌شود.</p>';
                }
            }
            echo '</div>';
        }

    } elseif ($tab === 'edit') {
        $res = isset($_SESSION['bulk_res']) ? $_SESSION['bulk_res'] : null;
        unset($_SESSION['bulk_res']);
        if (!empty($_SESSION['bulk_note'])) { echo '<div class="ok-msg" style="background:#fff7ed;color:#9a3412">' . h($_SESSION['bulk_note']) . '</div>'; unset($_SESSION['bulk_note']); }
        if ($res) {
            echo '<div class="card" style="border:2px solid ' . ($res['dry'] ? 'var(--blue)' : 'var(--ok)') . '"><h2>' . ($res['dry'] ? 'نتیجهٔ پیش‌نمایش (هیچ تغییری اعمال نشد)' : 'نتیجهٔ اجرا') . '</h2><p>رکوردهای انتخاب‌شده: <b>' . tr_num($res['ids']) . '</b> | تأثیر: <b>' . tr_num($res['n']) . '</b>' . ($res['bk'] ? ' | پشتیبان خودکار: <code>' . h($res['bk']) . '</code>' : '') . '</p><ul style="line-height:1.9;font-size:13px">';
            foreach ($res['msgs'] as $m) echo '<li>' . h($m) . '</li>';
            echo '</ul></div>';
        }
        $lopts = '';
        foreach ($Locations as $lc) $lopts .= '<option value="' . h($lc) . '">' . h($lc) . '</option>';
        $lopts_any = '<option value="">(بدون فیلتر)</option>' . $lopts;
        $topts = '<option value="">همه</option>';
        foreach ($RTypes as $k => $v) $topts .= '<option value="' . $k . '">' . $v . '</option>';
        $fopts = '<option value="rtype">نوع منبع (book / article / thesis / other)</option>';
        foreach ($F as $k => $v) $fopts .= '<option value="' . $k . '">' . h($v) . '</option>';
        echo '<form method="post" action="?p=bulk_run" class="card"><input type="hidden" name="t" value="' . $tok . '">
        <h2>۱) انتخاب رکوردها</h2><p style="font-size:12.5px">حداقل یک شرط لازم است (برای جلوگیری از اجرای تصادفی روی کل پایگاه). برای ویرایش شمارهٔ ثبت یا مجلدات یک عنوان فقط «شناسهٔ رکورد» را بنویسید.</p>
        <div class="grid"><div><label>شناسهٔ رکورد(ها) — با ویرگول</label><input name="ids" placeholder="مثلاً 8 یا 8,12,40"></div>
        <div><label>عبارت جستجو</label><input name="q"></div>
        <div><label>نوع منبع</label><select name="type">' . $topts . '</select></div>
        <div><label>کتابخانه (فیلتر مجلدات)</label><select name="sloc">' . $lopts_any . '</select></div>
        <div><label>شمارهٔ ثبت از</label><input name="from"></div><div><label>شمارهٔ ثبت تا</label><input name="to"></div></div>
        <h2 style="margin-top:18px">۲) عملیات</h2>
        <div class="card" style="background:var(--ice)"><label style="display:flex;gap:8px"><input type="radio" name="act" value="items_cmd" checked> <b>مجلدات و شمارهٔ ثبت (یک دستور)</b></label>
        <div class="grid" style="margin-top:8px"><div><label>کتابخانه هدف *</label><select name="p_loc">' . $lopts . '</select></div>
        <div><label>تعداد مجلد را به این عدد برسان</label><input type="number" name="p_vols" min="0" placeholder="مثلاً 5"></div>
        <div><label>شمارهٔ ثبت را از این عدد پشت‌سرهم بازنویسی کن</label><input name="p_start" placeholder="مثلاً 100200"></div>
        <div><label>شماره‌ها را به اندازهٔ این مقدار جابه‌جا کن (+/−)</label><input name="p_shift" placeholder="مثلاً 100 یا -5"></div>
        <div><label>انتقال مجلدات به محل</label><select name="p_move"><option value="">—</option>' . $lopts . '</select></div>
        <div><label style="display:flex;gap:6px"><input type="checkbox" name="p_regen" value="1"> در انتقال، شمارهٔ ثبت جدید طبق پیش‌شمارهٔ مقصد صادر شود</label><label style="display:flex;gap:6px"><input type="checkbox" name="p_renumber" value="1"> بازشماره‌گذاری با شمارهٔ بعدیِ همین محل</label></div></div>
        <label style="margin-top:8px">یا فرمان یک‌خطی (جایگزین موارد بالا):</label><input name="cmd" dir="ltr" style="text-align:left" placeholder="id=8 loc=&quot;نام کتابخانه&quot; vols=5 start=100200">
        <p style="font-size:12px;margin:6px 0 0">کلیدها: <code>id</code> <code>loc</code> <code>vols</code> <code>start</code> <code>shift</code> <code>move</code> <code>regen=1</code> <code>renumber=1</code> <code>from</code> <code>to</code> — مقدارهای دارای فاصله را در گیومه بنویسید.</p></div>
        <div class="card" style="background:var(--ice)"><label style="display:flex;gap:8px"><input type="radio" name="act" value="set_field"> <b>ویرایش گروهی یک فیلد</b></label>
        <div class="grid" style="margin-top:8px"><div><label>فیلد</label><select name="p_field">' . $fopts . '</select></div>
        <div><label>روش</label><select name="p_mode"><option value="set">جایگزینی کل مقدار</option><option value="replace">یافتن و جایگزینی متن</option><option value="append">افزودن به انتها</option><option value="prepend">افزودن به ابتدا</option><option value="clear">خالی‌کردن</option></select></div>
        <div><label>متن مورد جستجو (برای «یافتن و جایگزینی»)</label><input name="p_find"></div><div><label>مقدار جدید</label><input name="p_value"></div></div></div>
        <div class="card" style="background:#fff1f2"><label style="display:flex;gap:8px"><input type="radio" name="act" value="del_titles"> <b style="color:var(--warn)">حذف گروهی عنوان‌ها</b></label><p style="font-size:12.5px;margin:6px 0">اگر «کتابخانه» بالا انتخاب شده باشد فقط موجودی همان محل حذف می‌شود، وگرنه عنوان با همهٔ مجلدات. رکوردهای دارای امانت فعال حذف نمی‌شوند. برای اجرای واقعی کلمهٔ «حذف» را تایپ کنید: <input name="word" style="width:120px;display:inline-block"></p></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><button class="btn pri" name="go" value="">پیش‌نمایش (بدون تغییر)</button><label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="confirm" value="1"> متوجه‌ام که این عملیات روی پایگاه اعمال می‌شود (پشتیبان خودکار گرفته می‌شود)</label><button class="btn dng" name="go" value="1">اجرای واقعی</button></div></form>';
        echo '<div class="card"><h2>سوابق اجرای عملیات گروهی</h2><table><tr><th>زمان</th><th>کاربر</th><th>عملیات</th><th>تعداد اثر</th></tr>';
        foreach ($db->query("SELECT * FROM bulk_log ORDER BY id DESC LIMIT 12") as $b) echo '<tr><td>' . jdate($b['ts'], true) . '</td><td>' . h($b['uname']) . '</td><td>' . h($b['action']) . '</td><td>' . tr_num($b['n']) . '</td></tr>';
        echo '</table><h3>پشتیبان‌های خودکار</h3><p style="font-size:12.5px">';
        $bkl = glob(ASEMAN_ROOT . '/backups/auto_*.json');
        foreach (array_reverse($bkl ? $bkl : array()) as $bf) echo '<code>' . h(basename($bf)) . '</code> (' . tr_num(round(filesize($bf) / 1048576, 1)) . ' MB)<br>';
        echo '</p></div>';

    } elseif ($tab === 'index') {
        $cr = (int)$db->query("SELECT COUNT(*) FROM records")->fetchColumn();
        $ci = $FTS_OK ? (int)$db->query("SELECT COUNT(*) FROM records_fts")->fetchColumn() : 0;
        $fresh = $FTS_OK && $cr === $ci;
        echo '<div class="stats"><div class="card"><b>' . ($FTS_OK ? '' : '') . '</b>ایندکس متنی GIN در PostgreSQL</div><div class="card"><b>' . tr_num($cr) . '</b>رکورد در پایگاه</div><div class="card"><b>' . tr_num($ci) . '</b>رکورد در ایندکس متنی</div><div class="card"><b>' . ($fresh ? '' : '') . '</b>' . ($fresh ? 'ایندکس به‌روز است' : 'ایندکس ناقص است؛ جستجو موقتاً با روش کندتر انجام می‌شود') . '</div></div>';
        echo '<div class="card"><h2>عملیات ایندکس</h2><p style="font-size:12.8px;line-height:1.8">ایندکس متنی (FTS5) کلمات عنوان، پدیدآور، موضوع، ناشر، شابک و رده را با یکسان‌سازی حروف فارسی/عربی (ی/ي، ک/ك، ارقام، نیم‌فاصله، اعراب) ذخیره می‌کند و جستجو را در پایگاه‌های بزرگ بسیار سریع‌تر می‌کند. آخرین ساخت: <b>' . h(get_cms('fts_built') ? jdate(get_cms('fts_built'), true) : 'هرگز') . '</b></p><div style="display:flex;gap:8px;flex-wrap:wrap">';
        foreach (array('rebuild' => 'بازسازی ایندکس متنی', 'fts_optimize' => 'بهینه‌سازی ایندکس متنی', 'analyze' => 'به‌روزرسانی آمار (ANALYZE)', 'reindex' => 'بازسازی ایندکس جدول‌ها (REINDEX)', 'vacuum' => 'فشرده‌سازی (VACUUM)') as $ak => $al) echo post_btn('idx_act', $al, array('act' => $ak), $ak === 'rebuild' ? 'btn pri' : 'btn');
        echo '</div></div>';
        $tq = trim(isset($_GET['tq']) ? $_GET['tq'] : '');
        echo '<div class="card"><h2>آزمون سرعت جستجو</h2><form method="get" class="row"><input type="hidden" name="p" value="bulk"><input type="hidden" name="tab" value="index"><div style="flex:1"><label>عبارت آزمایشی</label><input name="tq" value="' . h($tq) . '"></div><button class="btn pri">اجرا</button></form>';
        if ($tq !== '') {
            $t0 = microtime(true);
            $st = $db->prepare("SELECT COUNT(*) FROM records WHERE f200a LIKE :q OR f700 LIKE :q OR f606 LIKE :q OR f210c LIKE :q OR f010 LIKE :q");
            $st->execute(array(':q' => "%$tq%"));
            $nl = (int)$st->fetchColumn();
            $tl = (microtime(true) - $t0) * 1000;
            echo '<p>روش LIKE: ' . tr_num($nl) . ' نتیجه در ' . tr_num(round($tl, 1)) . ' میلی‌ثانیه</p>';
            if ($FTS_OK) {
                $t0 = microtime(true); $e = fts_expr($tq); $nf = 0;
                if ($e !== '') { try { $st = $db->prepare("SELECT COUNT(*) FROM records_fts WHERE tsv @@ to_tsquery('simple', ?)"); $st->execute(array($e)); $nf = (int)$st->fetchColumn(); } catch (Exception $ex) {} }
                $tf = (microtime(true) - $t0) * 1000;
                echo '<p>روش ایندکس متنی: ' . tr_num($nf) . ' نتیجه در ' . tr_num(round($tf, 1)) . ' میلی‌ثانیه (جستجوی پیشوندی کلمات)</p>';
            }
        }
        echo '</div>';

    } elseif ($tab === 'health') {
        $ok_ic = true; $ic = array('ok');
        $jm = 'PostgreSQL MVCC/WAL';
        $db_size_bytes = 0;
        try { $db_size_bytes = (int)$db->query("SELECT pg_database_size(current_database())")->fetchColumn(); } catch (Exception $e) {}
        $canw = true; $werr = '';
        try { $db->exec("BEGIN"); $db->exec("ROLLBACK"); } catch (Exception $e) { $canw = false; $werr = $e->getMessage(); try { $db->exec("ROLLBACK"); } catch (Exception $e2) {} }
        $orph_items = (int)$db->query("SELECT COUNT(*) FROM items WHERE rid NOT IN (SELECT id FROM records)")->fetchColumn();
        $orph_loc = (int)$db->query("SELECT COUNT(*) FROM loc_rec WHERE rid NOT IN (SELECT id FROM records) OR (rid, loc) NOT IN (SELECT rid, loc FROM items)")->fetchColumn();
        $miss_loc = (int)$db->query("SELECT COUNT(*) FROM (SELECT DISTINCT rid, loc FROM items) x WHERE (rid, loc) NOT IN (SELECT rid, loc FROM loc_rec)")->fetchColumn();
        $copies_bad = (int)$db->query("SELECT COUNT(*) FROM records WHERE copies <> (SELECT COUNT(*) FROM items WHERE rid=records.id)")->fetchColumn();
        $known = array_map(function ($x) use ($db) { return $db->quote($x); }, $Locations);
        $unk = (int)$db->query("SELECT COUNT(*) FROM items WHERE loc NOT IN (" . implode(',', $known) . ")")->fetchColumn();
        $bad_text = array();
        foreach ($db->query("SELECT * FROM records") as $r) {
            foreach (array_keys($F) as $col) { $v = (string)$r[$col]; if ($v !== '' && (!mb_check_encoding($v, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v))) { $bad_text[] = $r['id']; break; } }
            if (count($bad_text) >= 500) break;
        }
        $notitle = (int)$db->query("SELECT COUNT(*) FROM records WHERE f200a IS NULL OR TRIM(f200a)=''")->fetchColumn();
        $orph_loans = (int)$db->query("SELECT COUNT(*) FROM loans WHERE rid NOT IN (SELECT id FROM items)")->fetchColumn();
        $lopts_h = '';
        foreach ($Locations as $lc) $lopts_h .= '<option value="' . h($lc) . '">' . h($lc) . '</option>';
        $rows = array(
            array('یکپارچگی فایل پایگاه (integrity_check)', $ok_ic ? 'سالم' : implode(' | ', array_slice($ic, 0, 3)), $ok_ic, ''),
            array('امکان نوشتن در پایگاه (قفل بودن)', $canw ? 'قابل نوشتن' : 'قفل/خطا: ' . $werr, $canw, 'checkpoint'),
            array('مجلد بدون رکورد (یتیم)', tr_num($orph_items), $orph_items === 0, 'del_orphan_items'),
            array('سریال رکورد نامعتبر', tr_num($orph_loc), $orph_loc === 0, 'del_orphan_locrec'),
            array('عنوان‌های فاقد سریال رکورد در محل', tr_num($miss_loc), $miss_loc === 0, 'rebuild_locrec'),
            array('مغایرت شمارندهٔ نسخه با تعداد مجلد', tr_num($copies_bad), $copies_bad === 0, 'sync_copies'),
            array('مجلد با کتابخانه ناشناخته', tr_num($unk), $unk === 0, 'move_unknown_loc'),
            array('رکوردهای دارای نویسهٔ خراب/کنترلی', tr_num(count($bad_text)), !$bad_text, 'fix_text'),
            array('رکورد بدون عنوان', tr_num($notitle), $notitle === 0, ''),
            array('امانت بدون مجلد', tr_num($orph_loans), $orph_loans === 0, '')
        );
        echo '<div class="card"><h2>سلامت پایگاه داده</h2><p style="font-size:12.5px">حالت ژورنال: <b>' . h($jm) . '</b> | حجم فایل: <b>' . tr_num(round($db_size_bytes / 1048576, 1)) . ' MB</b></p><table><tr><th>بررسی</th><th>نتیجه</th><th>وضعیت</th><th>ترمیم</th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td>' . $r[0] . '</td><td>' . h($r[1]) . '</td><td>' . ($r[2] ? '' : '<b class="warn"></b>') . '</td><td>';
            if (!$r[2] && $r[3] !== '') {
                if ($r[3] === 'move_unknown_loc') echo '<form method="post" action="?p=repair" class="row" style="gap:6px"><input type="hidden" name="t" value="' . $tok . '"><input type="hidden" name="act" value="move_unknown_loc"><select name="target" style="width:170px;padding:4px">' . $lopts_h . '</select><button class="btn-sm pri">انتقال</button></form>';
                else echo post_btn('repair', 'ترمیم', array('act' => $r[3]), 'btn-sm pri');
            }
            echo '</td></tr>';
        }
        echo '</table>' . ($bad_text ? '<p style="font-size:12px">شناسهٔ نمونه: ' . h(implode(', ', array_slice($bad_text, 0, 15))) . '</p>' : '') . '<p style="font-size:12px">پیش از هر ترمیم، نسخهٔ پشتیبان خودکار گرفته می‌شود.</p></div>';
        $locks = $db->query("SELECT l.*, r.f200a FROM rec_locks l LEFT JOIN records r ON r.id=l.rid ORDER BY l.ts DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo '<div class="card"><h2>رکوردهای قفل (در حال ویرایش)</h2><p style="font-size:12.5px">هنگام ویرایش یک رکورد، قفل ثبت می‌شود تا دو نفر هم‌زمان آن را ویرایش نکنند؛ قفل پس از ۱۰ دقیقه بی‌فعالیتی خودکار منقضی می‌شود.</p><table><tr><th>رکورد</th><th>کاربر</th><th>محل</th><th>زمان قفل</th><th>وضعیت</th><th></th></tr>';
        if (!$locks) echo '<tr><td colspan="6">قفلی ثبت نشده است.</td></tr>';
        foreach ($locks as $l) { $live = $l['ts'] > time() - 600; echo '<tr><td><a href="?p=marc&id=' . $l['rid'] . '">' . h($l['f200a']) . '</a></td><td>' . h($l['uname']) . '</td><td>' . h($l['loc']) . '</td><td>' . jdate(date('Y-m-d H:i:s', $l['ts']), true) . '</td><td>' . ($live ? '<b class="warn">فعال</b>' : 'منقضی') . '</td><td>' . post_btn('repair', 'آزاد کردن', array('act' => 'release_one', 'rid' => $l['rid']), 'btn-sm') . '</td></tr>'; }
        echo '</table><div style="margin-top:10px;display:flex;gap:8px">' . post_btn('repair', 'آزادسازی قفل‌های منقضی', array('act' => 'release_locks', 'only_expired' => 1), 'btn') . post_btn('repair', 'آزادسازی همهٔ قفل‌ها', array('act' => 'release_locks'), 'btn dng') . '</div></div>';
    }
    }
