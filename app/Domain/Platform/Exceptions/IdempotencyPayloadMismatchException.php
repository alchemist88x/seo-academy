<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

class IdempotencyPayloadMismatchException extends RuntimeException {}
