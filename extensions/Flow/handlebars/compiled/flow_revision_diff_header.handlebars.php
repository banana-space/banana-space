<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'enablePatrollingLink' => 'Flow\TemplateHelper::enablePatrollingLink',
);
    $partials = array('flow_patrol_diff' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((LR::ifvar($cx, ((isset($in['revision']['rev_view_links']) && is_array($in['revision']['rev_view_links']) && isset($in['revision']['rev_view_links']['markPatrolled'])) ? $in['revision']['rev_view_links']['markPatrolled'] : null), false)) ? '<div>
'.$sp.'        <span class="patrollink" data-mw="interface">
'.$sp.'            [<a class="mw-ui-quiet"
'.$sp.'               href="'.LR::encq($cx, ((isset($in['revision']['rev_view_links']['markPatrolled']) && is_array($in['revision']['rev_view_links']['markPatrolled']) && isset($in['revision']['rev_view_links']['markPatrolled']['url'])) ? $in['revision']['rev_view_links']['markPatrolled']['url'] : null)).'"
'.$sp.'               title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-mark-diff-patrolled-link-title'),array()), 'encq', $in)).'"
'.$sp.'               data-role="patrol">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-mark-diff-patrolled-link-text'),array()), 'encq', $in)).'</a>]
'.$sp.'        </span>
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
    return '<div>
	<a href="'.LR::encq($cx, ((isset($in['revision']['rev_view_links']['single-view']) && is_array($in['revision']['rev_view_links']['single-view']) && isset($in['revision']['rev_view_links']['single-view']['url'])) ? $in['revision']['rev_view_links']['single-view']['url'] : null)).'" class="flow-diff-revision-link">
		'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-compare-revisions-revision-header',((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['human_timestamp'])) ? $in['revision']['human_timestamp'] : null),((isset($in['revision']['author']) && is_array($in['revision']['author']) && isset($in['revision']['author']['name'])) ? $in['revision']['author']['name'] : null)),array()), 'encq', $in)).'
	</a>

'.((LR::ifvar($cx, (($inary && isset($in['new'])) ? $in['new'] : null), false)) ? ''.((LR::ifvar($cx, ((isset($in['revision']['actions']) && is_array($in['revision']['actions']) && isset($in['revision']['actions']['undo'])) ? $in['revision']['actions']['undo'] : null), false)) ? '('.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="mw-ui-anchor mw-ui-quiet" href="'.LR::encq($cx, ((isset($in['revision']['actions']['undo']) && is_array($in['revision']['actions']['undo']) && isset($in['revision']['actions']['undo']['url'])) ? $in['revision']['actions']['undo']['url'] : null)).'">'.LR::encq($cx, ((isset($in['revision']['actions']['undo']) && is_array($in['revision']['actions']['undo']) && isset($in['revision']['actions']['undo']['title'])) ? $in['revision']['actions']['undo']['title'] : null)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).')' : '').'' : '').'</div>
'.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['previous'])) ? $in['links']['previous'] : null), false)) ? ''.((!LR::ifvar($cx, (($inary && isset($in['new'])) ? $in['new'] : null), false)) ? '<div><a href="'.LR::encq($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['previous'])) ? $in['links']['previous'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-previous-diff'),array()), 'encq', $in)).'</a></div>' : '').'' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['next'])) ? $in['links']['next'] : null), false)) ? ''.((LR::ifvar($cx, (($inary && isset($in['new'])) ? $in['new'] : null), false)) ? '<div><a href="'.LR::encq($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['next'])) ? $in['links']['next'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-next-diff'),array()), 'encq', $in)).'</a></div>' : '').'' : '').''.LR::p($cx, 'flow_patrol_diff', array(array($in),array()),0).'';
};