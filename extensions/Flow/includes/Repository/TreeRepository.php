<?php

namespace Flow\Repository;

use Flow\Data\FlowObjectCache;
use Flow\Data\ObjectManager;
use Flow\DbFactory;
use Flow\Exception\DataModelException;
use Flow\Model\UUID;
use Wikimedia\Rdbms\DatabaseMysqlBase;
use Wikimedia\Rdbms\DBQueryError;

/*
 *
 * In SQL
 *
 * CREATE TABLE flow_tree_node (
 *     descendant DECIMAL(39) UNSIGNED NOT NULL,
 *     ancestor DECIMAL(39) UNSIGNED NULL,
 *     depth SMALLINT UNSIGNED NOT NULL,
 *     PRIMARY KEY ( ancestor, descendant ),
 *     UNIQUE KEY ( descendant, depth )
 * );
 *
 * In Memcache
 *
 * flow:tree:subtree:<descendant>
 * flow:tree:rootpath:<descendant>
 * flow:tree:parent:<descendant> - should we just use rootpath?
 *
 * Not sure how to handle topic splits with caching yet, i can imagine
 * a number of potential race conditions for writing root paths and sub trees
 * during a topic split
*/
class TreeRepository {

	/**
	 * @var string
	 */
	protected $tableName = 'flow_tree_node';

	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * @var FlowObjectCache
	 */
	protected $cache;

	/**
	 * @param DbFactory $dbFactory Factory to source connection objects from
	 * @param FlowObjectCache $cache
	 */
	public function __construct( DbFactory $dbFactory, FlowObjectCache $cache ) {
		$this->dbFactory = $dbFactory;
		$this->cache = $cache;
	}

	/**
	 * A helper function to generate cache keys for tree repository
	 * @param string $treeType
	 * @param UUID $uuid
	 * @return string
	 */
	protected function cacheKey( $treeType, UUID $uuid ) {
		return TreeCacheKey::build( $treeType, $uuid );
	}

	/**
	 * Insert a new tree node.  If ancestor === null then this node is a root.
	 *
	 * Also delete cache entries related to this tree.
	 * @param UUID $descendant
	 * @param UUID|null $ancestor
	 * @return true
	 * @throws DataModelException
	 */
	public function insert( UUID $descendant, UUID $ancestor = null ) {
		$this->cache->delete( $this->cacheKey( 'subtree', $descendant ) );
		$this->cache->delete( $this->cacheKey( 'parent', $descendant ) );
		$this->cache->delete( $this->cacheKey( 'rootpath', $descendant ) );

		if ( $ancestor === null ) {
			$path = [ $descendant ];
		} else {
			$path = $this->findRootPath( $ancestor );
			$path[] = $descendant;
		}
		$this->deleteSubtreeCache( $descendant, $path );

		$dbw = $this->dbFactory->getDB( DB_MASTER );
		$dbw->insert(
			$this->tableName,
			[
				'tree_descendant_id' => $descendant->getBinary(),
				'tree_ancestor_id' => $descendant->getBinary(),
				'tree_depth' => 0,
			],
			__METHOD__
		);

		$ok = true;
		if ( $ancestor !== null ) {
			try {
				if ( defined( 'MW_PHPUNIT_TEST' ) && $dbw instanceof DatabaseMysqlBase ) {
					/*
					 * Combination of MW unit tests + MySQL DB is known to cause
					 * query failures of code 1137, so instead of executing a
					 * known bad query, let's just consider it failed right away
					 * (and let catch statement deal with it)
					 */
					throw new DBQueryError( $dbw, 'Prevented execution of known bad query', 1137, '', __METHOD__ );
				}

				$dbw->insertSelect(
					$this->tableName,
					$this->tableName,
					[
						'tree_descendant_id' => $dbw->addQuotes( $descendant->getBinary() ),
						'tree_ancestor_id' => 'tree_ancestor_id',
						'tree_depth' => 'tree_depth + 1',
					],
					[
						'tree_descendant_id' => $ancestor->getBinary(),
					],
					__METHOD__
				);
			} catch ( DBQueryError $e ) {
				/*
				 * insertSelect won't work on temporary tables (as used for MW
				 * unit tests), because it refers to the same table twice, in
				 * one query.
				 * In this case, we'll do a separate select & insert. This used
				 * to always be detected via the DBQueryError, but it can also
				 * return false from insertSelect.
				 *
				 * @see https://dev.mysql.com/doc/refman/5.0/en/temporary-table-problems.html
				 * @see http://dba.stackexchange.com/questions/45270/mysql-error-1137-hy000-at-line-9-cant-reopen-table-temp-table
				 */
				if ( $e->errno === 1137 ) {
					$rows = $dbw->select(
						$this->tableName,
						[ 'tree_depth', 'tree_ancestor_id' ],
						[ 'tree_descendant_id' => $ancestor->getBinary() ],
						__METHOD__
					);

					if ( $rows ) {
						foreach ( $rows as $row ) {
							$dbw->insert(
								$this->tableName,
								[
									'tree_descendant_id' => $descendant->getBinary(),
									'tree_ancestor_id' => $row->tree_ancestor_id,
									'tree_depth' => $row->tree_depth + 1,
								],
								__METHOD__
							);
						}
					}
				} else {
					$ok = false;
				}
			}
		}

		if ( !$ok ) {
			throw new DataModelException( 'Failed inserting new tree node', 'process-data' );
		}

		return true;
	}

