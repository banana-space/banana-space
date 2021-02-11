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
        $article = $editPage->getArticle();
        $revRecord = $article->getPage()->getRevisionRecord();
		$content = $revRecord ? $revRecord->getContent(
			SlotRecord::MAIN,
			RevisionRecord::RAW
        ) : null;
        $text = $content instanceof TextContent ? $content->getText() : '';
        if ($text !== '') $text .= "\n";
        $quotedText = json_encode($text);
        $useBtex = $article->getTitle()->getNamespace() !== NS_TEMPLATE;

        $html = '
            <script>
                var require = {
                    paths: { vs: "/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs" },
                    "vs/nls": { availableLanguages: { "*": "zh-cn" } },
                };
            </script>
            <script src="/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs/loader.js"></script>
            <script>
                window.hasEditForm = true;
                window.oldText = $oldText;
                window.useBtex = $useBtex;
            </script>
            <script src="/static/scripts/btex-monaco-preload.js"></script>
            <script src="/static/scripts/btex-monaco/dist/btex-monaco.js"></script>
            <script src="/static/scripts/btex-monaco-postload.js"></script>';

        $html = str_replace("\n", ' ', $html);
        $html = preg_replace('#\s+#', ' ', $html);
        $html = str_replace('> ', '>', $html);
        $html = str_replace(' <', '<', $html);
        $html = str_replace('$oldText', $quotedText, $html);
        $html = str_replace('$useBtex', $useBtex ? 'true' : 'false', $html);

        $editPage->editFormTextBottom = $html;
    }
}
