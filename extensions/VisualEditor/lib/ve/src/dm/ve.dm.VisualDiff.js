/*!
 * VisualEditor DataModel VisualDiff class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* global treeDiffer */

/**
 * VisualDiff
 *
 * Gets the diff between two VisualEditor DataModel DocumentNodes
 *
 * @class
 * @constructor
 * @param {ve.dm.Document|ve.dm.BranchNode} oldDocOrNode
 * @param {ve.dm.Document|ve.dm.BranchNode} newDocOrNode
 * @param {number} [timeout=1000] Timeout after which to stop performing linear diffs (in ms)
 */
ve.dm.VisualDiff = function VeDmVisualDiff( oldDocOrNode, newDocOrNode, timeout ) {
	var oldDoc = oldDocOrNode instanceof ve.dm.Document ? oldDocOrNode : oldDocOrNode.getDocument(),
		newDoc = newDocOrNode instanceof ve.dm.Document ? newDocOrNode : newDocOrNode.getDocument();

	this.oldDoc = oldDoc.cloneFromRange(
		oldDocOrNode instanceof ve.dm.DocumentNode || oldDocOrNode instanceof ve.dm.Document ?
			undefined :
			oldDocOrNode.getRange(),
		true,
		'noMetadata'
	);
	this.newDoc = newDoc.cloneFromRange(
		newDocOrNode instanceof ve.dm.DocumentNode || newDocOrNode instanceof ve.dm.Document ?
			undefined :
			newDocOrNode.getRange(),
		true,
		'noMetadata'
	);

	this.oldDocNode = this.oldDoc.getDocumentNode();
	this.newDocNode = this.newDoc.getDocumentNode();
	this.oldDocChildren = this.getDocChildren( this.oldDocNode );
	this.newDocChildren = this.getDocChildren( this.newDocNode );
	this.oldDocInternalListNode = this.oldDoc.getInternalList().getListNode();
	this.newDocInternalListNode = this.newDoc.getInternalList().getListNode();
	this.treeDiffer = treeDiffer;
	this.linearDiffer = new ve.DiffMatchPatch( this.oldDoc.getStore(), this.newDoc.getStore() );

	// Minimum ratio of content (same : different) allowed between two corresponding nodes
	this.diffThreshold = 0.5;

	this.endTime = new Date().getTime() + ( timeout || 1000 );
	this.timedOut = false;

	this.freezeInternalListIndices( this.oldDoc );
	this.freezeInternalListIndices( this.newDoc );

	this.diff = {
		docDiff: this.diffList( this.oldDocChildren, this.newDocChildren ),
		internalListDiff: this.getInternalListDiffInfo()
	};

};

/**
 * Get the children of the document that are not internal list nodes
 *
 * @param {ve.dm.Node} docNode The document node
 * @return {Array} The children of the document node
 */
ve.dm.VisualDiff.prototype.getDocChildren = function ( docNode ) {
	var i, ilen,
		docChildren = [];

	for ( i = 0, ilen = docNode.children.length; i < ilen; i++ ) {
		if (
			!( docNode.children[ i ] instanceof ve.dm.InternalListNode )
		) {
			docChildren.push( docNode.children[ i ] );
		}
	}

	return docChildren;
};

/**
 * Attach the internal list indexOrder to each node referenced by the internal
 * list, ahead of document merge.
 *
 * @param {ve.dm.Document} doc
 */
ve.dm.VisualDiff.prototype.freezeInternalListIndices = function ( doc ) {
	var i, ilen, j, jlen, group, groupName,
		groupIndexOrder, nodeIndex, refNodes,
		nodes = doc.getInternalList().nodes,
		internalListGroups = doc.getInternalList().getNodeGroups();

	for ( groupName in internalListGroups ) {
		group = internalListGroups[ groupName ];
		groupIndexOrder = group.indexOrder;
		for ( i = 0, ilen = groupIndexOrder.length; i < ilen; i++ ) {
			nodeIndex = groupIndexOrder[ i ];
			refNodes = nodes[ groupName ].keyedNodes[ nodes[ groupName ].firstNodes[ nodeIndex ].registeredListKey ];
			for ( j = 0, jlen = refNodes.length; j < jlen; j++ ) {
				ve.setProp( refNodes[ j ].element, 'internal', 'overrideIndex', i + 1 );
			}
		}
	}
};

/**
 * Get the diff between two lists of nodes.
 *
 * @param {ve.dm.Node[]} oldNodes Nodes from the old document
 * @param {ve.dm.Node[]} newNodes Nodes from the new document
 * @return {Object} Object containing diff information
 */
