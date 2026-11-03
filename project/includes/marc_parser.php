<?php
// مبدل‌های استاندارد ایزو ۲۷۰۹ (UNIMARC/MARC21)، ایران‌سیستم، XML و اکسل
if (!defined('ASEMAN_ROOT')) die('Access Denied');

// پاکسازی دقیق کاراکترهای کنترل یونیکد، ZWJ/ZWNJ اضافی و علائم مارک
function clean_marc_str($s) {
    $bidi_bytes = array(
        "\xE2\x80\x8D", "\xE2\x80\x8E", "\xE2\x80\x8F",
        "\xE2\x80\xAA", "\xE2\x80\xAB", "\xE2\x80\xAC", "\xE2\x80\xAD", "\xE2\x80\xAE",
        "\xE2\x81\xA6", "\xE2\x81\xA7", "\xE2\x81\xA8", "\xE2\x81\xA9", "\xEF\xBB\xBF"
    );
    $s = str_replace($bidi_bytes, '', (string)$s);
    $s = str_replace(array("\x1e", "\x1d", "\x1f", "\r", "\n"), ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = preg_replace('/^[\s:;\-\/،.؛«»\[\]()]+|[\s:;\-\/،.؛«»\[\]()]+$/u', '', $s);
    return trim($s);
}

// ۱. مبدل هوشمند کدینگ ایران‌سیستم (IranSystem بایت خام و متن کپی‌شده MacRoman) به یونیکد فارسی استاندارد
function decode_iransystem_str($raw) {
    $mac_to_byte = array(
        'Ä'=>"\x80",'Å'=>"\x81",'Ç'=>"\x82",'É'=>"\x83",'Ñ'=>"\x84",'Ö'=>"\x85",'Ü'=>"\x86",'á'=>"\x87",
        'à'=>"\x88",'â'=>"\x89",'ä'=>"\x8a",'ã'=>"\x8b",'å'=>"\x8c",'ç'=>"\x8d",'é'=>"\x8e",'è'=>"\x8f",
        'ê'=>"\x90",'ë'=>"\x91",'í'=>"\x92",'ì'=>"\x93",'î'=>"\x94",'ï'=>"\x95",'ñ'=>"\x96",'ó'=>"\x97",
        'ò'=>"\x98",'ô'=>"\x99",'ö'=>"\x9a",'õ'=>"\x9b",'ú'=>"\x9c",'ù'=>"\x9d",'û'=>"\x9e",'ü'=>"\x9f",
        '†'=>"\xa0",'°'=>"\xa1",'¢'=>"\xa2",'£'=>"\xa3",'§'=>"\xa4",'•'=>"\xa5",'¶'=>"\xa6",'ß'=>"\xa7",
        '®'=>"\xa8",'©'=>"\xa9",'™'=>"\xaa",'´'=>"\xab",'¨'=>"\xac",'≠'=>"\xad",'Æ'=>"\xae",'Ø'=>"\xaf",
        '¥'=>"\xb4",' '=>"\xca",'›'=>"\xdd",'ﬁ'=>"\xde",'‡'=>"\xe0",'·'=>"\xe1",'‚'=>"\xe2",'„'=>"\xe3",
        '‰'=>"\xe4",'Â'=>"\xe5",'Ê'=>"\xe6",'Á'=>"\xe7",'Ë'=>"\xe8",'È'=>"\xe9",'Í'=>"\xea",'Î'=>"\xeb",
        'Ï'=>"\xec",'Ì'=>"\xed",'Ó'=>"\xee",'Ô'=>"\xef",''=>"\xf0",'Ò'=>"\xf1",'Ú'=>"\xf2",'Û'=>"\xf3",
        'Ù'=>"\xf4",'ı'=>"\xf5",'ˆ'=>"\xf6",'˜'=>"\xf7",'¯'=>"\xf8",'˘'=>"\xf9",'˙'=>"\xfa",'˚'=>"\xfb",
        '¸'=>"\xfc",'˝'=>"\xfd",'˛'=>"\xfe",'ˇ'=>"\xff"
    );
    if (preg_match('/[ù™ó¸äËÚÙêÛ¢ÉÑâÅ]/u', $raw)) {
        $raw = strtr($raw, $mac_to_byte);
    }

    static $map = array(
        0x80=>'۰', 0x81=>'۱', 0x82=>'۲', 0x83=>'۳', 0x84=>'۴',
        0x85=>'۵', 0x86=>'۶', 0x87=>'۷', 0x88=>'۸', 0x89=>'۹',
        0x8A=>'،', 0x8B=>'ـ', 0x8C=>'؟', 0x8D=>'آ', 0x8E=>'ئ',
        0x8F=>'ء', 0x90=>'ا', 0x91=>'ا', 0x92=>'ب', 0x93=>'ب',
        0x94=>'پ', 0x95=>'پ', 0x96=>'ت', 0x97=>'ت', 0x98=>'ث',
        0x99=>'ث', 0x9A=>'ج', 0x9B=>'ج', 0x9C=>'چ', 0x9D=>'چ',
        0x9E=>'ح', 0x9F=>'ح', 0xA0=>'خ', 0xA1=>'خ', 0xA2=>'د',
        0xA3=>'ذ', 0xA4=>'ر', 0xA5=>'ز', 0xA6=>'ژ', 0xA7=>'س',
        0xA8=>'س', 0xA9=>'ش', 0xAA=>'ش', 0xAB=>'ص', 0xAC=>'ص',
        0xAD=>'ض', 0xAE=>'ض', 0xAF=>'ط', 0xB4=>' ', 0xCA=>'ء',
        0xDD=>'؛', 0xDE=>'ه', 0xE0=>'ظ', 0xE1=>'ع', 0xE2=>'ع',
        0xE3=>'ع', 0xE4=>'ع', 0xE5=>'غ', 0xE6=>'غ', 0xE7=>'غ',
        0xE8=>'غ', 0xE9=>'ف', 0xEA=>'ف', 0xEB=>'ق', 0xEC=>'ق',
        0xED=>'ک', 0xEE=>'ک', 0xEF=>'گ', 0xF0=>'گ', 0xF1=>'ل',
        0xF2=>'لا',0xF3=>'ل', 0xF4=>'م', 0xF5=>'م', 0xF6=>'ن',
        0xF7=>'ن', 0xF8=>'و', 0xF9=>'ه', 0xFA=>'ه', 0xFB=>'ه',
        0xFC=>'ی', 0xFD=>'ی', 0xFE=>'ی', 0xFF=>' '
    );

    $out = '';
    $len = strlen($raw);
    for ($i = 0; $i < $len; $i++) {
        $b = ord($raw[$i]);
        $out .= isset($map[$b]) ? $map[$b] : $raw[$i];
    }
    $out = preg_replace('/\}([^{}]*)\{/', '$1', $out);
    $out = str_replace(array('{', '}'), '', $out);
    $out = preg_replace_callback('/[۰-۹]{2,}/u', function($m) {
        preg_match_all('/./u', $m[0], $chars);
        return implode('', array_reverse($chars[0]));
    }, $out);
    return clean_marc_str($out);
}

// ۲. مبدل فایل‌های XML (شامل RDF/DublinCore مانند 22.xml و MARCXML استاندارد)
function parse_xml_records($raw, $F_keys) {
    $parsed = array();
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($raw);
    if (!$xml) return $parsed;

    $ns = $xml->getNamespaces(true);
    $descriptions = $xml->xpath('//*[local-name()="Description"]');
    if ($descriptions) {
        foreach ($descriptions as $desc) {
            $acc = array();
            foreach ($F_keys as $k) $acc[$k] = array();
            foreach ($desc->children(isset($ns['dc']) ? $ns['dc'] : null) as $ename => $eval) {
                $val = clean_marc_str((string)$eval);
                if ($val === '') continue;
                $attrs = $eval->attributes();
                $scheme = isset($attrs['Scheme']) ? strtoupper((string)$attrs['Scheme']) : '';

                if ($ename === 'Title') $acc['f200a'][] = $val;
                elseif ($ename === 'Creator.PersonalName') $acc['f700'][] = $val;
                elseif ($ename === 'Creator.CorporateName.IncludesCorporateName') {
                    if (preg_match('/^[0-9۰-۹\s\-؟?ق‌م.]+$/u', $val) && !empty($acc['f700'])) {
                        $acc['f700'][count($acc['f700']) - 1] .= ' (' . $val . ')';
                    } elseif (!preg_match('/^[A-Za-z\s,.\-]+$/', $val)) {
                        $acc['f710'][] = $val;
                    }
                }
                elseif ($ename === 'Creator.unqualified') $acc['f200f'][] = $val;
                elseif ($ename === 'Publisher.unqualified') {
                    if (empty($acc['f210a'])) $acc['f210a'][] = $val;
                    else $acc['f210c'][] = $val;
                }
                elseif ($ename === 'Date.Issued') $acc['f210d'][] = $val;
                elseif ($ename === 'Subject.Classification') {
                    if ($scheme === 'DDC') $acc['f676'][] = $val;
                    else $acc['f686'][] = $val;
                }
                elseif ($ename === 'Subject') $acc['f606'][] = $val;
                elseif ($ename === 'Contributer.CorporateName') $acc['f710'][] = $val;
                elseif ($ename === 'Contributer.unqualified') {
                    if (!in_array($val, array('مترجم','مصحح','شارح','گردآورنده','محقق','ویراستار','ناظر','حاشیه‌نویس','حاشیه نویس')) && !preg_match('/^[0-9۰-۹\s\-؟?ق‌م.]+$/u', $val)) {
                        $acc['f700'][] = $val;
                    }
                }
                elseif ($ename === 'Identifier') $acc['f010'][] = $val;
                elseif ($ename === 'Language' && empty($acc['f101'])) {
                    $acc['f101'][] = (mb_strpos($val, 'عرب') !== false && mb_strpos($val, 'فارس') === false) ? 'ara' : 'per';
                }
            }
            $row = array();
            foreach ($F_keys as $k) {
                $sep = ($k === 'f686') ? ' ' : ' ؛ ';
                $row[$k] = implode($sep, array_values(array_unique(array_filter($acc[$k]))));
            }
            if ($row['f101'] === '') $row['f101'] = 'per';
            if ($row['f200a'] !== '') $parsed[] = $row;
        }
        if ($parsed) return $parsed;
    }

    $mrecs = $xml->xpath('//*[local-name()="record"]');
    if ($mrecs) {
        foreach ($mrecs as $mr) {
            $pairs = array();
            foreach ($mr->xpath('./*[local-name()="datafield"]') as $df) {
                $tag = (string)$df['tag'];
                $sstr = '';
                foreach ($df->xpath('./*[local-name()="subfield"]') as $sf) {
                    $code = (string)$sf['code'];
                    $sstr .= "\x1f" . $code . (string)$sf;
                }
                if ($sstr !== '') $pairs[] = array($tag, $sstr);
            }
            $r = map_marc_pairs_to_row($pairs, $F_keys, false);
            if ($r['f200a'] !== '') $parsed[] = $r;
        }
    }
    return $parsed;
}

// ۳. استخراج هوشمند زیرفیلدها از انواع فرمت‌ها (\x1f، ^a، $a، a$ و زیرفیلدهای فاصله‌دار کتابخانه ملی)
function extract_subfields_universal($tag, $val, $is_iransys = false) {
    $subs = array();
    if ($is_iransys) {
        $val = rtrim($val, "# \r\n");
        if (strpos($val, '^') !== false) {
            $parts = preg_split('/\^([a-z0-9])/i', $val, -1, PREG_SPLIT_DELIM_CAPTURE);
            $first = decode_iransystem_str($parts[0]);
            if ($first !== '') $subs['a'][] = $first;
            for ($i = 1; $i + 1 < count($parts); $i += 2) {
                $c = strtolower($parts[$i]);
                $v = decode_iransystem_str($parts[$i + 1]);
                if ($v !== '') $subs[$c][] = $v;
            }
        } else {
            $v = decode_iransystem_str($val);
            if ($v !== '') $subs['a'][] = $v;
        }
        return $subs;
    }

    if (strpos($val, "\x1f") !== false) {
        $parts = explode("\x1f", $val);
        foreach ($parts as $idx => $part) {
            if ($idx === 0 || $part === '') continue;
            $code = strtolower($part[0]);
            $v_clean = clean_marc_str(substr($part, 1));
            if ($v_clean !== '') $subs[$code][] = $v_clean;
        }
        return $subs;
    }

    if (preg_match('/(\^[a-z0-9]|\$[a-z0-9]|[a-z0-9]\$)/i', $val)) {
        $norm = preg_replace('/([a-z0-9])\$(?=[آ-یA-Za-z0-9\[])/u', '$$1', $val);
        $norm = str_replace('^', '$', $norm);
        $parts = preg_split('/\$([a-z0-9])/i', $norm, -1, PREG_SPLIT_DELIM_CAPTURE);
        $first = clean_marc_str($parts[0]);
        if ($first !== '' && !preg_match('/^[0-9#]{1,2}$/', $first)) $subs['a'][] = $first;
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $code = strtolower($parts[$i]);
            $v_clean = clean_marc_str($parts[$i + 1]);
            if ($v_clean !== '') $subs[$code][] = $v_clean;
        }
        return $subs;
    }

    $v = preg_replace('/^\s*[0-9#]{1,2}\s+/u', '   ', $val);
    if ($tag === '200' || $tag === '245') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+b(?=\[)/u', "\x1fb", $v);
        $v = preg_replace('/\s+e(?=\s*:)/u', "\x1fe", $v);
        $v = preg_replace('/\s+f(?=\s*\/|\S)/u', "\x1ff", $v);
        $v = preg_replace('/\s+g(?=\s*؛)/u', "\x1fg", $v);
    } elseif ($tag === '210' || $tag === '260' || $tag === '264') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v);
        $v = preg_replace('/\s+b(?=تلفن|آدرس|:)/u', "\x1fb", $v);
        $v = preg_replace('/\s+c(?=\s*:|\S)/u', "\x1fc", $v);
        $v = preg_replace('/\s+d(?=\s*،|\s*\[|\s*[۰-۹0-9])/u', "\x1fd", $v);
    } elseif ($tag === '215') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+c(?=\s*:)/u', "\x1fc", $v);
        $v = preg_replace('/\s+d(?=\s*؛)/u', "\x1fd", $v);
    } elseif ($tag === '225' || $tag === '490') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+v(?=\s*؛)/u', "\x1fv", $v);
    } elseif ($tag === '010' || $tag === '020') {
        if (preg_match('/([0-9]{9,13}[0-9Xx\-]*)/', en_num($v), $im)) {
            $subs['a'][] = $im[1];
            return $subs;
        }
    } elseif (in_array($tag, array('600','606','610','650'))) {
        $v = preg_replace('/\s+[0-9]{5,9}(\s+[0-9a-zA-Z]+)*\s*$/u', '', $v);
        $v = preg_replace('/(?<=^|\s)[a9](?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+[xyzj](?=\s*--)/u', "\x1fx", $v);
    } elseif (in_array($tag, array('700','701','702','710','711','712'))) {
        $v = preg_replace('/\s+[0-9]{5,9}(\s+[0-9a-zA-Z]+)*(\s+4[^\s]+)?\s*$/u', '', $v);
        $v = preg_replace('/(?<=^|\s)[a9](?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+b(?=\s*،|\s*\.)/u', "\x1fb", $v);
        $v = preg_replace('/\s+[f4](?=\s*،)/u', "\x1ff", $v);
    } elseif ($tag === '676' || $tag === '082' || $tag === '680' || $tag === '686' || $tag === '050') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+b(?=\S)/u', "\x1fb", $v, 1);
    } elseif ($tag === '995') {
        $v = preg_replace('/(?<=^|\s)a(?=\S)/u', "\x1fa", $v, 1);
        $v = preg_replace('/\s+f(?=[0-9۰-۹])/u', "\x1ff", $v, 1);
        $v = preg_replace('/\s+v(?=[0-9۰-۹])/u', "\x1fv", $v, 1);
    } else {
        $v = preg_replace('/(?<=^|\s)[a9](?=\S)/u', "\x1fa", $v, 1);
    }

    if (strpos($v, "\x1f") !== false) {
        return extract_subfields_universal($tag, $v, false);
    }
    $v_clean = clean_marc_str($v);
    if ($v_clean !== '') $subs['a'][] = $v_clean;
    return $subs;
}

