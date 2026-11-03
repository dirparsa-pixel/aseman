<?php
// پردازش یکپارچه درخواست‌های POST در سامانه
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ==================== پردازش درخواست‌های POST ====================
if ($post) {
    if ($p === 'do_mem_register') {
        $name   = trim($_POST['name'] ?? '');
        $father = trim($_POST['father'] ?? '');
        $u      = trim(en_num($_POST['mcode'] ?? ''));
        $pw     = $_POST['pw'] ?? '';
        $phone  = trim(en_num($_POST['phone'] ?? ''));
        $loc    = trim($_POST['loc'] ?? $Locations[0]);
        if (!in_array($loc, $Locations)) $loc = $Locations[0];

        if ($name === '' || $u === '' || $pw === '') {
            header('Location: ?p=home&ltab=mem_reg&mem_msg=err');
            exit;
        }
        $chk = $db->prepare("SELECT 1 FROM members WHERE u=? OR mcode=?");
        $chk->execute(array($u, $u));
        if ($chk->fetchColumn()) {
            header('Location: ?p=home&ltab=mem_reg&mem_msg=dup');
            exit;
        }
        $db->prepare("INSERT INTO members(name, father, u, mcode, pw, phone, loc, status) VALUES(?,?,?,?,?,?,?,'pending')")
           ->execute(array($name, $father, $u, $u, password_hash($pw, PASSWORD_DEFAULT), $phone, $loc));
        header('Location: ?p=home&ltab=mem_in&mem_msg=reg_pending');
        exit;
    }
    if ($p === 'do_mem_login') {
        check_login_rate_limit();
        $u  = trim(en_num($_POST['u'] ?? ''));
        $pw = $_POST['pw'] ?? '';
        $st = $db->prepare("SELECT * FROM members WHERE u=? OR mcode=?");
        $st->execute(array($u, $u));
        $mem = $st->fetch(PDO::FETCH_ASSOC);
        if ($mem && !empty($mem['pw']) && password_verify($pw, $mem['pw'])) {
            clear_login_failures();
            if (isset($mem['status']) && $mem['status'] === 'pending') {
                header('Location: ?p=home&ltab=mem_in&mem_msg=pending');
                exit;
            }
            session_regenerate_id(true);
            $_SESSION['mid'] = $mem['id'];
            $_SESSION['mname'] = $mem['name'];
            header('Location: ?p=my_panel');
            exit;
        }
        record_login_failure();
        header('Location: ?p=home&ltab=mem_in&mem_msg=err');
        exit;
    }
    if ($p === 'reserve_book' && isset($_SESSION['mid'])) {
        $rid = (int)($_POST['rid'] ?? 0);
        $loc = trim($_POST['loc'] ?? $Locations[0]);
        if (!in_array($loc, $Locations)) $loc = $Locations[0];
        if ($rid > 0) {
            $db->exec("DELETE FROM reservations WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP");
            $exp_ts = date('Y-m-d H:i:s', time() + 48 * 3600);
            $db->prepare("INSERT INTO reservations(rid, mid, loc, status, expires_at) VALUES(?,?,?,'pending',?)")
               ->execute(array($rid, (int)$_SESSION['mid'], $loc, $exp_ts));
            $_SESSION['flash'] = 'منبع مورد نظر با موفقیت برای شما رزرو شد. توجه: این رزرو به مدت ۴۸ ساعت (تا ' . jdate($exp_ts, true) . ') فعال است و پس از آن در صورت عدم مراجعه به طور خودکار حذف می‌شود.';
        }
        header('Location: ?p=my_panel&tab=reservations');
        exit;
    }
    if ($p === 'cancel_res' && isset($_SESSION['mid'])) {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM reservations WHERE id=? AND mid=?")->execute(array($id, (int)$_SESSION['mid']));
        $_SESSION['flash'] = 'رزرو مورد نظر لغو گردید.';
        header('Location: ?p=my_panel&tab=reservations');
        exit;
    }
    if ($p === 'mem_ticket' && isset($_SESSION['mid'])) {
        $subj = trim($_POST['subj'] ?? '');
        $msg  = trim($_POST['msg'] ?? '');
        if ($subj !== '' && $msg !== '') {
            $m = $db->prepare("SELECT name, loc FROM members WHERE id=?");
            $m->execute(array((int)$_SESSION['mid']));
            $mr = $m->fetch(PDO::FETCH_ASSOC);
            $sender_lbl = 'عضو: ' . ($mr['name'] ?? '') . ' (#' . (int)$_SESSION['mid'] . ')';
            $db->prepare("INSERT INTO tickets(sender,uloc,target,subj,msg,status) VALUES(?,?,'admin',?,?,'open')")
               ->execute(array($sender_lbl, $mr['loc'] ?? 'مخزن اصلی', $subj, $msg));
        }
        $_SESSION['flash'] = 'تیکت شما برای مدیریت مرکزی کتابخانه ارسال شد.';
        header('Location: ?p=my_panel&tab=tickets');
        exit;
    }
    if ($p === 'mem_profile' && isset($_SESSION['mid'])) {
        $name   = trim($_POST['name'] ?? '');
        $father = trim($_POST['father'] ?? '');
        $phone  = trim(en_num($_POST['phone'] ?? ''));
        $bdate  = trim($_POST['bdate'] ?? '');
        $pw     = $_POST['pw'] ?? '';
        if ($name !== '') {
            if ($pw !== '') {
                $db->prepare("UPDATE members SET name=?, father=?, phone=?, bdate=?, pw=? WHERE id=?")
                   ->execute(array($name, $father, $phone, $bdate, password_hash($pw, PASSWORD_DEFAULT), (int)$_SESSION['mid']));
            } else {
                $db->prepare("UPDATE members SET name=?, father=?, phone=?, bdate=? WHERE id=?")
                   ->execute(array($name, $father, $phone, $bdate, (int)$_SESSION['mid']));
            }
            $_SESSION['mname'] = $name;
        }
        $_SESSION['flash'] = 'مشخصات پروفایل شما بروزرسانی شد.';
        header('Location: ?p=my_panel&tab=profile');
        exit;
    }
    if ($p === 'mem_logout') {
        unset($_SESSION['mid'], $_SESSION['mname']);
        header('Location: ?p=home');
        exit;
    }
}
if ($in && $post) {
    if ($p === 'activate_lic_key' && is_super_admin()) {
        $kstr = trim(isset($_POST['lic_key']) ? $_POST['lic_key'] : '');
        $parsed = parse_vendor_key($kstr);
        if (is_array($parsed)) {
            $def = lic_default_payload();
            $clean = array_merge($def, $parsed);
            $clean['mod_dash'] = 1;
            ksort($clean);
            $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
            $sig  = lic_sign_data($clean);
            set_cms('lic_asm2_key', '');
            set_cms('lic_payload', $json);
            set_cms('lic_sig', $sig);
            $GLOBALS['_LIC_CACHE'] = null;
            if (!empty($parsed['theme'])) set_cms('theme', $parsed['theme']);
            if (!empty($parsed['font'])) set_cms('font', $parsed['font']);
            $_SESSION['flash'] = 'کد لایسنس با موفقیت تایید و فعال‌سازی شد!';
        } else {
            $_SESSION['flash'] = 'کد لایسنس واردشده نامعتبر است. لطفاً کد تولیدشده توسط رمزساز فروشنده را به طور کامل کپی و جای‌گذاری نمایید.';
        }
        header('Location: ?p=settings');
        exit;
    }
        if ($p === 'auth_bulk_del' && can('authority')) {
        $tbl = (isset($_POST['tbl']) && $_POST['tbl'] === 'names') ? 'auth_names' : 'auth_subject';
        $tab = $tbl === 'auth_names' ? 'names' : 'subject';
        if (!empty($_POST['del_all'])) {
            $q = trim(isset($_POST['q']) ? $_POST['q'] : '');
            if ($q !== '') {
                $w = "WHERE norm LIKE :q OR heading LIKE :q2";
                $db->prepare("DELETE FROM $tbl $w")->execute(array(':q' => '%' . fts_norm($q) . '%', ':q2' => '%' . $q . '%'));
            } else {
                $db->exec("TRUNCATE TABLE $tbl RESTART IDENTITY CASCADE");
            }
            $_SESSION['flash'] = 'تمامی سرشناسه‌های ' . ($q !== '' ? 'یافت‌شده' : 'موجود') . ' با موفقیت حذف شدند.';
        } elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_filter(array_map('intval', $_POST['ids']));
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $db->prepare("DELETE FROM $tbl WHERE id IN ($placeholders)")->execute($ids);
                $_SESSION['flash'] = '' . tr_num(count($ids)) . ' سرشناسه با موفقیت حذف شد.';
            }
        }
        header('Location: ?p=authority&tab=' . $tab);
        exit;
    }
