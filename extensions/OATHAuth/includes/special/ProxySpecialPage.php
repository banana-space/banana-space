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
 */

/**
 * A proxy class that routes a special page to other special pages based on
 * request parameters
 */
abstract class ProxySpecialPage extends SpecialPage {
	/**
	 * @var SpecialPage|null Target page to execute
	 */
	private $target = null;

	/**
	 * Instantiate a SpecialPage based on request parameters
	 *
	 * The page returned by this function will be cached and used as
	 * the target page for this proxy object.
	 *
	 * @return SpecialPage
	 */
	abstract protected function getTargetPage();

	/**
	 * Helper function that initializes the target SpecialPage object
	 */
	private function init() {
		if ( $this->target === null ) {
			$this->target = $this->getTargetPage();
		}
	}

	/**
	 * Magic function that proxies function calls to the target object
	 *
	 * @param string $method Method name being called
	 * @param array $args Array of arguments
	 *
	 * @return mixed
	 */
	public function __call( $method, $args ) {
		$this->init();
		return call_user_func_array( [ $this->target, $method ], $args );
	}

	/**
	 * @return string
	 */
	function getName() {
		$this->init();
		return $this->target->getName();
	}

	/**
	 * @param string|bool $subpage
	 * @return Title
	 */
	function getPageTitle( $subpage = false ) {
		$this->init();
		return $this->target->getPageTitle( $subpage );
	}

	/**
	 * @return string
	 */
	function getLocalName() {
		$this->init();
		return $this->target->getLocalName();
	}

	/**
	 * @return string
	 */
	function getRestriction() {
		$this->init();
		return $this->target->getRestriction();
	}

	/**
	 * @return bool
	 */
	function isListed() {
		$this->init();
		return $this->target->isListed();
	}

	/**
	 * @param bool $listed
	 * @return bool
	 */
	function setListed( $listed ) {
		$this->init();
		return $this->target->setListed( $listed );
	}

	/**
	 * @param bool|null $x
	 * @return bool
	 */
	function listed( $x = null ) {
		$this->init();
		return $this->target->listed( $x );
	}

	/**
	 * @return bool
	 */
	public function isIncludable() {
		$this->init();
		return $this->target->isIncludable();
	}

	/**
	 * @param bool|null $x
	 * @return bool
	 */
	function including( $x = null ) {
		$this->init();
		return $this->target->including( $x );
	}

	/**
	 * @return bool
	 */
	public function isRestricted() {
		$this->init();
		return $this->target->isRestricted();
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function userCanExecute( User $user ) {
		$this->init();
		return $this->target->userCanExecute( $user );
	}

	/**
	 * @throws PermissionsError
	 */
	function displayRestrictionError() {
		$this->init();
		$this->target->displayRestrictionError();
	}

	/**
	 * @return void
	 * @throws PermissionsError
	 */
	public function checkPermissions() {
		$this->init();
		$this->target->checkPermissions();
	}

	/**
	 * @param string|null $subPage
	 */
	protected function beforeExecute( $subPage ) {
		$this->init();
		$this->target->beforeExecute( $subPage );
	}

	/**
	 * @param string|null $subPage
	 */
	protected function afterExecute( $subPage ) {
		$this->init();
		$this->target->afterExecute( $subPage );
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->init();
		$this->target->execute( $subPage );
	}

	/**
	 * @return string
	 */
	function getDescription() {
		$this->init();
		return $this->target->getDescription();
	}

	/**
	 * @param IContextSource $context
	 */
	public function setContext( $context ) {
		$this->init();
		$this->target->setContext( $context );
		parent::setContext( $context );
	}

	/**
	 * @return string
	 */
	protected function getRobotPolicy() {
		$this->init();
		return $this->target->getRobotPolicy();
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		$this->init();
		return $this->target->getGroupName();
	}
}
