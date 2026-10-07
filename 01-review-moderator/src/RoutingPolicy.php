<?php

declare(strict_types=1);

namespace ReviewModerator;

use MarcReichel\Laya\Answers\ChoiceAnswer;
use MarcReichel\Laya\Answers\YesNoAnswer;

/**
 * Rejects only what laya is sure is spam or abuse, publishes only what it is sure is clean,
 * and sends everything in between to a person.
 */
final readonly class RoutingPolicy
{
    public function __construct(
        public float $minConfidence = 0.7,
        public float $flagThreshold = 0.5,
        public float $rejectThreshold = 0.9,
    ) {
        foreach (['minConfidence' => $minConfidence, 'flagThreshold' => $flagThreshold, 'rejectThreshold' => $rejectThreshold] as $name => $value) {
            if ($value < 0.0 || $value > 1.0) {
                throw new \InvalidArgumentException(sprintf('%s must be between 0 and 1, got %.2f.', $name, $value));
            }
        }

        if ($flagThreshold > $rejectThreshold) {
            throw new \InvalidArgumentException('flagThreshold must not be higher than rejectThreshold.');
        }
    }

    public function decide(YesNoAnswer $spam, YesNoAnswer $abuse, ChoiceAnswer $sentiment): Verdict
    {
        $flags = [ReviewQuestions::SPAM => $spam, ReviewQuestions::ABUSE => $abuse];

        $rejectReasons = [];
        foreach ($flags as $id => $answer) {
            if ($answer->yes($this->rejectThreshold) && $answer->answerConfidence >= $this->minConfidence) {
                $rejectReasons[] = sprintf('%s (P=%.2f)', $id, $answer->probability);
            }
        }

        if ($rejectReasons !== []) {
            return new Verdict(Decision::Reject, $rejectReasons);
        }

        $reasons = [];
        foreach ($flags as $id => $answer) {
            if ($answer->yes($this->flagThreshold)) {
                $reasons[] = sprintf('possible %s (P=%.2f)', $id, $answer->probability);
            }
        }

        foreach ([...$flags, ReviewQuestions::SENTIMENT => $sentiment] as $id => $answer) {
            if ($answer->answerConfidence < $this->minConfidence) {
                $reasons[] = sprintf('unsure about %s (confidence %.2f)', $id, $answer->answerConfidence);
            }
        }

        return $reasons === []
            ? new Verdict(Decision::Publish)
            : new Verdict(Decision::ManualReview, $reasons);
    }
}
