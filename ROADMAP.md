# Roadmap: Menguasai LayaPHP — Self-Hosted Text Classification untuk PHP & Laravel

Side track dari jurnal *AI Engineering 1000 jam*. Tujuannya menguasai klasifikasi teks self-hosted
dengan [Laya](https://github.com/NandhaKishorM/laya) + [LayaPHP](https://github.com/marcreichel/laya-php)
secara **konsep** (kenapa) dan **praktik** (bagaimana), dengan 5 project portofolio sebagai target.

- Estimasi total: **±5–6 minggu** ritme santai (1–2 jam/hari).
- Lingkungan: kode ditulis & di-push dari Claude Code → di VPS (8 vCPU, 24 GB, CPU only) `git pull` → jalan
  dengan model asli. Setup VPS: [`vps/SETUP.md`](vps/SETUP.md).
- Konsep dasar wajib dibaca dulu: [`notes/fase-0-konsep.md`](notes/fase-0-konsep.md).

## Peta besar

| Fase | Fokus | Estimasi | Project | Status |
|---|---|---|---|---|
| 0 | Konsep: decision model, kalibrasi, abstention, batasan | 2–3 hari | — (kuis) | ☐ |
| 1 | Fundamental LayaPHP + VPS | 3–4 hari | **#1 Review Moderator CLI** | ☐ (kode siap, run di VPS) |
| 2 | Typed decisions, error handling, cache | 4–5 hari | **#2 Support Ticket Triage** | ☐ |
| 3 | Evaluasi & kalibrasi | 5–7 hari | **#3 laya-eval** | ☐ |
| 4 | Integrasi Laravel + human-in-the-loop | 5–7 hari | **#4 ModQueue** | ☐ |
| 5 | Produksi & ekosistem (API, multilingual, benchmark) | 5–7 hari | **#5 Inbox & Lead Triage API** | ☐ |
| 6 | Bonus: sisi Python, kalibrasi ulang, fine-tuning | ±1 minggu | (upgrade #3) | ☐ |

## Top 5 Project (target portofolio)

| # | Project | Skill utama yang dibuktikan | Kenapa menarik di portofolio |
|---|---|---|---|
| 1 | [Review Moderator CLI](01-review-moderator/) | `predict/predictMany`, 3 tipe pertanyaan, routing berbasis confidence, testing dengan `Laya::fake()` | Contoh nyata "AI tanpa LLM API", policy yang bisa dijelaskan, CI hijau |
| 2 | [Support Ticket Triage](02-ticket-triage/) | `decide()` + enum + attributes, batch, cache PSR-16, exception & retry | Kode PHP 8.4 modern yang typed end-to-end |
| 3 | [laya-eval](03-laya-eval/) | precision/recall, confusion matrix, Brier, ECE, threshold dari data | Bukti kamu **mengukur** model, bukan cuma memakainya — skill AI engineer yang paling dicari |
| 4 | [ModQueue](04-modqueue/) | Laravel 13, queue, DI, dashboard moderator, feedback loop | Aplikasi full-stack lengkap dengan human-in-the-loop |
| 5 | [Inbox & Lead Triage API](05-inbox-lead-api/) | REST API, multilingual ID/EN, `score` untuk ranking, `laravel/ai` provider, benchmark p50/p95 | Siap produksi: auth, rate limit, deployment, angka performa nyata |

## Cara kerja tiap fase

1. Baca bagian **Konsep** fase itu (dan sumbernya).
2. Claude menyiapkan kerangka/spec project → kita kerjakan bareng; kamu wajib bisa menjelaskan setiap file.
3. Push dari Claude Code → `git pull` di VPS → jalankan dengan model asli.
4. Isi tabel hasil & bagian **What I learned** di README project (English, untuk portofolio).
5. Centang **Selesai kalau…**, update status di tabel atas, commit.

---

## Fase 0 — Konsep (2–3 hari)

**Tujuan:** paham *kenapa* Laya ada dan kapan (tidak) memakainya, sebelum menyentuh kode.

**Konsep:** generatif vs non-autoregressive; System 1 vs System 2; encoder (ModernBERT/mmBERT); tipe
`choice`/`score`/`noul`; `confidence` vs `answerConfidence`; kalibrasi & ECE; abstention (coverage vs akurasi);
router & checkpoint; self-host vs Jev vs LLM; batasan jujur (over-confident, zero-shot lemah di beberapa tugas,
negasi, bias posisi, label boolean di `choice`).

**Praktik:**
- [ ] Baca [`notes/fase-0-konsep.md`](notes/fase-0-konsep.md) sampai habis.
- [ ] Baca README Laya bagian *Automated Confidence Gating*, *Calibration*, *Honest limits*.
- [ ] Baca README LayaPHP sampai *Testing*.
- [ ] Kerjakan kuis self-check tanpa melihat jawaban.

**Selesai kalau…**
- [ ] Skor kuis ≥ 8/10.
- [ ] Bisa menjelaskan ke orang lain (atau di catatan sendiri, 1 paragraf) kenapa threshold harus dipilih dari data.

---

## Fase 1 — Fundamental LayaPHP + VPS (3–4 hari) → Project #1

**Tujuan:** server Laya jalan di VPS, paham protokolnya, dan paham setiap baris Project 1.

**Konsep:** protokol `POST /v1/systemone` & `/batch`; `Laya` client (PSR-18, `php-http/discovery`);
`Question::choice|score|yesNo`; `Result` & accessor typed (`yesNo()->probability`, `choice()->probabilities`,
`score()->level()`); `predict` vs `predictMany`; threshold `yes($t)`; `Laya::fake()` & assertion-nya.

**Praktik:**
- [ ] Setup VPS sesuai [`vps/SETUP.md`](vps/SETUP.md) sampai `docker compose ps` = healthy.
- [ ] Kirim request mentah dengan `curl` (langkah 6 SETUP). Coba teks Indonesia vs English, lihat field `routing`.
- [ ] Baca kode Project 1 berurutan: `ReviewQuestions` → `ReviewModerator` → `RoutingPolicy` → `Console/ModerateCommand` → tests.
- [ ] Jalankan `php bin/moderate ../datasets/reviews.csv` dengan model asli; isi tabel *Results on the real model*.
- [ ] Eksperimen & catat:
  - [ ] `--model=auto` vs `--model=multilingual` untuk baris `lang=id` — mana yang lebih sering benar?
  - [ ] `--single` vs batch — berapa ms/review masing-masing?
  - [ ] `--min-confidence=0.5` vs `0.9` — berapa yang pindah antara publish dan manual-review?
  - [ ] Review mana saja yang salah? Cocokkan dengan "jebakan" di [`datasets/README.md`](datasets/README.md).
- [ ] Latihan coding (kerjakan sendiri, minta review):
  - [ ] Tambah test untuk kasus abuse borderline (P=0.6) di `RoutingPolicyTest`.
  - [ ] Tambah option `--only=manual-review` untuk menampilkan baris dengan keputusan tertentu saja.
  - [ ] Tambah pertanyaan ke-4 `Question::score('How toxic is the language?', [...])` dan tampilkan di tabel.

**Selesai kalau…**
- [ ] Tabel hasil di README Project 1 terisi dari run VPS.
- [ ] Bisa menjelaskan kenapa `reject` butuh **dua** syarat (probabilitas tinggi *dan* `answerConfidence` tinggi).
- [ ] 3 latihan coding selesai, `composer check` hijau.
- [ ] Bagian *What I learned* di README Project 1 ditulis (3–5 poin).

---

## Fase 2 — Typed Decisions (4–5 hari) → Project #2

**Tujuan:** pakai gaya deklaratif `decide()` (class + enum + attribute) dan buat client yang tahan error.

**Konsep:**
- `decide()` / `decideMany()` dan `DecisionMapper`: properti constructor → pertanyaan.
- Enum backed + `#[Describe]` = opsi `choice`; `int` + `#[Levels]` = `score`; `bool` = yes/no.
- `#[Ask(minConfidence: 0.7)]` + tipe nullable → `null` kalau ragu (abstention deklaratif). `#[Ask(threshold:, yes:, no:)]`.
- Hierarki exception: `ValidationException` (400/413/422), `AuthenticationException` (401),
  `ServerBusyException` (503), `ServerException`, `TransportException` — mana yang layak di-retry?
- Cache PSR-16 (`symfony/cache`): Laya deterministik, jadi teks + pertanyaan + model yang sama = jawaban sama.
- Token budget: `maxLen`, `headMaxLen`, `$result->truncated`.
- Batasan: hindari label boolean di `choice`, uji kalimat **negasi** secara eksplisit.

**Praktik:**
- [ ] Buat dataset `datasets/tickets.csv` (±40 tiket EN/ID, berlabel department/urgency/churn, termasuk kalimat negasi).
- [ ] Implementasi sesuai spec [`02-ticket-triage/README.md`](02-ticket-triage/README.md) — test `->todo()` sudah menunggu.
- [ ] Buktikan cache: run kedua = 0 request ke server (hitung dengan `Laya::fake()` + `assertPredictedCount`, dan di VPS dengan log).
- [ ] Simulasikan server mati / API key salah / 503 dan pastikan pesan error jelas.

**Selesai kalau…**
- [ ] Semua test `->todo()` sudah jadi test nyata dan hijau; PHPStan max bersih.
- [ ] Run di VPS terdokumentasi di README Project 2 (akurasi per field, contoh kasus negasi yang gagal/berhasil).

---

## Fase 3 — Evaluasi & Kalibrasi (5–7 hari) → Project #3

**Tujuan:** berhenti menebak. Ukur seberapa benar dan seberapa jujur model di data kamu, lalu pilih threshold dari bukti.

**Konsep:**
- Confusion matrix; precision, recall, F1 per kelas; macro vs micro average.
- **Paradoks akurasi**: baseline "semua bersih" di Project 1 sudah 80–85% akurat di spam/abuse — kenapa itu menyesatkan.
- **Brier score** (rata-rata kuadrat selisih probabilitas vs kenyataan) dan **ECE** + reliability diagram (bins).
- **Selective classification**: kurva coverage vs akurasi; "di threshold berapa precision ≥ 95%, dan berapa % yang masih otomatis?"
- Overfitting threshold: pilih threshold di satu split, laporkan di split lain.
- Analisis per **slice** (EN vs ID, teks pendek vs panjang).
- Bias posisi opsi: kirim pertanyaan yang sama dengan urutan opsi berbeda (request mentah dengan `option_order`) dan bandingkan.

**Praktik:**
- [ ] Implementasi sesuai spec [`03-laya-eval/README.md`](03-laya-eval/README.md): baca report JSON Project 1 → laporan Markdown.
- [ ] Hitung metrik semua fungsi secara manual di 1 contoh kecil di kertas, lalu cocokkan dengan unit test.
- [ ] Jalankan di VPS untuk `auto` dan `multilingual`; bandingkan ECE per checkpoint.
- [ ] **Tutup loop:** ubah default threshold Project 1 berdasarkan hasil eval, tulis alasannya di README Project 1.

**Selesai kalau…**
- [ ] `EVAL.md` untuk dataset reviews ada di repo, berisi confusion matrix, ECE, tabel threshold sweep, rekomendasi.
- [ ] Bisa menjawab: "Kalau kita auto-publish di threshold X, berapa review jahat yang lolos per 1.000 review?"

---

## Fase 4 — Laravel + Human-in-the-loop (5–7 hari) → Project #4

**Tujuan:** memasukkan Laya ke aplikasi Laravel nyata dengan antrian, dashboard moderator, dan feedback loop.

**Konsep:**
- Package auto-discovery (`LayaServiceProvider`), `config/laya.php`, `.env` (`LAYA_URL`, `LAYA_API_KEY`, `LAYA_CACHE_STORE`, `LAYA_CACHE_TTL`).
- `Laya` sebagai singleton di container & dependency injection ke Job/Controller.
- Queue (`ShouldQueue`), `tries`/`backoff` untuk `ServerBusyException`, `failed()` handler.
- `php artisan laya:health`.
- Testing: `Laya::fake()` otomatis menggantikan binding di container; `Queue::fake()`, `Bus::assertDispatched`.
- Menyimpan probabilitas & `answer_confidence` (kolom JSON) untuk audit.
- Feedback loop: keputusan moderator disimpan → jadi data berlabel baru untuk Fase 3 & 6.

**Praktik:**
- [ ] `composer create-project laravel/laravel` di folder `04-modqueue` (bareng, langkah per langkah).
- [ ] Implementasi sesuai spec [`04-modqueue/README.md`](04-modqueue/README.md).
- [ ] Deploy di VPS: `php artisan serve` (dev) lalu nginx + php-fpm + supervisor untuk queue worker.

**Selesai kalau…**
- [ ] Submit review → status berubah otomatis dalam hitungan detik; kasus ragu muncul di dashboard.
- [ ] Export CSV keputusan moderator bisa dibaca `laya-eval`.
- [ ] Feature test hijau tanpa server Laya.

---

## Fase 5 — Produksi & Ekosistem (5–7 hari) → Project #5

**Tujuan:** API klasifikasi yang siap produksi, multilingual, dengan angka performa yang terukur.

**Konsep:**
- `laravel/ai` Classification API dan provider pihak ketiga [`maeandrew/laya-laravel`](https://packagist.org/packages/maeandrew/laya-laravel):
  abstraksi provider (bisa ganti Laya ↔ Jev ↔ LLM lewat config). Bandingkan dengan pakai LayaPHP langsung.
- Multilingual: router, `Model::Multilingual`, `LAYA_DEFAULT_MODEL`, teks pendek berbahasa Indonesia.
- `score()` untuk ranking (expected score float) vs `level()` untuk bucket.
- API design: validasi request, auth (API key/Sanctum), rate limiting, endpoint batch.
- Observability: log latency per request, p50/p95; `usage.input_tokens`; `truncated`.
- Load test sederhana; perilaku `laya-serve` di bawah beban (satu forward pass per waktu, `LAYA_MAX_CONCURRENT`, 503).
- Hardening deploy: nginx, php-fpm, supervisor, firewall (port 8000 tetap loopback).

**Praktik:**
- [ ] Implementasi sesuai spec [`05-inbox-lead-api/README.md`](05-inbox-lead-api/README.md).
- [ ] Dataset `datasets/emails.csv` (EN/ID, berlabel department/urgency/lead/churn).
- [ ] `BENCHMARK.md`: p50/p95 single vs batch, 1 vs 8 klien paralel, di VPS 8 vCPU.

**Selesai kalau…**
- [ ] API jalan di VPS, terdokumentasi (README + contoh `curl`), ada rate limit & auth.
- [ ] Provider bisa diganti lewat config tanpa ubah controller.
- [ ] Angka benchmark nyata tercatat dan dijelaskan.

---

## Fase 6 — Bonus: Python, kalibrasi ulang & fine-tuning (±1 minggu)

**Tujuan:** menyambungkan side track ini ke jalur utama AI Engineering (Python), dan melihat dari mana akurasi
"sungguhan" datang. Ingat batasan dari README Laya: *"a fast base to specialise, not a zero-shot decision engine"*.

**Konsep:** `laya.Router` di Python (pakai `uv`, konsisten dengan repo ini); temperature scaling per
(tipe pertanyaan, jumlah opsi); `laya-train` dari CSV; split train/validasi/test; data dari feedback ModQueue.

**Praktik:**
- [ ] Jalankan contoh Python `Router().predict(...)` di VPS, bandingkan hasilnya dengan LayaPHP (harus identik).
- [ ] Fit temperature di data validasi, ukur ulang ECE dengan `laya-eval`.
- [ ] Fine-tune kecil dengan `laya-train` (CPU di VPS bisa, tapi lambat; alternatif: notebook Kaggle 2×T4 dari repo Laya).
- [ ] Serve checkpoint hasil fine-tune, bandingkan base vs fine-tuned di `laya-eval`.
- [ ] Opsional (open source): kontribusi ke LayaPHP, mis. dukungan `option_order` yang belum ada di v1.2.

**Selesai kalau…**
- [ ] Ada laporan perbandingan base vs kalibrasi ulang vs fine-tuned di dataset yang sama.

---

## Sumber resmi

- Laya (engine, Python): https://github.com/NandhaKishorM/laya — versi di VPS: 0.3.23 (commit `d8a2e59`).
- LayaPHP (client PHP): https://github.com/marcreichel/laya-php — versi: 1.2.0.
- Artikel pemicu: *LayaPHP: Self-Hosted Text Classification for PHP and Laravel* — Laravel News.
- `laya-laravel` (provider `laravel/ai`): https://packagist.org/packages/maeandrew/laya-laravel
