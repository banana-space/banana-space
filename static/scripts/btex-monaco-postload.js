require(['btex-monaco'], function (btex) {
    const isMobileDevice = /mobi/i.test(window.navigator.userAgent);

    if (isMobileDevice) {
        $('textarea#wpTextbox1').addClass('b-unhide');
        return;
    }

    btex.setLocale('zh');

    // jquery might not have loaded yet
    docReady(function () {
        if (window.monacoEditorData) {
            let $textBox = $('textarea#wpTextbox1');
            $textBox.css('display', 'none');
    
            $div = $('<div id="btex-monaco-container" class="btex-monaco-container">');
            $div.insertBefore($textBox);
    
            let data = window.monacoEditorData;
            let editor = btex.createEditor(
                document.getElementById('btex-monaco-container'),
                $textBox.val(),
                data.oldText,
                data.lang,
                data.readOnly
            );
            
            if (data.preamble) {
                btex.addImport('/preamble', data.preamble);
            }

            editor.onDidChangeModelContent(function () {
                $textBox.val(editor.getValue());
            });

            window.monacoEditor = editor;
        }
    });
});

function docReady(fn) {
    let waitForJquery = function() {
        if (window.$) { fn(); return; }
        setTimeout(waitForJquery, 250);
    }
    if (document.readyState === "complete" || document.readyState === "interactive") {
        setTimeout(waitForJquery, 1);
    } else {
        document.addEventListener("DOMContentLoaded", waitForJquery);
    }
}    
