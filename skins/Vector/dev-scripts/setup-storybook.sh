#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

mkdir -p .storybook/resolve-less-imports/mediawiki.ui
mkdir -p docs/ui/assets/

curl -sS "https://en.wikipedia.org/w/load.php?only=styles&skin=vector&debug=true&modules=ext.echo.styles.badge|ext.uls.pt|wikibase.client.init|mediawiki.skinning.interface" -o .storybook/integration.less
curl -sSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/core/+/master/resources/src/mediawiki.less/mediawiki.mixins.less?format=TEXT" | base64 --decode > .storybook/resolve-less-imports/mediawiki.mixins.less
curl -sSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/core/+/master/resources/src/mediawiki.less/mediawiki.ui/variables.less?format=TEXT" | base64 --decode > .storybook/resolve-less-imports/mediawiki.ui/variables.less
curl -sSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/core/+/master/resources/src/mediawiki.less/mediawiki.mixins.rotation.less?format=TEXT" | base64 --decode > .storybook/resolve-less-imports/mediawiki.mixins.rotation.less
curl -sSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/core/+/master/resources/src/mediawiki.less/mediawiki.mixins.animation.less?format=TEXT" | base64 --decode > .storybook/resolve-less-imports/mediawiki.mixins.animation.less
curl -sS "https://en.m.wikipedia.org/static/images/mobile/copyright/wikipedia-wordmark-en.svg" -o "docs/ui/assets/wordmark.svg"
curl -sS "https://en.m.wikipedia.org/static/images/mobile/copyright/wikipedia.png" -o "docs/ui/assets/icon.png"
curl -sS "https://en.wikipedia.org/static/images/mobile/copyright/wikipedia-tagline-en.svg" -o "docs/ui/assets/tagline.svg"
