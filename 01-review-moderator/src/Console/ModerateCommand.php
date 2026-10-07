<?php

declare(strict_types=1);

namespace ReviewModerator\Console;

use MarcReichel\Laya\Exceptions\LayaException;
use MarcReichel\Laya\Laya;
use MarcReichel\Laya\Model;
use ReviewModerator\Decision;
use ReviewModerator\GoldAgreement;
use ReviewModerator\ModerationResult;
use ReviewModerator\Review;
use ReviewModerator\ReviewCsv;
use ReviewModerator\ReviewModerator;
use ReviewModerator\RoutingPolicy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'moderate', description: 'Classify reviews for spam, abuse and sentiment, then route each one to publish, manual-review or reject.')]
final class ModerateCommand extends Command
{
    /** Canned answers for --fake: every review looks clean, so the run only proves the plumbing works. */
    private const array FAKE_ANSWERS = ['spam' => 0.08, 'abuse' => 0.03, 'sentiment' => 'positive'];

    /** @var \Closure(string, ?string): Laya */
    private readonly \Closure $layaFactory;

    /**
     * @param  (\Closure(string $url, ?string $apiKey): Laya)|null  $layaFactory
     */
    public function __construct(
        private readonly string $defaultOutput = 'output/moderation-latest.json',
        ?\Closure $layaFactory = null,
    ) {
        parent::__construct();
        $this->layaFactory = $layaFactory ?? fn (string $url, ?string $apiKey): Laya => new Laya($url, apiKey: $apiKey);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'CSV with an "id" and a "text" column (optional: lang, spam, abuse, sentiment)')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'laya-serve base URL', self::env('LAYA_URL') ?? 'http://localhost:8000')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'Bearer token if laya-serve runs with LAYA_API_KEY', self::env('LAYA_API_KEY'))
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'auto, english, multilingual or typed-decisions', 'auto')
            ->addOption('min-confidence', null, InputOption::VALUE_REQUIRED, 'Below this answer confidence a person decides', '0.7')
            ->addOption('flag-threshold', null, InputOption::VALUE_REQUIRED, 'P(spam|abuse) at which a review is flagged for a person', '0.5')
            ->addOption('reject-threshold', null, InputOption::VALUE_REQUIRED, 'P(spam|abuse) at which a confident answer rejects the review', '0.9')
            ->addOption('single', null, InputOption::VALUE_NONE, 'One request per review instead of batches (for laya-serve < 0.3.22, or to time single calls)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the JSON report instead of a table')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Where to write the JSON report', $this->defaultOutput)
            ->addOption('fake', null, InputOption::VALUE_NONE, 'Use Laya::fake() instead of a server: a smoke test with canned answers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $model = self::model(self::string($input->getOption('model')));
            $policy = new RoutingPolicy(
                self::float($input->getOption('min-confidence'), 'min-confidence'),
                self::float($input->getOption('flag-threshold'), 'flag-threshold'),
                self::float($input->getOption('reject-threshold'), 'reject-threshold'),
            );
            $reviews = ReviewCsv::read(self::string($input->getArgument('file')));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        if ($reviews === []) {
            $io->warning('The file has no reviews.');

            return self::SUCCESS;
        }

        $fake = (bool) $input->getOption('fake');
        $url = self::string($input->getOption('url'));
        $apiKey = $input->getOption('api-key');
        $laya = $fake ? Laya::fake(self::FAKE_ANSWERS) : ($this->layaFactory)($url, is_string($apiKey) && $apiKey !== '' ? $apiKey : null);
        $json = (bool) $input->getOption('json');

        if ($fake && ! $json) {
            $io->warning('Fake mode: every review gets the same canned answers. Run without --fake against laya-serve for real decisions.');
        }

        if (! $fake && ! $this->serverIsHealthy($laya, $url, $io, $json)) {
            return self::FAILURE;
        }

        $single = (bool) $input->getOption('single');
        $moderator = new ReviewModerator($laya, $policy, $model);

        try {
            $start = hrtime(true);
            $results = $single
                ? array_map(fn (Review $review) => $moderator->moderate($review), $reviews)
                : $moderator->moderateMany($reviews);
            $elapsedMs = (hrtime(true) - $start) / 1e6;
        } catch (LayaException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $report = [
            'generated_at' => date(DATE_ATOM),
            'source' => self::string($input->getArgument('file')),
            'laya_url' => $fake ? 'fake' : $url,
            'model' => $model->value ?? 'auto',
            'mode' => $single ? 'single' : 'batch',
            'policy' => [
                'min_confidence' => $policy->minConfidence,
                'flag_threshold' => $policy->flagThreshold,
                'reject_threshold' => $policy->rejectThreshold,
            ],
            'elapsed_ms' => round($elapsedMs, 1),
            'summary' => self::summary($results),
            'gold_agreement' => (object) GoldAgreement::of($results),
            'results' => array_map(fn (ModerationResult $result) => $result->toArray(), $results),
        ];
        $reportJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $outputPath = self::string($input->getOption('output'));
        $written = $this->write($outputPath, $reportJson);

        if ($json) {
            $output->writeln($reportJson);

            return self::SUCCESS;
        }

        $this->render($io, $results, $elapsedMs, $single);
        $written
            ? $io->writeln(sprintf('Report written to <info>%s</info>', $outputPath))
            : $io->warning(sprintf('Could not write the report to %s.', $outputPath));

        return self::SUCCESS;
    }

    private function serverIsHealthy(Laya $laya, string $url, SymfonyStyle $io, bool $quiet): bool
    {
        try {
            $health = $laya->health();
        } catch (LayaException $e) {
            $io->error([
                $e->getMessage(),
                'Start it with `docker compose up -d --wait` (see laya-php/vps/SETUP.md), or try the CLI with --fake.',
            ]);

            return false;
        }

        if (! $health->ok) {
            $io->error(sprintf('laya-serve at %s answered, but is not healthy yet. Wait for the model to load and retry.', $url));

            return false;
        }

        if (! $quiet) {
            $io->writeln(sprintf(
                'laya-serve at <info>%s</info> is up (device: %s, loaded: %s)',
                $url,
                $health->device,
                $health->loaded === [] ? 'none yet, the first request loads a checkpoint' : implode(', ', $health->loaded),
            ));
        }

        return true;
    }

    /** @param list<ModerationResult> $results */
    private function render(SymfonyStyle $io, array $results, float $elapsedMs, bool $single): void
    {
        $io->table(
            ['ID', 'Lang', 'Review', 'Spam', 'Abuse', 'Sentiment', 'Decision', 'Why'],
            array_map(fn (ModerationResult $result) => [
                $result->review->id,
                $result->review->lang ?? '-',
                mb_strimwidth(str_replace(["\r", "\n"], ' ', $result->review->text), 0, 40, '…'),
                sprintf('%.2f', $result->spam->probability),
                sprintf('%.2f', $result->abuse->probability),
                sprintf('%s (%.2f)', $result->sentimentLabel(), $result->sentiment->answerConfidence),
                self::colored($result->decision()),
                mb_strimwidth(implode('; ', $result->verdict->reasons), 0, 60, '…'),
            ], $results),
        );

        $summary = self::summary($results);
        $io->writeln(sprintf(
            'Decisions: <fg=green>%d publish</>, <fg=yellow>%d manual-review</>, <fg=red>%d reject</>',
            $summary[Decision::Publish->value],
            $summary[Decision::ManualReview->value],
            $summary[Decision::Reject->value],
        ));

        foreach (GoldAgreement::of($results) as $question => $agreement) {
            $io->writeln(sprintf(
                'Gold agreement %-10s %d/%d (%.0f%%)',
                $question.':',
                $agreement['correct'],
                $agreement['total'],
                $agreement['accuracy'] * 100,
            ));
        }

        $io->writeln(sprintf(
            'Moderated %d reviews in %.0f ms (%.1f ms/review, %s)',
            count($results),
            $elapsedMs,
            $elapsedMs / count($results),
            $single ? 'one request per review' : 'batched',
        ));
    }

    /**
     * @param  list<ModerationResult>  $results
     * @return array<string, int>
     */
    private static function summary(array $results): array
    {
        $summary = array_fill_keys(array_map(fn (Decision $d) => $d->value, Decision::cases()), 0);
        foreach ($results as $result) {
            $summary[$result->decision()->value]++;
        }

        return $summary;
    }

    private static function colored(Decision $decision): string
    {
        $color = match ($decision) {
            Decision::Publish => 'green',
            Decision::ManualReview => 'yellow',
            Decision::Reject => 'red',
        };

        return sprintf('<fg=%s>%s</>', $color, $decision->value);
    }

    private function write(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            return false;
        }

        return @file_put_contents($path, $contents.PHP_EOL) !== false;
    }

    private static function model(string $value): ?Model
    {
        if ($value === 'auto') {
            return null;
        }

        return Model::tryFrom($value) ?? throw new \InvalidArgumentException(sprintf(
            'Unknown model "%s". Use auto, %s.',
            $value,
            implode(', ', array_map(fn (Model $m) => $m->value, Model::cases())),
        ));
    }

    private static function float(mixed $value, string $option): float
    {
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('--%s must be a number.', $option));
        }

        return (float) $value;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