	protected function deleteSubtreeCache( UUID $descendant, array $rootPath ) {
		foreach ( $rootPath as $subtreeRoot ) {
			$cacheKey = $this->cacheKey( 'subtree', $subtreeRoot );
			$this->cache->delete( $cacheKey );
		}
	}

	/**
	 * Deletes a descendant from the tree repo.
	 *
	 * @param UUID $descendant
	 * @return bool
	 */
	public function delete( UUID $descendant ) {
		$dbw = $this->dbFactory->getDB( DB_MASTER );
		$res = $dbw->delete(
			$this->tableName,
			[
				'tree_descendant_id' => $descendant->getBinary(),
			],
			__METHOD__
		);

		if ( $res ) {
			$subtreeKey = $this->cacheKey( 'subtree', $descendant );
			$parentKey = $this->cacheKey( 'parent', $descendant );
			$pathKey = $this->cacheKey( 'rootpath', $descendant );

			$this->cache->delete( $subtreeKey );
			$this->cache->delete( $parentKey );
			$this->cache->delete( $pathKey );
		}

		return $res;
	}

	public function findParent( UUID $descendant ) {
		$map = $this->fetchParentMap( [ $descendant ] );
		return $map[$descendant->getAlphadecimal()] ?? null;
	}

	/**
	 * Given a list of nodes, find the path from each node to the root of its tree.
	 * the root must be the first element of the array, $node must be the last element.
	 * @param UUID[] $descendants Array of UUID objects to find the root paths for.
	 * @return UUID[][] Associative array, key is the post ID in hex, value is the path as an array.
	 */
	public function findRootPaths( array $descendants ) {
		// alphadecimal => cachekey
		$cacheKeys = [];
		// alphadecimal => cache result ( distance => parent uuid obj )
		$cacheValues = [];
		// list of binary values for db query
		$missingValues = [];
		// alphadecimal => distance => parent uuid obj
		$paths = [];

		foreach ( $descendants as $descendant ) {
			$cacheKeys[$descendant->getAlphadecimal()] = $this->cacheKey( 'rootpath', $descendant );
		}

		$cacheResult = $this->cache->getMulti( array_values( $cacheKeys ) );
		foreach ( $descendants as $descendant ) {
			$alpha = $descendant->getAlphadecimal();
			if ( isset( $cacheResult[$cacheKeys[$alpha]] ) ) {
				$cacheValues[$alpha] = $cacheResult[$cacheKeys[$alpha]];
			} else {
				$missingValues[] = $descendant->getBinary();
				$paths[$alpha] = [];
			}
		}

		if ( !count( $missingValues ) ) {
			return $cacheValues;
		}

		$dbr = $this->dbFactory->getDB( DB_REPLICA );
		$res = $dbr->select(
			$this->tableName,
			[ 'tree_descendant_id', 'tree_ancestor_id', 'tree_depth' ],
			[
				'tree_descendant_id' => $missingValues,
			],
			__METHOD__
		);

		if ( !$res || $res->numRows() === 0 ) {
			return $cacheValues;
		}

		foreach ( $res as $row ) {
			$alpha = UUID::create( $row->tree_descendant_id )->getAlphadecimal();
			$paths[$alpha][$row->tree_depth] = UUID::create( $row->tree_ancestor_id );
		}

		foreach ( $paths as $descendantId => &$path ) {
			if ( !$path ) {
				$path = null;
				continue;
			}

			// sort by reverse distance, so furthest away
			// parent (root) is at position 0.
			ksort( $path );
			$path = array_reverse( $path );

			$this->cache->set( $cacheKeys[$descendantId], $path );
		}

		return $paths + $cacheValues;
	}

