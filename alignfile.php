<?php
    // 
    // increase file size to be aligned with some value
    //

    if (!isset($argv[1]) || !isset($argv[2])) 
    {
        echo "Utility to increase file size to be aligned with some value\n";
        echo "php -f app_file.php file.ext align\n";
	echo "align - number in octal\n";
        exit(1);
    }

    $fname = $argv[1];
    $align = octdec($argv[2]);
    if ($align < 8 || $align > 0x10000) {
        echo "Wrong align value $align\n";
        exit(1);
    }

    $fsize = filesize($fname);
    if ($fsize === false) exit(1);
    if (($l = ($fsize % $align)) == 0) exit(0);
    echo "$fname $fsize -> ".($fsize+$align-$l)."\n";

    $f = fopen($fname, "a");
    for ($i=0; $i<($align-$l); $i++) fwrite($f, chr(0));
    fclose($f);
