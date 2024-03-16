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
*/


    $prev_empty = true;
    $included_arr = Array();
    
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

function IncludeFileBin ($fn)
{
    $fn = trim($fn);
    if (!file_exists($fn)) {
	echo "ERROR: include file $fn does not exists!";
	exit(1);
    }
    echo "including binary $fn\n";
    $filesize = filesize($fn);
    $f = fopen($fn, 'rb');
    $binary = fread($f, $filesize);
    fclose($f);
    $s = "";
    $k = 0;
    for ($i=0; $i<$filesize; $i++)
    {
	if ($k==0) $s = $s . "\t.byte\t";
	$bb = ord($binary[$i]);
        $s = $s . decoct($bb);
	if ($k<16 && ($i<($filesize-1))) { 
	    $s = $s . ", "; 
	    $k++; 
	} else {
            OutputLine($s);
	    $s = ""; $k=0; 
	}
    }
    if (strlen($s) > 0) OutputLine($s);
}

function ReplaceHexToOctal ($arr)
{
    $d = hexdec($arr[1]);
    return decoct($d);
}

function ProcessLine ($s)
{
    // remove comment
    $s = StripComment($s);
    if ($s === false) return false;
    $s2 = ltrim($s);
    // process @includebin
    if (strlen($s2) > 13 && (strtolower(substr($s2, 0, 11)) === '@includebin'))
    {
	$s2 = substr($s2, 12);
	IncludeFileBin($s2);
	return false;
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
    //
    return $s;
}

////////////////////////////////////////////////////////////////////////////////

    $fname = $argv[1];
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
    