let disableCtrlS = false;
let justAfterScroll = false;

$(document).ready(function () {
  let $actions = $("#p-cactions");
  $actions.addClass("b-dropdown");
  $actions.children("#p-cactions-label").addClass("b-dropdown-toggle");
  $actions.children(".mw-portlet-body").addClass("b-dropdown-content");

  // Simulate hover on mobile devices
  $(".b-dropdown").click(function () {
    $content = $(this).find(".b-dropdown-content");
    setTimeout(() => {
      $content.addClass("b-dropdown-showing");
    }, 100);
  });
  $("body").click(function () {
    $(".b-dropdown-showing").removeClass("b-dropdown-showing");
  });

  // Syntax highlighting
  setInterval(() => {
    $(".code-btex:not(.highlighted)").each(function () {
      syntaxHighlightBtex($(this));
      $(this).addClass("highlighted");
    });
  }, 1000);

  // Highlight code in Module namespace
  if (window.require) {
    window.require(["vs/editor/editor.main"], function (monaco) {
      monaco.editor.defineTheme("ayu-light", {
        base: "vs",
        inherit: false,
        rules: [
          { token: "keyword", foreground: "f87000" },
          { token: "attribute.value", foreground: "f87000" },
          { token: "tag", foreground: "f8a000" },
          { token: "command.math", foreground: "f8a000" },
          { token: "delimiter", foreground: "40484c" },
          { token: "delimiter.css", foreground: "687074" },
          { token: "delimiter.html", foreground: "a0a8b0" },
          { token: "attribute.name", foreground: "30a0e0" },
          { token: "string", foreground: "70a000" },
          { token: "identifier", foreground: "40484c" },
          { token: "text", foreground: "40484c" },
          { token: "comment", foreground: "a0a0a0" },
        ],
      });
      $(".mw-code.mw-css").attr("data-lang", "css");
      $(".mw-code.mw-script").attr("data-lang", "lua");
      $(".mw-code[data-lang]").each(function () {
        monaco.editor.colorizeElement(this, { theme: "ayu-light" });
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
  let ctrl = /^mac/i.test(window.navigator.platform) ? "Cmd" : "Ctrl";
  const isMobileDevice = /mobi/i.test(window.navigator.userAgent);
  if ($("body.action-edit").length > 0 && !isMobileDevice) {
    $("#wikiPreview > .mw-content-ltr").html(
      '<span class="preview-hint">按 ' +
        ctrl +
        " + S 编译并预览<br/>双击预览结果跳转到代码</span>"
    );
    $("#wikiPreview").show();
  }

  // Inverse search
  $("#wikiPreview").dblclick(function () {
    let $span = $(this).find("span[data-pos]:hover");
    let lines = $span.attr("data-pos");
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
          endColumn: editor.getModel().getLineContent(line).length + 1,
        });
      }
    }
  });

  // Ctrl + S
  $(window).on("keydown", function (event) {
    if (window.monacoEditor && (event.ctrlKey || event.metaKey)) {
      switch (String.fromCharCode(event.which).toLowerCase()) {
        case "s":
          event.preventDefault();

          if (disableCtrlS) return;
          disableCtrlS = true;
          setTimeout(() => {
            disableCtrlS = false;
          }, 5000);

          // prevent scrolling
          let $html = $("html");
          let scrollTop = $html.scrollTop();
          $html.animate({ scrollTop }, 100);

          $("#wpPreview").click();
          break;
      }
    }
  });

  $("#wpPreview")
    .attr("accesskey", null)
    .attr("title", "显示预览 [" + ctrl + "+S]");

  // Side TOC
  let $toc = $('.btex-output .toc');
  if ($('body.action-view').length > 0 && $toc.length > 0) {
    let $sideToc = $toc.clone().removeClass('toc').addClass('side-toc');
    let $sideTocContainer = $('<div class="side-toc-container">').append($sideToc)
    $('.b-page-body').prepend($sideTocContainer);
    updateSideToc();

    $(window).scroll(updateSideToc);
    $(window).resize(updateSideToc);
  }

  // Scroll
  $('a[href^="#"]').click(function () {
    let $target = $(CSS.escape(decodeURIComponent($(this).attr("href"))).replace(/^\\#/, '#'));
    if ($target.length > 0) 
      goToHash($target, $(this).closest('.toc, .side-toc').length > 0);
  });

  if (window.location.hash) {
    let $target = $(decodeURIComponent(window.location.hash));
    if ($target.length > 0)
      goToHash($target);
  }

  setInterval(() => {
    // Proof expander
    if ($('.proof-collapsible:not(.initialized)').length > 0) {
      $('.proof-collapsible').addClass('initialized');
      $('.proof-expander.proof-expander-expanding').html('<svg height="16" width="16"><path stroke="#999" stroke-width="1.5" fill="none" d="M8 2 L14 8 L8 14"></path></svg> ');
      $('.proof-expander.proof-expander-collapsing').html('<svg height="16" width="16"><path stroke="#999" stroke-width="1.5" fill="none" d="M2 6 L8 12 L14 6"></path></svg> ');
      $('.proof-expander.proof-expander-ellipsis').html('<span style="font-size:40%">• • •</span>');
      $('.proof-expander').click(function () {
        let $this = $(this);
        let $parent = $this.closest('.proof-collapsible');
        if ($parent.length === 0) return;
    
        let wasCollapsed = !$this.hasClass('proof-expander-collapsing');
        $parent.removeClass('proof-collapsible-collapsed');
        $parent.removeClass('proof-collapsible-expanded');
        $parent.addClass(wasCollapsed ? 'proof-collapsible-expanded' : 'proof-collapsible-collapsed');
      });
    }
  }, 500);
});

function syntaxHighlightBtex($code) {
  String.prototype.recursiveReplace = function (a, b) {
    let x = this,
      y = this.replace(a, b),
      i = 0;
    while (x !== y && i++ < 10000) {
      x = y;
      y = y.replace(a, b);
    }
    return y;
  };
  $code[0].innerHTML = $code[0].innerHTML
    .split(/<br\s*\/?>/)
    .map((l) =>
      l
        .recursiveReplace(
          /((?:^|[^\\#])(?:\\\\)*)(\\#)(?!<)/g,
          '$1<span class="btex-function">\\#</span>'
        )
        .replace(
          /#+(?!<)[+-]?([a-zA-Z]+|&\w+;|.?)/g,
          '<span class="btex-argument">$&</span>'
        )
        .replace(/\\\\/g, '<span class="btex-function">\\\\</span>')
        .replace(
          /\\(?!<)(@*[a-zA-Z]+|@+|&\w+;|.?)/g,
          '<span class="btex-function">$&</span>'
        )
        .recursiveReplace(/(\%(?!<).*)<[^>]*>/g, "$1")
        .replace(/\%(?!<).*/g, '<span class="btex-comment">$&</span>')
        .replace(
          /(\\(?:begin|end|newenvironment|renewenvironment|newtheorem|env[ap]?def)<\/span>\*?\s*\{)([a-zA-Z@*]+)(\})/g,
          '$1<span class="btex-string">$2</span>$3'
        )
    )
    .join("<br>");
}

function goToHash($target, noHighlight) {
  let scrollTarget = $target.offset().top;
  let $highlight = $target.closest('tr.list-item, .block, h2, h3, h4');
  if ($highlight.length > 0)
    scrollTarget = $highlight.offset().top;
  $("html").animate({ scrollTop: scrollTarget - 80 }, 400);

  if (!noHighlight && $highlight.length > 0) {
    $highlight.css('outline', '5px solid #fff1a3');
    setTimeout(() => {
      $highlight.css('outline', '5px solid transparent');
    }, 3000);
    setTimeout(() => {
      $highlight.css('outline', '');
    }, 4000);
  }

  justAfterScroll = true;
  setTimeout(() => {
    justAfterScroll = false;
  }, 100);
}

function updateSideToc() {
  let $sideToc = $('.b-page-body .side-toc-container');
  if ($sideToc.length === 0) return;
  let $mainToc = $('.btex-output .toc');

  let $pageBody = $('.b-page-body');
  let windowWidth = window.innerWidth;
  if (windowWidth < 1400) {
    $sideToc.addClass('invisible');
    $mainToc.removeClass('invisible');
    $pageBody.removeClass('shifted-right').css('margin-left', '');
  } else {
    let tocLeft = $pageBody.offset().left - 250;
    let tocWidth = 250;

    if (windowWidth < 1640) {
      $pageBody.addClass('shifted-right').css('margin-left', '270px');
      tocLeft = 20;
    } else {
      $pageBody.removeClass('shifted-right').css('margin-left', '');
    }
    
    let scrollPosition = $(document).scrollTop();
    let tocBottom = scrollPosition + window.innerHeight - $pageBody.outerHeight();
    if (tocBottom < 50) tocBottom = 50;

    $mainToc.addClass('invisible');
    $sideToc.removeClass('invisible')
      .css('left', tocLeft + 'px')
      .css('width', tocWidth + 'px')
      .css('bottom', tocBottom + 'px');

    let $headings = $sideToc.find('ul li a');
    let currentHeading = -1;
    for (let i = 0; i < $headings.length; i++) {
      let $heading = $($headings[i])
      let $target = $(CSS.escape(decodeURIComponent($heading.attr("href"))).replace(/^\\#/, '#'));
      if ($target.length === 0) continue;
      let position = $target.offset().top;
      if (position < scrollPosition + 120) currentHeading = i;
      else break;
    }

    if (!justAfterScroll) {
      let $tocTitle = $sideToc.find('.toctitle');
      let $heading = currentHeading >= 0 ? $($headings[currentHeading]) : $tocTitle;
      if (!$heading.hasClass('highlighted')) {
        $headings.removeClass('highlighted');
        $tocTitle.removeClass('highlighted');
        $heading.addClass('highlighted');
        
        // Scroll TOC item into view
        let offset = $heading.offset().top - $sideToc.find('.side-toc').offset().top;
        let tocScroll = $sideToc.scrollTop();
        let targetScroll = tocScroll;
        if (offset < tocScroll + 50)
          targetScroll = offset - 100;
        if (offset > tocScroll + $sideToc.innerHeight() - 130) 
          targetScroll = offset - $sideToc.innerHeight() + 180;
        if (targetScroll !== tocScroll)
          $sideToc.animate({ scrollTop: targetScroll }, { duration: 400, queue: false });
      }
    }
  }
}
