MW_INSTALL_PATH ?= ../..
MEDIAWIKI_LOAD_URL ?= http://localhost:8080/w/load.php

ifneq ("$(wildcard /vagrant)","")
IS_VAGRANT = 1
endif

# Flow files to analyze
ANALYZE=container.php Flow.php Resources.php includes/

PHP=`command -v php`

###
# Meta stuff
###
installhooks:
	ln -sf ${PWD}/scripts/pre-commit .git/hooks/pre-commit
	ln -sf ${PWD}/scripts/pre-review .git/hooks/pre-review

remotes:
	@scripts/remotecheck.sh

gerrit: remotes
	@scripts/remotes/gerrit.py --project 'mediawiki/extensions/Flow' --gtscore -1 --ignorepattern 'WIP'

message: remotes
	@python scripts/remotes/message.py

messagecheck: remotes
	@python scripts/remotes/message.py check

###
# Lints
###
lint: grunt phplint checkless messagecheck

phplint:
	@. scripts/hooks-shared.sh; list_files_changed_in_commit '\.php' | xargs -P 12 -L 1 ${PHP} -l

nodecheck:
	@which npm > /dev/null && npm install \
		|| (echo "You need to install Node.JS and npm! See http://nodejs.org/" && false)

grunt: nodecheck
	@npm test

checkless:
ifdef IS_VAGRANT
	mwscript maintenance/checkLess.php --wiki=wiki
else
	@${PHP} ../../maintenance/checkLess.php
endif

jsduck:
	jsduck

csscss: gems
	echo "Generating CSS file..."
	php scripts/generatecss.php ${MEDIAWIKI_LOAD_URL} /tmp/foo.css
	csscss -v /tmp/foo.css --num 2 --no-match-shorthand --ignore-properties=display,position,top,bottom,left,right
###
# Testing
###
phpunit:
	cd ${MW_INSTALL_PATH}/tests/phpunit && ${PHP} phpunit.php --configuration ${MW_INSTALL_PATH}/extensions/Flow/tests/phpunit/flow.suite.xml --group=Flow

vagrant-browsertests:
	@vagrant ssh -- -X cd /vagrant/mediawiki/extensions/Flow/tests/browser '&&' MEDIAWIKI_URL=http://127.0.0.1:8080/wiki/ MEDIAWIKI_USER=Admin MEDIAWIKI_PASSWORD=vagrant MEDIAWIKI_API_URL=http://127.0.0.1:8080/w/api.php bundle exec cucumber /vagrant/mediawiki/extensions/Flow/tests/browser/features/ -f pretty

###
# Static analysis
###

analyze-phpstorm:
	@scripts/analyze-phpstorm.sh

analyze: analyze-phpstorm

###
# Compile lightncandy templates
###
compile-lightncandy:
ifdef IS_VAGRANT
	mwscript extensions/Flow/maintenance/compileLightncandy.php --wiki=wiki
else
	@${PHP} maintenance/compileLightncandy.php
endif
###
# Automatically rename/move files based on fully-qualified classname &
# compile class autoloader for $wgAutoloadClasses
###
autoload:
	if [ ! -d "vendor/PHP-Parser" ]; then git clone https://github.com/nikic/PHP-Parser.git vendor/PHP-Parser; fi
	@${PHP} scripts/one-class-per-file.php
	@${PHP} scripts/gen-autoload.php

###
# Update this repository
###
gems:
	bundle install
