<?php use \LightnCandy\SafeString as SafeString;use \LightnCandy\Runtime as LR;return function ($in = null, $options = null) {
    $helpers = array(            'l10n' => 'Flow\TemplateHelper::l10n',
            'uuidTimestamp' => 'Flow\TemplateHelper::uuidTimestamp',
            'html' => 'Flow\TemplateHelper::htmlHelper',
            'post' => 'Flow\TemplateHelper::post',
            'l10nParse' => 'Flow\TemplateHelper::l10nParse',
            'concat' => 'Flow\TemplateHelper::concat',
            'linkWithReturnTo' => 'Flow\TemplateHelper::linkWithReturnTo',
            'escapeContent' => 'Flow\TemplateHelper::escapeContent',
            'getSaveOrPublishMessage' => 'Flow\TemplateHelper::getSaveOrPublishMessage',
            'eachPost' => 'Flow\TemplateHelper::eachPost',
            'ifAnonymous' => 'Flow\TemplateHelper::ifAnonymous',
            'ifCond' => 'Flow\TemplateHelper::ifCond',
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
'flow_post_author' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<span class="flow-author">
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['links'])) ? $in['links'] : null), false)) ? ''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['userpage'])) ? $in['links']['userpage'] : null), false)) ? '			<a href="'.LR::encq($cx, ((isset($in['links']['userpage']) && is_array($in['links']['userpage']) && isset($in['links']['userpage']['url'])) ? $in['links']['userpage']['url'] : null)).'"
'.$sp.'			   '.((!LR::ifvar($cx, (($inary && isset($in['name'])) ? $in['name'] : null), false)) ? 'title="'.LR::encq($cx, ((isset($in['links']['userpage']) && is_array($in['links']['userpage']) && isset($in['links']['userpage']['title'])) ? $in['links']['userpage']['title'] : null)).'"' : '').'
'.$sp.'			   class="'.((!LR::ifvar($cx, ((isset($in['links']['userpage']) && is_array($in['links']['userpage']) && isset($in['links']['userpage']['exists'])) ? $in['links']['userpage']['exists'] : null), false)) ? 'new ' : '').'mw-userlink">
'.$sp.'' : '').''.((LR::ifvar($cx, (($inary && isset($in['name'])) ? $in['name'] : null), false)) ? '<bdi>'.LR::encq($cx, (($inary && isset($in['name'])) ? $in['name'] : null)).'</bdi>' : ''.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-anonymous'),array()), 'encq', $in)).'').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['userpage'])) ? $in['links']['userpage'] : null), false)) ? '</a>' : '').'<span class="mw-usertoollinks flow-pipelist">
'.$sp.'			('.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['talk'])) ? $in['links']['talk'] : null), false)) ? '<span><a href="'.LR::encq($cx, ((isset($in['links']['talk']) && is_array($in['links']['talk']) && isset($in['links']['talk']['url'])) ? $in['links']['talk']['url'] : null)).'"
'.$sp.'				    class="'.((!LR::ifvar($cx, ((isset($in['links']['talk']) && is_array($in['links']['talk']) && isset($in['links']['talk']['exists'])) ? $in['links']['talk']['exists'] : null), false)) ? 'new ' : '').'"
'.$sp.'				    title="'.LR::encq($cx, ((isset($in['links']['talk']) && is_array($in['links']['talk']) && isset($in['links']['talk']['title'])) ? $in['links']['talk']['title'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('talkpagelinktext'),array()), 'encq', $in)).'</a></span>' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['contribs'])) ? $in['links']['contribs'] : null), false)) ? '<span><a href="'.LR::encq($cx, ((isset($in['links']['contribs']) && is_array($in['links']['contribs']) && isset($in['links']['contribs']['url'])) ? $in['links']['contribs']['url'] : null)).'" title="'.LR::encq($cx, ((isset($in['links']['contribs']) && is_array($in['links']['contribs']) && isset($in['links']['contribs']['title'])) ? $in['links']['contribs']['title'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('contribslink'),array()), 'encq', $in)).'</a></span>' : '').''.((LR::ifvar($cx, ((isset($in['links']) && is_array($in['links']) && isset($in['links']['block'])) ? $in['links']['block'] : null), false)) ? '<span><a class="'.((!LR::ifvar($cx, ((isset($in['links']['block']) && is_array($in['links']['block']) && isset($in['links']['block']['exists'])) ? $in['links']['block']['exists'] : null), false)) ? 'new ' : '').'"
'.$sp.'				   href="'.LR::encq($cx, ((isset($in['links']['block']) && is_array($in['links']['block']) && isset($in['links']['block']['url'])) ? $in['links']['block']['url'] : null)).'"
'.$sp.'				   title="'.LR::encq($cx, ((isset($in['links']['block']) && is_array($in['links']['block']) && isset($in['links']['block']['title'])) ? $in['links']['block']['title'] : null)).'">'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('blocklink'),array()), 'encq', $in)).'</a></span>' : '').')
'.$sp.'		</span>
'.$sp.'' : '').'</span>
';},
'flow_post_moderation_state' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<span class="plainlinks">'.((LR::ifvar($cx, (($inary && isset($in['replyToId'])) ? $in['replyToId'] : null), false)) ? ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'-post-content'),array()), 'raw', $in),((isset($in['moderator']) && is_array($in['moderator']) && isset($in['moderator']['name'])) ? $in['moderator']['name'] : null),((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)),array()), 'encq', $in)).'' : ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array(LR::hbch($cx, 'concat', array(array('flow-',(($inary && isset($in['moderateState'])) ? $in['moderateState'] : null),'-title-content'),array()), 'raw', $in),((isset($in['moderator']) && is_array($in['moderator']) && isset($in['moderator']['name'])) ? $in['moderator']['name'] : null),((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)),array()), 'encq', $in)).'').'</span>
';},
'flow_post_meta_actions' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-post-meta">
'.$sp.'	<span class="flow-post-meta-actions">
'.$sp.''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['reply'])) ? $in['actions']['reply'] : null), false)) ? '			<a href="'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['url'])) ? $in['actions']['reply']['url'] : null)).'"
'.$sp.'			   title="'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['title'])) ? $in['actions']['reply']['title'] : null)).'"
'.$sp.'			   class="mw-ui-anchor mw-ui-progressive mw-ui-quiet flow-reply-link"
'.$sp.'			   data-flow-eventlog-schema="FlowReplies"
'.$sp.'			   data-flow-eventlog-action="initiate"
'.$sp.'			   data-flow-eventlog-entrypoint="reply-post"
'.$sp.'			   data-flow-eventlog-forward="
'.$sp.'				   < .flow-post:not([data-flow-post-max-depth=\'1\']) .flow-reply-form [data-role=\'cancel\'],
'.$sp.'				   < .flow-post:not([data-flow-post-max-depth=\'1\']) .flow-reply-form [data-role=\'submit\']
'.$sp.'			   "
'.$sp.'			>'.LR::encq($cx, ((isset($in['actions']['reply']) && is_array($in['actions']['reply']) && isset($in['actions']['reply']['text'])) ? $in['actions']['reply']['text'] : null)).'</a>
'.$sp.'' : '').''.((LR::ifvar($cx, ((isset($in['actions']) && is_array($in['actions']) && isset($in['actions']['thank'])) ? $in['actions']['thank'] : null), false)) ? '			<a class="mw-ui-anchor mw-ui-progressive mw-ui-quiet mw-thanks-flow-thank-link"
'.$sp.'			   href="'.LR::encq($cx, ((isset($in['actions']['thank']) && is_array($in['actions']['thank']) && isset($in['actions']['thank']['url'])) ? $in['actions']['thank']['url'] : null)).'"
'.$sp.'			   title="'.LR::encq($cx, ((isset($in['actions']['thank']) && is_array($in['actions']['thank']) && isset($in['actions']['thank']['title'])) ? $in['actions']['thank']['title'] : null)).'">'.LR::encq($cx, ((isset($in['actions']['thank']) && is_array($in['actions']['thank']) && isset($in['actions']['thank']['text'])) ? $in['actions']['thank']['text'] : null)).'</a>
'.$sp.'' : '').'	</span>
'.$sp.'
'.$sp.'	<span class="flow-post-timestamp">
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isOriginalContent'])) ? $in['isOriginalContent'] : null), false)) ? '			<a href="'.LR::encq($cx, ((isset($in['links']['topic-history']) && is_array($in['links']['topic-history']) && isset($in['links']['topic-history']['url'])) ? $in['links']['topic-history']['url'] : null)).'" class="flow-timestamp-anchor">
'.$sp.'				'.LR::encq($cx, LR::hbch($cx, 'uuidTimestamp', array(array((($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), 'encq', $in)).'
'.$sp.'			</a>
'.$sp.'' : '			<span>
'.$sp.''.LR::hbbch($cx, 'ifCond', array(array(((isset($in['creator']) && is_array($in['creator']) && isset($in['creator']['name'])) ? $in['creator']['name'] : null),'===',((isset($in['lastEditUser']) && is_array($in['lastEditUser']) && isset($in['lastEditUser']['name'])) ? $in['lastEditUser']['name'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '					'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-edited'),array()), 'encq', $in)).'
'.$sp.'';}, function($cx, $in)use($sp){$inary=is_array($in);return '					'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-edited-by',((isset($in['lastEditUser']) && is_array($in['lastEditUser']) && isset($in['lastEditUser']['name'])) ? $in['lastEditUser']['name'] : null)),array()), 'encq', $in)).'
'.$sp.'';}).'			</span>
'.$sp.'			<a href="'.LR::encq($cx, ((isset($in['links']['diff-prev']) && is_array($in['links']['diff-prev']) && isset($in['links']['diff-prev']['url'])) ? $in['links']['diff-prev']['url'] : null)).'" class="flow-timestamp-anchor">'.LR::encq($cx, LR::hbch($cx, 'uuidTimestamp', array(array((($inary && isset($in['lastEditId'])) ? $in['lastEditId'] : null)),array()), 'encq', $in)).'</a>
'.$sp.'').'	</span>
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
'flow_post_actions' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-menu flow-menu-hoverable">
'.$sp.'	<div class="flow-menu-js-drop"><a href="javascript:void(0);"><span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-only mw-ui-icon-ellipsis" aria-label="'.LR::encq($cx, LR::hbch($cx, 'l10n', array(array('flow-post-action-menu-accessibility-name'),array()), 'encq', $in)).'"></span></a></div>
'.$sp.'	<ul class="mw-ui-button-container flow-list">
'.$sp.''.LR::p($cx, 'flow_moderation_actions_list', array(array($in),array('moderationType'=>'post','moderationTarget'=>'post','moderationTemplate'=>'post','moderationContainerClass'=>'flow-menu','moderationMwUiClass'=>'mw-ui-button','moderationIcons'=>true)),0, '		').'	</ul>
'.$sp.'</div>
';},
'flow_post_inner' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-post-main">
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'
'.$sp.''.LR::wi($cx, (($inary && isset($in['creator'])) ? $in['creator'] : null), null, $in, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_post_author', array(array($in),array()),0, '		').'';}).'
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isModerated'])) ? $in['isModerated'] : null), false)) ? '		<div class="flow-moderated-post-content">
'.$sp.''.LR::p($cx, 'flow_post_moderation_state', array(array($in),array()),0, '			').'		</div>
'.$sp.'' : '').'
'.$sp.'	<article class="flow-post-content mw-parser-output">'.LR::encq($cx, LR::hbch($cx, 'escapeContent', array(array(((isset($in['content']) && is_array($in['content']) && isset($in['content']['format'])) ? $in['content']['format'] : null),((isset($in['content']) && is_array($in['content']) && isset($in['content']['content'])) ? $in['content']['content'] : null)),array()), 'encq', $in)).'</article>
'.$sp.'
'.$sp.''.LR::p($cx, 'flow_post_meta_actions', array(array($in),array()),0, '	').''.LR::p($cx, 'flow_post_actions', array(array($in),array()),0, '	').'</div>
';},
'flow_anon_warning' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-anon-warning">
'.$sp.'	<div class="flow-anon-warning-mobile">
'.$sp.''.LR::hbbch($cx, 'tooltip', array(array(),array('positionClass'=>'down','contextClass'=>'progressive','extraClass'=>'flow-form-collapsible','isBlock'=>true)), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-anon-warning',LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin'),array()), 'raw', $in),LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin/signup'),array()), 'raw', $in)),array()), 'encq', $in)).'';}).'	</div>
'.$sp.'
'.$sp.''.LR::hbbch($cx, 'progressiveEnhancement', array(array(),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '		<div class="flow-anon-warning-desktop">
'.$sp.''.LR::hbbch($cx, 'tooltip', array(array(),array('positionClass'=>'left','contextClass'=>'progressive','extraClass'=>'flow-form-collapsible','isBlock'=>true)), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-anon-warning',LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin'),array()), 'raw', $in),LR::hbch($cx, 'linkWithReturnTo', array(array('Special:UserLogin/signup'),array()), 'raw', $in)),array()), 'encq', $in)).'';}).'		</div>
'.$sp.'';}).'</div>
';},
'flow_edit_post' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<form class="flow-edit-post-form"
'.$sp.'	method="POST"
'.$sp.'	action="'.LR::encq($cx, ((isset($in['actions']['edit']) && is_array($in['actions']['edit']) && isset($in['actions']['edit']['url'])) ? $in['actions']['edit']['url'] : null)).'"
'.$sp.'>
'.$sp.''.LR::p($cx, 'flow_errors', array(array($in),array()),0, '	').'	<input type="hidden" name="wpEditToken" value="'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editToken']) ? $cx['sp_vars']['root']['rootBlock']['editToken'] : null)).'" />
'.$sp.'	<input type="hidden" name="topic_prev_revision" value="'.LR::encq($cx, (($inary && isset($in['revisionId'])) ? $in['revisionId'] : null)).'" />
'.$sp.''.LR::hbbch($cx, 'ifAnonymous', array(array(),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_anon_warning', array(array($in),array()),0, '		').'';}).'
'.$sp.'	<div class="flow-editor">
'.$sp.'		<textarea name="topic_content" class="mw-ui-input flow-form-collapsible mw-editfont-'.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['editFont']) ? $cx['sp_vars']['root']['rootBlock']['editFont'] : null)).'" data-role="content">'.((LR::ifvar($cx, (isset($cx['sp_vars']['root']['rootBlock']['submitted']['content']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['content'] : null), false)) ? ''.LR::encq($cx, (isset($cx['sp_vars']['root']['rootBlock']['submitted']['content']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['content'] : null)).'' : ''.LR::encq($cx, ((isset($in['content']) && is_array($in['content']) && isset($in['content']['content'])) ? $in['content']['content'] : null)).'').'</textarea>
'.$sp.'	</div>
'.$sp.'
'.$sp.'	<div class="flow-form-actions flow-form-collapsible">
'.$sp.'		<button class="mw-ui-button mw-ui-progressive">'.LR::encq($cx, LR::hbch($cx, 'getSaveOrPublishMessage', array(array(),array('save'=>'flow-post-action-edit-post-submit','publish'=>'flow-post-action-edit-post-submit-publish')), 'encq', $in)).'</button>
'.$sp.'		<small class="flow-terms-of-use plainlinks">'.LR::encq($cx, LR::hbch($cx, 'l10nParse', array(array('flow-terms-of-use-edit'),array()), 'encq', $in)).'</small>
'.$sp.'	</div>
'.$sp.'</form>
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
'flow_post_replies' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.'<div class="flow-replies">
'.$sp.''.LR::sec($cx, (($inary && isset($in['replies'])) ? $in['replies'] : null), null, $in, true, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'eachPost', array(array((isset($cx['sp_vars']['root']['rootBlock']) ? $cx['sp_vars']['root']['rootBlock'] : null),$in),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return '			<!-- eachPost nested replies -->
'.$sp.'			'.LR::encq($cx, LR::hbch($cx, 'post', array(array((isset($cx['sp_vars']['root']['rootBlock']) ? $cx['sp_vars']['root']['rootBlock'] : null),$in),array()), 'encq', $in)).'
'.$sp.'';}).'';}).''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['rootBlock']['submitted']['postId']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['postId'] : null),'===',(($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['rootBlock']['submitted']['action']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['action'] : null),'===','reply'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_reply_form', array(array($in),array()),0, '			').'';}).'';}).'</div>
';},
'flow_post_partial' => function ($cx, $in, $sp) {$inary=is_array($in);return ''.$sp.''.LR::wi($cx, (($inary && isset($in['revision'])) ? $in['revision'] : null), null, $in, function($cx, $in)use($sp){$inary=is_array($in);return '	<div id="flow-post-'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'	     class="flow-post'.((LR::ifvar($cx, (($inary && isset($in['isMaxThreadingDepth'])) ? $in['isMaxThreadingDepth'] : null), false)) ? ' flow-post-max-depth' : '').'"
'.$sp.'	     data-flow-id="'.LR::encq($cx, (($inary && isset($in['postId'])) ? $in['postId'] : null)).'"
'.$sp.'	>
'.$sp.''.((LR::ifvar($cx, (($inary && isset($in['isModerated'])) ? $in['isModerated'] : null), false)) ? ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['rootBlock']['submitted']['showPostId']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['showPostId'] : null),'===',(($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_post_inner', array(array($in),array()),0, '				').'';}, function($cx, $in)use($sp){$inary=is_array($in);return '				<div class="flow-post-main flow-post-moderated">
'.$sp.'					<span class="flow-moderated-post-content">
'.$sp.''.LR::p($cx, 'flow_post_moderation_state', array(array($in),array()),0, '						').'					</span>
'.$sp.'				</div>
'.$sp.'';}).'' : ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['rootBlock']['submitted']['action']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['action'] : null),'===','edit-post'),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::hbbch($cx, 'ifCond', array(array((isset($cx['sp_vars']['root']['rootBlock']['submitted']['postId']) ? $cx['sp_vars']['root']['rootBlock']['submitted']['postId'] : null),'===',(($inary && isset($in['postId'])) ? $in['postId'] : null)),array()), $in, false, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_edit_post', array(array($in),array()),0, '					').'';}, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_post_inner', array(array($in),array()),0, '					').'';}).'';}, function($cx, $in)use($sp){$inary=is_array($in);return ''.LR::p($cx, 'flow_post_inner', array(array($in),array()),0, '				').'';}).'').'
'.$sp.''.LR::p($cx, 'flow_post_replies', array(array($in),array()),0, '		').'	</div>
'.$sp.'';}).'';});
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
    return ''.LR::p($cx, 'flow_post_partial', array(array($in),array()),0).'';
};