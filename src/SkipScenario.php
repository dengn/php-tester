<?php

declare(strict_types=1);

namespace MoTest;

/** Throw to mark a scenario as not-applicable rather than failed. */
class SkipScenario extends \RuntimeException
{
}
