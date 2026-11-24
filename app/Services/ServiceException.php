<?php

namespace App\Services;

use RuntimeException;

/**
 * A business-rule failure whose message is safe to show to the user
 * (e.g. "Insufficient wallet balance."). Anything else that escapes a
 * service is an unexpected error and is logged, not shown.
 */
class ServiceException extends RuntimeException
{
}
