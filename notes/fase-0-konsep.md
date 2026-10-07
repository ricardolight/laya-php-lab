---
tags: [ai-roadmap, laya, konsep, kalibrasi, system1]
fase: 0
---

# Fase 0 — Konsep Dasar Laya & LayaPHP

Bahan bacaan sebelum menulis kode. Targetnya: kamu bisa menjelaskan **kenapa** setiap keputusan di
project nanti dibuat, bukan cuma **bagaimana** memanggil API-nya. Di akhir ada kuis self-check.

Sumber utama: [README Laya](https://github.com/NandhaKishorM/laya) (versi 0.3.23, yang dipakai di VPS kita)
dan [README LayaPHP](https://github.com/marcreichel/laya-php).

---

## 1. Masalah yang diselesaikan

Banyak kebutuhan "AI" di aplikasi sebenarnya **bukan** menulis teks, tapi **mengambil keputusan kecil**:

- Review ini spam atau bukan?
- Tiket ini untuk tim billing, technical, atau sales?
- Seberapa urgent email ini: santai, segera, atau blocking?

Kalau pakai LLM generatif (ChatGPT/Claude API) untuk ini, kamu membayar mesin penulis esai untuk menjawab
"ya/tidak": lambat (generate token demi token), mahal (bayar per token), output harus di-parse, bisa menjawab
di luar pilihan, dan angka "confidence" yang ia tulis hanyalah teks yang terdengar masuk akal.

Laya dibuat khusus untuk keputusan kecil ini.

## 2. Non-autoregressive & "System 1"

**Autoregressive** = model menghasilkan output satu token, lalu token berikutnya berdasarkan token sebelumnya,
berulang-ulang (cara kerja LLM generatif). **Non-autoregressive** = sekali jalan (**satu forward pass**) langsung
keluar hasilnya.

Laya membaca *state* (teks kamu) + pertanyaan + daftar opsi sebagai satu urutan token, lalu langsung
mengeluarkan **distribusi probabilitas atas opsi-opsi itu**. Tidak ada teks yang di-generate, jadi:
- tidak ada yang perlu di-parse,
- tidak mungkin menjawab di luar opsi (tidak bisa "halusinasi" label baru),
- cepat: ±33 ms/pertanyaan di GPU T4 (di CPU VPS kita: ratusan ms sampai detik per request — ukur sendiri).

Istilah **System 1** dipinjam dari Kahneman: *System 1* = cepat, intuitif, otomatis; *System 2* = lambat,
deliberatif. LLM yang "berpikir panjang" itu System 2. Laya sengaja jadi System 1: keputusan cepat yang
**tahu kapan dirinya ragu**, lalu menyerahkan kasus sulit ke System 2 (manusia, atau LLM).

Di balik layar Laya adalah **encoder** (ModernBERT / mmBERT), keluarga model yang sama dengan BERT:
membaca seluruh teks sekaligus, cocok untuk memahami/menilai, bukan untuk menulis.

## 3. Tiga tipe pertanyaan

| Tipe (wire) | LayaPHP | Output utama | Contoh |
|---|---|---|---|
| `choice` | `Question::choice($instr, ['label' => 'deskripsi', ...])` | `choice` (label pemenang) + `probabilities` semua label | departemen tiket, sentimen |
| `score` | `Question::score($instr, ['rendah', 'sedang', 'tinggi'])` | `score` (float, *expected level*) + `level()` (argmax) + `label()` | urgensi, tingkat frustrasi |
| `noul` (yes/no) | `Question::yesNo($instr, yes: ..., no: ...)` | `probability` = P(yes); `yes($threshold)` | spam?, ancaman churn? |

Hal yang sering tertukar pada `score`: `score = 1.74` artinya *rata-rata tertimbang* level (antara "soon"=1 dan
"blocking"=2), sedangkan `level()` = level dengan probabilitas tertinggi. Untuk **mengurutkan** (mis. lead mana
dulu yang di-follow up) pakai `score`; untuk **mengelompokkan** pakai `level()`.

Deskripsi opsi itu penting: model memilih berdasarkan **deskripsi**, bukan cuma nama label.

## 4. Probabilitas, `confidence`, dan `answerConfidence`

Setiap jawaban punya dua angka keyakinan — dan ini paling sering disalahpahami:

| Field | Artinya | Dipakai untuk |
|---|---|---|
| `confidence` | Seberapa "terkumpul" distribusinya (1 − entropi ternormalisasi untuk choice/score) | Diagnostik. Nilainya bergeser tergantung jumlah opsi. |
| `answerConfidence` (`answer_confidence`) | Probabilitas jawaban yang dilaporkan (≈ `max(p)`), besaran yang dikalibrasi, sebanding antar tipe pertanyaan | **Gating**: kapan otomatis, kapan ke manusia |

Aturan praktis: **threshold selalu pakai `answerConfidence`.** Threshold dari sistem lain (mis. Jev) tidak bisa
dipindahkan begitu saja karena definisi `confidence`-nya beda.

## 5. Kalibrasi — kenapa ini inti dari semuanya

Model **terkalibrasi** kalau dari semua jawaban yang ia beri confidence 0.8, kira-kira 80% memang benar.
Kalau ternyata cuma 60% yang benar, modelnya **over-confident**.

Laya dilatih dengan RLCD (reinforcement learning terhadap *strictly proper scoring rules*), metode yang
memberi "hadiah" pada probabilitas yang jujur. **Tapi**, kata README-nya sendiri:

> Both checkpoints are over-confident as shipped. Refitting one temperature per (question type, option count)
> on held-out data moves mean ECE 0.466 → 0.081 (`laya`) and 0.314 → 0.106 (`laya-multilingual`).

Artinya: angka confidence bawaan **belum bisa langsung dipercaya**. Kamu perlu mengukurnya di data sendiri
(Fase 3) dan, kalau perlu, mengkalibrasi ulang (temperature scaling, Fase 6).

- **ECE (Expected Calibration Error)**: kelompokkan prediksi per rentang confidence (0.5–0.6, 0.6–0.7, …),
  bandingkan rata-rata confidence vs akurasi asli di tiap kelompok, lalu rata-ratakan selisihnya. 0 = sempurna.
- **Temperature scaling**: membagi skor mentah model dengan angka T sebelum softmax. T > 1 membuat
  probabilitas lebih "rendah hati". Satu angka, di-fit di data validasi.

> "A threshold is a policy you choose from measured accuracy at that coverage on your data, not a property of
> the model. … Confidence orders decisions; it does not establish that a decision is correct."

## 6. Abstention & human-in-the-loop

**Abstention** = model boleh "tidak menjawab" kalau ragu. Di sistem nyata artinya: kasus ragu diserahkan ke
manusia. Ini trade-off antara:

- **Coverage**: berapa persen kasus yang diputuskan otomatis.
- **Akurasi pada yang diputuskan otomatis** (atau sebaliknya, *risk*).

Threshold tinggi → coverage turun, akurasi naik. Contoh di Project 1: `RoutingPolicy` hanya *reject* otomatis
kalau P(spam) ≥ 0.9 **dan** `answerConfidence` ≥ 0.7; yang abu-abu masuk `manual-review`.

Di LayaPHP ada dua cara:
- manual: bandingkan `$answer->answerConfidence` dengan threshold (Project 1);
- deklaratif: `#[Ask('…', minConfidence: 0.7)] public ?Department $department` → nilainya `null` kalau ragu (Project 2).

## 7. Router & checkpoint

| Checkpoint | Encoder | Bahasa | Konteks |
|---|---|---|---|
| `english` | ModernBERT-large (421M) | English | 512 token |
| `multilingual` | mmBERT-base (322M) | 100+ bahasa, termasuk Indonesia | 1.024 token (bisa sampai 8.192) |
| `typed-decisions` | ModernBERT-large, fine-tuned | English, alur keputusan bisnis | 1.024 token |

Kalau `model` tidak diisi, **router** memilih berdasarkan deteksi aksara & bahasa (<1 ms). Teks Latin yang terlalu
pendek ("Ok", "Mantap!") tidak punya cukup bukti bahasa → jatuh ke default (`english`). Untuk trafik yang mayoritas
Bahasa Indonesia, coba `LAYA_DEFAULT_MODEL=multilingual` di server, atau pin `model: Model::Multilingual` di PHP —
lalu **ukur** bedanya.

Token budget: teks yang terlalu panjang dipotong (`$result->truncated === true`). Naikkan dengan `maxLen`.

## 8. Self-host vs API vs LLM

| | LLM generatif (API) | Jev (TypeSafe, hosted) | Laya (self-host) |
|---|---|---|---|
| Output | teks bebas | typed + probabilitas | typed + probabilitas |
| Biaya | per token | per call | compute sendiri (VPS), call tak terbatas |
| Data | keluar ke vendor | keluar ke vendor | tetap di server kamu |
| Latensi | detik | cepat | cepat di GPU; di CPU ratusan ms–detik |
| Fleksibilitas | sangat tinggi (bisa menalar, menulis) | keputusan saja | keputusan saja, bisa di-fine-tune |
| Operasional | nol | nol | kamu yang maintain server & model |

`laya-serve` memakai protokol yang sama dengan Jev (`POST /v1/systemone`), jadi client bisa pindah antar keduanya.

**Kapan jangan pakai Laya:** butuh teks yang di-generate, butuh penalaran banyak langkah, pilihan > ±50 opsi
dalam satu pertanyaan, atau keputusan yang bergantung pada negasi halus (lihat bagian 9).

## 9. Batasan jujur (dari README Laya) — wajib diingat

1. **Over-confident bawaan pabrik** (bagian 5). Ukur dulu, baru percaya.
2. **Zero-shot belum tentu bagus.** Di benchmark *typed-decisions*, checkpoint dasar cuma 0,362 (random 0,318,
   tebak kelas mayoritas 0,461); setelah fine-tune 0,766. *"Laya is a fast base to specialise, not a zero-shot
   decision engine."* → Fase 3 (ukur) dan Fase 6 (fine-tune) bukan opsional kalau mau dipakai serius.
3. **Hindari label `yes`/`no`/`true`/`false` di pertanyaan `choice`.** Model bisa mengikuti kata labelnya, bukan
   deskripsinya. Pakai label semantik (`billing`) atau netral (`A`/`B`). Untuk ya/tidak, pakai `Question::yesNo`.
4. **Negasi rawan.** "Saya *tidak* mau membatalkan akun" bisa terbaca sebagai "batalkan akun". Selalu uji kalimat negatif.
5. **Bias posisi opsi.** Opsi yang posisinya di depan cenderung lebih disukai. Server mendukung `option_order`
   untuk merata-ratakan efek ini (LayaPHP 1.2 belum mengekspos ini — peluang kontribusi open source!).
6. **Banyak opsi = tiap opsi dapat sedikit token.** Di atas ±20 opsi berdeskripsi, deskripsi mulai terpotong.

---

## Kuis self-check

Jawab dulu tanpa melihat, baru buka jawabannya.

1. Kenapa Laya tidak bisa "berhalusinasi" label baru, sedangkan LLM generatif bisa?
2. Apa beda `score = 1.74` dan `level() = 2` pada pertanyaan urgensi 3 level? Mana yang dipakai untuk mengurutkan lead?
3. Untuk menentukan "otomatis atau ke manusia", pakai `confidence` atau `answerConfidence`? Kenapa?
4. Model bilang confidence 0.9 untuk 100 jawaban, tapi cuma 70 yang benar. Sebut istilahnya, dan apa yang harus dilakukan.
5. Threshold 0.7 di artikel Laravel News — angka itu didapat dari mana, dan apa yang seharusnya kamu lakukan?
6. Kenapa review "Ok" bisa diproses checkpoint `english` walaupun datasetnya bertanda `id`?
7. Kamu bikin `Question::choice('Refund?', ['yes', 'no'])`. Apa masalahnya, dan bagaimana sebaiknya?
8. Sistem "tandai semua review sebagai bersih" dapat akurasi 85% di deteksi abuse. Apa yang salah dengan metrik akurasi di sini?
9. Sebut 2 alasan memilih Laya self-host dibanding LLM API, dan 2 alasan sebaliknya.
10. Kenapa `predictMany()` (batch) jauh lebih efisien daripada 40× `predict()` di `laya-serve` CPU?

<details>
<summary>Jawaban</summary>

1. Laya tidak menghasilkan teks; ia hanya memberi skor/probabilitas pada opsi yang kamu kirim. Outputnya selalu salah satu opsi itu.
2. `score` = level yang diharapkan (rata-rata tertimbang probabilitas), `level()` = argmax. Untuk mengurutkan pakai `score` karena membedakan dua lead yang sama-sama level 2.
3. `answerConfidence`: besaran yang dikalibrasi dan sebanding antar tipe pertanyaan/jumlah opsi. `confidence` bergeser tergantung jumlah opsi.
4. Over-confident (miskalibrasi). Ukur ECE di data berlabel, pilih threshold dari data (bukan feeling), dan/atau kalibrasi ulang dengan temperature scaling.
5. Dipilih penulis sebagai contoh. Seharusnya: bandingkan keputusan model dengan keputusan moderator di data sendiri, lalu pilih threshold dari kurva coverage-vs-akurasi (Fase 3).
6. Teks Latin yang sangat pendek tidak cukup jadi bukti bahasa, jadi router jatuh ke default (`english`). Atasi dengan `LAYA_DEFAULT_MODEL=multilingual` atau pin model.
7. Label `yes`/`no` di `choice` bisa membuat model mengikuti kata labelnya. Pakai `Question::yesNo('Does the user ask for a refund?')`.
8. Datanya tidak seimbang (mayoritas bukan abuse), jadi tebakan bodoh pun akurasinya tinggi. Pakai precision/recall per kelas (Fase 3).
9. Laya: biaya per call nol, data tidak keluar, output typed + probabilitas, cepat. LLM: bisa menalar/menulis, tidak perlu maintain server, lebih kuat zero-shot di tugas baru.
10. Server menjalankan satu forward pass dalam satu waktu; request terpisah antre satu per satu. Batch berbagi forward pass sehingga overhead per review jauh lebih kecil.

</details>
