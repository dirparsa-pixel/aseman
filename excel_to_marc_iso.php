<?php
/**
 * excel_to_marc_iso.php
 * ---------------------------------------------------------------------------
 * تبدیل داده‌های اکسل (‎.xlsx / .xls HTML / .csv) به فرمت مارک و فایل ایزو
 * ISO 2709 (ساختار MARC) + خروجی MARCXML و MARC21 متنی (readable)
 *
 * This script is intentionally dependency-free: it uses only the PHP core
 * extensions (ZipArchive, DOMDocument, SimpleXML, mbstring). If Composer and
 * ext-mbstring are unavailable it still runs in a limited ASCII mode.
 *
 * Usage (CLI):
 *   php excel_to_marc_iso.php <input.xlsx|input.xls|input.csv> [output_prefix] [--enc=UTF-8]
 *
 * Outputs produced next to input directory (default prefix = input basename):
 *   <prefix>.iso      - binary ISO 2709 records (MARC packets), one after another
 *   <prefix>.mrk      - MARC-MRK plain text format
 *   <prefix>.xml      - MARCXML (ISO 25577)
 *   <prefix>.txt      - human-readable MARC21 with Persian labels
 *
 * Column mapping for library book lists (override with --map=a,b,c,... or edit $MAP):
 *   A => عنوان (245$a)        B => پدیدآور/مسئولیت (200$c)
 *   C => موضوع (606$a)        D => تعداد جلد/شماره جلد (950$a)
 *   E => ناشر (210$b)         F => سال نشر (210$d)
 *   G => دوره (951$a)         H => شابک/ISBN (010$a)    I => یادداشت (500$a)
 *
 * Notes on MARC fields used (UNIMARC-flavoured tags common in Iranian
 * library automation, e.g. Noor/Parsbook style; adjust as needed):
 *   010 ISBN | 200 Title&Responsibility | 210 Publisher&Date | 606 Subject
 *   950 Volume info (local) | 951 Course term (local)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ======================= CONFIG ========================================= */

// Default column -> field mapping (0-based index into spreadsheet columns)
$DEFAULT_MAP = array(
    0  => array('tag' => '200', 'code' => 'a', 'label' => 'عنوان اصلی'),
    1  => array('tag' => '200', 'code' => 'c', 'label' => 'پدیدآور / مسئولیت'),
    2  => array('tag' => '606', 'code' => 'a', 'label' => 'موضوع'),
    3  => array('tag' => '950', 'code' => 'a', 'label' => 'تعداد جلد / شماره جلد'),
    4  => array('tag' => '210', 'code' => 'b', 'label' => 'ناشر'),
    5  => array('tag' => '210', 'code' => 'd', 'label' => 'سال نشر'),
    6  => array('tag' => '951', 'code' => 'a', 'label' => 'دوره'),
    7  => array('tag' => '010', 'code' => 'a', 'label' => 'ISBN'),
    8  => array('tag' => '500', 'code' => 'a', 'label' => 'یادداشت'),
);

// Subfield separator (ASCII 0x1F) & record terminator (0x1D) & field sep (0x1E)
define('SF_SEP', "\x1F");
define('REC_END', "\x1D");
define('FLD_SEP', "\x1E");

/* ======================= CLI PARSE ====================================== */

$args   = array_slice($argv, 1);
$input  = null;
$prefix = null;
$map    = $DEFAULT_MAP;
$enc    = 'UTF-8';

foreach ($args as $a) {
    if (strpos($a, '--map=') === 0) {
        // --map=0:245a,1:700a,...  (colIdx:tagCode pairs)
        $map = array();
        foreach (explode(',', substr($a, 6)) as $pair) {
            list($idx, $tc) = explode(':', $pair);
            $map[(int)$idx] = array('tag' => substr($tc, 0, 3), 'code' => substr($tc, 3), 'label' => '');
        }
    } elseif (strpos($a, '--enc=') === 0) {
        $enc = substr($a, 6);
    } elseif ($input === null) {
        $input = $a;
    } else {
        $prefix = $a;
    }
}

