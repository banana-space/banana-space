<?php

namespace CirrusSearch\Maintenance;

/**
 * Splits maintenance scripts into chunks and prints out the commands to run
 * the chunks.
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
class ChunkBuilder {
	/**
	 * @param string $self Name of maintenance script
	 * @param array $options
	 * @param string|int $buildChunks If specified as a number then chunks no
	 *  larger than that size are spat out.  If specified as a number followed
	 *  by the word "total" without a space between them then that many chunks
	 *  will be spat out sized to cover the entire wiki.
	 * @param int $fromPageId
	 * @param int $toPageId
	 */
	public function build( $self, array $options, $buildChunks, $fromPageId, $toPageId ) {
		$fixedChunkSize = strpos( $buildChunks, 'total' ) === false;
		$buildChunks = intval( $buildChunks );
		if ( $fixedChunkSize ) {
			$chunkSize = $buildChunks;
		} else {
			$chunkSize = max( 1, ceil( ( $toPageId - $fromPageId ) / $buildChunks ) );
		}
		for ( $pageId = $fromPageId; $pageId < $toPageId; $pageId += $chunkSize ) {
			$chunkToId = min( $toPageId, $pageId + $chunkSize );
			print "php $self";
			foreach ( $options as $optName => $optVal ) {
				if ( $optVal === null || $optVal === false || $optName === 'fromId' ||
					$optName === 'toId' || $optName === 'buildChunks' ||
					( $optName === 'memory-limit' && $optVal === 'max' )
				) {
					continue;
				}
				print " --$optName $optVal";
			}
			print " --fromId $pageId --toId $chunkToId\n";
		}
	}
}
