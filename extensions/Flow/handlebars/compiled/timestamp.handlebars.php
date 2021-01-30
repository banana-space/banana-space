<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array();
    $partials = array();
    $cx = array(
        'flags' => array(
            'jstrue' => false,
            'jsobj' => false,
            'jslen' => false,
            'spvar' => true,
            'prop' => false,
            'method' => false,
            'lambda' => false,
            'mustlok' => false,
            'mustlam' => false,
            'mustsec' => false,
            'echo' => false,
            'partnc' => false,
            'knohlp' => false,
            'debug' => isset($options['debug']) ? $options['debug'] : 1,
        ),
        'constants' => array(),
        'helpers' => isset($options['helpers']) ? array_merge($helpers, $options['helpers']) : $helpers,
        'partials' => isset($options['partials']) ? array_merge($partials, $options['partials']) : $partials,
        'scopes' => array(),
        'sp_vars' => isset($options['data']) ? array_merge(array('root' => $in), $options['data']) : array('root' => $in),
        'blparam' => array(),
        'partialid' => 0,
        'runtime' => '\LightnCandy\Runtime',
    );
    
    $inary=is_array($in);
    return ''.((LR::ifvar($cx, (($inary && isset($in['guid'])) ? $in['guid'] : null), false)) ? '	<span datetime="'.LR::encq($cx, (($inary && isset($in['time_iso'])) ? $in['time_iso'] : null)).'" class="flow-timestamp">
' : '	<span datetime="'.LR::encq($cx, (($inary && isset($in['time_iso'])) ? $in['time_iso'] : null)).'"
	      class="flow-timestamp flow-load-interactive"
	      data-flow-load-handler="timestamp">
').'	<span class="flow-timestamp-user-formatted">'.LR::encq($cx, (($inary && isset($in['time_readable'])) ? $in['time_readable'] : null)).'</span>
	<span id="'.LR::encq($cx, (($inary && isset($in['guid'])) ? $in['guid'] : null)).'" class="flow-timestamp-ago">'.LR::encq($cx, (($inary && isset($in['time_ago'])) ? $in['time_ago'] : null)).'</span>
</span>
';
};