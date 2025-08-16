<?php
//
// BK0010 binary file -> .wav (.mp3)
// original (javascript): http://thesands.ru/bk0010/wav-converter/
// changes: no russian (means no koi8r in header)
//

define('LEVEL_1', 208);
define('LEVEL_1_TUNE', 200); // чтоб визуально отличать в звуковом редакторе
define('LEVEL_0', 48);

define('TUNE_COUNT', 4096);
define('TUNE_COUNT_SECOND', 10);
define('TUNE_COUNT_END', 200);

define('SAMPLE_RATE_10', 21428);
define('SAMPLE_RATE_11', 25000);

define('BIT_0', chr(LEVEL_1).chr(LEVEL_1).chr(LEVEL_0).chr(LEVEL_0));
define('BIT_1', chr(LEVEL_1).chr(LEVEL_1).chr(LEVEL_1).chr(LEVEL_1).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0));
define('TUNE', chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_0).chr(LEVEL_0));
define('AFTER_TUNE', chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0).chr(LEVEL_0));
define('SYNCHRO_LONG', chr(LEVEL_1_TUNE).chr(LEVEL_1_TUNE).chr(LEVEL_0).chr(LEVEL_0));


    // filename padding char in header
    // space for monitor, zero for basic
    $pad_char = ' ';
    

function toStrWord ( $w )
{
    return chr($w&0xff) . chr(($w>>8)&0xff);
}


function toStrDword ( $dw )
{
    return chr($dw&0xff).chr(($dw>>8)&0xff).chr(($dw>>16)&0xff).chr(($dw>>24)&0xFF);
}


// Внедрение имени файла и контрольной суммы в бинарные данные
function insertFileNameAndCheckSum ($binary, $fileName) 
{
    global $pad_char;
    $hdr = substr($binary, 0, 4);
    $fnm = str_pad(substr($fileName, 0, 16), 16, $pad_char, STR_PAD_RIGHT);
    $dat = substr($binary, 4);
    $c = 0;
    for ($i=0; $i<strlen($dat); $i++)
    {
        $c += ord($dat[$i]);
        if ($c > 65535) { $c-= 65536; $c++; }
    }
    $chk = chr($c & 0xFF) . chr(($c >> 8) & 0xFF);
    return $hdr . $fnm . $dat . $chk;
}
  

// Функция преобразования бинарных данных в тело wav-файла
function binaryToSoundBytes ($binary) 
{
    // using arrays is faster than string concatenations
    $arrbin = Array();
    for ($i=0; $i<TUNE_COUNT; $i++) array_push($arrbin, TUNE);
    array_push($arrbin, AFTER_TUNE);
    array_push($arrbin, BIT_1);
    for ($i=0; $i<TUNE_COUNT_SECOND; $i++) array_push($arrbin, TUNE);
    array_push($arrbin, AFTER_TUNE);
    array_push($arrbin, BIT_1);
    for ($i=0; $i<strlen($binary); $i++) 
    {
        // после заголовков
        if ($i === 20) {
            for ($j=0; $j<TUNE_COUNT_SECOND; $j++) array_push($arrbin, TUNE);
            array_push($arrbin, AFTER_TUNE);
            array_push($arrbin, BIT_1);
        }
        $byte = ord($binary[$i]);
        for ($bit=1; $bit<255; $bit<<=1) {
            array_push($arrbin, SYNCHRO_LONG);
            array_push($arrbin, ($byte & $bit ? BIT_1 : BIT_0));
        }
    }
    for ($i=0; $i<TUNE_COUNT_END; $i++) array_push($arrbin, TUNE);
    return implode($arrbin);
}


// Добавление заголовков wav-файла к телу
function toWavFile ($bin, $sampleRate) 
{
    $channelCount = 1;
    $bitsPerSample = 8;
    $subChunk1Size = 16;
    $subChunk2Size = strlen($bin);
    $chunkSize = 4 + (8 + $subChunk1Size) + (8 + $subChunk2Size);
    $blockAlign = $channelCount * ($bitsPerSample / 8);
    $byteRate = $sampleRate * $blockAlign;
    $data = "RIFF" . toStrDword($chunkSize) . "WAVEfmt " .
        toStrDword($subChunk1Size) . 
        toStrWord(1) . 
        toStrWord($channelCount) .
        toStrDword($sampleRate) . 
        toStrDword($byteRate) . 
        toStrWord($blockAlign) . 
        toStrWord($bitsPerSample) . 
        "data" .
        toStrDword($subChunk2Size);
    return $data . $bin;
}

////////////////////////////////////////////////////////////////////////////////////////////

    // usage
    if (!isset($argv[1])) {
        echo "BK 0010/0011 binary to .wav file converter\n";
        echo "Usage: php -f bin2wav.php filename.bin [bas]\n";
        echo "       optional bas parameter - convert for basic CLOAD\n";
        exit(1);
    }

    // filenames
    $input_fname = $argv[1];
    if (!file_exists($input_fname)) {
        echo "ERROR: File $input_file is not exist\n";
        exit(1);
    }
    $binsize = filesize($input_fname);
    $input_filename = pathinfo($input_fname, PATHINFO_FILENAME);
    $output_wavname = pathinfo($input_fname, PATHINFO_DIRNAME) . '/' . $input_filename . '.wav';

    // maybe for basic
    if (isset($argv[2]) && ($argv[2]=='bas')) $pad_char=chr(0);

    // get binary and check basic validity
    $bin = file_get_contents($input_fname);
    $length4 = strlen($bin) - 4;
    if ($length4 < 1) {
        echo "ERROR: File is too small (less than 5 bytes)\n";
        exit(1);
    }
    $bin_length = ord($bin[2]) + (ord($bin[3]) << 8);
    if ($bin_length != $length4) {
        echo "ERROR: Binary file is bad, lengths are wrong (bin:$bin_length, file-4:$length4)\n";
        exit(1);
    }    

    // convert to wav bytes
    echo "making $input_filename.wav ... $binsize -> ";
    $bin = insertFileNameAndCheckSum($bin, $input_filename);
    $bin = binaryToSoundBytes($bin);
    $bin = toWavFile($bin, SAMPLE_RATE_10);
    echo strlen($bin)." bytes\n";

    // write file
    file_put_contents($output_wavname, $bin);
