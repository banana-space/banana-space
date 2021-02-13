<?php

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class EditorHooks {
    public static function onEditPageShowEditFormInitial( EditPage $editPage, OutputPage $output ) {
        self::injectBtexMonaco( $editPage, false );
    }

    public static function onEditPageShowReadOnlyFormInitial( EditPage $editPage, OutputPage $output ) {
        self::injectBtexMonaco( $editPage, true );
    }

    private static function injectBtexMonaco( EditPage $editPage, bool $readOnly ) {
        $json = [];

        $article = $editPage->getArticle();
        $revRecord = $article->getPage()->getRevisionRecord();
		$content = $revRecord ? $revRecord->getContent(
			SlotRecord::MAIN,
			RevisionRecord::RAW
        ) : null;
        $text = $content instanceof TextContent ? $content->getText() : '';
        if ($text !== '') $text .= "\n";
        $json['oldText'] = $text;

        $title = $article->getTitle();
        if ($title->getNamespace() === NS_NOTES) {
            $preamble = '';
            $subpageInfo = SubpageHandler::getSubpageInfo($title);
            if ($subpageInfo !== false) {
                $preambleName = $subpageInfo['parent_title'] . '/preamble';
                $preambleTitle = Title::makeTitle(NS_NOTES, $preambleName);
                if ($preambleTitle->exists()) {
                    $content = WikiPage::newFromID($preambleTitle->getArticleID())->getContent();
                    if ($content instanceof TextContent) {
                        $preamble = $content->getText();
                    }
                }
            }
            $json['preamble'] = $preamble;
        }

        $lang = 'btex';
        $ns = $article->getTitle()->getNamespace();
        $contentModel = $article->getTitle()->getContentModel();
        if ($ns === NS_TEMPLATE || $ns === NS_MODULE) $lang = '';
        if ($contentModel === 'Scribunto') $lang = 'lua';
        if ($contentModel === 'css' || $contentModel === 'sanitized-css') $lang = 'css';
        $json['lang'] = $lang;

        $json['readOnly'] = $readOnly;

        $html = '
            <script>
                var require = {
                    paths: { vs: "/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs" },
                    "vs/nls": { availableLanguages: { "*": "zh-cn" } },
                };
            </script>
            <script src="/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs/loader.js"></script>
            <script>
                window.monacoEditorData = $json;
            </script>
            <script src="/static/scripts/btex-monaco-preload.js"></script>
            <script src="/static/scripts/btex-monaco/dist/btex-monaco.js"></script>
            <script src="/static/scripts/btex-monaco-postload.js"></script>';

        $html = str_replace("\n", ' ', $html);
        $html = preg_replace('#\s+#', ' ', $html);
        $html = str_replace('> ', '>', $html);
        $html = str_replace(' <', '<', $html);
        $html = str_replace('$json', json_encode($json), $html);

        $editPage->editFormTextBottom = $html;
    }
}
