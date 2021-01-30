<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
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
    return '<div class="flow-topic-summary-container">
	<div class="flow-topic-summary">
		<form class="flow-edit-form" method="POST" action="'.LR::encq($cx, ((isset($in['revision']['actions']['summarize']) && is_array($in['revision']['actions']['summarize']) && isset($in['revision']['actions']['summarize']['url'])) ? $in['revision']['actions']['summarize']['url'] : null)).'">
'.LR::p($cx, 'flow_errors', array(array($in),array()),0, '			').'			<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (($inary && isset($in['editToken'])) ? $in['editToken'] : null)).'" />

'.((LR::ifvar($cx, ((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['revisionId'])) ? $in['revision']['revisionId'] : null), false)) ? '				<input type="hidden" name="'.LR::encq($cx, (($inary && isset($in['type'])) ? $in['type'] : null)).'_prev_revision" value="'.LR::encq($cx, ((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['revisionId'])) ? $in['revision']['revisionId'] : null)).'" />
' : '').'
			<div class="flow-editor">
				<textarea class="mw-ui-input mw-editfont-'.LR::encq($cx, (($inary && isset($in['editFont'])) ? $in['editFont'] : null)).'"
				          name="'.LR::encq($cx, (($inary && isset($in['type'])) ? $in['type'] : null)).'_summary"
				          type="text"
				          placeholder="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-edit-summary-placeholder'),array()), 'encq', $in)).'"
				          data-role="content"
				>'.((LR::ifvar($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['summary'])) ? $in['submitted']['summary'] : null), false)) ? ''.LR::encq($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['summary'])) ? $in['submitted']['summary'] : null)).'' : ''.((LR::ifvar($cx, ((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['revisionId'])) ? $in['revision']['revisionId'] : null), false)) ? ''.LR::encq($cx, ((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['content'])) ? $in['revision']['content']['content'] : null)).'' : '').'').'</textarea>
			</div>

			<div class="flow-form-actions flow-form-collapsible">
				<button
					data-role="submit"
					class="mw-ui-button mw-ui-progressive">
						'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-action-update-topic-summary'),array()), 'encq', $in)).'
				</button>
				<small class="flow-terms-of-use plainlinks">'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-terms-of-use-summarize'),array()), 'encq', $in)).'</small>
			</div>
		</form>
	</div>
</div>
';
};