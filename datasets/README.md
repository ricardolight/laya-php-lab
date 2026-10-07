# Datasets

Data berlabel ("gold") yang dipakai bersama oleh project-project LayaPHP. Gold label = jawaban yang
dianggap benar menurut manusia, dipakai untuk mengukur seberapa sering Laya setuju (Fase 1) dan untuk
evaluasi + kalibrasi penuh (Fase 3, project `03-laya-eval`).

## `reviews.csv`

40 review produk, campuran English (`en`) dan Bahasa Indonesia (`id`), termasuk slang dan code-switching.

| Kolom | Isi |
|---|---|
| `id` | ID unik (`r01`…`r40`) |
| `lang` | `en` / `id` |
| `text` | isi review |
| `spam` | `yes` kalau iklan, promosi situs/jasa lain, atau tidak membahas produk |
| `abuse` | `yes` kalau menghina/menyerang orang atau kelompok (bukan sekadar kritik keras) |
| `sentiment` | `positive` / `neutral` / `negative` terhadap produk; **kosong** untuk spam (tidak relevan) |

Aturan pelabelan yang sengaja dibuat "menjebak", supaya kelihatan di mana model ragu:
- **Kritik keras ≠ abuse.** `r18` ("worst I have ever bought") dan `r36` ("produk ini sampah") = `abuse: no`.
  Yang dihina produknya, bukan orangnya.
- **Campuran = neutral**, kecuali satu sisi jelas dominan (`r10`, `r37` = positive).
- **Sarkasme** (`r08`) = negative walau kata-katanya positif.
- **Spam terselubung** (`r14`) dimulai seperti review asli.
- **Super pendek** (`r09`, `r27`, `r38`): sedikit konteks, cek apakah confidence model ikut rendah.

Kalau menambah data: pertahankan format kolom, isi `sentiment` kosong untuk spam, dan tulis alasannya
di sini kalau labelnya bisa diperdebatkan.
