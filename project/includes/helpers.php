<?php
// توابع عمومی، تاریخ شمسی، مدیریت کتابخانه‌ها و ویرایشگر پیشرفته متن
if (!defined('ASEMAN_ROOT')) die('Access Denied');

function tr_num($str) {
    return str_replace(array('0','1','2','3','4','5','6','7','8','9'), array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), (string)$str);
}
function en_num($str) {
    return str_replace(array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), array('0','1','2','3','4','5','6','7','8','9'), (string)$str);
}
function jdate($str, $with_time = false) {
    if (!$str) return '-';
    $ts = ($str === 'now') ? time() : strtotime($str);
    if (!$ts) return tr_num($str);
    $parts = explode('-', date('Y-n-j', $ts));
    $gy = (int)$parts[0]; $gm = (int)$parts[1]; $gd = (int)$parts[2];
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    $out = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    if ($with_time) $out .= ' ساعت ' . date('H:i', $ts);
    return tr_num($out);
}

// مدیریت کتابخانه‌ها و پیش‌شماره اختصاصی هر مرکز (شروع از ۱۰، ۱۱، ۱۲ و...)
function get_locations_map() {
    $lines = array_values(array_filter(array_map('trim', explode("\n", get_cms('locations')))));
    $map = array();
    $auto_pref = 10;
    foreach ($lines as $line) {
        $pts = explode('|', $line);
        $name = trim($pts[0]);
        if ($name === '') continue;
        $pref = isset($pts[1]) && trim(en_num($pts[1])) !== '' ? (int)en_num(trim($pts[1])) : $auto_pref;
        if ($pref < 10) $pref = $auto_pref;
        $map[$name] = (string)$pref;
        if ($pref >= $auto_pref) $auto_pref = $pref + 1;
    }
    if (empty($map)) $map['مخزن اصلی'] = '10';
    $lic = get_license();
    $max_l = (int)$lic['max_locs'];
    if ($max_l > 0 && count($map) > $max_l) {
        $map = array_slice($map, 0, $max_l, true);
    }
    return $map;
}
function save_locations_map($map) {
    $lines = array();
    foreach ($map as $name => $pref) {
        $lines[] = trim($name) . '|' . trim(en_num($pref));
    }
    set_cms('locations', implode("\n", $lines));
}
function get_locations() {
    return array_keys(get_locations_map());
}
function get_loc_prefix($loc_name) {
    $map = get_locations_map();
    if (isset($map[$loc_name])) return $map[$loc_name];
    $first = reset($map);
    return $first ? $first : '10';
}

