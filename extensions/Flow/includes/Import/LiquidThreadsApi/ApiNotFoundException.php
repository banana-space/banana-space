<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\ImportException;

/**
 * Thrown when the liquidthreads api reports that a
 * requested page or revision does not exist.
 */
class ApiNotFoundException extends ImportException {
}
