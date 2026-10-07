<?php

declare(strict_types=1);

use MarcReichel\Laya\Answers\ChoiceAnswer;
use MarcReichel\Laya\Answers\YesNoAnswer;

/** A yes/no answer whose confidence defaults to how far P(yes) is from a coin flip, like laya's. */
function yesNo(float $probability, ?float $answerConfidence = null): YesNoAnswer
{
    $confidence = max($probability, 1 - $probability);

    return new YesNoAnswer($probability, $confidence, $answerConfidence ?? $confidence, []);
}

function sentiment(string $choice, float $answerConfidence = 0.95): ChoiceAnswer
{
    return new ChoiceAnswer($choice, [$choice => $answerConfidence], $answerConfidence, $answerConfidence, []);
}

function missingFile(): string
{
    return sys_get_temp_dir().'/laya-moderator-does-not-exist.csv';
}