// ===== مدل جدید: رکورد (عنوان) ← کتابخانه ← مجلد با شماره ثبت و سریال رکورد اختصاصی هر محل =====
function max_seq($loc) {
    global $db;
    $pref = (string)get_loc_prefix($loc);
    $plen = strlen($pref);
    $st = $db->prepare("SELECT MAX(COALESCE(NULLIF(regexp_replace(SUBSTR(reg_no, " . ($plen + 1) . "), '[^0-9]', '', 'g'), '')::bigint, 0)) FROM items WHERE loc=? AND reg_no LIKE ? AND LENGTH(reg_no) > " . $plen);
    $st->execute(array($loc, $pref . '%'));
    return (int)$st->fetchColumn();
}
function next_reg_no($loc_name = null) {
    $map = get_locations_map();
    if ($loc_name === null || !isset($map[$loc_name])) {
        $k = array_keys($map);
        $loc_name = $k[0];
    }
    return (string)$map[$loc_name] . sprintf('%04d', max_seq($loc_name) + 1);
}
function ensure_loc_rec($rid, $loc) {
    global $db;
    $db->prepare("INSERT INTO loc_rec(rid,loc,rec_no) SELECT ?,?,COALESCE(MAX(rec_no),0)+1 FROM loc_rec WHERE loc=? ON CONFLICT (rid,loc) DO NOTHING")->execute(array($rid, $loc, $loc));
}
function sync_copies($rid) {
    global $db;
    $db->prepare("UPDATE records SET copies=(SELECT COUNT(*) FROM items WHERE rid=?) WHERE id=?")->execute(array($rid, $rid));
}
function alloc_items($rid, $loc, $n, $first = '') {
    global $db;
    if (!$rid) return array();
    $n = max(0, min(5000, (int)$n));
    $n = lic_avail_items_quota($n);
    if ($n <= 0) return array();
    $pref = (string)get_loc_prefix($loc);
    $plen = strlen($pref);
    $first = trim(en_num($first));
    $seq = max_seq($loc) + 1;
    if ($first !== '' && ctype_digit($first) && strpos($first, $pref) === 0 && strlen($first) > $plen) $seq = (int)substr($first, $plen);
    $hv = $db->prepare("SELECT COUNT(*) FROM items WHERE rid=? AND loc=?");
    $hv->execute(array($rid, $loc));
    $have = (int)$hv->fetchColumn();
    $chk = $db->prepare("SELECT 1 FROM items WHERE loc=? AND reg_no=?");
    $ins = $db->prepare("INSERT INTO items(rid,loc,reg_no,vol) VALUES(?,?,?,?) RETURNING id");
    $ids = array();
    for ($k = 0; $k < $n; $k++) {
        do {
            $reg = $pref . sprintf('%04d', $seq);
            $seq++;
            $chk->execute(array($loc, $reg));
            $dup = $chk->fetchColumn();
        } while ($dup);
        $ins->execute(array($rid, $loc, $reg, (string)($have + $k + 1)));
        $ids[] = (int)$ins->fetchColumn();
    }
    if ($ids) {
        ensure_loc_rec($rid, $loc);
        sync_copies($rid);
    }
    return $ids;
}
function add_item_exact($rid, $loc, $reg, $vol = '') {
    global $db;
    $reg = trim(en_num($reg));
    $chk = $db->prepare("SELECT 1 FROM items WHERE loc=? AND reg_no=?");
    $chk->execute(array($loc, $reg));
    if ($reg === '' || $chk->fetchColumn()) return alloc_items($rid, $loc, 1, '');
    $st = $db->prepare("INSERT INTO items(rid,loc,reg_no,vol) VALUES(?,?,?,?) RETURNING id");
    $st->execute(array($rid, $loc, $reg, (string)$vol));
    $id = (int)$st->fetchColumn();
    ensure_loc_rec($rid, $loc);
    sync_copies($rid);
    return array($id);
}
function norm_key($s) {
    $s = en_num((string)$s);
    $s = str_replace(array('ي', 'ك', 'ة'), array('ی', 'ک', 'ه'), $s);
    $s = preg_replace('/[\x{200C}\x{200B}\x{200E}\x{200F}\x{064B}-\x{065F}\s\p{P}]+/u', '', $s);
    return mb_strtolower($s, 'UTF-8');
}
function find_title($d) {
    global $db;
    $isbn = preg_replace('/\D/', '', en_num(isset($d['f010']) ? $d['f010'] : ''));
    if (strlen($isbn) >= 10) {
        $st = $db->prepare("SELECT id FROM records WHERE REPLACE(REPLACE(f010,'-',''),' ','') IN (?,?) LIMIT 1");
        $st->execute(array($isbn, tr_num($isbn)));
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
    }
    $t = isset($d['f200a']) ? trim($d['f200a']) : '';
    if ($t === '') return 0;
    $kt = norm_key($t);
    $ka = norm_key(!empty($d['f700']) ? $d['f700'] : (isset($d['f200f']) ? $d['f200f'] : ''));
    $ky = preg_replace('/\D/', '', en_num(isset($d['f210d']) ? $d['f210d'] : ''));
    $st = $db->prepare("SELECT id,f200a,f700,f200f,f210d FROM records WHERE f200a LIKE ? LIMIT 60");
    $st->execute(array('%' . mb_substr($t, 0, 10, 'UTF-8') . '%'));
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (norm_key($r['f200a']) !== $kt) continue;
        $ra = norm_key($r['f700'] ? $r['f700'] : $r['f200f']);
        if ($ka !== '' && $ra !== '' && $ka !== $ra) continue;
        $ry = preg_replace('/\D/', '', en_num($r['f210d']));
        if ($ky !== '' && $ry !== '' && $ky !== $ry) continue;
        return (int)$r['id'];
    }
    return 0;
}
function insert_title($d) {
    global $db, $F;
    if (!lic_check_record_quota(1)) return 0;
    $cols = array_keys($F);
    $vals = array();
    foreach ($cols as $c) $vals[] = isset($d[$c]) ? trim($d[$c]) : ($c === 'f101' ? 'per' : '');
    $rt = isset($d['rtype']) && $d['rtype'] !== '' ? $d['rtype'] : 'book';
    $file = isset($d['file']) ? $d['file'] : '';
    $qs = implode(',', array_fill(0, count($cols) + 3, '?'));
    $st = $db->prepare("INSERT INTO records(" . implode(',', $cols) . ",rtype,file,copies) VALUES($qs) RETURNING id");
    $st->execute(array_merge($vals, array($rt, $file, 0)));
    $nid = (int)$st->fetchColumn();
    fts_sync($nid);
    return $nid;
}
// ثبت عنوان (در صورت نبودن) و افزودن مجلدات به کتابخانه؛ خروجی: [شناسه رکورد، عنوان جدید بود؟، تعداد مجلد افزوده‌شده]
function import_title($d, $loc, $n = 1, $first = '') {
    $rid = find_title($d);
    $isnew = false;
    if (!$rid) {
        $rid = insert_title($d);
        if (!$rid) return array(0, false, 0);
        $isnew = true;
    }
    $ids = alloc_items($rid, $loc, max(1, (int)$n), $first);
    return array($rid, $isnew, count($ids));
}
function can_edit_title($id) {
    global $db;
    if (!is_loc_restricted()) return true;
    $st = $db->prepare("SELECT 1 FROM items WHERE rid=? AND loc=? LIMIT 1");
    $st->execute(array((int)$id, user_loc()));
    return (bool)$st->fetchColumn();
}
function move_loc($from, $to) {
    global $db;
    $rids = $db->prepare("SELECT DISTINCT rid FROM items WHERE loc=?");
    $rids->execute(array($from));
    $db->prepare("UPDATE items SET loc=? WHERE loc=?")->execute(array($to, $from));
    foreach ($rids->fetchAll(PDO::FETCH_COLUMN) as $rid) ensure_loc_rec($rid, $to);
    $db->prepare("DELETE FROM loc_rec WHERE loc=?")->execute(array($from));
}
function safe_ret() {
    $r = isset($_POST['ret_url']) ? $_POST['ret_url'] : '';
    return preg_match('/^\?p=[a-z_]+[\w&%=.+\-]*$/', $r) ? $r : '?p=loan';
}
function norm_perms($a) {
    $a = array_map('trim', $a);
    if (in_array('nlai', $a) && !in_array('imp_exp', $a)) $a[] = 'imp_exp';
    return $a;
}
function clean_style($s) {
    $ok = array('color','background-color','background','font-size','font-family','font-weight','font-style','text-decoration','text-align','direction','line-height','margin','margin-top','margin-bottom','margin-left','margin-right','padding','width','height','max-width','border','border-collapse','float','display','vertical-align','list-style-type','unicode-bidi');
    $out = array();
    foreach (explode(';', (string)$s) as $decl) {
        $pp = explode(':', $decl, 2);
        if (count($pp) < 2) continue;
        $k = strtolower(trim($pp[0]));
        $v = trim($pp[1]);
        if (!in_array($k, $ok)) continue;
        if (preg_match('/url\s*\(|expression|javascript|@import|behavior|<|>|\\\\/i', $v)) continue;
        if (!preg_match('/^[\p{L}\p{N}\s#%.,()\-"\'!]+$/u', $v)) continue;
        $out[] = $k . ':' . $v;
    }
    return implode(';', $out);
}
function clean_html($html) {
    $html = (string)$html;
    if (trim($html) === '') return '';
    $ok_tags = array('p','br','b','strong','i','em','u','s','strike','del','sub','sup','span','div','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','pre','code','hr','a','img','table','thead','tbody','tfoot','tr','td','th','caption','figure','figcaption','font','small','mark');
    $drop = array('script','style','iframe','object','embed','link','meta','form','input','button','textarea','select','svg','math','base','noscript','template');
    $ok_attr = array('href','src','alt','title','dir','align','colspan','rowspan','target','width','height','color','face','size','style','class','lang');
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) return '';
    $walk = function ($node) use (&$walk, $ok_tags, $drop, $ok_attr) {
        $kids = array();
        foreach ($node->childNodes as $c) $kids[] = $c;
        foreach ($kids as $c) {
            if ($c->nodeType === XML_COMMENT_NODE) { $node->removeChild($c); continue; }
            if ($c->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($c->nodeName);
            if (in_array($tag, $drop)) { $node->removeChild($c); continue; }
            if (!in_array($tag, $ok_tags)) {
                $walk($c);
                while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
                $node->removeChild($c);
                continue;
            }
            $names = array();
            foreach ($c->attributes as $a) $names[] = $a->nodeName;
            foreach ($names as $an) {
                $lan = strtolower($an);
                $val = $c->getAttribute($an);
                if (!in_array($lan, $ok_attr)) { $c->removeAttribute($an); continue; }
                if ($lan === 'href' || $lan === 'src') {
                    $v = preg_replace('/[\x00-\x20]+/', '', $val);
                    $has_scheme = preg_match('#^[a-z][a-z0-9+.\-]*:#i', $v);
                    $okv = !$has_scheme || preg_match('#^(https?|mailto|tel):#i', $v) || ($lan === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $v));
                    if (!$okv) $c->removeAttribute($an);
                } elseif ($lan === 'style') {
                    $c->setAttribute('style', clean_style($val));
                } elseif ($lan === 'target') {
                    $c->setAttribute('target', '_blank');
                    $c->setAttribute('rel', 'noopener');
                }
            }
            $walk($c);
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $dom->saveHTML($c);
    return trim($out);
}
function migrate_v2() {
    global $db;
    $db->exec("CREATE OR REPLACE VIEW v_items AS SELECT i.id AS id, i.rid AS rid, i.reg_no AS reg_no, i.loc AS loc, i.vol AS vol, 1 AS copies, i.created AS icreated, r.rtype AS rtype, r.file AS file, r.f010 AS f010, r.f101 AS f101, r.f200a AS f200a, r.f200e AS f200e, r.f200f AS f200f, r.f205 AS f205, r.f210a AS f210a, r.f210c AS f210c, r.f210d AS f210d, r.f215 AS f215, r.f225 AS f225, r.f300 AS f300, r.f606 AS f606, r.f676 AS f676, r.f686 AS f686, r.f700 AS f700, r.f710 AS f710, r.created AS created FROM items i JOIN records r ON r.id=i.rid");
    if (get_cms('schema_v2') !== '1') {
        $db->beginTransaction();
        $map = get_locations_map();
        $keys = array_keys($map);
        $firstloc = $keys[0];
        $recs = $db->query("SELECT * FROM records ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $key2main = array();
        $dups = array();
        $extras = array();
        $extra_plan = array();
        $ins = $db->prepare("INSERT INTO items(id,rid,loc,reg_no,vol,created) VALUES(?,?,?,?,?,?) ON CONFLICT (id) DO NOTHING");
        foreach ($recs as $r) {
            $loc = (!empty($r['loc']) && isset($map[$r['loc']])) ? $r['loc'] : $firstloc;
            $isbn = preg_replace('/\D/', '', en_num($r['f010']));
            $key = strlen($isbn) >= 10 ? 'i' . $isbn : 't' . norm_key($r['f200a']) . '|' . norm_key($r['f700'] ? $r['f700'] : $r['f200f']) . '|' . preg_replace('/\D/', '', en_num($r['f210d']));
            if (!isset($key2main[$key])) $key2main[$key] = (int)$r['id'];
            $main = $key2main[$key];
            if ($main !== (int)$r['id']) $dups[] = (int)$r['id'];
            $reg = trim(en_num($r['reg_no']));
            $chk = $db->prepare("SELECT 1 FROM items WHERE loc=? AND reg_no=?");
            $chk->execute(array($loc, $reg));
            if ($reg === '' || $chk->fetchColumn()) $reg = next_reg_no($loc);
            $ins->execute(array($r['id'], $main, $loc, $reg, '1', $r['created']));
            $copies = max(1, (int)$r['copies']);
            if ($copies > 1) $extra_plan[] = array((int)$r['id'], $main, $loc, $copies, $r['created']);
        }
        $db->exec("SELECT setval(pg_get_serial_sequence('items', 'id'), COALESCE((SELECT MAX(id) FROM items), 1), true)");
        $ins_ex = $db->prepare("INSERT INTO items(rid,loc,reg_no,vol,created) VALUES(?,?,?,?,?) RETURNING id");
        foreach ($extra_plan as $ep) {
            $new_ids = array();
            for ($k = 2; $k <= $ep[3]; $k++) {
                $nr = next_reg_no($ep[2]);
                $ins_ex->execute(array($ep[1], $ep[2], $nr, (string)$k, $ep[4]));
                $new_ids[] = (int)$ins_ex->fetchColumn();
            }
            $extras[$ep[0]] = $new_ids;
        }
        foreach ($extras as $old_id => $ids) {
            $ls = $db->prepare("SELECT id FROM loans WHERE rid=? AND ret IS NULL ORDER BY id");
            $ls->execute(array($old_id));
            $active = $ls->fetchAll(PDO::FETCH_COLUMN);
            array_shift($active);
            foreach ($active as $i => $lid) {
                if (isset($ids[$i])) $db->prepare("UPDATE loans SET rid=? WHERE id=?")->execute(array($ids[$i], $lid));
            }
        }
        foreach ($dups as $did) $db->prepare("DELETE FROM records WHERE id=?")->execute(array($did));
        foreach ($db->query("SELECT rid, loc FROM items GROUP BY rid, loc ORDER BY MIN(id)")->fetchAll(PDO::FETCH_ASSOC) as $g) ensure_loc_rec($g['rid'], $g['loc']);
        $db->exec("UPDATE records SET copies=(SELECT COUNT(*) FROM items WHERE rid=records.id)");
        set_cms('schema_v2', '1');
        $db->commit();
    }
    if (get_cms('news_v2') !== '1') {
        $lines = array_values(array_filter(array_map('trim', explode("\n", get_cms('news')))));
        $ins = $db->prepare("INSERT INTO news(title,subtitle,body,img,pub,active) VALUES(?,?,?,?,?,1)");
        foreach (array_reverse($lines) as $line) {
            $pts = explode('|', $line);
            $body = isset($pts[2]) ? trim($pts[2]) : '';
            $ins->execute(array(trim($pts[0]), '', $body !== '' ? '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>' : '', '', isset($pts[1]) ? trim($pts[1]) : ''));
        }
        set_cms('news_v2', '1');
    }
}
function rich_editor($id, $name, $html, $minh = 280) {
    static $first = true;
    $o = '';
    if ($first) {
        $first = false;
        $o .= '<style>
.rt-wrap{border:1px solid var(--line);border-radius:14px;background:#fff;overflow:hidden;margin-top:6px}
.rt-tb{display:flex;flex-wrap:wrap;gap:4px;padding:8px;background:var(--sky);border-bottom:1px solid var(--line);align-items:center}
.rt-tb button{width:auto;padding:5px 9px;font:inherit;font-size:13px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--navy);cursor:pointer;line-height:1.3}
.rt-tb button:hover{background:var(--ice);border-color:var(--blue)}
.rt-tb select{width:auto;padding:4px 6px;font-size:12.5px;border-radius:8px}
.rt-tb input[type=color]{width:34px;height:30px;padding:1px;border-radius:8px;cursor:pointer}
.rt-tb .sep{width:1px;height:22px;background:var(--line);margin:0 3px}
.rt-area{min-height:280px;padding:14px;outline:none;line-height:2;direction:rtl;text-align:right;overflow:auto;font-size:14px}
.rt-area img{max-width:100%;height:auto}.rt-area table{border-collapse:collapse}.rt-area td,.rt-area th{border:1px solid #94a3b8;padding:6px 10px}
.rt-src{display:none;width:100%;min-height:280px;direction:ltr;text-align:left;border:0;border-radius:0;font-family:inherit;font-size:13px}
.rt-stat{padding:5px 12px;font-size:12px;background:var(--ice);border-top:1px solid var(--line);color:var(--navy2)}
.rt-fs{position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;border-radius:0;margin:0}.rt-fs .rt-area,.rt-fs .rt-src{flex:1;max-height:none}
</style><script>
(function(){
function ed(w){return w.querySelector(".rt-area");}
function sync(w){var s=w.querySelector(".rt-src"),a=ed(w),h=w.querySelector("textarea.rt-out");if(s.style.display==="block"){a.innerHTML=s.value;}else{s.value=a.innerHTML;}h.value=a.innerHTML;var t=(a.textContent||"").trim();w.querySelector(".rt-count").textContent=(t?t.split(/\s+/).length:0)+" کلمه | "+t.length+" نویسه";}
function cmd(w,c,v){ed(w).focus();document.execCommand(c,false,v||null);sync(w);}
function blockOf(a){var n=window.getSelection().anchorNode;while(n&&n!==a){if(n.nodeType===1&&/^(P|DIV|LI|H[1-6]|BLOCKQUOTE|PRE|TD|TH)$/.test(n.nodeName))return n;n=n.parentNode;}return null;}
document.addEventListener("click",function(e){var b=e.target.closest("[data-rt]");if(!b)return;var w=b.closest(".rt-wrap"),a=ed(w),k=b.getAttribute("data-rt");e.preventDefault();
if(k==="link"){var u=prompt("آدرس پیوند (https://...)","https://");if(u)cmd(w,"createLink",u);var l=a.querySelectorAll("a[href]");l.forEach(function(x){x.target="_blank";});sync(w);}
else if(k==="imgurl"){var u2=prompt("آدرس تصویر","https://");if(u2)cmd(w,"insertImage",u2);}
else if(k==="imgfile"){w.querySelector(".rt-file").click();}
else if(k==="table"){var r=parseInt(prompt("تعداد سطر","3"),10),c=parseInt(prompt("تعداد ستون","3"),10);if(r>0&&c>0){var t="<table style=\"border-collapse:collapse;width:100%\">";for(var i=0;i<r;i++){t+="<tr>";for(var j=0;j<c;j++)t+="<td style=\"border:1px solid #94a3b8;padding:6px\">&nbsp;</td>";t+="</tr>";}cmd(w,"insertHTML",t+"</table><p><br></p>");}}
else if(k==="rtl"||k==="ltr"){a.focus();var bl=blockOf(a);if(bl){bl.setAttribute("dir",k);bl.style.textAlign=k==="rtl"?"right":"left";}else{cmd(w,"formatBlock","p");bl=blockOf(a);if(bl){bl.setAttribute("dir",k);bl.style.textAlign=k==="rtl"?"right":"left";}}sync(w);}
else if(k==="src"){var s=w.querySelector(".rt-src");if(s.style.display==="block"){a.innerHTML=s.value;s.style.display="none";a.style.display="block";}else{s.value=a.innerHTML;s.style.display="block";a.style.display="none";}sync(w);}
else if(k==="fs"){w.classList.toggle("rt-fs");}
else if(k==="hr"){cmd(w,"insertHorizontalRule");}
else{cmd(w,k);}});
document.addEventListener("change",function(e){var s=e.target,w=s.closest&&s.closest(".rt-wrap");if(!w)return;var a=ed(w),k=s.getAttribute("data-rts");
if(k==="block"){cmd(w,"formatBlock",s.value);s.selectedIndex=0;}
else if(k==="font"){cmd(w,"fontName",s.value);s.selectedIndex=0;}
else if(k==="size"){if(!s.value)return;a.focus();document.execCommand("fontSize",false,"7");a.querySelectorAll("font[size=\"7\"]").forEach(function(f){var sp=document.createElement("span");sp.style.fontSize=s.value+"px";sp.innerHTML=f.innerHTML;f.parentNode.replaceChild(sp,f);});s.selectedIndex=0;sync(w);}
else if(k==="emoji"){if(s.value)cmd(w,"insertText",s.value);s.selectedIndex=0;}
else if(k==="fore"){cmd(w,"foreColor",s.value);}
else if(k==="back"){a.focus();if(!document.execCommand("hiliteColor",false,s.value))document.execCommand("backColor",false,s.value);sync(w);}
else if(s.classList.contains("rt-file")&&s.files[0]){var fd=new FormData();fd.append("f",s.files[0]);fd.append("t",w.getAttribute("data-t"));fetch("?p=upload_img",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){if(j.url){a.focus();document.execCommand("insertImage",false,j.url);sync(w);}else{alert(j.error||"بارگذاری ناموفق بود");}}).catch(function(){alert("خطا در بارگذاری تصویر");});s.value="";}});
document.addEventListener("input",function(e){var w=e.target.closest&&e.target.closest(".rt-wrap");if(w)sync(w);});
document.addEventListener("paste",function(e){var a=e.target.closest&&e.target.closest(".rt-area");if(!a)return;var h=(e.clipboardData||window.clipboardData).getData("text/html")||"";if(/mso-|class="?Mso|<o:p>/i.test(h)){e.preventDefault();var t=(e.clipboardData||window.clipboardData).getData("text/plain");document.execCommand("insertText",false,t);}});
document.addEventListener("submit",function(e){e.target.querySelectorAll&&e.target.querySelectorAll(".rt-wrap").forEach(sync);},true);
document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".rt-wrap").forEach(sync);});
})();
</script>';
    }
    $btn = function ($c, $label, $title) { return '<button type="button" data-rt="' . $c . '" title="' . $title . '">' . $label . '</button>'; };
    $o .= '<div class="rt-wrap" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" data-t="' . htmlspecialchars($GLOBALS['tok'], ENT_QUOTES, 'UTF-8') . '"><div class="rt-tb">'
        . '<select data-rts="block" title="قالب پاراگراف"><option value="">قالب…</option><option value="p">پاراگراف</option><option value="h1">تیتر ۱</option><option value="h2">تیتر ۲</option><option value="h3">تیتر ۳</option><option value="h4">تیتر ۴</option><option value="h5">تیتر ۵</option><option value="h6">تیتر ۶</option><option value="blockquote">نقل‌قول</option><option value="pre">پیش‌قالب</option></select>'
        . '<select data-rts="font" title="فونت"><option value="">فونت…</option><option>Vazirmatn</option><option>Tahoma</option><option>Arial</option><option>Times New Roman</option><option>Courier New</option></select>'
        . '<select data-rts="size" title="اندازه"><option value="">اندازه…</option><option>11</option><option>13</option><option>15</option><option>18</option><option>22</option><option>28</option><option>36</option><option>48</option></select><span class="sep"></span>'
        . $btn('bold', '<b>B</b>', 'ضخیم') . $btn('italic', '<i>I</i>', 'کج') . $btn('underline', '<u>U</u>', 'زیرخط') . $btn('strikeThrough', '<s>S</s>', 'خط‌خورده') . $btn('subscript', 'x₂', 'زیرنویس') . $btn('superscript', 'x²', 'بالانویس') . '<span class="sep"></span>'
        . '<label style="margin:0;font-size:11px">رنگ</label><input type="color" data-rts="fore" value="#0b1f4d" title="رنگ متن"><label style="margin:0;font-size:11px">پس‌زمینه</label><input type="color" data-rts="back" value="#fff59d" title="رنگ پس‌زمینه متن"><span class="sep"></span>'
        . $btn('justifyRight', '⇥ راست', 'راست‌چین') . $btn('justifyCenter', '⇔ وسط', 'وسط‌چین') . $btn('justifyLeft', '⇤ چپ', 'چپ‌چین') . $btn('justifyFull', 'تراز', 'دوطرفه') . $btn('rtl', 'RTL', 'جهت راست به چپ') . $btn('ltr', 'LTR', 'جهت چپ به راست') . '<span class="sep"></span>'
        . $btn('insertUnorderedList', '• فهرست', 'فهرست نقطه‌ای') . $btn('insertOrderedList', '۱. فهرست', 'فهرست شماره‌دار') . $btn('outdent', '⇠', 'کاهش تورفتگی') . $btn('indent', '⇢', 'افزایش تورفتگی') . '<span class="sep"></span>'
        . $btn('link', 'پیوند', 'درج پیوند') . $btn('unlink', 'حذف پیوند', 'حذف پیوند') . $btn('imgfile', 'تصویر', 'بارگذاری تصویر از دستگاه') . $btn('imgurl', 'تصویر از آدرس', 'درج تصویر با آدرس') . $btn('table', '▦ جدول', 'درج جدول') . $btn('hr', '― خط', 'خط افقی') . '<span class="sep"></span>'
        . '<select data-rts="emoji" title="نمادها"><option value="">نماد…</option><option></option><option></option><option></option><option></option><option></option><option>«</option><option>»</option><option>…</option><option>©</option><option>®</option><option>™</option><option>°</option><option>±</option><option>←</option><option>→</option><option></option><option></option><option></option><option></option><option></option><option></option></select><span class="sep"></span>'
        . $btn('undo', '↶', 'واگرد') . $btn('redo', '↷', 'ازنو') . $btn('removeFormat', 'پاک‌سازی', 'حذف قالب‌بندی') . $btn('selectAll', 'همه', 'انتخاب همه') . $btn('src', '&lt;/&gt; کد', 'ویرایش HTML') . $btn('fs', '⤢ تمام‌صفحه', 'تمام‌صفحه')
        . '<input type="file" class="rt-file" accept="image/*" style="display:none"></div>'
        . '<div class="rt-area" contenteditable="true" style="min-height:' . (int)$minh . 'px">' . ($html !== '' ? $html : '<p><br></p>') . '</div>'
        . '<textarea class="rt-src" spellcheck="false"></textarea><textarea class="rt-out" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" style="display:none"></textarea>'
        . '<div class="rt-stat"><span class="rt-count"></span></div></div>';
    return $o;
}

function xterm256() {
    $c = array('#000000','#800000','#008000','#808000','#000080','#800080','#008080','#c0c0c0','#808080','#ff0000','#00ff00','#ffff00','#0000ff','#ff00ff','#00ffff','#ffffff');
    $lv = array(0, 95, 135, 175, 215, 255);
    for ($r = 0; $r < 6; $r++) for ($g = 0; $g < 6; $g++) for ($b = 0; $b < 6; $b++) $c[] = sprintf('#%02x%02x%02x', $lv[$r], $lv[$g], $lv[$b]);
    for ($i = 0; $i < 24; $i++) { $v = 8 + 10 * $i; $c[] = sprintf('#%02x%02x%02x', $v, $v, $v); }
    return $c;
}
function color_studio() {
    global $Themes;
    $cur = get_cms('theme');
    $base = isset($Themes[$cur]) ? $Themes[$cur] : $Themes['royal_dark'];
    $cc = json_decode(get_cms('theme_custom'), true);
    if (!is_array($cc)) $cc = array();
    $roles = array('navy' => 'رنگ اصلی تیره (سربرگ و متن)', 'navy2' => 'رنگ دوم تیره', 'blue' => 'رنگ برجسته (دکمه‌ها)', 'sky' => 'رنگ روشن (نشان‌ها)', 'ice' => 'پس‌زمینه بسیار روشن', 'line' => 'رنگ خطوط و حاشیه');
    $o = '<div style="margin-top:18px;padding:14px;border:2px dashed var(--line);border-radius:16px"><h3 style="margin:0 0 8px">طیف دلخواه: ترکیب از پالت ۲۵۶ رنگ</h3>
    <label style="display:flex;gap:8px;align-items:center;font-weight:700"><input type="radio" name="theme" value="custom"' . ($cur === 'custom' ? ' checked' : '') . '> استفاده از طیف دلخواه (به‌جای ۲۰ طیف بالا)</label>
    <p style="font-size:12.5px;margin:8px 0">یک نقش را انتخاب کنید و روی رنگ مورد نظر در پالت ۲۵۶ رنگی کلیک کنید؛ برای ترکیب، هر نقش را جداگانه رنگ کنید. دکمهٔ «طیف روشن خودکار» سه رنگ روشن را از رنگ برجسته می‌سازد.</p><div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">';
    foreach ($roles as $k => $lbl) {
        $val = isset($cc[$k]) ? $cc[$k] : $base[$k];
        $o .= '<label style="display:flex;gap:6px;align-items:center;border:1px solid var(--line);border-radius:12px;padding:6px 10px;cursor:pointer;font-weight:600"><input type="radio" name="cc_role" value="' . $k . '"' . ($k === 'navy' ? ' checked' : '') . '> ' . $lbl . '<input type="color" name="cc_' . $k . '" id="cc_' . $k . '" value="' . h($val) . '" style="width:34px;height:28px;padding:1px"></label>';
    }
    $o .= '</div><div id="cc_grid" style="display:grid;grid-template-columns:repeat(16,1fr);gap:2px;max-width:560px;direction:ltr">';
    foreach (xterm256() as $i => $hx) $o .= '<button type="button" data-c="' . $hx . '" title="' . ($i) . ' ' . $hx . '" style="aspect-ratio:1;border:1px solid rgba(0,0,0,.15);border-radius:3px;padding:0;background:' . $hx . ';cursor:pointer"></button>';
    $o .= '</div><div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button type="button" class="btn-sm" id="cc_auto">طیف روشن خودکار از رنگ برجسته</button><span class="badge" id="cc_warn"></span></div>
    <div id="cc_prev" style="margin-top:10px;border-radius:14px;overflow:hidden;border:1px solid var(--line)"><div id="cc_p1" style="padding:10px 14px;color:#fff;font-weight:700">پیش‌نمایش سربرگ</div><div id="cc_p2" style="padding:12px 14px"><span id="cc_p3" style="display:inline-block;padding:6px 14px;border-radius:10px;color:#fff;font-weight:700">دکمه</span> <span id="cc_p4" style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px">نشان</span> <span id="cc_p5">متن نمونه</span></div></div>
    <script>(function(){var g=document.getElementById("cc_grid");function role(){return document.querySelector("input[name=cc_role]:checked").value;}
    function lum(h){var r=parseInt(h.substr(1,2),16)/255,g2=parseInt(h.substr(3,2),16)/255,b=parseInt(h.substr(5,2),16)/255;function f(c){return c<=0.03928?c/12.92:Math.pow((c+0.055)/1.055,2.4);}return 0.2126*f(r)+0.7152*f(g2)+0.0722*f(b);}
    function mix(h,t){var c=[1,3,5].map(function(i){return Math.round(parseInt(h.substr(i,2),16)*(1-t)+255*t);});return "#"+c.map(function(x){return ("0"+x.toString(16)).slice(-2);}).join("");}
    function v(k){return document.getElementById("cc_"+k).value;}
    function prev(){document.getElementById("cc_p1").style.background="linear-gradient(135deg,"+v("navy")+","+v("navy2")+")";var p2=document.getElementById("cc_p2");p2.style.background=v("ice");p2.style.borderTop="3px solid "+v("line");document.getElementById("cc_p3").style.background=v("blue");var p4=document.getElementById("cc_p4");p4.style.background=v("sky");p4.style.color=v("navy2");document.getElementById("cc_p5").style.color=v("navy");
    var cr=(lum("#ffffff")+0.05)/(lum(v("navy"))+0.05);document.getElementById("cc_warn").textContent=cr<4.5?"کنتراست رنگ تیره با متن سفید کم است ("+cr.toFixed(1)+")":"کنتراست مناسب ("+cr.toFixed(1)+")";}
    g.addEventListener("click",function(e){var b=e.target.closest("button[data-c]");if(!b)return;document.getElementById("cc_"+role()).value=b.getAttribute("data-c");document.querySelector("input[name=theme][value=custom]").checked=true;prev();});
    ["navy","navy2","blue","sky","ice","line"].forEach(function(k){document.getElementById("cc_"+k).addEventListener("input",function(){document.querySelector("input[name=theme][value=custom]").checked=true;prev();});});
    document.getElementById("cc_auto").addEventListener("click",function(){var b=v("blue");document.getElementById("cc_sky").value=mix(b,0.82);document.getElementById("cc_ice").value=mix(b,0.95);document.getElementById("cc_line").value=mix(b,0.7);prev();});prev();})();</script></div>';
    return $o;
}
// ---- کنترل گروهی ----


function secure_upload_file($file_arr, $allowed_exts, $allowed_mimes, $prefix = 'file_') {
    if (empty($file_arr['name']) || !isset($file_arr['error']) || $file_arr['error'] !== UPLOAD_ERR_OK) return '';
    $lic = get_license();
    $max_bytes = max(1, (int)($lic['max_upload_mb'] ?? 20)) * 1048576;
    if (isset($file_arr['size']) && $file_arr['size'] > $max_bytes) return '';

    $ext = strtolower(pathinfo($file_arr['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true) || in_array($ext, array('svg','php','phtml','php3','php4','php5','php7','php8','phar','exe','sh','bat','cmd','cgi','pl','py','html','htm'), true)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $real_mime = @finfo_file($finfo, $file_arr['tmp_name']);
            @finfo_close($finfo);
            if ($real_mime && !empty($allowed_mimes) && !in_array($real_mime, $allowed_mimes, true)) {
                return '';
            }
        }
    }

    if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
        if (!@getimagesize($file_arr['tmp_name'])) return '';
    }

    $new_name = $prefix . bin2hex(random_bytes(10)) . '.' . $ext;
    $dest = ASEMAN_ROOT . '/uploads/' . $new_name;
    if (move_uploaded_file($file_arr['tmp_name'], $dest)) {
        @chmod($dest, 0644);
        return 'uploads/' . $new_name;
    }
    return '';
}


// ===== مدیریت استاندارد فهرست‌نویسی، رده‌بندی و کاربرگه‌های پویا برای هر کتابخانه =====
function get_lib_worksheets_all() {
    global $F;
    $raw = get_cms('lib_worksheets_cfg');
    $data = $raw ? json_decode($raw, true) : array();
    if (!is_array($data)) $data = array();
    return $data;
}

function save_lib_worksheets_all($data) {
    set_cms('lib_worksheets_cfg', json_encode($data, JSON_UNESCAPED_UNICODE));
}

function get_default_worksheet_fields() {
    global $F;
    $fields = array();
    foreach ($F as $col => $lbl) {
        $tag_code = $col;
        if (preg_match('/\(([^)]+)\)/', $lbl, $m)) {
            $tag_code = trim($m[1]);
        }
        $clean_lbl = trim(preg_replace('/\s*\([^)]+\)/', '', $lbl));
        $fields[$col] = array(
            'col'    => $col,
            'code'   => $tag_code,
            'label'  => $clean_lbl,
            'full'   => $lbl,
            'active' => 1,
            'req'    => ($col === 'f200a' ? 1 : 0)
        );
    }
    return $fields;
}

function get_lib_config($loc_name) {
    $all = get_lib_worksheets_all();
    $def_fields = get_default_worksheet_fields();
    if (!isset($all[$loc_name]) || !is_array($all[$loc_name])) {
        return array(
            'cat_std'    => 'iranmarc', // iranmarc | marc21
            'class_sys'  => 'both',     // dewey | congress | both
            'worksheets' => array(
                'book' => array(
                    'key'    => 'book',
                    'title'  => 'کاربرگه عمومی کتاب',
                    'rtype'  => 'book',
                    'fields' => $def_fields
                ),
                'map' => array(
                    'key'    => 'map',
                    'title'  => 'کاربرگه نقشه و اطلس',
                    'rtype'  => 'other',
                    'fields' => array(
                        'f010'  => array('col'=>'f010',  'code'=>'010 $a', 'label'=>'شماره استاندارد / شابک نقشه', 'full'=>'شماره استاندارد / شابک نقشه (010 $a)', 'active'=>1, 'req'=>0),
                        'f101'  => array('col'=>'f101',  'code'=>'101 $a', 'label'=>'زبان نقشه', 'full'=>'زبان نقشه (101 $a)', 'active'=>1, 'req'=>0),
                        'f200a' => array('col'=>'f200a', 'code'=>'200 $a', 'label'=>'عنوان اصلی نقشه', 'full'=>'عنوان اصلی نقشه (200 $a)', 'active'=>1, 'req'=>1),
                        'f200e' => array('col'=>'f200e', 'code'=>'200 $e', 'label'=>'عنوان فرعی / منطقه جغرافیایی', 'full'=>'عنوان فرعی / منطقه جغرافیایی (200 $e)', 'active'=>1, 'req'=>0),
                        'f200f' => array('col'=>'f200f', 'code'=>'200 $f', 'label'=>'تهیه‌کننده / کارتوگراف', 'full'=>'تهیه‌کننده / کارتوگراف (200 $f)', 'active'=>1, 'req'=>0),
                        'f205'  => array('col'=>'f205',  'code'=>'206 $a', 'label'=>'مقیاس و سیستم تصویر نقشه', 'full'=>'مقیاس و سیستم تصویر نقشه (206 $a)', 'active'=>1, 'req'=>0),
                        'f210a' => array('col'=>'f210a', 'code'=>'210 $a', 'label'=>'محل نشر / ترسیم', 'full'=>'محل نشر / ترسیم (210 $a)', 'active'=>1, 'req'=>0),
                        'f210c' => array('col'=>'f210c', 'code'=>'210 $c', 'label'=>'سازمان ناشر / جغرافیایی', 'full'=>'سازمان ناشر / جغرافیایی (210 $c)', 'active'=>1, 'req'=>0),
                        'f210d' => array('col'=>'f210d', 'code'=>'210 $d', 'label'=>'سال نشر / ترسیم', 'full'=>'سال نشر / ترسیم (210 $d)', 'active'=>1, 'req'=>0),
                        'f215'  => array('col'=>'f215',  'code'=>'215 $a', 'label'=>'ابعاد، رنگ و مشخصات ظاهری', 'full'=>'ابعاد، رنگ و مشخصات ظاهری (215 $a)', 'active'=>1, 'req'=>0),
                        'f225'  => array('col'=>'f225',  'code'=>'225 $a', 'label'=>'مجموعه نقشه / سری', 'full'=>'مجموعه نقشه / سری (225 $a)', 'active'=>1, 'req'=>0),
                        'f300'  => array('col'=>'f300',  'code'=>'300 $a', 'label'=>'یادداشت فنی و راهنما', 'full'=>'یادداشت فنی و راهنما (300 $a)', 'active'=>1, 'req'=>0),
                        'f606'  => array('col'=>'f606',  'code'=>'606 $a', 'label'=>'موضوع و منطقه جغرافیایی', 'full'=>'موضوع و منطقه جغرافیایی (606 $a)', 'active'=>1, 'req'=>0),
                        'f676'  => array('col'=>'f676',  'code'=>'676 $a', 'label'=>'شماره رده دیویی', 'full'=>'شماره رده دیویی (676 $a)', 'active'=>1, 'req'=>0),
                        'f686'  => array('col'=>'f686',  'code'=>'680 $a', 'label'=>'شماره رده کنگره (G)', 'full'=>'شماره رده کنگره (680 $a)', 'active'=>1, 'req'=>0),
                        'f700'  => array('col'=>'f700',  'code'=>'700 $a', 'label'=>'پدیدآور شخصی', 'full'=>'پدیدآور شخصی (700 $a)', 'active'=>1, 'req'=>0),
                        'f710'  => array('col'=>'f710',  'code'=>'710 $a', 'label'=>'سازمان نقشه‌برداری / پدیدآور سازمانی', 'full'=>'سازمان نقشه‌برداری / پدیدآور سازمانی (710 $a)', 'active'=>1, 'req'=>0)
                    )
                )
            )
        );
    }
    $cfg = $all[$loc_name];
    if (empty($cfg['cat_std'])) $cfg['cat_std'] = 'iranmarc';
    if (empty($cfg['class_sys'])) $cfg['class_sys'] = 'both';
    if (empty($cfg['worksheets']) || !is_array($cfg['worksheets'])) {
        $cfg['worksheets'] = array('book' => array('key'=>'book', 'title'=>'کاربرگه عمومی کتاب', 'rtype'=>'book', 'fields'=>$def_fields));
    }
    return $cfg;
}

function get_worksheet_active_fields($loc_name, $ws_key = 'book') {
    $cfg = get_lib_config($loc_name);
    $ws = isset($cfg['worksheets'][$ws_key]) ? $cfg['worksheets'][$ws_key] : reset($cfg['worksheets']);
    $out = array();
    $class_sys = $cfg['class_sys'];
    foreach ($ws['fields'] as $col => $fd) {
        if (empty($fd['active'])) continue;
        if ($class_sys === 'dewey' && $col === 'f686') continue;
        if ($class_sys === 'congress' && $col === 'f676') continue;
        $lbl = trim($fd['label']) . (trim($fd['code']) !== '' ? ' (' . trim($fd['code']) . ')' : '');
        $out[$col] = array(
            'col'   => $col,
            'code'  => $fd['code'],
            'label' => $fd['label'],
            'full'  => $lbl,
            'req'   => !empty($fd['req'])
        );
    }
    return $out;
}