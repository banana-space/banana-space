== Localisation Update ==
Localisation Update extension can update the MediaWiki messages at any time,
without needing to upgrade the MediaWiki software.

For more information see:
 https://www.mediawiki.org/wiki/Extension:LocalisationUpdate

== Installation ==
1. Add the following to LocalSettings.php of your MediaWiki setup:

 wfLoadExtension( 'LocalisationUpdate' );
 $wgLocalisationUpdateDirectory = "$IP/cache";

2. Create a cache folder in the installation directory, and be sure the server
has permissions to write on it.

If localization updates don't seem to come through, you may need to run,

 php maintenance/rebuildLocalisationCache.php --force.

3. Whenever you want to run an update, run,

 php extensions/LocalisationUpdate/update.php

For detailed help, see:

 php extensions/LocalisationUpdate/update.php --help

4. If you are on Unix like system, you should add LocalisationUpdate to
crontab:

 crontab -e
 # Add the following line
 @daily php /path/to/your/wiki/extensions/LocalisationUpdate/update.php --quiet
