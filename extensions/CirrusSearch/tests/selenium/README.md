# Selenium tests

Please see tests/selenium/README.md file in mediawiki/core repository.

Tests here are running daily in selenium-CirrusSearch-jessie Jenkins job. For documentation see https://www.mediawiki.org/wiki/Selenium/Node.js/selenium-EXTENSION-jessie_Jenkins_job

## Usage

In one terminal window or tab start Chromedriver:

    chromedriver --url-base=wd/hub --port=4444

In another terminal tab or window go to mediawiki/core folder:

    MW_SERVER=https://en.wikipedia.beta.wmflabs.org:443 MW_SCRIPT_PATH=/w ./node_modules/.bin/wdio tests/selenium/wdio.conf.js --spec extensions/CirrusSearch/tests/selenium/specs/*.js
