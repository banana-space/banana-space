<?php

/**
 * Stub methods from hhvm that phan doesn't know about
 */

/**
 * @param string $poolName
 * @param string|null $url
 * @return resource|false
 */
function curl_init_pooled( $poolName, $url = null ) {
	return false;
}
