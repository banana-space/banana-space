#!/bin/sh

# the realpath executable isn't guaranteed, so define one.
# FIXME: path with ' in it will break
realpath() {
	php -r "echo realpath('$1'), \"\\n\";"
}

if [ ! -x "$(which xmllint)" ]; then
	echo xmllint is required to filter results
	exit 1
fi

# specifying directly kinda sucks
if [ ! -d "$PHPSTORM_BIN_HOME" ]; then
	PHPSTORM_BIN_HOME="$(realpath ~/PhpStorm-133.803/bin)"
fi

if [ ! -x "$PHPSTORM_BIN_HOME/inspect.sh" ]; then
	echo Could not locate PhpStorm.
	echo Set PHPSTORM_BIN_HOME to the bin dir inside the uncompressed phpstorm executable
	echo If you need a license check https://office.wikimedia.org/wiki/JetBrains
	exit 1
fi

# all paths must be absolute
EXTENSION="$(dirname $(dirname $(realpath $0)))"

# Path to the main project
MEDIAWIKI="$(realpath $EXTENSION/../..)"

# Output path
OUTPUT="/tmp/phpstorm-inspect.$$"

$PHPSTORM_BIN_HOME/inspect.sh $MEDIAWIKI $EXTENSION/scripts/analyze-phpstorm.xml $OUTPUT -d $EXTENSION/includes -v2

EXIT=0
if [ $(find $OUTPUT | wc -l) -gt 1 ]; then
	# @todo format the xml
	for i in $OUTPUT/*; do
		# Filter errors in api, its currently just a stub
		xmllint --xpath "//problem[not(file[contains(text(), '/Flow/includes/api/')])]" "$i" 2>/dev/null > "$OUTPUT/tmp.out"
		if [ -s "$OUTPUT/tmp.out" ]; then
			EXIT=1
			echo $i
			echo
			cat "$OUTPUT/tmp.out"
			echo
			echo
		fi
	done
	test -f "$OUTPUT/tmp.out" && rm "$OUTPUT/tmp.out"
fi

if [ $EXIT -eq 0 ]; then
	rm -rf $OUTPUT
else
	echo XML output stored in $OUTPUT
fi

exit $EXIT
