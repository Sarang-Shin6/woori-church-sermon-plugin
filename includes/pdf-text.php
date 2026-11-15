<?php
/** Small PDF text extractor for Woori's Microsoft Print to PDF bulletins.
 * Supports classic indirect objects, FlateDecode content streams, Type0
 * Identity-H fonts with ToUnicode bfchar/bfrange maps and TJ/Tj text.
 * Does not execute external commands, parse scanned PDFs, or support all PDFs.
 */
if (!defined('ABSPATH') && !defined('WOORI_PDF_STANDALONE_TEST')) exit;
function woori_pdf_object_stream($object) {
    if (!preg_match('/\bstream\r?\n/', $object, $m, PREG_OFFSET_CAPTURE)) return '';
    $start = $m[0][1] + strlen($m[0][0]);
    if (!preg_match('/\/Length\s+(\d+)\b/', substr($object, 0, $start), $l)) return '';
    $length = (int)$l[1];
    if ($length < 0 || $length > 6000000 || $start + $length > strlen($object)) return '';
    $data = substr($object, $start, $length);
    if (strpos(substr($object,0,$start), '/FlateDecode') !== false) {
        $data = @gzuncompress($data);
        if ($data === false) return '';
    }
    return $data;
}
function woori_pdf_utf16($hex) {
    $raw = @hex2bin($hex);
    if ($raw === false) return '';
    return function_exists('iconv') ? (string)@iconv('UTF-16BE', 'UTF-8//IGNORE', $raw) : '';
}
function woori_pdf_cmap($data) {
    $map = array();
    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $data, $blocks)) {
        foreach ($blocks[1] as $block) if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) $map[strtoupper($pair[1])] = woori_pdf_utf16($pair[2]);
        }
    }
    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $data, $blocks)) {
        foreach ($blocks[1] as $block) if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $a=hexdec($pair[1]);$b=hexdec($pair[2]);$v=hexdec($pair[3]);
                if ($b-$a > 1024) continue;
                for($i=$a;$i<=$b;$i++) $map[strtoupper(sprintf('%0'.strlen($pair[1]).'X',$i))] = woori_pdf_utf16(sprintf('%0'.strlen($pair[3]).'X',$v+$i-$a));
            }
        }
    }
    return $map;
}
function woori_pdf_decode_hex($hex, $map) {
    $hex = strtoupper(preg_replace('/\s+/', '', $hex)); $out='';
    for ($i=0;$i+3<strlen($hex);$i+=4) $out .= $map[substr($hex,$i,4)] ?? '';
    return $out;
}
function woori_pdf_extract_text($filename) {
    $raw=@file_get_contents($filename);
    if ($raw===false || strlen($raw)>20*1024*1024 || substr($raw,0,5)!=='%PDF-') return '';
    $objects=array();
    if (!preg_match_all('/(?:^|\n)(\d+) [0-9]+ obj\r?\n/m', $raw, $matches, PREG_OFFSET_CAPTURE)) return '';
    foreach($matches[0] as $i=>$match) {
        $start=$match[1]+strlen($match[0]);
        $end=strpos($raw, 'endobj', $start);
        if($end===false) continue;
        $objects[(int)$matches[1][$i][0]]=substr($raw,$start,$end-$start);
    }
    $pages=array();
    foreach($objects as $num=>$obj) if(preg_match('/\/Type\s*\/Page\b/', $obj) && !preg_match('/\/Type\s*\/Pages\b/', $obj)) $pages[$num]=$obj;
    if(count($pages)<1 || count($pages)>10) return '';
    $all=''; $pageNumber=0;
    foreach($pages as $page) {
        if(++$pageNumber>2) break;
        if(!preg_match('/\/Resources\s+(\d+)\s+\d+\s+R/', $page,$r) || !isset($objects[(int)$r[1]])) continue;
        $resources=$objects[(int)$r[1]];
        if(!preg_match('/\/Font\s*<<(.*?)>>/s',$resources,$fontblock)) continue;
        $fonts=array();
        if(preg_match_all('/\/(F\d+)\s+(\d+)\s+\d+\s+R/', $fontblock[1],$fs,PREG_SET_ORDER)) foreach($fs as $f) {
            $font=$objects[(int)$f[2]]??'';
            if(preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $font,$u)) $fonts[$f[1]]=woori_pdf_cmap(woori_pdf_object_stream($objects[(int)$u[1]]??''));
        }
        if(!preg_match('/\/Contents\s*\[([^\]]+)\]/s',$page,$c)) continue;
        $fragments=array();
        if(preg_match_all('/(\d+)\s+\d+\s+R/',$c[1],$streams)) foreach($streams[1] as $streamid) {
            $stream=woori_pdf_object_stream($objects[(int)$streamid]??'');
            if(!preg_match_all('/BT\s+(.*?)\s+ET/s',$stream,$blocks)) continue;
            foreach($blocks[1] as $block) {
                if(!preg_match('/\/([A-Za-z0-9]+)\s+[\d.]+\s+Tf/',$block,$fontmatch)) continue;
                $map=$fonts[$fontmatch[1]]??array(); if(!$map) continue;
                if(!preg_match('/[-\d.]+\s+[-\d.]+\s+[-\d.]+\s+[-\d.]+\s+([-\d.]+)\s+([-\d.]+)\s+Tm/',$block,$xy)) continue;
                $line='';
                if(preg_match_all('/<([0-9A-Fa-f]+)>/',$block,$hexes)) foreach($hexes[1] as $hex) $line.=woori_pdf_decode_hex($hex,$map);
                if(trim($line)!=='') $fragments[]=array('x'=>(float)$xy[1], 'y'=>(float)$xy[2], 'text'=>$line);
            }
        }
        usort($fragments,function($a,$b){$d=$a['y']-$b['y'];return abs($d)>2 ? ($d<0?-1:1) : ($a['x']<=>$b['x']);});
        $lastY=null;
        foreach($fragments as $fragment) {
            if($lastY===null || abs($fragment['y']-$lastY)>2) $all.="\n";
            else $all.=' ';
            $all.=$fragment['text']; $lastY=$fragment['y'];
        }
        $all.="\n\f\n";
    }
    return $all;
}
