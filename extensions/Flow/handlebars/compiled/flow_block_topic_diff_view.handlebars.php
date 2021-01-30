<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'diffRevision' => 'Flow\TemplateHelper::diffRevision',
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
    return '<div class="flow-board">
	<div class="flow-compare-revisions-header plainlinks">
		'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-compare-revisions-header-post',((isset($in['revision']['new']['rev_view_links']['board']) && is_array($in['revision']['new']['rev_view_links']['board']) && isset($in['revision']['new']['rev_view_links']['board']['title'])) ? $in['revision']['new']['rev_view_links']['board']['title'] : null),((isset($in['revision']['new']['properties']) && is_array($in['revision']['new']['properties']) && isset($in['revision']['new']['properties']['topic-of-post-text-from-html'])) ? $in['revision']['new']['properties']['topic-of-post-text-from-html'] : null),((isset($in['revision']['new']['author']) && is_array($in['revision']['new']['author']) && isset($in['revision']['new']['author']['name'])) ? $in['revision']['new']['author']['name'] : null),((isset($in['revision']['new']['rev_view_links']['board']) && is_array($in['revision']['new']['rev_view_links']['board']) && isset($in['revision']['new']['rev_view_links']['board']['url'])) ? $in['revision']['new']['rev_view_links']['board']['url'] : null),((isset($in['revision']['new']['rev_view_links']['root']) && is_array($in['revision']['new']['rev_view_links']['root']) && isset($in['revision']['new']['rev_view_links']['root']['url'])) ? $in['revision']['new']['rev_view_links']['root']['url'] : null),((isset($in['revision']['new']['rev_view_links']['hist']) && is_array($in['revision']['new']['rev_view_links']['hist']) && isset($in['revision']['new']['rev_view_links']['hist']['url'])) ? $in['revision']['new']['rev_view_links']['hist']['url'] : null)),array()), 'encq', $in)).'
	</div>
	<div class="flow-compare-revisions">
		'.LR::encq($cx, LR::hbch($cx, 'diffRevision', array(array((($inary && isset($in['revision'])) ? $in['revision'] : null)),array()), 'encq', $in)).'
	</div>
</div>

';
};