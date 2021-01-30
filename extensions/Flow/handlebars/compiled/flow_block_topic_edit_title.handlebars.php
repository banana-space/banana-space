<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'eachPost' => 'Flow\TemplateHelper::eachPost',
);
    $partials = array('flow_errors' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-error-container">
'.$sp.''.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), false)) ? '	<div class="flow-errors errorbox">
'.$sp.'		<ul>
'.$sp.''.LR::sec($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return '				<li>'.LR::encq($cx, LR::hbch($cx, 'html', array(array((($inary && isset($in['message'])) ? $in['message'] : null)),array()), 'encq', $in)).'</li>
'.$sp.'';}).'		</ul>
'.$sp.'	</div>
'.$sp.'' : '').'</div>
';},
'flow_edit_topic_title' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<form method="POST" action="'.LR::encq($cx, ((isset($in['actions']['edit']) && is_array($in['actions']['edit']) && isset($in['actions']['edit']['url'])) ? $in['actions']['edit']['url'] : null)).'" class="flow-edit-title-form">
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'	<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (isset($cx['sp_vars']['root']['editToken']) ? $cx['sp_vars']['root']['editToken'] : null)).'" />
'.$sp.'	<input type="hidden" name="topic_prev_revision" value="'.LR::encq($cx, (($inary && isset($in['revisionId'])) ? $in['revisionId'] : null)).'" />
'.$sp.'	<input name="topic_content" class="mw-ui-input" value="'.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['submitted']['content']) ? $cx['sp_vars']['root']['submitted']['content'] : null), false)) ? ''.LR::encq($cx, (isset($cx['sp_vars']['root']['submitted']['content']) ? $cx['sp_vars']['root']['submitted']['content'] : null)).'' : ''.LR::encq($cx, ((isset($in['content']) && is_array($in['content']) && isset($in['content']['content'])) ? $in['content']['content'] : null)).'').'" />
'.$sp.'	<div class="flow-form-actions">
'.$sp.'		<button data-role="submit"
'.$sp.'		        class="mw-ui-button mw-ui-progressive">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-edit-title-submit'),array()), 'encq', $in)).'</button>
'.$sp.'	</div>
'.$sp.'</form>
';});
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

'.LR::sec($cx, (($inary && isset($in['roots'])) ? $in['roots'] : null), null, $in, true, function($cx, $in) {$inary=is_array($in);return ''.LR::hbbch($cx, 'eachPost', array(array((isset($cx['sp_vars']['root']) ? $cx['sp_vars']['root'] : null),$in),array()), $in, false, function($cx, $in) {$inary=is_array($in);return ''.LR::p($cx, 'flow_edit_topic_title', array(array($in),array()),0, '			').'';}).'';}).'</div>
';
};