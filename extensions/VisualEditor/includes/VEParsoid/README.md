This code exports a REST API for Parsoid.  It is a temporary
mechanism to connect VE and Parsoid, which will eventually
be replaced by direct communication as Parsoid is integrated
in MediaWiki core.

Any patches must be submitted and merged into the Parsoid repo
(mediawiki/services/parsoid.git) first, before being applied
here, to avoid implementation drift.

Note that, unlike the rest of this repo, this code is licensed
under GPL 2.0+.
