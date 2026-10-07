<?php

declare(strict_types=1);

use ReviewModerator\ReviewCsv;

it('reads reviews with language and gold labels', function () {
    $reviews = ReviewCsv::read(fixture('reviews.csv'));

    expect($reviews)->toHaveCount(3)
        ->and($reviews[0]->id)->toBe('a1')
        ->and($reviews[0]->lang)->toBe('en')
        ->and($reviews[0]->text)->toBe('Great grinder, works perfectly.')
        ->and($reviews[0]->gold)->toBe(['spam' => 'no', 'abuse' => 'no', 'sentiment' => 'positive']);
});

it('leaves out empty gold labels', function () {
    $reviews = ReviewCsv::read(fixture('reviews.csv'));

    expect($reviews[1]->gold)->toBe(['spam' => 'yes', 'abuse' => 'no']);
});

it('keeps line breaks inside quoted text', function () {
    $reviews = ReviewCsv::read(fixture('reviews.csv'));

    expect($reviews[2]->text)->toBe("Arrived late,\nbut it works.");
});

it('reads a file with only id and text, case-insensitive, skipping blank lines', function () {
    $reviews = ReviewCsv::read(fixture('minimal.csv'));

    expect($reviews)->toHaveCount(2)
        ->and($reviews[0]->lang)->toBeNull()
        ->and($reviews[0]->gold)->toBe([])
        ->and($reviews[1]->text)->toBe('Another one, with a comma.');
});

it('returns no reviews for a header-only file', function () {
    expect(ReviewCsv::read(fixture('empty.csv')))->toBe([]);
});

it('needs a text column', function () {
    ReviewCsv::read(fixture('no-text.csv'));
})->throws(InvalidArgumentException::class, 'needs a "text" column');

it('reports rows with the wrong number of columns', function () {
    ReviewCsv::read(fixture('ragged.csv'));
})->throws(InvalidArgumentException::class, 'Row 3');

it('reports a missing file', function () {
    ReviewCsv::read(missingFile());
})->throws(InvalidArgumentException::class, 'Cannot read review file');
