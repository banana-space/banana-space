<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'uuidTimestamp' => 'Flow\TemplateHelper::uuidTimestamp',
            'timestamp' => 'Flow\TemplateHelper::timestampHelper',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'post' => 'Flow\TemplateHelper::post',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'concat' => 'Flow\TemplateHelper::concat',
            'linkWithReturnTo' => 'Flow\TemplateHelper::linkWithReturnTo',
            'escapeContent' => 'Flow\TemplateHelper::escapeContent',
            'eachPost' => 'Flow\TemplateHelper::eachPost',
            'ifAnonymous' => 'Flow\TemplateHelper::ifAnonymous',
            'ifCond' => 'Flow\TemplateHelper::ifCond',
            'tooltip' => 'Flow\TemplateHelper::tooltip',
            'progressiveEnhancement' => 'Flow\TemplateHelper::progressiveEnhancement',
);
    $partials = array('flow_board_navigation' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['board-sort'])) ? $in['links']['board-sort'] : null), false)) ? '<div class="flow-board-navigation" data-flow-load-handler="boardNavigation">
'.$sp.'	<div class="flow-error-container">
'.$sp.'	</div>
'.$sp.'</div>
'.$sp.'' : '').'';},
'flow_errors' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-error-container">
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
'.$sp.'' : '').'';},
'flow_topic_moderation_flag' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<span class="mw-ui-icon mw-ui-icon-before'.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'===','lock'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ' mw-ui-icon-check';}).''.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'===','hide'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ' mw-ui-icon-flag';}).''.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'===','delete'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ' mw-ui-icon-trash';}).'"></span>
';},
'flow_post_moderation_state' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<span class="plainlinks">'.((LR::ifvar($cx, (($inary && isset($in['replyToId'])) ? $in['replyToId'] : null), false)) ? ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'-post-content'),array()), 'raw', $in),((isset($in['moderator']) && is_array($in['moderator']) && isset($in['moderator']['name'])) ? $in['moderator']['name'] : null),((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)),array()), 'encq', $in)).'' : ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'-title-content'),array()), 'raw', $in),((isset($in['moderator']) && is_array($in['moderator']) && isset($in['moderator']['name'])) ? $in['moderator']['name'] : null),((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)),array()), 'encq', $in)).'').'</span>
';},
'flow_topic_titlebar_content' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-topic-titlebar-container">
'.$sp.'    <h2 class="flow-topic-title flow-load-interactive '.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? 'flow-collapse-toggle flow-click-interactive' : '').'"
'.$sp.'        data-flow-topic-title="'.LR::encq($cx, ((isset($in['content']) && is_array($in['content']) && isset($in['content']['content'])) ? $in['content']['content'] : null)).'"
'.$sp.'        data-flow-load-handler="topicTitle"
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? '        data-flow-interactive-handler="collapserCollapsibleToggle"
'.$sp.'' : '').'            >
'.$sp.'		'.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? '<span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-check"></span> ' : '').''.LR::encq($cx, LR::hbch($cx, 'escapeContent', array(array(((isset($in['content']) && is_array($in['content']) && isset($in['content']['format'])) ? $in['content']['format'] : null),((isset($in['content']) && is_array($in['content']) && isset($in['content']['content'])) ? $in['content']['content'] : null)),array()), 'encq', $in)).'</h2>
'.$sp.'    <div class="flow-topic-meta">
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? '<a class="expand-collapse-posts-link flow-collapse-toggle flow-click-interactive"
'.$sp.'               href="javascript:void(0);"
'.$sp.'               title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-show-comments-title',(($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)),array()), 'encq', $in)).'"
'.$sp.'               data-collapsed-title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-show-comments-title',(($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)),array()), 'encq', $in)).'"
'.$sp.'               data-expanded-title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-hide-comments-title',(($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)),array()), 'encq', $in)).'"
'.$sp.'               data-flow-interactive-handler="collapserCollapsibleToggle"
'.$sp.'                    >'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-comments',(($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)),array()), 'encq', $in)).'</a>' : ''.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-comments',(($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)),array()), 'encq', $in)).'').' &bull;
'.$sp.'
'.$sp.'        <a href="'.LR::encq($cx, ((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)).'" class="flow-timestamp-anchor">
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['last_updated'])) ? $in['last_updated'] : null), false)) ? '				'.LR::encq($cx, LR::hbch($cx, 'timestamp', array(array((($inary && isset($in['last_updated'])) ? $in['last_updated'] : null)),array()), 'encq', $in)).'
'.$sp.'' : '				'.LR::encq($cx, LR::hbch($cx, 'uuidTimestamp', array(array((($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), 'encq', $in)).'
'.$sp.'').'        </a>
'.$sp.'    </div>
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isModeratedNotLocked'])) ? $in['isModeratedNotLocked'] : null), false)) ? '        <div class="flow-moderated-topic-title flow-ui-text-truncated">'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).''.LR::p($cx, 'flow_topic_moderation_flag', array(array($in),array()),0).'
'.$sp.''.LR::p($cx, 'flow_post_moderation_state', array(array($in),array()),0, '			').'        </div>
'.$sp.'        <div class="flow-moderated-topic-reason">
'.$sp.'			'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-moderated-reason-prefix'),array()), 'encq', $in)).'
'.$sp.'			'.LR::encq($cx, LR::hbch($cx, 'escapeContent', array(array(((isset($in['moderateReason']) && is_array($in['moderateReason']) && isset($in['moderateReason']['format'])) ? $in['moderateReason']['format'] : null),((isset($in['moderateReason']) && is_array($in['moderateReason']) && isset($in['moderateReason']['content'])) ? $in['moderateReason']['content'] : null)),array()), 'encq', $in)).'
'.$sp.'        </div>
'.$sp.'' : '').'    <span class="flow-reply-count"><span class="flow-reply-count-number">'.LR::encq($cx, (($inary && isset($in['reply_count'])) ? $in['reply_count'] : null)).'</span></span>
'.$sp.'</div>';},
'flow_topic_titlebar_summary' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-topic-summary-container '.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? 'flow-collapse-toggle flow-click-interactive' : '').'"
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? '		data-flow-interactive-handler="collapserCollapsibleToggle"
'.$sp.'' : '').'		>
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').''.((LR::ifvar($cx, ((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['content'])) ? $in['revision']['content']['content'] : null), false)) ? '		<div class="flow-topic-summary">
'.$sp.'			<div class="flow-topic-summary-author">
'.$sp.''.LR::hbbch($cx, 'ifCond', array(array(((isset($in['revision']['creator']) && is_array($in['revision']['creator']) && isset($in['revision']['creator']['name'])) ? $in['revision']['creator']['name'] : null),'===',((isset($in['revision']['author']) && is_array($in['revision']['author']) && isset($in['revision']['author']['name'])) ? $in['revision']['author']['name'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '					'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-summary-authored',((isset($in['revision']['creator']) && is_array($in['revision']['creator']) && isset($in['revision']['creator']['name'])) ? $in['revision']['creator']['name'] : null)),array()), 'encq', $in)).'
'.$sp.'';}, function($cx, $in)use($sp){$inary=is_array($in);return '					'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-summary-edited',((isset($in['revision']['author']) && is_array($in['revision']['author']) && isset($in['revision']['author']['name'])) ? $in['revision']['author']['name'] : null)),array()), 'encq', $in)).'
'.$sp.'					<a href="'.LR::encq($cx, ((isset($in['revision']['links']['diff-prev']) && is_array($in['revision']['links']['diff-prev']) && isset($in['revision']['links']['diff-prev']['url'])) ? $in['revision']['links']['diff-prev']['url'] : null)).'" class="flow-timestamp-anchor">'.LR::encq($cx, LR::hbch($cx, 'uuidTimestamp', array(array(((isset($in['revision']) && is_array($in['revision']) && isset($in['revision']['lastEditId'])) ? $in['revision']['lastEditId'] : null)),array()), 'encq', $in)).'</a>
'.$sp.'';}).'			</div>
'.$sp.'			<div class="flow-topic-summary-content mw-parser-output">
'.$sp.'				'.LR::encq($cx, LR::hbch($cx, 'escapeContent', array(array(((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['format'])) ? $in['revision']['content']['format'] : null),((isset($in['revision']['content']) && is_array($in['revision']['content']) && isset($in['revision']['content']['content'])) ? $in['revision']['content']['content'] : null)),array()), 'encq', $in)).'
'.$sp.'			</div>
'.$sp.'			<div style="clear: both;"></div>
'.$sp.'		</div>
'.$sp.'' : '').'</div>
';},
'flow_topic_titlebar_watch' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-topic-watchlist flow-watch-link">
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'
'.$sp.'	<a href="'.((LR::ifvar($cx, (($inary && isset($in['isWatched'])) ? $in['isWatched'] : null), false)) ? ''.LR::encq($cx, ((isset($in['links']['unwatch-topic']) && is_array($in['links']['unwatch-topic']) && isset($in['links']['unwatch-topic']['url'])) ? $in['links']['unwatch-topic']['url'] : null)).'' : ''.LR::encq($cx, ((isset($in['links']['watch-topic']) && is_array($in['links']['watch-topic']) && isset($in['links']['watch-topic']['url'])) ? $in['links']['watch-topic']['url'] : null)).'').'"
'.$sp.'	   class="mw-ui-anchor mw-ui-hovericon '.((!LR::ifvar($cx, (($inary && isset($in['isWatched'])) ? $in['isWatched'] : null), false)) ? 'mw-ui-quiet' : '').'
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isWatched'])) ? $in['isWatched'] : null), false)) ? 'flow-watch-link-unwatch' : 'flow-watch-link-watch').'"
'.$sp.'	   data-flow-api-handler="watchItem"
'.$sp.'	   data-flow-api-target="< .flow-topic-watchlist"
'.$sp.'	   data-flow-api-method="POST">'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<span class="flow-unwatch mw-ui-icon mw-ui-icon-before mw-ui-icon-only mw-ui-icon-unStar mw-ui-icon-unStar-progressive" title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-action-watchlist-remove'),array()), 'encq', $in)).'"></span>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).''.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<span class="flow-watch mw-ui-icon mw-ui-icon-before mw-ui-icon-only mw-ui-icon-star mw-ui-icon-star-progressive-hover" title="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-action-watchlist-add'),array()), 'encq', $in)).'"></span>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</a>
'.$sp.'</div>
';},
'flow_moderation_actions_list' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<section>'.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'===','topic'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['edit'])) ? $in['actions']['edit'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-edit-title-link'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-edit' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['edit']) && is_array($in['actions']['edit']) && isset($in['actions']['edit']['url'])) ? $in['actions']['edit']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-topic-action-edit-title'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['topic-history'])) ? $in['links']['topic-history'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-clock' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-topic-action-history'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['topic'])) ? $in['links']['topic'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-link' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['links']['topic']) && is_array($in['links']['topic']) && isset($in['links']['topic']['url'])) ? $in['links']['topic']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-topic-action-view'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['summarize'])) ? $in['actions']['summarize'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-summarize-topic-link'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-listBullet' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['summarize']) && is_array($in['actions']['summarize']) && isset($in['actions']['summarize']['url'])) ? $in['actions']['summarize']['url'] : null)).'">'.((LR::ifvar($cx, ((isset($in['summary']['revision']['content']) && is_array($in['summary']['revision']['content']) && isset($in['summary']['revision']['content']['content'])) ? $in['summary']['revision']['content']['content'] : null), false)) ? ''.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-topic-action-resummarize-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'' : ''.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-topic-action-summarize-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'').'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'';}).''.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'===','history'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['lock'])) ? $in['actions']['lock'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-topicmenu-lock'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-check' : '').'"
'.$sp.'				   data-role="lock"
'.$sp.'				   data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['lock']) && is_array($in['actions']['lock']) && isset($in['actions']['lock']['url'])) ? $in['actions']['lock']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-lock-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['unlock'])) ? $in['actions']['unlock'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-topicmenu-lock'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-ongoingConversation' : '').'"
'.$sp.'				   data-role="unlock"
'.$sp.'				   data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['unlock']) && is_array($in['actions']['unlock']) && isset($in['actions']['unlock']['url'])) ? $in['actions']['unlock']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-unlock-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'';}, function($cx, $in)use($sp){$inary=is_array($in);return ''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['lock'])) ? $in['actions']['lock'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-topicmenu-lock'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-check' : '').'"
'.$sp.'				   data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'				   data-role="lock"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['lock']) && is_array($in['actions']['lock']) && isset($in['actions']['lock']['url'])) ? $in['actions']['lock']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-lock-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['unlock'])) ? $in['actions']['unlock'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-topicmenu-lock'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-ongoingConversation' : '').'"
'.$sp.'				   data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'				   data-role="unlock"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['unlock']) && is_array($in['actions']['unlock']) && isset($in['actions']['unlock']['url'])) ? $in['actions']['unlock']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-unlock-topic'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'';}).''.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'===','post'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['edit'])) ? $in['actions']['edit'] : null), false)) ? '<li>
'.$sp.'				<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet flow-ui-edit-post-link'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-edit' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['edit']) && is_array($in['actions']['edit']) && isset($in['actions']['edit']['url'])) ? $in['actions']['edit']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-post-action-edit-post'),array()), 'encq', $in)).'</a>
'.$sp.'			</li>' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['post'])) ? $in['links']['post'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-link' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['links']['post']) && is_array($in['links']['post']) && isset($in['links']['post']['url'])) ? $in['links']['post']['url'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-post-action-view'),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'';}).'</section>
'.$sp.'
'.$sp.'<section>'.LR::hbbch($cx, 'ifCond', array(array((($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'===','history'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['undo'])) ? $in['actions']['undo'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['actions']['undo']) && is_array($in['actions']['undo']) && isset($in['actions']['undo']['url'])) ? $in['actions']['undo']['url'] : null)).'"
'.$sp.'				>'.LR::encq($cx, ((isset($in['actions']['undo']) && is_array($in['actions']['undo']) && isset($in['actions']['undo']['title'])) ? $in['actions']['undo']['title'] : null)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'';}).''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['hide'])) ? $in['actions']['hide'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-flag' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['hide']) && is_array($in['actions']['hide']) && isset($in['actions']['hide']['url'])) ? $in['actions']['hide']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="hide">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-hide-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['unhide'])) ? $in['actions']['unhide'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-flag' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['unhide']) && is_array($in['actions']['unhide']) && isset($in['actions']['unhide']['url'])) ? $in['actions']['unhide']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="unhide">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-unhide-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['delete'])) ? $in['actions']['delete'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-trash' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['delete']) && is_array($in['actions']['delete']) && isset($in['actions']['delete']['url'])) ? $in['actions']['delete']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="delete">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-delete-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['undelete'])) ? $in['actions']['undelete'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-trash' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['undelete']) && is_array($in['actions']['undelete']) && isset($in['actions']['undelete']['url'])) ? $in['actions']['undelete']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="undelete">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-undelete-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['suppress'])) ? $in['actions']['suppress'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-block' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['suppress']) && is_array($in['actions']['suppress']) && isset($in['actions']['suppress']['url'])) ? $in['actions']['suppress']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="suppress">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-suppress-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['unsuppress'])) ? $in['actions']['unsuppress'] : null), false)) ? '<li>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'<a class="'.LR::encq($cx, (($inary && isset($in['moderationMwUiClass'])) ? $in['moderationMwUiClass'] : null)).' mw-ui-quiet'.((LR::ifvar($cx, (($inary && isset($in['moderationIcons'])) ? $in['moderationIcons'] : null), false)) ? ' mw-ui-icon mw-ui-icon-before mw-ui-icon-block' : '').'"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['unsuppress']) && is_array($in['actions']['unsuppress']) && isset($in['actions']['unsuppress']['url'])) ? $in['actions']['unsuppress']['url'] : null)).'"
'.$sp.'			   data-flow-interactive-handler="moderationDialog"
'.$sp.'			   data-flow-template="flow_moderate_'.LR::encq($cx, (($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)).'.partial"
'.$sp.'			   data-role="unsuppress">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderationType'])) ? $in['moderationType'] : null),'-action-unsuppress-',(($inary && isset($in['moderationTemplate'])) ? $in['moderationTemplate'] : null)),array()), 'raw', $in)),array()), 'encq', $in)).'</a>'.LR::encq($cx, (($inary && isset($in['noop'])) ? $in['noop'] : null)).'</li>' : '').'</section>
';},
'flow_topic_titlebar' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-topic-titlebar">
'.$sp.''.LR::p($cx, 'flow_topic_titlebar_content', array(array($in),array()),0, '	').''.LR::p($cx, 'flow_topic_titlebar_summary', array(array((($inary && isset($in['summary'])) ? $in['summary'] : null)),array('isLocked'=>(($inary && isset($in['isLocked'])) ? $in['isLocked'] : null))),0, '	').''.((LR::ifvar($cx, (($inary && isset($in['watchable'])) ? $in['watchable'] : null), false)) ? ''.LR::p($cx, 'flow_topic_titlebar_watch', array(array($in),array()),0, '		').'' : '').'	<div class="flow-menu flow-menu-hoverable">
'.$sp.'		<div class="flow-menu-js-drop"><a href="javascript:void(0);"><span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-only mw-ui-icon-ellipsis" aria-label="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-topic-action-menu-accessibility-name'),array()), 'encq', $in)).'"></span></a></div>
'.$sp.'		<ul class="mw-ui-button-container flow-list">
'.$sp.''.LR::p($cx, 'flow_moderation_actions_list', array(array($in),array('moderationType'=>'topic','moderationTarget'=>'title','moderationTemplate'=>'topic','moderationContainerClass'=>'flow-menu','moderationMwUiClass'=>'mw-ui-button','moderationIcons'=>true)),0, '			').'		</ul>
'.$sp.'	</div>
'.$sp.'</div>
';},
'flow_reply_form' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((!LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['unlock'])) ? $in['actions']['unlock'] : null), false)) ? '
'.$sp.'<form class="flow-post flow-reply-form"
'.$sp.'      method="POST"
'.$sp.'      action="'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['url'])) ? $in['actions']['reply']['url'] : null)).'"
'.$sp.'      id="flow-reply-'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'>
'.$sp.'	<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editToken']) ? $cx['sp_vars']['root']['rootBlock']['editToken'] : null)).'" />
'.$sp.'	<input type="hidden" name="topic_replyTo" value="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'" />
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'
'.$sp.''.LR::hbbch($cx, 'ifAnonymous', array(array(),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_anon_warning', array(array($in),array()),0, '		').'';}).'
'.$sp.'	<div class="flow-editor">
'.$sp.'		<textarea id="flow-post-'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'-form-content"
'.$sp.'		          name="topic_content"
'.$sp.'		          required
'.$sp.'		          class="mw-ui-input flow-click-interactive mw-editfont-'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editFont']) ? $cx['sp_vars']['root']['rootBlock']['editFont'] : null)).'"
'.$sp.'		          type="text"
'.$sp.'			      placeholder="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-reply-topic-title-placeholder',((isset($in['properties']) && is_array($in['properties']) && isset($in['properties']['topic-of-post-text-from-html'])) ? $in['properties']['topic-of-post-text-from-html'] : null)),array()), 'encq', $in)).'"
'.$sp.'		          data-role="content"
'.$sp.'
'.$sp.'		>'.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['submitted']) ? $cx['sp_vars']['root']['submitted'] : null), false)) ? ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['submitted']['postId']) ? $cx['sp_vars']['root']['submitted']['postId'] : null),'===',(($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::encq($cx, (isset($cx['sp_vars']['root']['submitted']['content']) ? $cx['sp_vars']['root']['submitted']['content'] : null)).'';}).'' : '').'</textarea>
'.$sp.'	</div>
'.$sp.'
'.$sp.'	<div class="flow-form-actions flow-form-collapsible">
'.$sp.'		<button data-role="submit"
'.$sp.'		        class="mw-ui-button mw-ui-progressive"
'.$sp.'		>'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-reply-link'),array()), 'encq', $in)).'</button>
'.$sp.'		<small class="flow-terms-of-use plainlinks">'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-terms-of-use-reply'),array()), 'encq', $in)).'</small>
'.$sp.'	</div>
'.$sp.'</form>
'.$sp.'' : '').'';},
'flow_topic' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-topic flow-load-interactive'.((LR::ifvar($cx, (($inary && isset($in['moderateState'])) ? $in['moderateState'] : null), false)) ? ' flow-topic-moderatestate-'.LR::encq($cx, (($inary && isset($in['moderateState'])) ? $in['moderateState'] : null)).' ' : '').''.((LR::ifvar($cx, (($inary && isset($in['isModerated'])) ? $in['isModerated'] : null), false)) ? ' flow-topic-moderated ' : '').''.((LR::ifvar($cx, (($inary && isset($in['isLocked'])) ? $in['isLocked'] : null), false)) ? 'flow-element-collapsible flow-element-collapsed' : '').'"
'.$sp.'     id="flow-topic-'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'     data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'     data-flow-load-handler="topic"
'.$sp.'     data-flow-toc-scroll-target=".flow-topic-titlebar"
'.$sp.'     data-flow-topic-timestamp-updated="'.LR::encq($cx, (($inary && isset($in['last_updated'])) ? $in['last_updated'] : null)).'"
'.$sp.'>
'.$sp.''.LR::p($cx, 'flow_topic_titlebar', array(array($in),array()),0, '	').'
'.$sp.''.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['posts']) ? $cx['sp_vars']['root']['posts'] : null), false)) ? ''.LR::sec($cx, (($inary && isset($in['replies'])) ? $in['replies'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'eachPost', array(array((isset($cx['sp_vars']['root']) ? $cx['sp_vars']['root'] : null),$in),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '				<!-- eachPost topic -->
'.$sp.'				'.LR::encq($cx, LR::hbch($cx, 'post', array(array((isset($cx['sp_vars']['root']) ? $cx['sp_vars']['root'] : null),$in),array()), 'encq', $in)).'
'.$sp.'';}).'';}).'' : '').'
'.$sp.''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['reply'])) ? $in['actions']['reply'] : null), false)) ? ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['submitted']['postId']) ? $cx['sp_vars']['root']['submitted']['postId'] : null),'===',(($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_reply_form', array(array($in),array()),0, '			').'';}, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'progressiveEnhancement', array(array(),array('type'=>'replace','target'=>'~ a')), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_reply_form', array(array($in),array()),0, '				').'';}).'			<a href="'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['url'])) ? $in['actions']['reply']['url'] : null)).'"
'.$sp.'			   title="'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['title'])) ? $in['actions']['reply']['title'] : null)).'"
'.$sp.'			   class="flow-ui-input-replacement-anchor mw-ui-input"
'.$sp.'			>'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-reply-topic-title-placeholder',((isset($in['properties']) && is_array($in['properties']) && isset($in['properties']['topic-of-post-text-from-html'])) ? $in['properties']['topic-of-post-text-from-html'] : null)),array()), 'encq', $in)).'</a>
'.$sp.'';}).'' : ''.LR::hbbch($cx, 'progressiveEnhancement', array(array(),array('type'=>'insert')), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_reply_form', array(array($in),array()),0, '			').'';}).'').'</div>
';},
'flow_topiclist_loop' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.LR::sec($cx, (($inary && isset($in['roots'])) ? $in['roots'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'eachPost', array(array((isset($cx['sp_vars']['root']) ? $cx['sp_vars']['root'] : null),$in),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_topic', array(array($in),array()),0, '		').'';}).'';}).'';},
'flow_load_more' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.((LR::ifvar($cx, (($inary && isset($in['loadMoreObject'])) ? $in['loadMoreObject'] : null), false)) ? '	<div class="flow-load-more">
'.$sp.'		<div class="flow-error-container">
'.$sp.'		</div>
'.$sp.'
'.$sp.'		<div class="flow-ui-loading"><div class="mw-ui-icon mw-ui-icon-before mw-ui-icon-only mw-ui-icon-advanced"></div></div>
'.$sp.'
'.$sp.'		<a data-flow-interactive-handler="apiRequest"
'.$sp.'		   data-flow-api-handler="'.LR::encq($cx, (($inary && isset($in['loadMoreApiHandler'])) ? $in['loadMoreApiHandler'] : null)).'"
'.$sp.'		   data-flow-api-target="< .flow-load-more"
'.$sp.'		   data-flow-load-handler="loadMore"
'.$sp.'		   data-flow-scroll-target="'.LR::encq($cx, (($inary && isset($in['loadMoreTarget'])) ? $in['loadMoreTarget'] : null)).'"
'.$sp.'		   data-flow-scroll-container="'.LR::encq($cx, (($inary && isset($in['loadMoreContainer'])) ? $in['loadMoreContainer'] : null)).'"
'.$sp.'		   data-flow-template="'.LR::encq($cx, (($inary && isset($in['loadMoreTemplate'])) ? $in['loadMoreTemplate'] : null)).'"
'.$sp.'		   href="'.LR::encq($cx, ((isset($in['loadMoreObject']) && is_array($in['loadMoreObject']) && isset($in['loadMoreObject']['url'])) ? $in['loadMoreObject']['url'] : null)).'"
'.$sp.'		   title="'.LR::encq($cx, ((isset($in['loadMoreObject']) && is_array($in['loadMoreObject']) && isset($in['loadMoreObject']['title'])) ? $in['loadMoreObject']['title'] : null)).'"
'.$sp.'		   class="mw-ui-button mw-ui-progressive flow-load-interactive flow-ui-fallback-element"><span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-article-invert"></span> '.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-load-more'),array()), 'encq', $in)).'</a>
'.$sp.'	</div>
'.$sp.'' : '	<div class="flow-no-more">
'.$sp.'		'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-no-more-fwd'),array()), 'encq', $in)).'
'.$sp.'	</div>
'.$sp.'	<div class="flow-bottom-spacer"></div>
'.$sp.'').'';});
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
    return ''.LR::p($cx, 'flow_board_navigation', array(array($in),array()),0).'
<div class="flow-board" data-flow-sortby="'.LR::encq($cx, (($inary && isset($in['sortby'])) ? $in['sortby'] : null)).'">
	<div class="flow-newtopic-container">
		<div class="flow-nojs">
			<a class="mw-ui-input mw-ui-input-large flow-ui-input-replacement-anchor"
				href="'.LR::encq($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['newtopic'])) ? $in['links']['newtopic'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-newtopic-start-placeholder'),array()), 'encq', $in)).'</a>
		</div>

		<div class="flow-js">
'.LR::p($cx, 'flow_newtopic_form', array(array($in),array('isOnFlowBoard'=>true)),0, '			').'		</div>
	</div>

	<div class="flow-topics">
'.LR::p($cx, 'flow_topiclist_loop', array(array($in),array()),0, '		').'
'.LR::p($cx, 'flow_load_more', array(array($in),array('loadMoreApiHandler'=>'loadMoreTopics','loadMoreTarget'=>'window','loadMoreContainer'=>'< .flow-topics','loadMoreTemplate'=>'flow_topiclist_loop.partial','loadMoreObject'=>((isset($in['links']['pagination']) && is_array($in['links']['pagination']) && isset($in['links']['pagination']['fwd'])) ? $in['links']['pagination']['fwd'] : null))),0, '		').'	</div>
</div>
';
};