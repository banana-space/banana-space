<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'diffUndo' => 'Flow\TemplateHelper::diffUndo',
);
    $partials = array('flow_errors' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-error-container">
'.$sp.''.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), false)) ? '	<div class="flow-errors errorbox">
'.$sp.'		<ul>
'.$sp.''.LR::sec($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return '				<li>'.LR::encq($cx, LR::hbch($cx, 'html', array(array((($inary && isset($in['message'])) ? $in['message'] : null)),array()), 'encq', $in)).'</li>
'.$sp.'';}).'		</ul>
'.$sp.'	</div>
'.$sp.'' : '').'</div>
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
'.((LR::ifvar($cx, ((isset($in['undo']) && is_array($in['undo']) && isset($in['undo']['possible'])) ? $in['undo']['possible'] : null), false)) ? '		<p>'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-undo-edit-content'),array()), 'encq', $in)).'</p>
' : '		<p class="error">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-undo-edit-failure'),array()), 'encq', $in)).'</p>
').'
'.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'
'.((LR::ifvar($cx, ((isset($in['undo']) && is_array($in['undo']) && isset($in['undo']['possible'])) ? $in['undo']['possible'] : null), false)) ? '		'.LR::encq($cx, LR::hbch($cx, 'diffUndo', array(array(((isset($in['undo']) && is_array($in['undo']) && isset($in['undo']['diff_content'])) ? $in['undo']['diff_content'] : null)),array()), 'encq', $in)).'
' : '').'
	<form method="POST" action="'.LR::encq($cx, ((isset($in['links']['undo-edit-header']) && is_array($in['links']['undo-edit-header']) && isset($in['links']['undo-edit-header']['url'])) ? $in['links']['undo-edit-header']['url'] : null)).'" class="flow-post" data-module="header">
		<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editToken']) ? $cx['sp_vars']['root']['rootBlock']['editToken'] : null)).'" />
		<input type="hidden" name="header_prev_revision" value="'.LR::encq($cx, ((isset($in['current']) && is_array($in['current']) && isset($in['current']['revisionId'])) ? $in['current']['revisionId'] : null)).'" />

		<div class="flow-editor">
			<textarea name="header_content" class="mw-ui-input mw-editfont-'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editFont']) ? $cx['sp_vars']['root']['rootBlock']['editFont'] : null)).'" data-role="content">'.((LR::ifvar($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['content'])) ? $in['submitted']['content'] : null), false)) ? ''.LR::encq($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['content'])) ? $in['submitted']['content'] : null)).'' : ''.((LR::ifvar($cx, ((isset($in['undo']) && is_array($in['undo']) && isset($in['undo']['possible'])) ? $in['undo']['possible'] : null), false)) ? ''.LR::encq($cx, ((isset($in['undo']) && is_array($in['undo']) && isset($in['undo']['content'])) ? $in['undo']['content'] : null)).'' : ''.LR::encq($cx, ((isset($in['current']['content']) && is_array($in['current']['content']) && isset($in['current']['content']['content'])) ? $in['current']['content']['content'] : null)).'').'').'</textarea>
		</div>

		<div class="flow-form-actions flow-form-collapsible">
			<button class="mw-ui-button mw-ui-progressive">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-edit-header-submit'),array()), 'encq', $in)).'</button>
			<small class="flow-terms-of-use plainlinks">'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-terms-of-use-edit'),array()), 'encq', $in)).'
			</small>
		</div>
	</form>
</div>

';
};