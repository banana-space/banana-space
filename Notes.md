## Notes

We avoid modifying the MediaWiki source code whenever possible. The exceptions are listed as follows. These need to be taken care of when updating MediaWiki.

* The `Flow` extension uses a 2-pane layout for wide screens. But we always have a narrow container and we don't want the 2-pane layout. To fix it, add the following lines to `/extensions/Flow/modules/styles/flow.variables.less`:
    ``` less
    @medium: 1000000px;
    @large: 1000000px;
    @xlarge: 1000000px;
    ```

* I have changed the following lines of `/languages/messages/MessagesZh_hans.php`, with whitespaces between numbers and Chinese.
    ``` php
    $dateFormats = [
        'zh time' => 'H:i',
        'zh date' => 'Y 年 n 月 j 日 (l)',
        'zh both' => 'Y 年 n 月 j 日 (D) H:i',
    ];
    ```
    The js counterpart `resources/lib/moment/locale/zh-cn.js` is also changed.

* I have changed `/extensions/Flow/includes/Conversion/Utils.php`, at Line 62, to enable KaTeX in discussion headers.

* Line 24 of `resources/lib/CLDRPluralRuleParser/CLDRPluralRuleParser.js` is changed from `if (...)` to `if (false && ...)` -- it is incompatible with bTeX monaco editor. (It shows a console warning and prevents that module being loaded.)
