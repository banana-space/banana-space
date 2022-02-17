#!/bin/sh
#
# WARNING: This may break other thing inside vagrant. Not really sure.
#
# Script to reset the databases inside mwvagrant to simulate starting the tests from
# nothing, like if they were run inside a fresh CI instance.

# Kill any open mysql connections that might have locks on tables
sql -Nse "SELECT ID from information_schema.PROCESSLIST where INFO not like '%information_schema%'" | while read pid; do
   sql -Nse "KILL $pid"
done

# Drop any caches in redis
redis-cli flushdb

wikis="cirrustestwiki commonswiki ruwiki"
for wiki in $wikis; do
	# Truncate all the tables
	sql -Nse 'show tables' $wiki | while read table; do sql -e "truncate table $table" $wiki; done
	# Re-create vagrant user
	mwscript createAndPromote.php --wiki=$wiki Admin vagrant
	mwscript createAndPromote.php --wiki=$wiki --custom-groups sysop --force Admin
	# Inject main page and its template
	mwscript edit.php --wiki=$wiki --summary="Vagrant import" --no-rc Template:Main_Page < /vagrant/puppet/modules/mediawiki/files/main_page_template.wiki
	mwscript edit.php --wiki=$wiki --summary="Vagrant import" --no-rc Main_Page < /vagrant/puppet/modules/mediawiki/files/main_page.wiki
	# Re-create cirrus indices
	mwscript extensions/CirrusSearch/tests/jenkins/cleanSetup.php --wiki=$wiki
	mwscript extensions/CirrusSearch/maintenance/UpdateSuggesterIndex.php --wiki=$wiki
done
