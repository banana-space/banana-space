<?php declare( strict_types=1 );
/**
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

use ast\Node;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type\ClosureType;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;

/**
 * This class visits all the nodes in the ast. It has two jobs:
 *
 * 1) Return the taint value of the current node we are visiting.
 * 2) In the event of an assignment (and similar things) propogate
 *  the taint value from the left hand side to the right hand side.
 *
 * For the moment, the taint values are stored in a "taintedness"
 * property of various phan TypedElement objects. This is probably
 * not the best solution for where to store the data, but its what
 * this does for now.
 *
 * This also maintains some other properties, such as where the error
 * originates, and dependencies in certain cases.
 *
 * @phan-file-suppress PhanUnusedPublicMethodParameter Many methods don't use $node
 */
class TaintednessVisitor extends PluginAwarePostAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * @inheritDoc
	 */
	public function __construct( CodeBase $code_base, Context $context ) {
		parent::__construct( $code_base, $context );
		$this->plugin = SecurityCheckPlugin::$pluginInstance;
	}

	/**
	 * Generic visitor when we haven't defined a more specific one.
	 *
	 * @param Node $node
	 * @return int The taintedness of the node.
	 * @suppress PhanParamSignatureMismatch
	 */
	public function visit( Node $node ) : int {
		// This method will be called on all nodes for which
		// there is no implementation of its kind visitor.

		// To see what kinds of nodes are passing through here,
		// you can run `Debug::printNode($node)`.
		# Debug::printNode( $node );
		$this->debug( __METHOD__, "unhandled case " . Debug::nodeName( $node ) );
		return SecurityCheckPlugin::UNKNOWN_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitClosure( Node $node ) : int {
		// We cannot use getFunctionLikeInScope for closures
		$closureFQSEN = FullyQualifiedFunctionName::fromClosureInContext( $this->context, $node );

		if ( $this->code_base->hasFunctionWithFQSEN( $closureFQSEN ) ) {
			$func = $this->code_base->getFunctionByFQSEN( $closureFQSEN );
			return $this->analyzeFunctionLike( $func );
		} else {
			$this->debug( __METHOD__, 'closure doesn\'t exist' );
			return SecurityCheckPlugin::INAPPLICABLE_TAINT;
		}
	}

	/**
	 * These are the vars passed to closures via use()
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitClosureVar( Node $node ) : int {
		$pobjs = $this->getPhanObjsForNode( $node );
		if ( !$pobjs ) {
			$this->debug( __METHOD__, 'No variable found' );
			return SecurityCheckPlugin::INAPPLICABLE_TAINT;
		}
		assert( count( $pobjs ) === 1 && $pobjs[0] instanceof Variable );
		return $this->getTaintednessPhanObj( $pobjs[0] );
	}

	/**
	 * The 'use' keyword for closures. The variables inside it are handled in visitClosureVar
	 *
	 * @param Node $node
	 * @return mixed
	 */
	public function visitClosureUses( Node $node ) {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitFuncDecl( Node $node ) : int {
		$func = $this->context->getFunctionLikeInScope( $this->code_base );
		return $this->analyzeFunctionLike( $func );
	}

	/**
	 * Visit a method decleration
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitMethod( Node $node ) : int {
		$method = $this->context->getFunctionLikeInScope( $this->code_base );
		return $this->analyzeFunctionLike( $method );
	}

	/**
	 * Handles methods, functions and closures.
	 *
	 * At this point we should have already hit a return statement
	 * so if we haven't yet, mark this function as no taint.
	 *
	 * @param FunctionInterface $func The func to analyze, or null to retrieve
	 *   it from the context.
	 * @return int Taint
	 */
	private function analyzeFunctionLike( FunctionInterface $func ) : int {
		// Phan will remove the variable map after analysis, so save it for later
		// use by GetReturnObjsVisitor. Ref phan issue #2963
		$func->scopeAfterAnalysis = $this->context->getScope();
		if (
			$this->getBuiltinFuncTaint( $func->getFQSEN() ) === null &&
			$this->getDocBlockTaintOfFunc( $func ) === null &&
			!$func->hasYield() &&
			!$func->hasReturn() &&
			!property_exists( $func, 'funcTaint' )
		) {
			// At this point, if func exec's stuff, funcTaint
			// should already be set.

			// So we have a func with no yield, return and no
			// dangerous side effects. Which seems odd, since
			// what's the point, but mark it as safe.

			// FIXME: In the event that the method stores its arg
			// to a class prop, and that class prop gets output later
			// somewhere else - the exec status of this won't be detected
			// until later, so setting this to NO_TAINT here might miss
			// some issues in the inbetween period.
			$this->setFuncTaint( $func, [ 'overall' => SecurityCheckPlugin::NO_TAINT ] );
		}
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	// No-ops we ignore.
	// separate methods so we can use visit to output debugging
	// for anything we miss.

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitStmtList( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitUseElem( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitType( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitArgList( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitParamList( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @note Params should be handled in PreTaintednessVisitor
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitParam( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitClass( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitClassConstDecl( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitConstDecl( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitIf( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitThrow( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * Actual property decleration is PropElem
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPropDecl( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitConstElem( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitUse( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitUseTrait( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitBreak( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitContinue( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitGoto( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitCatch( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitNamespace( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitSwitch( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitSwitchCase( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitWhile( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitDoWhile( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitFor( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitSwitchList( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * This is e.g. the list of expressions inside the for condition
	 *
	 * @param Node $node
	 * @return int
	 */
	public function visitExprList( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitUnset( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitTry( Node $node ) : int {
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int
	 */
	public function visitClone( Node $node ) : int {
		// @todo This should first check the __clone method, acknowledge its side effects
		// (probably via handleMethodCall), and *then* return the taintedness of the cloned
		// item. But finding the __clone definition might be hard...
		return $this->getTaintedness( $node->children['expr'] );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitAssignOp( Node $node ) : int {
		return $this->visitAssign( $node );
	}

	/**
	 * Also handles visitAssignOp
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitAssign( Node $node ) : int {
		// echo __METHOD__ . $this->dbgInfo() . ' ';
		// Debug::printNode($node);

		// Note: If there is a local variable that is a reference
		// to another non-local variable, this will probably incorrectly
		// override the taint (Pass by reference variables are handled
		// specially and should be ok).

		// Make sure $foo[2] = 0; doesn't kill taint of $foo generally.
		// Ditto for $this->bar, or props in general just in case.
		$override = $node->children['var']->kind !== \ast\AST_DIM
			&& $node->children['var']->kind !== \ast\AST_PROP;

		$variableObjs = $this->getPhanObjsForNode( $node->children['var'] );

		$lhsTaintedness = $this->getTaintedness( $node->children['var'] );
		# $this->debug( __METHOD__, "Getting taint LHS = $lhsTaintedness:" );
		$rhsTaintedness = $this->getTaintedness( $node->children['expr'] );
		# $this->debug( __METHOD__, "Getting taint RHS = $rhsTaintedness:" );

		if ( $node->kind === \ast\AST_ASSIGN_OP ) {
			// TODO, be more specific for different OPs
			// Expand rhs to include implicit lhs ophand.
			$rhsTaintedness = $this->mergeAddTaint( $rhsTaintedness, $lhsTaintedness );
			$override = false;
		}

		// Special case for SQL_NUMKEY_TAINT
		// If we're assigning an SQL tainted value as an array key
		// or as the value of a numeric key, then set NUMKEY taint.
		$var = $node->children['var'];
		if ( $var->kind === \ast\AST_DIM ) {
			$dim = $var->children['dim'];
			if ( $rhsTaintedness & SecurityCheckPlugin::SQL_NUMKEY_TAINT ) {
				// Things like 'foo' => ['taint', 'taint']
				// are ok.
				$rhsTaintedness &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			} elseif ( $rhsTaintedness & SecurityCheckPlugin::SQL_TAINT ) {
				// Checking the case:
				// $foo[1] = $sqlTainted;
				// $foo[] = $sqlTainted;
				// But ensuring we don't catch:
				// $foo['bar'][] = $sqlTainted;
				// $foo[] = [ $sqlTainted ];
				// $foo[2] = [ $sqlTainted ];
				if (
					( $dim === null ||
					$this->nodeIsInt( $dim ) )
					&& !$this->nodeIsArray( $node->children['expr'] )
					&& !( $var->children['expr'] instanceof Node
						&& $var->children['expr']->kind === \ast\AST_DIM
					)
				) {
					$rhsTaintedness |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
				}
			}
			if ( $this->getTaintedness( $dim ) & SecurityCheckPlugin::SQL_TAINT ) {
				$rhsTaintedness |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;

			}
		}

		// If we're assigning to a variable we know will be output later
		// raise an issue now.
		// We only want to give a warning if we are adding new taint to the
		// variable. If the variable is alredy tainted, no need to retaint.
		// Otherwise, this could result in a variable basically tainting itself.
		// TODO: Additionally, we maybe consider skipping this when in
		// branch scope and variable is not pass by reference.
		// @fixme Is this really necessary? It doesn't seem helpful for local variables,
		// and it doesn't handle props or globals.
		$adjustedRHS = $rhsTaintedness & ~$lhsTaintedness;
		$this->maybeEmitIssue(
			$lhsTaintedness,
			$adjustedRHS,
			"Assigning a tainted value to a variable that later does something unsafe with it"
				. $this->getOriginalTaintLine( $node->children['var'] )
		);

		$rhsObjs = [];
		if ( is_object( $node->children['expr'] ) ) {
			$rhsObjs = $this->getPhanObjsForNode( $node->children['expr'] );
		}

		foreach ( $variableObjs as $variableObj ) {
			// echo $this->dbgInfo() . " " . $variableObj .
			// " now merging in taintedness " . $rhsTaintedness
			// . " (previously $lhsTaintedness)\n";
			$isGlobal = property_exists( $variableObj, 'taintednessHasOuterScope' );
			if (
				$override &&
				!( $variableObj instanceof Property ) &&
				!$isGlobal &&
				!in_array( $variableObj, $rhsObjs, true )
			) {
				// Clear any error before setting taintedness if we're overriding taint.
				// Don't do that for globals and props, as we don't handle them really well yet.
				// Also don't do that if one of the objects in the RHS is the same as this object
				// in the LHS. This is especially important in conditionals e.g.
				// tainted = tainted ?: null.
				$this->clearTaintError( $variableObj );
				// Ditto for links. Beyond this point the object is free of links.
				$this->clearTaintLinks( $variableObj );
			}
			$this->setTaintedness( $variableObj, $rhsTaintedness, $override );

			if ( $isGlobal ) {
				$globalVar = $this->context->getScope()->getGlobalVariableByName( $variableObj->getName() );
				$this->setTaintedness( $globalVar, $rhsTaintedness, false );
			}

			foreach ( $rhsObjs as $rhsObj ) {
				// Only merge dependencies if there are no other
				// sources of taint. Otherwise we can potentially
				// misattribute where the taint is coming from
				// See testcase dblescapefieldset.
				$taintRHSObj = $this->getTaintednessPhanObj( $rhsObj );
				if (
					( ( ( $lhsTaintedness | $rhsTaintedness )
					& ~$taintRHSObj ) & SecurityCheckPlugin::ALL_YES_EXEC_TAINT )
					=== 0
				) {
					$this->mergeTaintDependencies( $variableObj, $rhsObj );
				} elseif ( $taintRHSObj ) {
					$this->mergeTaintError( $variableObj, $rhsObj );
				}
			}
		}
		return $rhsTaintedness;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitBinaryOp( Node $node ) : int {
		$safeBinOps = [
			// Unsure about BITWISE ops, since
			// "A" | "B" still is a string
			// so skipping.
			\ast\flags\BINARY_BOOL_XOR,
			\ast\flags\BINARY_DIV,
			\ast\flags\BINARY_IS_EQUAL,
			\ast\flags\BINARY_IS_IDENTICAL,
			\ast\flags\BINARY_IS_NOT_EQUAL,
			\ast\flags\BINARY_IS_NOT_IDENTICAL,
			\ast\flags\BINARY_IS_SMALLER,
			\ast\flags\BINARY_IS_SMALLER_OR_EQUAL,
			\ast\flags\BINARY_MOD,
			\ast\flags\BINARY_MUL,
			\ast\flags\BINARY_POW,
			// BINARY_ADD handled below due to array addition.
			\ast\flags\BINARY_SUB,
			\ast\flags\BINARY_BOOL_AND,
			\ast\flags\BINARY_BOOL_OR,
			\ast\flags\BINARY_IS_GREATER,
			\ast\flags\BINARY_IS_GREATER_OR_EQUAL
		];

		if ( in_array( $node->flags, $safeBinOps ) ) {
			return SecurityCheckPlugin::NO_TAINT;
		} elseif (
			$node->flags === \ast\flags\BINARY_ADD && (
				$this->nodeIsInt( $node->children['left'] ) ||
				$this->nodeIsInt( $node->children['right'] )
			)
		) {
			// This is used to avoid removing taintedness from array addition, and addition
			// of unknown types. If at least one node is integer, either the result will be an
			// integer, or PHP will throw a fatal.
			return SecurityCheckPlugin::NO_TAINT;
		}

		// Otherwise combine the ophand taint.
		$leftTaint = $this->getTaintedness( $node->children['left'] );
		$rightTaint = $this->getTaintedness( $node->children['right'] );
		$res = $this->mergeAddTaint( $leftTaint, $rightTaint );
		return $res;
	}

	/**
	 * @todo We need more fine grained handling of arrays.
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitDim( Node $node ) : int {
		return $this->getTaintednessNode( $node->children['expr'] );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPrint( Node $node ) : int {
		return $this->visitEcho( $node );
	}

	/**
	 * This is for exit() and die(). If they're passed an argument, they behave the
	 * same as print.
	 * @param Node $node
	 * @return int
	 */
	public function visitExit( Node $node ) : int {
		return $this->visitEcho( $node );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitShellExec( Node $node ) : int {
		$taintedness = $this->getTaintedness( $node->children['expr'] );

		$this->maybeEmitIssue(
			SecurityCheckPlugin::SHELL_EXEC_TAINT,
			$taintedness,
			"Backtick shell execution operator contains user controlled arg"
				. $this->getOriginalTaintLine( $node->children['expr'] )
		);

		if (
			$this->isSafeAssignment( SecurityCheckPlugin::SHELL_EXEC_TAINT, $taintedness ) &&
			is_object( $node->children['expr'] )
		) {
			// In the event the assignment looks safe, keep track of it,
			// in case it later turns out not to be safe.
			$phanObjs = $this->getPhanObjsForNode( $node->children['expr'], [ 'return' ] );
			foreach ( $phanObjs as $phanObj ) {
				$this->debug( __METHOD__, "Setting {$phanObj->getName()} exec due to backtick" );
				$this->markAllDependentMethodsExec(
					$phanObj,
					SecurityCheckPlugin::SHELL_EXEC_TAINT
				);
			}
		}
		// Its unclear if we should consider this tainted or not
		return SecurityCheckPlugin::YES_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitIncludeOrEval( Node $node ) : int {
		$taintedness = $this->getTaintedness( $node->children['expr'] );

		$this->maybeEmitIssue(
			SecurityCheckPlugin::MISC_EXEC_TAINT,
			$taintedness,
			"Argument to require, include or eval is user controlled"
				. $this->getOriginalTaintLine( $node->children['expr'] )
		);

		if (
			$this->isSafeAssignment( SecurityCheckPlugin::MISC_EXEC_TAINT, $taintedness ) &&
			is_object( $node->children['expr'] )
		) {
			// In the event the assignment looks safe, keep track of it,
			// in case it later turns out not to be safe.
			$phanObjs = $this->getPhanObjsForNode( $node->children['expr'], [ 'return' ] );
			foreach ( $phanObjs as $phanObj ) {
				$this->debug( __METHOD__, "Setting {$phanObj->getName()} exec due to require/eval" );
				$this->markAllDependentMethodsExec(
					$phanObj,
					SecurityCheckPlugin::MISC_EXEC_TAINT
				);
			}
		}
		// Strictly speaking we have no idea if the result
		// of an eval() or require() is safe. But given that we
		// don't know, and at least in the require() case its
		// fairly likely to be safe, no point in complaining.
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Also handles exit(), print, eval() and include() (for now).
	 *
	 * We assume a web based system, where outputting HTML via echo
	 * is bad. This will have false positives in a CLI environment.
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitEcho( Node $node ) : int {
		$echoTaint = SecurityCheckPlugin::HTML_EXEC_TAINT;
		$echoedExpr = $node->children['expr'];
		$taintedness = $this->getTaintedness( $echoedExpr );
		# $this->debug( __METHOD__, "Echoing with taint $taintedness" );

		$this->maybeEmitIssue(
			$echoTaint,
			$taintedness,
			"Echoing expression that was not html escaped"
				. $this->getOriginalTaintLine( $echoedExpr )
		);

		if ( $this->isSafeAssignment( $echoTaint, $taintedness ) && is_object( $echoedExpr ) ) {
			// In the event the assignment looks safe, keep track of it,
			// in case it later turns out not to be safe.
			$phanObjs = $this->getPhanObjsForNode( $echoedExpr, [ 'return' ] );
			foreach ( $phanObjs as $phanObj ) {
				$this->debug( __METHOD__, "Setting {$phanObj->getName()} exec due to echo" );
				// FIXME, maybe not do this for local variables
				// since they don't have other code paths that can set them.
				$this->markAllDependentMethodsExec(
					$phanObj,
					$echoTaint
				);
			}
		}
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitStaticCall( Node $node ) : int {
		return $this->visitMethodCall( $node );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitNew( Node $node ) : int {
		if ( $node->children['class']->kind === \ast\AST_NAME ) {
			return $this->visitMethodCall( $node );
		} else {
			$this->debug( __METHOD__, "cannot understand new" );
			return SecurityCheckPlugin::UNKNOWN_TAINT;
		}
	}

	/**
	 * Somebody calls a method or function
	 *
	 * This has to figure out:
	 *  Is the return value of the call tainted
	 *  Are any of the arguments tainted
	 *  Does the function do anything scary with its arguments
	 * It also has to maintain quite a bit of book-keeping.
	 *
	 * This also handles (function) call, static call, and new operator
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitMethodCall( Node $node ) : int {
		$ctxNode = $this->getCtxN( $node );
		$isStatic = ( $node->kind === \ast\AST_STATIC_CALL );
		$isFunc = ( $node->kind === \ast\AST_CALL );

		// First we need to get the taintedness of the method
		// in question.
		try {
			if ( $node->kind === \ast\AST_NEW ) {
				// We check the __construct() method first, but the
				// final resulting taint is from the __toString()
				// method. This is a little hacky.
				$constructor = $ctxNode->getMethod(
					'__construct',
					false,
					false,
					true
				);
				// First do __construct()
				$this->handleMethodCall(
					$constructor,
					$constructor->getFQSEN(),
					$this->getTaintOfFunction( $constructor ),
					$node->children['args']->children
				);
				// Now return __toString()
				$clazz = $constructor->getClass( $this->code_base );
				try {
					$toString = $clazz->getMethodByName( $this->code_base, '__toString' );
				} catch ( CodeBaseException $_ ) {
					// There is no __toString(), then presumably the object can't be outputed, so should be safe.
					$this->debug( __METHOD__, "no __toString() in $clazz" );
					return SecurityCheckPlugin::NO_TAINT;
				}

				return $this->handleMethodCall(
					$toString,
					$toString->getFQSEN(),
					$this->getTaintOfFunction( $toString ),
					[] // __toString() has no args
				);
			} elseif ( $isFunc ) {
				if ( $node->children['expr']->kind === \ast\AST_NAME ) {
					$func = $ctxNode->getFunction( $node->children['expr']->children['name'] );
				} elseif ( $node->children['expr']->kind === \ast\AST_VAR ) {
					// Closure
					$pobjs = $this->getPhanObjsForNode( $node->children['expr'] );
					assert( count( $pobjs ) === 1 );
					$types = $pobjs[0]->getUnionType()->getTypeSet();
					$func = null;
					foreach ( $types as $type ) {
						if ( $type instanceof ClosureType ) {
							$func = $type->asFunctionInterfaceOrNull( $this->code_base, $this->context );
						}
					}
					if ( $func === null ) {
						throw new Exception( 'Cannot get closure from variable.' );
					}
				} else {
					throw new Exception( "Non-simple func call" );
				}
			} else {
				$methodName = $node->children['method'];
				$func = $ctxNode->getMethod( $methodName, $isStatic );
			}
			$funcName = $func->getFQSEN();
			$taint = $this->getTaintOfFunction( $func );
		} catch ( Exception $e ) {
			$this->debug( __METHOD__, "FIXME complicated case not handled."
				. " Maybe func not defined. " . $this->getDebugInfo( $e ) );
			$func = null;
			$funcName = '[UNKNOWN FUNC]';
			return SecurityCheckPlugin::UNKNOWN_TAINT;
		}

		return $this->handleMethodCall( $func, $funcName, $taint, $node->children['args']->children );
	}

	/**
	 * A function call
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitCall( Node $node ) : int {
		return $this->visitMethodCall( $node );
	}

	/**
	 * A variable (e.g. $foo)
	 *
	 * This always considers superglobals as tainted
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitVar( Node $node ) : int {
		$varName = $this->getCtxN( $node )->getVariableName();
		if ( $varName === '' ) {
			$this->debug( __METHOD__, "FIXME: Complex variable case not handled." );
			// Debug::printNode( $node );
			return SecurityCheckPlugin::UNKNOWN_TAINT;
		}
		if ( $this->isSuperGlobal( $varName ) ) {
			// Superglobals are tainted, regardless of whether they're in the current scope:
			// `function foo() use ($argv)` puts $argv in the local scope, but it retains its
			// taintedness (see test closure2).
			// echo "$varName is superglobal. Marking tainted\n";
			return SecurityCheckPlugin::YES_TAINT;
		} elseif ( !$this->context->getScope()->hasVariableWithName( $varName ) ) {
			// Probably the var just isn't in scope yet.
			// $this->debug( __METHOD__, "No var with name \$$varName in scope (Setting Unknown taint)" );
			return SecurityCheckPlugin::UNKNOWN_TAINT;
		}
		$variableObj = $this->context->getScope()->getVariableByName( $varName );
		if ( $variableObj instanceof PassByReferenceVariable ) {
			$variableObj = $variableObj->getElement();
		}
		return $this->getTaintednessPhanObj( $variableObj );
	}

	/**
	 * A global declaration. Assume most globals are untainted.
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitGlobal( Node $node ) : int {
		assert( isset( $node->children['var'] ) && $node->children['var']->kind === \ast\AST_VAR );
		$varName = $node->children['var']->children['name'];
		if ( !is_string( $varName ) ) {
			// Something like global $$indirectReference;
			return SecurityCheckPlugin::INAPPLICABLE_TAINT;
		}
		$scope = $this->context->getScope();
		if ( $scope->hasGlobalVariableWithName( $varName ) ) {
			$globalVar = $scope->getGlobalVariableByName( $varName );
			$localVar = clone $globalVar;
			$localVar->taintednessHasOuterScope = true;
			$scope->addVariable( $localVar );
		}
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * Set the taint of the function based on what's returned
	 *
	 * This attempts to match the return value up to the argument
	 * to figure out which argument might taint the function. This won't
	 * work in complex cases though.
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitReturn( Node $node ) : int {
		if ( !$this->context->isInFunctionLikeScope() ) {
			$this->debug( __METHOD__, "return outside func?" );
			// Debug::printNode( $node );
			return SecurityCheckPlugin::UNKNOWN_TAINT;
		}

		$curFunc = $this->context->getFunctionLikeInScope( $this->code_base );
		// The EXEC taint flags have different meaning for variables and
		// functions. We don't want to transmit exec flags here.
		$taintedness = $this->getTaintedness( $node->children['expr'] ) &
			SecurityCheckPlugin::ALL_TAINT;

		$funcTaint = $this->matchTaintToParam(
			$node->children['expr'],
			$taintedness,
			$curFunc
		);

		$this->checkFuncTaint( $funcTaint );
		$this->setFuncTaint( $curFunc, $funcTaint );

		if ( $funcTaint['overall'] & SecurityCheckPlugin::YES_EXEC_TAINT ) {
			$taintSource = '';
			$pobjs = $this->getPhanObjsForNode( $node->children['expr'] );
			foreach ( $pobjs as $pobj ) {
				$taintSource .= $pobj->taintedOriginalError ?? '';
			}
			if ( strlen( $taintSource ) < 200 ) {
				if ( !isset( $curFunc->taintedOriginalError ) ) {
					$curFunc->taintedOriginalError = '';
				}
				$curFunc->taintedOriginalError = substr(
					$curFunc->taintedOriginalError . $taintSource,
					0,
					250
				);
			}
		}
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitArray( Node $node ) : int {
		$curTaint = SecurityCheckPlugin::NO_TAINT;
		foreach ( $node->children as $child ) {
			if ( $child === null ) {
				// Happens for list( , $x ) = foo()
				continue;
			}
			assert( $child->kind === \ast\AST_ARRAY_ELEM );
			$childTaint = $this->getTaintedness( $child );
			$key = $child->children['key'];
			$value = $child->children['value'];
			$sqlTaint = SecurityCheckPlugin::SQL_TAINT;
			if (
				$this->getTaintedness( $value )
				& SecurityCheckPlugin::SQL_NUMKEY_TAINT
			) {
				$childTaint &= ~SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			if (
				( $this->getTaintedness( $key ) & $sqlTaint ) ||
				( ( $key === null || $this->nodeIsInt( $key ) )
				&& ( $this->getTaintedness( $value ) & $sqlTaint )
				&& $this->nodeIsString( $value ) )
			) {
				$childTaint |= SecurityCheckPlugin::SQL_NUMKEY_TAINT;
			}
			$curTaint = $this->mergeAddTaint( $curTaint, $childTaint );
		}
		return $curTaint;
	}

	/**
	 * A => B
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitArrayElem( Node $node ) : int {
		return $this->mergeAddTaint(
			$this->getTaintedness( $node->children['value'] ),
			$this->getTaintedness( $node->children['key'] )
		);
	}

	/**
	 * A foreach() loop
	 *
	 * The variable from the loop condition has its taintedness
	 * transferred in PreTaintednessVisitor
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitForeach( Node $node ) : int {
		// This is handled by PreTaintednessVisitor.
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitClassConst( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitConst( Node $node ) : int {
		// We are going to assume nobody is doing stupid stuff like
		// define( "foo", $_GET['bar'] );
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * The :: operator (for props)
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitStaticProp( Node $node ) : int {
		$props = $this->getPhanObjsForNode( $node );
		if ( count( $props ) > 1 ) {
			// This is unexpected.
			$this->debug( __METHOD__, "static prop has many objects" );
		}
		$taint = 0;
		foreach ( $props as $prop ) {
			$taint |= $this->getTaintednessPhanObj( $prop );
		}
		return $taint;
	}

	/**
	 * The -> operator (when not a method call)
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitProp( Node $node ) : int {
		$props = $this->getPhanObjsForNode( $node );
		if ( count( $props ) !== 1 ) {
			if (
				is_object( $node->children['expr'] ) &&
				$node->children['expr']->kind === \ast\AST_VAR &&
				$node->children['expr']->children['name'] === 'row'
			) {
				// Almost certainly a MW db result.
				// FIXME this case isn't fully handled.
				// Stuff from db probably not escaped. Most of the time.
				// Don't include serialize here due to high false positives
				// Eventhough unserializing stuff from db can be very
				// problematic if user can ever control.
				// FIXME This is MW specific so should not be
				// in the generic visitor.
				return SecurityCheckPlugin::YES_TAINT & ~SecurityCheckPlugin::SERIALIZE_TAINT;
			}
			if (
				is_object( $node->children['expr'] ) &&
				$node->children['expr']->kind === \ast\AST_VAR &&
				is_string( $node->children['expr']->children['name'] ) &&
				is_string( $node->children['prop'] )
			) {
				$this->debug( __METHOD__, "Could not find Property \$" .
					$node->children['expr']->children['name'] . "->" .
					$node->children['prop']
				);
			} else {
				// FIXME, we should handle $this->foo->bar
				$this->debug( __METHOD__, "Nested property reference " . count( $props ) . "" );
				# Debug::printNode( $node );
			}
			if ( count( $props ) === 0 ) {
				// Should this be NO_TAINT?
				return SecurityCheckPlugin::UNKNOWN_TAINT;
			}
		}
		$prop = $props[0];

		if ( $node->children['expr'] instanceof Node && $node->children['expr']->kind === \ast\AST_VAR ) {
			$variable = $this->getCtxN( $node->children['expr'] )->getVariable();
			if ( property_exists( $variable, 'taintedness' ) ) {
				// If the variable has taintedness set and its union type contains stdClass, it's
				// because this is the result of casting an array to object. Share the taintedness
				// of the variable with all its properties like we do for arrays.
				$types = array_map( 'strval', $variable->getUnionType()->getTypeSet() );
				if ( in_array( '\stdClass', $types ) ) {
					$prop->taintedness = $this->mergeAddTaint( $prop->taintedness ?? 0, $variable->taintedness );
					$this->mergeTaintError( $prop, $variable );
				}
			}
		}

		return $this->getTaintednessPhanObj( $prop );
	}

	/**
	 * When a class property is declared
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPropElem( Node $node ) : int {
		assert( $this->context->isInClassScope() );
		$clazz = $this->context->getClassInScope( $this->code_base );

		assert( $clazz->hasPropertyWithName( $this->code_base, $node->children['name'] ) );
		$prop = $clazz->getPropertyByName( $this->code_base, $node->children['name'] );
		// FIXME should this be NO?
		// $this->debug( __METHOD__, "Setting taint preserve if not set"
		// . " yet for \$" . $node->children['name'] . "" );
		$this->setTaintedness( $prop, SecurityCheckPlugin::NO_TAINT, false );
		return SecurityCheckPlugin::INAPPLICABLE_TAINT;
	}

	/**
	 * Ternary operator.
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitConditional( Node $node ) : int {
		if ( $node->children['true'] === null ) {
			// $foo ?: $bar;
			$t = $this->getTaintedness( $node->children['cond'] );
		} else {
			$t = $this->getTaintedness( $node->children['true'] );
		}
		$f = $this->getTaintedness( $node->children['false'] );
		return $this->mergeAddTaint( $t, $f );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitName( Node $node ) : int {
		// FIXME I'm a little unclear on what a name is in php.
		// I think this means literal true, false, null
		// or a class name (The Foo part of Foo::bar())
		// Maybe other things too? Are class references always
		// untainted? Probably.

		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * This is e.g. for class X implements Name,List
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitNameList( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * @todo Is this right? The child condition should
	 *  be visited when going in post order analysis anyways,
	 *  and the taint of an If statement isn't really its condition.
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitIfElem( Node $node ) : int {
		return $this->getTaintedness( $node->children['cond'] );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitUnaryOp( Node $node ) : int {
		// ~ and @ are the only two unary ops
		// that can preserve taint (others cast bool or int)
		$unsafe = [
			\ast\flags\UNARY_BITWISE_NOT,
			\ast\flags\UNARY_SILENCE
		];
		if ( in_array( $node->flags, $unsafe ) ) {
			return $this->getTaintedness( $node->children['expr'] );
		}
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPostInc( Node $node ) : int {
		return $this->analyzeIncOrDec( $node );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPreInc( Node $node ) : int {
		return $this->analyzeIncOrDec( $node );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPostDec( Node $node ) : int {
		return $this->analyzeIncOrDec( $node );
	}

	/**
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitPreDec( Node $node ) : int {
		return $this->analyzeIncOrDec( $node );
	}

	/**
	 * Handles all post/pre-increment/decrement operators. They have no effect on the
	 * taintedness of a variable.
	 *
	 * @param Node $node
	 * @return int
	 */
	private function analyzeIncOrDec( Node $node ) : int {
		$children = $this->getPhanObjsForNode( $node );
		if ( count( $children ) === 1 ) {
			return $this->getTaintednessPhanObj( reset( $children ) );
		} elseif ( isset( $node->children['var'] ) ) {
			// @fixme Stopgap to handle superglobals, which getPhanObjsForNode doesn't return
			return $this->visitVar( $node->children['var'] );
		} else {
			return SecurityCheckPlugin::NO_TAINT;
		}
	}

	/**
	 * @param Node $node
	 * @return int The taint
	 */
	public function visitCast( Node $node ) : int {
		// Casting between an array and object maintains
		// taint. Casting an object to a string calls __toString().
		// Future TODO: handle the string case properly.
		$dangerousCasts = [
			ast\flags\TYPE_STRING,
			ast\flags\TYPE_ARRAY,
			ast\flags\TYPE_OBJECT
		];

		if ( !in_array( $node->flags, $dangerousCasts ) ) {
			return SecurityCheckPlugin::NO_TAINT;
		}
		return $this->getTaintedness( $node->children['expr'] );
	}

	/**
	 * The taint is the taint of all the child elements
	 *
	 * @param Node $node
	 * @return int the taint
	 */
	public function visitEncapsList( Node $node ) : int {
		$taint = SecurityCheckPlugin::NO_TAINT;
		foreach ( $node->children as $child ) {
			$taint = $this->mergeAddTaint( $taint, $this->getTaintedness( $child ) );
		}
		return $taint;
	}

	/**
	 * Visit a node that is always safe
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitIsset( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Visits calls to empty(), which is always safe
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitEmpty( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Visit a node that is always safe
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitMagicConst( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Visit a node that is always safe
	 *
	 * @param Node $node
	 * @return int Taint
	 */
	public function visitInstanceOf( Node $node ) : int {
		return SecurityCheckPlugin::NO_TAINT;
	}
}
