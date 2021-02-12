# BananaSpace

BananaSpace is a project based on MediaWIki (the platform for Wikipedia) with several extensions and bTeX (a compiler that transform LaTeX-styled code into html). BananaSpace parsers TeX-styled source code, instead of WikiText used in MediaWiki. Math formulae are (at least should be) well supported in this project, with the help of KaTeX. The project is aimed to build a website about math, which has encyclopedia articles, lectures and discussions on it. We wish that it offers users a delicious using experience, well, anyway, at least not a bad experience.

BananaSpace 是一个基于带扩展的 MediaWiki（Wikipedia 使用的平台）和 bTeX （将LaTeX风格代码转化为html代码）的项目。相比渲染 WikiText 的 Mediawiki，BananaSpace 渲染的是 TeX 风格的源代码。借助 KaTeX，这个项目很好地（起码我们觉得它应当很好地）支持数学公式的显示。这个项目致力于搭建一个集百科条目、讲义和讨论与一体的数学网站；我们希望它能给用户提供嘉肴甘旨般的体验——咳，至少别是太坏的体验吧。

### Content

This repo contains MediaWiki source code together with

* Several extensions from [mediawiki.org](https://www.mediawiki.org);
* `TeXParser` extension;
* `Banana` skin.

To enable them, add the following lines to `LocalSettings.php`.

``` php
wfLoadSkin( 'Banana' );
$wgDefaultSkin = 'Banana';wfLoadSkin( 'Vector' );

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
$wgNamespaceContentModels[NS_MODULE_TALK]    = 'flow-board';
$wgNamespaceContentModels[NS_PROJECT_TALK]   = 'flow-board';
$wgNamespaceContentModels[NS_CATEGORY_TALK]  = 'flow-board';
$wgNamespaceContentModels[NS_TEMPLATE_TALK]  = 'flow-board';
$wgNamespaceContentModels[NS_MEDIAWIKI_TALK] = 'flow-board';

$wgNamespacesToBeSearchedDefault[NS_NOTES] = true;

$wgFileExtensions = [ 'png', 'gif', 'jpg', 'jpeg', 'pdf', 'svg' ];
```

If you encounter errors, go to the `maintenance` folder, and run
``` bash
php update.php
```

### How to use

If you want to use the project to build your own website, please see below:

As the project is in development, it's not recommended to use it right now. When the project is somehow stable, we will have using introduction here.

如果你想使用这个项目来搭建你自己的网站，请看如下说明：

这个项目还正在开发，所以我们不推荐你现在就使用它。当这个项目有一定稳定性后，我们将会把使用说明写在这里。

### 运行步骤

* 通过 MediaWiki 安装向导生成 `LocalSettings.php` 文件，放在根目录。
* 运行 [bTeX](https://github.com/banana-space/btex)。
* 运行 `php -S localhost:5000`。
* 在浏览器打开 `localhost:5000`。
