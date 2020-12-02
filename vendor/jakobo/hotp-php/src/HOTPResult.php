<?php

namespace jakobo\HOTP;

/**
 * The HOTPResult Class converts an HOTP item to various forms
 * Supported formats include hex, decimal, string, and HOTP

 * @author Jakob Heuser (firstname)@felocity.com
 * @copyright 2011
 * @license BSD-3-Clause
 * @version 1.0
 */
class HOTPResult {
    protected $hash;
    protected $binary;
    protected $decimal;
    protected $hex;

    /**
     * Build an HOTP Result
     * @param string $value the value to construct with
     */
    public function __construct( $value ) {
        // store raw
        $this->hash = $value;
    }

    /**
     * Returns the string version of the HOTP
     * @return string
     */
    public function toString() {
        return $this->hash;
    }

    /**
     * Returns the hex version of the HOTP
     * @return string
     */
    public function toHex() {
        if( !$this->hex )
        {
            $this->hex = dechex( $this->toDec() );
        }
        return $this->hex;
    }

    /**
     * Returns the decimal version of the HOTP
     * @return int
     */
    public function toDec() {
        if( !$this->decimal )
        {
            // store calculate decimal
            $hmac_result = array();

            // Convert to decimal
            foreach ( str_split( $this->hash,2 ) as $hex )
            {
               $hmac_result[] = hexdec($hex);
            }

            $offset = $hmac_result[19] & 0xf;

            $this->decimal = (
                ( ( $hmac_result[$offset+0] & 0x7f ) << 24 ) |
                ( ( $hmac_result[$offset+1] & 0xff ) << 16 ) |
                ( ( $hmac_result[$offset+2] & 0xff ) << 8 ) |
                ( $hmac_result[$offset+3] & 0xff )
            );
        }
        return $this->decimal;
    }

    /**
     * Returns the truncated decimal form of the HOTP
     * @param int $length the length of the HOTP to return
     * @return string
     */
    public function toHOTP( $length ) {
        $str = str_pad( $this->toDec(), $length, "0", STR_PAD_LEFT );
        return substr( $str, ( -1 * $length ) );
    }

}
