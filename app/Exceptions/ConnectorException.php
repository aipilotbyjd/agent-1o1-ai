<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain-level connector/credential errors — an OAuth-only connector store
 * attempt, an invalid/expired OAuth state, a failed token exchange/refresh,
 * an expired or unresolvable credential at node-execution time. Mapped to a
 * 422 response in `bootstrap/app.php`.
 */
class ConnectorException extends RuntimeException {}
