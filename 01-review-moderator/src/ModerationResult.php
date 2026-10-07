<?php

declare(strict_types=1);

namespace ReviewModerator;

use MarcReichel\Laya\Answers\ChoiceAnswer;
use MarcReichel\Laya\Answers\YesNoAnswer;

final readonly class ModerationResult
{
    public function __construct(
        public Review $review,
        public YesNoAnswer $spam,
        public YesNoAnswer $abuse,
        public ChoiceAnswer $sentiment,
        public Verdict $verdict,
        public ?string $routedModel = null,
    ) {}

    public function decision(): Decision
    {
        return $this->verdict->decision;
    }

    public function sentimentLabel(): string
    {
        return (string) $this->sentiment->choice;
    }

    /**
     * Predictions in the same vocabulary as the dataset's gold columns.
     *
     * @return array<string, string>
     */
    public function predictedLabels(): array
    {
        return [
            ReviewQuestions::SPAM => $this->spam->yes() ? 'yes' : 'no',
            ReviewQuestions::ABUSE => $this->abuse->yes() ? 'yes' : 'no',
            ReviewQuestions::SENTIMENT => $this->sentimentLabel(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->review->id,
            'lang' => $this->review->lang,
            'text' => $this->review->text,
            'decision' => $this->decision()->value,
            'reasons' => $this->verdict->reasons,
            'routed_model' => $this->routedModel,
            'answers' => [
                ReviewQuestions::SPAM => [
                    'probability' => $this->spam->probability,
                    'answer_confidence' => $this->spam->answerConfidence,
                ],
                ReviewQuestions::ABUSE => [
                    'probability' => $this->abuse->probability,
                    'answer_confidence' => $this->abuse->answerConfidence,
                ],
                ReviewQuestions::SENTIMENT => [
                    'choice' => $this->sentimentLabel(),
                    'probabilities' => $this->sentiment->probabilities,
                    'answer_confidence' => $this->sentiment->answerConfidence,
                ],
            ],
            'predicted' => $this->predictedLabels(),
            'gold' => (object) $this->review->gold,
        ];
    }
}
