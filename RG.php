#!/usr/bin/php
<?php

function bitRotation($value, $amount, $version)
{
    //kadangi php kalboje 32 bitu right-shiftas mums prideda 
    //nereikalingu bitu del skirtingu integer didziu 32 ir 64 bit sistemose, 
    //tad mums reikes patiems apsibrezti max integeri
    if ($version == 64) {
        $systemIntLimit = 9223372036854775807;
    } else {
        $systemIntLimit = 2147483647;
    }
    if ($amount != 0) {
    $rot =
        (($value >> $amount) & ($systemIntLimit >> $amount - 1)) |
        ($value << $version - $amount);
    }else{
        $rot = $value;
    }
    return $rot;
}

//The mill function
//priima versija ($version)
function millFunction($version)
{
    global $mill;
    $rotate = 0;
    $millHold = [];
    //γ: non-linearity
    $i = 0;
    while($i < 19) {
        $millHold[$i] =
            $mill[$i % 19] ^ ($mill[($i + 1) % 19] | ~$mill[($i + 2) % 19]); // ~ simbolis paneigia sekanti elementa (pvz: 0010 -> 1101)
        $i++;
    }
    //π: intra-word and inter-word dispersion
    $mill2 = []; //Mill rotuotou reiksmiu saugojimui
    $i = 0;
    while($i < 19) {
        $mill2[$i] = bitRotation(
            $millHold[($i * 7) % 19],
            (($i*($i+1))/2 % $version),//rotavimo kiekis
            $version
        );
        $i++;
    }
    // θ: diffusion
    $i = 0;
    while($i < 19) {
        $mill[$i] =
            $mill2[$i % 19] ^ $mill2[($i + 1) % 19] ^ $mill2[($i + 4) % 19];
        $i++;
    }
    //ι: asymmetry
    $mill[0] = $mill[0] ^ 1;
}

//The round function
//priima versija ($version)
function roundFunction($version)
{
    global $belt;
    global $mill;
    $belt2 = $belt;
    // Belt function: simple rotation
    $row=0;
    while($row < 3) {
        array_unshift($belt[$row], array_pop($belt[$row]));//rotuoti masyva į dešinę
        $row++;
    }
    // Mill to belt feedforward
    $i = 0;
    while($i <= 11) {
        //printf($i % $3);
        //printf("\n");
        $belt[$i % 3][($i + 1) % 13] =
            $belt[$i % 3][($i + 1) % 13] ^ $mill[($i + 1) % 19];
        $i++;
    }
    // Mill function
    millFunction($version);
    // Belt to mill feedforward
    $i = 0;
    while($i <= 2) {
        $mill[13 + $i] = $mill[13 + $i] ^ $belt2[$i][12];
        $i++;
    }
}

//paimamas kiekis ($quantity) bloko dydziui apskaiciuoti ir stringbit reiksme ($string), grazina input bloka.
function getInputBlock($quantity, $string)
{
    //input blokam mes paimame 'žodžius' kuriu dydis priklauso nuo versijos ir nuskaitome po 8 bitus nuo desines puses
    //analogiskai 'žodi' galime suskirstyti i masyva po 8 bitus, sukeisdami elementus atvirksciai.
    //tai galime isivaizduoti konvertuojant 8 bitus i ascii simbolius pzv.: "abcd"->"dcba"
    $strArr=implode("",array_reverse(array_slice(str_split($string,8),0,$quantity/8)));//sustatome $quantity kiekio elementus po 8 bitu porose ir sudeliojam išvirkščia tvarka.
    if(strlen($strArr)!=$quantity){
        $strArr="00000001" . $strArr;//sioje vietoje tiesiog pridedame 00000001 kaip apvalkala (appending)
    }
    print "\n";
    print_r($strArr);print "\n";
    $inputBlockVaue=bindec($strArr);
    return $inputBlockVaue;
}

