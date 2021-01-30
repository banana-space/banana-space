#
# Shared functionality of the Flow git hooks
#

realpath() {
	php -r "echo realpath('$1'), \"\\n\";"
}

is_vagrant() {
    DEST='.'
    while [ "$(realpath $DEST)" != "/" ]; do
        if [ -f $DEST/Vagrantfile ]; then
            return 0;
        fi
        DEST="$DEST/.."
    done
    return 1
}

make() {
    if is_vagrant; then
        echo 'git hooks: Attempting to ssh into Vagrant'
        vagrant ssh -- cd /vagrant/mediawiki/extensions/Flow '&&' /bin/echo 'git hooks: Running commands inside Vagrant' '&&' sudo -u www-data make $* || exit 1
    else
        /usr/bin/env make $* || exit 1
    fi
}

list_files_changed_in_commit() {
	git diff --name-only --cached | grep -E "$1"
}

file_changed_in_commit() {
	list_files_changed_in_commit "$1" 2>&1 > /dev/null
}

file_changed_in_head() {
	git diff-tree --no-commit-id --name-only -r HEAD | grep -E "$1" 2>&1 >/dev/null
}

