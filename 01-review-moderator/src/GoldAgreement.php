<?php

declare(strict_types=1);

namespace ReviewModerator;

/**
 * How often the predictions match the dataset's gold labels, per question. A quick sanity check;
 * the full evaluation (precision, recall, calibration) lives in the laya-eval project.
 */
final class GoldAgreement
{
    /**
     * @param  list<ModerationResult>  $results
     * @return array<string, array{correct: int, total: int, accuracy: float}>
     */
    public static function of(array $results): array
    {
        $agreement = [];
        foreach (ReviewQuestions::IDS as $id) {
            $correct = 0;
            $total = 0;
            foreach ($results as $result) {
                $gold = $result->review->gold[$id] ?? null;
                if ($gold === null) {
                    continue;
                }

                $total++;
                if (strtolower($gold) === $result->predictedLabels()[$id]) {
                    $correct++;
                }
            }

            if ($total > 0) {
                $agreement[$id] = ['correct' => $correct, 'total' => $total, 'accuracy' => (float) $correct / $total];
            }
        }

        return $agreement;
    }
}
