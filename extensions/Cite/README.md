Cite
=============

The Cite extension provides a way for users to create references as footnotes to articles.

See https://www.mediawiki.org/wiki/Extension:Cite for detailed documentation.

Configuration
-------------
* `$wgCiteStoreReferencesData`: If set to true, references are saved in the database so that
other extensions can retrieve them independently of the main article content.
* `$wgCiteCacheReferencesDataOnParse`: (`$wgCiteStoreReferencesData` required) By default,
references are cached only on database access. If set to true, references are also cached
whenever pages are parsed.
