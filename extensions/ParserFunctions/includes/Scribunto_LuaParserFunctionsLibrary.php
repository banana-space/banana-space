<?php

class Scribunto_LuaParserFunctionsLibrary extends Scribunto_LuaLibraryBase {
	public function register() {
		$lib = [
			'expr' => [ $this, 'expr' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.ParserFunctions.lua', $lib, []
		);
	}

	public function expr( $expression = null ) {
		$this->checkType( 'mw.ext.ParserFunctions.expr', 1, $expression, 'string' );
		try {
			return [ ExtParserFunctions::getExprParser()->doExpression( $expression ) ];
		} catch ( ExprError $e ) {
			throw new Scribunto_LuaError( $e->getMessage() );
		}
	}

}
