<?php

declare(strict_types=1);

use MarcReichel\Laya\Laya;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReviewModerator\Console\ModerateCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->report = sys_get_temp_dir().'/laya-moderator-'.bin2hex(random_bytes(4)).'/report.json';
});

afterEach(function () {
    if (is_file($this->report)) {
        unlink($this->report);
        rmdir(dirname($this->report));
    }
});

function commandTester(?Closure $layaFactory = null): CommandTester
{
    return new CommandTester(new ModerateCommand(layaFactory: $layaFactory));
}

function unreachableLaya(): Laya
{
    $client = new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            throw new class('Connection refused') extends RuntimeException implements ClientExceptionInterface {};
        }
    };

    return new Laya('http://127.0.0.1:1', httpClient: $client);
}

it('moderates a CSV in fake mode and writes a JSON report', function () {
    $tester = commandTester();

    $status = $tester->execute(['file' => fixture('reviews.csv'), '--fake' => true, '--output' => $this->report]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Fake mode', 'publish', 'Gold agreement', 'Moderated 3 reviews');

    $report = json_decode((string) file_get_contents($this->report), true);
    expect($report['laya_url'])->toBe('fake')
        ->and($report['mode'])->toBe('batch')
        ->and($report['summary'])->toBe(['publish' => 3, 'manual-review' => 0, 'reject' => 0])
        ->and($report['results'])->toHaveCount(3)
        ->and($report['gold_agreement']['spam'])->toMatchArray(['correct' => 2, 'total' => 3]);
});

it('prints only JSON with --json', function () {
    $tester = commandTester();

    $tester->execute(['file' => fixture('reviews.csv'), '--fake' => true, '--json' => true, '--output' => $this->report]);

    expect(json_decode($tester->getDisplay(), true))->toBeArray()->toHaveKey('results');
});

it('checks laya-serve health, then sends one request per review with --single', function () {
    $laya = Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);
    $tester = commandTester(fn () => $laya);

    $status = $tester->execute(['file' => fixture('reviews.csv'), '--single' => true, '--model' => 'multilingual', '--output' => $this->report]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('is up (device: fake', 'one request per review');
    $laya->assertPredictedCount(3);
    $laya->assertPredicted(fn (mixed $state, array $questions, ?string $model) => $model === 'multilingual');
});

it('passes the URL and API key to the client factory', function () {
    $received = [];
    $tester = commandTester(function (string $url, ?string $apiKey) use (&$received) {
        $received = [$url, $apiKey];

        return Laya::fake(['spam' => 0.05, 'abuse' => 0.02, 'sentiment' => 'positive']);
    });

    $tester->execute(['file' => fixture('reviews.csv'), '--url' => 'http://laya.internal:8000', '--api-key' => 'secret', '--output' => $this->report]);

    expect($received)->toBe(['http://laya.internal:8000', 'secret']);
});

it('explains how to start laya-serve when it is unreachable', function () {
    $tester = commandTester(fn () => unreachableLaya());

    $status = $tester->execute(['file' => fixture('reviews.csv'), '--output' => $this->report]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('Could not reach laya-serve at http://127.0.0.1:1', 'Connection refused', 'SETUP.md')
        ->and($this->report)->not->toBeFile();
});

it('rejects an unknown model', function () {
    $tester = commandTester();

    $status = $tester->execute(['file' => fixture('reviews.csv'), '--fake' => true, '--model' => 'gpt']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('Unknown model "gpt"');
});

it('rejects a non-numeric threshold', function () {
    $tester = commandTester();

    $status = $tester->execute(['file' => fixture('reviews.csv'), '--fake' => true, '--min-confidence' => 'high']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('--min-confidence must be a number');
});

it('reports a missing file', function () {
    $tester = commandTester();

    $status = $tester->execute(['file' => missingFile(), '--fake' => true]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('Cannot read review file');
});

it('stops early on a file without reviews', function () {
    $tester = commandTester();

    $status = $tester->execute(['file' => fixture('empty.csv'), '--fake' => true, '--output' => $this->report]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('no reviews');
});
