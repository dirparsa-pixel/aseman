<?php
// کلاینت بومی پروتکل Z39.50 (سوکت ASN.1/BER مستقیم + YAZ + SRU)
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// ==================== ۶. کلاینت بومی پروتکل Z39.50 (Pure-PHP ASN.1/BER Socket + YAZ + SRU) ====================
// توابع بسته‌بندی استاندارد ASN.1 BER برای ارتباط مستقیم سوکت TCP با سرور Z39.50 کتابخانه ملی (z3950.nlai.ir:210) بدون نیاز به php_yaz
function ber_len($len) {
    if ($len < 128) return chr($len);
    $hex = dechex($len);
    if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
    $bytes = pack('H*', $hex);
    return chr(0x80 | strlen($bytes)) . $bytes;
}
function ber_tag($class, $constructed, $tag_num, $content) {
    $b1 = ($class << 6) | ($constructed ? 0x20 : 0x00);
    if ($tag_num < 31) {
        $tag_bytes = chr($b1 | $tag_num);
    } else {
        $tag_bytes = chr($b1 | 0x1F);
        $t = $tag_num;
        $chunks = array();
        $chunks[] = $t & 0x7F;
        $t >>= 7;
        while ($t > 0) {
            $chunks[] = ($t & 0x7F) | 0x80;
            $t >>= 7;
        }
        foreach (array_reverse($chunks) as $c) $tag_bytes .= chr($c);
    }
    return $tag_bytes . ber_len(strlen($content)) . $content;
}
function ber_int($n) {
    $n = (int)$n;
    if ($n === 0) return "\x00";
    $bytes = '';
    $tmp = $n;
    while ($tmp > 0) {
        $bytes = chr($tmp & 0xFF) . $bytes;
        $tmp >>= 8;
    }
    if (ord($bytes[0]) & 0x80) $bytes = "\x00" . $bytes;
    return $bytes;
}
function ber_oid($oid_str) {
    $pts = array_map('intval', explode('.', $oid_str));
    $out = chr(40 * $pts[0] + $pts[1]);
    for ($i = 2; $i < count($pts); $i++) {
        $v = $pts[$i];
        if ($v === 0) { $out .= "\x00"; continue; }
        $ch = array();
        $ch[] = $v & 0x7F;
        $v >>= 7;
        while ($v > 0) {
            $ch[] = ($v & 0x7F) | 0x80;
            $v >>= 7;
        }
        foreach (array_reverse($ch) as $c) $out .= chr($c);
    }
    return $out;
}
function z3950_read_pdu($fp) {
    $h = @fread($fp, 2);
    if (strlen($h) < 2) return '';
    $buf = $h;
    $b0 = ord($h[0]);
    $pos = 1;
    if (($b0 & 0x1F) === 0x1F) {
        while (true) {
            $b = ord($buf[$pos]);
            $pos++;
            if (!($b & 0x80)) break;
            $buf .= @fread($fp, 1);
        }
        $buf .= @fread($fp, 1);
    }
    $lb = ord($buf[$pos]);
    $pos++;
    if ($lb < 128) {
        $len = $lb;
    } elseif ($lb === 0x80) {
        // طول نامعین (Indefinite length): خواندن تا دریافت کل محتوای آماده در سوکت
        $rest = '';
        stream_set_timeout($fp, 4);
        while (!feof($fp)) {
            $chunk = @fread($fp, 8192);
            if ($chunk === '' || $chunk === false) break;
            $rest .= $chunk;
            $meta = stream_get_meta_data($fp);
            if ($meta['timed_out'] || $meta['unread_bytes'] === 0) break;
        }
        return $buf . $rest;
    } else {
        $nbytes = $lb & 0x7F;
        $lbytes = @fread($fp, $nbytes);
        $buf .= $lbytes;
        $len = 0;
        for ($i = 0; $i < strlen($lbytes); $i++) {
            $len = ($len << 8) | ord($lbytes[$i]);
        }
    }
    $rem = $len;
    while ($rem > 0 && !feof($fp)) {
        $ch = @fread($fp, min(8192, $rem));
        if ($ch === '' || $ch === false) break;
        $buf .= $ch;
        $rem -= strlen($ch);
    }
    return $buf;
}
function z3950_build_init_pdu() {
    // InitRequest [20] IMPLICIT: ProtocolVersion (v1,v2,v3) + Options (search, present, scan, namedResultSets) + PreferredMessageSize + MaximumRecordSize + ImplementationName
    $ver = ber_tag(2, 0, 3, "\x01\xE0"); // BIT STRING: v1, v2, v3
    $opt = ber_tag(2, 0, 4, "\x00\xEC\x80"); // BIT STRING: search(0), present(1), del(2), scan(7), namedResultSets(14)
    $pref = ber_tag(2, 0, 5, ber_int(1048576));
    $maxr = ber_tag(2, 0, 6, ber_int(1048576));
    $imp_id   = ber_tag(2, 0, 110, 'AsemanLib');
    $imp_name = ber_tag(2, 0, 111, 'Aseman Integrated Library System ZClient (Unicode)');
    $imp_ver  = ber_tag(2, 0, 112, '2.5');
    return ber_tag(2, 1, 20, $ver . $opt . $pref . $maxr . $imp_id . $imp_name . $imp_ver);
}
function z3950_build_search_pdu($db_name, $use_attr, $term, $syntax_oid = '1.2.840.10003.5.1') {
    // SearchRequest [22] IMPLICIT
    $small = ber_tag(2, 0, 13, ber_int(0));
    $large = ber_tag(2, 0, 14, ber_int(100000));
    $med   = ber_tag(2, 0, 15, ber_int(0));
    $repl  = ber_tag(2, 0, 16, "\x01"); // replaceIndicator = TRUE
    $rset  = ber_tag(2, 0, 17, 'default');
    $dbs   = ber_tag(2, 1, 18, ber_tag(2, 0, 105, $db_name));
    $syn   = ber_tag(2, 0, 104, ber_oid($syntax_oid)); // 1.2.840.10003.5.1 = UNIMARC, .5.10 = USMARC

    // RPNQuery [1] -> attributeSet (Bib-1: 1.2.840.10003.3.1) + rpn [0] Op [0] Operand -> AttrPlusTerm [102]
    $bib1_oid = ber_tag(0, 0, 6, ber_oid('1.2.840.10003.3.1'));
    $attr_type = ber_tag(2, 0, 120, ber_int(1)); // AttributeType = 1 (Use)
    $attr_val  = ber_tag(2, 0, 121, ber_int((int)$use_attr)); // AttributeValue (1016, 4, 7, 8, 21, 31, 59, 1018, 1003)
    $attr_el   = ber_tag(0, 1, 16, $attr_type . $attr_val);
    $attr_list = ber_tag(2, 1, 44, $attr_el);
    $term_oct  = ber_tag(2, 0, 45, $term); // general [45] OCTET STRING (UTF-8 Unicode)
    $apt       = ber_tag(2, 1, 102, $attr_list . $term_oct);
    $rpn_struct= ber_tag(2, 1, 0, $apt);
    $rpn_query = ber_tag(2, 1, 1, $bib1_oid . $rpn_struct);
    $query     = ber_tag(2, 1, 21, $rpn_query);

    return ber_tag(2, 1, 22, $small . $large . $med . $repl . $rset . $dbs . $syn . $query);
}
function z3950_build_present_pdu($start = 1, $count = 15, $syntax_oid = '1.2.840.10003.5.1') {
    // PresentRequest [24] IMPLICIT
    $rset  = ber_tag(2, 0, 31, 'default');
    $s_pos = ber_tag(2, 0, 30, ber_int($start));
    $n_req = ber_tag(2, 0, 29, ber_int($count));
    $syn   = ber_tag(2, 0, 104, ber_oid($syntax_oid));
    return ber_tag(2, 1, 24, $rset . $s_pos . $n_req . $syn);
}

