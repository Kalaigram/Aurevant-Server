<?php

declare(strict_types=1);

namespace App\Domain\Stock\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException {}