ve.dm.VisualDiff.prototype.diffList = function ( oldNodes, newNodes ) {
	var i, ilen, j, jlen,
		oldNodesToDiff = [],
		newNodesToDiff = [],
		diff = {
			oldToNew: {},
			newToOld: {},
			remove: [],
			insert: []
		};

	// STEP 1: Find identical nodes

	for ( i = 0, ilen = oldNodes.length; i < ilen; i++ ) {
		for ( j = 0, jlen = newNodes.length; j < jlen; j++ ) {
			if ( !Object.prototype.hasOwnProperty.call( diff.newToOld, j ) &&
				this.compareNodes( oldNodes[ i ], newNodes[ j ] )
			) {

				diff.oldToNew[ i ] = j;
				diff.newToOld[ j ] = i;
				break;

			}
			// If no new nodes equalled the old node, add it to nodes to diff
			if ( j === jlen - 1 ) {
				oldNodesToDiff.push( i );
			}
		}
	}

	for ( j = 0; j < jlen; j++ ) {
		if ( !Object.prototype.hasOwnProperty.call( diff.newToOld, j ) ) {
			newNodesToDiff.push( j );
		}
	}

	// STEP 2: Find removed, inserted and modified nodes

	if ( oldNodesToDiff.length !== 0 || newNodesToDiff.length !== 0 ) {

		if ( oldNodesToDiff.length === 0 ) {

			// Everything new is an insert
			diff.insert = newNodesToDiff;

		} else if ( newNodesToDiff.length === 0 ) {

			// Everything old is a remove
			diff.remove = oldNodesToDiff;

		} else {

			// Find out which remaining docChildren are removed, inserted or modified
			this.findModifiedNodes(
				oldNodesToDiff, newNodesToDiff, oldNodes, newNodes, diff
			);

		}
	}

	// STEP 3: Compute moves (up, down, no move)
	if ( Object.keys( diff.newToOld ).length > 0 ) {
		diff.moves = this.calculateDiffMoves( diff.oldToNew, diff.newToOld );
	} else {
		diff.moves = [];
	}

	return diff;
};

/**
 * Calculate how items in a new list have moved, compared to items in the old list.
 * More specifically, calculate the minimal moves, keeping the maximum possible number
 * of nodes unmoved. Do this by finding the longest increasing subsequence in the
 * sequence of oldDoc node indices, sorted by their corresponding newDoc nodes'
 * indices. Those indices in the longest increasing subsequence represent the unmoved
 * nodes.
 *
 * @param {Object} oldToNew Index map of oldDoc nodes to corresponding newDoc nodes
 * @param {Object} newToOld Index map of newDoc nodes to corresponding oldDoc nodes
 * @return {Array} Record of whether and how each newDoc node has moved
 */
ve.dm.VisualDiff.prototype.calculateDiffMoves = function ( oldToNew, newToOld ) {
	var i, ilen, sortedKeys, moves, latestUnmoved, oldIndex,
		oldPermuted = [],
		unmoved = 0,
		up = 'up',
		down = 'down';

	// See https://en.wikipedia.org/wiki/Longest_increasing_subsequence
	function longestIncreasingSubsequence( sequence, oldToNew ) {
		var i, ilen, k, low, high, middle, newLength, oldIndex, newIndex,
			currentLength = 0,
			moves = [],
			// finalIndices[i] holds:
			// - if i is 0, 0
			// - if there's an increasing subsequence of length i, the final item in that subsequence
			// - if i > length of longest increasing subsequence, undefined
			finalIndices = [],
			// previousIndices[i] holds:
			// - if i is in the longest increasing subsequence, the item before i in that subsequence
			// - otherwise, 0
			previousIndices = [];

		finalIndices[ 0 ] = 0;

		// Perform algorithm (i.e. populate finalIndices and previousIndices)
		for ( i = 0, ilen = sequence.length; i < ilen; i++ ) {
			low = 1;
			high = currentLength;
			while ( low <= high ) {
				middle = Math.ceil( ( low + high ) / 2 );
				if ( sequence[ finalIndices[ middle ] ] < sequence[ i ] ) {
					low = middle + 1;
				} else {
					high = middle - 1;
				}
			}
			newLength = low;
			previousIndices[ i ] = finalIndices[ newLength - 1 ];
			finalIndices[ newLength ] = i;
			if ( newLength > currentLength ) {
				currentLength = newLength;
			}
		}

		// Items in the longest increasing subsequence are oldDoc indices of unmoved nodes.
		// Mark corresponding newDoc indices of these unmoved nodes, in moves array.
		k = finalIndices[ currentLength ];
		for ( i = currentLength, ilen = 0; i > ilen; i-- ) {
			oldIndex = sequence[ k ];
			newIndex = oldToNew[ oldIndex ];
			newIndex = typeof newIndex === 'number' ? newIndex : newIndex.node;
			moves[ newIndex ] = unmoved;
			k = previousIndices[ k ];
		}

		return moves;
	}

	// Get oldDoc indices, sorted according to their order in the new doc
	sortedKeys = Object.keys( newToOld ).sort( function ( a, b ) {
		return Number( a ) - Number( b );
	} );
	for ( i = 0, ilen = sortedKeys.length; i < ilen; i++ ) {
		oldIndex = newToOld[ sortedKeys[ i ] ];
		oldPermuted.push( typeof oldIndex === 'number' ? oldIndex : oldIndex.node );
	}

	// Record which newDoc nodes have NOT moved. NB nodes inserted at the end of the
	// newDoc will be treated as not moved by default.
	moves = longestIncreasingSubsequence( oldPermuted, oldToNew );

	// Record whether the remaining newDoc nodes have moved up or down
	// (or not at all, e.g. if they are an insert)
	ilen = Number( sortedKeys[ sortedKeys.length - 1 ] ) + 1;
	for ( i = 0; i < ilen; i++ ) {

		if ( !( i in newToOld ) ) {

			// This node must be an insert, so wasn't moved
			moves[ i ] = unmoved;

		} else if ( moves[ i ] === unmoved ) {

			// This is the latest unmoved node so far
			latestUnmoved = i;

		} else {

			// The node has moved
			if ( latestUnmoved === undefined ) {
				// This node comes before any unmoved nodes so must have moved up
				moves[ i ] = up;
			} else {
				// If this node's oldDoc index is higher than the latest unmoved
				// node's oldDoc index, then it must have moved up; otherwise it
				// must have moved down
				moves[ i ] = newToOld[ i ] > newToOld[ latestUnmoved ] ? up : down;
			}

		}

	}

	return moves;
};