	/**
	 * Finds the root path for a single post ID.
	 * @param UUID $descendant Post ID
	 * @return UUID[]|null Path to the root of that node.
	 */
	public function findRootPath( UUID $descendant ) {
		$paths = $this->findRootPaths( [ $descendant ] );

		return $paths[$descendant->getAlphadecimal()] ?? null;
	}

	/**
	 * Finds the root posts of a list of posts.
	 * @param UUID[] $descendants Array of PostRevision objects to find roots for.
	 * @return UUID[] Associative array of post ID (as hex) to UUID object representing its root.
	 */
	public function findRoots( array $descendants ) {
		$paths = $this->findRootPaths( $descendants );
		$roots = [];

		foreach ( $descendants as $descendant ) {
			$alpha = $descendant->getAlphadecimal();
			if ( isset( $paths[$alpha] ) ) {
				$roots[$alpha] = $paths[$alpha][0];
			}
		}

		return $roots;
	}

	/**
	 * Given a specific child node find the associated root node
	 *
	 * @param UUID $descendant
	 * @return UUID
	 * @throws DataModelException
	 */
	public function findRoot( UUID $descendant ) {
		// To simplify caching we will work through the root path instead
		// of caching our own value
		$path = $this->findRootPath( $descendant );
		if ( !$path ) {
			throw new DataModelException( $descendant->getAlphadecimal() . ' has no root post. Probably is a root post.', 'process-data' );
		}

		$root = array_shift( $path );

		return $root;
	}

	/**
	 * Fetch a node and all its descendants. Children are returned in the
	 * same order they were inserted.
	 *
	 * @param UUID|UUID[] $roots
	 * @return array Multi-dimensional tree. The top level is a map from the uuid of a node
	 *  to attributes about that node.  The top level contains not just the parents, but all nodes
	 *  within this tree. Within each node there is a 'children' key that contains a map from
	 *  the child uuid's to references back to the top level of this identity map. As such this
	 *  result can be read either as a list or a tree.
	 * @throws DataModelException When invalid data is received from self::fetchSubtreeNodeList
	 */
	public function fetchSubtreeIdentityMap( $roots ) {
		$roots = ObjectManager::makeArray( $roots );
		if ( !$roots ) {
			return [];
		}
		$nodes = $this->fetchSubtreeNodeList( $roots );
		if ( !$nodes ) {
			throw new DataModelException(
				'subtree node list should have at least returned root: ' . implode( ', ', $roots ),
				'process-data'
			);
		} elseif ( count( $nodes ) === 1 ) {
			$parentMap = $this->fetchParentMap( reset( $nodes ) );
		} else {
			$parentMap = $this->fetchParentMap( array_merge( ...array_values( $nodes ) ) );
		}
		$identityMap = [];
		foreach ( $parentMap as $child => $parent ) {
			if ( !array_key_exists( $child, $identityMap ) ) {
				$identityMap[$child] = [ 'children' => [] ];
			}
			// Root nodes have no parent
			if ( $parent !== null ) {
				$identityMap[$parent->getAlphadecimal()]['children'][$child] =& $identityMap[$child];
			}
		}
		foreach ( array_keys( $identityMap ) as $parent ) {
			ksort( $identityMap[$parent]['children'] );
		}

		return $identityMap;
	}

