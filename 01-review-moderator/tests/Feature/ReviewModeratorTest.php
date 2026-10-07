<?php

declare(strict_types=1);

use MarcReichel\Laya\Laya;
use MarcReichel\Laya\Model;
use ReviewModerator\Decision;
use ReviewModerator\Review;
use ReviewModerator\ReviewModerator;
use ReviewModerator\ReviewQuestions;

it('sends the review text and the three moderation questions to laya', function () {
    $laya = Laya::fake(['spam' => false, 'abuse' => false, 'sentiment' => 'positive']);

    new ReviewModerator($laya)->moderate(new Review('1', 'Great grinder, works perfectly.'));

    $laya->assertPredictedCount(1);
    $laya->assertPredicted(fn (mixed $state, array $questions) => $state === 'Great grinder, works perfectly.'
        && array_keys($questions) === ReviewQuestions::IDS);
});

it('publishes a clean review', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);

    $result = new ReviewModerator($laya)->moderate(new Review('1', 'Great grinder, works perfectly.'));

    expect($result->decision())->toBe(Decision::Publish)
        ->and($result->sentimentLabel())->toBe('positive')
        ->and($result->predictedLabels())->toBe(['spam' => 'no', 'abuse' => 'no', 'sentiment' => 'positive']);
});

it('rejects confident spam', function () {
    $laya = Laya::fake(['spam' => 0.97, 'abuse' => 0.02, 'sentiment' => 'neutral']);

    $result = new ReviewModerator($laya)->moderate(new Review('1', 'Promo slot gacor hari ini!'));

    expect($result->decision())->toBe(Decision::Reject)
        ->and($result->verdict->reasons)->toBe(['spam (P=0.97)']);
});

it('asks a person when laya is unsure', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => null]);

    $result = new ReviewModerator($laya)->moderate(new Review('1', 'The product works, but delivery took too long.'));

    expect($result->decision())->toBe(Decision::ManualReview)
        ->and($result->verdict->reasons)->toBe(['unsure about sentiment (confidence 0.00)']);
});

it('moderates many reviews in input order', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'neutral']);
    $reviews = [new Review('b', 'second'), new Review('a', 'first'), new Review('c', 'third')];

    $results = new ReviewModerator($laya)->moderateMany($reviews);

    expect(array_map(fn ($result) => $result->review->id, $results))->toBe(['b', 'a', 'c']);
    $laya->assertPredictedCount(3);
    $laya->assertPredicted(fn (mixed $state) => $state === 'third');
});

it('pins the checkpoint when a model is given', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);

    new ReviewModerator($laya, model: Model::Multilingual)->moderate(new Review('1', 'Barangnya bagus banget.'));

    $laya->assertPredicted(fn (mixed $state, array $questions, ?string $model) => $model === 'multilingual');
});

it('lets the router pick the checkpoint by default', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);

    new ReviewModerator($laya)->moderate(new Review('1', 'Barangnya bagus banget.'));

    $laya->assertPredicted(fn (mixed $state, array $questions, ?string $model) => $model === null);
});

it('serialises a result for the JSON report', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);

    $result = new ReviewModerator($laya)->moderate(new Review('r1', 'Great.', 'en', ['spam' => 'no']));

    expect($result->toArray())
        ->toMatchArray(['id' => 'r1', 'lang' => 'en', 'decision' => 'publish', 'reasons' => []])
        ->and($result->toArray()['answers'])->toHaveKeys(['spam', 'abuse', 'sentiment'])
        ->and($result->toArray()['gold'])->toEqual((object) ['spam' => 'no']);
});
