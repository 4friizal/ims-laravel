# IMS — Inventory Management System

Restaurant inventory management built with **Laravel 13** + **Livewire 4** + **Tailwind CSS v4**.

Every page is a full-page Livewire component — there are no controller-rendered views for app pages.

| Area | Route | Access |
| --- | --- | --- |
| Dashboard | `/dashboard` | admin, operator |
| User management | `/user-management` | admin |
| Categories | `/inventory/categories` | admin, operator |
| Raw materials | `/inventory/raw-materials` | admin, operator |
| Stock | `/inventory/stock` | admin, operator |
| Menus | `/inventory/menus` | admin, operator |
| Stock prediction | `/inventory/prediction` | admin, operator |

---

## Running with Docker (recommended)

Requires Docker with the Compose plugin. Nothing else — PHP, Composer and Bun all live inside the image.

```bash
docker compose up --build
```

That's it. The first boot copies `.env`, installs Composer and Bun dependencies, generates the app key, waits for MySQL, and runs migrations. Subsequent boots skip whatever is already done, so `docker compose up` is always safe to re-run.

Then open **http://localhost:8000**.

### What gets started

| Service | Purpose | Port |
| --- | --- | --- |
| `app` | `php artisan serve` — also the only service that runs migrations | 8000 |
| `queue` | `php artisan queue:listen` | — |
| `vite` | Vite dev server with HMR | 5173 |
| `db` | MySQL 8.0 (`ims_restaurant`, user `root`, password `1234`) | 3306 |

### Hot reload

Already running — the `vite` service provides it, no extra step. With `docker compose up`:

| You edit | What happens |
| --- | --- |
| `resources/css/**` | Style swapped in place, no page reload, scroll position kept |
| `resources/js/**` | Module hot-swapped |
| `resources/views/**`, `routes/**` | Full page reload (via the plugin's `refresh: true`) |

The relevant config is in `vite.config.js`:

- `server.host: '0.0.0.0'` — makes the dev server reachable from outside the container.
- `server.hmr.host: 'localhost'` — the address the **browser** connects to. This one matters: `laravel-vite-plugin` otherwise writes the bind address into `public/hot`, and the browser is told to fetch assets from `http://0.0.0.0:5173`, which it cannot reach. Symptom is an unstyled page and a dead HMR socket.

If edits aren't detected, the container isn't receiving filesystem events. Linux bind mounts forward inotify natively, but Docker Desktop on macOS/Windows does not — switch to polling:

```bash
VITE_USE_POLLING=true docker compose up
```

### Seed the database

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Seeded logins (password is `password` for both):

- `admin@example.com` — Admin
- `operator@example.com` — Operator

### Everyday commands

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate
docker compose exec app ./vendor/bin/pint         # PHP code style
docker compose exec app bun run format            # Blade / JS / CSS formatting

docker compose logs -f app                        # tail a service
docker compose down                               # stop
docker compose down -v                            # stop and wipe the database volume
```

### Running tests

`phpunit.xml` points the test suite at a **separate** `ims_restaurant_test` database, which is not created automatically. Create it once:

```bash
docker compose exec db mysql -uroot -p1234 -e "CREATE DATABASE IF NOT EXISTS ims_restaurant_test"
```

Inside the container the database host is `db`, not `127.0.0.1`, so override it when running the suite:

```bash
docker compose exec -e DB_HOST=db app composer run test
```

### Notes

- **Code is bind-mounted, not baked in.** Edits on the host are live in the container; you only need `--build` after changing something under `docker/`.
- **File ownership** — the container user is built to match uid/gid 1000. If your host user differs, run `UID=$(id -u) GID=$(id -g) docker compose up --build`.
- **`vendor/` and `node_modules/` are shared with the host.** If you have previously run the app natively against a different PHP version, `rm -rf vendor` once and let the container reinstall.
- **Database credentials come from Compose, not `.env`.** Laravel does not override real environment variables, so your `.env` can stay pointed at `127.0.0.1` for native runs while containers use the `db` host.

  This relies on `AppServiceProvider::register()` adding the `DB_*` variables to `ServeCommand::$passthroughVariables`. `php artisan serve` forwards only a whitelist of environment variables to the PHP subprocess that actually serves requests — without that registration the Compose values are silently dropped and the app falls back to `.env`, producing `SQLSTATE[HY000] [2002] Connection refused ... Host: 127.0.0.1`. If you ever see that error inside Docker, check that block first.
- **Xdebug** is installed but off. Enable it per-command: `docker compose exec -e XDEBUG_MODE=debug app php artisan ...`

---

## Running natively (without Docker)

Requires PHP 8.3+, Composer and [Bun](https://bun.sh).

```bash
composer run setup     # install deps, copy .env, generate key, migrate, build assets
composer run dev       # server + queue + log tail + Vite HMR, all in one
```

Defaults to SQLite at `database/database.sqlite` — no database server needed.

To use MySQL instead, start the `db` service (`docker compose up -d db`) and set in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ims_restaurant
DB_USERNAME=root
DB_PASSWORD=1234
```

### Native commands

```bash
php artisan migrate:fresh --seed     # reset database and seed users + inventory
composer run test                    # test suite
php artisan test tests/Feature/ExampleTest.php
./vendor/bin/pint                    # PHP code style
bun run format                       # Blade / JS / CSS formatting
```

---

## Project layout

```
app/
  Enums/Role.php              admin | operator, with permission methods
  Http/Middleware/            RoleMiddleware — used as role:admin or role:admin,operator
  Livewire/                   one full-page component per route
docker/
  Dockerfile.dev              dev toolchain image (PHP 8.4 + Composer + Bun)
  entrypoint.dev.sh           idempotent boot: deps, key, migrations
  php/php.dev.ini             dev PHP settings
resources/views/layouts/
  auth.blade.php              unauthenticated pages
  app.blade.php               authenticated pages, with navbar
```

Roles are a backed enum cast on the `User` model; permission logic lives on the enum itself (`canManageUsers()`, `canManageInventory()`). Routes are protected by `RoleMiddleware` in `routes/web.php`.
