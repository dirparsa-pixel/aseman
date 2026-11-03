<?php
// موتور جستجوی تمام‌متن PostgreSQL (GIN/tsvector)، آمار بازدید، قفل رکورد، مستندات و کنترل گروهی
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ===== نشست، آمار سامانه، مستندات، قفل رکورد، ایندکس جستجو، پشتیبان خودکار =====
$FTS_OK = true;
function ensure_schema2() {
    global $db, $FTS_OK;
    $db->exec("
CREATE TABLE IF NOT EXISTS visits(id SERIAL PRIMARY KEY,ts TEXT,day TEXT,kind TEXT,page TEXT,rid INT DEFAULT 0,loc TEXT,vh TEXT,region TEXT,uname TEXT);
CREATE INDEX IF NOT EXISTS v_day ON visits(day,kind);
CREATE INDEX IF NOT EXISTS v_rid ON visits(rid);
CREATE INDEX IF NOT EXISTS v_ts ON visits(ts);
CREATE TABLE IF NOT EXISTS sessions_log(id SERIAL PRIMARY KEY,sid TEXT,uname TEXT,loc TEXT,region TEXT,vh TEXT,login_ts BIGINT,last_seen BIGINT,ended INTEGER DEFAULT 0,reason TEXT);
CREATE INDEX IF NOT EXISTS sl_sid ON sessions_log(sid);
CREATE INDEX IF NOT EXISTS sl_ls ON sessions_log(last_seen);
CREATE TABLE IF NOT EXISTS conc(hr TEXT PRIMARY KEY,mx INTEGER,sm INTEGER,n INTEGER);
CREATE TABLE IF NOT EXISTS geo_cache(ih TEXT PRIMARY KEY,region TEXT,ts BIGINT);
CREATE TABLE IF NOT EXISTS auth_subject(id SERIAL PRIMARY KEY,heading TEXT UNIQUE,variants TEXT,broader TEXT,related TEXT,note TEXT,norm TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS auth_names(id SERIAL PRIMARY KEY,heading TEXT UNIQUE,ntype TEXT DEFAULT 'person',variants TEXT,years TEXT,field TEXT,note TEXT,norm TEXT,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS as_n ON auth_subject(norm);
CREATE INDEX IF NOT EXISTS an_n ON auth_names(norm);
CREATE TABLE IF NOT EXISTS chat_log(id SERIAL PRIMARY KEY,ts BIGINT,vh TEXT,q TEXT,mode TEXT,nres INT);
CREATE INDEX IF NOT EXISTS cl_ts ON chat_log(ts);
CREATE TABLE IF NOT EXISTS rec_locks(rid INTEGER PRIMARY KEY,uname TEXT,loc TEXT,sid TEXT,ts BIGINT);
CREATE TABLE IF NOT EXISTS bulk_log(id SERIAL PRIMARY KEY,ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,uname TEXT,action TEXT,detail TEXT,n INT);
CREATE TABLE IF NOT EXISTS records_fts(rowid INTEGER PRIMARY KEY,title TEXT,author TEXT,subject TEXT,publisher TEXT,isbn TEXT,other TEXT,tsv tsvector);
CREATE INDEX IF NOT EXISTS idx_records_fts_tsv ON records_fts USING GIN(tsv);
");
    $FTS_OK = true;
}
function fts_norm($s) {
    $s = en_num((string)$s);
    $s = str_replace(array('ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'ؤ', 'ئ', 'آ'), array('ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'و', 'ی', 'ا'), $s);
    $s = preg_replace('/[\x{064B}-\x{065F}\x{0640}\x{0670}]/u', '', $s);
    $s = preg_replace('/[\x{200C}\x{200B}\x{200D}\x{200E}\x{200F}]/u', ' ', $s);
    return mb_strtolower($s, 'UTF-8');
}
function fts_row($r) {
    return array(
        fts_norm($r['f200a'] . ' ' . $r['f200e'] . ' ' . $r['f225']),
        fts_norm($r['f700'] . ' ' . $r['f710'] . ' ' . $r['f200f']),
        fts_norm($r['f606']),
        fts_norm($r['f210c'] . ' ' . $r['f210a']),
        trim(preg_replace('/\D/', '', en_num($r['f010'])) . ' ' . fts_norm($r['f010'])),
        fts_norm($r['f676'] . ' ' . $r['f686'] . ' ' . $r['f300'] . ' ' . $r['f210d'])
    );
}
function fts_sync($rid) {
    global $db, $FTS_OK;
    if (!$FTS_OK) return;
    $st = $db->prepare("SELECT * FROM records WHERE id=?");
    $st->execute(array($rid));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $db->prepare("DELETE FROM records_fts WHERE rowid=?")->execute(array($rid));
    if (!$r) return;
    $fr = fts_row($r);
    $all_txt = implode(' ', $fr);
    $db->prepare("INSERT INTO records_fts(rowid,title,author,subject,publisher,isbn,other,tsv) VALUES(?,?,?,?,?,?,?,to_tsvector('simple', ?))")
       ->execute(array($rid, $fr[0], $fr[1], $fr[2], $fr[3], $fr[4], $fr[5], $all_txt));
}
function fts_rebuild() {
    global $db, $FTS_OK;
    if (!$FTS_OK) return 0;
    $db->beginTransaction();
    $db->exec("DELETE FROM records_fts");
    $ins = $db->prepare("INSERT INTO records_fts(rowid,title,author,subject,publisher,isbn,other,tsv) VALUES(?,?,?,?,?,?,?,to_tsvector('simple', ?))");
    $n = 0;
    foreach ($db->query("SELECT * FROM records") as $r) {
        $fr = fts_row($r);
        $ins->execute(array($r['id'], $fr[0], $fr[1], $fr[2], $fr[3], $fr[4], $fr[5], implode(' ', $fr)));
        $n++;
    }
    $db->commit();
    set_cms('fts_built', date('Y-m-d H:i:s'));
    return $n;
}
function fts_fresh($reset = false) {
    global $db, $FTS_OK;
    static $c = null;
    if ($reset) $c = null;
    if (!$FTS_OK) return false;
    if ($c === null) {
        $a = (int)$db->query("SELECT COUNT(*) FROM records")->fetchColumn();
        $b = (int)$db->query("SELECT COUNT(*) FROM records_fts")->fetchColumn();
        $c = ($a === $b);
    }
    return $c;
}
function fts_expr($q) {
    $n = fts_norm($q);
    $toks = preg_split('/[^\p{L}\p{N}]+/u', $n, -1, PREG_SPLIT_NO_EMPTY);
    $out = array();
    foreach (array_slice($toks, 0, 8) as $t) {
        $clean = preg_replace('/[\'\\\\:&|!()<>]/u', '', $t);
        if ($clean !== '') $out[] = $clean . ':*';
    }
    return implode(' & ', $out);
}
// شرط جستجوی متنی با ایندکس GIN/tsvector در PostgreSQL؛ اگر نتیجه‌ای نداشت null برمی‌گرداند تا LIKE استفاده شود
function title_cond($q, &$par, $alias = 'r') {
    global $db;
    $e = fts_expr($q);
    if ($e === '' || !fts_fresh()) return null;
    try {
        $st = $db->prepare("SELECT 1 FROM records_fts WHERE tsv @@ to_tsquery('simple', ?) LIMIT 1");
        $st->execute(array($e));
        if (!$st->fetchColumn()) return null;
    } catch (Exception $ex) {
        return null;
    }
    $par[':m'] = $e;
    return "$alias.id IN (SELECT rowid FROM records_fts WHERE tsv @@ to_tsquery('simple', :m))";
}
function auto_backup($tag) {
    global $db;
    $dir = ASEMAN_ROOT . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents($dir . '/index.html', '');
    }
    $f = $dir . '/auto_' . date('Ymd_His') . '_' . preg_replace('/\W/', '', $tag) . '.json';
    $data_bk = array(
        'version' => 'pg-2.2',
        'date'    => date('Y-m-d H:i:s'),
        'records' => $db->query("SELECT * FROM records")->fetchAll(PDO::FETCH_ASSOC),
        'items'   => $db->query("SELECT * FROM items")->fetchAll(PDO::FETCH_ASSOC),
        'loc_rec' => $db->query("SELECT * FROM loc_rec")->fetchAll(PDO::FETCH_ASSOC),
        'members' => $db->query("SELECT * FROM members")->fetchAll(PDO::FETCH_ASSOC),
        'loans'   => $db->query("SELECT * FROM loans")->fetchAll(PDO::FETCH_ASSOC)
    );
    @file_put_contents($f, json_encode($data_bk, JSON_UNESCAPED_UNICODE));
    $files = glob($dir . '/auto_*.json');
    sort($files);
    while (count($files) > 15) @unlink(array_shift($files));
    return is_file($f) ? basename($f) : '';
}
function blog($action, $detail, $n) {
    global $db;
    $db->prepare("INSERT INTO bulk_log(uname,action,detail,n) VALUES(?,?,?,?)")->execute(array(isset($_SESSION['u']) ? $_SESSION['u'] : '-', $action, $detail, (int)$n));
}
// ---- قفل ویرایش رکورد ----
function my_sid() { return substr(hash('sha256', session_id()), 0, 20); }
function lock_get($rid) {
    global $db;
    $st = $db->prepare("SELECT * FROM rec_locks WHERE rid=? AND ts>?");
    $st->execute(array((int)$rid, time() - 600));
    return $st->fetch(PDO::FETCH_ASSOC);
}
function lock_blocked($rid) {
    $l = lock_get($rid);
    return ($l && $l['sid'] !== my_sid()) ? $l : null;
}
function lock_acquire($rid, $force = false) {
    global $db;
    $l = lock_blocked($rid);
    if ($l && !$force) return $l;
    $db->prepare("INSERT INTO rec_locks(rid,uname,loc,sid,ts) VALUES(?,?,?,?,?) ON CONFLICT (rid) DO UPDATE SET uname=EXCLUDED.uname,loc=EXCLUDED.loc,sid=EXCLUDED.sid,ts=EXCLUDED.ts")->execute(array((int)$rid, $_SESSION['u'], user_loc(), my_sid(), time()));
    return null;
}
function lock_release($rid) {
    global $db;
    $db->prepare("DELETE FROM rec_locks WHERE rid=? AND sid=?")->execute(array((int)$rid, my_sid()));
}
// ---- نشست و آمار ----
function client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}
function stat_salt() {
    $s = get_cms('stat_salt');
    if ($s === '') { $s = bin2hex(random_bytes(8)); set_cms('stat_salt', $s); }
    return $s;
}
function visitor_hash() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return substr(hash('sha256', client_ip() . '|' . $ua . '|' . stat_salt()), 0, 16);
}
function country_fa($c) {
    $m = array('IR'=>'ایران','IQ'=>'عراق','AF'=>'افغانستان','TR'=>'ترکیه','AE'=>'امارات','DE'=>'آلمان','US'=>'آمریکا','GB'=>'بریتانیا','CA'=>'کانادا','FR'=>'فرانسه','AZ'=>'جمهوری آذربایجان','PK'=>'پاکستان','TJ'=>'تاجیکستان','TM'=>'ترکمنستان','SA'=>'عربستان','SY'=>'سوریه','LB'=>'لبنان','IN'=>'هند','CN'=>'چین','RU'=>'روسیه','SE'=>'سوئد','NL'=>'هلند','AM'=>'ارمنستان','KW'=>'کویت','QA'=>'قطر','OM'=>'عمان','BH'=>'بحرین');
    $c = strtoupper($c);
    return isset($m[$c]) ? $m[$c] : $c;
}
function region_for($ip) {
    global $db;
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return 'شبکه داخلی';
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && $_SERVER['HTTP_CF_IPCOUNTRY'] !== 'XX' && $_SERVER['HTTP_CF_IPCOUNTRY'] !== 'T1') return country_fa($_SERVER['HTTP_CF_IPCOUNTRY']);
    if (get_cms('geo_mode') !== 'ipapi') return 'نامشخص';
    $ih = substr(hash('sha256', $ip . '|' . stat_salt()), 0, 16);
    $st = $db->prepare("SELECT region FROM geo_cache WHERE ih=? AND ts>?");
    $st->execute(array($ih, time() - 30 * 86400));
    $c = $st->fetchColumn();
    if ($c !== false) return $c;
    $reg = 'نامشخص';
    $js = @file_get_contents('http://ip-api.com/json/' . rawurlencode($ip) . '?lang=fa&fields=status,country,regionName', false, stream_context_create(array('http' => array('timeout' => 2))));
    if ($js) {
        $j = json_decode($js, true);
        if (is_array($j) && isset($j['status']) && $j['status'] === 'success') $reg = trim($j['country'] . ' / ' . $j['regionName'], ' /');
    }
    $db->prepare("INSERT INTO geo_cache(ih,region,ts) VALUES(?,?,?) ON CONFLICT (ih) DO UPDATE SET region=EXCLUDED.region, ts=EXCLUDED.ts")->execute(array($ih, $reg, $reg === 'نامشخص' ? time() - 29 * 86400 : time()));
    return $reg;
}
function cur_loc_label() {
    return (isset($_SESSION['u']) && is_loc_restricted()) ? user_loc() : (isset($_SESSION['u']) ? 'مدیریت مرکزی' : 'پرتال');
}
function sess_log_start() {
    global $db;
    $db->prepare("INSERT INTO sessions_log(sid,uname,loc,region,vh,login_ts,last_seen) VALUES(?,?,?,?,?,?,?)")->execute(array(my_sid(), $_SESSION['u'], cur_loc_label(), region_for(client_ip()), visitor_hash(), time(), time()));
}
function sess_end_sid($sid, $reason, $at = null) {
    global $db;
    $ts = ($at !== null) ? (int)$at : time();
    $db->prepare("UPDATE sessions_log SET ended=1, reason=?, last_seen=GREATEST(last_seen, ?) WHERE sid=? AND ended=0")->execute(array($reason, $ts, $sid));
    $db->prepare("DELETE FROM rec_locks WHERE sid=?")->execute(array($sid));
}
function online_now() {
    global $db;
    $st = $db->prepare("SELECT COUNT(*) FROM sessions_log WHERE ended=0 AND last_seen>?");
    $st->execute(array(time() - 300));
    $staff = (int)$st->fetchColumn();
    $st = $db->prepare("SELECT COUNT(DISTINCT vh) FROM visits WHERE kind='portal' AND ts>?");
    $st->execute(array(date('Y-m-d H:i:s', time() - 300)));
    return array($staff, (int)$st->fetchColumn());
}
function track_request() {
    global $db, $p, $in;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (!$in && ($ua === '' || preg_match('/bot|crawl|spider|slurp|curl|wget|python|monitor|headless|preview/i', $ua))) return;
    $now = time();
    if ($in) {
        if (!isset($_SESSION['sl_t']) || $now - $_SESSION['sl_t'] > 20) {
            $_SESSION['sl_t'] = $now;
            $u = $db->prepare("UPDATE sessions_log SET last_seen=? WHERE sid=? AND ended=0");
            $u->execute(array($now, my_sid()));
            if (!$u->rowCount()) {
                $c = $db->prepare("SELECT COUNT(*) FROM sessions_log WHERE sid=?");
                $c->execute(array(my_sid()));
                if (!$c->fetchColumn()) sess_log_start();
            }
        }
    }
    $kind = '';
    $rid = 0;
    if (in_array($p, array('home', 'news_list', 'res'))) { $kind = 'portal'; if ($p === 'res') $rid = (int)(isset($_GET['id']) ? $_GET['id'] : 0); }
    elseif ($p === 'marc' && !empty($_GET['id'])) { $kind = 'rec'; $rid = (int)$_GET['id']; }
    if ($kind !== '') {
        $key = $p . ':' . $rid;
        if (!isset($_SESSION['lv'][$key]) || $now - $_SESSION['lv'][$key] > 30) {
            $_SESSION['lv'][$key] = $now;
            $ip = client_ip();
            $vh = visitor_hash();
            $region = 'نامشخص';
            $st = $db->prepare("SELECT region FROM visits WHERE vh=? AND day=? LIMIT 1");
            $st->execute(array($vh, date('Y-m-d')));
            $r0 = $st->fetchColumn();
            $region = $r0 !== false ? $r0 : region_for($ip);
            $db->prepare("INSERT INTO visits(ts,day,kind,page,rid,loc,vh,region,uname) VALUES(?,?,?,?,?,?,?,?,?)")->execute(array(date('Y-m-d H:i:s'), date('Y-m-d'), $kind, $p, $rid, $in ? cur_loc_label() : 'پرتال', $vh, $region, $in ? $_SESSION['u'] : ''));
        }
    }
    if (!isset($_SESSION['cc_t']) || $now - $_SESSION['cc_t'] > 30) {
        $_SESSION['cc_t'] = $now;
        $o = online_now();
        $cnt = max(1, $o[0] + $o[1]);
        $db->prepare("INSERT INTO conc(hr,mx,sm,n) VALUES(?,?,?,1) ON CONFLICT(hr) DO UPDATE SET mx=GREATEST(conc.mx,EXCLUDED.mx), sm=conc.sm+EXCLUDED.sm, n=conc.n+1")->execute(array(date('Y-m-d H'), $cnt, $cnt));
    }
    if (mt_rand(1, 300) === 1) {
        $db->prepare("DELETE FROM visits WHERE day<?")->execute(array(date('Y-m-d', $now - 400 * 86400)));
        $db->prepare("DELETE FROM sessions_log WHERE last_seen<?")->execute(array($now - 400 * 86400));
        $db->prepare("DELETE FROM conc WHERE hr<?")->execute(array(date('Y-m-d H', $now - 400 * 86400)));
        $db->prepare("DELETE FROM chat_log WHERE ts<?")->execute(array($now - 90 * 86400));
    }
}
function bar_chart($rows, $unit = '') {
    $mx = 1;
    foreach ($rows as $v) if ($v > $mx) $mx = $v;
    $o = '<div class="chart-wrap" style="overflow-x:auto">';
    foreach ($rows as $lbl => $v) {
        $hp = max(4, round(($v / $mx) * 120));
        $o .= '<div class="chart-col"><span style="font-size:10.5px;font-weight:700;color:var(--blue)">' . tr_num($v) . '</span><div class="chart-bar" style="height:' . $hp . 'px" title="' . h($lbl) . ': ' . tr_num($v) . $unit . '"></div><span style="font-size:10px;font-weight:600;white-space:nowrap">' . h($lbl) . '</span></div>';
    }
    return $o . '</div>';
}
function fmt_dur($s) {
    $s = (int)$s;
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    return tr_num(($h ? $h . ' ساعت و ' : '') . $m . ' دقیقه');
}
// ---- مستندات فهرست‌نویسی ----
function auth_save_norm($tbl, $id) {
    global $db;
    $st = $db->prepare("SELECT heading, variants FROM $tbl WHERE id=?");
    $st->execute(array($id));
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) $db->prepare("UPDATE $tbl SET norm=? WHERE id=?")->execute(array(fts_norm($r['heading'] . ' ' . str_replace(array('|', '؛', ';'), ' ', $r['variants'])), $id));
}
function auth_suggest($f, $q) {
    global $db;
    $q = trim($q);
    $subj = ($f === 'f606');
    $prefix = '';
    $seg = $q;
    if ($subj && preg_match('/^(.*[؛;]\s*)([^؛;]*)$/us', $q, $m)) { $prefix = $m[1]; $seg = trim($m[2]); }
    if (mb_strlen($seg, 'UTF-8') < 2) return array();
    $nq = fts_norm($seg);
    $toks = array_slice(preg_split('/[^\p{L}\p{N}]+/u', $nq, -1, PREG_SPLIT_NO_EMPTY), 0, 4);
    if (!$toks) return array();
    $tbl = $subj ? 'auth_subject' : 'auth_names';
    $out = array();
    $seen = array();
    foreach (array('AND', 'OR') as $op) {
        if (count($out) >= 6) break;
        $w = array(); $par = array();
        foreach ($toks as $i => $t) { $w[] = "norm LIKE :t$i"; $par[":t$i"] = "%$t%"; }
        $st = $db->prepare("SELECT * FROM $tbl WHERE " . implode(" $op ", $w) . " LIMIT 60");
        $st->execute($par);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $nh = fts_norm($r['heading']);
            similar_text($nq, $nh, $pct);
            $r['_sc'] = ($nh === $nq ? 200 : (strpos($nh, $nq) === 0 ? 120 : (strpos($nh, $nq) !== false ? 80 : 0))) + $pct;
            $r['_via'] = (strpos($nh, $toks[0]) === false);
        }
        unset($r);
        usort($rows, function ($a, $b) { return $b['_sc'] <=> $a['_sc']; });
        foreach ($rows as $r) {
            if (isset($seen[$r['id']])) continue;
            $seen[$r['id']] = 1;
            $note = $r['_via'] ? 'ارجاع: ' . trim(explode('|', str_replace(array('؛', ';'), '|', $r['variants']))[0]) . ' ← ' . $r['heading'] : '';
            if (!$subj && !empty($r['years'])) $note .= ($note ? ' | ' : '') . $r['years'];
            $out[] = array('label' => $r['heading'], 'value' => $prefix . $r['heading'], 'kind' => $r['_via'] ? 'variant' : 'auth', 'note' => $note);
            if (count($out) >= 8) break;
        }
    }
    $variants = array($seg, str_replace(array('ی', 'ک'), array('ي', 'ك'), $seg));
    $st = $db->prepare("SELECT DISTINCT $f FROM records WHERE $f LIKE ? OR $f LIKE ? LIMIT 12");
    $st->execute(array('%' . $variants[0] . '%', '%' . $variants[1] . '%'));
    $have = array();
    foreach ($out as $o) $have[fts_norm($o['label'])] = 1;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $val) {
        $cands = $subj ? preg_split('/\s*[؛;]\s*/u', $val) : array($val);
        foreach ($cands as $cv) {
            $cv = trim($cv);
            if ($cv === '' || stripos(fts_norm($cv), $toks[0]) === false) continue;
            $k = fts_norm($cv);
            if (isset($have[$k])) continue;
            $have[$k] = 1;
            $out[] = array('label' => $cv, 'value' => $prefix . $cv, 'kind' => 'rec', 'note' => 'در رکوردهای موجود (بدون مستند)');
        }
    }
    return array_slice($out, 0, 10);
}
function merge_titles($keep, $ids) {
    global $db, $F;
    $keep = (int)$keep;
    $ids = array_values(array_diff(array_map('intval', $ids), array($keep)));
    if (!$ids) return 0;
    $ks = $db->prepare("SELECT * FROM records WHERE id=?");
    $ks->execute(array($keep));
    $kr = $ks->fetch(PDO::FETCH_ASSOC);
    if (!$kr) return 0;
    $n = 0;
    foreach ($ids as $did) {
        $ks->execute(array($did));
        $dr = $ks->fetch(PDO::FETCH_ASSOC);
        if (!$dr) continue;
        foreach (array_keys($F) as $c) {
            if (($kr[$c] === null || $kr[$c] === '') && $dr[$c] !== null && $dr[$c] !== '') {
                $db->prepare("UPDATE records SET $c=? WHERE id=?")->execute(array($dr[$c], $keep));
                $kr[$c] = $dr[$c];
            }
        }
        if (empty($kr['file']) && !empty($dr['file'])) { $db->prepare("UPDATE records SET file=? WHERE id=?")->execute(array($dr['file'], $keep)); $kr['file'] = $dr['file']; }
        $db->prepare("UPDATE items SET rid=? WHERE rid=?")->execute(array($keep, $did));
        $db->prepare("DELETE FROM loc_rec WHERE rid=?")->execute(array($did));
        $db->prepare("DELETE FROM records WHERE id=?")->execute(array($did));
        $db->prepare("DELETE FROM rec_locks WHERE rid=?")->execute(array($did));
        fts_sync($did);
        $n++;
    }
    foreach ($db->query("SELECT DISTINCT loc FROM items WHERE rid=" . $keep)->fetchAll(PDO::FETCH_COLUMN) as $lc) ensure_loc_rec($keep, $lc);
    // سریال‌های تکراری ناشی از ادغام: فقط یک ردیف loc_rec به ازای هر (رکورد، محل) وجود دارد
    sync_copies($keep);
    fts_sync($keep);
    return $n;
}
function isbn_valid($s) {
    $d = preg_replace('/[^0-9Xx]/', '', en_num($s));
    if (strlen($d) === 13 && ctype_digit($d)) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int)$d[$i] * ($i % 2 ? 3 : 1);
        return ((10 - $sum % 10) % 10) === (int)$d[12];
    }
    if (strlen($d) === 10) {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) { if (!ctype_digit($d[$i])) return false; $sum += (10 - $i) * (int)$d[$i]; }
        $sum += (strtoupper($d[9]) === 'X') ? 10 : (ctype_digit($d[9]) ? (int)$d[9] : -100);
        return $sum % 11 === 0;
    }
    return false;
}
function year_valid($y) {
    $d = preg_replace('/\D/', '', en_num($y));
    if (strlen($d) < 4) return false;
    $v = (int)substr($d, 0, 4);
    return ($v >= 1200 && $v <= 1500) || ($v >= 1800 && $v <= 2100);
}

