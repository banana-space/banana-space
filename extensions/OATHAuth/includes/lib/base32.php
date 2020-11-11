<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP Google two-factor authentication module.
 *
 * See http://www.idontplaydarts.com/2011/07/google-totp-two-factor-authentication-for-php/
 * for more details
 *
 * @author Phil
 **/

class Base32 {

	private static $lut = array(
		"A" => 0, "B" => 1,
		"C" => 2, "D" => 3,
		"E" => 4, "F" => 5,
		"G" => 6, "H" => 7,
		"I" => 8, "J" => 9,
		"K" => 10, "L" => 11,
		"M" => 12, "N" => 13,
		"O" => 14, "P" => 15,
		"Q" => 16, "R" => 17,
		"S" => 18, "T" => 19,
		"U" => 20, "V" => 21,
		"W" => 22, "X" => 23,
		"Y" => 24, "Z" => 25,
		"2" => 26, "3" => 27,
		"4" => 28, "5" => 29,
		"6" => 30, "7" => 31
	);

	/**
	 * Decodes a base32 string into a binary string according to RFC 4648.
	 **/
	public static function decode($b32) {

		$b32 = strtoupper($b32);

		if (!preg_match('/^[ABCDEFGHIJKLMNOPQRSTUVWXYZ234567]+$/', $b32, $match))
			throw new Exception('Invalid characters in the base32 string.');

		$l      = strlen($b32);
		$n      = 0;
		$j      = 0;
		$binary = "";

		for ($i = 0; $i < $l; $i++) {
			// Move buffer left by 5 to make room
			$n = $n << 5;
			// Add value into buffer
			$n = $n + self::$lut[$b32[$i]];
			// Keep track of number of bits in buffer
			$j = $j + 5;

			if ($j >= 8) {
				$j = $j - 8;
				$binary .= chr(($n & (0xFF << $j)) >> $j);
			}
		}

		return $binary;
	}

	/**
	 * Encodes a binary string into a base32 string according to RFC 4648 (no padding).
	 **/
	public static function encode($string) {

		if (empty($string))
			throw new Exception('Empty string.');

		$b32 = "";
		$binary = "";

		$bytes = str_split($string);
		$length = count( $bytes );
		for ($i = 0; $i < $length; $i++) {
			$bits = base_convert(ord($bytes[$i]), 10, 2);
			$binary .= str_pad($bits, 8, '0', STR_PAD_LEFT);
		}

		$map = array_keys(self::$lut);
		$fivebits = str_split($binary, 5);
		$length = count( $fivebits );
		for ($i = 0; $i < $length; $i++) {
			$dec = base_convert(str_pad($fivebits[$i],  5, '0'), 2, 10);
			$b32 .= $map[$dec];
		}

		return $b32;
	}
}
