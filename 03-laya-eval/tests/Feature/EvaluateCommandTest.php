<?php

declare(strict_types=1);

it('reads a review-moderator JSON report into predictions with gold labels')->todo();

it('skips predictions without a gold label for that question')->todo();

it('writes a Markdown report with metrics, a confusion matrix, a reliability table and a threshold sweep per question')->todo();

it('splits every metric by a slice column such as lang')->todo();

it('rejects a file that is not a supported report')->todo();
