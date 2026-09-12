<?php
/**
 * Converte um valor numérico para extenso em português brasileiro.
 * Suporta valores de 0 até 999.999.999.999,99.
 */
function valorPorExtenso(float $valor): string
{
    if ($valor == 0) return 'Zero reais';

    $inteiro = (int)floor($valor);
    $centavos = round(($valor - $inteiro) * 100);

    $extenso = '';

    if ($inteiro > 0) {
        $extenso = intExtenso($inteiro);
        $conector = (preg_match('/(milh|bilh|trilh)\w+$/u', $extenso) ? ' de ' : ' ') . ($inteiro === 1 ? 'real' : 'reais');
        $extenso .= $conector;
    }

    if ($centavos > 0) {
        if ($inteiro > 0) $extenso .= ' e ';
        $extenso .= intExtenso($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
    }

    return ucfirst($extenso);
}

function intExtenso(int $n): string
{
    if ($n < 1) return '';

    $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
                 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis',
                 'dezessete', 'dezoito', 'dezenove'];
    $dezenas  = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta',
                 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
                 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    $parts = [];
    $bilhoes   = (int)floor($n / 1000000000); $n %= 1000000000;
    $milhoes   = (int)floor($n / 1000000);    $n %= 1000000;
    $milhares  = (int)floor($n / 1000);       $n %= 1000;
    $resto     = $n;

    if ($bilhoes > 0) {
        if ($bilhoes === 1) $parts[] = 'um bilhão';
        else $parts[] = intExtenso($bilhoes) . ' bilhões';
    }
    if ($milhoes > 0) {
        if ($milhoes === 1) $parts[] = 'um milhão';
        else $parts[] = intExtenso($milhoes) . ' milhões';
    }
    if ($milhares > 0) {
        if ($milhares === 1) $parts[] = 'mil';
        else $parts[] = intExtenso($milhares) . ' mil';
    }
    if ($resto > 0) {
        $parts[] = tratarResto($resto);
    }

    return implode(' e ', $parts);
}

function tratarResto(int $n): string
{
    $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
                 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis',
                 'dezessete', 'dezoito', 'dezenove'];
    $dezenas  = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta',
                 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
                 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    $c = (int)floor($n / 100);
    $d = (int)floor(($n % 100) / 10);
    $u = $n % 10;

    $parts = [];

    if ($c > 0) {
        if ($n === 100) return 'cem';
        $parts[] = $centenas[$c];
    }
    if ($d >= 2) {
        $parts[] = $dezenas[$d];
        if ($u > 0) $parts[] = $unidades[$u];
    } elseif ($n % 100 > 0) {
        $parts[] = $unidades[$n % 100];
    }

    return implode(' e ', $parts);
}