// ۴. نگاشت تگ‌ها (هم استاندارد UNIMARC/MARC21 و هم تگ‌های سیمرغ/پارس‌آذرخش/کاوش مانند Test.iso)
function map_marc_pairs_to_row($tag_values_pairs, $F_keys, $is_iransys = false) {
    $acc = array();
    foreach ($F_keys as $k) $acc[$k] = array();
    $extra_copies = 0;

    foreach ($tag_values_pairs as $pair) {
        $tag = $pair[0];
        $val = $pair[1];
        $subs = extract_subfields_universal($tag, $val, $is_iransys);
        if (empty($subs)) continue;

        if ($is_iransys) {
            if ($tag === '002' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f700'][] = $x;
            elseif ($tag === '004' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f200e'][] = $x;
            elseif ($tag === '005' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f200a'][] = $x;
            elseif (($tag === '007' || $tag === '008' || $tag === '009' || $tag === '011') && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f200f'][] = $x;
            }
            elseif ($tag === '012' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f205'][] = $x;
            elseif ($tag === '014') {
                if (isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f210a'][] = $x;
                if (isset($subs['b'])) foreach ($subs['b'] as $x) $acc['f210c'][] = $x;
                if (isset($subs['c'])) foreach ($subs['c'] as $x) $acc['f210d'][] = $x;
            }
            elseif ($tag === '016' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f215'][] = $x;
            elseif ($tag === '017' && isset($subs['a'])) {
                $s225 = implode(' - ', $subs['a']);
                if (isset($subs['b'])) $s225 .= ' ؛ ' . implode('، ', $subs['b']);
                $acc['f225'][] = $s225;
            }
            elseif (in_array($tag, array('022','023','024','043')) && isset($subs['a'])) {
                foreach ($subs['a'] as $x) $acc['f300'][] = $x;
            }
            elseif ($tag === '025') {
                $lc = array();
                foreach (array('a','n','c','d') as $ck) if (isset($subs[$ck])) $lc[] = implode(' ', $subs[$ck]);
                if ($lc) $acc['f686'][] = implode(' ', $lc);
            }
            elseif ($tag === '036' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f676'][] = $x;
            elseif ($tag === '044') {
                $sp = array();
                foreach (array('a','b','x','y','z') as $sk) if (isset($subs[$sk])) $sp[] = implode(' -- ', $subs[$sk]);
                if ($sp) $acc['f606'][] = implode(' -- ', $sp);
            }
            elseif ($tag === '045' && isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f700'][] = $x;
            elseif ($tag === '048') {
                if (isset($subs['c'])) foreach ($subs['c'] as $x) $acc['f010'][] = $x;
                $extra_copies++;
            }
            elseif ($tag === '058' && isset($subs['a'])) {
                foreach ($subs['a'] as $x) if ($x !== 'عنوان') $acc['f710'][] = $x;
            }
            continue;
        }

        if (($tag === '010' || $tag === '011' || $tag === '020') && isset($subs['a'])) {
            foreach ($subs['a'] as $x) $acc['f010'][] = $x;
        }
        if (($tag === '101' || $tag === '041') && isset($subs['a'])) {
            foreach ($subs['a'] as $x) $acc['f101'][] = $x;
        }
        if ($tag === '200' || $tag === '245') {
            if (isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f200a'][] = preg_replace('/^<([^>=]+)=[^>]+>/u', '$1', $x);
            if (isset($subs['e'])) foreach ($subs['e'] as $x) $acc['f200e'][] = $x;
            if ($tag === '245' && isset($subs['b'])) foreach ($subs['b'] as $x) $acc['f200e'][] = $x;
            if (isset($subs['f'])) foreach ($subs['f'] as $x) $acc['f200f'][] = $x;
            if (isset($subs['g'])) foreach ($subs['g'] as $x) $acc['f200f'][] = $x;
            if ($tag === '245' && isset($subs['c'])) foreach ($subs['c'] as $x) $acc['f200f'][] = $x;
        }
        if (($tag === '205' || $tag === '250') && isset($subs['a'])) {
            foreach ($subs['a'] as $x) $acc['f205'][] = $x;
        }
        if ($tag === '210' || $tag === '260' || $tag === '264') {
            if (isset($subs['a'])) foreach ($subs['a'] as $x) $acc['f210a'][] = $x;
            if (isset($subs['c']) && $tag === '210') foreach ($subs['c'] as $x) $acc['f210c'][] = $x;
            if (isset($subs['b']) && ($tag === '260' || $tag === '264')) foreach ($subs['b'] as $x) $acc['f210c'][] = $x;
            if (isset($subs['d']) && $tag === '210') foreach ($subs['d'] as $x) $acc['f210d'][] = $x;
            if (isset($subs['c']) && ($tag === '260' || $tag === '264')) foreach ($subs['c'] as $x) $acc['f210d'][] = $x;
        }
        if ($tag === '215' && isset($subs['a'])) {
            $p215 = implode(' - ', $subs['a']);
            if (isset($subs['c'])) $p215 .= ' : ' . implode('، ', $subs['c']);
            $acc['f215'][] = $p215;
        }
        if (($tag === '225' || $tag === '490') && isset($subs['a'])) {
            $p225 = implode(' - ', $subs['a']);
            if (isset($subs['v'])) $p225 .= ' ؛ ج. ' . implode('، ', $subs['v']);
            $acc['f225'][] = $p225;
        }
        if (in_array($tag, array('300','304','305','312','314','320','330','500','504')) && isset($subs['a'])) {
            foreach ($subs['a'] as $x) $acc['f300'][] = $x;
        }
        if (in_array($tag, array('600','606','610','650')) && isset($subs['a'])) {
            if (!isset($subs['7']) && !preg_match('/^[A-Za-z\s,\-\.]+$/', $subs['a'][0])) {
                $s_parts = $subs['a'];
                foreach (array('x','y','z','j') as $sk) if (isset($subs[$sk])) $s_parts = array_merge($s_parts, $subs[$sk]);
                $acc['f606'][] = implode(' -- ', $s_parts);
            }
        }
        if (($tag === '676' || $tag === '082') && isset($subs['a'])) {
            foreach ($subs['a'] as $x) $acc['f676'][] = $x;
        }
        if (($tag === '680' || $tag === '686' || $tag === '050') && isset($subs['a'])) {
            $lc_str = implode(' ', $subs['a']);
            if (isset($subs['b'])) $lc_str .= ' ' . implode(' ', $subs['b']);
            $acc['f686'][] = trim($lc_str);
        }
        if ($tag === '100' && isset($subs['a'])) {
            $v100 = en_num($subs['a'][0]);
            // تشخیص فیلد کنترلی 100 در ایران‌مارک/UNIMARC (شامل تاریخ ۸ رقمی یا کد زبان per/ara/eng و کد کاراکتر 50)
            if (preg_match('/(\d{8}|(?:per|far|ara|eng|fre|ger|rus|tur|kur)\s*[0-9]{0,2}|\b50\b)/i', $v100)) {
                if (empty($acc['f101']) && preg_match('/\b(per|far|ara|eng|fre|ger|rus|tur|kur)\b/i', $v100, $lm)) {
                    $acc['f101'][] = strtolower($lm[1]) === 'far' ? 'per' : strtolower($lm[1]);
                }
            } else {
                $pname = $subs['a'][0];
                if (!preg_match('/^[A-Za-z0-9\s,\-\.]+$/', $pname)) {
                    if (isset($subs['b'][0]) && $subs['b'][0] !== '') $pname .= '، ' . $subs['b'][0];
                    $acc['f700'][] = $pname;
                }
            }
        }
        if (in_array($tag, array('700','701','702')) && isset($subs['a'])) {
            $pname = $subs['a'][0];
            $p_en = en_num($pname);
            if (!preg_match('/^[A-Za-z\s,\-\.]+$/', $pname) && !preg_match('/^\d{8}/', $p_en) && !preg_match('/\b(per|ara|eng)\s*50\b/i', $p_en)) {
                if (isset($subs['b'][0]) && $subs['b'][0] !== '') $pname .= '، ' . $subs['b'][0];
                $acc['f700'][] = $pname;
            }
        }
        if (in_array($tag, array('710','711','712','110')) && isset($subs['a'])) {
            foreach ($subs['a'] as $x) {
                if (!preg_match('/^[A-Za-z\s,\-\.]+$/', $x)) $acc['f710'][] = $x;
            }
        }
        if ($tag === '995') {
            $extra_copies++;
        }
    }

    $row = array();
    foreach ($F_keys as $k) {
        $unique_vals = array_values(array_unique(array_filter($acc[$k])));
        $row[$k] = implode(' ؛ ', $unique_vals);
    }
    if ($row['f101'] === '') $row['f101'] = 'per';
    if ($extra_copies > 1) $row['copies'] = $extra_copies;
    return $row;
}

// ۵. موتور جامع پردازشگر ایزو ۲۷۰۹، ایران‌سیستم، کتابخانه ملی و XML
function parse_iso2709_records($raw, $F_keys) {
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    $trim_raw = ltrim($raw);
    if (strpos($trim_raw, '<') === 0 || stripos($trim_raw, '<rdf:RDF') !== false || stripos($trim_raw, '<collection') !== false || stripos($trim_raw, '<record') !== false) {
        return parse_xml_records($raw, $F_keys);
    }

    $parsed = array();
    $raw_unwrapped = str_replace(array("\r\n", "\r", "\n"), '', $raw);
    $is_iransys = (strpos($raw_unwrapped, '##') !== false && strpos($raw_unwrapped, '^a') !== false && preg_match('/^\d{5}0000000\d{5}/', ltrim($raw_unwrapped)));

    if ($is_iransys) {
        $pos = 0;
        $total_len = strlen($raw_unwrapped);
        while ($pos + 24 <= $total_len) {
            while ($pos < $total_len && ($raw_unwrapped[$pos] < '0' || $raw_unwrapped[$pos] > '9')) $pos++;
            if ($pos + 24 > $total_len) break;
            $rlen = (int)substr($raw_unwrapped, $pos, 5);
            $base = (int)substr($raw_unwrapped, $pos + 12, 5);
            if ($rlen <= 24 || $base <= 24 || $base > $rlen) break;

            $end_hash = strpos($raw_unwrapped, '##', $pos + $base);
            if ($end_hash !== false && abs(($end_hash + 2 - $pos) - $rlen) > 5) {
                $rec = substr($raw_unwrapped, $pos, $end_hash + 2 - $pos);
                $pos = $end_hash + 2;
                $dir = substr($rec, 24, $base - 25);
                $data = substr($rec, $base);
                $fields_raw = explode('#', rtrim($data, '#'));
                $pairs = array();
                $f_idx = 0;
                for ($i = 0; $i + 12 <= strlen($dir); $i += 12) {
                    $tag = substr($dir, $i, 3);
                    if (isset($fields_raw[$f_idx])) {
                        $pairs[] = array($tag, $fields_raw[$f_idx]);
                    }
                    $f_idx++;
                }
            } else {
                $rec = substr($raw_unwrapped, $pos, $rlen);
                $pos += $rlen;
                $dir = substr($rec, 24, $base - 25);
                $data = substr($rec, $base);
                $pairs = array();
                for ($i = 0; $i + 12 <= strlen($dir); $i += 12) {
                    $tag = substr($dir, $i, 3);
                    $flen = (int)substr($dir, $i + 3, 4);
                    $fpos = (int)substr($dir, $i + 7, 5);
                    $pairs[] = array($tag, substr($data, $fpos, $flen));
                }
            }
            $row = map_marc_pairs_to_row($pairs, $F_keys, true);
            if ($row['f200a'] !== '') $parsed[] = $row;
        }
        if ($parsed) return $parsed;
    }

    $raw_records = explode("\x1d", $raw);
    if (count($raw_records) <= 1 && preg_match_all('/\d{5}[a-z0-9\s]{19}(?=\d{12})/i', $raw, $ldr_m, PREG_OFFSET_CAPTURE)) {
        if (count($ldr_m[0]) >= 1) {
            $raw_records = array();
            for ($k = 0; $k < count($ldr_m[0]); $k++) {
                $start = $ldr_m[0][$k][1];
                $end = isset($ldr_m[0][$k+1]) ? $ldr_m[0][$k+1][1] : strlen($raw);
                $raw_records[] = substr($raw, $start, $end - $start);
            }
        }
    }

    foreach ($raw_records as $rec) {
        $rec = ltrim($rec, "\r\n ");
        if (strlen($rec) < 24) continue;
        $tag_values_pairs = array();

        $chunks = explode("\x1e", $rec);
        if (count($chunks) > 1) {
            $leader_and_dir = $chunks[0];
            if (strlen($leader_and_dir) >= 36) {
                $dir_str = substr($leader_and_dir, 24);
                if (preg_match_all('/(\d{3})\d{9}/', $dir_str, $tm)) {
                    foreach ($tm[1] as $idx => $tg) {
                        if (isset($chunks[$idx + 1])) $tag_values_pairs[] = array($tg, $chunks[$idx + 1]);
                    }
                }
            }
        }

        if (empty($tag_values_pairs)) {
            $base = (int)substr($rec, 12, 5);
            if ($base > 24 && $base <= strlen($rec)) {
                $dir = substr($rec, 24, $base - 25);
                $data = substr($rec, $base);
                $entries = array();
                for ($i = 0; $i + 12 <= strlen($dir); $i += 12) {
                    $entries[] = array(
                        'tag' => substr($dir, $i, 3),
                        'len' => (int)substr($dir, $i + 3, 4),
                        'pos' => (int)substr($dir, $i + 7, 5)
                    );
                }
                $expected_data_len = !empty($entries) ? ($entries[count($entries)-1]['pos'] + $entries[count($entries)-1]['len']) : 0;
                $use_mb = ($expected_data_len > 0 && abs(mb_strlen($data, 'UTF-8') - $expected_data_len) <= 5 && strlen($data) > $expected_data_len + 10);
                foreach ($entries as $en) {
                    $val = $use_mb ? mb_substr($data, $en['pos'], $en['len'], 'UTF-8') : substr($data, $en['pos'], $en['len']);
                    $tag_values_pairs[] = array($en['tag'], $val);
                }
            }
        }

        $row = map_marc_pairs_to_row($tag_values_pairs, $F_keys, false);
        if ($row['f200a'] !== '') $parsed[] = $row;
    }
    return $parsed;
}

// تبدیل اعداد فارسی/عربی به لاتین در کل متن UTF-8 (برای سرستون‌های اکسل)
function xl_norm_digits($s) {
    return str_replace(
        array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'),
        array('0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'),
        (string)$s
    );
}

// نرمال‌سازی متن سلول: حذف کاراکترهای کنترل، ZWJ/ZWNJ و مرتب‌سازی ظاهری متون RTL
function xl_norm_text($s) {
    $s = str_replace("\xEF\xBB\xBF", '', (string)$s);          // BOM میانی
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s); // کاراکترهای کنترل
    $s = preg_replace('/\x{200C}|\x{200B}/u', '', $s);         // ZWNJ / ZWSP
    if (function_exists('normalizer_normalize')) {
        $n = @normalizer_normalize($s, Normalizer::FORM_C);
        if ($n !== false) $s = $n;
    }
    return trim($s);
}

// تشخیص اینکه آیا سرستون ردیف اولِ داده است یا خیر (حداقل ۲ سلول غیرخالی + حداقل یک برچسب شناخته‌شده)
function xl_header_score_row($row, $ws_fields = null) {
    $non_empty = 0;
    foreach ($row as $v) if (trim((string)$v) !== '') $non_empty++;
    if ($non_empty < 2) return array(false, $non_empty);
    $known = 0;
    $labels = array('نام','عنوان','شابک','شاپا','زبان','ناشر','نشر','محل','سال','صفحه','جلد','نسخه','ثبت','یادداشت','موضوع','دیویی','کنگره','پدیدآور','مسئولیت','ویراست','فروست','مجموعه','تلفن','موبایل','تماس','کد','عضو','نوع','منبع','سازمانی','ابعاد','قیاس');
    foreach ($row as $v) {
        $cv = xl_norm_digits(xl_norm_text($v));
        if ($cv === '') continue;
        $is_label = false;
        foreach ($labels as $lb) if (mb_strpos($cv, $lb) !== false) { $is_label = true; break; }
        if (!$is_label && preg_match('/\b(0\d\d|1\d\d|2\d\d|3\d\d|6\d\d|7\d\d)\b/', $cv)) $is_label = true;
        if (!$is_label && $ws_fields) {
            foreach ($ws_fields as $fd) {
                if (($fd['code'] !== '' && stripos(strtolower($cv), strtolower(xl_norm_digits($fd['code']))) !== false) ||
                    ($fd['label'] !== '' && mb_strpos($cv, $fd['label']) !== false)) { $is_label = true; break; }
            }
        }
        if ($is_label) $known++;
    }
    return array($known >= 1, $non_empty);
}

// استخراج رشته‌های مشترک sharedStrings با پشتیبانی از runs چندتکه‌ای و متون RTL
function xl_decode_entities($t) {
    $t = html_entity_decode((string)$t, ENT_QUOTES | ENT_XML1, 'UTF-8');
    // کاراکترهای Unicode به شکل &#xNNNN; / &#NNNN; که در برخی خروجی‌های Excel فارسی رایج است
    $t = preg_replace_callback('/&#x([0-9a-fA-F]{1,6});|&#(\d{1,7});/', function ($m) {
        $cp = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : hexdec($m[1]);
        if ($cp <= 0 || $cp > 0x10FFFF) return '';
        if (function_exists('mb_chr')) return mb_chr($cp, 'UTF-8');
        if ($cp < 0x80) return chr($cp);
        if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 63));
        if ($cp < 0x10000) return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 63)) . chr(0x80 | ($cp & 63));
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 63)) . chr(0x80 | (($cp >> 6) & 63)) . chr(0x80 | ($cp & 63));
    }, $t);
    return $t;
}

function xl_si_text($si_inner) {
    $t = '';
    // حالت استاندارد: تمام گره‌های <t> (شامل runs های تکه‌تکه <r><t>)
    if (preg_match_all('#<t\b[^>]*>(.*?)</t>#s', $si_inner, $tm)) {
        foreach ($tm[1] as $part) $t .= $part;
    }
    // حالت جایگزین: محتوای متنی مستقیم داخل <si> بدون تگ <t> (سازگار با خروجی‌های قدیمی)
    if (trim($t) === '' && strpos($si_inner, '<t') === false) {
        $t = strip_tags($si_inner);
    }
    return xl_norm_text(xl_decode_entities($t));
}

function xl_read_shared_strings($xml) {
    $strings = array();
    if ($xml === false || $xml === '') return $strings;
    // روش مطمئن: DOMParser (همان روش نسخه تک‌فایل قدیمی که پایدار بود)
    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        if ($dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET)) {
            $xsi = $dom->getElementsByTagName('si');
            foreach ($xsi as $si) {
                $t = '';
                $xt = $si->getElementsByTagName('t');
                if ($xt->length > 0) {
                    foreach ($xt as $tnode) $t .= $tnode->textContent;
                } else {
                    $t = $si->textContent;
                }
                $strings[] = xl_norm_text($t);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($strings) return $strings;
    }
    // روش ذخیره: regex با پوشش هر دو حالت
    if (preg_match_all('#<si\b[^>]*>(.*?)</si>#s', $xml, $si_m)) {
        foreach ($si_m[1] as $si_inner) $strings[] = xl_si_text($si_inner);
    }
    return $strings;
}

// استخراج متن یک ردیف <row> به صورت نقشه «شاخص ستون => مقدار»
function xl_parse_row_cells($row_inner, $strings) {
    $row_map = array();
    $next_idx = 0;
    // سلول‌های خالی بدون مقدار (<c r="B2"/>) نیز درست پیمایش شوند
    if (preg_match_all('#<c\b([^>]*?)(/>|>(.*?)</c>)#s', $row_inner, $cm_all, PREG_SET_ORDER)) {
        foreach ($cm_all as $cm_set) {
            $attrs = $cm_set[1];
            $inner = isset($cm_set[4]) ? $cm_set[4] : '';
            if (preg_match('/\br="([A-Z]+)\d*"/i', $attrs, $rl)) {
                $letters = strtoupper($rl[1]);
                $col_idx = 0;
                for ($li = 0; $li < strlen($letters); $li++) $col_idx = $col_idx * 26 + (ord($letters[$li]) - 64);
                $col_idx -= 1;
            } else {
                $col_idx = $next_idx;
            }
            $next_idx = $col_idx + 1;
            $type_s = preg_match('/\bt="(s|inlineStr|str)"/i', $attrs, $tm2) ? strtolower($tm2[1]) : '';
            $v = '';
            if ($type_s === 'inlineStr') {
                if (preg_match_all('#<t\b[^>]*>(.*?)</t>#s', $inner, $tt)) $v = implode('', $tt[1]);
            } elseif (preg_match('#<v\b[^>]*>(.*?)</v>#s', $inner, $vm)) {
                $v = $vm[1];
            } elseif (preg_match('#<t\b[^>]*>(.*?)</t>#s', $inner, $tm3)) {
                $v = $tm3[1];
            }
            if ($type_s === 's') {
                $vi = (int)trim($v);
                $v = isset($strings[$vi]) ? $strings[$vi] : '';
            } else {
                $v = xl_decode_entities($v);
            }
            $row_map[$col_idx] = xl_norm_text($v);
        }
    }
    return $row_map;
}

// تبدیل شماره سریال تاریخ اکسل (مبنای ۱۹۰۰) به تاریخ میلادی
function xl_serial_to_date($n) {
    $n = (float)$n;
    if ($n < 1 || $n > 2958465) return '';
    if ($n < 60) $n += 1; // پرش معروف باگ روز ۲۹ فوریه ۱۹۰۰ در Excel
    $ts = ($n - 25569) * 86400;
    return gmdate('Y-m-d', (int)round($ts));
}

// پیمایش کاربرگه با SimpleXML و بازگرداندن ردیف‌ها (آرایه‌های متنی هم‌تراز)
function xl_rows_from_sxml(SimpleXMLElement $sx, $strings) {
    $pending = array();
    $max_col = 0;
    $nsx = $sx->getNamespaces(true);
    $main_ns = isset($nsx['']) ? $nsx[''] : '';
    foreach ($sx->sheetData->row as $r) {
        $cells = array();
        if ($main_ns !== '') {
            $sd = $r->children($main_ns);
            if (isset($sd->c)) $cells = $sd->c;
        }
        if (!$cells) $cells = $r->c;
        $row_map = array();
        $pos = 0;
        foreach ($cells as $c) {
            $ref = (string)$c['r'];
            if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $rl)) {
                $letters = strtoupper($rl[1]);
                $col_idx = 0;
                for ($li = 0; $li < strlen($letters); $li++) $col_idx = $col_idx * 26 + (ord($letters[$li]) - 64);
                $col_idx -= 1;
            } else {
                $col_idx = $pos;
            }
            $pos = $col_idx + 1;
            $type_s = strtolower((string)$c['t']);
            $v = '';
            if ($type_s === 'inlineStr') {
                if (isset($c->is)) {
                    $v = (string)$c->is->t;
                    if ($v === '') { foreach ($c->is->r as $rr) $v .= (string)$rr->t; }
                }
            } else {
                $v = isset($c->v) ? (string)$c->v : '';
            }
            if ($type_s === 's') {
                $vi = (int)trim($v);
                $v = isset($strings[$vi]) ? $strings[$vi] : '';
            }
            $row_map[$col_idx] = xl_norm_text($v);
        }
        if (array_filter($row_map, function ($x) { return $x !== ''; })) {
            $pending[] = $row_map;
            if ($row_map) $max_col = max($max_col, max(array_keys($row_map)));
        }
    }
    $out = array();
    foreach ($pending as $row_map) {
        $row = array();
        for ($ci = 0; $ci <= $max_col; $ci++) $row[$ci] = isset($row_map[$ci]) ? $row_map[$ci] : '';
        $out[] = $row;
    }
    return $out;
}