/**
 * Compare the linear data for two nodes
 *
 * @param {ve.dm.Node} oldNode Node from the old document
 * @param {ve.dm.Node} newNode Node from the new document
 * @return {boolean} The linear data is the same
 */
ve.dm.VisualDiff.prototype.compareNodes = function ( oldNode, newNode ) {
	var i, ilen, oldData, newData, oldStore, newStore;

	if ( oldNode.length !== newNode.length || !oldNode.isDiffComparable( newNode ) ) {
		return false;
	}

	oldData = this.oldDoc.getData( oldNode.getOuterRange() );
	newData = this.newDoc.getData( newNode.getOuterRange() );

	if ( JSON.stringify( oldData ) === JSON.stringify( newData ) ) {
		return true;
	}

	// If strings are not equal, the data may still be the same as far as
	// we are concerned so should compare them properly.
	oldStore = this.oldDoc.getStore();
	newStore = this.newDoc.getStore();

	for ( i = 0, ilen = oldData.length; i < ilen; i++ ) {
		if ( oldData[ i ] !== newData[ i ] &&
			!ve.dm.ElementLinearData.static.compareElements( oldData[ i ], newData[ i ], oldStore, newStore ) ) {
			return false;
		}
	}

	return true;
};

/**
 * Diff each old node against each new node in the list. If the differs decide
 * that an old node is similar enough to a new node, record these as a change
 * from the old to the new node and don't diff either of these nodes any more.
 *
 * This might not find the optimal diff in some cases (e.g. if the old node is
 * similar to two of the new nodes), but diffing every old node against every
 * new node could have a heavy performance cost.
 *
 * @param {number[]} oldIndices Indices of the old nodes
 * @param {number[]} newIndices Indices of the new nodes
 * @param {ve.dm.Node[]} oldNodes Nodes from the old document
 * @param {ve.dm.Node[]} newNodes Nodes from the new document
 * @param {Object} diff Object that will contain information about the diff
 */
ve.dm.VisualDiff.prototype.findModifiedNodes = function ( oldIndices, newIndices, oldNodes, newNodes, diff ) {
	var diffResults, i, j,
		ilen = oldIndices.length,
		jlen = newIndices.length;

	for ( i = 0; i < ilen; i++ ) {
		for ( j = 0; j < jlen; j++ ) {

			if ( oldIndices[ i ] !== null && newIndices[ j ] !== null ) {

				diffResults = this.diffNodes( oldNodes[ oldIndices[ i ] ], newNodes[ newIndices[ j ] ] );

				if ( diffResults ) {
					diff.oldToNew[ oldIndices[ i ] ] = {
						node: newIndices[ j ],
						diff: diffResults
					};
					diff.newToOld[ newIndices[ j ] ] = {
						node: oldIndices[ i ]
					};

					oldIndices[ i ] = null;
					newIndices[ j ] = null;
					break;
				}

			}

		}
	}

	// Any nodes remaining in the 'toDiff' arrays are removes and inserts
	for ( i = 0; i < ilen; i++ ) {
		if ( oldIndices[ i ] !== null ) {
			diff.remove.push( oldIndices[ i ] );
		}
	}
	for ( j = 0; j < jlen; j++ ) {
		if ( newIndices[ j ] !== null ) {
			diff.insert.push( newIndices[ j ] );
		}
	}

};

