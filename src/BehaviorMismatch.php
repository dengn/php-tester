<?php

declare(strict_types=1);

namespace MoTest;

/**
 * Throw when MatrixOne ran the statement without error but produced a result
 * that differs from MySQL semantics (a silent behaviour incompatibility).
 */
class BehaviorMismatch extends \RuntimeException
{
}
