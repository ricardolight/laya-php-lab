<?php

declare(strict_types=1);

namespace ReviewModerator;

final readonly class Verdict
{
    /** @param list<string> $reasons */
    public function __construct(
        public Decision $decision,
        public array $reasons = [],
    ) {}
}
