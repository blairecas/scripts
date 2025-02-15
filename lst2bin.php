<?php
    // WARNING: relative ' addrs can be converted wrong
    // It will not work with a regular RT-11 MACRO11
    // Works with .asect and macro11.exe listing files!

    // sav mode restrictions:
    // - very simple first block
    // - start and stack set on 0x200

    $input_fname = $argv[1];
    $output_fname = $argv[2];
    $mode = $argv[3];
    $arg4 = -1; if (isset($argv[4])) $arg4 = octdec($argv[4]);
    $arg5 = -1; if (isset($argv[5])) $arg5 = octdec($argv[5]);

    if ($mode !== 'bin' && $mode !== 'mac' && $mode !== 'sav' && $mode !== 'bbk' && $mode !== 'bin512' && $mode !== 'bkpack') 
    {
        echo "Usage: php.exe -f lst2bin.php in_fname out_fname mode [start addr octal] [align to octal]\n";
        echo "in_fname - .lst filename\n";
        echo "mode = bin, bin512, bbk, bkpack, mac, sav\n";
        echo "if mode = bin/bin512/bbk - next octal is start addr (default 1000)\n";
        echo "if mode = sav - next octal is top ram addr\n";
        echo "align to size works in bin mode";
        exit(1);
    }

    $allRAM = Array(
        'ram'      => Array(),
        'max'      => 0xDFFF, /* 0157777 */
        'min'      => 0,
        'high'     => 0
    );

    if ($mode == 'sav') $allRAM['min'] = 0x200;

    $lnum = 0;
    $current_line = "";

    ProcessFile();

    if ($mode == 'mac')    WriteMac(0, $allRAM['high']);
    if ($mode == 'bin')    WriteBin($arg4, $allRAM['high'], $arg5);
    if ($mode == 'bin512') WriteBin512($arg4, $allRAM['high']);
    if ($mode == 'bbk')    WriteBinBk($arg4);
    if ($mode == 'bkpack') WriteBkpack();
    if ($mode == 'sav')    WriteSav();

    exit(0);

////////////////////////////////////////////////////////////////////////////////

$ssline0 = "";
$ssline1 = "";
$ssline2 = "";

function exit_with_error ($s)
{
    global $ssline0, $ssline1, $ssline2;
    echo "$s\n";
    echo $ssline0;
    echo $ssline1;
    echo $ssline2;
    exit(1);
}


function ProcessFile ()
{
    global $input_fname, $current_line;
    echo "processing $input_fname ... ";
    $lcount = 0;
    $fin = fopen($input_fname, "r");
    if ($fin === false) {
        echo "ERROR: unable to open input file\n";
        exit(1);
    }    
    while (!feof($fin))
    {
        $current_line = fgets($fin);
        // don't use first line if it contains word 'macro11'
        if (($lcount==0) && (strpos($current_line, "macro11") !== false)) continue;
        //
        $b = UseLine($current_line);
        if (!$b) break;
        $lcount++;
    }
    fclose($fin);    
    echo "used $lcount lines\n";
}