//Injection
//priima nuskaityta is failo reiksme ($string) ir versija ($version)
function Injection($string, $version)
{
    global $belt;
    global $mill;
    $a=$belt;//masyvai su 0 reiksmemis
    $b=$mill;//masyvas su 0 reiksmemis
    while (strlen($string) >= 1) {//užsibaigti ciklui salyga tada kai lieka maziau negu 8 bitai
        $i = 0;
        $inputBlocks=[];
        //input blokai (po 3 zodzius)
        while(($i < 3)&&(strlen($string) >= 1)) {
            array_push($inputBlocks,getInputBlock($version, $string));
            $string = substr($string, $version);
            $i++;
        }
        $mapped=inputMapping($a,$b,$inputBlocks);
        $a=$mapped[0];
        $b=$mapped[1];
        $n = 0;
        while(($n <= 2) && !is_null($inputBlocks[$n])) {
            for($nn=0;$nn<13;$nn++){
                $belt[$n][$nn] = $belt[$n][$nn] ^ $a[$n][$nn];
            }
            $mill[$n + 16] = $mill[$n + 16 ] ^ $b[$n + 16];
            $n++;
        }
        roundFunction($version);
    }
}
//The input mapping
function inputMapping($a,$b,$inputBlocks){
    $i = 0;
    while(($i <= 2) && !is_null($inputBlocks[$i])) {
        $a[$i][0] = $inputBlocks[$i];
        $b[$i + 16] = $inputBlocks[$i];
        $i++;
    }
    return array($a,$b);
}

//The output mapping // grazina 2 mill reiksmes
function outputMapping(){
    global $mill;
    $z=[];
    $z[0] = $mill[1];
    $z[1] = $mill[2];
    return $z;
}
//radioGatun priima reiksmes kurios yra skirstomos po 8 bitus.
//priima nuskaityta faila ($string) ir versija is version.txt.
//representuoja alternating-input construction algoritma
function radioGatun($string, $version = 32)
{
    //printf($version);
    global $mill;
    global $argv;
    $result=[];
    //Injection
    Injection($string, $version);
    //Mangling
    //procesuojame 16 (blank rounds);
    $a = 0;
    while ($a < 16) {
        roundFunction($version);
        $a++;
    }

    if ($version == 64) {
        $amountPrint = 2;
    } else {
        $amountPrint = 4;
    }
    //Extraction
    for ($i = 0; $i < $amountPrint; $i++) {

        roundFunction($version);

        //The output mapping
        $millElements = outputMapping();

        foreach($millElements as $millElement){
            //paimam bitus po 8 poras iš dešinės į kairę atlikinejant bitu poslinkius
            print "\n";
            $stringBits=(string)decbin($millElement);
            printf($stringBits);
            printf("-------------");
            print "\n";
                while(strlen($stringBits)!=64){
                    $stringBits = "0" . $stringBits;//konvertuojant i string gali nuimti pirmaujancius nulius, noredami palaikyti reikiama atkarpa pridedame "0"-us
                }
                $x=0;
                $pivot=strlen($stringBits)-$version;
                if($pivot<0){$pivot=0;}
                for ($bit = 0; $bit < $version/8; $bit++) {
                        $x = $x | (bindec(substr($stringBits, $pivot, 8)) << 8 * $bit);
                    printf(decbin($x));
                    printf("--");
                    $stringBits = substr($stringBits, 0, $pivot) . substr($stringBits, $pivot+8);
                }
            printf('> ');
            printf(dechex($x));
            array_push($result,str_pad(dechex($x),8,'0',STR_PAD_LEFT));
        }
    }
    ($file3 = fopen($argv[2], "w")) or die("Unable to open output file!");
    fwrite($file3, implode("",$result));
}

//inicializuojame tuscia belt ir mill
$belt = [
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
];
$mill = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

//atidaromi failai
($file1 = fopen($argv[1], 'r')) or die('Unable to open value file!');
($file2 = fopen('version.txt', 'r')) or die('Unable to open version file!');
$first = fread($file1, filesize($argv[1]));
$second = fread($file2, filesize('version.txt'));
//uztikrinamos versijos
if ($second == 64) {
    $second = 64;
} else {
    $second = 32;
}

print "\n";
radioGatun($first, $second);
print "\n";
?>
