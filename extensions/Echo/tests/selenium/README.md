# Selenium tests

For more information see https://www.mediawiki.org/wiki/Selenium/Node.js and [PATH]/mediawiki/vagrant/mediawiki/tests/selenium/README.md.

## Setup

Set up MediaWiki-Vagrant:

    cd [PATH]/mediawiki/vagrant/mediawiki/extensions/Echo
    vagrant up
    vagrant roles enable echo
    vagrant provision
    npm install

Chromedriver has to run in one terminal window:

    chromedriver --url-base=wd/hub --port=4444

## Run all specs

In another terminal window:

    npm run selenium-test

## [T171963](https://phabricator.wikimedia.org/T171963) `No active login attempt is in progress for your session`

If you get this error message when logging in at `127.0.0.1:8080`, the workaround
is to log in at `dev.wiki.local.wmftest.net:8080`

    MW_SERVER=http://dev.wiki.local.wmftest.net:8080  npm run selenium-test

## Run specific tests

Filter by file name:

    npm run selenium-test -- --spec tests/selenium/specs/[FILE-NAME].js

Filter by file name and test name:

    npm run selenium-test -- --spec tests/selenium/specs/[FILE-NAME.js] --mochaOpts.grep [TEST-NAME]