/**
 * Get the diff between a node from the old document and a node from the new
 * document.
 *
 * @param {ve.dm.Node} oldNode Node from the old document
 * @param {ve.dm.Node} newNode Node from the new document
 * @return {Array|boolean} The diff, or false if the nodes are too different
 */
ve.dm.VisualDiff.prototype.diffNodes = function ( oldNode, newNode ) {
	var diff;
	// If not diff comparable, return no diff
	if ( !( oldNode.isDiffComparable( newNode ) ) ) {
		return false;
	}

	// Diff according to whether node behaves like a leaf, list, or tree (default)
	if ( oldNode.isDiffedAsLeaf() ) {
		diff = this.diffLeafNodes( oldNode, newNode );
	} else if ( oldNode.isDiffedAsList() ) {
		diff = this.diffListNodes( oldNode, newNode );
	} else {
		diff = this.diffTreeNodes( oldNode, newNode );
	}

	return diff;
};

/**
 * Diff two leaf nodes
 *
 * @param {ve.dm.Node} oldNode Node from the old document
 * @param {ve.dm.Node} newNode Node from the new document
 * @return {Object|boolean} Leaf diff, or false if the nodes are too different
 * or if the diff timed out
 */
ve.dm.VisualDiff.prototype.diffLeafNodes = function ( oldNode, newNode ) {
	var diff, changeRecord,
		linearDiff = null;

	if ( oldNode.canContainContent() ) {
		changeRecord = {
			keepLength: oldNode.length,
			diffLength: 0
		};
		linearDiff = this.diffContent( oldNode, newNode );
		if ( !( linearDiff ) ) {
			return false;
		}
		this.updateChangeRecordLinearDiff( linearDiff, changeRecord );
		if ( this.underDiffThreshold( changeRecord ) ) {
			return false;
		}
	}

	diff = {
		attributeChange: this.diffAttributes( oldNode, newNode ),
		linearDiff: linearDiff
	};

	return diff;
};

/**
 * Diff two list nodes
 *
 * @param {ve.dm.Node} oldNode Node from the old document
 * @param {ve.dm.Node} newNode Node from the new document
 * @return {Object|boolean} Leaf diff, or false if the nodes are too different
 * or if the diff timed out
 */
ve.dm.VisualDiff.prototype.diffListNodes = function ( oldNode, newNode ) {
	var i, ilen, j, jTemp, listDiff, listDiffInfo, oldMetadata, newMetadata,
		listNodeAttributeChange, listItemAttributeChange, depthChange,
		attributeChange,
		oldFlatList = {
			nodes: [],
			metadata: [],
			indices: []
		},
		newFlatList = {
			nodes: [],
			metadata: [],
			indices: []
		};

	this.flattenList( oldNode, oldFlatList, 0 );
	this.flattenList( newNode, newFlatList, 0 );

	for ( i = 0, ilen = oldFlatList.nodes.length; i < ilen; i++ ) {
		oldFlatList.indices.push( { indexOrder: i } );
	}
	for ( i = 0, ilen = newFlatList.nodes.length; i < ilen; i++ ) {
		newFlatList.indices.push( { indexOrder: i } );
	}

	listDiff = this.diffList( oldFlatList.nodes, newFlatList.nodes );

	// Do metadata diff of all aligned nodes
	for ( i in listDiff.oldToNew ) {
		jTemp = listDiff.oldToNew[ i ];
		j = typeof jTemp === 'number' ? jTemp : jTemp.node;

		oldMetadata = oldFlatList.metadata[ i ];
		newMetadata = newFlatList.metadata[ j ];

		listNodeAttributeChange = this.diffAttributes( oldMetadata.listNode, newMetadata.listNode );
		listItemAttributeChange = this.diffAttributes( oldMetadata.listItem, newMetadata.listItem );
		depthChange = oldMetadata.depth === newMetadata.depth ? false :
			{
				oldAttributes: { listItemDepth: oldMetadata.depth },
				newAttributes: { listItemDepth: newMetadata.depth }
			};

		if ( listNodeAttributeChange || listItemAttributeChange || depthChange ) {

			// Some attributes have changed for this item
			attributeChange = {};
			if ( listNodeAttributeChange ) {
				attributeChange.listNodeAttributeChange = listNodeAttributeChange;
			}
			if ( listItemAttributeChange ) {
				attributeChange.listItemAttributeChange = listItemAttributeChange;
			}
			if ( depthChange ) {
				attributeChange.depthChange = depthChange;
			}

			if ( typeof listDiff.oldToNew[ i ] === 'number' ) {
				listDiff.oldToNew[ i ] = {
					node: j,
					diff: {
						attributeChange: attributeChange
					}
				};
			} else {
				listDiff.oldToNew[ i ].diff.attributeChange = attributeChange;
			}
			listDiff.newToOld[ j ] = { node: i };
		}

	}

	listDiffInfo = this.getListDiffInfo( listDiff, oldFlatList.indices, newFlatList.indices );

	listDiffInfo.oldList = oldFlatList;
	listDiffInfo.newList = newFlatList;

	return listDiffInfo;
};

