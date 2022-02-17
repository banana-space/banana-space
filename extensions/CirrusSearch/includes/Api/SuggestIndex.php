<?php

namespace CirrusSearch\Api;

/**
 * Update ElasticSearch suggestion index
 *
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
 */
class SuggestIndex extends \ApiBase {
	use ApiTrait;

	public function execute() {
		// FIXME: This is horrible, no good, very bad hack. Only for testing,
		// and probably should be eventually replaced with something more sane.
		$updaterScript = "extensions/CirrusSearch/maintenance/UpdateSuggesterIndex.php";
		$this->getResult()->addValue( null, 'result',
			wfShellExecWithStderr( "unset REQUEST_METHOD; /usr/local/bin/mwscript $updaterScript --wiki " . wfWikiID() )
		);
	}
}
