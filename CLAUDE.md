# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

An Inventory Management System (IMS) built with Laravel 13 + Livewire 4. All interactive pages are Livewire full-page components — there are no traditional controller-rendered views for app pages.

## Common Commands

```bash
# Start full dev environment (server + queue + log tail + vite HMR)
composer run dev

# Run tests
composer run test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# PHP code style (Laravel Pint)
./vendor/bin/pint

# Blade/JS/CSS formatting (Prettier)
bun run format
bun run format:check

# Fresh migrate + seed (creates admin/operator users)
php artisan migrate:fresh --seed
```

## Database Setup

Default: SQLite (`database/database.sqlite`). No additional setup needed for local dev.

For MySQL: start Docker, then update `.env` to use `DB_CONNECTION=mysql` with credentials matching `docker-compose.yml` (`ims_restaurant` / `root` / `1234`).

```bash
docker compose up -d
```

Seeded users (all password: `password`):
- `admin@example.com` — Admin
- `operator@example.com` — Operator

## Architecture

### Role-Based Access Control

Roles are defined as a PHP 8.3 backed enum in `app/Enums/Role.php` (`admin`, `operator`). The `User` model casts the `role` column to this enum.

Route protection uses `RoleMiddleware` (`app/Http/Middleware/RoleMiddleware.php`) via `role:admin` or `role:admin,operator` syntax in `routes/web.php`. Permission logic lives on the enum itself (`canManageUsers()`, `canManageInventory()`).

### Livewire Component Pattern

Every page is a full-page Livewire component placed in `app/Livewire/`. Two layouts exist:
- `layouts.auth` — unauthenticated pages (login)
- `layouts.app` — authenticated pages with navbar

Apply layout via the `#[Layout('layouts.app')]` attribute on the component class.

### Frontend Stack

Tailwind CSS v4 (via `@tailwindcss/vite` plugin) + Vite. Fonts loaded via Bunny Fonts (Instrument Sans). No separate `tailwind.config.js` — configuration is CSS-first.
