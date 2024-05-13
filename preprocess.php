<?php
/*
 * preprocess .mac files, use: php.exe -f preprocess.php file1.mac
 * =====================
 * - strips comments
 * - multiple empty lines are squeezed to single
 * - replacements:
 * @include file.ext
 *     will add file.ext lines to output file
 * @includebin file.ext
 *     will add file.ext as .byte ** ** **
 * .ppexe #cmd
 *     as macro command 
 *     mov #cmd, r5
 *     call ppuexecute
 * ^xAB, ^xCDEF - changed to appropriate octal numbers
 * @packstart[10] ... .word/.byte ... @packend
 *     pack bytes with zx0 (10 - will use radix 10, default 8)
*/


    $prev_empty = true;
    $included_arr = Array();

    $packing_start = false;
    $packing_data = Array();
    $packing_rad = 8;

    
function StripComment ($s)
{
    $icomm = strpos($s, ';');
    if ($icomm !== false && $icomm >= 0)
    {
        $i1 = strpos($s, '/');
        $i2 = strpos($s, '"');
	if (($i1 === false || $icomm < $i1) && ($i2 === false || $icomm < $i2))
        {
	    $s = substr($s, 0, $icomm);
	    if (strlen(trim($s)) == 0) return false;
        }
    }
    return rtrim($s);
}

function OutputLine ($s)
{
    global $prev_empty, $fout;
    if ($s !== false) 
    {
        if (strlen($s) == 0) {
            if (!$prev_empty) fputs($fout, "\r\n");
            $prev_empty = true; // to not use many empty lines
        } else {
            fputs($fout, $s."\r\n");
            $prev_empty = false;
        }
    }
}

function IncludeFile ($fn)
{
    global $included_arr;
    $fn = trim($fn);
    if (!file_exists($fn)) {
        echo "ERROR: include file $fn does not exists!";
        exit(1);
    }
    if (isset($included_arr[$fn])) {
        echo "ERROR: can't include file more than once!";
        exit(1);
    }
    $included_arr[$fn] = 1;
    echo "including $fn\n";
    $f = fopen($fn, "r");
    while (!feof($f))
    {
        $s = fgets($f);
        $s = ProcessLine($s);
        OutputLine($s);
    }
    fclose($f);
}

function IncludeFileBin ($fn, $rad)
{
    $fn = trim($fn);
    if (!file_exists($fn)) {
        echo "ERROR: include file $fn does not exists!";
        exit(1);
    }
    if (!isset($rad)) $rad = 8;
    $filesize = filesize($fn);
    $f = fopen($fn, 'rb');
    $binary = fread($f, $filesize);
    fclose($f);
    $sout = "";
    $s = "";
    $k = 0;
    for ($i=0; $i<$filesize; $i++)
    {
        if ($k==0) $s = $s . "\t.byte\t";
        $bb = ord($binary[$i]);
        if ($rad == 8) $s = $s . decoct($bb); else $s = $s . $bb;
        if ($k<16 && ($i<($filesize-1))) { 
            $s = $s . ", "; 
            $k++; 
        } else {
            $sout = $sout . $s . "\n";
            $s = ""; $k=0;
        }
    }
    if (strlen($s) > 0) $sout = $sout . $s . "\n";
    return $sout;
}


function ReplaceHexToOctal ($arr)
{
    $d = hexdec($arr[1]);
    return decoct($d);
}


function AddDataToPacking ($s)
{
    global $packing_data, $packing_rad;
    preg_match_all('/(\d+)[,]?\s*/i', $s, $arr);
    if (count($arr) != 2) return;
    $is_byte = true; if (stripos($s, ".word") !== false) $is_byte = false;
    foreach ($arr[1] as $k => $v) {
        $b = ($packing_rad==8 ? octdec($v) : intval($v, 10));
        if ($is_byte) {
            array_push($packing_data, $b);
        } else {
            array_push($packing_data, $b&0xFF, ($b>>8)&0xFF);
        }
    }
}


function GetPacked ()
{
    global $fname, $packing_data, $packing_rad;
    // write to temp file
    $_fname = "_" . $fname . ".pak";
    $_fname_zx0 = "_" . $fname . ".zx0";
    if (file_exists(($_fname))) { echo "ERROR: file exists $_fname"; exit(1); }
    if (file_exists(($_fname_zx0))) { echo "ERROR: file exists $_fname_zx0"; exit(1); }
    if (count($packing_data) == 0) { echo "ERROR: packing_data size == 0"; exit(1); }
    $f = fopen($_fname, "wb");
    foreach ($packing_data as $k => $v) fwrite($f, chr($v), 1);
    fclose($f);
    // pack it
    exec(dirname(__FILE__)."/zx0 -f -q ".$_fname." ".$_fname_zx0);
    unlink($_fname);
    // get as .mac code
    $s = IncludeFileBin($_fname_zx0, $packing_rad);
    unlink($_fname_zx0);
    return $s;
}


function ProcessLine ($s)
{
    global $packing_start, $packing_data, $packing_rad;

    // remove comment
    $s = StripComment($s);
    if ($s === false) return false;
    $s2 = ltrim($s);
    // process @packend
    if (strtolower(substr($s2, 0, 8)) === '@packend') 
    {
        $packing_start = false;
        return GetPacked();
    }
    // process @packstart10
    if (strtolower(substr($s2, 0, 12)) === '@packstart10')
    {
        $packing_start = true;
        $packing_data = Array();
        $packing_rad = 10;
        return false;
    }
    // process @packstart
    if (strtolower(substr($s2, 0, 10)) === '@packstart')
    {
        $packing_start = true;
        $packing_data = Array();
        $packing_rad = 8;
        return false;
    }
    // process @includebin
    if (strlen($s2) > 13 && (strtolower(substr($s2, 0, 11)) === '@includebin'))
    {
        $s2 = substr($s2, 12);
        echo "including binary $s2\n";
        return IncludeFileBin($s2, 8);
    }    
    // process @include
    if (strlen($s2) > 9 && (strtolower(substr($s2, 0, 8)) === '@include'))
    {
    	$s2 = substr($s2, 9);
    	IncludeFile($s2);
    	return false;
    }
    // process .ppexec
    $s = preg_replace("/(\.ppexe)(\s+)(\S+)/i", "mov$2$3, R5\r\n\tcall\tPPUExecute", $s);
    // change hex ^xABCD to octal
    $s = preg_replace_callback('/\\^x([a-f0-9]+)/i', 'ReplaceHexToOctal', $s);
    // if we are packing
    if ($packing_start)
    {
        AddDataToPacking($s);
        return false;
    }
    return $s;
}

////////////////////////////////////////////////////////////////////////////////

    $fname = false;
    if (isset($argv[1])) $fname = $argv[1];
    if (!$fname) {
        echo "Usage: php.exe -f preprocess.php filename.mac\n";
        exit(0);
    }
    
    $fin = fopen($fname, "r");
    if (!$fin) {
        echo "Can't open file $fname\n";
        exit(1);
    }
    
    $ofname = "_".$fname;
    $fout = fopen($ofname, "w");
    if (!$fout) {
    	echo "Can't open file $ofname\n";
    	fclose($fin);
    	exit(1);
    }
    
    $linenum = 1;
    while (!feof($fin))
    {
    	$sin = fgets($fin);
    	$sout = ProcessLine($sin, $linenum);
    	OutputLine($sout);
    	$linenum++;
    }
    
    fclose($fin);
    fclose($fout);
    