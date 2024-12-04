<?php
    // a.out to BK-0010 binary converter
    // usage: php -f aout2bin.php file.out file.bin
    
    $f = fopen($argv[1], "r"); if ($f === false) exit(1);
    $g = fopen($argv[2], "w"); if ($g === false) exit(1);
    
    fseek($f, 2, SEEK_SET);
    $s = fread($f, 2);
    $wTextSize = (ord($s[1])<<8) | ord($s[0]);
    $s = fread($f, 2);
    $wDataSize = (ord($s[1])<<8) | ord($s[0]);

    $wTotalSize = $wTextSize + $wDataSize;
    echo "Text:".decoct($wTextSize)." Data:".decoct($wDataSize)."\n";
    
    fseek($f, 0x10, SEEK_SET);
    $sText = fread($f, $wTextSize);
    $sData = fread($f, $wDataSize);
        
    fwrite($g, chr(0x00).chr(0x02), 2);
    fwrite($g, chr($wTotalSize&0xFF).chr($wTotalSize>>8));
    fwrite($g, $sText);
    fwrite($g, $sData);
    
    fclose($f);
    fclose($g);