function UseLine ( $sline )
{
    global $lnum;
    global $ssline0, $ssline1, $ssline2;

    // rotate history
    $ssline0 = $ssline1; $ssline1 = $ssline2; $ssline2 = $sline;

    // empty string?
    $sline = rtrim($sline); if (strlen($sline)==0) return true;
    // assume 'Symbol table' as end
    if (strcasecmp($sline, "Symbol table") == 0) return false;
    // first character
    $fc = ord($sline[0]);
    // it's a page description - skip it
    if ($fc == 0x0C) return true;
    // no line number
    $lnum = 0;
    if ($fc == 0x09) $sline = substr($sline, 1);
                else $sline = GetLineNumber($sline, $lnum);
    // try to get addr
    $gAddr = 0; $type0 = -1;
    $sline = GetOctal($sline, $gAddr, $type0);
    if ($type0 < 0) exit_with_error("ERROR: in ADDR on $lnum");
    // now trying to get three octals
    $oct1 = 0; $type1 = -1; $sline = GetOctal($sline, $oct1, $type1);
    $oct2 = 0; $type2 = -1; $sline = GetOctal($sline, $oct2, $type2);
    $oct3 = 0; $type3 = -1; $sline = GetOctal($sline, $oct3, $type3);
    // error when converting (e.g. got 000000G global)
    if ($type1 < 0 || $type2 < 0 || $type3 < 0) exit_with_error("ERROR: in DATA on $lnum");
    // empty line
    if ($type1==0 && $type2==0 && $type3==0) return true;
    // no first octal?
    if ($type1==0 && ($type2>0 || $type3>0)) exit_with_error("ERROR: no first octal in DATA on $lnum");
    // no second octal
    if ($type2==0 && $type3>0) exit_with_error("ERROR: no second octal in DATA on $lnum");
    // first octal can't be relative
    if ($type1==3) exit_with_error("ERROR: first octal can't be relative on $lnum\n");
    // convert relatives for 2nd octal
    $next_addr = $gAddr + 2;
    if ($type2==1) $next_addr++;
    if ($type2==2 || $type2==3) $next_addr += 2;
    if ($type2==3) {
	// echo "REL2: ".decoct($oct2)." - ".decoct($next_addr);
	$oct2 = $oct2 - $next_addr;
	if ($oct2<0) $oct2 = (0x10000 + $oct2) & 0xFFFF;
	// echo " = ".decoct($oct2)." on $lnum\n";
    }
    // .. for 3rd octal
    if ($type3==1) $next_addr++;
    if ($type3==2 || $type3==3) $next_addr += 2;
    if ($type3==3) {
	// echo "REL3: ".decoct($oct3)." ".decoct($next_addr);
	$oct3 = $oct3 - $next_addr;
	if ($oct3<0) $oct3 = (0x10000 + $oct3) & 0xFFFF;
	// echo " = ".decoct($oct3)." on $lnum\n";
    }
    
    // DEBUG: echo decoct($gAddr)."-".$type0."\t\t".decoct($oct1)."-".$type1."\t\t".decoct($oct2)."-".$type2."\t\t".decoct($oct3)."-".$type3."\n";
    // now we have addr and up to three octals
    $gAddr = PutBytes($gAddr, $oct1, $type1);
    $gAddr = PutBytes($gAddr, $oct2, $type2);
    $gAddr = PutBytes($gAddr, $oct3, $type3);
    return true;
}


function GetLineNumber ($s, &$lnum)
{
    $s1 = trim(substr($s, 0, 8));
    $lnum = intval($s1, 10);
    return substr($s, 8);
}


function GetOctal ( $s, &$num, &$type )
{
    global $lnum;
    $l = 0;
    $sbuf = "";
    $relative = false;
    while ($l<8 && strlen($s) > 0)
    {
        $fc = ord($s[0]);
        if ($fc == 0x09) { $l = (($l+8) >> 3) << 3; $s = substr($s, 1); break; }
        if ($fc == 0x20) { $l++; $s = substr($s, 1); continue; }
        // last character can be G - globals ARE NOT ALLOWED for me
        if ($l == 6 && $fc == ord('G')) {
            exit_with_error("ERROR: global symbol on $lnum");
        }
        // last character in word data can be ' - need to convert to relative addr
        if ($l == 6 && $fc == ord('\'')) {
            $relative = true;
        } else {
            // else check for non [0..7] (octal) characters
            if ($fc < 0x30 || $fc > 0x37) { $type = -1; return ""; }
        }
        $sbuf .= chr($fc);
        $s = substr($s, 1);
        $l++;
    }
    // no data at all
    if (strlen($sbuf) == 0) {
        $type = 0;
        return $s;
    }
    // relative addr
    if ($relative) {
	$sbuf = substr($sbuf, 0, strlen($sbuf)-1);
    }
    // usual octal word of byte
    // 1 - byte, 2 - word, 3 - relative addr
    $type = 1;
    if (strlen($sbuf) > 3) $type = 2;
    if ($relative) {
        if ($type == 1) $type = -1; else $type = 3;
    }
    $num = octdec($sbuf);
    return $s;
}


