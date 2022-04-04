#!/usr/bin/php
<?php
function bitRotation($value, $amount, $version)
{
    if ($version == 64) {
        $mask = 0x7fffffffffffffff;
    } else {
        $mask = 0x7fffffff;
    }
    $rot =
        (($value >> $amount) & ($mask >> $amount - 1)) |
        ($value << $version - $amount);
    return $rot;
}

$belt = [
    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
];
$mill = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

function millFunction($version)
{
    global $mill;
    $CPMill = []; # Copy of mill
    $rotate = 0;
    $temporary = [];

    //γ: non-linearity
    for ($i = 0; $i < 19; $i++) {
        $temporary[$i] =
            $mill[$i % 19] ^ ($mill[($i + 1) % 19] | ~$mill[($i + 2) % 19]); //mes paneigiame sekanti elementa (pvz: 0010 -> 1101)
    }

    //π: intra-word and inter-word dispersion(bitwise rotation)
    for ($i = 0; $i < 19; $i++) {
        $bitamount = ($i * 7) % 19;
        $rotate = ($rotate + $i) % $version; //our word size (lw)

        if ($rotate == 0) {
            $CPMill[$i] = $temporary[$bitamount];
        } else {
            $CPMill[$i] = bitRotation(
                $temporary[$bitamount],
                $rotate,
                $version
            );
        }
    }

    // θ: diffusion
    for ($i = 0; $i < 19; $i++) {
        $mill[$i] =
            $CPMill[$i % 19] ^ $CPMill[($i + 1) % 19] ^ $CPMill[($i + 4) % 19];
    }

    //ι: asymmetry
    $mill[0] ^= 1;
}
//The round function
function beltFunction($version)
{
    global $belt;
    global $mill;
    $belt2 = [];

    # Belt function: simple rotation
    for ($row = 0; $row < 3; $row++) {
        $belt2[$row] = $belt[$row][13 - 1];
        //printf("----\n");
        //printf($belt2[$row]);
        //printf("----\n");
        for ($collumn = 13 - 1; $collumn > 0; $collumn--) {
            $belt[$row][$collumn] = $belt[$row][$collumn - 1];
        }
        $belt[$row][$collumn] = $belt2[$row];
    }

    # Mill to belt feedforward
    for ($i = 0; $i < 13 - 1; $i++) {
        //printf($i % $3);
        //printf("\n");
        $belt[$i % 3][($i + 1) % 13] =
            $belt[$i % 3][($i + 1) % 13] ^ $mill[($i + 1) % 19];
    }

    # Mill function
    millFunction($version);

    # Belt to mill feedforward
    for ($i = 0; $i < 3; $i++) {
        $mill[13 + $i] = $mill[13 + $i] ^ $belt2[$i];
    }
}

function bittifyStringByQuantity($quantity, $string)
{
    $shiftedValue = 0;
    for ($q = 0; $q < $quantity; $q++) {
        //printf($q);
        //printf(" cycle \n");
        if (strlen($string) < 1) {
            $letterToDecimal = 1;
        } else {
            $letterToDecimal = ord(substr($string, 0, 1)); //paima pirma raide konvertuojama i ASCII koda
        }
        $shiftedValue = $shiftedValue | ($letterToDecimal << 8 * $q); //the | operator (or) adds 1 to bits eg: 0101 | 0010 = 0111, << operator shifts by one bit
        //printf($shiftedValue);
        //printf("\n");
        if (strlen($string) < 1) {
            break;
        }
        $string = substr($string, 1); //reduce string from beginning
    }
    //printf("\n");
    //printf($string);
    return $shiftedValue;
}

function InitialMapping($string, $version)
{
    global $belt;
    global $mill;
    if ($version == 64) {
        $quantity = 8;
    } else {
        $quantity = 4;
    }
    $end = false;
    while (!$end) {
        //užsibaigti ciklui slyga tada kai visi simboliai apdoroti
        for ($i = 0; $i < 3; $i++) {
            //3 kartai, nes belt turi tris eiles
            $shiftedValue = bittifyStringByQuantity($quantity, $string);
            $string = substr($string, $quantity);
            $belt[$i][0] = $belt[$i][0] ^ $shiftedValue;
            $mill[$i + 16] = $mill[$i + 16] ^ $shiftedValue;
            if (strlen($string) < 1) {
                //if we pass an empty string
                //printf('should be the last process of the string ');
                $a = 0;
                while ($a < 17) {
                    beltFunction($version);
                    $a++;
                }
                $end = true;
                break;
            }
            //print_r($this->belt);
            //print_r($this->mill);
        }
        //print_r('full 3 loop');
        if (!$end) {
            beltFunction($version);
        }
    }
}

function rg($string, $version = 32)
{
    //printf($version);
    global $mill;
    InitialMapping($string, $version);
    $p = 0;
    if ($version == 64) {
        $amountPrint = 4;
    } else {
        $amountPrint = 8;
    }
    for ($i = 0; $i < $amountPrint; $i++) {
        //printf($p % 2);
        //print "\n";
        if ($p % 2 == 0) {
            beltFunction($version); // as antras elementas bus procesuojamas
        }
        //printf($p % 2);
        //print "\n";
        $millElement = $mill[($p % 2) + 1];
        //printf($millElement);
        //print "\n";

        //sukeičiame vietomis bitus po 8 poras iš airės į dešinę
        if ($version == 64) {
            $millElement =
                (($millElement & 0xff) << 56) |
                (($millElement & 0xff00) << 40) |
                (($millElement & 0xff0000) << 24) |
                (($millElement & 0xff000000) << 8) |
                (($millElement & 0xff00000000) >> 8) |
                (($millElement & 0xff0000000000) >> 24) |
                (($millElement & 0xff000000000000) >> 40) |
                (($millElement >> 56) & 0xff);
        } else {
            $millElement =
                (($millElement & 0xff) << 24) |
                (($millElement & 0xff00) << 8) |
                (($millElement & 0xff0000) >> 8) |
                (($millElement >> 24) & 0xff);
        }
        //printf($millElement);
        //print "\n";
        printf('%08x', $millElement);
        //print "\n";
        $p++;
    }
}

($file1 = fopen('value.txt', 'r')) or die('Unable to open value file!');
($file2 = fopen('version.txt', 'r')) or die('Unable to open version file!');
$first = fread($file1, filesize('value.txt'));
$second = fread($file2, filesize('version.txt'));
if ($second == 64) {
    $second = 64;
} else {
    $second = 32;
}
rg($first, $second);


?>