/**
 * Flatten a (potentially nested) list, ready for diffing. Lists are common, and
 * tree diffs of lists are expensive, so lists are flattened then diffed as
 * linear structures.
 *
 * Appends information for each list item to a flat list object. Will be called
 * once for each list node within a nested list.
 *
 * If a list item contains a non-list node that contains a list, that list will
 * not get flattened out. If a list item contains more than one identical list
 * node, they will be flattened out to the same depth, and the information that
 * they were separate lists will be lost.
 *
 * @param {ve.dm.Node} listNode A list node, possibly nested inside another list
 * @param {Object} flatList Flat structure describing the entire list
 * @param {number} depth Depth of this list node with respect to the outermost
 */
ve.dm.VisualDiff.prototype.flattenList = function ( listNode, flatList, depth ) {
	var i, ilen, j, jlen, listItem,
		listContents, listContent,
		listItems = listNode.children;

	for ( i = 0, ilen = listItems.length; i < ilen; i++ ) {
		listItem = listItems[ i ];
		listContents = listItem.children;
		for ( j = 0, jlen = listContents.length; j < jlen; j++ ) {
			listContent = listContents[ j ];
			if ( listContent.isDiffedAsList() ) {
				this.flattenList( listContent, flatList, depth + 1 );
			} else {
				flatList.metadata.push( {
					listNode: listNode,
					listItem: listItem,
					depth: depth
				} );
				flatList.nodes.push( listContent );
			}
		}
	}
};

/**
 * Align two tree structures
 *
 * @param {treeDiffer.Tree} oldTree Tree rooted at the old node
 * @param {treeDiffer.Tree} newTree Tree rooted at the new node
 * @return {Array|boolean} Corresponding tree node indices, or false if timed out
 */
ve.dm.VisualDiff.prototype.alignTrees = function ( oldTree, newTree ) {
	var transactions = new this.treeDiffer.Differ( oldTree, newTree ).transactions;

	if ( transactions === null ) {
		// Tree diff timed out
		this.timedOut = true;
		return false;
	}

	return transactions[ oldTree.orderedNodes.length - 1 ][ newTree.orderedNodes.length - 1 ];
};

/**
 * Do a tree diff. There are three steps: (1) Do a tree diff to find the minimal
 * transactions between the old tree and the new tree. Allowed transactions
 * are: remove a node, insert a node, or change an old node to a new node. (The
 * cost of each transaction is the same, and the change always costs the same,
 * no matter how similar the nodes are.) The tree differ is not currently aware
 * of legal relationships between nodes, and ve.dm.ContentBranchNodes are
 * treated as leaves. (2) Do a linear diff on the linear data of any changed
 * pair that are both ve.dm.ContentBranchNodes. (3) Find the ratio of the
 * linear data that has changed to the linear data that is retained. If this is
 * above a threshold, the nodes are too different and the old node has not
 * been changed to make the new node, and the diff should be discarded.
 * Otherwise the diff should be cleaned and returned.
 *
 * TODO: It would be possible to discover within-node moves by comparing
 * removed and inserted nodes from the tree differ.
 *
 * @param {ve.dm.Node} oldTreeNode Node from the old document
 * @param {ve.dm.Node} newTreeNode Node from the new document
 * @return {Object|boolean} Diff object, or false if nodes are too different
 * @return {ve.DiffTreeNode[]} return.oldTreeOrderedNodes nodes of the old tree, deepest first then in document order
 * @return {ve.DiffTreeNode[]} return.newTreeOrderedNodes nodes of the new tree, deepest first then in document order
 * @return {Array[]} return.treeDiff Node correspondences as indexes in *TreeOrderedNodes
 * @return {number[]} return.treeDiff.i The i'th correspondence [ oldTreeOrderedNodes index, newTreeOrderedNodes index ]
 * @return {Object|null} return.diffInfo Linear diffs applying to each corresponding node pair
 * @return {Object} return.diffInfo.i Linear diff applying to i'th node in newTreeOrderedNodes
 * @return {Array|boolean} return.diffInfo.i.linearDiff Output of #diffContent
 * @return {Array|boolean} return.diffInfo.i.attributeChange Output of #diffAttributes
 */
