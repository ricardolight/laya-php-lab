# laya-eval

> Project 3 of 5 in my [LayaPHP learning roadmap](../ROADMAP.md) — **Fase 3: Evaluation & Calibration**.
> Status: **spec + scaffold**. The tests in `tests/` are Pest `->todo()`s.

A small, model-agnostic evaluation tool: feed it the JSON report of a classifier run (Project 1's
`output/moderation-latest.json`, later Project 2's and the ModQueue feedback export) and it writes a Markdown
report answering three questions:

1. **How often is it right?** — per class, not just overall.
2. **Can I trust its confidence?** — calibration.
3. **Where should the automation threshold be?** — chosen from data, not guessed.

It depends on no Laya code at all: evaluation shouldn't care which model produced the predictions.

## Why this project matters

Laya's own README says the checkpoints are **over-confident as shipped** (mean ECE 0.466 for `laya`) and that
*"a threshold is a policy you choose from measured accuracy at that coverage on your data"*. Project 1 also
showed the trap of plain accuracy: a fake "everything is clean" run already scores 80–85% on spam/abuse.
This tool is how those statements become numbers for *my* data.

## Report contents (per question: spam, abuse, sentiment, …)

| Section | What | Notes |
|---|---|---|
| Summary | accuracy, macro-F1, Brier, ECE, n | compare against the majority-class baseline |
| Per class | precision, recall, F1, support | precision is *undefined* for a never-predicted class, not 0 |
| Confusion matrix | gold × predicted | |
| Reliability table | per confidence bin: n, mean confidence, accuracy, gap | over-confident when confidence > accuracy |
| Threshold sweep | per threshold: coverage, accuracy of automated answers, errors per 1,000 | selective classification |
| Recommendation | lowest threshold reaching `--target-accuracy` | chosen on one split, reported on the other |
| Slices | all of the above per `--slice` value (e.g. `lang`) | EN vs ID |

Formulas to implement (and test on hand-worked examples):

- **Brier** (binary): mean of (p − y)², with y ∈ {0, 1}. Multi-class: mean over samples of Σₖ (pₖ − yₖ)².
- **ECE**: split predictions into B confidence bins; ECE = Σ_b (n_b / N) · |accuracy_b − confidence_b|.
- **Coverage** at threshold t: share of predictions with `answer_confidence ≥ t`; accuracy is computed on those only.

## CLI

```bash
php bin/laya-eval ../01-review-moderator/output/moderation-latest.json \
    --target-accuracy=0.95 --bins=10 --slice=lang --split=0.5 -o EVAL.md
```

## Acceptance criteria

- [ ] Every `->todo()` is a real passing test; `composer check` is green.
- [ ] `EVAL.md` for the review dataset (real VPS run) is committed, for both `auto` and `multilingual`.
- [ ] Project 1's default thresholds are updated from this evidence, with the reasoning in its README.
- [ ] I can answer: *"If we auto-publish at threshold X, how many bad reviews slip through per 1,000?"*

## What I learned

_todo_
