# Banana Space

Banana Space 是将 MediaWiki 与 TeX 语法结合的项目，给用户提供使用 TeX 语法写作和讨论的平台。

## 内容

除了包含 MediaWiki 源代码外，还有

* 几个扩展，来自 [mediawiki.org](https://www.mediawiki.org)；
* `Banana` 扩展；
* `BananaSkin` 皮肤。

目前 `LocalSettings.php` 除自动生成的部分外，还有如下设置。

``` php
wfLoadSkin( 'BananaSkin' );
$wgDefaultSkin = 'BananaSkin';

wfLoadExtension( 'Echo' );
wfLoadExtension( 'Flow' );
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'HeadScript' );
wfLoadExtension( 'Description2' );
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'TemplateStyles' );
wfLoadExtension( 'Elastica' );
wfLoadExtension( 'CirrusSearch' );
wfLoadExtension( 'Banana' );

$wgSearchType = 'CirrusSearch';
$wgCirrusSearchUseIcuFolding = true;

$wgObjectCaches['redis'] = [
    'class'                => 'RedisBagOStuff',
    'servers'              => [ '127.0.0.1:6379' ],
];
$wgMainCacheType = 'redis';
$wgSessionCacheType = 'redis';

$wgJobTypeConf['default'] = [
    'class'          => 'JobQueueRedis',
    'redisServer'    => '127.0.0.1:6379',
    'redisConfig'    => [],
    'claimTTL'       => 3600,
    'daemonized'     => true
];

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

$wgHiddenPrefs += [
	'editfont', 'editsection', 'editsectiononrightclick', 'fancysig', 'gender', 'language', 'nickname', 'numberheadings', 'previewontop', 'showtoc', 'skin', 'stubthreshold', 'underline'
];
```

## 运行步骤

* 通过 MediaWiki 安装向导生成 `LocalSettings.php` 文件，在其末尾添加以上代码，然后放在项目目录。

* 配置搜索引擎:

    * 安装 [Redis](https://redis.io/) 和 [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html)
    6.8.x。

    * 安装 Elasticsearch 插件 `analysis-icu`、`analysis-smartcn`、`analysis-stconvert`。

    * (更新 MediaWiki 时注意，使用时无需操作) 在 `extensions/CirrusSearch/includes/Maintenance/AnalysisConfigBuilder.php` 末尾，
    在原来的 `zh` 语言选项之后加入 `zh-cn` 语言选项。

    * 进入 `extensions/CirrusSearch/maintenance`，运行 `php UpdateSearchIndexConfig.php --reindexAndRemoveOk --indexIdentifier=now`

    * 检查 `(wikiurl)/api.php?action=cirrus-settings-dump`，确保 `smartcn` 已在运行。

* 运行 [bTeX](https://github.com/banana-space/btex)。

* 运行 PHP 本地服务器进行调试。

如遇数据库错误，可在 `maintenance` 目录中运行
``` bash
php update.php
```
以刷新数据库。