// یافتن فایل کاربرگه اول از داخل بسته OOXML با ترتیب واقعی workbook.xml
function xl_first_sheet_xml(ZipArchive $zip) {
    // روش دقیق: workbook.xml -> rels -> مسیر فیزیکی اولین شیت
    $wb_xml = $zip->getFromName('xl/workbook.xml');
    $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb_xml !== false && $rels_xml !== false && $wb_xml !== '' && $rels_xml !== '' && class_exists('DOMDocument')) {
        $rid_map = array();
        $rd = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        if ($rd->loadXML($rels_xml)) {
            foreach ($rd->getElementsByTagName('Relationship') as $rel) {
                if (stripos($rel->getAttribute('Type'), '/worksheet') !== false) {
                    $rid_map[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                }
            }
        }
        $wd = new DOMDocument;
        if ($wd->loadXML($wb_xml)) {
            $sheets = $wd->getElementsByTagName('sheet');
            if ($sheets->length > 0) {
                $rid = '';
                foreach ($sheets->item(0)->attributes as $at) {
                    if ($at->localName === 'id') { $rid = $at->value; break; }
                }
                if ($rid !== '' && isset($rid_map[$rid])) {
                    $target = ltrim($rid_map[$rid], '/');
                    if (strpos($target, 'xl/') !== 0) $target = 'xl/' . $target;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        if (strcasecmp($zip->getNameIndex($i), $target) === 0) return $zip->getNameIndex($i);
                    }
                }
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
    }
    // روش ذخیره: sheet1.xml یا اولین worksheet موجود
    if (($idx = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) return $zip->getNameIndex($idx);
    $names = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nm = $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $nm)) $names[] = $nm;
    }
    if (!$names) return false;
    sort($names, SORT_STRING);
    return $names[0];
}

