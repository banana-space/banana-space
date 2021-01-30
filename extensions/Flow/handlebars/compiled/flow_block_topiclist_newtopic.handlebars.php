<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'linkWithReturnTo' => 'Flow\TemplateHelper::linkWithReturnTo',
            'ifAnonymous' => 'Flow\TemplateHelper::ifAnonymous',
            'tooltip' => 'Flow\TemplateHelper::tooltip',
            'progressiveEnhancement' => 'Flow\TemplateHelper::progressiveEnhancement',
);
    $partials = array('flow_errors' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-error-container">
'.$sp.''.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), false)) ? '	<div class="flow-errors errorbox">
'.$sp.'		<ul>
'.$sp.''.LR::sec($cx, (isset($cx['sp_vars']['root']['errors']) ? $cx['sp_vars']['root']['errors'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return '				<li>'.LR::encq($cx, LR::hbch($cx, 'html', array(array((($inary && isset($in['message'])) ? $in['message'] : null)),array()), 'encq', $in)).'</li>
'.$sp.'';}).'		</ul>
'.$sp.'	</div>
'.$sp.'' : '').'</div>
';},
'flow_anon_warning' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-anon-warning">
'.$sp.'	<div class="flow-anon-warning-mobile">
'.$sp.''.LR::hbbch($cx, 'tooltip', array(array(),array('positionClass'=>'down','contextClass'=>'progressive','extraClass'=>'flow-form-collapsible','isBlock'=>true)), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-anon-warning',LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin'),array()), 'raw', $in),LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin/signup'),array()), 'raw', $in)),array()), 'encq', $in)).'';}).'	</div>
'.$sp.'
'.$sp.''.LR::hbbch($cx, 'progressiveEnhancement', array(array(),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '		<div class="flow-anon-warning-desktop">
'.$sp.''.LR::hbbch($cx, 'tooltip', array(array(),array('positionClass'=>'left','contextClass'=>'progressive','extraClass'=>'flow-form-collapsible','isBlock'=>true)), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-anon-warning',LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin'),array()), 'raw', $in),LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin/signup'),array()), 'raw', $in)),array()), 'encq', $in)).'';}).'		</div>
'.$sp.'';}).'</div>
';},
'flow_newtopic_form' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['newtopic'])) ? $in['actions']['newtopic'] : null), false)) ? '	<form action="'.LR::encq($cx, ((isset($in['actions']['newtopic']) && is_array($in['actions']['newtopic']) && isset($in['actions']['newtopic']['url'])) ? $in['actions']['newtopic']['url'] : null)).'" method="POST" class="flow-newtopic-form">
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '		').'
'.$sp.''.LR::hbbch($cx, 'ifAnonymous', array(array(),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_anon_warning', array(array($in),array()),0, '			').'';}).'
'.$sp.'		<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (isset($cx['sp_vars']['root']['editToken']) ? $cx['sp_vars']['root']['editToken'] : null)).'" />
'.$sp.'		<input type="hidden" name="topiclist_replyTo" value="'.LR::encq($cx, (($inary && isset($in['workflowId'])) ? $in['workflowId'] : null)).'" />
'.$sp.'		<input name="topiclist_topic" class="mw-ui-input mw-ui-input-large"
'.$sp.'			required
'.$sp.'			'.((LR::ifvar($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['topic'])) ? $in['submitted']['topic'] : null), false)) ? 'value="'.LR::encq($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['topic'])) ? $in['submitted']['topic'] : null)).'"' : '').'
'.$sp.'			type="text"
'.$sp.'			placeholder="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-newtopic-start-placeholder'),array()), 'encq', $in)).'"
'.$sp.'			data-role="title"
'.$sp.'		/>
'.$sp.''.((!LR::ifvar($cx, (($inary && isset($in['isOnFlowBoard'])) ? $in['isOnFlowBoard'] : null), false)) ? '			<div class="flow-editor">
'.$sp.'				<textarea name="topiclist_content"
'.$sp.'				          class="mw-ui-input flow-form-collapsible mw-editfont-'.LR::encq($cx, (isset($cx['sp_vars']['root']['editFont']) ? $cx['sp_vars']['root']['editFont'] : null)).'"
'.$sp.'				          placeholder="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-newtopic-content-placeholder',(isset($cx['sp_vars']['root']['title']) ? $cx['sp_vars']['root']['title'] : null)),array()), 'encq', $in)).'"
'.$sp.'				          data-role="content"
'.$sp.'				          required
'.$sp.'				>'.((LR::ifvar($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['content'])) ? $in['submitted']['content'] : null), false)) ? ''.LR::encq($cx, ((isset($in['submitted']) && is_array($in['submitted']) && isset($in['submitted']['content'])) ? $in['submitted']['content'] : null)).'' : '').'</textarea>
'.$sp.'			</div>
'.$sp.'			<div class="flow-form-actions flow-form-collapsible">
'.$sp.'				<button data-role="submit"
'.$sp.'					class="mw-ui-button mw-ui-progressive mw-ui-flush-right">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-newtopic-save'),array()), 'encq', $in)).'</button>
'.$sp.'				<small class="flow-terms-of-use plainlinks">'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-terms-of-use-new-topic'),array()), 'encq', $in)).'</small>
'.$sp.'			</div>
'.$sp.'' : '').'	</form>
'.$sp.'' : '').'';});
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
    return '<div class="flow-board flow-board-newtopic">
'.LR::p($cx, 'flow_newtopic_form', array(array($in),array()),0, '	').'</div>
';
};