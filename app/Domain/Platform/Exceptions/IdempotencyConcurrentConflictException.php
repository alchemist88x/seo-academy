<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

class IdempotencyConcurrentConflictException extends RuntimeException {}
