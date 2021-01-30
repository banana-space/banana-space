<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'html' => 'Flow\TemplateHelper::htmlHelper',
);
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
    return '<div class="'.LR::encq($cx, (($inary && isset($in['extraClass'])) ? $in['extraClass'] : null)).' flow-ui-tooltip '.LR::encq($cx, (($inary && isset($in['contextClass'])) ? $in['contextClass'] : null)).' '.LR::encq($cx, (($inary && isset($in['positionClass'])) ? $in['positionClass'] : null)).' '.LR::encq($cx, (($inary && isset($in['blockClass'])) ? $in['blockClass'] : null)).' plainlinks">'.LR::encq($cx, LR::hbch($cx, 'html', array(array((($inary && isset($in['content'])) ? $in['content'] : null)),array()), 'encq', $in)).'<span class="flow-ui-tooltip-triangle"></span>
</div>
';
};