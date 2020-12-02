#!/usr/bin/env bash
#
# CI entry point to run MediaWiki parser tests
# https://phabricator.wikimedia.org/T202523
#

set -eu -o pipefail

if [ -z "${MW_INSTALL_PATH:-}" ]; then
	echo "Please set MW_INSTALL_PATH environment variable."
	exit 2
fi

printf "MW_INSTALL_PATH=%s\n" "$MW_INSTALL_PATH"

parsoid_dir=$(realpath "$(dirname "$0")"/../)

printf 'Looking for test files in %s\n' "$parsoid_dir"
test_files=($(find "$parsoid_dir" -iname '*parsertests.txt'|sort))
printf 'Found test file: %s\n' "${test_files[@]}"

printf 'md5 of each parser test files:\n'
for test_file in "${test_files[@]}"
do
	md5sum -b "${test_file}"
done

for test_file in "${test_files[@]}"
do
	printf '\n===[ %s ]===\n\n' "${test_file}"
	(cd "$MW_INSTALL_PATH" && php tests/parser/parserTests.php --color=no --quiet --file="${test_file}")
done
