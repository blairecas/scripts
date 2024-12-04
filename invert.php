<?php
    // invert bytes in file
    // used for inversion of HDD images
    
    if (!isset($argv[1])) {
        echo "invert.php - bytes inverter for file\n";
        echo "Usage: php -f invert.php file.ext\n";
        exit(1);
    }

    echo "inverting bytes ".$argv[1]." ... ";
    $s = file_get_contents($argv[1]);
    for ($i=0; $i<strlen($s); $i++) $s[$i] = chr(ord($s[$i])^0xFF);
    file_put_contents($argv[1], $s);
    echo "done\n";
