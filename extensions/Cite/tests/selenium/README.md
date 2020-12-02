# Selenium tests

Please see tests/selenium/README.md file in mediawiki/core repository, usually at mediawiki/vagrant/mediawiki folder.

## Setup

Set up MediaWiki-Vagrant:

    cd mediawiki/vagrant
    vagrant up
    vagrant roles enable cite
    vagrant provision
    cd mediawiki
    npm install

## Run all specs

Run test specs from both mediawiki/core and installed extensions:

    cd mediawiki
    npm run selenium

## Run specific tests

To run only some tests, you first have to start Chromedriver in one terminal window:

    chromedriver --url-base=wd/hub --port=4444

Then, in another terminal window run this the current extension directory:

    npm install
    npm run selenium-test -- --spec tests/selenium/specs/FILE-NAME.js

You can also filter specific test(s) by name:

    npm run selenium-test -- --spec tests/selenium/specs/FILE-NAME.js --mochaOpts.grep TEST-NAME

Make sure Chromedriver is running when executing the above command.
