<?php

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\PluginV2\PluginAwarePreAnalysisVisitor;

/**
 * Class for visiting any nodes we want to handle in pre-order.
 *
 * Unlike TaintednessVisitor, this is solely used to set taint
 * on variable objects, and not to determine the taint of the
 * current node, so this class does not return anything.
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
 */
class PreTaintednessVisitor extends PluginAwarePreAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * @inheritDoc
	 */
	public function __construct( CodeBase $code_base, Context $context ) {
		parent::__construct( $code_base, $context );
		$this->plugin = SecurityCheckPlugin::$pluginInstance;
	}

	/**
	 * Visit a foreach loop
	 *
	 * This is done in pre-order so that we can handle
	 * the loop condition prior to determine the taint
	 * of the loop variable, prior to evaluating the
	 * loop body.
	 *
	 * @param Node $node
	 */
	public function visitForeach( Node $node ) {
		// TODO: Could we do something better here detecting the array
		// type
		$lhsTaintedness = $this->getTaintedness( $node->children['expr'] );

		$value = $node->children['value'];
		if ( $value->kind === \ast\AST_REF ) {
			// FIXME, this doesn't fully handle the ref case.
			// taint probably won't be propagated to outer scope.
			$value = $value->children['var'];
		}

		if ( $value->kind !== \ast\AST_VAR ) {
			$this->debug( __METHOD__, "FIXME foreach complex case not handled" );
			// Debug::printNode( $node );
			return;
		}

		try {
			$variableObj = $this->getCtxN( $value )->getVariable();
			$this->setTaintedness( $variableObj, $lhsTaintedness );

			if ( isset( $node->children['key'] ) ) {
				// This will probably have a lot of false positives with
				// arrays containing only numeric keys.
				assert( $node->children['key']->kind === \ast\AST_VAR );
				$variableObj = $this->getCtxN( $node->children['key'] )->getVariable();
				$this->setTaintedness( $variableObj, $lhsTaintedness );
			}
		} catch ( Exception $e ) {
			$this->debug( __METHOD__, "Exception " . $this->getDebugInfo( $e ) );
		}
	}

	/**
	 * @see visitMethod
	 * @param Node $node
	 * @return void Just has a return statement in case visitMethod changes
	 */
	public function visitFuncDecl( Node $node ) {
		return $this->visitMethod( $node );
	}

	/**
	 * @see visitMethod
	 * @param Node $node
	 * @return void Just has a return statement in case visitMethod changes
	 */
	public function visitClosure( Node $node ) {
		return $this->visitMethod( $node );
	}

	/**
	 * Set the taintedness of parameters to method/function.
	 *
	 * Parameters that are ints (etc) are clearly safe so
	 * this marks them as such. For other parameters, it
	 * creates a map between the function object and the
	 * parameter object so if anyone later calls the method
	 * with a dangerous argument we can determine if we need
	 * to output a warning.
	 *
	 * Also handles FuncDecl and Closure
	 * @param Node $node
	 */
	public function visitMethod( Node $node ) {
		// var_dump( __METHOD__ ); Debug::printNode( $node );
		$method = $this->context->getFunctionLikeInScope( $this->code_base );

		$params = $node->children['params']->children;
		foreach ( $params as $i => $param ) {
			$scope = $this->context->getScope();
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );

			if ( $varObj instanceof PassByReferenceVariable ) {
				// PassByReferenceVariable objects are too ephemeral to store taintedness there.
				$varObj = $varObj->getElement();
				if ( $varObj instanceof Property ) {
					// This may be a Property passed by ref. Don't reset its taintedness and don't link it
					continue;
				}
			}

			$paramTypeTaint = $this->getTaintByReturnType( $varObj->getUnionType() );
			if ( $paramTypeTaint === SecurityCheckPlugin::NO_TAINT ) {
				// The param is an integer or something, so skip.
				$this->setTaintedness( $varObj, $paramTypeTaint );
				continue;
			}

			// Initially, the variable starts off with no taint.
			$this->setTaintedness( $varObj, SecurityCheckPlugin::NO_TAINT );
			$this->linkParamAndFunc( $varObj, $method, $i );
		}
	}
}