// استخراج رکوردهای ISO2709 و XML از داخل پاسخ باینری PDU سرور Z39.50
function z3950_extract_marc_from_pdu($bin_data) {
    $raw_records = '';
    if (preg_match_all('/\d{5}[a-z0-9\s]{19}\d{12}[^\x1d]*\x1d/s', $bin_data, $m)) {
        foreach ($m[0] as $r) $raw_records .= $r;
    }
    if ($raw_records === '' && preg_match('/<(?:rdf:RDF|collection|record)[\s>].*<\/(?:rdf:RDF|collection|record)>/is', $bin_data, $xm)) {
        return $xm[0];
    }
    return $raw_records;
}

// تابع اصلی جستجو و بازیابی Z39.50 (سازگار با z3950.nlai.ir:210 و سایر سرورهای Z39.50 / SRU)
function fetch_z3950_records($server_host, $port, $db_name, $use_attr, $query, $syntax, $F_keys, &$status_info = '') {
    $q = trim($query);
    if ($q === '') return array();
    $server_host = trim($server_host) !== '' ? trim($server_host) : 'z3950.nlai.ir';
    $port = (int)en_num($port) > 0 ? (int)en_num($port) : 210;
    $db_name = trim($db_name) !== '' ? trim($db_name) : 'Default';
    $use_attr = (int)en_num($use_attr) > 0 ? (int)en_num($use_attr) : 1016;
    $syntax_oid = (strtolower($syntax) === 'marc21' || strtolower($syntax) === 'usmarc') ? '1.2.840.10003.5.10' : '1.2.840.10003.5.1';

    // ۱. اگر آدرس وب SRU وارد شده باشد (http:// یا https://)
    if (strpos($server_host, 'http://') === 0 || strpos($server_host, 'https://') === 0) {
        $sep = (strpos($server_host, '?') === false) ? '?' : '&';
        $url = $server_host . $sep . 'version=1.1&operation=searchRetrieve&maximumRecords=15&query=' . rawurlencode($q);
        $ctx = stream_context_create(array('http' => array('timeout' => 12, 'ignore_errors' => true), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
        $xml_res = @file_get_contents($url, false, $ctx);
        if ($xml_res) {
            $parsed = parse_iso2709_records($xml_res, $F_keys);
            $status_info = 'درگاه SRU: تعداد ' . count($parsed) . ' رکورد بازیابی شد.';
            return $parsed;
        }
        $status_info = 'پاسخی از درگاه SRU دریافت نشد.';
        return array();
    }

    // ۲. اگر افزونه php_yaz روی سرور نصب و فعال باشد
    if (function_exists('yaz_connect')) {
        $zurl = $server_host . ':' . $port . '/' . $db_name;
        $id = @yaz_connect($zurl, array('charset' => 'UTF-8'));
        if ($id) {
            @yaz_syntax($id, (strtolower($syntax) === 'marc21') ? 'usmarc' : 'unimarc');
            @yaz_range($id, 1, 15);
            @yaz_search($id, 'rpn', '@attr 1=' . $use_attr . ' "' . addslashes($q) . '"');
            @yaz_wait(array('timeout' => 12));
            $err = @yaz_error($id);
            $hits = (int)@yaz_hits($id);
            $raw_all = '';
            for ($i = 1; $i <= min(15, $hits); $i++) {
                $rec = @yaz_record($id, $i, 'raw');
                if ($rec) $raw_all .= $rec . (substr($rec, -1) === "\x1d" ? '' : "\x1d");
            }
            @yaz_close($id);
            if ($raw_all !== '') {
                $parsed = parse_iso2709_records($raw_all, $F_keys);
                $status_info = 'اتصال YAZ به ' . $zurl . ' موفق بود (تعداد کل نتایج در سرور: ' . tr_num($hits) . ' رکورد).';
                return $parsed;
            }
            if ($err) $status_info = 'پیام سرور Z39.50 (YAZ): ' . $err;
        }
    }

    // ۳. کلاینت بومی Pure-PHP Z39.50 (ارتباط مستقیم سوکت TCP با بسته ASN.1/BER بدون نیاز به افزونه)
    $errno = 0; $errstr = '';
    $fp = @fsockopen($server_host, $port, $errno, $errstr, 10);
    if (!$fp) {
        $status_info = 'عدم برقراری ارتباط با سرور ' . $server_host . ':' . $port . ' (' . $errstr . ' - کد ' . $errno . '). مطمئن شوید پورت ' . $port . ' در فایروال یا اینترنت شما باز است.';
        return array();
    }
    stream_set_timeout($fp, 10);

    // گام الف: ارسال بسته InitRequest
    @fwrite($fp, z3950_build_init_pdu());
    $init_resp = z3950_read_pdu($fp);
    if ($init_resp === '') {
        @fclose($fp);
        $status_info = 'سرور ' . $server_host . ':' . $port . ' به درخواست اولیه (InitRequest) پاسخ نداد.';
        return array();
    }

    // گام ب: ارسال بسته SearchRequest با کد ویژگی Bib-1 انتخاب‌شده (مثلاً 1016، 4، 7، 1003 و...) و کدینگ UTF-8
    $syntaxes_to_try = ($syntax_oid === '1.2.840.10003.5.1') ? array('1.2.840.10003.5.1', '1.2.840.10003.5.10') : array('1.2.840.10003.5.10', '1.2.840.10003.5.1');
    $raw_marc = '';
    foreach ($syntaxes_to_try as $syn_oid) {
        @fwrite($fp, z3950_build_search_pdu($db_name, $use_attr, $q, $syn_oid));
        $search_resp = z3950_read_pdu($fp);
        $raw_marc .= z3950_extract_marc_from_pdu($search_resp);

        // گام ج: ارسال بسته PresentRequest برای دریافت رکوردها (۱ تا ۱۵)
        @fwrite($fp, z3950_build_present_pdu(1, 15, $syn_oid));
        $pres_resp = z3950_read_pdu($fp);
        $raw_marc .= z3950_extract_marc_from_pdu($pres_resp);
        if ($raw_marc !== '') break;
    }
    @fclose($fp);

    if ($raw_marc !== '') {
        $parsed = parse_iso2709_records($raw_marc, $F_keys);
        $status_info = '✅ بازیابی مستقیم از سرور Z39.50 (' . $server_host . ':' . $port . ' / پایگاه ' . $db_name . ') با موفقیت انجام شد (' . tr_num(count($parsed)) . ' رکورد یونیکد استخراج شد).';
        return $parsed;
    }

    $status_info = 'ارتباط با سرور Z39.50 (' . $server_host . ':' . $port . ') برقرار شد اما هیچ رکوردی برای عبارت «' . $q . '» با ویژگی Use=' . $use_attr . ' یافت نشد.';
    return array();
}

// خواندن انواع فایل اکسل (.xlsx، جدول HTML .xls و .csv)