function PutBytes ($adr, $w, $type)
{
    global $allRAM, $lnum;
    global $current_line;
    // type == 0 - don't use this
    if ($type == 0) return $adr;
    // check for overflow
    if ($adr > $allRAM['max'] || $adr < $allRAM['min'])
    {
        echo "ERROR: address ".decoct($adr)." is out of range (".decoct($allRAM['min'])."-".decoct($allRAM['max']).") on line $lnum\n";
	    echo "$current_line\n";
        exit(1);
    }
    // type == 1 - its a byte
    if ($type == 1) { 
        $allRAM['ram'][$adr] = $w & 0xFF;
        // set maximal addr
        if ($adr > $allRAM['high']) $allRAM['high'] = $adr;
        return $adr+1; // return next addr
    }
    // type == 2|3 - its a word
    if ($type == 2 || $type == 3) {
        $allRAM['ram'][$adr] = $w & 0xFF;
        $allRAM['ram'][$adr+1] = ($w>>8) & 0xFF;
        // set maximal addr
        if (($adr+1) > $allRAM['high']) $allRAM['high'] = ($adr+1);
        return $adr+2;
    }
    echo "ERROR in PutBytes() $adr $w $type on line $lnum\n";
    echo "$current_line\n";
    exit(1);
}


function WriteWord ($g, $w)
{
    $w = $w & 0xFFFF;
    $b1 = $w & 0xFF;
    $b2 = ($w & 0xFF00) >> 8;
    fwrite($g, chr($b1));
    fwrite($g, chr($b2));
}


function WriteBinaryToFile($g, $start, $end)
{
    global $allRAM;
    for ($i=$start; $i<=$end; $i++)
    {
        $byte = 0x00;
        if (isset($allRAM['ram'][$i])) $byte = $allRAM['ram'][$i];
        fwrite($g, chr($byte), 1);
    }
}


function WriteBin ($start, $end, $align)
{
    global $output_fname;
    if ($start < 0) $start = 0x200;
    $g = fopen($output_fname, 'w');
    WriteBinaryToFile($g, $start, $end);
    $size = $end-$start+1;
    if (($align > 0)) {
        if ($size % $align != 0) for ($i=0; $i<($align-($size%$align)); $i++) fwrite($g, chr(0x00));
    }
    fclose($g);
}


function WriteBin512 ($start, $end)
{
    global $output_fname;
    if ($start < 0) $start = 0x200;
    $g = fopen($output_fname, 'w');
    $l = $end - $start + 1;
    if (($l % 512) != 0) {
	$l = ($l + 512) & 0xFE00;
	$end = $start + $l - 1;
    }
    echo "Binary 512: ".decoct($start)." - ".decoct($end)."\n";
    WriteBinaryToFile($g, $start, $end);
    fclose($g);
}


function WriteBinBk ($start)
{
    global $allRAM, $output_fname;
    if ($start < 0) $start = 0x200;
    $end = $allRAM['high'];
    $length = $end - $start + 1;
    echo "BK binary: ".decoct($start)." - ".decoct($start+$length)."\n";
    $g = fopen($output_fname, 'w');
    WriteWord($g, $start);
    WriteWord($g, $length);
    WriteBinaryToFile($g, $start, $end);
    fclose($g);
}


function WriteMac ($start, $end)
{
    global $allRAM, $output_fname;
    $g = fopen($output_fname, 'w');
    for ($i=$start, $n=0; $i<=$end; $i++)
    {
        if ($n==0) fputs($g, "\t.byte\t");
        $byte = 0x00; if (isset($allRAM['ram'][$i])) $byte = $allRAM['ram'][$i];
        fputs($g, decoct($byte));
        $n++;
        if ($n < 16) { if ($i<$end) fputs($g, ", "); } else { $n=0; fputs($g, "\n"); }
    }
    fputs($g, "\n");
    fclose($g);
}