if (($p === 'auth_save' || $p === 'auth_del') && can('authority')) {
        $subj = (isset($_POST['tbl']) && $_POST['tbl'] === 'subject');
        $tbl = $subj ? 'auth_subject' : 'auth_names';
        $aid = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($p === 'auth_del') {
            $db->prepare("DELETE FROM $tbl WHERE id=?")->execute(array($aid));
            header('Location: ?p=authority&tab=' . ($subj ? 'subject' : 'names') . '&msg=deleted');
            exit;
        }
        $hd = trim(isset($_POST['heading']) ? $_POST['heading'] : '');
        $vr = trim(str_replace(array('؛', ';', "\n"), '|', isset($_POST['variants']) ? $_POST['variants'] : ''));
        $note = trim(isset($_POST['note']) ? $_POST['note'] : '');
        $msg = 'saved';
        if ($hd !== '') {
            try {
                if ($subj) {
                    $br = trim(isset($_POST['broader']) ? $_POST['broader'] : ''); $rl = trim(isset($_POST['related']) ? $_POST['related'] : '');
                    if ($aid) $db->prepare("UPDATE auth_subject SET heading=?,variants=?,broader=?,related=?,note=? WHERE id=?")->execute(array($hd, $vr, $br, $rl, $note, $aid));
                    else { $st_a = $db->prepare("INSERT INTO auth_subject(heading,variants,broader,related,note) VALUES(?,?,?,?,?) RETURNING id"); $st_a->execute(array($hd, $vr, $br, $rl, $note)); $aid = (int)$st_a->fetchColumn(); }
                } else {
                    $nt = (isset($_POST['ntype']) && $_POST['ntype'] === 'corp') ? 'corp' : 'person';
                    $yr = trim(isset($_POST['years']) ? $_POST['years'] : ''); $fl = trim(isset($_POST['field']) ? $_POST['field'] : '');
                    if ($aid) $db->prepare("UPDATE auth_names SET heading=?,ntype=?,variants=?,years=?,field=?,note=? WHERE id=?")->execute(array($hd, $nt, $vr, $yr, $fl, $note, $aid));
                    else { $st_a = $db->prepare("INSERT INTO auth_names(heading,ntype,variants,years,field,note) VALUES(?,?,?,?,?,?) RETURNING id"); $st_a->execute(array($hd, $nt, $vr, $yr, $fl, $note)); $aid = (int)$st_a->fetchColumn(); }
                }
                auth_save_norm($tbl, $aid);
            } catch (Exception $e) { $msg = 'dup'; }
        }
        header('Location: ?p=authority&tab=' . ($subj ? 'subject' : 'names') . '&msg=' . $msg);
        exit;
    }
    if ($p === 'auth_import' && can('authority')) {
        $subj = (isset($_POST['tbl']) && $_POST['tbl'] === 'subject');
        $n = 0;
        if (!empty($_FILES['f']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['f']['name'], PATHINFO_EXTENSION));
            $db->beginTransaction();
            if ($ext === 'mrc' || $ext === 'txt' || $ext === 'iso' || $ext === 'dat') {
                $raw_mrc = file_get_contents($_FILES['f']['tmp_name']);
                $cols_dummy = array_keys($F);
                $parsed_recs = parse_iso2709_records($raw_mrc, $cols_dummy);
                $ins_sub = $db->prepare("INSERT INTO auth_subject(heading) VALUES(?) ON CONFLICT (heading) DO NOTHING");
                $ins_nam = $db->prepare("INSERT INTO auth_names(heading, ntype) VALUES(?, 'person') ON CONFLICT (heading) DO NOTHING");
                foreach ($parsed_recs as $pr) {
                    if ($subj && !empty($pr['f606'])) {
                        foreach (preg_split('/\s*[؛;]\s*/u', $pr['f606']) as $s) {
                            $s = trim($s);
                            if ($s !== '') { $ins_sub->execute(array($s)); $n += $ins_sub->rowCount(); }
                        }
                    } elseif (!$subj) {
                        foreach (array('f700', 'f710', 'f200f') as $fc) {
                            if (!empty($pr[$fc])) {
                                foreach (explode('؛', $pr[$fc]) as $pname) {
                                    $pname = trim($pname);
                                    if ($pname !== '') { $ins_nam->execute(array($pname)); $n += $ins_nam->rowCount(); }
                                }
                            }
                        }
                    }
                }
            } else {
                $rows = parse_excel($_FILES['f']['tmp_name']);
                foreach ($rows as $idx => $r) {
                    $c0 = isset($r[0]) ? trim($r[0]) : '';
                    if ($c0 === '' || ($idx === 0 && (stripos($c0, 'heading') !== false || mb_strpos($c0, 'سرشناسه') !== false || mb_strpos($c0, 'عنوان') !== false))) continue;
                    $vr = trim(str_replace(array('؛', ';'), '|', isset($r[$subj ? 1 : 2]) ? $r[$subj ? 1 : 2] : ''));
                    if ($subj) {
                        $db->prepare("INSERT INTO auth_subject(heading,variants,broader,related,note) VALUES(?,?,?,?,?) ON CONFLICT (heading) DO NOTHING")->execute(array($c0, $vr, isset($r[2]) ? $r[2] : '', isset($r[3]) ? $r[3] : '', isset($r[4]) ? $r[4] : ''));
                    } else {
                        $db->prepare("INSERT INTO auth_names(heading,ntype,variants,years,field,note) VALUES(?,?,?,?,?,?) ON CONFLICT (heading) DO NOTHING")->execute(array($c0, (isset($r[1]) && stripos($r[1], 'corp') !== false) || (isset($r[1]) && mb_strpos($r[1], 'سازمان') !== false) ? 'corp' : 'person', $vr, isset($r[3]) ? $r[3] : '', isset($r[4]) ? $r[4] : '', isset($r[5]) ? $r[5] : ''));
                    }
                    $n++;
                }
            }
            $db->commit();
            $db->exec("UPDATE auth_subject SET norm='' WHERE norm IS NULL");
            foreach (array('auth_subject', 'auth_names') as $t) foreach ($db->query("SELECT id FROM $t WHERE norm IS NULL OR norm=''")->fetchAll(PDO::FETCH_COLUMN) as $i) auth_save_norm($t, $i);
        }
        $_SESSION['flash'] = '' . tr_num($n) . ' سرشناسه از فایل بررسی و وارد شد.';
        header('Location: ?p=authority&tab=' . ($subj ? 'subject' : 'names'));
        exit;
    }
    if ($p === 'auth_harvest' && can('authority')) {
        $n1 = 0; $n2 = 0;
        $db->beginTransaction();
        $ins = $db->prepare("INSERT INTO auth_names(heading,ntype) VALUES(?,?) ON CONFLICT (heading) DO NOTHING");
        foreach (array(array('f700', 'person'), array('f710', 'corp')) as $fc) {
            foreach ($db->query("SELECT DISTINCT {$fc[0]} FROM records WHERE {$fc[0]} IS NOT NULL AND TRIM({$fc[0]})<>''")->fetchAll(PDO::FETCH_COLUMN) as $v) { $ins->execute(array(trim($v), $fc[1])); $n1 += $ins->rowCount(); }
        }
        $ins2 = $db->prepare("INSERT INTO auth_subject(heading) VALUES(?) ON CONFLICT (heading) DO NOTHING");
        foreach ($db->query("SELECT DISTINCT f606 FROM records WHERE f606 IS NOT NULL AND TRIM(f606)<>''")->fetchAll(PDO::FETCH_COLUMN) as $v) {
            foreach (preg_split('/\s*[؛;]\s*/u', $v) as $s) { $s = trim($s); if ($s !== '') { $ins2->execute(array($s)); $n2 += $ins2->rowCount(); } }
        }
        $db->commit();
        foreach (array('auth_subject', 'auth_names') as $t) foreach ($db->query("SELECT id FROM $t WHERE norm IS NULL OR norm=''")->fetchAll(PDO::FETCH_COLUMN) as $i) auth_save_norm($t, $i);
        $_SESSION['flash'] = 'از رکوردهای موجود ' . tr_num($n1) . ' نام (مشاهیر/سازمان) و ' . tr_num($n2) . ' سرشناسه موضوعی جدید ساخته شد.';
        header('Location: ?p=authority&tab=tools');
        exit;
    }
    if ($p === 'auth_apply' && can('authority') && is_super_admin()) {
        auto_backup('auth_apply');
        $n = 0;
        foreach (array(array('auth_names', array('f700', 'f710', 'f200f')), array('auth_subject', array('f606'))) as $tb) {
            foreach ($db->query("SELECT heading, variants FROM {$tb[0]} WHERE variants<>''")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                foreach (explode('|', $a['variants']) as $vv) {
                    $vv = trim($vv);
                    if ($vv === '' || $vv === $a['heading']) continue;
                    foreach ($tb[1] as $fcol) {
                        $u = $db->prepare("UPDATE records SET $fcol=? WHERE $fcol=?");
                        $u->execute(array($a['heading'], $vv));
                        $n += $u->rowCount();
                    }
                }
            }
        }
        if ($n) fts_rebuild();
        $_SESSION['flash'] = '' . tr_num($n) . ' مقدار در رکوردها با سرشناسهٔ معتبر جایگزین شد (نسخهٔ پشتیبان خودکار گرفته شد).';
        header('Location: ?p=authority&tab=tools');
        exit;
    }
    if ($p === 'save_chat' && can('cms')) {
        foreach (array('chat_mode', 'chat_provider', 'chat_endpoint', 'chat_model', 'chat_system', 'chat_welcome', 'chat_daily', 'chat_limit') as $k) {
            if (isset($_POST[$k])) set_cms($k, trim($_POST[$k]));
        }
        if (isset($_POST['chat_key']) && trim($_POST['chat_key']) !== '' && strpos($_POST['chat_key'], '•') === false) set_cms('chat_key', aseman_encrypt_secret(trim($_POST['chat_key'])));
        if (isset($_POST['clear_key'])) set_cms('chat_key', '');
        if (isset($_POST['test'])) {
            $err = '';
            $rep = chat_ai('سلام، این یک آزمایش اتصال است.', array(), array(), $err);
            $_SESSION['flash'] = $rep !== '' ? 'اتصال به هوش مصنوعی موفق بود. پاسخ: ' . mb_substr($rep, 0, 200, 'UTF-8') : 'اتصال ناموفق: ' . $err;
        }
        header('Location: ?p=cms&box=chat&msg=saved');
        exit;
    }
    if ($p === 'save_geo' && can('stats') && is_super_admin()) {
        set_cms('geo_mode', (isset($_POST['geo_mode']) && $_POST['geo_mode'] === 'ipapi') ? 'ipapi' : 'off');
        header('Location: ?p=stats&tab=online&msg=saved');
        exit;
    }
    if ($p === 'merge_titles' && is_super_admin()) {
        $keep = (int)$_POST['keep'];
        $ids = array_filter(array_map('intval', explode(',', isset($_POST['ids']) ? $_POST['ids'] : '')));
        if ($keep && count($ids) > 1) {
            $bk = auto_backup('merge');
            $n = merge_titles($keep, $ids);
            blog('merge', 'keep=' . $keep . ' ids=' . implode(',', $ids), $n);
            $_SESSION['flash'] = '' . tr_num($n) . ' رکورد تکراری در رکورد ' . tr_num($keep) . ' ادغام شد (مجلدات و شماره‌های ثبت همهٔ محل‌ها حفظ شد). پشتیبان: ' . $bk;
        }
        header('Location: ?p=bulk&tab=audit&issue=' . urlencode(isset($_POST['issue']) ? $_POST['issue'] : 'dup_isbn'));
        exit;
    }
    if ($p === 'bulk_run' && is_super_admin()) {
        $act = isset($_POST['act']) ? $_POST['act'] : '';
        $sel = array('ids' => trim(isset($_POST['ids']) ? $_POST['ids'] : ''), 'q' => trim(isset($_POST['q']) ? $_POST['q'] : ''), 'type' => isset($_POST['type']) ? $_POST['type'] : '', 'loc' => isset($_POST['sloc']) ? $_POST['sloc'] : '', 'from' => isset($_POST['from']) ? $_POST['from'] : '', 'to' => isset($_POST['to']) ? $_POST['to'] : '');
        $P = array();
        foreach (array('field', 'mode', 'value', 'find', 'loc', 'vols', 'start', 'shift', 'move', 'regen', 'renumber', 'from', 'to') as $k) if (isset($_POST['p_' . $k]) && $_POST['p_' . $k] !== '') $P[$k] = $_POST['p_' . $k];
        if ($act === 'items_cmd' && !empty($_POST['cmd'])) {
            $cm = parse_bulk_cmd($_POST['cmd']);
            if (isset($cm['id'])) $sel['ids'] = $cm['id'];
            if (isset($cm['ids'])) $sel['ids'] = $cm['ids'];
            foreach (array('loc', 'vols', 'start', 'shift', 'move', 'regen', 'renumber', 'from', 'to') as $k) if (isset($cm[$k])) $P[$k] = $cm[$k];
        }
        if ($act === 'del_titles' && isset($sel['loc']) && $sel['loc'] !== '' && !isset($P['loc'])) $P['loc'] = $sel['loc'];
        if ($act === 'items_cmd' && isset($P['loc'])) { $sel['loc'] = $P['loc']; }
        $ids = bulk_ids($sel);
        $dry = empty($_POST['go']);
        if (!$dry && (empty($_POST['confirm']) || ($act === 'del_titles' && trim(isset($_POST['word']) ? $_POST['word'] : '') !== 'حذف'))) { $dry = true; $_SESSION['bulk_note'] = 'برای اجرای واقعی، تأیید را علامت بزنید' . ($act === 'del_titles' ? ' و کلمهٔ «حذف» را تایپ کنید.' : '.'); }
        $bk = '';
        if (!$dry) $bk = auto_backup('bulk_' . $act);
        if (!$ids) { $res = array(0, array('هیچ رکوردی با این شرایط انتخاب نشد. حداقل یک فیلتر وارد کنید.')); }
        else $res = bulk_run($act, $ids, $P, $dry);
        if (!$dry && $ids) { blog($act, json_encode(array('sel' => $sel, 'p' => $P), JSON_UNESCAPED_UNICODE), $res[0]); if ($act !== 'set_field') fts_rebuild(); }
        $_SESSION['bulk_res'] = array('dry' => $dry, 'ids' => count($ids), 'msgs' => array_slice($res[1], 0, 60), 'n' => $res[0], 'bk' => $bk);
        header('Location: ?p=bulk&tab=edit');
        exit;
    }
    if ($p === 'idx_act' && is_super_admin()) {
        $a = isset($_POST['act']) ? $_POST['act'] : '';
        if ($a === 'rebuild') { $n = fts_rebuild(); $_SESSION['flash'] = 'ایندکس متنی برای ' . tr_num($n) . ' رکورد بازسازی شد.'; }
        elseif ($a === 'analyze') { $db->exec("ANALYZE"); $_SESSION['flash'] = 'آمار برنامهٔ پرس‌وجو (ANALYZE) در PostgreSQL به‌روز شد.'; }
        elseif ($a === 'reindex') { $db->exec("REINDEX DATABASE " . PG_DB); $_SESSION['flash'] = 'همهٔ ایندکس‌های جدول‌ها در PostgreSQL بازسازی شد.'; }
        elseif ($a === 'vacuum') { auto_backup('vacuum'); $db->exec("VACUUM"); $_SESSION['flash'] = 'فشرده‌سازی پایگاه داده انجام شد.'; }
        elseif ($a === 'fts_optimize' && true) { try { $db->exec("REINDEX TABLE records_fts"); $_SESSION['flash'] = 'ایندکس GIN متنی در PostgreSQL بهینه شد.'; } catch (Exception $e) { $_SESSION['flash'] = 'ایندکس متنی در دسترس نیست.'; } }
        header('Location: ?p=bulk&tab=index');
        exit;
    }
    if ($p === 'repair' && is_super_admin()) {
        $a = isset($_POST['act']) ? $_POST['act'] : '';
        $msg = '';
        if ($a === 'release_locks') { if (isset($_POST['only_expired'])) $db->prepare("DELETE FROM rec_locks WHERE ts<=?")->execute(array(time() - 600)); else $db->exec("DELETE FROM rec_locks"); $msg = 'قفل‌ها آزاد شد.'; }
        elseif ($a === 'release_one') { $db->prepare("DELETE FROM rec_locks WHERE rid=?")->execute(array((int)$_POST['rid'])); $msg = 'قفل رکورد آزاد شد.'; }
        elseif ($a === 'checkpoint') { $db->exec("ANALYZE"); $msg = 'نقطهٔ بازبینی و ANALYZE در PostgreSQL ثبت شد.'; }
        else {
            $bk = auto_backup('repair_' . $a);
            if ($a === 'del_orphan_items') { $c = $db->exec("DELETE FROM items WHERE rid NOT IN (SELECT id FROM records)"); $msg = tr_num($c) . ' مجلد یتیم حذف شد.'; }
            elseif ($a === 'del_orphan_locrec') { $c = $db->exec("DELETE FROM loc_rec WHERE rid NOT IN (SELECT id FROM records) OR (rid, loc) NOT IN (SELECT rid, loc FROM items)"); $msg = tr_num($c) . ' سریال رکورد نامعتبر حذف شد.'; }
            elseif ($a === 'rebuild_locrec') { $c = 0; foreach ($db->query("SELECT rid, loc FROM items GROUP BY rid, loc ORDER BY MIN(id)")->fetchAll(PDO::FETCH_ASSOC) as $g) { $b = $db->prepare("SELECT 1 FROM loc_rec WHERE rid=? AND loc=?"); $b->execute(array($g['rid'], $g['loc'])); if (!$b->fetchColumn()) { ensure_loc_rec($g['rid'], $g['loc']); $c++; } } $msg = tr_num($c) . ' سریال رکورد ساخته شد.'; }
            elseif ($a === 'sync_copies') { $db->exec("UPDATE records SET copies=(SELECT COUNT(*) FROM items WHERE rid=records.id)"); $msg = 'شمارندهٔ نسخه‌ها با مجلدها هماهنگ شد.'; }
            elseif ($a === 'fix_text') { $c = 0; $cols = array_keys($F); $up = array(); foreach ($db->query("SELECT * FROM records")->fetchAll(PDO::FETCH_ASSOC) as $r) { $set = array(); $par = array(); foreach ($cols as $col) { $v = (string)$r[$col]; $nv = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FEFF}]/u', '', mb_convert_encoding($v, 'UTF-8', 'UTF-8')); if ($nv !== $v) { $set[] = "$col=?"; $par[] = $nv; } } if ($set) { $par[] = $r['id']; $db->prepare("UPDATE records SET " . implode(',', $set) . " WHERE id=?")->execute($par); fts_sync($r['id']); $c++; } } $msg = tr_num($c) . ' رکورد پاک‌سازی شد.'; }
            elseif ($a === 'move_unknown_loc') { $t = isset($_POST['target']) ? $_POST['target'] : ''; if (in_array($t, $Locations)) { $c = 0; foreach ($db->query("SELECT id, rid, loc FROM items")->fetchAll(PDO::FETCH_ASSOC) as $it) { if (!in_array($it['loc'], $Locations)) { $nr = next_reg_no($t); $db->prepare("UPDATE items SET loc=?, reg_no=? WHERE id=?")->execute(array($t, $nr, $it['id'])); ensure_loc_rec($it['rid'], $t); $c++; } } $db->exec("DELETE FROM loc_rec WHERE (rid, loc) NOT IN (SELECT rid, loc FROM items)"); $msg = tr_num($c) . ' مجلد با محل نامعتبر به «' . $t . '» منتقل شد.'; } }
            $msg .= ' (پشتیبان: ' . $bk . ')';
        }
        blog('repair_' . $a, $msg, 0);
        $_SESSION['flash'] = '' . $msg;
        header('Location: ?p=bulk&tab=health');
        exit;
    }
    if ($p === 'save' && can('form')) {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $cols = array_keys($F);
        $d = array();
        foreach ($cols as $c) $d[$c] = trim(isset($_POST[$c]) ? $_POST[$c] : '');
        $rtype = array_key_exists(isset($_POST['rtype']) ? $_POST['rtype'] : '', $RTypes) ? $_POST['rtype'] : 'book';
        $d['rtype'] = $rtype;
        $facc = in_array($_POST['file_access']??'', ['public','member','private']) ? $_POST['file_access'] : 'public';
        $loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        if (!in_array($loc, $Locations)) $loc = $Locations[0];
        $vols = max(0, min(5000, (int)en_num(isset($_POST['vols']) ? $_POST['vols'] : 1)));
        $first = trim(en_num(isset($_POST['reg_no']) ? $_POST['reg_no'] : ''));

        $file_path = isset($_POST['old_file']) ? $_POST['old_file'] : '';
        if (!empty($_FILES['file']['name'])) {
            $up_att = secure_upload_file(
                $_FILES['file'],
                array('pdf','doc','docx','jpg','jpeg','png','webp','zip','rar','mp3','mp4','txt'),
                array('application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','image/jpeg','image/png','image/webp','application/zip','application/x-rar-compressed','application/vnd.rar','audio/mpeg','video/mp4','text/plain'),
                'att_'
            );
            if ($up_att !== '') $file_path = $up_att;
        }
        $d['file'] = $file_path;
        if ($id) {
            if (!can_edit_title($id)) { header('Location: ?p=rec'); exit; }
            if (lock_blocked($id)) { header("Location: ?p=marc&id=$id&msg=locked"); exit; }
            $set_parts = array();
            foreach ($cols as $c) $set_parts[] = "$c=?";
            $params = array();
            foreach ($cols as $c) $params[] = $d[$c];
            $params[] = $rtype;
            $params[] = $file_path;
            $params[] = $facc;
            $params[] = $id;
            $db->prepare("UPDATE records SET " . implode(',', $set_parts) . ",rtype=?,file=?,file_access=? WHERE id=?")->execute($params);
            if ($vols > 0) alloc_items($id, $loc, $vols, $first);
            fts_sync($id);
            lock_release($id);
            $msg = 'saved';
        } else {
            $res = import_title($d, $loc, max(1, $vols), $first);
            $id = $res[0];
            if (!$id) {
                $_SESSION['flash'] = isset($GLOBALS['lic_err']) ? $GLOBALS['lic_err'] : 'سقف مجاز ثبت رکورد در مجوز این مرکز تکمیل شده است.';
                header('Location: ?p=rec');
                exit;
            }
            $msg = $res[1] ? 'saved' : 'dup';
            if (!$res[1] && $file_path !== '') {
                $db->prepare("UPDATE records SET file=? WHERE id=? AND (file IS NULL OR file='')")->execute(array($file_path, $id));
            } elseif ($res[1]) {
                $db->prepare("UPDATE records SET file=? WHERE id=?")->execute(array($file_path, $id));
            }
        }
        header("Location: ?p=marc&id=$id&msg=$msg");
        exit;
    }
    if ($p === 'del' && (can('form') || can('rec'))) {
        $id = (int)$_POST['id'];
        $loc_sql = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM items WHERE rid=? $loc_sql AND id NOT IN (SELECT rid FROM loans WHERE ret IS NULL)")->execute(array($id));
        $db->prepare("DELETE FROM loc_rec WHERE rid=? AND loc NOT IN (SELECT loc FROM items WHERE rid=?)")->execute(array($id, $id));
        sync_copies($id);
        $left = $db->prepare("SELECT COUNT(*) FROM items WHERE rid=?");
        $left->execute(array($id));
        if ((int)$left->fetchColumn() === 0) { $db->prepare("DELETE FROM records WHERE id=?")->execute(array($id)); fts_sync($id); }
        header('Location: ?p=rec');
        exit;
    }
    if (($p === 'delitem' || $p === 'upditem') && can('form')) {
        $iid = (int)$_POST['id'];
        $st = $db->prepare("SELECT * FROM items WHERE id=?");
        $st->execute(array($iid));
        $it = $st->fetch(PDO::FETCH_ASSOC);
        if ($it && (!is_loc_restricted() || $it['loc'] === user_loc())) {
            $on = $db->prepare("SELECT COUNT(*) FROM loans WHERE rid=? AND ret IS NULL");
            $on->execute(array($iid));
            if ($p === 'delitem') {
                if ((int)$on->fetchColumn() === 0) {
                    $db->prepare("DELETE FROM items WHERE id=?")->execute(array($iid));
                    $left = $db->prepare("SELECT COUNT(*) FROM items WHERE rid=? AND loc=?");
                    $left->execute(array($it['rid'], $it['loc']));
                    if ((int)$left->fetchColumn() === 0) $db->prepare("DELETE FROM loc_rec WHERE rid=? AND loc=?")->execute(array($it['rid'], $it['loc']));
                    sync_copies($it['rid']);
                }
            } else {
                $nreg = trim(en_num(isset($_POST['reg_no']) ? $_POST['reg_no'] : ''));
                $nvol = trim(isset($_POST['vol']) ? $_POST['vol'] : '');
                $chk = $db->prepare("SELECT 1 FROM items WHERE loc=? AND reg_no=? AND id<>?");
                $chk->execute(array($it['loc'], $nreg, $iid));
                if ($nreg !== '' && !$chk->fetchColumn()) {
                    $db->prepare("UPDATE items SET reg_no=?, vol=? WHERE id=?")->execute(array($nreg, $nvol, $iid));
                }
            }
        }
        header('Location: ' . ($it ? '?p=marc&id=' . (int)$it['rid'] : '?p=rec'));
        exit;
    }
    if ($p === 'add_hold' && (can('imp_exp') || can('form'))) {
        $rid = (int)$_POST['rid'];
        $loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        if (!in_array($loc, $Locations)) $loc = $Locations[0];
        $vols = max(1, min(5000, (int)en_num(isset($_POST['vols']) ? $_POST['vols'] : 1)));
        $ex = $db->prepare("SELECT COUNT(*) FROM records WHERE id=?");
        $ex->execute(array($rid));
        if ($ex->fetchColumn()) {
            $ids = alloc_items($rid, $loc, $vols, isset($_POST['reg_no']) ? $_POST['reg_no'] : '');
            $_SESSION['flash'] = '' . tr_num(count($ids)) . ' مجلد با شماره ثبت اختصاصی در «' . $loc . '» ثبت شد (بدون فهرست‌نویسی مجدد).';
        }
        $back = isset($_POST['back']) && $_POST['back'] === 'marc' ? '?p=marc&id=' . $rid : '?p=imp_exp&xq=' . urlencode(isset($_POST['xq']) ? $_POST['xq'] : '');
        header("Location: $back");
        exit;
    }
    if ($p === 'upload_img' && can('cms')) {
        header('Content-Type: application/json; charset=utf-8');
        $res = array('error' => 'فایل تصویر انتخاب نشد.');
        if (!empty($_FILES['f']['name'])) {
            $up_ed = secure_upload_file($_FILES['f'], array('jpg','jpeg','png','gif','webp'), array('image/jpeg','image/png','image/gif','image/webp'), 'ed_');
            if ($up_ed !== '') $res = array('url' => $up_ed);
            else $res = array('error' => 'فقط تصاویر معتبر (JPG, PNG, GIF, WebP) مجاز است.');
        }
        echo json_encode($res);
        exit;
    }
    if ($p === 'save_news' && can('cms')) {
        $nid = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $sub = trim(isset($_POST['subtitle']) ? $_POST['subtitle'] : '');
        $body = clean_html(isset($_POST['body']) ? $_POST['body'] : '');
        $pub = trim(isset($_POST['pub']) ? $_POST['pub'] : '');
        $act = isset($_POST['active']) ? 1 : 0;
        $img = isset($_POST['old_img']) ? $_POST['old_img'] : '';
        if (isset($_POST['rm_img'])) $img = '';
        if (!empty($_FILES['img']['name'])) {
            $up_nw = secure_upload_file($_FILES['img'], array('jpg','jpeg','png','gif','webp'), array('image/jpeg','image/png','image/gif','image/webp'), 'news_');
            if ($up_nw !== '') $img = $up_nw;
        }
        if ($title !== '') {
            if ($nid) {
                $db->prepare("UPDATE news SET title=?,subtitle=?,body=?,img=?,pub=?,active=? WHERE id=?")->execute(array($title, $sub, $body, $img, $pub, $act, $nid));
            } else {
                $db->prepare("INSERT INTO news(title,subtitle,body,img,pub,active) VALUES(?,?,?,?,?,?)")->execute(array($title, $sub, $body, $img, $pub, $act));
            }
        }
        header('Location: ?p=cms&box=news&msg=saved');
        exit;
    }
    if ($p === 'del_news' && can('cms')) {
        $db->prepare("DELETE FROM news WHERE id=?")->execute(array((int)$_POST['id']));
        header('Location: ?p=cms&box=news');
        exit;
    }
    if ($p === 'save_z3950_cfg' && can('imp_exp')) {
        set_cms('z3950_host', trim(en_num(isset($_POST['z_host']) ? $_POST['z_host'] : 'z3950.nlai.ir')));
        set_cms('z3950_port', trim(en_num(isset($_POST['z_port']) ? $_POST['z_port'] : '210')));
        set_cms('z3950_db',   trim(isset($_POST['z_db']) ? $_POST['z_db'] : 'Default'));
        set_cms('z3950_syn',  trim(isset($_POST['z_syn']) ? $_POST['z_syn'] : 'unimarc'));
        $_SESSION['flash'] = 'تنظیمات سرور Z39.50 (میزبان، پورت، نام پایگاه و فرمت رکورد) با موفقیت ذخیره شد.';
        header('Location: ?p=imp_exp');
        exit;
    }
    if ($p === 'imp_iso_preview' && (can('imp_exp') || can('nlai') || can('form'))) {
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        $from_page = isset($_POST['from_page']) ? $_POST['from_page'] : 'imp_exp';
        $_SESSION['iso_preview'] = array();
        $_SESSION['iso_loc'] = $def_loc;
        $_SESSION['iso_from'] = $from_page;
        $cols = array_keys($F);
        if (!empty($_FILES['f']['tmp_name'])) {
            $raw = file_get_contents($_FILES['f']['tmp_name']);
            $_SESSION['iso_preview'] = parse_iso2709_records($raw, $cols);
        } elseif (!empty($_POST['raw_text'])) {
            $_SESSION['iso_preview'] = parse_iso2709_records($_POST['raw_text'], $cols);
        } elseif (!empty($_POST['z_query'])) {
            $z_host = !empty($_POST['z_host']) ? trim(en_num($_POST['z_host'])) : (get_cms('z3950_host') ?: 'z3950.nlai.ir');
            $z_port = !empty($_POST['z_port']) ? trim(en_num($_POST['z_port'])) : (get_cms('z3950_port') ?: '210');
            $z_db   = !empty($_POST['z_db'])   ? trim($_POST['z_db'])          : (get_cms('z3950_db')   ?: 'Default');
            $z_attr = !empty($_POST['z_attr']) ? trim(en_num($_POST['z_attr'])) : '1016';
            $z_syn  = !empty($_POST['z_syn'])  ? trim($_POST['z_syn'])         : (get_cms('z3950_syn')  ?: 'unimarc');
            if (!empty($_POST['save_z_cfg'])) {
                set_cms('z3950_host', $z_host);
                set_cms('z3950_port', $z_port);
                set_cms('z3950_db',   $z_db);
                set_cms('z3950_syn',  $z_syn);
            }
            $z_info = '';
            $_SESSION['iso_preview'] = fetch_z3950_records($z_host, $z_port, $z_db, $z_attr, $_POST['z_query'], $z_syn, $cols, $z_info);
            if ($z_info !== '') $_SESSION['flash'] = $z_info;
        }
        header("Location: ?p=$from_page&show_preview=1");
        exit;
    }
    if ($p === 'imp_iso_confirm' && (can('imp_exp') || can('nlai') || can('form'))) {
        $cnt = 0;
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? trim($_POST['loc']) : (isset($_SESSION['iso_loc']) ? $_SESSION['iso_loc'] : $Locations[0]));
        $from_page = isset($_POST['from_page']) ? $_POST['from_page'] : 'imp_exp';
        $cols = array_keys($F);
        $n_new = 0; $n_att = 0; $n_vol = 0;
        if (!in_array($def_loc, $Locations)) $def_loc = $Locations[0];
        if (!empty($_POST['recs']) && is_array($_POST['recs'])) {
            $db->beginTransaction();
            foreach ($_POST['recs'] as $idx => $r_data) {
                if (empty($_POST['sel'][$idx])) continue;
                $d = array();
                foreach ($cols as $c) $d[$c] = isset($r_data[$c]) ? trim($r_data[$c]) : '';
                if ($d['f200a'] === '') continue;
                $d['rtype'] = (isset($r_data['rtype']) && isset($RTypes[$r_data['rtype']])) ? $r_data['rtype'] : 'book';
                $cp = max(1, min(5000, (int)en_num(isset($r_data['copies']) ? $r_data['copies'] : 1)));
                $first_reg = isset($r_data['reg_no']) ? trim(en_num($r_data['reg_no'])) : '';
                $res = import_title($d, $def_loc, $cp, $first_reg);
                if ($res[0]) {
                    if ($res[1]) $n_new++; else $n_att++;
                    $n_vol += $res[2];
                    $cnt++;
                }
            }
            $db->commit();
        }
        $_SESSION['flash'] = 'تایید نهایی و ثبت انجام شد: ' . tr_num($cnt) . ' عنوان پردازش شد (' . tr_num($n_new) . ' رکورد جدید، ' . tr_num($n_att) . ' عنوان تکراری، مجموعاً ' . tr_num($n_vol) . ' مجلد در کتابخانه «' . $def_loc . '» ثبت گردید).';
        unset($_SESSION['iso_preview'], $_SESSION['iso_loc'], $_SESSION['iso_ws'], $_SESSION['iso_src_type']);
        header("Location: ?p=$from_page&msg=iso_$cnt");
        exit;
    }
    if ($p === 'restore_bk' && can('backup')) {
        $cnt_r = 0; $cnt_m = 0;
        $target_loc = is_loc_restricted() ? user_loc() : trim(isset($_POST['target_loc']) ? $_POST['target_loc'] : '');
        if (!empty($_FILES['bk_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['bk_file']['name'], PATHINFO_EXTENSION));
            $lic_bk = get_license();
            if ($ext === 'db' && is_super_admin() && empty($lic_bk['lock_raw_db'])) {
                $fh_chk = @fopen($_FILES['bk_file']['tmp_name'], 'rb');
                $magic_hdr = $fh_chk ? @fread($fh_chk, 16) : '';
                if ($fh_chk) @fclose($fh_chk);
                if ($magic_hdr !== "SQLite format 3 ") {
                    header('Location: ?p=backup');
                    exit;
                }
                $sq = new PDO('sqlite:' . $_FILES['bk_file']['tmp_name']);
                $sq->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->beginTransaction();
                $tables = array('cms', 'users', 'records', 'items', 'loc_rec', 'members', 'loans', 'orders', 'tickets', 'gallery', 'portal_boxes', 'news', 'auth_subject', 'auth_names');
                foreach ($tables as $tb) {
                    try {
                        $rows_sq = $sq->query("SELECT * FROM $tb")->fetchAll(PDO::FETCH_ASSOC);
                        if (!$rows_sq) continue;
                        $db->exec("TRUNCATE TABLE $tb RESTART IDENTITY CASCADE");
                        $cols_sq = array_keys($rows_sq[0]);
                        $qs_sq = implode(',', array_fill(0, count($cols_sq), '?'));
                        $ins_sq = $db->prepare("INSERT INTO $tb(" . implode(',', $cols_sq) . ") VALUES($qs_sq) ON CONFLICT DO NOTHING");
                        foreach ($rows_sq as $r_sq) $ins_sq->execute(array_values($r_sq));
                        if (in_array('id', $cols_sq)) {
                            $db->exec("SELECT setval(pg_get_serial_sequence('$tb', 'id'), COALESCE((SELECT MAX(id) FROM $tb), 1), true)");
                        }
                    } catch (Exception $e) {}
                }
                $db->commit();
                fts_rebuild();
                header('Location: ?p=backup&msg=db_restored');
                exit;
            } elseif ($ext === 'json') {
                $json_str = file_get_contents($_FILES['bk_file']['tmp_name']);
                $data_bk = json_decode($json_str, true);
                if (is_array($data_bk)) {
                    $cols = array_keys($F);
                    if (!empty($data_bk['records']) && is_array($data_bk['records'])) {
                        $old2new = array();
                        $has_items = !empty($data_bk['items']) && is_array($data_bk['items']);
                        foreach ($data_bk['records'] as $rec) {
                            $d = array();
                            foreach ($cols as $c) $d[$c] = isset($rec[$c]) ? $rec[$c] : '';
                            if (empty($d['f200a'])) continue;
                            $d['rtype'] = isset($rec['rtype']) ? $rec['rtype'] : 'book';
                            $d['file'] = isset($rec['file']) ? $rec['file'] : '';
                            $rid = find_title($d);
                            if (!$rid) $rid = insert_title($d);
                            if (isset($rec['id'])) $old2new[$rec['id']] = $rid;
                            if (!$has_items) {
                                $cp = max(1, (int)(isset($rec['copies']) ? $rec['copies'] : 1));
                                $lc = ($target_loc !== '' && $target_loc !== 'keep') ? $target_loc : (isset($rec['loc']) && in_array($rec['loc'], $Locations) ? $rec['loc'] : $Locations[0]);
                                alloc_items($rid, $lc, $cp, isset($rec['reg_no']) ? $rec['reg_no'] : '');
                            }
                            $cnt_r++;
                        }
                        if ($has_items) {
                            foreach ($data_bk['items'] as $it) {
                                if (!isset($old2new[$it['rid']])) continue;
                                $lc = ($target_loc !== '' && $target_loc !== 'keep') ? $target_loc : (in_array($it['loc'], $Locations) ? $it['loc'] : $Locations[0]);
                                add_item_exact($old2new[$it['rid']], $lc, $it['reg_no'], isset($it['vol']) ? $it['vol'] : '');
                            }
                        }
                    }
                    if (!empty($data_bk['members']) && is_array($data_bk['members'])) {
                        foreach ($data_bk['members'] as $mem) {
                            if (empty($mem['name'])) continue;
                            $lc = ($target_loc !== '' && $target_loc !== 'keep') ? $target_loc : (isset($mem['loc']) ? $mem['loc'] : $Locations[0]);
                            $db->prepare("INSERT INTO members(name,phone,mcode,loc) VALUES(?,?,?,?)")->execute(array($mem['name'], isset($mem['phone'])?$mem['phone']:'', isset($mem['mcode'])?$mem['mcode']:'', $lc));
                            $cnt_m++;
                        }
                    }
                }
            }
        }
        header("Location: ?p=backup&msg=json_{$cnt_r}_{$cnt_m}");
        exit;
    }
    if ($p === 'loc_action' && can('locs') && !is_loc_restricted()) {
        $act = isset($_POST['act']) ? $_POST['act'] : '';
        $cur_map = get_locations_map();
        if ($act === 'add') {
            $lic = get_license();
            if (!$lic['valid'] || ((int)$lic['max_locs'] > 0 && count($cur_map) >= (int)$lic['max_locs'])) {
                $_SESSION['flash'] = 'سقف مجاز تعداد محل نگهداری در مجوز این مرکز (' . tr_num($lic['max_locs']) . ' مرکز) تکمیل شده است.';
                header('Location: ?p=locs');
                exit;
            }
            $new_l = str_replace('|', '', trim(isset($_POST['new_loc']) ? $_POST['new_loc'] : ''));
            $new_pref = trim(en_num(isset($_POST['new_pref']) ? $_POST['new_pref'] : ''));
            if ($new_l !== '' && !isset($cur_map[$new_l])) {
                if ($new_pref === '' || (int)$new_pref < 10) {
                    $max_p = 9;
                    foreach ($cur_map as $p_val) { if ((int)$p_val > $max_p) $max_p = (int)$p_val; }
                    $new_pref = (string)($max_p + 1);
                }
                $cur_map[$new_l] = $new_pref;
                save_locations_map($cur_map);
            }
        } elseif ($act === 'rename') {
            $old_l = trim(isset($_POST['old_loc']) ? $_POST['old_loc'] : '');
            $new_l = str_replace('|', '', trim(isset($_POST['new_loc']) ? $_POST['new_loc'] : ''));
            $new_pref = trim(en_num(isset($_POST['new_pref']) ? $_POST['new_pref'] : ''));
            if ($old_l !== '' && isset($cur_map[$old_l]) && $new_l !== '') {
                $new_map = array();
                foreach ($cur_map as $k => $v) {
                    if ($k === $old_l) {
                        $new_map[$new_l] = ($new_pref !== '' && (int)$new_pref >= 10) ? $new_pref : $v;
                    } else {
                        $new_map[$k] = $v;
                    }
                }
                save_locations_map($new_map);
                if ($old_l !== $new_l) {
                    $db->prepare("UPDATE items SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE loc_rec SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE members SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE orders SET loc=? WHERE loc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE users SET uloc=? WHERE uloc=?")->execute(array($new_l, $old_l));
                    $db->prepare("UPDATE tickets SET uloc=? WHERE uloc=?")->execute(array($new_l, $old_l));
                }
            }
        } elseif ($act === 'delete') {
            $del_l = trim(isset($_POST['del_loc']) ? $_POST['del_loc'] : '');
            $target_l = trim(isset($_POST['target_loc']) ? $_POST['target_loc'] : '');
            if ($del_l !== '' && $target_l !== '' && $del_l !== $target_l && count($cur_map) > 1) {
                unset($cur_map[$del_l]);
                save_locations_map($cur_map);
                move_loc($del_l, $target_l);
                $db->prepare("UPDATE members SET loc=? WHERE loc=?")->execute(array($target_l, $del_l));
                $db->prepare("UPDATE orders SET loc=? WHERE loc=?")->execute(array($target_l, $del_l));
                $db->prepare("UPDATE users SET uloc=? WHERE uloc=?")->execute(array($target_l, $del_l));
            }
        }
        header('Location: ?p=locs&msg=ok');
        exit;
    }
    if ($p === 'order_status' && can('orders')) {
        $oid = (int)$_POST['id'];
        $st = in_array($_POST['status'], array('bought', 'rejected', 'pending')) ? $_POST['status'] : 'pending';
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("UPDATE orders SET status=? WHERE id=? $w_loc")->execute(array($st, $oid));
        header('Location: ?p=orders&msg=ok');
        exit;
    }
    if ($p === 'del_order' && can('orders')) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM orders WHERE id=? $w_loc")->execute(array((int)$_POST['id']));
        header('Location: ?p=orders');
        exit;
    }
    if ($p === 'send_ticket' && can('tickets')) {
        $target = in_array($_POST['target'], array('admin', 'admin1')) ? $_POST['target'] : 'admin';
        $subj = trim(isset($_POST['subj']) ? $_POST['subj'] : '');
        $msg = trim(isset($_POST['msg']) ? $_POST['msg'] : '');
        $ul = is_loc_restricted() ? user_loc() : 'مدیریت مرکزی';
        if ($subj !== '' && $msg !== '') {
            $db->prepare("INSERT INTO tickets(sender,uloc,target,subj,msg,status) VALUES(?,?,?,?,?,'open')")->execute(array($_SESSION['u'], $ul, $target, $subj, $msg));
        }
        header('Location: ?p=tickets&msg=sent');
        exit;
    }
    if ($p === 'reply_ticket' && can('tickets') && is_super_admin()) {
        $tid = (int)$_POST['id'];
        $rep = trim(isset($_POST['reply']) ? $_POST['reply'] : '');
        $st = isset($_POST['status']) ? $_POST['status'] : 'answered';
        $db->prepare("UPDATE tickets SET reply=?, status=? WHERE id=?")->execute(array($rep, $st, $tid));
        header('Location: ?p=tickets&msg=replied');
        exit;
    }
    if ($p === 'approve_mem' && can('mem')) {
        $id = (int)($_POST['id'] ?? 0);
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("UPDATE members SET status='active' WHERE id=? $w_loc")->execute(array($id));
        $_SESSION['flash'] = 'عضویت کاربر تایید شد و اکنون می‌تواند وارد پنل کاربری خود شود.';
        header('Location: ?p=mem');
        exit;
    }
    if ($p === 'savemem' && can('mem')) {
        $id       = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $name     = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $father   = trim(isset($_POST['father']) ? $_POST['father'] : '');
        $phone    = trim(en_num(isset($_POST['phone']) ? $_POST['phone'] : ''));
        $mcode    = trim(en_num(isset($_POST['mcode']) ? $_POST['mcode'] : ''));
        $u        = trim(en_num(!empty($_POST['u']) ? $_POST['u'] : $mcode));
        $pw       = isset($_POST['pw']) ? $_POST['pw'] : '';
        $bdate    = trim(isset($_POST['bdate']) ? $_POST['bdate'] : '');
        $exp_date = trim(isset($_POST['exp_date']) ? $_POST['exp_date'] : '');
        $status   = (isset($_POST['status']) && $_POST['status'] === 'pending') ? 'pending' : 'active';
        $loc      = is_loc_restricted() ? user_loc() : trim(isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);

        $photo = isset($_POST['old_photo']) ? $_POST['old_photo'] : '';
        if (!empty($_FILES['photo']['name'])) {
            $up_ph = secure_upload_file($_FILES['photo'], array('jpg','jpeg','png','webp'), array('image/jpeg','image/png','image/webp'), 'mem_');
            if ($up_ph !== '') $photo = $up_ph;
        }

        if ($id) {
            $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
            if ($pw !== '') {
                $db->prepare("UPDATE members SET name=?,father=?,phone=?,mcode=?,u=?,pw=?,bdate=?,exp_date=?,loc=?,status=?,photo=? WHERE id=? $w_loc")
                   ->execute(array($name, $father, $phone, $mcode, $u, password_hash($pw, PASSWORD_DEFAULT), $bdate, $exp_date, $loc, $status, $photo, $id));
            } else {
                $db->prepare("UPDATE members SET name=?,father=?,phone=?,mcode=?,u=?,bdate=?,exp_date=?,loc=?,status=?,photo=? WHERE id=? $w_loc")
                   ->execute(array($name, $father, $phone, $mcode, $u, $bdate, $exp_date, $loc, $status, $photo, $id));
            }
            $_SESSION['flash'] = 'اطلاعات عضو و تنظیمات کاربری با موفقیت ذخیره شد.';
        } else {
            if (!lic_check_member_quota(1)) {
                $_SESSION['flash'] = $GLOBALS['lic_err'];
                header('Location: ?p=mem');
                exit;
            }
            $init_pw = $pw !== '' ? $pw : ($mcode !== '' ? $mcode : '123456');
            $db->prepare("INSERT INTO members(name,father,phone,mcode,u,pw,bdate,exp_date,loc,status,photo) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
               ->execute(array($name, $father, $phone, $mcode, $u, password_hash($init_pw, PASSWORD_DEFAULT), $bdate, $exp_date, $loc, $status, $photo));
            $_SESSION['flash'] = 'عضو جدید ثبت شد.';
        }
        header('Location: ?p=mem');
        exit;
    }
    if ($p === 'delmem' && can('mem')) {
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $db->prepare("DELETE FROM members WHERE id=? $w_loc")->execute(array((int)$_POST['id']));
        header('Location: ?p=mem');
        exit;
    }
    if ($p === 'out' && (can('rec') || can('loan'))) {
        $rid = (int)$_POST['rid'];
        $mid = (int)$_POST['mid'];
        $days = max(1, (int)(isset($_POST['days']) ? $_POST['days'] : 14));
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        $c = $db->prepare("SELECT (SELECT COUNT(*) FROM items WHERE id=? $w_loc) - (SELECT COUNT(*) FROM loans WHERE rid=? AND ret IS NULL)");
        $c->execute(array($rid, $rid));
        if ((int)$c->fetchColumn() > 0) {
            $db->prepare("INSERT INTO loans(rid,mid,d_out,due) VALUES(?,?,?,?)")->execute(array($rid, $mid, date('Y-m-d'), date('Y-m-d', strtotime("+$days day"))));
        }
        $ret_url = safe_ret();
        header("Location: $ret_url");
        exit;
    }
    if ($p === 'back' && (can('rec') || can('loan'))) {
        $db->prepare("UPDATE loans SET ret=? WHERE id=?")->execute(array(date('Y-m-d'), (int)$_POST['id']));
        $ret_url = safe_ret();
        header("Location: $ret_url");
        exit;
    }
    if ($p === 'editloan' && (can('rec') || can('loan'))) {
        $id = (int)$_POST['id'];
        $days = (int)$_POST['add_days'];
        if ($days != 0) {
            $op = $days > 0 ? "+$days day" : "$days day";
            $db->prepare("UPDATE loans SET due = to_char((due::date + (? || ' day')::interval), 'YYYY-MM-DD') WHERE id=?")->execute(array($days, $id));
        }
        $ret_url = safe_ret();
        header("Location: $ret_url");
        exit;
    }
        if ($p === 'res_admin_act' && (can('rec') || can('loan'))) {
        $res_id = (int)($_POST['res_id'] ?? 0);
        $act = $_POST['act'] ?? '';
        $w_loc = is_loc_restricted() ? " AND loc=" . $db->quote(user_loc()) : "";
        if ($act === 'cancel') {
            $db->prepare("DELETE FROM reservations WHERE id=? $w_loc")->execute(array($res_id));
            $_SESSION['flash'] = 'درخواست رزرو حذف گردید.';
        } elseif ($act === 'approve_loan') {
            $st_r = $db->prepare("SELECT * FROM reservations WHERE id=? $w_loc");
            $st_r->execute(array($res_id));
            $rr = $st_r->fetch(PDO::FETCH_ASSOC);
            if ($rr) {
                $st_av = $db->prepare("SELECT i.id FROM items i WHERE i.rid=? AND i.loc=? AND NOT EXISTS (SELECT 1 FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) ORDER BY i.id ASC LIMIT 1");
                $st_av->execute(array($rr['rid'], $rr['loc']));
                $avail_item_id = (int)$st_av->fetchColumn();
                $days = max(1, (int)($_POST['days'] ?? 14));
                if ($avail_item_id > 0) {
                    $db->prepare("INSERT INTO loans(rid,mid,d_out,due) VALUES(?,?,?,?)")
                       ->execute(array($avail_item_id, $rr['mid'], date('Y-m-d'), date('Y-m-d', strtotime("+$days day"))));
                    $db->prepare("DELETE FROM reservations WHERE id=?")->execute(array($res_id));
                    $_SESSION['flash'] = 'امانت منبع برای عضو ثبت شد و از فهرست رزرو خارج گردید.';
                } else {
                    $_SESSION['flash'] = 'در حال حاضر نسخه آزادی از این کتاب در «' . $rr['loc'] . '» روی قفسه موجود نیست.';
                }
            }
        }
        $from_p = (isset($_POST['from_p']) && $_POST['from_p'] === 'rec') ? 'rec' : 'loan';
        header('Location: ?p=' . $from_p);
        exit;
    }
    if ($p === 'save_page' && can('cms')) {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim(en_num($_POST['slug'] ?? ''));
        $slug = preg_replace('/[^a-zA-Z0-9_\-\x{0600}-\x{06FF}]/u', '-', $slug);
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        if ($slug === '') $slug = 'page-' . time();
        $body = clean_html($_POST['body'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($title !== '') {
            if ($id > 0) {
                $db->prepare("UPDATE pages SET slug=?, title=?, body=?, active=? WHERE id=?")
                   ->execute(array($slug, $title, $body, $active, $id));
            } else {
                $db->prepare("INSERT INTO pages(slug,title,body,active) VALUES(?,?,?,?) ON CONFLICT (slug) DO UPDATE SET title=EXCLUDED.title, body=EXCLUDED.body, active=EXCLUDED.active")
                   ->execute(array($slug, $title, $body, $active));
            }
            $_SESSION['flash'] = 'صفحه تکی با لینک یکتا «?p=page&slug=' . $slug . '» ذخیره شد.';
        }
        header('Location: ?p=cms&box=pages&msg=saved');
        exit;
    }
    if ($p === 'del_page' && can('cms')) {
        $db->prepare("DELETE FROM pages WHERE id=?")->execute(array((int)($_POST['id'] ?? 0)));
        header('Location: ?p=cms&box=pages');
        exit;
    }
    if ($p === 'save_menu_item' && can('cms')) {
        $id = (int)($_POST['id'] ?? 0);
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        $pos = (isset($_POST['pos']) && $_POST['pos'] === 'header') ? 'header' : 'footer';
        $title = trim($_POST['title'] ?? '');
        $target_type = in_array($_POST['target_type'] ?? '', array('url','page','box')) ? $_POST['target_type'] : 'url';
        $target_val = '';
        if ($target_type === 'page') {
            $target_val = trim($_POST['target_page'] ?? '');
        } elseif ($target_type === 'box') {
            $target_val = trim($_POST['target_box'] ?? '');
        } else {
            $target_val = trim($_POST['target_url'] ?? '');
        }
        $ord = (int)($_POST['ord'] ?? 10);
        $active = isset($_POST['active']) ? 1 : 0;
        if ($title !== '') {
            if ($id > 0) {
                if ($parent_id === $id) $parent_id = 0;
                $db->prepare("UPDATE portal_menus SET parent_id=?, pos=?, title=?, target_type=?, target_val=?, ord=?, active=? WHERE id=?")
                   ->execute(array($parent_id, $pos, $title, $target_type, $target_val, $ord, $active, $id));
            } else {
                $db->prepare("INSERT INTO portal_menus(parent_id,pos,title,target_type,target_val,ord,active) VALUES(?,?,?,?,?,?,?)")
                   ->execute(array($parent_id, $pos, $title, $target_type, $target_val, $ord, $active));
            }
        }
        header('Location: ?p=cms&box=menus&msg=saved');
        exit;
    }
    if ($p === 'del_menu_item' && can('cms')) {
        $mid_del = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM portal_menus WHERE id=? OR parent_id=?")->execute(array($mid_del, $mid_del));
        header('Location: ?p=cms&box=menus');
        exit;
    }
    if ($p === 'savecms' && can('cms')) {
        $lic = get_license();
        $theme_locked = !empty($lic['lock_theme']);
                for ($si = 1; $si <= 3; $si++) {
            $fk_s = 'slider_file' . $si;
            if (!empty($_FILES[$fk_s]['name'])) {
                $up_sl = secure_upload_file($_FILES[$fk_s], array('png','jpg','jpeg','webp','gif'), array('image/jpeg','image/png','image/gif','image/webp'), 'slide' . $si . '_');
                if ($up_sl !== '') $_POST['slider_img' . $si] = $up_sl;
            }
        }
        if (isset($_POST['active_box']) && $_POST['active_box'] === 'slider') {
            $_POST['slider_active'] = isset($_POST['slider_active']) ? '1' : '0';
        }
        if (isset($_POST['stats_tabs_arr']) && is_array($_POST['stats_tabs_arr'])) {
            $_POST['stats_tabs_cfg'] = implode(',', array_map('trim', $_POST['stats_tabs_arr']));
        }
        if (!empty($_FILES['logo_file']['name'])) {
            $up_lg = secure_upload_file($_FILES['logo_file'], array('png','jpg','jpeg','webp','gif'), array('image/jpeg','image/png','image/gif','image/webp'), 'logo_');
            if ($up_lg !== '') $_POST['header_logo'] = $up_lg;
        }
        $stmt = $db->prepare("INSERT INTO cms(k,v) VALUES(?,?) ON CONFLICT (k) DO UPDATE SET v=EXCLUDED.v");
        foreach (array_keys($cms_defaults) as $k) {
            if ($theme_locked && ($k === 'theme' || $k === 'font')) continue;
            if (isset($_POST[$k])) { $vv = trim($_POST[$k]); if ($k === 'about' || $k === 'contact') $vv = clean_html($vv); $stmt->execute(array($k, $vv)); }
        }
        if (!$theme_locked && isset($_POST['cc_navy'])) {
            $cc = array();
            foreach (array('navy', 'navy2', 'blue', 'sky', 'ice', 'line') as $ck) $cc[$ck] = (isset($_POST['cc_' . $ck]) && preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cc_' . $ck])) ? strtolower($_POST['cc_' . $ck]) : '#000000';
            set_cms('theme_custom', json_encode($cc));
        }
        $box = isset($_POST['active_box']) ? $_POST['active_box'] : 'general';
        header("Location: ?p=cms&box=$box&msg=saved");
        exit;
    }
    if ($p === 'save_box_layout' && can('cms')) {
        if (isset($_POST['b_id']) && is_array($_POST['b_id'])) {
            $st_up = $db->prepare("UPDATE portal_boxes SET title=?, pos=?, ord=?, active=? WHERE id=?");
            foreach ($_POST['b_id'] as $idx => $bid) {
                $b_title = trim($_POST['b_title'][$idx]);
                $b_pos = ($_POST['b_pos'][$idx] === 'side') ? 'side' : 'main';
                $b_ord = (int)$_POST['b_ord'][$idx];
                $b_act = isset($_POST['b_active'][$bid]) ? 1 : 0;
                $st_up->execute(array($b_title, $b_pos, $b_ord, $b_act, (int)$bid));
            }
        }
        header('Location: ?p=cms&box=layout&msg=saved');
        exit;
    }
    if ($p === 'add_custom_box' && can('cms')) {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $pos = (isset($_POST['pos']) && $_POST['pos'] === 'side') ? 'side' : 'main';
        $ord = (int)(isset($_POST['ord']) ? $_POST['ord'] : 10);
        $content = clean_html(isset($_POST['content']) ? $_POST['content'] : '');
        $edit_id = (int)(isset($_POST['edit_id']) ? $_POST['edit_id'] : 0);
        if ($title !== '') {
            if ($edit_id) {
                $db->prepare("UPDATE portal_boxes SET title=?, pos=?, ord=?, content=? WHERE id=? AND btype='custom'")->execute(array($title, $pos, $ord, $content, $edit_id));
            } else {
                $bkey = 'custom_' . time() . '_' . rand(10,99);
                $db->prepare("INSERT INTO portal_boxes(bkey,title,btype,content,pos,ord,active) VALUES(?,?,'custom',?,?,?,1)")->execute(array($bkey, $title, $content, $pos, $ord));
            }
        }
        if (isset($_POST['back']) && $_POST['back'] === 'boxes' && $edit_id) header('Location: ?p=cms&box=boxes&sel_b=cust_' . $edit_id . '&msg=saved'); else header('Location: ?p=cms&box=layout&msg=saved');
        exit;
    }
    if ($p === 'del_custom_box' && can('cms')) {
        $db->prepare("DELETE FROM portal_boxes WHERE id=? AND btype='custom'")->execute(array((int)$_POST['id']));
        header('Location: ?p=cms&box=layout');
        exit;
    }
    if ($p === 'add_gal' && can('cms')) {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        if (!empty($_FILES['img']['name'])) {
            $up_gl = secure_upload_file($_FILES['img'], array('jpg','jpeg','png','gif','webp'), array('image/jpeg','image/png','image/gif','image/webp'), 'gal_');
            if ($up_gl !== '') {
                $db->prepare("INSERT INTO gallery(title,img) VALUES(?,?)")->execute(array($title, $up_gl));
            }
        }
        header('Location: ?p=cms&box=gallery&msg=saved');
        exit;
    }
    if ($p === 'del_gal' && can('cms')) {
        $db->prepare("DELETE FROM gallery WHERE id=?")->execute(array((int)$_POST['id']));
        header('Location: ?p=cms&box=gallery');
        exit;
    }
    if ($p === 'imp_xl_rec' && can('imp_exp')) {
        $sel_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) && in_array($_POST['loc'], $Locations) ? trim($_POST['loc']) : $Locations[0]);
        $ws_key  = isset($_POST['ws_key']) && $_POST['ws_key'] !== '' ? trim($_POST['ws_key']) : 'book';
        $mode    = isset($_POST['mode']) ? $_POST['mode'] : 'preview';

        if (!empty($_FILES['f']['tmp_name'])) {
            $parsed_xl = parse_excel_to_preview_records($_FILES['f']['tmp_name'], $sel_loc, $ws_key);
            if ($mode === 'preview') {
                $_SESSION['iso_preview']  = $parsed_xl;
                $_SESSION['iso_loc']      = $sel_loc;
                $_SESSION['iso_ws']       = $ws_key;
                $_SESSION['iso_src_type'] = 'فایل اکسل (' . ($_FILES['f']['name'] ?? 'Excel') . ')';
                $_SESSION['iso_from']     = 'imp_exp';
                header("Location: ?p=imp_exp&show_preview=1#preview_box_container");
                exit;
            } else {
                $n_new = 0; $n_att = 0; $n_vol = 0;
                $db->beginTransaction();
                foreach ($parsed_xl as $d) {
                    $cp = isset($d['copies']) ? (int)$d['copies'] : 1;
                    $first = isset($d['reg_no']) ? $d['reg_no'] : '';
                    $res = import_title($d, $sel_loc, $cp, $first);
                    if ($res[0]) {
                        if ($res[1]) $n_new++; else $n_att++;
                        $n_vol += $res[2];
                    }
                }
                $db->commit();
                $_SESSION['flash'] = 'ورود مستقیم از اکسل در کتابخانه «' . $sel_loc . '» انجام شد: ' . tr_num($n_new) . ' رکورد جدید، ' . tr_num($n_att) . ' عنوان تکراری، مجموع ' . tr_num($n_vol) . ' مجلد ثبت گردید.';
            }
        }
        header("Location: ?p=imp_exp");
        exit;
    }
    if ($p === 'save_lib_ws' && can('locs')) {
        $target_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) && in_array($_POST['loc'], $Locations) ? trim($_POST['loc']) : $Locations[0]);
        $all_cfg = get_lib_worksheets_all();
        $cur_cfg = get_lib_config($target_loc);
        $act_ws = isset($_POST['ws_act']) ? $_POST['ws_act'] : 'save_fields';

        if ($act_ws === 'save_std') {
            $cur_cfg['cat_std']   = (isset($_POST['cat_std']) && $_POST['cat_std'] === 'marc21') ? 'marc21' : 'iranmarc';
            $cur_cfg['class_sys'] = in_array($_POST['class_sys'] ?? '', array('dewey', 'congress', 'both')) ? $_POST['class_sys'] : 'both';
            $all_cfg[$target_loc] = $cur_cfg;
            save_lib_worksheets_all($all_cfg);
            $_SESSION['flash'] = 'استاندارد فهرست‌نویسی و روش رده‌بندی کتابخانه «' . $target_loc . '» ذخیره شد.';
            header('Location: ?p=locs&tab=worksheets&wloc=' . urlencode($target_loc));
            exit;
        } elseif ($act_ws === 'add_ws') {
            $ws_title = trim($_POST['ws_title'] ?? '');
            $ws_rtype = isset($RTypes[$_POST['ws_rtype'] ?? '']) ? $_POST['ws_rtype'] : 'book';
            $ws_key   = preg_replace('/[^a-z0-9_]/i', '', strtolower(en_num($_POST['ws_key'] ?? '')));
            if ($ws_key === '') $ws_key = 'ws_' . time();
            if ($ws_title !== '') {
                $cur_cfg['worksheets'][$ws_key] = array(
                    'key'    => $ws_key,
                    'title'  => $ws_title,
                    'rtype'  => $ws_rtype,
                    'fields' => get_default_worksheet_fields()
                );
                $all_cfg[$target_loc] = $cur_cfg;
                save_lib_worksheets_all($all_cfg);
                $_SESSION['flash'] = 'کاربرگه جدید «' . $ws_title . '» برای کتابخانه «' . $target_loc . '» ایجاد شد. اکنون می‌توانید فیلدها و کدهای آن را سفارشی کنید.';
            }
            header('Location: ?p=locs&tab=worksheets&wloc=' . urlencode($target_loc) . '&ws=' . urlencode($ws_key));
            exit;
        } elseif ($act_ws === 'del_ws') {
            $ws_key = trim($_POST['ws_key'] ?? '');
            if ($ws_key !== '' && isset($cur_cfg['worksheets'][$ws_key]) && count($cur_cfg['worksheets']) > 1) {
                unset($cur_cfg['worksheets'][$ws_key]);
                $all_cfg[$target_loc] = $cur_cfg;
                save_lib_worksheets_all($all_cfg);
                $_SESSION['flash'] = 'کاربرگه مورد نظر حذف شد.';
            }
            header('Location: ?p=locs&tab=worksheets&wloc=' . urlencode($target_loc));
            exit;
        } elseif ($act_ws === 'save_fields') {
            $ws_key = trim($_POST['ws_key'] ?? 'book');
            if (isset($cur_cfg['worksheets'][$ws_key])) {
                if (!empty($_POST['ws_title'])) $cur_cfg['worksheets'][$ws_key]['title'] = trim($_POST['ws_title']);
                if (isset($_POST['ws_rtype']) && isset($RTypes[$_POST['ws_rtype']])) $cur_cfg['worksheets'][$ws_key]['rtype'] = $_POST['ws_rtype'];
                $new_fields = array();
                foreach ($F as $fk => $flbl) {
                    $code_v  = trim($_POST['f_code'][$fk] ?? '');
                    $label_v = trim($_POST['f_label'][$fk] ?? '');
                    if ($label_v === '') $label_v = trim(preg_replace('/\s*\([^)]+\)/', '', $flbl));
                    $act_v   = !empty($_POST['f_active'][$fk]) || $fk === 'f200a' ? 1 : 0;
                    $req_v   = !empty($_POST['f_req'][$fk]) || $fk === 'f200a' ? 1 : 0;
                    $full_v  = $label_v . ($code_v !== '' ? ' (' . $code_v . ')' : '');
                    $new_fields[$fk] = array(
                        'col'    => $fk,
                        'code'   => $code_v,
                        'label'  => $label_v,
                        'full'   => $full_v,
                        'active' => $act_v,
                        'req'    => $req_v
                    );
                }
                $cur_cfg['worksheets'][$ws_key]['fields'] = $new_fields;
                $all_cfg[$target_loc] = $cur_cfg;
                save_lib_worksheets_all($all_cfg);
                $_SESSION['flash'] = 'تنظیمات فیلدها و کدهای کاربرگه «' . $cur_cfg['worksheets'][$ws_key]['title'] . '» با موفقیت ذخیره شد.';
            }
            header('Location: ?p=locs&tab=worksheets&wloc=' . urlencode($target_loc) . '&ws=' . urlencode($ws_key));
            exit;
        }
    }
    if ($p === 'imp_xl_mem' && can('mem')) {
        $cnt = 0;
        $def_loc = is_loc_restricted() ? user_loc() : (isset($_POST['loc']) ? $_POST['loc'] : $Locations[0]);
        if (!empty($_FILES['f']['tmp_name'])) {
            $rows = parse_excel($_FILES['f']['tmp_name']);
            foreach ($rows as $idx => $r) {
                $c0 = isset($r[0]) ? $r[0] : '';
                if ($idx === 0 && (stripos($c0, 'نام') !== false || stripos($c0, 'name') !== false)) continue;
                if (empty($c0)) continue;
                if (!lic_check_member_quota(1)) break;
                $db->prepare("INSERT INTO members(name,phone,mcode,loc) VALUES(?,?,?,?)")->execute(array($c0, isset($r[1]) ? $r[1] : '', isset($r[2]) ? $r[2] : '', $def_loc));
                $cnt++;
            }
        }
        header("Location: ?p=mem&msg=imp_$cnt");
        exit;
    }
    if ($p === 'saveuser' && can('users')) {
        $edit_uid = (int)(isset($_POST['edit_uid']) ? $_POST['edit_uid'] : 0);
        $u = trim(isset($_POST['u']) ? $_POST['u'] : '');
        $pw = isset($_POST['pw']) ? $_POST['pw'] : '';
        $uloc = isset($_POST['uloc']) ? trim($_POST['uloc']) : 'all';
        if ($u === 'admin' || $u === 'admin1') $uloc = 'all';
        $perms = (isset($_POST['perms']) && is_array($_POST['perms'])) ? implode(',', $_POST['perms']) : '';
        if ($u !== '') {
            if ($edit_uid > 0) {
                if ($pw !== '') {
                    $db->prepare("UPDATE users SET u=?,pw=?,perms=?,uloc=? WHERE id=?")->execute(array($u, password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc, $edit_uid));
                } else {
                    $db->prepare("UPDATE users SET u=?,perms=?,uloc=? WHERE id=?")->execute(array($u, $perms, $uloc, $edit_uid));
                }
            } else {
                $ex = $db->prepare("SELECT id FROM users WHERE u=?");
                $ex->execute(array($u));
                if ($id = $ex->fetchColumn()) {
                    if ($pw !== '') {
                        $db->prepare("UPDATE users SET pw=?,perms=?,uloc=? WHERE id=?")->execute(array(password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc, $id));
                    } else {
                        $db->prepare("UPDATE users SET perms=?,uloc=? WHERE id=?")->execute(array($perms, $uloc, $id));
                    }
                } elseif ($pw !== '') {
                    $db->prepare("INSERT INTO users(u,pw,perms,uloc) VALUES(?,?,?,?)")->execute(array($u, password_hash($pw, PASSWORD_DEFAULT), $perms, $uloc));
                }
            }
        }
        header('Location: ?p=users&msg=saved');
        exit;
    }
    if ($p === 'deluser' && can('users')) {
        $u = isset($_POST['u']) ? $_POST['u'] : '';
        if ($u !== 'admin' && $u !== 'admin1') {
            $db->prepare("DELETE FROM users WHERE u=?")->execute(array($u));
        }
        header('Location: ?p=users');
        exit;
    }
}
