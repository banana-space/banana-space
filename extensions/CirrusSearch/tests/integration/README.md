# Integration tests

## Setup

Set up MediaWiki-Vagrant:

    cd mediawiki/vagrant
    vagrant up
    vagrant roles enable cirrussearch
    vagrant provision
    cd mediawiki/extensions/CirrusSearch
    npm install

## Run all tests

From CirrusSearch folder:

    npm run selenium

If you get this error message

    >> Something went wrong: listen EADDRINUSE /tmp/cirrussearch-integration-tagtracker

just delete the file

    rm /tmp/cirrussearch-integration-tagtracker
