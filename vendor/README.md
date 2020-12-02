MediaWiki-Vendor
================

[Composer] managed libraries required or recommended for use with [MediaWiki].
This repository is maintained for use on the Wikimedia Foundation production
and testing clusters, but may be useful for anyone wishing to avoid directly
managing MediaWiki dependencies with Composer.


Usage
-----

Checkout this library into $IP/vendor using `git clone <URL>` or add the
repository as a git submodule using `git submodule add <URL> vendor` followed
by `git submodule update --init`.


Adding or updating libraries
----------------------------

0. Read the [documentation] on the process for adding new libraries.
1. Ensure you're using version 1.10.0 (or later) of composer via
   `composer --version`. This keeps installed.json alphasorted, making patches
   less likely to conflict, and diffs easier to read.
2. Edit the composer.json file to add/update the libraries you want to change.
3. Run `composer update --no-dev --ignore-platform-reqs` to download files and
   update the autoloader.
4. Add all the new dependencies that got installed to composer.json as well,
   so that everything has their version pinned. (You can look at the changes
   in composer.lock or composer/installed.json to see what they are.)
5. Rarely, lint checks fail because test files in some library were written
   for an unsupported PHP version. In that case add the test directories to
   the --exclude parameter in the script > test field in composer.json, and
   to .gitignore.
6. Add and commit changes as a gerrit patch.
7. Review and merge changes.

Note that you MUST pair patches changing versions of libraries used by MediaWiki
itself with ones for the "core" repo. Specifically, the patch in mediawiki/core
must have a `Depends-On` footer to the patch in mediawiki/vendor.

The vendor repo has special configuration, which skips the integrity checks and
so allowing a circular dependency Gordian knot to be fixed. However, this means
that, if merged alone without a pair, you'll cause ALL patches in MediaWiki and
ALL extensions to fail their continuous integration tests.

If in doubt, seek advice from regular commiters to this repository.


[Composer]: https://getcomposer.org/
[MediaWiki]: https://www.mediawiki.org/wiki/MediaWiki
[documentation]: https://www.mediawiki.org/wiki/Manual:External_libraries