	public function fetchSubtree( UUID $root ) {
		$identityMap = $this->fetchSubtreeIdentityMap( $root );
		if ( !isset( $identityMap[$root->getAlphadecimal()] ) ) {
			throw new DataModelException( 'No root exists in the identityMap', 'process-data' );
		}

		return $identityMap[$root->getAlphadecimal()];
	}

	public function fetchFullTree( UUID $nodeId ) {
		return $this->fetchSubtree( $this->findRoot( $nodeId ) );
	}

	/**
	 * Return the id's of all nodes which are a descendant of provided roots
	 *
	 * @param UUID[] $roots
	 * @return array map from root id to its descendant list
	 * @throws \Flow\Exception\InvalidInputException
	 */
	public function fetchSubtreeNodeList( array $roots ) {
		$list = new MultiGetList( $this->cache );
		$res = $list->get(
			'subtree',
			$roots,
			[ $this, 'fetchSubtreeNodeListFromDb' ]
		);
		// $idx is a binary UUID
		$retval = [];
		foreach ( $res as $idx => $val ) {
			$retval[UUID::create( $idx )->getAlphadecimal()] = $val;
		}
		return $retval;
	}

	public function fetchSubtreeNodeListFromDb( array $roots ) {
		$res = $this->dbFactory->getDB( DB_REPLICA )->select(
			$this->tableName,
			[ 'tree_ancestor_id', 'tree_descendant_id' ],
			[
				'tree_ancestor_id' => UUID::convertUUIDs( $roots ),
			],
			__METHOD__
		);
		if ( $res === false ) {
			wfDebugLog( 'Flow', __METHOD__ . ': Failure fetching node list from database' );
			return false;
		}
		if ( !$res ) {
			return [];
		}
		$nodes = [];
		foreach ( $res as $node ) {
			$ancestor = UUID::create( $node->tree_ancestor_id );
			$descendant = UUID::create( $node->tree_descendant_id );
			$nodes[$ancestor->getAlphadecimal()][$descendant->getAlphadecimal()] = $descendant;
		}

		return $nodes;
	}

	/**
	 * Fetch the id of the immediate parent node of all ids in $nodes.  Non-existent
	 * nodes are not represented in the result set.
	 * @param array $nodes
	 * @return array
	 */
	public function fetchParentMap( array $nodes ) {
		$list = new MultiGetList( $this->cache );
		return $list->get(
			'parent',
			$nodes,
			[ $this, 'fetchParentMapFromDb' ]
		);
	}

	/**
	 * @param UUID[] $nodes
	 * @return UUID[]
	 * @throws DataModelException
	 */
	public function fetchParentMapFromDb( array $nodes ) {
		// Find out who the parent is for those nodes
		$dbr = $this->dbFactory->getDB( DB_REPLICA );
		$res = $dbr->select(
			$this->tableName,
			[ 'tree_ancestor_id', 'tree_descendant_id' ],
			[
				'tree_descendant_id' => UUID::convertUUIDs( $nodes ),
				'tree_depth' => 1,
			],
			__METHOD__
		);
		if ( !$res ) {
			return [];
		}
		$result = [];
		foreach ( $res as $node ) {
			if ( isset( $result[$node->tree_descendant_id] ) ) {
				throw new DataModelException( 'Already have a parent for ' . $node->tree_descendant_id, 'process-data' );
			}
			$descendant = UUID::create( $node->tree_descendant_id );
			$result[$descendant->getAlphadecimal()] = UUID::create( $node->tree_ancestor_id );
		}
		foreach ( $nodes as $node ) {
			if ( !isset( $result[$node->getAlphadecimal()] ) ) {
				// $node is a root, it has no parent
				$result[$node->getAlphadecimal()] = null;
			}
		}

		return $result;
	}
}
