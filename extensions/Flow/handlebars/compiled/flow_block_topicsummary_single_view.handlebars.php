<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'escapeContent' => 'Flow\TemplateHelper::escapeContent',
            'enablePatrollingLink' => 'Flow\TemplateHelper::enablePatrollingLink',
);
    $partials = array('flow_errors' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-error-container">
'.$sp.''.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), false)) ? '	<div class="flow-errors errorbox">
'.$sp.'		<ul>
'.$sp.''.LR::sec($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return '				<li>'.LR::encq($cx, LR::hbch($cx, 'html', array(array((($inary && isset($in['message'])) ? $in['message'] : null)),array()), 'encq', $in)).'</li>
'.$sp.'';}).'		</ul>
'.$sp.'	</div>
'.$sp.'' : '').'</div>
';},
'flow_patrol_action' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((LR::ifvar($cx, ((isset($in['revision']['rev_view_links']) && is_array($in['revision']['rev_view_links']) && isset($in['revision']['rev_view_links']['markPatrolled'])) ? $in['revision']['rev_view_links']['markPatrolled'] : null), false)) ? '<div class="patrollink" data-mw="interface">
'.$sp.'        [<a class="mw-ui-quiet"
'.$sp.'           href="'.LR::encq($cx, ((isset($in['revision']['rev_view_links']['markPatrolled']) && is_array($in['revision']['rev_view_links']['markPatrolled']) && isset($in['revision']['rev_view_links']['markPatrolled']['url'])) ? $in['revision']['rev_view_links']['markPatrolled']['url'] : null)).'"
'.$sp.'           title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-mark-revision-patrolled-link-title'),array()), 'encq', $in)).'"
'.$sp.'           data-role="patrol">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-mark-revision-patrolled-link-text'),array()), 'encq', $in)).'</a>]
'.$sp.'    </div>
'.$sp.'    '.LR::encq($cx, LR::hbch($cx, 'enablePatrollingLink', array(array(),array()), 'encq', $in)).'' : '').'';});
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
'.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'
'.((LR::ifvar($cx, (($inary && isset($in['revision'])) ? $in['revision'] : null), false)) ? '		<div class="flow-revision-permalink-warning plainlinks">
'.((LR::ifvar($cx, ((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['previousRevisionId'])) ? $in['revision']['previousRevisionId'] : null), false)) ? '				'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-revision-permalink-warning-postsummary',((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['human_timestamp'])) ? $in['revision']['human_timestamp'] : null),((isset($in['revision']['rev_view_links']['board']) && is_array($in['revision']['rev_view_links']['board']) && isset($in['revision']['rev_view_links']['board']['title'])) ? $in['revision']['rev_view_links']['board']['title'] : null),((isset($in['revision']['root']) && is_array($in['revision']['root']) && isset($in['revision']['root']['content'])) ? $in['revision']['root']['content'] : null),((isset($in['revision']['rev_view_links']['hist']) && is_array($in['revision']['rev_view_links']['hist']) && isset($in['revision']['rev_view_links']['hist']['url'])) ? $in['revision']['rev_view_links']['hist']['url'] : null),((isset($in['revision']['rev_view_links']['diff']) && is_array($in['revision']['rev_view_links']['diff']) && isset($in['revision']['rev_view_links']['diff']['url'])) ? $in['revision']['rev_view_links']['diff']['url'] : null)),array()), 'encq', $in)).'
' : '				'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-revision-permalink-warning-postsummary-first',((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['human_timestamp'])) ? $in['revision']['human_timestamp'] : null),((isset($in['revision']['rev_view_links']['board']) && is_array($in['revision']['rev_view_links']['board']) && isset($in['revision']['rev_view_links']['board']['title'])) ? $in['revision']['rev_view_links']['board']['title'] : null),((isset($in['revision']['root']) && is_array($in['revision']['root']) && isset($in['revision']['root']['content'])) ? $in['revision']['root']['content'] : null),((isset($in['revision']['rev_view_links']['hist']) && is_array($in['revision']['rev_view_links']['hist']) && isset($in['revision']['rev_view_links']['hist']['url'])) ? $in['revision']['rev_view_links']['hist']['url'] : null),((isset($in['revision']['rev_view_links']['diff']) && is_array($in['revision']['rev_view_links']['diff']) && isset($in['revision']['rev_view_links']['diff']['url'])) ? $in['revision']['rev_view_links']['diff']['url'] : null)),array()), 'encq', $in)).'
').'		</div>
		<div class="flow-revision-content mw-parser-output">
			'.LR::encq($cx, LR::hbch($cx, 'escapeContent', array(array(((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['format'])) ? $in['revision']['content']['format'] : null),((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['content'])) ? $in['revision']['content']['content'] : null)),array()), 'encq', $in)).'
		</div>

'.LR::p($cx, 'flow_patrol_action', array(array($in),array()),0, '        ').'' : '').'</div>
';
};