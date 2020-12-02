var ItemModel = require( './ItemModel.js' ),
	FilterItem;

/**
 * Filter item model
 *
 * @class mw.rcfilters.dm.FilterItem
 * @extends mw.rcfilters.dm.ItemModel
 *
 * @constructor
 * @param {string} param Filter param name
 * @param {mw.rcfilters.dm.FilterGroup} groupModel Filter group model
 * @param {Object} config Configuration object
 * @cfg {string[]} [excludes=[]] A list of filter names this filter, if
 *  selected, makes inactive.
 * @cfg {string[]} [subset] Defining the names of filters that are a subset of this filter
 * @cfg {Object} [conflicts] Defines the conflicts for this filter
 * @cfg {boolean} [visible=true] The visibility of the group
 */
FilterItem = function MwRcfiltersDmFilterItem( param, groupModel, config ) {
	config = config || {};

	this.groupModel = groupModel;

	// Parent
	FilterItem.parent.call( this, param, $.extend( {
		namePrefix: this.groupModel.getNamePrefix()
	}, config ) );
	// Mixin constructor
	OO.EventEmitter.call( this );

	// Interaction definitions
	this.subset = config.subset || [];
	this.conflicts = config.conflicts || {};
	this.superset = [];
	this.visible = config.visible === undefined ? true : !!config.visible;

	// Interaction states
	this.included = false;
	this.conflicted = false;
	this.fullyCovered = false;
};

/* Initialization */

OO.inheritClass( FilterItem, ItemModel );

/* Methods */

/**
 * Return the representation of the state of this item.
 *
 * @return {Object} State of the object
 */
FilterItem.prototype.getState = function () {
	return {
		selected: this.isSelected(),
		included: this.isIncluded(),
		conflicted: this.isConflicted(),
		fullyCovered: this.isFullyCovered()
	};
};

/**
 * Get the message for the display area for the currently active conflict
 *
 * @return {string} Conflict result message key
 */
FilterItem.prototype.getCurrentConflictResultMessage = function () {
	var details;

	// First look in filter's own conflicts
	details = this.getConflictDetails( this.getOwnConflicts(), 'globalDescription' );
	if ( !details.message ) {
		// Fall back onto conflicts in the group
		details = this.getConflictDetails( this.getGroupModel().getConflicts(), 'globalDescription' );
	}

	return details.message;
};

/**
 * Get the details of the active conflict on this filter
 *
 * @private
 * @param {Object} conflicts Conflicts to examine
 * @param {string} [key='contextDescription'] Message key
 * @return {Object} Object with conflict message and conflict items
 * @return {string} return.message Conflict message
 * @return {string[]} return.names Conflicting item labels
 */
FilterItem.prototype.getConflictDetails = function ( conflicts, key ) {
	var group,
		conflictMessage = '',
		itemLabels = [];

	key = key || 'contextDescription';

	// eslint-disable-next-line no-jquery/no-each-util
	$.each( conflicts, function ( filterName, conflict ) {
		if ( !conflict.item.isSelected() ) {
			return;
		}

		if ( !conflictMessage ) {
			conflictMessage = conflict[ key ];
			group = conflict.group;
		}

		if ( group === conflict.group ) {
			itemLabels.push( mw.msg( 'quotation-marks', conflict.item.getLabel() ) );
		}
	} );

	return {
		message: conflictMessage,
		names: itemLabels
	};

};

/**
 * @inheritdoc
 */
FilterItem.prototype.getStateMessage = function () {
	var messageKey, details, superset,
		affectingItems = [];

	if ( this.isSelected() ) {
		if ( this.isConflicted() ) {
			// First look in filter's own conflicts
			details = this.getConflictDetails( this.getOwnConflicts() );
			if ( !details.message ) {
				// Fall back onto conflicts in the group
				details = this.getConflictDetails( this.getGroupModel().getConflicts() );
			}

			messageKey = details.message;
			affectingItems = details.names;
		} else if ( this.isIncluded() && !this.isHighlighted() ) {
			// We only show the 'no effect' full-coverage message
			// if the item is also not highlighted. See T161273
			superset = this.getSuperset();
			// For this message we need to collect the affecting superset
			affectingItems = this.getGroupModel().findSelectedItems( this )
				.filter( function ( item ) {
					return superset.indexOf( item.getName() ) !== -1;
				} )
				.map( function ( item ) {
					return mw.msg( 'quotation-marks', item.getLabel() );
				} );

			messageKey = 'rcfilters-state-message-subset';
		} else if ( this.isFullyCovered() && !this.isHighlighted() ) {
			affectingItems = this.getGroupModel().findSelectedItems( this )
				.map( function ( item ) {
					return mw.msg( 'quotation-marks', item.getLabel() );
				} );

			messageKey = 'rcfilters-state-message-fullcoverage';
		}
	}

	if ( messageKey ) {
		// Build message
		// The following messages are used here:
		// * rcfilters-state-message-subset
		// * rcfilters-state-message-fullcoverage
		// * conflict.message values...
		return mw.msg(
			messageKey,
			mw.language.listToText( affectingItems ),
			affectingItems.length
		);
	}

	// Display description
	return this.getDescription();
};

/**
 * Get the model of the group this filter belongs to
 *
 * @return {mw.rcfilters.dm.FilterGroup} Filter group model
 */
FilterItem.prototype.getGroupModel = function () {
	return this.groupModel;
};