// bk binary with packed data from addr 2000(8)
// 1000-2000 loader
// 2000      packed size in bytes
// 2002-.... packed data
//
function WriteBkpack ()
{
    global $allRAM, $output_fname;
    // check size
    $end = $allRAM['high'];
    if ($end <= 01777) { echo "ERROR: data end addr must be greater than 1777(8)"; exit(1); }
    // compress data from 2000(8)..
    $zx0_fname_src = $output_fname . ".zxs";
    if (file_exists(($zx0_fname_src))) { echo "ERROR: file exists $zx0_fname_src"; exit(1); }
    $zx0_fname_dst = $zx0_fname_src . ".zx0";
    if (file_exists(($zx0_fname_dst))) { echo "ERROR: file exists $zx0_fname_dst"; exit(1); }
    $f = fopen($zx0_fname_src, "wb");
    $size = 0;
    for ($i=02000; $i<=$end; $i++) { 
        $byte = 0x00; if (isset($allRAM['ram'][$i])) $byte = $allRAM['ram'][$i];
        fwrite($f, chr($byte), 1); 
        $size++; 
    }
    fclose($f); 
    echo "packing 02000..0".decoct($end).", size $size -> ";
    exec(dirname(__FILE__)."/zx0 -f -q ".$zx0_fname_src." ".$zx0_fname_dst);
    unlink($zx0_fname_src);
    // read compressed and put to RAM
    $size_zx0 = filesize($zx0_fname_dst);
    echo "$size_zx0 bytes\n";
    if ($size_zx0 == 0) { echo "ERROR: filesize=0 of file $zx0_fname_dst"; exit(1); }    
    $f = fopen($zx0_fname_dst, "rb");
    $data = fread($f, $size_zx0);
    fclose($f);
    for ($i=0; $i<$size_zx0; $i++) $allRAM['ram'][02002+$i] = ord($data[$i]);
    unlink($zx0_fname_dst);
    // put compressed size
    $allRAM['ram'][02000] = $size_zx0 & 0xFF;
    $allRAM['ram'][02001] = ($size_zx0 >> 8) & 0xFF;
    // write resulting binary
    $g = fopen($output_fname, 'w');
    WriteWord($g, 01000);
    WriteWord($g, 01000+2+$size_zx0);
    WriteBinaryToFile($g, 01000, 02002+$size_zx0-1);
    fclose($g);
}


// .sav format for RT-11 
//
function WriteSav ()
{
    global $allRAM, $arg4;    
    // clear first block
    for ($i=0; $i<0x200; $i++) $allRAM['ram'][$i] = 0;
    $allRAM['ram'][0x21] = 0x02;    // 0x20-0x21 - relative start addr (0x0200)
    $allRAM['ram'][0x23] = 0x02;    // 0x22-0x23 - initial location of stack pointer (0x0200)
    // 0x28-0x29 - program's high limit - word aligned?
    $high = ($allRAM['high']+2) & 0xFFFE;
    if ($arg4 >= 0) $high = $arg4;
    $allRAM['ram'][0x28] = ($high & 0xFF);
    $allRAM['ram'][0x29] = (($high & 0xFF00) >> 8);
    // 0xF0-0xFF - bitmask area - to load blocks from file [11111000][...] bytes, bits are readed from high to low
    $adr = $high - 1;
    if ($adr < 0x200) {
        echo "ERROR: sav mode data must be at least 2 blocks in size!";
        exit(1);
    }
    $block0adr = 0xF0;
    $block0byte = 0;
    $rotcount = 0;        
    while ($adr >= 0) {
        $block0byte = ($block0byte >> 1) | 0x80;
        $rotcount++;
        if ($rotcount >= 8) {
            $rotcount = 0;
            $allRAM['ram'][$block0adr] = $block0byte;
            $block0adr++;
            $block0byte = 0;
        }
        $adr -= 512;
    }
    $allRAM['ram'][$block0adr] = $block0byte;
    // align high to 512-bytes
    $allRAM['high'] = (($allRAM['high']+512) & 0xFE00) - 1;
    // save 
    WriteBin(0, $allRAM['high'], -1);
}
