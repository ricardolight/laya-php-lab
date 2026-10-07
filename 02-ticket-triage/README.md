# Support Ticket Triage

> Project 2 of 5 in my [LayaPHP learning roadmap](../ROADMAP.md) — **Fase 2: Typed Decisions**.
> Status: **spec + scaffold**. The tests in `tests/` are Pest `->todo()`s: turning each one into a real,
> passing test is the work of this phase.

Route incoming support tickets (English and Bahasa Indonesia) to the right team, with a priority and a churn
flag, using LayaPHP's **declarative** API: a PHP class *is* the set of questions, and `decide()` returns an
instance of it.

## What it should do

```php
enum Department: string
{
    #[Describe('invoices, payments, refunds, double charges')]
    case Billing = 'billing';

    #[Describe('bugs, errors, outages, something does not work')]
    case Technical = 'technical';

    #[Describe('login, password, profile, deleting or changing an account')]
    case Account = 'account';

    #[Describe('pricing questions, upgrades, buying more seats')]
    case Sales = 'sales';

    case Other = 'other';
}

final readonly class Triage
{
    public function __construct(
        #[Ask('Which team should handle this ticket?', minConfidence: 0.7)]
        public ?Department $department,

        #[Ask('How urgently does the customer need an answer?'), Levels('not urgent', 'soon', 'blocking')]
        public int $urgency,

        #[Ask('Does the customer threaten to cancel, leave or switch to a competitor?', yes: 'says they will cancel or leave')]
        public bool $churn,
    ) {}
}

$triages = $laya->decideMany($tickets, Triage::class);  // ['T-1042' => Triage, ...]
```

Routing rules (`TicketRouter`):

| Condition | Result |
|---|---|
| `department === null` (Laya below 0.7 confidence) | queue `human-triage` |
| otherwise | queue = department value |
| `urgency === 2` (blocking) | priority `P1`, else `P2`/`P3` by level |
| `churn === true` | also notify `retention` |

## Components to build

| Class | Responsibility | Concepts |
|---|---|---|
| `Department`, `Triage` | the decision schema | backed enums, `#[Describe]`, `#[Ask]`, `#[Levels]`, nullable = abstention |
| `TicketTriager` | wraps `Laya::decideMany()`, chunking, timing | batching, keys preserved |
| `TicketRouter` | pure function from `Triage` to queue/priority | testable business rules |
| `RetryingHttpClient` | PSR-18 decorator: retry 503 + transport errors with exponential backoff, never 4xx | decorator pattern, which errors are retryable |
| `Cache` wiring | `new Laya($url, cache: new Psr16Cache(new FilesystemAdapter(...)), cacheTtl: 86400)` | determinism makes caching safe; TTL vs model upgrades |
| `bin/triage` | `php bin/triage ../datasets/tickets.csv [--no-cache] [--json] [--fake]` | same CLI conventions as Project 1 |

## Dataset

Create `../datasets/tickets.csv` with columns `id,lang,text,department,urgency,churn` (~40 rows, EN + ID).
Include on purpose:
- **Negations** — "I do *not* want to cancel, I just need the invoice", "saya tidak mau berhenti langganan".
- Tickets that fit two departments ("charged twice *because* the app crashed").
- Very short tickets ("help!!", "tolong").
- Polite-but-blocking vs angry-but-not-urgent.

## Acceptance criteria

- [ ] Every `->todo()` in `tests/` is a real passing test; `composer check` is green (Pint, PHPStan max, Pest).
- [ ] A second run on the same CSV makes **zero** requests (cache), shown in a test and in the VPS log.
- [ ] Server down / wrong API key / 503 each print one clear line, not a stack trace.
- [ ] VPS results table below is filled in, including which negation tickets went wrong.

## Results on the real model

_todo — accuracy per field, a few wrong answers and why, ms/ticket batched vs single._

## What I learned

_todo_
