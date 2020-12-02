[![NPM version](https://badge.fury.io/js/unicodejs.svg)](https://badge.fury.io/js/unicodejs)

UnicodeJS
=================

UnicodeJS is a JavaScript library for working with the Unicode standard.

Quick start
----------

This library is available as an [npm](https://npmjs.org/) package! Install it right away:
<pre lang="bash">
npm install unicodejs
</pre>

Or clone the repo, `git clone https://gerrit.wikimedia.org/r/p/unicodejs.git`.

Versioning
----------

We use the Semantic Versioning guidelines as much as possible.

Releases will be numbered in the following format:

`<major>.<minor>.<patch>`

For more information on SemVer, please visit http://semver.org/.

Bug tracker
-----------

Found a bug? Please report it in the [issue tracker](https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?project=Utilities-UnicodeJS)!

Release
----------

Release process:
<pre lang="bash">
$ cd path/to/unicodejs/
$ git remote update
$ git checkout -b release -t origin/master

# Ensure tests pass
$ npm install-test

# Avoid using "npm version patch" because that creates
# both a commit and a tag, and we shouldn't tag until after
# the commit is merged.

# Update release notes
# Copy the resulting list into a new section on History.md
$ git log --format='* %s (%aN)' --no-merges --reverse v$(node -e 'console.log(require("./package.json").version);')...HEAD
$ edit History.md

# Update the version number
$ edit package.json

$ git add -p
$ git commit -m "Tag vX.X.X"
$ git review

# After merging:
$ git remote update
$ git checkout origin/master
$ git tag "vX.X.X"
$ git push --tags
$ npm publish
</pre>