ve.dm.VisualDiff.prototype.diffTreeNodes = function ( oldTreeNode, newTreeNode ) {
	var i, ilen,
		treeDiff, linearDiff,
		oldNode, newNode,
		oldTree,
		newTree,
		changeRecord = {
			removeLength: 0,
			insertLength: 0,
			diffLength: 0,
			keepLength: 0
		},
		diffInfo = [];

	oldTree = new this.treeDiffer.Tree( oldTreeNode, ve.DiffTreeNode );
	newTree = new this.treeDiffer.Tree( newTreeNode, ve.DiffTreeNode );

	treeDiff = this.alignTrees( oldTree, newTree );

	// Length of old content is length of old node minus the open and close
	// tags for each node
	changeRecord.keepLength = oldTreeNode.length - 2 * ( oldTree.orderedNodes.length - 1 );

	for ( i = 0, ilen = treeDiff.length; i < ilen; i++ ) {

		if ( treeDiff[ i ][ 0 ] !== null && treeDiff[ i ][ 1 ] !== null ) {

			// There is a change
			oldNode = oldTree.orderedNodes[ treeDiff[ i ][ 0 ] ].node;
			newNode = newTree.orderedNodes[ treeDiff[ i ][ 1 ] ].node;

			if ( !oldNode.canContainContent() && !newNode.canContainContent() ) {

				// There is no content change
				if ( oldNode.isDiffComparable( newNode ) ) {
					diffInfo[ i ] = {
						linearDiff: null,
						attributeChange: this.diffAttributes( oldNode, newNode )
					};
				}

			} else if ( !newNode.canContainContent() ) {

				// Content was removed
				this.updateChangeRecord( oldNode.length, true, changeRecord );

			} else if ( !oldNode.canContainContent() ) {

				// Content was inserted
				this.updateChangeRecord( newNode.length, false, changeRecord );

			// If we got this far, they are both CBNs
			} else {

				if ( oldNode.isDiffComparable( newNode ) ) {
					linearDiff = this.diffContent( oldNode, newNode, changeRecord );
					diffInfo[ i ] = {
						linearDiff: linearDiff,
						attributeChange: this.diffAttributes( oldNode, newNode )
					};
				}

				if ( linearDiff ) {
					this.updateChangeRecordLinearDiff( linearDiff, changeRecord );
				} else {
					this.updateChangeRecord( oldNode.getLength(), true, changeRecord );
					this.updateChangeRecord( newNode.getLength(), false, changeRecord );
				}
			}

		} else if ( treeDiff[ i ][ 0 ] !== null ) {

			// Node was removed
			oldNode = oldTree.orderedNodes[ treeDiff[ i ][ 0 ] ];
			if ( oldNode.node.canContainContent() ) {
				this.updateChangeRecord( oldNode.node.length, true, changeRecord );
			}

		} else {

			// Node was inserted
			newNode = newTree.orderedNodes[ treeDiff[ i ][ 1 ] ];
			if ( newNode.node.canContainContent() ) {
				this.updateChangeRecord( newNode.node.length, false, changeRecord );
			}

		}

	}

	// Only return the diff if a high enough proportion of the content is
	// unchanged; otherwise, these nodes don't correspond and shouldn't be
	// diffed.
	if ( this.underDiffThreshold( changeRecord ) ) {
		return false;
	}

	return {
		treeDiff: treeDiff,
		diffInfo: diffInfo,
		oldTreeOrderedNodes: oldTree.orderedNodes,
		newTreeOrderedNodes: newTree.orderedNodes,
		correspondingNodes: this.treeDiffer.Differ.prototype.getCorrespondingNodes(
			treeDiff, oldTree.orderedNodes.length, newTree.orderedNodes.length
		)
	};
};

/**
 * Find the difference between attributes of two nodes
 *
 * @param {ve.dm.Node} oldNode Node from the old document
 * @param {ve.dm.Node} newNode Node from the new document
 * @return {Object|boolean} The attributes diff, or false if unchanged
 */
ve.dm.VisualDiff.prototype.diffAttributes = function ( oldNode, newNode ) {
	var attributesUnchanged = ve.compare( oldNode.getAttributes(), newNode.getAttributes() );

	if ( attributesUnchanged ) {
		return false;
	}
	return {
		oldAttributes: oldNode.getAttributes(),
		newAttributes: newNode.getAttributes()
	};
};

