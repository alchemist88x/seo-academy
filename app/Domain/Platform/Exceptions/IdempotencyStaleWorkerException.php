<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

class IdempotencyStaleWorkerException extends RuntimeException {}
