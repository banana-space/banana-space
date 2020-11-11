# Firejail profile used by MediaWiki when shelling out
# Most rules are applied via command-line flags controlled by the
# Shell::RESTRICTION_* constants.
# Rules added to this file must be compatible with every command that could
# be invoked. If something might need to be disabled, then it should be added
# as a Shell:RESTRICTION_* constant instead so that commands can opt-in/out.

# See <https://firejail.wordpress.com/features-3/man-firejail-profile/> for
# syntax documentation.

# Optionally allow sysadmins to set extra restrictions that apply to their
# MediaWiki setup, e.g. disallowing access to extra private directories.
include /etc/firejail/mediawiki.local

# Include any global firejail customizations.
include /etc/firejail/globals.local