if (!$input || !is_file($input)) {
    fwrite(STDERR, "Usage: php excel_to_marc_iso.php <file.xlsx|file.xls|file.csv> [out_prefix] [--map=0:200a,1:200c,...] [--enc=UTF-8]\n");
    exit(1);
}
if ($prefix === null) {
    $prefix = pathinfo($input, PATHINFO_DIRNAME) . '/' . pathinfo($input, PATHINFO_FILENAME);
}
if (!function_exists('mb_convert_encoding')) {
    fwrite(STDERR, "Warning: mbstring not available; assuming inputs already in {$enc}.\n");
}

/* ======================= EXCEL READING ================================== */

/**
 * Read any supported spreadsheet and return array of rows (array of strings).
 */
function readSpreadsheet($path, $enc)
{
    $head = file_get_contents($path, false, null, 0, 8);
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    // Real XLSX = ZIP package (PK\x03\x04)
    if (strncmp($head, "PK\x03\x04", 4) === 0 || $ext === 'xlsx') {
        return readXlsx($path);
    }
    // CSV
    if ($ext === 'csv' || strpos($head, '<') === false) {
        return readCsv($path, $enc);
    }
    // Excel "HTML" .xls (as produced by many Iranian library tools)
    return readHtmlXls($path, $enc);
}

function normalizeText($s, $enc)
{
    $s = (string)$s;
    if (function_exists('mb_convert_encoding')) {
        $det = mb_detect_encoding($s, array('UTF-8', 'ISO-8859-2', 'ISO-8859-1'), true);
        if ($det && strtoupper($det) !== 'UTF-8') {
            $s = @iconv('Windows-1256', 'UTF-8//TRANSLIT', $s); // Arabic/Persian legacy xls
        }
    }
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);           // NBSP
    $s = preg_replace('/\s+/u', ' ', trim($s));       // collapse whitespace
    return $s;
}

function readCsv($path, $enc)
{
    $rows = array();
    if (($h = fopen($path, 'r')) !== false) {
        while (($data = fgetcsv($h, 0, ',', '"')) !== false) {
            $row = array();
            foreach ($data as $c) $row[] = normalizeText($c, $enc);
            $rows[] = $row;
        }
        fclose($h);
    }
    return $rows;
}

function readHtmlXls($path, $enc)
{
    $html = file_get_contents($path);
    $dom  = new DOMDocument();
    libxml_use_internal_errors(true);
    // wrap with encoding meta so DOM parses UTF-8 bytes correctly
    $html = preg_replace('/^\xEF\xBB\xBF/', '', $html);
    @$dom->loadXML('<?xml encoding="UTF-8"?>' . $html);
    if ($dom->getElementsByTagName('table')->length === 0) {
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    }
    libxml_clear_errors();
    $rows = array();
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $row = array();
        foreach ($tr->getElementsByTagName('td') as $td) {
            $row[] = normalizeText($td->textContent, $enc);
        }
        if (!$row) {
            foreach ($tr->getElementsByTagName('th') as $th) {
                $row[] = normalizeText($th->textContent, $enc);
            }
        }
        if ($row) $rows[] = $row;
    }
    return $rows;
}

function colLetterToIndex($ref)
{
    $letters = preg_replace('/\d+/', '', $ref);
    $idx = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $idx = $idx * 26 + (ord(strtoupper($letters[$i])) - 64);
    }
    return $idx - 1;
}

