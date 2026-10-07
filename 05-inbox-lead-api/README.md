# Inbox & Lead Triage API

> Project 5 of 5 in my [LayaPHP learning roadmap](../ROADMAP.md) — **Fase 5: Production & ecosystem**.
> Status: **spec only**. Built as a Laravel 13 API in this phase.

A production-style HTTP API that triages incoming emails and contact-form messages in **English and Bahasa
Indonesia**: which team, how urgent, is this a sales lead (and how hot), is a customer about to churn. Built to
answer the questions a real deployment asks: auth, rate limits, batch throughput, latency percentiles, and
whether the classifier provider can be swapped without touching the controllers.

## Endpoints

| Method | Path | Body | Notes |
|---|---|---|---|
| `POST` | `/api/triage` | `{subject, body, lang?}` | one message |
| `POST` | `/api/triage/batch` | `{messages: [...]}` (≤ 64) | one laya-serve batch request |
| `GET` | `/api/health` | — | app + laya-serve health, loaded checkpoints |

Example response:

```json
{
  "department": {"value": "sales", "answer_confidence": 0.91},
  "urgency": {"level": 1, "label": "soon", "score": 1.38},
  "lead": {"readiness": {"level": 2, "label": "ready to buy", "score": 1.82}, "mentions_budget": true},
  "churn_risk": {"probability": 0.07},
  "needs_human": false,
  "model": "multilingual",
  "latency_ms": 412
}
```

`lead.readiness.score` (expected level, a float) is what the sales inbox sorts by: two leads with the same
level 2 still get ordered. `needs_human` is true when any `answer_confidence` is below the threshold chosen
with `laya-eval`.

## Design

- **Provider abstraction** — a `Classifier` interface with two drivers selected by `config('triage.driver')`:
  - `laya-php`: LayaPHP directly (`predict` / `predictMany`).
  - `laravel-ai`: Laravel's AI SDK Classification API with the community
    [`maeandrew/laya-laravel`](https://packagist.org/packages/maeandrew/laya-laravel) provider.
  Same JSON either way; comparing the two is part of the learning (if the provider isn't compatible with the
  `laravel/ai` version at build time, document why and ship the LayaPHP driver).
- **Multilingual** — compare `auto` routing, `Model::Multilingual`, and `LAYA_DEFAULT_MODEL=multilingual` on
  short Indonesian messages; pick by measurement.
- **Auth & limits** — API key per client (Sanctum tokens), `RateLimiter` per key, request validation
  (max body length, batch size ≤ 64).
- **Errors** — `ServerBusyException` → 503 + `Retry-After`; `TransportException` → 502; validation → 422.
- **Observability** — middleware logs latency, checkpoint, `input_tokens` and `truncated` per request;
  `php artisan triage:latency-report` prints p50/p95/p99 from the log.

## Dataset

`../datasets/emails.csv`: `id,lang,subject,body,department,urgency,lead_readiness,churn` (~50 rows, EN + ID),
including negations and very short messages.

## Benchmark (`BENCHMARK.md`)

On the VPS (8 vCPU, CPU-only laya-serve, `LAYA_THREADS=6`), measure and explain:

| Scenario | p50 | p95 | messages/s |
|---|---|---|---|
| single, 1 client | | | |
| single, 8 parallel clients | | | |
| batch of 32, 1 client | | | |

Expect parallel single requests to queue (laya-serve runs one forward pass at a time) and batches to win.

## Acceptance criteria

- [ ] API running on the VPS behind nginx, documented with copy-paste `curl` examples.
- [ ] Switching `triage.driver` changes the provider with no controller changes; both covered by tests.
- [ ] Feature tests green without laya-serve (`Laya::fake()` / `Classification::fake()`).
- [ ] `BENCHMARK.md` filled with real numbers and a short explanation.

## What I learned

_todo_
