<?php

declare(strict_types=1);

namespace AnvilDb\Exception;

/**
 * Exception thrown when the process-based driver encounters an error.
 *
 * Examples: binary not found, pipe communication failure, process crashed.
 */
class ProcessException extends AnvilDbException
{
}
