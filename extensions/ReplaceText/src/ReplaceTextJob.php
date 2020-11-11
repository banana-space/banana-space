<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Yaron Koren
 * @author Ankit Garg
 */

use Wikimedia\ScopedCallback;

/**
 * Background job to replace text in a given page
 * - based on /includes/RefreshLinksJob.php
 */
class ReplaceTextJob extends Job {
	/**
	 * Constructor.
	 * @param Title $title
	 * @param array|bool $params Cannot be === true
	 */
	function __construct( $title, $params = '' ) {
		parent::__construct( 'replaceText', $title, $params );
	}

	/**
	 * Run a replaceText job
	 * @return bool success
	 */
	function run() {
		if ( isset( $this->params['session'] ) ) {
			$callback = RequestContext::importScopedSession( $this->params['session'] );
			$this->addTeardownCallback( function () use ( &$callback ) {
				ScopedCallback::consume( $callback );
			} );
		}

		if ( is_null( $this->title ) ) {
			$this->error = "replaceText: Invalid title";
			return false;
		}

		if ( array_key_exists( 'move_page', $this->params ) ) {
			global $wgUser;
			$actual_user = $wgUser;
			$wgUser = User::newFromId( $this->params['user_id'] );
			$cur_page_name = $this->title->getText();
			if ( $this->params['use_regex'] ) {
				$new_page_name = preg_replace(
					"/" . $this->params['target_str'] . "/Uu", $this->params['replacement_str'], $cur_page_name
				);
			} else {
				$new_page_name =
					str_replace( $this->params['target_str'], $this->params['replacement_str'], $cur_page_name );
			}

			$new_title = Title::newFromText( $new_page_name, $this->title->getNamespace() );
			$reason = $this->params['edit_summary'];
			$create_redirect = $this->params['create_redirect'];
			$this->title->moveTo( $new_title, true, $reason, $create_redirect );
			if ( $this->params['watch_page'] ) {
				WatchAction::doWatch( $new_title, $wgUser );
			}
			$wgUser = $actual_user;
		} else {
			if ( $this->title->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
				$this->error = 'replaceText: Wiki page "' .
					$this->title->getPrefixedDBkey() . '" does not hold regular wikitext.';
				return false;
			}
			$wikiPage = new WikiPage( $this->title );
			// Is this check necessary?
			if ( !$wikiPage ) {
				$this->error =
					'replaceText: Wiki page not found for "' . $this->title->getPrefixedDBkey() . '."';
				return false;
			}
			$wikiPageContent = $wikiPage->getContent();
			if ( is_null( $wikiPageContent ) ) {
				$this->error =
					'replaceText: No contents found for wiki page at "' . $this->title->getPrefixedDBkey() . '."';
				return false;
			}
			$article_text = $wikiPageContent->getNativeData();

			$target_str = $this->params['target_str'];
			$replacement_str = $this->params['replacement_str'];
			$num_matches = 0;

			if ( $this->params['use_regex'] ) {
				$new_text =
					preg_replace( '/' . $target_str . '/Uu', $replacement_str, $article_text, -1, $num_matches );
			} else {
				$new_text = str_replace( $target_str, $replacement_str, $article_text, $num_matches );
			}

			// If there's at least one replacement, modify the page,
			// using the passed-in edit summary.
			if ( $num_matches > 0 ) {
				// Change global $wgUser variable to the one
				// specified by the job only for the extent of
				// this replacement.
				global $wgUser;
				$actual_user = $wgUser;
				$wgUser = User::newFromId( $this->params['user_id'] );
				$edit_summary = $this->params['edit_summary'];
				$flags = EDIT_MINOR;
				if ( $wgUser->isAllowed( 'bot' ) ) {
					$flags |= EDIT_FORCE_BOT;
				}
				if ( isset( $this->params['doAnnounce'] ) &&
					 !$this->params['doAnnounce'] ) {
					$flags |= EDIT_SUPPRESS_RC;
					# fixme log this action
				}
				$new_content = new WikitextContent( $new_text );
				$wikiPage->doEditContent( $new_content, $edit_summary, $flags );
				$wgUser = $actual_user;
			}
		}
		return true;
	}
}
