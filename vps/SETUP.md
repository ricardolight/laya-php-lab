# Setup VPS untuk LayaPHP

Panduan sekali jalan supaya VPS (8 vCPU, 24 GB RAM, tanpa GPU) bisa menjalankan `laya-serve` dan semua
project di `laya-php/`. Alurnya: **kode ditulis & di-push dari Claude Code (cloud) → di VPS tinggal `git pull`
→ jalankan dengan model asli.**

> Ganti `<user>`, `<vps-ip>`, dan `<ssh-port>` dengan punyamu. Jangan commit IP, user, atau key ke repo.

## 1. Cek spek & OS

```bash
nproc            # jumlah vCPU (harusnya 8)
free -h          # RAM (harusnya ~24 GB)
df -h /          # disk kosong, butuh ±15 GB untuk image Docker + model
cat /etc/os-release
```

Panduan ini untuk **Ubuntu 22.04/24.04** (Debian mirip, bedanya di repo PHP — lihat catatan di langkah 3).

## 2. Install Docker

```bash
sudo apt update && sudo apt install -y ca-certificates curl git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER      # supaya docker bisa tanpa sudo
newgrp docker                      # atau logout-login
docker compose version             # pastikan plugin compose ada
```

## 3. Install PHP 8.4 + Composer

```bash
sudo add-apt-repository -y ppa:ondrej/php     # Debian: pakai repo packages.sury.org/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip unzip
php -v                                         # harus 8.4.x

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

Nanti di Fase 4–5 (Laravel) tambah: `php8.4-sqlite3 php8.4-redis php8.4-fpm nginx redis-server supervisor`.

## 4. Clone repo (private) pakai deploy key

Repo `ai-roadmap-lab` private, jadi VPS butuh akses baca:

```bash
ssh-keygen -t ed25519 -C "laya-vps" -f ~/.ssh/ai-roadmap-lab -N ""
cat ~/.ssh/ai-roadmap-lab.pub
```

Copy isi `.pub` → GitHub repo **Settings → Deploy keys → Add deploy key** (centang *read-only*, cukup).

```bash
cat >> ~/.ssh/config <<'EOF'
Host github-ai-roadmap-lab
    HostName github.com
    User git
    IdentityFile ~/.ssh/ai-roadmap-lab
EOF

git clone git@github-ai-roadmap-lab:ricardolight/ai-roadmap-lab.git
cd ai-roadmap-lab
git checkout claude/ecstatic-ramanujan-rjpq7y   # branch kerja sampai di-merge ke main
```

## 5. Jalankan `laya-serve`

```bash
cd laya-php/vps
cp .env.example .env
sed -i "s/^LAYA_API_KEY=.*/LAYA_API_KEY=$(openssl rand -hex 32)/" .env

docker compose up -d --wait        # build image + download 3 checkpoint, 10–20 menit pertama kali
docker compose ps                  # STATUS harus "healthy"
docker compose logs -f laya-serve  # pantau progres download/load (Ctrl+C untuk keluar)
```

Start berikutnya cuma butuh beberapa detik–menit (model sudah di volume `model-cache`). Setelah start pertama
sukses, boleh set `HF_HUB_OFFLINE=1` di `.env` supaya server tidak bergantung ke huggingface.co lagi.

## 6. Tes server langsung (tanpa PHP)

Pahami protokolnya dulu sebelum pakai SDK — ini yang dikirim LayaPHP di balik layar.

```bash
set -a; source .env; set +a        # load LAYA_API_KEY & LAYA_URL ke shell

curl -s $LAYA_URL/health | python3 -m json.tool

curl -s $LAYA_URL/v1/systemone \
  -H "Authorization: Bearer $LAYA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "Barangnya bagus, tapi pengiriman lama banget.",
    "questions": {
      "spam":      {"type": "noul", "instructions": "Is this review spam or an advertisement?"},
      "sentiment": {"type": "choice", "instructions": "Overall sentiment towards the product?",
                    "criteria": {"positive": "mostly praise", "neutral": "mixed", "negative": "mostly criticism"}},
      "urgency":   {"type": "score", "instructions": "How urgently should support reply?",
                    "criteria": ["not urgent", "soon", "blocking"]}
    }
  }' | python3 -m json.tool
```

Yang perlu diperhatikan di response:
- `answers.spam.noul` = P(yes) untuk pertanyaan yes/no (di PHP: `->probability`).
- `answers.*.answer_confidence` = confidence terkalibrasi → ini yang dipakai untuk gating (di PHP: `->answerConfidence`).
- `answers.sentiment.probabilities` = distribusi semua opsi, bukan cuma pemenangnya.
- `answers.urgency.score` = level *expected* (float, mis. 1.4), bukan argmax.
- `routing` = checkpoint yang dipilih router (english/multilingual) + alasannya. Coba kirim teks bahasa Indonesia vs English.
- Batch: `POST /v1/systemone/batch` dengan `"states": [...]` (maks 64 per request).

## 7. Jalankan Project 1

```bash
cd ../01-review-moderator
composer install
set -a; source ../vps/.env; set +a
php bin/moderate ../datasets/reviews.csv
php bin/moderate ../datasets/reviews.csv --model=multilingual
php bin/moderate ../datasets/reviews.csv --single      # bandingkan waktu per review vs batch
```

Catat hasilnya di tabel "Results on the real model" di `01-review-moderator/README.md`.

## 8. Workflow harian

```bash
cd ~/ai-roadmap-lab
git pull
# kalau composer.lock sebuah project berubah:
(cd laya-php/<project> && composer install)
# kalau laya-php/vps/compose.yaml berubah:
(cd laya-php/vps && docker compose up -d --build --wait)
```

## Akses dari laptop (opsional)

Port 8000 sengaja **hanya** di `127.0.0.1` — jangan dibuka di firewall. Kalau mau akses dari laptop, pakai SSH tunnel:

```bash
ssh -p <ssh-port> -L 8000:127.0.0.1:8000 <user>@<vps-ip>
# selama tunnel terbuka, http://localhost:8000 di laptop = laya-serve di VPS
```

## Ekspektasi performa (CPU)

- Angka ~33 ms/pertanyaan di README Laya itu **GPU (T4)**. Di CPU, satu request bisa ratusan ms sampai beberapa detik.
- `laya-serve` menjalankan **satu forward pass dalam satu waktu** → request paralel antre. Karena itu batch
  (`predictMany`) jauh lebih efisien daripada banyak request kecil. Ukur sendiri di Fase 1 & 5.
- Pantau resource: `docker stats` (RAM tiga checkpoint kira-kira beberapa GB, masih jauh di bawah 24 GB).

## Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `docker compose ps` lama di `starting` | Model masih di-download/load. Cek `docker compose logs -f laya-serve`. |
| PHP: `Could not reach laya-serve` | Container belum healthy, atau `LAYA_URL` salah. Cek `curl $LAYA_URL/health`. |
| HTTP 401 / `AuthenticationException` | `LAYA_API_KEY` di shell beda dengan di `.env` server. Ulangi `source .env`. |
| HTTP 503 / `ServerBusyException` | Terlalu banyak request sekaligus. Pakai batch, atau naikkan `LAYA_MAX_CONCURRENT`. |
| HTTP 422 / `ValidationException` | Pertanyaan/opsi tidak valid atau `max_len` melebihi batas server. |
| Hasil berubah setelah rebuild | Pastikan `LAYA_REVISION=reviewed`; commit di `compose.yaml` jangan diubah tanpa evaluasi ulang. |
