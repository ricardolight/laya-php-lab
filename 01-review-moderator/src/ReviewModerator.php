<?php

declare(strict_types=1);

namespace ReviewModerator;

use MarcReichel\Laya\Laya;
use MarcReichel\Laya\Model;
use MarcReichel\Laya\Result;

final readonly class ReviewModerator
{
    public function __construct(
        private Laya $laya,
        private RoutingPolicy $policy = new RoutingPolicy,
        private ?Model $model = null,
    ) {}

    public function moderate(Review $review): ModerationResult
    {
        return $this->toResult($review, $this->laya->predict($review->text, ReviewQuestions::all(), $this->model));
    }

    /**
     * Sends the reviews in shared batches (needs laya-serve >= 0.3.22).
     *
     * @param  list<Review>  $reviews
     * @return list<ModerationResult>
     */
    public function moderateMany(array $reviews): array
    {
        $results = $this->laya->predictMany(
            array_map(fn (Review $review): string => $review->text, $reviews),
            ReviewQuestions::all(),
            $this->model,
        );

        return array_map(fn (Review $review, Result $result) => $this->toResult($review, $result), $reviews, $results);
    }

    private function toResult(Review $review, Result $result): ModerationResult
    {
        $spam = $result->yesNo(ReviewQuestions::SPAM);
        $abuse = $result->yesNo(ReviewQuestions::ABUSE);
        $sentiment = $result->choice(ReviewQuestions::SENTIMENT);

        return new ModerationResult(
            $review,
            $spam,
            $abuse,
            $sentiment,
            $this->policy->decide($spam, $abuse, $sentiment),
            $result->routedModel,
        );
    }
}