function readXlsx($path)
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new Exception("Cannot open xlsx: $path");

    // shared strings
    $shared = array();
    if (($si = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $xml = $zip->getFromIndex($si);
        if (preg_match_all('#<si>(.*?)</si>#s', $xml, $m)) {
            foreach ($m[1] as $siContent) {
                $t = preg_replace('#<[^>]+>#', '', $siContent);
                $shared[] = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }

    // first worksheet
    $sheetIdx = $zip->locateName('xl/worksheets/sheet1.xml');
    if ($sheetIdx === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#xl/worksheets/sheet\d+\.xml#', $zip->getNameIndex($i))) { $sheetIdx = $i; break; }
        }
    }
    if ($sheetIdx === false) { $zip->close(); throw new Exception('No worksheet found'); }
    $sheetXml = $zip->getFromIndex($sheetIdx);
    $zip->close();

    $rows = array();
    if (preg_match_all('#<row[^>]*>(.*?)</row>#s', $sheetXml, $rm)) {
        foreach ($rm[1] as $rowXml) {
            $cells = array();
            if (preg_match_all('#<c\s+([^>]*?)(/>|>(.*?)</c>)#s', $rowXml, $cm)) {
                foreach ($cm[1] as $k => $attrs) {
                    $ref = ''; $type = '';
                    if (preg_match('/r="([A-Z]+\d+)"/', $attrs, $a)) $ref = $a[1];
                    if (preg_match('/t="(\w+)"/', $attrs, $a))       $type = $a[1];
                    $inner = isset($cm[3][$k]) ? $cm[3][$k] : '';
                    $val = '';
                    if ($type === 'inlineStr') {
                        if (preg_match('#<t[^>]*>(.*?)</t>#s', $inner, $v)) $val = $v[1];
                    } elseif (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                        $val = $v[1];
                        if ($type === 's') $val = isset($shared[(int)$val]) ? $shared[(int)$val] : '';
                    }
                    $idx = $ref !== '' ? colLetterToIndex($ref) : count($cells);
                    $cells[$idx] = normalizeText($val, 'UTF-8');
                }
            }
            $maxIdx = $cells ? max(array_keys($cells)) : -1;
            $row = array();
            for ($i = 0; $i <= $maxIdx; $i++) $row[] = isset($cells[$i]) ? $cells[$i] : '';
            $rows[] = $row;
        }
    }
    return $rows;
}

/* ======================= MARC BUILDERS ================================== */

/**
 * Build one logical MARC record from row data using $map.
 * Returns array: ['fields' => [ [tag, ind1, ind2, [[code,value],...] ], ... ] ]
 * Groups subfields belonging to same tag (e.g. 200$a + 200$c).
 */
function buildMarcRecord($row, $map, $recno)
{
    // collect subfields per tag preserving order of map keys
    $byTag = array();
    ksort($map);
    foreach ($map as $col => $def) {
        if (!isset($row[$col]) || $row[$col] === '') continue;
        $tag = $def['tag'];
        if (!isset($byTag[$tag])) $byTag[$tag] = array();
        $byTag[$tag][] = array($def['code'], $row[$col]);
    }
    if (!$byTag) return null;

    // Control field 001 (record id); data fields sorted by standard order
    $fields = array();
    $fields[] = array('001', '', '', array(array('', sprintf('%05d', $recno))));
    $order = array('010', '200', '210', '500', '606', '950', '951');
    $tagsSorted = array_keys($byTag);
    usort($tagsSorted, function ($a, $b) use ($order) {
        $ia = array_search($a, $order); $ib = array_search($b, $order);
        $ia = $ia === false ? 99 : $ia; $ib = $ib === false ? 99 : $ib;
        return $ia - $ib;
    });
    foreach ($tagsSorted as $tag) {
        $ind1 = ($tag === '200') ? '0' : ' ';
        $ind2 = ' ';
        $fields[] = array($tag, $ind1, $ind2, $byTag[$tag]);
    }
    return $fields;
}

/** Serialise fields to ISO 2709 binary record (UTF-8 encoded). */
function iso2709Record($fields)
{
    $body = '';
    $directory = '';
    foreach ($fields as $f) {
        list($tag, $i1, $i2, $subs) = $f;
        // directory entry length = 3(tag)+4(fieldlen)+5(fieldstart) = 12 chars
        $fd = '';
        if ($tag === '001' || $tag === '00') {          // control field
            $fd = $subs[0][1];
        } else {
            $fd = $i1 . $i2;
            foreach ($subs as $s) $fd .= SF_SEP . $s[0] . $s[1];
        }
        $fd .= REC_END;
        $pos = strlen($body);
        $body .= $fd;
        $directory .= sprintf('%s%04d%05d', $tag, strlen($fd), $pos);
    }
    $len         = 24 + strlen($directory) + 1 + strlen($body); // full record length
    $baseAddress = 24 + strlen($directory) + 1; // leader + directory + field-terminator
    // Proper 24-char ISO 2709 leader:
    // 00-04 length, 05 status 'm', 06 type 'a', 07-11 blanks,
    // 12-16 base address, 17-19 encoding levels, 20-23 reserved lengths
    $leader = sprintf('%05dm a     %05d  4500 ', $len, $baseAddress);
    $leader = substr(str_pad($leader, 24, ' '), 0, 24);
    return $leader . $directory . REC_END . $body;
}

/** MRK (human-readable marc) line for one record */
function mrkRecord($fields)
{
    $out = "=LDR  " . substr(iso2709Record($fields), 0, 24) . "\n";
    foreach ($fields as $f) {
        list($tag, $i1, $i2, $subs) = $f;
        if ($tag === '001') { $out .= "=001  " . $subs[0][1] . "\n"; continue; }
        $line = "=$tag  $i1$i2";
        foreach ($subs as $s) $line .= " \x1F" . $s[0] . $s[1];
        $out .= $line . "\n";
    }
    return $out . "\n";
}

/** MARCXML for one record */
function marcxmlRecord($fields)
{
    $esc = function ($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
    $xml = "  <record>\n    <controlfield tag=\"001\">" . $esc($fields[0][3][0][1]) . "</controlfield>\n";
    foreach (array_slice($fields, 1) as $f) {
        list($tag, $i1, $i2, $subs) = $f;
        $xml .= "    <datafield tag=\"$tag\" ind1=\"" . $esc($i1) . "\" ind2=\"" . $esc($i2) . "\">\n";
        foreach ($subs as $s) {
            $xml .= "      <subfield code=\"" . $esc($s[0]) . "\">" . $esc($s[1]) . "</subfield>\n";
        }
        $xml .= "    </datafield>\n";
    }
    $xml .= "  </record>\n";
    return $xml;
}

/** Pretty MARC21 text with Persian labels */
function prettyRecord($fields, $map) {
    $labels = array();
    foreach ($map as $def) $labels[$def['tag'] . '$' . $def['code']] = $def['label'];
    $out = '';
    foreach ($fields as $f) {
        list($tag, $i1, $i2, $subs) = $f;
        foreach ($subs as $s) {
            $key = $tag . '$' . $s[0];
            $lab = isset($labels[$key]) && $labels[$key] !== '' ? $labels[$key] : $key;
            $out .= sprintf("%-28s: %s\n", $lab, $s[1]);
        }
    }
    return $out . "-" . str_repeat("-", 60) . "\n";
}

/* ======================= MAIN =========================================== */

try {
    $rows = readSpreadsheet($input, $enc);
    if (!$rows) throw new Exception("No rows parsed from $input");

    // Skip header row if it contains known header words
    $first = implode('|', $rows[0]);
    $start = 0;
    if (preg_match('/عنوان|نام|ناشر|موضوع|Title|Author/iu', $first)) $start = 1;

    $iso = ''; $mrk = ''; $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<collection xmlns=\"http://www.loc.gov/MARC21/slim\">\n"; $txt = '';
    $recno = 0; $count = 0;
    for ($i = $start; $i < count($rows); $i++) {
        $fields = buildMarcRecord($rows[$i], $map, ++$recno);
        if (!$fields) continue;
        $count++;
        $iso  .= iso2709Record($fields);
        $mrk  .= mrkRecord($fields);
        $xml  .= marcxmlRecord($fields);
        $txt  .= prettyRecord($fields, $map);
    }
    $xml .= "</collection>\n";

    file_put_contents("$prefix.iso", $iso);
    file_put_contents("$prefix.mrk", $mrk);
    file_put_contents("$prefix.xml", $xml);
    file_put_contents("$prefix.txt", $txt);

    echo "OK: $count MARC/ISO records written:\n";
    echo "  $prefix.iso  (ISO 2709 binary)\n";
    echo "  $prefix.mrk  (MARC-MRK)\n";
    echo "  $prefix.xml  (MARCXML)\n";
    echo "  $prefix.txt  (readable MARC21)\n";
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(2);
}
