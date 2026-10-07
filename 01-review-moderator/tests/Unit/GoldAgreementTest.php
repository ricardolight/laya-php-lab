<?php

declare(strict_types=1);

use ReviewModerator\Decision;
use ReviewModerator\GoldAgreement;
use ReviewModerator\ModerationResult;
use ReviewModerator\Review;
use ReviewModerator\Verdict;

function moderated(Review $review, float $spam, float $abuse, string $sentiment): ModerationResult
{
    return new ModerationResult($review, yesNo($spam), yesNo($abuse), sentiment($sentiment), new Verdict(Decision::Publish));
}

it('counts matches per question, skipping reviews without that gold label', function () {
    $agreement = GoldAgreement::of([
        moderated(new Review('1', 'a', gold: ['spam' => 'no', 'abuse' => 'no', 'sentiment' => 'positive']), 0.1, 0.1, 'positive'),
        moderated(new Review('2', 'b', gold: ['spam' => 'YES', 'abuse' => 'no']), 0.9, 0.7, 'neutral'),
        moderated(new Review('3', 'c', gold: ['spam' => 'no', 'abuse' => 'no', 'sentiment' => 'negative']), 0.2, 0.1, 'neutral'),
    ]);

    expect($agreement)->toBe([
        'spam' => ['correct' => 3, 'total' => 3, 'accuracy' => 1.0],
        'abuse' => ['correct' => 2, 'total' => 3, 'accuracy' => 2 / 3],
        'sentiment' => ['correct' => 1, 'total' => 2, 'accuracy' => 0.5],
    ]);
});

it('is empty without gold labels', function () {
    expect(GoldAgreement::of([moderated(new Review('1', 'a'), 0.1, 0.1, 'positive')]))->toBe([]);
});
