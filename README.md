# laya-php-lab

Learning lab and portfolio for **self-hosted text classification** in PHP and Laravel, built on
[Laya](https://github.com/NandhaKishorM/laya) (a non-autoregressive "System 1" decision model) and its PHP
client [LayaPHP](https://github.com/marcreichel/laya-php).

Instead of asking an LLM API for a yes/no and parsing free text, these projects ask a model running on my own
server **typed questions** (choice, score, yes/no) and get back probabilities with a confidence I can threshold:
no per-call fees, no data leaving the server, and a clear rule for when a person has to decide.

- **Roadmap** (7 phases, in Bahasa Indonesia): [ROADMAP.md](ROADMAP.md)
- **Concept notes + quiz:** [notes/fase-0-konsep.md](notes/fase-0-konsep.md)
- **Run it on a VPS** (CPU only, Docker): [vps/SETUP.md](vps/SETUP.md)

## Projects

| # | Project | What it shows | Status |
|---|---|---|---|
| 1 | [Review Moderator CLI](01-review-moderator/) | spam / abuse / sentiment, confidence-based routing to publish, manual review or reject, tests with `Laya::fake()` | code done, VPS run pending |
| 2 | [Support Ticket Triage](02-ticket-triage/) | `decide()` with enums and attributes, batching, PSR-16 cache, retries | spec |
| 3 | [laya-eval](03-laya-eval/) | precision/recall, Brier, ECE, threshold sweeps: measuring the model instead of trusting it | spec |
| 4 | [ModQueue](04-modqueue/) | Laravel moderation app with queues and a human-in-the-loop dashboard | spec |
| 5 | [Inbox & Lead Triage API](05-inbox-lead-api/) | multilingual (ID/EN) triage API, provider abstraction, p50/p95 benchmark | spec |

Shared test data lives in [datasets/](datasets/). CI runs Pint, PHPStan (max) and Pest for every implemented
project; tests use `Laya::fake()`, so no model server is needed.

## Quick start

```bash
cd 01-review-moderator
composer install
composer check                                   # lint + static analysis + tests, no server needed
php bin/moderate ../datasets/reviews.csv --fake  # smoke test
```

License: MIT.
