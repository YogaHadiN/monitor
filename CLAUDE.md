# CLAUDE.md — monitor

Panduan konteks untuk Claude Code. File ini di-commit ke git agar konteks konsisten lintas mesin (laptop ↔ server).

## Gambaran proyek
- **monitor** = turunan ramping dari SIMRS `atika`, fokus pada **antrian, reservasi, dan bot** (WhatsApp/Telegram/Sunat).
- Laravel `^10.0`, PHP `^8.2`, database MySQL (`DB_CONNECTION=mysql`).
- **Multi-tenant**: model memakai trait `App\Traits\BelongsToTenant` (scoping per `tenant_id`).

## Skala kode (per 2026-09-26)
- Models: **85** (`app/Models/`, plus subfolder `Classes/`)
- Controllers: **37** (`app/Http/Controllers/`)
- Migrations: **13** (`database/migrations/`)
- Console Commands: **8** (`app/Console/Commands/`)
- Subfolder `app/`: Console, Events, Exceptions, Helpers, Http, Jobs, Listeners, Models, Providers, Scopes, Services (termasuk `SunatBot/`), Traits

## Domain utama
Antrian (`Antrian`, `AntrianPoli`, `AntrianApotek`, `AntrianFarmasi`), reservasi (`ReservasiOnline`, `Reservation`, `DentistReservation`, `WaitlistReservation`, `SchedulledReservation`), bot & pesan (`WhatsappBot`, `BotSession`, `BotIntent`, `TelegramUser`, `Message`), integrasi BPJS.

## Konvensi
- Nomor antrian di-generate lewat `App\Services\AntrianNumberService` (hook `creating` model `Antrian`) — sama seperti atika.
- Logika bot Sunat ada di `app/Services/SunatBot/`.

## Hubungan dengan repo `atika`
Berbagi ~79 model dengan `atika` (mis. `Antrian`, `Pasien`, `Poli`, `Ruangan`). Model unik monitor: `AntrianFarmasi`, `BotPendingBuffer`, `Contact`, `DentistReservation`, `RegisteringConfirmation`, `WebRegistration`. Kode model bersama **sudah drift** dari atika — jangan asumsikan identik.

## Git / sinkronisasi lintas mesin
- Remote: `https://github.com/YogaHadiN/monitor.git` (HTTPS).
- `.gitignore` meng-ignore `.claude/` — jadi folder memory tidak ter-commit, tapi `CLAUDE.md` di root **ikut** ter-commit.
- Alur lintas mesin: commit → push → `git pull` di mesin lain.

## Catatan kerja berjalan
<!-- Tulis di sini apa yang sedang dikerjakan agar bisa dilanjutkan di mesin lain. -->
- (belum ada)