/**
 * Get the group name this filter belongs to
 *
 * @return {string} Filter group name
 */
FilterItem.prototype.getGroupName = function () {
	return this.groupModel.getName();
};

/**
 * Get filter subset
 * This is a list of filter names that are defined to be included
 * when this filter is selected.
 *
 * @return {string[]} Filter subset
 */
FilterItem.prototype.getSubset = function () {
	return this.subset;
};

/**
 * Get filter superset
 * This is a generated list of filters that define this filter
 * to be included when either of them is selected.
 *
 * @return {string[]} Filter superset
 */
FilterItem.prototype.getSuperset = function () {
	return this.superset;
};

/**
 * Check whether the filter is currently in a conflict state
 *
 * @return {boolean} Filter is in conflict state
 */
FilterItem.prototype.isConflicted = function () {
	return this.conflicted;
};

/**
 * Check whether the filter is currently in an already included subset
 *
 * @return {boolean} Filter is in an already-included subset
 */
FilterItem.prototype.isIncluded = function () {
	return this.included;
};

/**
 * Check whether the filter is currently fully covered
 *
 * @return {boolean} Filter is in fully-covered state
 */
FilterItem.prototype.isFullyCovered = function () {
	return this.fullyCovered;
};

/**
 * Get all conflicts associated with this filter or its group
 *
 * Conflict object is set up by filter name keys and conflict
 * definition. For example:
 *
 *  {
 *      filterName: {
 *          filter: filterName,
 *          group: group1,
 *          label: itemLabel,
 *          item: itemModel
 *      }
 *      filterName2: {
 *          filter: filterName2,
 *          group: group2
 *          label: itemLabel2,
 *          item: itemModel2
 *      }
 *  }
 *
 * @return {Object} Filter conflicts
 */
FilterItem.prototype.getConflicts = function () {
	return $.extend( {}, this.conflicts, this.getGroupModel().getConflicts() );
};

/**
 * Get the conflicts associated with this filter
 *
 * @return {Object} Filter conflicts
 */
FilterItem.prototype.getOwnConflicts = function () {
	return this.conflicts;
};

/**
 * Set conflicts for this filter. See #getConflicts for the expected
 * structure of the definition.
 *
 * @param {Object} conflicts Conflicts for this filter
 */
FilterItem.prototype.setConflicts = function ( conflicts ) {
	this.conflicts = conflicts || {};
};

/**
 * Set filter superset
 *
 * @param {string[]} superset Filter superset
 */
FilterItem.prototype.setSuperset = function ( superset ) {
	this.superset = superset || [];
};

/**
 * Set filter subset
 *
 * @param {string[]} subset Filter subset
 */
FilterItem.prototype.setSubset = function ( subset ) {
	this.subset = subset || [];
};

/**
 * Check whether a filter exists in the subset list for this filter
 *
 * @param {string} filterName Filter name
 * @return {boolean} Filter name is in the subset list
 */
FilterItem.prototype.existsInSubset = function ( filterName ) {
	return this.subset.indexOf( filterName ) > -1;
};

/**
 * Check whether this item has a potential conflict with the given item
 *
 * This checks whether the given item is in the list of conflicts of
 * the current item, but makes no judgment about whether the conflict
 * is currently at play (either one of the items may not be selected)
 *
 * @param {mw.rcfilters.dm.FilterItem} filterItem Filter item
 * @return {boolean} This item has a conflict with the given item
 */
FilterItem.prototype.existsInConflicts = function ( filterItem ) {
	return Object.prototype.hasOwnProperty.call( this.getConflicts(), filterItem.getName() );
};

/**
 * Set the state of this filter as being conflicted
 * (This means any filters in its conflicts are selected)
 *
 * @param {boolean} [conflicted] Filter is in conflict state
 * @fires update
 */
FilterItem.prototype.toggleConflicted = function ( conflicted ) {
	conflicted = conflicted === undefined ? !this.conflicted : conflicted;

	if ( this.conflicted !== conflicted ) {
		this.conflicted = conflicted;
		this.emit( 'update' );
	}
};

/**
 * Set the state of this filter as being already included
 * (This means any filters in its superset are selected)
 *
 * @param {boolean} [included] Filter is included as part of a subset
 * @fires update
 */
FilterItem.prototype.toggleIncluded = function ( included ) {
	included = included === undefined ? !this.included : included;

	if ( this.included !== included ) {
		this.included = included;
		this.emit( 'update' );
	}
};

/**
 * Toggle the fully covered state of the item
 *
 * @param {boolean} [isFullyCovered] Filter is fully covered
 * @fires update
 */
FilterItem.prototype.toggleFullyCovered = function ( isFullyCovered ) {
	isFullyCovered = isFullyCovered === undefined ? !this.fullycovered : isFullyCovered;

	if ( this.fullyCovered !== isFullyCovered ) {
		this.fullyCovered = isFullyCovered;
		this.emit( 'update' );
	}
};

/**
 * Toggle the visibility of this item
 *
 * @param {boolean} [isVisible] Item is visible
 */
FilterItem.prototype.toggleVisible = function ( isVisible ) {
	isVisible = isVisible === undefined ? !this.visible : !!isVisible;

	if ( this.visible !== isVisible ) {
		this.visible = isVisible;
		this.emit( 'update' );
	}
};

/**
 * Check whether the item is visible
 *
 * @return {boolean} Item is visible
 */
FilterItem.prototype.isVisible = function () {
	return this.visible;
};

module.exports = FilterItem;
