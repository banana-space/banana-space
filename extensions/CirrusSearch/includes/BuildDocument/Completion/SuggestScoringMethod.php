<?php

namespace CirrusSearch\BuildDocument\Completion;

/**
 * Scoring methods used by the completion suggester
 *
 * Set $wgSearchType to 'CirrusSearch'
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
interface SuggestScoringMethod {
	/**
	 * @param array $doc A document from the PAGE type
	 * @return int the weight of the document
	 */
	public function score( array $doc );

	/**
	 * The list of fields needed to compute the score.
	 *
	 * @return string[] the list of required fields
	 */
	public function getRequiredFields();

	/**
	 * This method will be called by the indexer script.
	 * some scoring method may want to normalize values based index size
	 *
	 * @param int $maxDocs the total number of docs in the index
	 */
	public function setMaxDocs( $maxDocs );

	/**
	 * Explain the score
	 * @param array $doc
	 * @return array
	 */
	public function explain( array $doc );
}
