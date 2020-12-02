<?php

use MediaWiki\Revision\SlotRecord;

/**
 * @since 1.31
 */
interface ImportableOldRevision {

	/**
	 * @since 1.31
	 * @return User
	 */
	public function getUserObj();

	/**
	 * @since 1.31
	 * @return string
	 */
	public function getUser();

	/**
	 * @since 1.31
	 * @return Title
	 */
	public function getTitle();

	/**
	 * @since 1.31
	 * @return string
	 */
	public function getTimestamp();

	/**
	 * @since 1.31
	 * @return string
	 */
	public function getComment();

	/**
	 * @since 1.31
	 * @return string
	 */
	public function getModel();

	/**
	 * @since 1.31
	 * @return string
	 */
	public function getFormat();

	/**
	 * @since 1.31
	 * @param string $role
	 * @return Content
	 */
	public function getContent( $role = SlotRecord::MAIN );

	/**
	 * @since 1.35
	 * @param string $role
	 * @return SlotRecord
	 */
	public function getSlot( $role );

	/**
	 * @since 1.35
	 * @return string[]
	 */
	public function getSlotRoles();

	/**
	 * @since 1.31
	 * @return bool
	 */
	public function getMinor();

	/**
	 * @since 1.31
	 * @return bool|string
	 */
	public function getSha1Base36();

	/**
	 * @since 1.34
	 * @return string[]
	 */
	public function getTags();

}
