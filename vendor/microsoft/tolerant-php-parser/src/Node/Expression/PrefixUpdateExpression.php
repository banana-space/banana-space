<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Token;

class PrefixUpdateExpression extends UnaryExpression {

    /** @var Token */
    public $incrementOrDecrementOperator;

    /** @var Variable */
    public $operand;

    const CHILD_NAMES = [
        'incrementOrDecrementOperator',
        'operand'
    ];
}