function parse_excel($file) {
    $rows = array();
    $sig = @file_get_contents($file, false, null, 0, 8);
    $is_zip = (strncmp((string)$sig, "PK\x03\x04", 4) === 0);
    $is_ole = (strncmp((string)$sig, "\xD0\xCF\x11\xE0", 4) === 0);
    $orig_name = '';
    if (isset($_FILES['f']['name'])) $orig_name = $_FILES['f']['name'];
    elseif (isset($_FILES['file']['name'])) $orig_name = $_FILES['file']['name'];
    $ext = strtolower(pathinfo((string)($orig_name !== '' ? $orig_name : $file), PATHINFO_EXTENSION));
    $looks_xls = ($is_ole || $ext === 'xls');

    if ($is_zip && class_exists('ZipArchive')) {
        $zip = new ZipArchive;
        if ($zip->open($file) === TRUE) {
            $strings = xl_read_shared_strings($zip->getFromName('xl/sharedStrings.xml'));
            $sheet_name = xl_first_sheet_xml($zip);
            $xml = $sheet_name !== false ? $zip->getFromName($sheet_name) : false;
            if ($xml !== false && $xml !== '') {
                // روش ۱: SimpleXML (همان روش نسخه تک‌فایل قدیمی؛ در برابر XML بزرگ پایدارتر)
                $sx = @simplexml_load_string($xml);
                if ($sx !== false && isset($sx->sheetData->row)) {
                    $rows = xl_rows_from_sxml($sx, $strings);
                }
                // روش ۲: پارس regex برای فایل‌هایی با namespace پیچیده یا ساختار خاص
                if (!$rows && preg_match_all('#<row\b[^>]*r="(\d+)"[^>]*>(.*?)</row>#s', $xml, $rm_all, PREG_SET_ORDER)) {
                    $pending_rows = array();
                    $global_max_col = 0;
                    foreach ($rm_all as $rm_set) {
                        $row_map = xl_parse_row_cells($rm_set[2], $strings);
                        if (array_filter($row_map, function ($x) { return $x !== ''; })) {
                            $pending_rows[] = $row_map;
                            $global_max_col = max($global_max_col, max(array_keys($row_map)));
                        }
                    }
                    foreach ($pending_rows as $row_map) {
                        $row = array();
                        for ($ci = 0; $ci <= $global_max_col; $ci++) $row[$ci] = isset($row_map[$ci]) ? $row_map[$ci] : '';
                        $rows[] = $row;
                    }
                }
            }
            $zip->close();
            if ($rows) return $rows;
            if ($looks_xls) {
                // xlsx جعلی با پسوند xls؟ دوباره امتياز نمی‌دهیم — داده‌ای نبود
                return array();
            }
            return array();
        }
    }

    if ($looks_xls) {
        // اگر فایل با پسوند .xls در واقع HTML (خروجی قدیمی سامانه) باشد، از مسیر HTML خوانده شود
        $head_sniff = @file_get_contents($file, false, null, 0, 512);
        $is_html_like = ((bool)preg_match('/^\s*(\xEF\xBB\xBF)?\s*<(!doctype|html|table|workbook|\?xml)/i', (string)$head_sniff));
        if (!$is_html_like) {
            // فرمت باینری قدیمی .xls (OLE2) — از خواندن آن به‌عنوان CSV/HTML خودداری شود تا داده‌ها بهم نریزد
            return array();
        }
    }

    $raw_content = @file_get_contents($file);
    // تشخیص فایل HTML/XML اکسل جعلی (xls وب یا خروجی قدیمی سامانه)
    if ($raw_content !== false && preg_match('/^\s*(\xEF\xBB\xBF)?\s*<(!doctype|html|table|\?xml|Workbook)/i', $raw_content)) {
        if (stripos($raw_content, '<table') !== false && preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $raw_content, $tr_m)) {
            foreach ($tr_m[1] as $tr_html) {
                if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr_html, $td_m)) {
                    $row = array();
                    foreach ($td_m[1] as $cell) {
                        $row[] = xl_norm_text(xl_decode_entities(strip_tags($cell)));
                    }
                    if (array_filter($row)) $rows[] = $row;
                }
            }
            if ($rows) return $rows;
        }
        // SpreadsheetML 2003 (.xml)
        if (stripos($raw_content, 'urn:schemas-microsoft-com:office:spreadsheet') !== false && preg_match_all('#<Row\b[^>]*>(.*?)</Row>#s', $raw_content, $rm2)) {
            foreach ($rm2[1] as $rin) {
                $row = array();
                if (preg_match_all('#<Cell\b[^>]*>(.*?)</Cell>#s', $rin, $cl)) {
                    foreach ($cl[1] as $cin) {
                        $tv = '';
                        if (preg_match('#<Data\b[^>]*>(.*?)</Data>#s', $cin, $dm)) $tv = $dm[1];
                        $row[] = xl_norm_text(xl_decode_entities($tv));
                    }
                }
                if (array_filter($row)) $rows[] = $row;
            }
            if ($rows) return $rows;
        }
        return array();
    }

    if (($fp = fopen($file, 'r')) !== false) {
        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fp);
        $all_lines = array();
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line, "\r\n");
            if (trim($line) === '') continue;
            $all_lines[] = $line;
        }
        fclose($fp);
        if ($all_lines) {
            // تعیین جداکننده با شمارش روی چند خط اول (نه هر خط مستقل)
            $probe = implode("\n", array_slice($all_lines, 0, 10));
            $cnt_tab = substr_count($probe, "\t");
            $cnt_semi = substr_count($probe, ';');
            $cnt_comma = substr_count($probe, ',');
            $delim = ',';
            if ($cnt_tab > $cnt_comma && $cnt_tab >= $cnt_semi) $delim = "\t";
            elseif ($cnt_semi > $cnt_comma) $delim = ';';
            $max_d = 0; $first = true;
            foreach ($all_lines as $line) {
                $d = str_getcsv($line, $delim);
                $d = array_map(function($x){ return xl_norm_text($x); }, $d);
                if (!array_filter($d, function($x){ return $x !== ''; })) continue;
                if ($first) { $max_d = count($d); $first = false; }
                else $max_d = max($max_d, count($d));
                $rows[] = $d;
            }
            // یکدست‌سازی پهنای ردیف‌ها تا آخر ردیف پر نشود و داده جابه‌جا نگردد
            foreach ($rows as $ri => $rv) $rows[$ri] = array_pad($rv, $max_d, '');
        }
    }
    return $rows;
}

