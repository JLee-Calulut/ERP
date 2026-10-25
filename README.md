# ERP Portal — Day 1 Foundation

A full-stack ERP (Enterprise Resource Planning) system being rebuilt from scratch over seven days. This README documents the **Day 1** state: a working Laravel 12 + Vue 3 + Inertia 2 + Tailwind 3 skeleton connected to MySQL and backed up on GitHub.

| | |
| --- | --- |
| Status | Day 1 of 7 — Foundation |
| Git checkpoint | `chore: initialize ERP project foundation` |
| Sign-in URL | not yet (authentication arrives Day 2) |

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 12 on PHP 8.2+ |
| Frontend | Vue 3 (Composition API) + Inertia.js 2 |
| Styling | Tailwind CSS 3 |
| Build | Vite 6 (`laravel-vite-plugin` + `@vitejs/plugin-vue`) |
| Database | MySQL + Eloquent ORM |
| Tests | PHPUnit (Laravel feature tests) |

## Prerequisites (check before starting)

Open PowerShell and run each command. Expected results shown.

```powershell
git --version        # git version 2.x.x
php -v               # PHP 8.2.x
composer --version   # Composer version 2.x.x
node -v              # v20.11+ (Vite 6 requirement)
npm -v               # ships with Node
mysql --version      # mysql Ver 8.x (or MariaDB 10.5+)
```

If a command says `not recognized`, that tool is missing — install it, then re-check.

## Setup (one time, in order)

```powershell
# 1. Create the project
cd C:\code
composer create-project laravel/laravel erp "^12.0"
cd erp

# 2. Create the database in MySQL
mysql -u root -p
#   at the MySQL prompt:
CREATE DATABASE erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# 3. Configure .env (project root): set these lines to your values
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=erp
#    DB_USERNAME=root
#    DB_PASSWORD=your_mysql_password

# 4. Run the initial migrations (creates users, sessions, cache, jobs tables)
php artisan migrate

# 5. Install the frontend stack
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
npm install
npm install vue @inertiajs/vue3 @vitejs/plugin-vue tailwindcss@^3.4 postcss autoprefixer @tailwindcss/forms

# 6. Wire the frontend files (see "Files created on Day 1" below)
#    vite.config.js, tailwind.config.js, resources/css/app.css,
#    resources/js/app.js, resources/views/app.blade.php,
#    resources/js/Pages/Welcome.vue, bootstrap/app.php (inertia alias)

# 7. Point the / route at the Vue page
#    In routes/web.php replace the default welcome view with:
#    Inertia::render('Welcome')
```

## Run the app (two terminals — keep both open)

```powershell
# Terminal 1: the Laravel web server (backend)
php artisan serve

# Terminal 2: Vite dev server (frontend, hot reload)
npm run dev
```

Open http://127.0.0.1:8000 — you should see **"Welcome to the ERP Portal"** centered on a light-gray background.

## Files created on Day 1

| File | Purpose |
| --- | --- |
| `vite.config.js` | Bundles the frontend; registers the Vue plugin and `@` alias |
| `tailwind.config.js` + `postcss.config.js` | Tailwind CSS setup and content scan paths |
| `resources/css/app.css` | Tailwind directives (`@tailwind base; components; utilities`) |
| `resources/js/app.js` | Boots Vue + Inertia; maps page names to `resources/js/Pages/*.vue` |
| `resources/views/app.blade.php` | The single HTML shell that hosts the whole app |
| `resources/js/Pages/Welcome.vue` | The first page (a Vue component) |
| `routes/web.php` | Maps `/` to the Vue Welcome page |
| `bootstrap/app.php` | Middleware config; registers the `inertia` alias |
| `.env` | Your secrets (database password) — NEVER commit this |
| `.gitignore` | Protects `.env`, `vendor/`, `node_modules/`, `public/build` from Git |

## Day 1 verification checklist

- [ ] `php artisan migrate` completes ("Database migrations completed successfully")
- [ ] `php artisan test` — default tests pass (green)
- [ ] http://127.0.0.1:8000 shows "Welcome to the ERP Portal"
- [ ] `git status` lists project files but NOT `.env`, `vendor/`, or `node_modules/`
- [ ] First commit created: `chore: initialize ERP project foundation`
- [ ] Pushed to GitHub (`git push -u origin main`) and visible in the Commits page

## Troubleshooting

| Symptom | Cause & fix |
| --- | --- |
| Page shows the default Laravel "welcome" screen | `routes/web.php` still returns `view('welcome')` — change it to `Inertia::render('Welcome')`, save, hard-refresh (Ctrl+F5) |
| Vue page loads but unstyled / asset 404 | `npm run dev` is not running — start it in the second terminal |
| `fatal: not a git repository` | Project not initialized — run `git init` inside `C:\code\erp` |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong credentials in `.env` — check `DB_USERNAME` / `DB_PASSWORD` |
| Port 8000 already in use | `php artisan serve --port=8001` and open that port instead |

## Next step

Day 2 — authentication and roles: login at `/erp/login`, Admin/Manager/Staff roles, permissions, the shared ERP layout with sidebar, and the first dashboard.
