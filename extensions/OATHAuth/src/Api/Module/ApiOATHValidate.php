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

namespace MediaWiki\Extension\OATHAuth\Api\Module;

use ApiBase;
use ApiResult;
use FormatJson;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\Module\TOTP;
use MediaWiki\MediaWikiServices;
use User;

/**
 * Validate an OATH token.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiOATHValidate extends ApiBase {
	public function execute() {
		// Be extra paranoid about the data that is sent
		$this->requireAtLeastOneParameter( $this->extractRequestParams(), 'totp', 'data' );
		$this->requirePostedParameters( [ 'token', 'data', 'totp' ] );

		$params = $this->extractRequestParams();
		if ( $params['user'] === null ) {
			$params['user'] = $this->getUser()->getName();
		}

		$this->checkUserRightsAny( 'oathauth-api-all' );

		$user = User::newFromName( $params['user'] );
		if ( $user === false ) {
			$this->dieWithError( 'noname' );
		}

		// Don't increase pingLimiter, just check for limit exceeded.
		if ( $user->pingLimiter( 'badoath', 0 ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}

		$result = [
			ApiResult::META_BC_BOOLS => [ 'enabled', 'valid' ],
			'enabled' => false,
			'valid' => false,
			'module' => ''
		];

		if ( !$user->isAnon() ) {
			$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
			$authUser = $userRepo->findByUser( $user );
			if ( $authUser ) {
				$module = $authUser->getModule();
				if ( $module instanceof IModule ) {
					$data = [];
					if ( isset( $params['totp'] ) ) {
						// Legacy
						if ( $module instanceof TOTP ) {
							$data = [
								'token' => $params['totp']
							];
						}
					} else {
						$decoded = FormatJson::decode( $params['data'], true );
						if ( is_array( $decoded ) ) {
							$data = $decoded;
						}
					}
					$result['enabled'] = $module->isEnabled( $authUser );
					$result['valid'] = $module->verify( $authUser, $data ) !== false;
					$result['module'] = $module->getName();
				}
			}
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function isInternal() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'user' => [
				ApiBase::PARAM_TYPE => 'user',
			],
			'totp' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DEPRECATED => true
			],
			'data' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	/**
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=oathvalidate&totp=123456&token=123ABC'
				=> 'apihelp-oathvalidate-example-1',
			'action=oathvalidate&user=Example&totp=123456&token=123ABC'
				=> 'apihelp-oathvalidate-example-2',
			'action=oathvalidate&user=Example&data={"totp":"123456"}&token=123ABC'
				=> 'apihelp-oathvalidate-example-3',
		];
	}
}