/**
 * Find the difference between linear data in two content branch nodes
 *
 * @param {ve.dm.ContentBranchNode} oldNode Node from the old document
 * @param {ve.dm.ContentBranchNode} newNode Node from the new document
 * @param {Object} changeRecord Record of the length of changed content
 * @return {Array|boolean} The linear diff, or false if timed out
 */
ve.dm.VisualDiff.prototype.diffContent = function ( oldNode, newNode ) {
	var linearDiff;

	if ( new Date().getTime() < this.endTime ) {

		linearDiff = this.linearDiffer.getCleanDiff(
			this.oldDoc.getData( oldNode.getRange() ),
			this.newDoc.getData( newNode.getRange() ),
			{ keepOldText: false }
		);
		this.timedOut = !!linearDiff.timedOut;

	} else {

		linearDiff = false;
		this.timedOut = true;

	}

	return linearDiff;
};

/**
 * Increment the record of the length of changed and unchanged content for
 * a node, given the length the removed or inserted content in one of its
 * descendants
 *
 * @param {number} length Length of removed or inserted content
 * @param {boolean} removed The content was removed (if false, was inserted)
 * @param {Object} changeRecord Record of the running totals for changed and
 * unchanged content
 */
ve.dm.VisualDiff.prototype.updateChangeRecord = function ( length, removed, changeRecord ) {
	changeRecord.diffLength += length;
	if ( removed ) {
		changeRecord.keepLength -= length;
	}
};

/**
 * Increment the record of the length of changed and unchanged content for
 * a node, given the linear diff for one of its descendants
 *
 * @param {Array} linearDiff Diff of the content
 * @param {Object} changeRecord Record of the running totals for changed and
 * unchanged content
 */
ve.dm.VisualDiff.prototype.updateChangeRecordLinearDiff = function ( linearDiff, changeRecord ) {
	var i, ilen,
		DIFF_INSERT = ve.DiffMatchPatch.static.DIFF_INSERT,
		DIFF_DELETE = ve.DiffMatchPatch.static.DIFF_DELETE;

	for ( i = 0, ilen = linearDiff.length; i < ilen; i++ ) {
		if ( linearDiff[ i ][ 0 ] === DIFF_INSERT ) {
			this.updateChangeRecord( linearDiff[ i ][ 1 ].length, false, changeRecord );
		} else if ( linearDiff[ i ][ 0 ] === DIFF_DELETE ) {
			this.updateChangeRecord( linearDiff[ i ][ 1 ].length, true, changeRecord );
		}
	}
};

/**
 * Check whether the proportion of changed content between two nodes is under
 * the threshold for accepting that the two nodes correspond.
 *
 * @param {Object} changeRecord Record for the proportion of content changed
 * @return {boolean} The proportion of changed content is under the threshold
 */
ve.dm.VisualDiff.prototype.underDiffThreshold = function ( changeRecord ) {
	return changeRecord.keepLength < this.diffThreshold * changeRecord.diffLength;
};

/*
 * Get the diff between the old document's internal list and the new document's
 * internal list. The diff is grouped by list group, and each node in each list
 * group is marked as removed, inserted, the same, or changed (in which case the
 * linear diff is given).
 *
 * @return {Object} Internal list diff object
 */
