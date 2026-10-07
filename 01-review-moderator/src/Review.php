<?php

declare(strict_types=1);

namespace ReviewModerator;

final readonly class Review
{
    /**
     * @param  array<string, string>  $gold  answers a human gave, keyed by question id (e.g. 'spam' => 'yes')
     */
    public function __construct(
        public string $id,
        public string $text,
        public ?string $lang = null,
        public array $gold = [],
    ) {}
}
