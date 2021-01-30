## Getting started

The easiest way to help develop Flow is to use MediaWiki-Vagrant.

Start at https://www.mediawiki.org/wiki/MediaWiki-Vagrant

Enable the Flow role (you may need to run `vagrant provision` afterwards).

If you do not use MediaWiki-Vagrant, you will need to set up all the
required dependencies listed at
https://www.mediawiki.org/wiki/Extension:Flow#Dependencies .

You can find the Collaboration team, which maintains Flow, in
#wikimedia-collaboration on Freenode.

## Libraries
Flow primarily uses two libraries, OOUI/OOUI PHP and Handlebars.  Handlebars
is a templating language used on both the client and server.

When developing, it is recommended to set:
$wgFlowServerCompileTemplates = true;

so templates are automatically updated.

Before committing a change that affects templates,
run:

make compile-lightncandy

to make sure all the PHP templates are updated.
