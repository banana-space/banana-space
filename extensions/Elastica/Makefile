MW_INSTALL_PATH ?= ../..

# mediawiki-vagrant defaults to hhvm rather than php5, which is mostly
# fine but crazy slow for commands like phplint
PHP ?= `command -v php5 || command -v php`

phpunit:
	@${PHP} ${MW_INSTALL_PATH}/tests/phpunit/phpunit.php ${MW_INSTALL_PATH}/extensions/Elastica/tests/unit/