function output_excel_xls($filename, $headers, $data_rows) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40" dir="rtl"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><style>
    table{border-collapse:collapse;direction:rtl;font-family:Tahoma,sans-serif;font-size:13px}
    th{background:#16336f;color:#ffffff;border:1px solid #999;padding:8px;font-weight:bold;text-align:center}
    td{border:1px solid #bbb;padding:6px;text-align:right;mso-number-format:"\@"}
    </style></head><body><table border="1"><thead><tr>';
    foreach ($headers as $th) {
        echo '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($data_rows as $r) {
        echo '<tr>';
        foreach ($r as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}


function parse_excel_to_preview_records($file, $loc_name, $ws_key = 'book') {
    global $F, $RTypes;
    $rows = parse_excel($file);
    if (!$rows) return array();
    $cols = array_keys($F);
    $ws_fields = get_worksheet_active_fields($loc_name, $ws_key);
    $cfg = get_lib_config($loc_name);
    $default_rtype = isset($cfg['worksheets'][$ws_key]['rtype']) ? $cfg['worksheets'][$ws_key]['rtype'] : 'book';
    $rt_map = array('کتاب'=>'book', 'مقاله'=>'article', 'پایان‌نامه'=>'thesis', 'پایان نامه'=>'thesis', 'نقشه'=>'other', 'سایر'=>'other', 'سایر منابع'=>'other', 'book'=>'book', 'article'=>'article', 'thesis'=>'thesis', 'other'=>'other');

    // تشخیص سرستون‌ها: اولین ردیفِ دارای حداقل ۲ سلول غیرخالی و دست‌کم یک برچسب شناخته‌شده
    $header_map = array(); // col_index => field_key ('f010', ..., 'rtype', 'copies', 'reg_no', 'loc')
    $is_header = false;
    $header_row_idx = -1;
    $first_row = $rows[0];

    // نگاشت محلی «متن سرستون => کلید فیلد» — ترتیب شرط‌ها از اختصاصی به عام است
    $match_header_cell = function ($cv) use ($ws_fields, $cols) {
        if ($cv === '') return null;
        $cv_en = strtolower($cv);
        // کدهای اختصاصی MARC با زیرفیلد
        if (preg_match('/200\s*[\$٤]?\s*a/iu', $cv) || mb_strpos($cv, 'عنوان اصلی') !== false || $cv === 'عنوان' || mb_strpos($cv_en, 'title') !== false) return 'f200a';
        if (preg_match('/200\s*[\$٤]?\s*e/iu', $cv) || mb_strpos($cv, 'عنوان فرعی') !== false || mb_strpos($cv, 'عنوان موازی') !== false) return 'f200e';
        if (preg_match('/200\s*[\$٤]?\s*f/iu', $cv) || mb_strpos($cv, 'نخستین پدیدآور') !== false || mb_strpos($cv, 'مسئولیت') !== false) return 'f200f';
        if (preg_match('/210\s*[\$٤]?\s*a/iu', $cv) || mb_strpos($cv, 'محل نشر') !== false || mb_strpos($cv, 'شهر نشر') !== false) return 'f210a';
        if (preg_match('/210\s*[\$٤]?\s*c/iu', $cv) || mb_strpos($cv, 'نام ناشر') !== false || mb_strpos($cv, 'ناشر') !== false) return 'f210c';
        if (preg_match('/210\s*[\$٤]?\s*d/iu', $cv) || mb_strpos($cv, 'سال نشر') !== false || mb_strpos($cv, 'تاریخ نشر') !== false) return 'f210d';
        // ستون‌های جانبی سامانه (قبل از کدهای عمومی بررسی شوند)
        if (mb_strpos($cv, 'نوع منبع') !== false || mb_strpos($cv_en, 'rtype') !== false || mb_strpos($cv_en, 'type of resource') !== false) return 'rtype';
        if (mb_strpos($cv, 'تعداد جلد') !== false || mb_strpos($cv, 'تعداد نسخه') !== false || mb_strpos($cv_en, 'copies') !== false) return 'copies';
        if (mb_strpos($cv, 'شماره ثبت') !== false || mb_strpos($cv_en, 'reg') !== false) return 'reg_no';
        if ($cv === 'کتابخانه' || mb_strpos($cv, 'نام کتابخانه') !== false) return 'loc';
        // تطابق با کد/برچسب سفارشی کاربرگه (کاربر می‌تواند هر نام دلخواه بگذارد)
        foreach ($ws_fields as $fk => $fd) {
            $fd_code = trim((string)$fd['code']);
            $fd_label = trim((string)$fd['label']);
            if ($fd_code !== '' && (stripos($cv_en, strtolower(xl_norm_digits($fd_code))) !== false || stripos(strtolower(xl_norm_digits($fd_code)), $cv_en) !== false)) return $fk;
            if ($fd_label !== '' && (mb_strpos($cv, $fd_label) !== false || mb_strpos($fd_label, $cv) !== false)) return $fk;
        }
        // کدهای عمومی MARC (ترتیب مهم: 010 قبل از 606/700 ... نه؛ هر الگوی عددی سه‌رقمی دقیق)
        if (preg_match('/\b010\b/', $cv_en) || mb_strpos($cv, 'شابک') !== false || mb_strpos($cv, 'شاپا') !== false || mb_strpos($cv_en, 'isbn') !== false || mb_strpos($cv_en, 'issn') !== false) return 'f010';
        if (preg_match('/\b101\b/', $cv_en) || mb_strpos($cv, 'زبان') !== false) return 'f101';
        if (preg_match('/\b(205|206)\b/', $cv_en) || mb_strpos($cv, 'ویراست') !== false || mb_strpos($cv, 'مقیاس') !== false) return 'f205';
        if (preg_match('/\b215\b/', $cv_en) || mb_strpos($cv, 'تعداد صفحات') !== false || mb_strpos($cv, 'مشخصات ظاهری') !== false || mb_strpos($cv, 'ابعاد') !== false) return 'f215';
        if (preg_match('/\b225\b/', $cv_en) || mb_strpos($cv, 'فروست') !== false || mb_strpos($cv, 'عنوان مجموعه') !== false) return 'f225';
        if (preg_match('/\b300\b/', $cv_en) || mb_strpos($cv, 'یادداشت') !== false || mb_strpos($cv, 'کتابنامه') !== false) return 'f300';
        if (preg_match('/\b606\b/', $cv_en) || mb_strpos($cv, 'موضوع') !== false) return 'f606';
        if (preg_match('/\b676\b/', $cv_en) || mb_strpos($cv, 'دیویی') !== false) return 'f676';
        if (preg_match('/\b(680|686)\b/', $cv_en) || mb_strpos($cv, 'کنگره') !== false) return 'f686';
        if (preg_match('/\b(700|701)\b/', $cv_en) || mb_strpos($cv, 'پدیدآوران شخصی') !== false || mb_strpos($cv, 'پدیدآور شخصی') !== false || mb_strpos($cv, 'پدیدآور') !== false || mb_strpos($cv_en, 'author') !== false) return 'f700';
        if (preg_match('/\b710\b/', $cv_en) || mb_strpos($cv, 'سازمانی') !== false) return 'f710';
        return null;
    };

    $scan_limit = min(count($rows), 5);
    for ($h_r_idx = 0; $h_r_idx < $scan_limit; $h_r_idx++) {
        $h_row = $rows[$h_r_idx];
        list($has_label, $non_empty_cnt) = xl_header_score_row($h_row, $ws_fields);
        if ($non_empty_cnt < 2) continue; // احتمالاً ردیف داده تک‌ستونه یا خالی است
        $try_map = array();
        $detected = false;
        foreach ($h_row as $c_idx => $cell_val) {
            $cv = xl_norm_digits(xl_norm_text($cell_val));
            $matched = $match_header_cell($cv);
            if ($matched !== null) { $try_map[$c_idx] = $matched; $detected = true; }
        }
        if ($detected) {
            // اگر ردیف‌های بعدی هم همین شکل سرستون را دارند، یعنی این واقعاً سرستون است (نه داده)
            $header_map = $try_map;
            $is_header = true;
            $header_row_idx = $h_r_idx;
            break;
        }
        if (!$has_label && count($rows) > 1 && $h_r_idx === 0) {
            // ردیف اول شبیه داده است؛ ردیف دوم را هم امتحان نکن اگر داده بود، حالت بدون سرستون فعال شود
            $second = isset($rows[1]) ? xl_header_score_row($rows[1], $ws_fields) : array(false, 0);
            if (!$second[0]) break;
        }
    }

    // اگر هیچ ردیفی سرستون نبود، نگاشت ترتیبی بر اساس فیلدهای کاربرگه (یا استاندارد ۱۷ فیلدی)
    if (!$is_header) {
        $seq_keys = array_keys($ws_fields);
        if (count($first_row) >= 17) $seq_keys = $cols;
        foreach ($seq_keys as $idx_k => $fk) {
            $header_map[$idx_k] = $fk;
        }
        $n_base = count($seq_keys);
        $header_map[$n_base]     = 'rtype';
        $header_map[$n_base + 1] = 'copies';
        $header_map[$n_base + 2] = 'reg_no';
    } else {
        // تکمیل هوشمند: اگر ستونی در سرستون شناسایی نشد اما داده آن ستون مشخصاً «تاریخ اکسل» یا رشته فارسی است، رها کن
        // ولی اگر عنوان اصلی هیچ ستونی پیدا نکرد و فایل دقیقاً به ترتیب فیلدهای کاربرگه ساخته شده بود، ترتیب پیش‌فرض را اعمال کن
        $mapped_targets = array_values($header_map);
        $need_fallback = !in_array('f200a', $mapped_targets, true);
        if ($need_fallback) {
            $seq_keys = array_keys($ws_fields);
            if (count($first_row) >= 17) $seq_keys = $cols;
            $unmapped_cols = array();
            foreach (array_keys($ws_fields) as $fk) if (!in_array($fk, $mapped_targets, true)) $unmapped_cols[] = $fk;
            // اگر بیش از نیمی از فیلدهای کاربرگه در سرستون نگاشت نشده بودند، احتمالاً فایل با ترتیب پیش‌فرض ساخته شده است
            if (count($unmapped_cols) > count($ws_fields) / 2) {
                $header_map = array();
                foreach ($seq_keys as $idx_k => $fk) $header_map[$idx_k] = $fk;
                $n_base = count($seq_keys);
                $header_map[$n_base]     = 'rtype';
                $header_map[$n_base + 1] = 'copies';
                $header_map[$n_base + 2] = 'reg_no';
            }
        }
    }

    $parsed = array();
    foreach ($rows as $r_idx => $r) {
        if ($is_header && $r_idx <= $header_row_idx) continue; // ردیف‌های قبل/روی سرستون رد شوند
        $d = array();
        foreach ($cols as $c) $d[$c] = '';
        $d['rtype']  = $default_rtype;
        $d['copies'] = 1;
        $d['reg_no'] = '';

        foreach ($header_map as $col_i => $target_key) {
            $val = isset($r[$col_i]) ? xl_norm_text($r[$col_i]) : '';
            if ($target_key === 'rtype') {
                if ($val !== '' && isset($rt_map[$val])) $d['rtype'] = $rt_map[$val];
            } elseif ($target_key === 'copies') {
                if ($val !== '') $d['copies'] = max(1, min(5000, (int)en_num($val)));
            } elseif ($target_key === 'reg_no') {
                $d['reg_no'] = trim(en_num($val));
            } elseif ($target_key === 'loc') {
                // نادیده گرفتن ستون کتابخانه در اکسل در صورت انتخاب کتابخانه در فرم ورودی
            } elseif (isset($d[$target_key])) {
                $d[$target_key] = $val;
            }
        }
        if ($d['f101'] === '') $d['f101'] = 'per';
        if ($d['f200a'] === '') continue;
        $parsed[] = $d;
    }
    return $parsed;
}