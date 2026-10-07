<?php

declare(strict_types=1);

use ReviewModerator\Decision;
use ReviewModerator\RoutingPolicy;

it('publishes a clean review laya is sure about', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.05), yesNo(0.02), sentiment('positive'));

    expect($verdict->decision)->toBe(Decision::Publish)
        ->and($verdict->reasons)->toBe([]);
});

it('rejects confident spam', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.97), yesNo(0.02), sentiment('positive'));

    expect($verdict->decision)->toBe(Decision::Reject)
        ->and($verdict->reasons)->toBe(['spam (P=0.97)']);
});

it('rejects confident abuse', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.1), yesNo(0.95), sentiment('negative'));

    expect($verdict->decision)->toBe(Decision::Reject)
        ->and($verdict->reasons)->toBe(['abuse (P=0.95)']);
});

it('sends a borderline flag to a person instead of rejecting it', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.6, answerConfidence: 0.8), yesNo(0.02), sentiment('neutral'));

    expect($verdict->decision)->toBe(Decision::ManualReview)
        ->and($verdict->reasons)->toBe(['possible spam (P=0.60)']);
});

it('does not reject on a high probability that laya itself does not trust', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.95, answerConfidence: 0.55), yesNo(0.02), sentiment('neutral'));

    expect($verdict->decision)->toBe(Decision::ManualReview)
        ->and($verdict->reasons)->toBe(['possible spam (P=0.95)', 'unsure about spam (confidence 0.55)']);
});

it('sends a review to a person when any answer has low confidence', function () {
    $verdict = new RoutingPolicy()->decide(yesNo(0.05), yesNo(0.02), sentiment('negative', 0.62));

    expect($verdict->decision)->toBe(Decision::ManualReview)
        ->and($verdict->reasons)->toBe(['unsure about sentiment (confidence 0.62)']);
});

it('follows the configured thresholds', function () {
    $lenient = new RoutingPolicy(minConfidence: 0.5, flagThreshold: 0.7, rejectThreshold: 0.8);

    expect($lenient->decide(yesNo(0.05), yesNo(0.02), sentiment('negative', 0.62))->decision)->toBe(Decision::Publish)
        ->and($lenient->decide(yesNo(0.6), yesNo(0.02), sentiment('neutral'))->decision)->toBe(Decision::Publish)
        ->and($lenient->decide(yesNo(0.85), yesNo(0.02), sentiment('neutral'))->decision)->toBe(Decision::Reject);
});

it('rejects thresholds outside 0..1', function (float $min, float $flag, float $reject) {
    new RoutingPolicy($min, $flag, $reject);
})->throws(InvalidArgumentException::class)->with([
    'negative min confidence' => [-0.1, 0.5, 0.9],
    'flag above one' => [0.7, 1.5, 0.9],
    'reject above one' => [0.7, 0.5, 1.01],
]);

it('rejects a flag threshold above the reject threshold', function () {
    new RoutingPolicy(flagThreshold: 0.95, rejectThreshold: 0.9);
})->throws(InvalidArgumentException::class, 'flagThreshold must not be higher than rejectThreshold.');
