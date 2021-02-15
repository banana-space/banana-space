# Banana Space

Banana Space 是将 MediaWiki 与 TeX 语法结合的项目，给用户提供使用 TeX 语法写作和讨论的平台。

## 内容

除了包含 MediaWiki 源代码外，还有

* 几个扩展，来自 [mediawiki.org](https://www.mediawiki.org)；
* `TeXParser` 扩展；
* `Banana` 皮肤。

目前 `LocalSettings.php` 除自动生成的部分外，还有如下设置。

``` php
wfLoadSkin( 'Banana' );
$wgDefaultSkin = 'Banana';

wfLoadExtension( 'Echo' );
wfLoadExtension( 'Flow' );
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'TemplateStyles' );
wfLoadExtension( 'Thanks' );
wfLoadExtension( 'TeXParser' );

$wgNamespaceContentModels[NS_TALK]           = 'flow-board';
$wgNamespaceContentModels[NS_USER_TALK]      = 'flow-board';
$wgNamespaceContentModels[NS_FILE_TALK]      = 'flow-board';
$wgNamespaceContentModels[NS_HELP_TALK]      = 'flow-board';
$wgNamespaceContentModels[NS_PROJECT_TALK]   = 'flow-board';
$wgNamespaceContentModels[NS_CATEGORY_TALK]  = 'flow-board';
$wgNamespaceContentModels[NS_TEMPLATE_TALK]  = 'flow-board';
$wgNamespaceContentModels[NS_MEDIAWIKI_TALK] = 'flow-board';
$wgNamespaceContentModels[829]               = 'flow-board'; // NS_MODULE_TALK

$wgNamespaceAliases['BS'] = NS_PROJECT;

$wgNamespacesToBeSearchedDefault[100] = true; // NS_NOTES

$wgFileExtensions = [ 'png', 'gif', 'jpg', 'jpeg', 'pdf', 'svg' ];

$wgNamespaceProtection[828] = ['edit-module']; // NS_MODULE
$wgGroupPermissions['sysop']['edit-module'] = true;
$wgGroupPermissions['sysop']['deletelogentry'] = true;
$wgGroupPermissions['sysop']['deleterevision'] = true;

$wgPageLanguageUseDB = true;

$wgDefaultUserOptions['uselivepreview'] = 1;
```

## 运行步骤

* 通过 MediaWiki 安装向导生成 `LocalSettings.php` 文件，在其末尾添加以上代码，然后放在项目目录。
* 运行 [bTeX](https://github.com/banana-space/btex)。
* 运行 PHP 本地服务器进行调试。

如遇数据库错误，可在 `maintenance` 目录中运行
``` bash
php update.php
```
以刷新数据库。
