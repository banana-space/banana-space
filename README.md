# BananaSpace

BananaSpace is a project based on MediaWIki (the platform for Wikipedia) with several extensions and bTeX (a compiler that transform LaTeX-styled code into html). BananaSpace parsers TeX-styled source code, instead of WikiText used in MediaWiki. Math formulae are (at least should be) well supported in this project, with the help of KaTeX. The project is aimed to build a website about math, which has encyclopedia articles, lectures and discussions on it. We wish that it offers users a delicious using experience, well, anyway, at least not a bad experience.

BananaSpace 是一个基于带扩展的 MediaWiki（Wikipedia 使用的平台）和 bTeX （将LaTeX风格代码转化为html代码）的项目。相比渲染 WikiText 的 Mediawiki，BananaSpace 渲染的是 TeX 风格的源代码。借助 KaTeX，这个项目很好地（起码我们觉得它应当很好地）支持数学公式的显示。这个项目致力于搭建一个集百科条目、讲义和讨论与一体的数学网站；我们希望它能给用户提供嘉肴甘旨般的体验——咳，至少别是太坏的体验吧。

### Content

The project is still in development. Currently, the BananaSpace repo includes the following contents:

* MediaWiki Sourcecode.
* Extension called TeXParser inside the extensions folder.

In the near future, a skin will be added into the project.

这个项目还在开发中。现在，BananaSpace repo 里有以下内容：

* MediaWiki 1.35.0 的源代码。
* 一个叫做 TeXParser 的扩展，塞在 extensions 文件夹里。

### How to use

If you want to use the project to build your own website, please see below:

As the project is in development, it's not recommended to use it right now. When the project is somehow stable, we will have using introduction here.

如果你想使用这个项目来搭建你自己的网站，请看如下说明：

这个项目还正在开发，所以我们不推荐你现在就使用它。当这个项目有一定稳定性后，我们将会把使用说明写在这里。

The following is the original introduction to MediaWiki:

# MediaWiki

MediaWiki is a free and open-source wiki software package written in PHP. It
serves as the platform for Wikipedia and the other Wikimedia projects, used
by hundreds of millions of people each month. MediaWiki is localised in over
350 languages and its reliability and robust feature set have earned it a large
and vibrant community of third-party users and developers.

MediaWiki is:

* feature-rich and extensible, both on-wiki and with hundreds of extensions;
* scalable and suitable for both small and large sites;
* simple to install, working on most hardware/software combinations; and
* available in your language.

For system requirements, installation, and upgrade details, see the files
RELEASE-NOTES, INSTALL, and UPGRADE.

* Ready to get started?
** https://www.mediawiki.org/wiki/Special:MyLanguage/Download
* Looking for the technical manual?
** https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Contents
* Seeking help from a person?
** https://www.mediawiki.org/wiki/Special:MyLanguage/Communication
* Looking to file a bug report or a feature request?
** https://bugs.mediawiki.org/
* Interested in helping out?
** https://www.mediawiki.org/wiki/Special:MyLanguage/How_to_contribute

MediaWiki is the result of global collaboration and cooperation. The CREDITS
file lists technical contributors to the project. The COPYING file explains
MediaWiki's copyright and license (GNU General Public License, version 2 or
later). Many thanks to the Wikimedia community for testing and suggestions.