function bulk_ids($sel) {
    global $db;
    $w = array(); $par = array();
    if (!empty($sel['ids'])) {
        $ids = array_filter(array_map('intval', preg_split('/[\s,،]+/u', $sel['ids'])));
        if ($ids) $w[] = "r.id IN (" . implode(',', $ids) . ")";
    }
    if (!empty($sel['q'])) {
        $c = title_cond($sel['q'], $par, 'r');
        if ($c === null) { $c = "(r.f200a LIKE :q OR r.f700 LIKE :q OR r.f710 LIKE :q OR r.f010 LIKE :q OR r.f606 LIKE :q OR r.f210c LIKE :q OR r.f676 LIKE :q)"; $par[':q'] = '%' . $sel['q'] . '%'; }
        $w[] = $c;
    }
    if (!empty($sel['type'])) { $w[] = "r.rtype=:rt"; $par[':rt'] = $sel['type']; }
    $iw = array();
    if (!empty($sel['loc'])) { $iw[] = "i.loc=:loc"; $par[':loc'] = $sel['loc']; }
    if (isset($sel['from']) && $sel['from'] !== '') { $iw[] = "COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0)>=:rf"; $par[':rf'] = (int)en_num($sel['from']); }
    if (isset($sel['to']) && $sel['to'] !== '') { $iw[] = "COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0)<=:rt2"; $par[':rt2'] = (int)en_num($sel['to']); }
    if ($iw) $w[] = "EXISTS (SELECT 1 FROM items i WHERE i.rid=r.id AND " . implode(' AND ', $iw) . ")";
    if (!$w) return array();
    $st = $db->prepare("SELECT r.id FROM records r WHERE " . implode(' AND ', $w) . " LIMIT 200000");
    $st->execute($par);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
function parse_bulk_cmd($s) {
    $o = array();
    if (preg_match_all('/([A-Za-z_]+)\s*=\s*("[^"]*"|\x{201C}[^\x{201D}]*\x{201D}|\S+)/u', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $o[strtolower($x[1])] = trim($x[2], "\"\u{201C}\u{201D}");
    }
    return $o;
}
function items_in_loc($rid, $loc, $from = '', $to = '') {
    global $db;
    $st = $db->prepare("SELECT i.*, (SELECT COUNT(*) FROM loans l WHERE l.rid=i.id AND l.ret IS NULL) AS onloan FROM items i WHERE i.rid=? AND i.loc=? ORDER BY COALESCE(NULLIF(regexp_replace(i.reg_no, '[^0-9]', '', 'g'), '')::bigint, 0), i.id");
    $st->execute(array($rid, $loc));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($from !== '' || $to !== '') {
        $rows = array_values(array_filter($rows, function ($x) use ($from, $to) {
            $n = (int)$x['reg_no'];
            return ($from === '' || $n >= (int)$from) && ($to === '' || $n <= (int)$to);
        }));
    }
    return $rows;
}
function renumber_items($rows, $loc, $numbers) {
    global $db;
    // فاز ۱: شماره موقت برای جلوگیری از تعارض یکتایی؛ فاز ۲: شماره نهایی
    $ids = array();
    foreach ($rows as $x) $ids[] = (int)$x['id'];
    $db->exec("UPDATE items SET reg_no='~' || id WHERE id IN (" . implode(',', $ids) . ")");
    $up = $db->prepare("UPDATE items SET reg_no=? WHERE id=?");
    foreach ($rows as $k => $x) $up->execute(array((string)$numbers[$k], $x['id']));
}
function bulk_items_cmd($rid, $p, &$msgs) {
    global $db, $Locations;
    $loc = isset($p['loc']) ? $p['loc'] : '';
    if (!in_array($loc, $Locations)) { $msgs[] = "رکورد $rid: کتابخانه معتبر نیست."; return 0; }
    $from = isset($p['from']) ? trim(en_num($p['from'])) : '';
    $to = isset($p['to']) ? trim(en_num($p['to'])) : '';
    $rows = items_in_loc($rid, $loc, $from, $to);
    $changed = 0;
    $pref = (string)get_loc_prefix($loc);
    if (isset($p['vols']) && $p['vols'] !== '') {
        $want = max(0, min(5000, (int)en_num($p['vols'])));
        $have = count(items_in_loc($rid, $loc));
        if ($want > $have) { $ids = alloc_items($rid, $loc, $want - $have, ''); $changed += count($ids); $msgs[] = "رکورد $rid: " . count($ids) . ' مجلد اضافه شد.'; }
        elseif ($want < $have) {
            $all = array_reverse(items_in_loc($rid, $loc));
            $del = 0;
            foreach ($all as $x) {
                if ($del >= $have - $want) break;
                if ($x['onloan']) continue;
                $db->prepare("DELETE FROM items WHERE id=?")->execute(array($x['id']));
                $del++;
            }
            $changed += $del;
            $msgs[] = "رکورد $rid: $del مجلد حذف شد" . ($del < $have - $want ? ' (بقیه در امانت‌اند)' : '') . '.';
            $left = count(items_in_loc($rid, $loc));
            if ($left === 0) $db->prepare("DELETE FROM loc_rec WHERE rid=? AND loc=?")->execute(array($rid, $loc));
            sync_copies($rid);
        }
        $rows = items_in_loc($rid, $loc, $from, $to);
    }
    $numbers = null;
    if (isset($p['start']) && $p['start'] !== '') {
        $s = trim(en_num($p['start']));
        if (ctype_digit($s)) { $numbers = array(); foreach ($rows as $k => $x) $numbers[] = (string)((int)$s + $k); }
    } elseif (isset($p['shift']) && $p['shift'] !== '' && (int)$p['shift'] !== 0) {
        $numbers = array(); foreach ($rows as $x) $numbers[] = (string)((int)$x['reg_no'] + (int)$p['shift']);
    } elseif (!empty($p['renumber'])) {
        $seq = max_seq($loc) + 1;
        $numbers = array(); foreach ($rows as $k => $x) $numbers[] = $pref . sprintf('%04d', $seq + $k);
    }
    if ($numbers !== null && $rows) {
        $ids = array_map(function ($x) { return (int)$x['id']; }, $rows);
        $chk = $db->prepare("SELECT COUNT(*) FROM items WHERE loc=? AND reg_no=? AND id NOT IN (" . implode(',', $ids) . ")");
        $bad = 0;
        foreach ($numbers as $n) { $chk->execute(array($loc, $n)); if ($chk->fetchColumn()) $bad++; }
        if ($bad || count(array_unique($numbers)) !== count($numbers)) $msgs[] = "رکورد $rid: تغییر شماره ثبت انجام نشد؛ $bad شماره با مجلد دیگر تعارض دارد.";
        else { renumber_items($rows, $loc, $numbers); $changed += count($rows); $msgs[] = "رکورد $rid: " . count($rows) . ' شمارهٔ ثبت تغییر کرد (' . $numbers[0] . ($numbers[count($numbers) - 1] !== $numbers[0] ? ' تا ' . $numbers[count($numbers) - 1] : '') . ').'; }
        $rows = items_in_loc($rid, $loc, '', '');
    }
    if (!empty($p['move']) && in_array($p['move'], $Locations) && $p['move'] !== $loc && $rows) {
        $dest = $p['move'];
        $dpref = (string)get_loc_prefix($dest);
        $seq = max_seq($dest) + 1;
        foreach ($rows as $x) {
            if ($x['onloan']) { $msgs[] = "رکورد $rid: مجلد {$x['reg_no']} در امانت است و منتقل نشد."; continue; }
            $newreg = !empty($p['regen']) ? $dpref . sprintf('%04d', $seq++) : $x['reg_no'];
            $dup = $db->prepare("SELECT 1 FROM items WHERE loc=? AND reg_no=?");
            $dup->execute(array($dest, $newreg));
            if ($dup->fetchColumn()) { $newreg = $dpref . sprintf('%04d', $seq++); }
            $db->prepare("UPDATE items SET loc=?, reg_no=? WHERE id=?")->execute(array($dest, $newreg, $x['id']));
            $changed++;
        }
        ensure_loc_rec($rid, $dest);
        if (!count(items_in_loc($rid, $loc))) $db->prepare("DELETE FROM loc_rec WHERE rid=? AND loc=?")->execute(array($rid, $loc));
        $msgs[] = "رکورد $rid: مجلدها به «$dest» منتقل شد.";
    }
    return $changed;
}
function bulk_run($act, $ids, $P, $dry) {
    global $db, $F, $RTypes;
    $msgs = array();
    $n = 0;
    if ($dry) $db->beginTransaction();
    try {
        if ($act === 'del_titles') {
            $loc = isset($P['loc']) ? $P['loc'] : '';
            foreach ($ids as $rid) {
                $on = $db->prepare("SELECT COUNT(*) FROM loans l JOIN items i ON i.id=l.rid WHERE i.rid=? AND l.ret IS NULL");
                $on->execute(array($rid));
                if ($on->fetchColumn()) { $msgs[] = "رکورد $rid دارای امانت فعال است و نادیده گرفته شد."; continue; }
                if ($loc !== '') $db->prepare("DELETE FROM items WHERE rid=? AND loc=?")->execute(array($rid, $loc));
                else $db->prepare("DELETE FROM items WHERE rid=?")->execute(array($rid));
                $db->prepare("DELETE FROM loc_rec WHERE rid=? AND loc NOT IN (SELECT loc FROM items WHERE rid=?)")->execute(array($rid, $rid));
                $left = $db->prepare("SELECT COUNT(*) FROM items WHERE rid=?");
                $left->execute(array($rid));
                if ((int)$left->fetchColumn() === 0) { $db->prepare("DELETE FROM records WHERE id=?")->execute(array($rid)); fts_sync($rid); } else sync_copies($rid);
                $n++;
            }
            $msgs[] = tr_num($n) . ' عنوان' . ($loc !== '' ? " (فقط موجودی محل «$loc»)" : ' با همهٔ مجلدات') . ' حذف می‌شود.';
        } elseif ($act === 'set_field') {
            $fld = isset($P['field']) ? $P['field'] : '';
            if ($fld !== 'rtype' && !isset($F[$fld])) { $msgs[] = 'فیلد نامعتبر است.'; }
            else {
                $mode = isset($P['mode']) ? $P['mode'] : 'set';
                $val = isset($P['value']) ? $P['value'] : '';
                $find = isset($P['find']) ? $P['find'] : '';
                if ($fld === 'rtype' && !isset($RTypes[$val]) && $mode !== 'clear') { $msgs[] = 'نوع منبع نامعتبر است (book/article/thesis/other).'; }
                else {
                    $sel = $db->prepare("SELECT $fld FROM records WHERE id=?");
                    $up = $db->prepare("UPDATE records SET $fld=? WHERE id=?");
                    foreach ($ids as $rid) {
                        $sel->execute(array($rid));
                        $old = (string)$sel->fetchColumn();
                        if ($mode === 'set') $new = $val; elseif ($mode === 'clear') $new = ''; elseif ($mode === 'append') $new = $old . $val; elseif ($mode === 'prepend') $new = $val . $old;
                        elseif ($mode === 'replace') { if ($find === '') continue; $new = str_replace($find, $val, $old); } else continue;
                        if ($new !== $old) { $up->execute(array($new, $rid)); fts_sync($rid); $n++; }
                    }
                    $msgs[] = tr_num($n) . ' رکورد تغییر می‌کند (فیلد ' . h($fld) . ').';
                }
            }
        } elseif ($act === 'items_cmd') {
            foreach ($ids as $rid) $n += bulk_items_cmd($rid, $P, $msgs) > 0 ? 1 : 0;
            array_unshift($msgs, tr_num($n) . ' عنوان تحت تأثیر قرار می‌گیرد.');
        }
    } catch (Exception $e) {
        $msgs[] = 'خطا: ' . $e->getMessage();
    }
    if ($dry) $db->rollBack();
    return array($n, $msgs);
}

$LocationsMap = get_locations_map();
$Locations = array_keys($LocationsMap);
save_locations_map($LocationsMap);
ensure_schema2();
migrate_v2();
if ($FTS_OK && !fts_fresh()) {
    if ((int)$db->query("SELECT COUNT(*) FROM records")->fetchColumn() <= 20000) { fts_rebuild(); fts_fresh(true); }
}

