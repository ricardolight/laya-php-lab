<?php

declare(strict_types=1);

namespace ReviewModerator;

use MarcReichel\Laya\Question;

final class ReviewQuestions
{
    public const string SPAM = 'spam';

    public const string ABUSE = 'abuse';

    public const string SENTIMENT = 'sentiment';

    public const array IDS = [self::SPAM, self::ABUSE, self::SENTIMENT];

    /** @return array<string, Question> */
    public static function all(): array
    {
        return [
            self::SPAM => Question::yesNo(
                'Is this review spam, an advertisement, or unrelated to the product?',
                yes: 'promotes another product, website or service, or is not about this product',
                no: 'a genuine opinion about the product or the purchase',
            ),
            self::ABUSE => Question::yesNo(
                'Does this review contain insults, hate speech, harassment or threats?',
                yes: 'insults or attacks a person or a group',
                no: 'criticism without personal attacks',
            ),
            self::SENTIMENT => Question::choice('What is the overall sentiment of this review towards the product?', [
                'positive' => 'mostly praises the product',
                'neutral' => 'mixed, or describes the product without clear praise or criticism',
                'negative' => 'mostly criticises the product or the purchase',
            ]),
        ];
    }
}
