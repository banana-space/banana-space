<?php

use ast\Node;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\UnionType;

/**
 * Class for visiting any nodes we want to handle in pre-order.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @suppress PhanUnreferencedClass https://github.com/phan/phan/issues/2945
 */
class MWPreVisitor extends PreTaintednessVisitor {
	/**
	 * Re-declared for better type inference
	 * @suppress PhanReadOnlyProtectedProperty
	 * @var MediaWikiSecurityCheckPlugin
	 */
	protected $plugin;

	/**
	 * Set taint for certain hook types.
	 *
	 * Also handles FuncDecl
	 * @param Node $node
	 */
	public function visitMethod( Node $node ) {
		parent::visitMethod( $node );

		$method = $this->context->getFunctionLikeInScope( $this->code_base );
		$hookType = $this->plugin->isSpecialHookSubscriber( $method->getFQSEN() );
		if ( !$hookType ) {
			return;
		}
		$params = $node->children['params']->children;

		switch ( $hookType ) {
		case '!ParserFunctionHook':
			$this->setFuncHookParamTaint( $params, $method );
			break;
		case '!ParserHook':
			$this->setTagHookParamTaint( $params, $method );
			break;
		}
	}

	/**
	 * Set taint for a tag hook.
	 *
	 * The parameters are:
	 *  string contents (Tainted from wikitext)
	 *  array attribs (Tainted from wikitext)
	 *  Parser object
	 *  PPFrame object
	 *
	 * @param array $params formal parameters of tag hook
	 * @param FunctionInterface $method @phan-unused-param (only used for debugging)
	 */
	private function setTagHookParamTaint( array $params, FunctionInterface $method ) {
		// Only care about first 2 parameters.
		$scope = $this->context->getScope();
		for ( $i = 0; $i < 2 && $i < count( $params ); $i++ ) {
			$param = $params[$i];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );
			$this->setTaintedness( $varObj, SecurityCheckPlugin::YES_TAINT );
			// $this->debug( __METHOD__, "In $method setting param $varObj as tainted" );
		}
		// If there are no type hints, phan won't know that the parser
		// is a parser as the hook isn't triggered from a real func call.
		if ( isset( $params[2] ) ) {
			$param = $params[2];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
			} else {
				$varObj = $scope->getVariableByName( $param->children['name'] );
				$varObj->setUnionType(
					UnionType::fromFullyQualifiedString( '\\Parser' )
				);
			}
		}
		if ( isset( $params[3] ) ) {
			$param = $params[3];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
			} else {
				$varObj = $scope->getVariableByName( $param->children['name'] );
				$varObj->setUnionType(
					UnionType::fromFullyQualifiedString( '\\PPFrame' )
				);
			}
		}
	}

	/**
	 * Set the appropriate taint for a parser function hook
	 *
	 * Basically all but the first arg comes from wikitext
	 * and is tainted.
	 *
	 * @todo This is handling SFH_OBJECT type func hooks incorrectly.
	 * @param Node[] $params Children of the AST_PARAM_LIST
	 * @param FunctionInterface $method @phan-unused-param (only used for debugging)
	 */
	private function setFuncHookParamTaint( array $params, FunctionInterface $method ) {
		// First make sure the first arg is set to be a Parser
		$scope = $this->context->getScope();
		if ( isset( $params[0] ) ) {
			$param = $params[0];
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
			} else {
				$varObj = $scope->getVariableByName( $param->children['name'] );
				$varObj->setUnionType(
					UnionType::fromFullyQualifiedString( '\\Parser' )
				);
			}
		}

		foreach ( $params as $i => $param ) {
			if ( $i === 0 ) {
				continue;
			}
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );
			$this->setTaintedness( $varObj, SecurityCheckPlugin::YES_TAINT );
			/* Is this needed ? Disabling for now.
			$funcTaint = $this->getTaintOfFunction( $method );
			if ( isset( $funcTaint[$i] ) ) {
				if ( !$this->isSafeAssignment(
					$funcTaint[$i],
					SecurityCheckPlugin::YES_TAINT
				) ) {
					$funcName = $method->getFQSEN();
					$this->plugin->emitIssue(
						$this->code_base,
						$this->context,
						'SecurityCheckTaintedOutput',
						"Outputting evil HTML from Parser function hook $funcName (case 2)"
							. $this->getOriginalTaintLine( $method )
					);
				}
			}
			*/
		}
	}
}
