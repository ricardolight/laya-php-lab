<?php

declare(strict_types=1);

namespace ReviewModerator;

enum Decision: string
{
    case Publish = 'publish';
    case ManualReview = 'manual-review';
    case Reject = 'reject';
}
