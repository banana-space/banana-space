let hideNotificationsTimeout;
let disableCtrlS = false;

$(document).ready(function () {
  let $actions = $("#p-cactions");
  $actions.addClass("b-dropdown");
  $actions.children("#p-cactions-label").addClass("b-dropdown-toggle");
  $actions.children(".mw-portlet-body").addClass("b-dropdown-content");

  // Simulate hover on mobile devices
  $('.b-dropdown').click(function () {
    $content = $(this).find('.b-dropdown-content');
    setTimeout(() => {
      $content.addClass('b-dropdown-showing');
    }, 100);
  });
  $('body').click(function () {
    $('.b-dropdown-showing').removeClass('b-dropdown-showing');
  });

  // Syntax highlighting
  setInterval(() => {
    $('.code-btex:not(.highlighted)').each( function () {
      syntaxHighlightBtex( $( this ) );
      $( this ).addClass('highlighted');
    } );
  }, 1000);

  // Highlight code in Module namespace
  if (window.require) {
    window.require(['vs/editor/editor.main'], function (monaco) {
      monaco.editor.defineTheme('ayu-light', {
        base: 'vs',
        inherit: false,
        rules: [
          { token: 'keyword', foreground: 'f87000' },
          { token: 'attribute.value', foreground: 'f87000' },
          { token: 'tag', foreground: 'f8a000' },
          { token: 'command.math', foreground: 'f8a000' },
          { token: 'delimiter', foreground: '40484c' },
          { token: 'delimiter.css', foreground: '687074' },
          { token: 'delimiter.html', foreground: 'a0a8b0' },
          { token: 'attribute.name', foreground: '30a0e0' },
          { token: 'string', foreground: '70a000' },
          { token: 'identifier', foreground: '40484c' },
          { token: 'text', foreground: '40484c' },
          { token: 'comment', foreground: 'a0a0a0' },
        ],
      });
      $('.mw-code.mw-css').attr('data-lang', 'css');
      $('.mw-code.mw-script').attr('data-lang', 'lua');
      $('.mw-code[data-lang]').each(function () {
        monaco.editor.colorizeElement(this, {theme: 'ayu-light'});
      });
    });
  }

  // Resize monaco editor
  $(window).resize(function () {
    if (window.monacoEditor) {
      window.monacoEditor.layout();
    }
  });

  // Show preview hint
  let ctrl = /^mac/i.test(window.navigator.platform) ? 'Cmd' : 'Ctrl';
  const isMobileDevice = /mobi/i.test(window.navigator.userAgent);
  if ($('body.action-edit').length > 0 && !isMobileDevice) {
    $('#wikiPreview > div').html('<span class="preview-hint">按 ' + ctrl + ' + S 编译并预览<br/>双击预览结果跳转到代码</span>');
    $('#wikiPreview').show();
  }

  // Inverse search
  $('#wikiPreview').dblclick(function () {
    let $span = $(this).find('span[data-pos]:hover');
    let lines = $span.attr('data-pos');
    if (lines) {
      let line = parseInt(lines);
      let editor = window.monacoEditor;
      if (editor) {
        editor.focus();
        editor.revealLineInCenterIfOutsideViewport(line);
        editor.setSelection({
          startLineNumber: line,
          endLineNumber: line,
          startColumn: 1,
          endColumn: editor.getModel().getLineContent(line).length + 1
        });
      }
    }
  });

  // Ctrl + S
  $(window).on('keydown', function(event) {
    if (window.monacoEditor && (event.ctrlKey || event.metaKey)) {
      switch (String.fromCharCode(event.which).toLowerCase()) {
        case 's':
          event.preventDefault();
          
          if (disableCtrlS) return;
          disableCtrlS = true;
          setTimeout(() => {
            disableCtrlS = false;
          }, 5000);

          // prevent scrolling
          let $html = $('html');
          let scrollTop = $html.scrollTop();
          $html.animate({scrollTop}, 100);

          $('#wpPreview').click();
          break;
      }
    }
  });

  $('#wpPreview').attr('accesskey', null).attr('title', '显示预览 [' + ctrl + '+S]');

  // Scroll
  $('a[href^="#"]').click(function () {
    let $target = $(decodeURIComponent($(this).attr('href')));
    if ($target.length > 0) {
      $('html').animate({scrollTop: $target.offset().top - 80}, 400);
    }
  });

  if (window.location.hash) {
    let $target = $(decodeURIComponent(window.location.hash));
    if ($target.length > 0)
      $('html').animate({scrollTop: $target.offset().top - 80}, 400);
  }
});

function syntaxHighlightBtex($code) {
  String.prototype.recursiveReplace = function(a, b) {
    let x = this, y = this.replace(a, b), i = 0;
    while (x !== y && i++ < 10000) { x = y; y = y.replace(a, b); }
    return y;
  }
  console.log($code[0].innerHTML.split('\n'));
  $code[0].innerHTML = $code[0].innerHTML.split('\n').map(l => l
    .recursiveReplace(/((?:^|[^\\#])(?:\\\\)*)(\\#)(?!<)/g, '$1<span class="btex-function">\\#</span>')
    .replace(/#+(?!<)[+-]?([a-zA-Z]+|&\w+;|.?)/g, '<span class="btex-argument">$&</span>')
    .replace(/\\\\/g, '<span class="btex-function">\\\\</span>')
    .replace(/\\(?!<)(@*[a-zA-Z]+|@+|&\w+;|.?)/g, '<span class="btex-function">$&</span>')
    .recursiveReplace(/(\%(?!<).*)<[^>]*>/g, '$1')
    .replace(/\%(?!<).*/g, '<span class="btex-comment">$&</span>')
  ).join('\n');
}
