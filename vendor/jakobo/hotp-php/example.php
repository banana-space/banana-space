<?php

/*
HOTP Example File
*/
require_once 'src/hotp.php';

use jakobo\HOTP\HOTP;

$key = '12345678901234567890';

$table = array(
    'HOTP' => array(
        array(
            'HMAC'  => 'cc93cf18508d94934c64b65d8ba7667fb7cde4b0',
            'hex'   => '4c93cf18',
            'dec'   => '1284755224',
            'hotp'  => '755224',
        ),
        array(
            'HMAC'  => '75a48a19d4cbe100644e8ac1397eea747a2d33ab',
            'hex'   => '41397eea',
            'dec'   => '1094287082',
            'hotp'  => '287082',
        ),
        array(
            'HMAC'  => '0bacb7fa082fef30782211938bc1c5e70416ff44',
            'hex'   => '82fef30',
            'dec'   => '137359152',
            'hotp'  => '359152',
        ),
        array(
            'HMAC'  => '66c28227d03a2d5529262ff016a1e6ef76557ece',
            'hex'   => '66ef7655',
            'dec'   => '1726969429',
            'hotp'  => '969429',
        ),
        array(
            'HMAC'  => 'a904c900a64b35909874b33e61c5938a8e15ed1c',
            'hex'   => '61c5938a',
            'dec'   => '1640338314',
            'hotp'  => '338314',
        ),
        array(
            'HMAC'  => 'a37e783d7b7233c083d4f62926c7a25f238d0316',
            'hex'   => '33c083d4',
            'dec'   => '868254676',
            'hotp'  => '254676',
        ),
        array(
            'HMAC'  => 'bc9cd28561042c83f219324d3c607256c03272ae',
            'hex'   => '7256c032',
            'dec'   => '1918287922',
            'hotp'  => '287922',
        ),
        array(
            'HMAC'  => 'a4fb960c0bc06e1eabb804e5b397cdc4b45596fa',
            'hex'   => '4e5b397',
            'dec'   => '82162583',
            'hotp'  => '162583',
        ),
        array(
            'HMAC'  => '1b3c89f65e6c9e883012052823443f048b4332db',
            'hex'   => '2823443f',
            'dec'   => '673399871',
            'hotp'  => '399871',
        ),
        array(
            'HMAC'  => '1637409809a679dc698207310c8c7fc07290d9e5',
            'hex'   => '2679dc69',
            'dec'   => '645520489',
            'hotp'  => '520489',
        ),
    ),
    'TOTP' => array(
        '59' => array(
            'totp'  => '94287082',
        ),
        '1111111109' => array(
            'totp'  => '07081804',
        ),
        '1111111111' => array(
            'totp'  => '14050471',
        ),
        '1234567890' => array(
            'totp'  => '89005924',
        ),
        '2000000000' => array(
            'totp'  => '69279037',
        ),
    ),
);

echo <<<DOCBLOCK
<!DOCTYPE><html><head></head><body><pre>
http://www.ietf.org/rfc/rfc4226.txt
http://tools.ietf.org/html/draft-mraihi-totp-timebased-06

TEST VECTOR VERIFICATION

HOTP Tests:

DOCBLOCK;

echo "Count Method Value                                           Pass/Fail\n";
echo "----------------------------------------------------------------------\n";

// loop over all HOTP table results, and calculate the matching value
foreach ( $table['HOTP'] as $seed => $results ) {
    $hotp = HOTP::generateByCounter( $key, $seed );
    $first = true;
    foreach ( $results as $type => $calc ) {
        if ( $first ) {
            echo str_pad( $seed, 4, ' ', STR_PAD_LEFT );
            $first = false;
        }
        else {
            echo '    ';
        }
        echo '  ';
        echo str_pad( $type, 5, ' ', STR_PAD_RIGHT);
        echo '  ';
        echo str_pad( $calc, 47, ' ', STR_PAD_RIGHT);
        echo '  ';
        $method = 'to' . ( ucfirst( str_replace( 'HMAC', 'string', $type ) ) );
        echo str_pad( ( $calc == $hotp->$method( 6 ) ) ? '[OK]' : '[FAIL]', 9, ' ', STR_PAD_LEFT );
        echo "\n";
    }
}

echo <<<DOCBLOCK

TOTP Tests:

DOCBLOCK;

echo "Time (sec)   Value                                           Pass/Fail\n";
echo "----------------------------------------------------------------------\n";

// now echo over the TOTP table
foreach ( $table['TOTP'] as $seed => $results ) {
    $totp = HOTP::generateByTime( $key, 30, $seed );
    $first = true;
    foreach ( $results as $type => $calc ) {
        if ( $first ) {
            echo str_pad( $seed, 10, ' ', STR_PAD_LEFT );
            $first = false;
        }
        else {
            echo '          ';
        }
        echo '   ';
        echo str_pad( $calc, 47, ' ', STR_PAD_RIGHT );
        echo '  ';
        $method = 'to' . ( ucfirst( str_replace('totp', 'hotp', $type ) ) );
        echo str_pad( ( $calc == $totp->$method( 8 ) ) ? '[OK]' : '[FAIL]', 9, ' ', STR_PAD_LEFT );
        echo "\n";
    }
}

echo '</pre></body></html>';
