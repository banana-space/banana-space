require(['btex-monaco'], function (btex) {
    const isMobileDevice = /mobi/i.test(window.navigator.userAgent);

    if (isMobileDevice) {
        $('textarea#wpTextbox1').addClass('b-unhide');
        return;
    }

    btex.setLocale('zh');

    $(document).ready(function () {
        if (window.hasEditForm) {
            let $textBox = $('textarea#wpTextbox1');
            $textBox.css('display', 'none');
    
            $div = $('<div id="btex-monaco-container" class="btex-monaco-container">');
            $div.insertBefore($textBox);
    
            let editor = btex.createEditor(
                document.getElementById('btex-monaco-container'),
                $textBox.val(),
                window.oldText,
                window.useBtex
            );
            // todo: set editor diff source to window.oldText
            editor.onDidChangeModelContent(function () {
                $textBox.val(editor.getValue());
            });
        }
    });
});