ve.dm.VisualDiff.prototype.getInternalListDiffInfo = function () {
	var i, ilen, diff, diffInfo, group,
		oldDocNodeGroups = this.oldDoc.getInternalList().getNodeGroups(),
		newDocNodeGroups = this.newDoc.getInternalList().getNodeGroups(),
		oldDocInternalListItems,
		newDocInternalListItems,
		groups = [],
		internalListDiffInfo = {};

	function getInternalListItemsToDiff( indexOrder, nodes ) {
		var i, ilen, nodeIndex,
			internalListItems = {
				toDiff: [],
				indices: []
			};

		for ( i = 0, ilen = indexOrder.length; i < ilen; i++ ) {
			nodeIndex = indexOrder[ i ];
			if ( nodeIndex !== null ) {
				internalListItems.toDiff.push( nodes[ nodeIndex ] );
				internalListItems.indices.push( {
					indexOrder: i,
					nodeIndex: nodeIndex
				} );
			}
		}

		return internalListItems;
	}

	function getInternalListItems( indexOrder, nodes, action ) {
		var i, ilen, nodeIndex, internalListItems = [];

		for ( i = 0, ilen = indexOrder.length; i < ilen; i++ ) {
			nodeIndex = indexOrder[ i ];
			if ( nodeIndex !== null ) {
				internalListItems.push( {
					diff: action,
					indexOrder: i,
					nodeIndex: nodeIndex
				} );
			}
		}

		return internalListItems;
	}

	// Find all groups common to old and new docs
	// Also find inserted groups
	for ( group in newDocNodeGroups ) {
		if ( group in oldDocNodeGroups ) {
			groups.push( {
				group: group,
				action: 'diff'
			} );
		} else {
			groups.push( {
				group: group,
				action: 'insert'
			} );
		}
	}

	// Find removed groups
	for ( group in oldDocNodeGroups ) {
		if ( !( group in newDocNodeGroups ) ) {
			groups.push( {
				group: group,
				action: 'remove'
			} );
		}
	}

	// Diff the internal list items for each group
	for ( i = 0, ilen = groups.length; i < ilen; i++ ) {
		group = groups[ i ];

		switch ( group.action ) {
			case 'diff':

				// Get old and new doc internal list items for this group
				oldDocInternalListItems = getInternalListItemsToDiff(
					oldDocNodeGroups[ group.group ].indexOrder,
					this.oldDocInternalListNode.children
				);
				newDocInternalListItems = getInternalListItemsToDiff(
					newDocNodeGroups[ group.group ].indexOrder,
					this.newDocInternalListNode.children
				);

				// Diff internal list items
				diff = this.diffList(
					oldDocInternalListItems.toDiff,
					newDocInternalListItems.toDiff
				);

				diffInfo = this.getListDiffInfo(
					diff, oldDocInternalListItems.indices, newDocInternalListItems.indices, true
				);
				if ( diffInfo ) {
					internalListDiffInfo[ group.group ] = diffInfo;
				}

				break;

			case 'insert':

				// Get new doc internal list items for this group and mark as inserted
				internalListDiffInfo[ group.group ] = getInternalListItems(
					newDocNodeGroups[ group.group ].indexOrder,
					this.newDocInternalListNode.children,
					1
				);
				internalListDiffInfo[ group.group ].changes = true;
				break;

			case 'remove':

				// Get old doc internal list items for this group and mark as removed
				internalListDiffInfo[ group.group ] = getInternalListItems(
					oldDocNodeGroups[ group.group ].indexOrder,
					this.oldDocInternalListNode.children,
					-1
				);
				internalListDiffInfo[ group.group ].changes = true;
				break;

		}
	}

	return internalListDiffInfo;
};

/**
 * Parse list diff into format that diff element can interpret
 *
 * @param {Object} diff The list diff
 * @param {Array} oldItems Old list items
 * @param {Array} newItems New list items
 * @param {boolean} internalListDiff Diff is of the internal list
 * @return {Array|boolean} The list diff information, or false if there are no changes
 */
ve.dm.VisualDiff.prototype.getListDiffInfo = function ( diff, oldItems, newItems, internalListDiff ) {
	var i, ilen, item, itemIndex, listDiffInfo;

	function containsDiff( diffObject ) {
		var i;
		for ( i in diffObject ) {
			if ( typeof diffObject[ i ] !== 'number' ) {
				return true;
			}
		}
		return false;
	}

	// Do not match within-document lists that have no corresponding list items
	if ( !( internalListDiff ) && ve.isEmptyObject( diff.oldToNew ) ) {
		return false;
	}

	// Check there actually are any changes
	if (
		( diff.remove.length > 0 || diff.insert.length > 0 ) ||
		( containsDiff( diff.oldToNew ) || containsDiff( diff.moves ) )
	) {

		// There are changes.
		// Mark each new item as unchanged, changed or inserted
		for ( i = 0, ilen = newItems.length; i < ilen; i++ ) {
			item = newItems[ i ];
			itemIndex = item.indexOrder;
			if ( typeof diff.newToOld[ itemIndex ] === 'number' ) {

				// Item hasn't changed
				item.diff = 0;

			} else if ( diff.newToOld[ itemIndex ] === undefined ) {

				// Item was inserted
				item.diff = 1;

			} else {

				// Item has changed
				// (The diff object is stored in oldToNew)
				item.diff = diff.oldToNew[
					diff.newToOld[ itemIndex ].node
				].diff;

			}
		}

		// Add all removed items and mark as removed
		for ( i = 0, ilen = oldItems.length; i < ilen; i++ ) {
			item = oldItems[ i ];
			if ( diff.remove.indexOf( i ) !== -1 ) {

				// Item is either not in the new list, or has been marked
				// as a remove-insert, so mark as removed
				item.diff = -1;
				newItems.push( item );

			}
		}

		// Sort items by index order
		listDiffInfo = newItems.sort( function ( a, b ) {
			if ( a.indexOrder === b.indexOrder ) {

				// When index order is the same, put removed item first
				return a.diff === -1 ? -1 : 1;

			}
			return a.indexOrder > b.indexOrder ? 1 : -1;
		} );

		listDiffInfo.changes = true;
		listDiffInfo.moves = diff.moves;

		return listDiffInfo;

	} else {

		return false;

	}

};
