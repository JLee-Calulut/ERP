# ERP Portal

A full-stack ERP (Enterprise Resource Planning) system rebuilt over seven days. This README documents **Days 1–7**: the Laravel 12 foundation, ERP authentication and role-based access, inventory, sales and purchasing, plus the double-entry accounting ledger and snapshot reporting suite for chart of accounts, journal entries, trial balance, general ledger, financial statements and CSV exports.

| | |
| --- | --- |
| Status | Day 7 of 7 — Reporting |
| Git baseline | Day 6 accounting checkpoint (`feat: Implement accounting and ledger`); Day 7 reporting files are in the current working tree |
| Sign-in URL | http://127.0.0.1:8000/erp/login — `admin@erp.test` / `password` |
| Test suite | 222 test methods and 1,514 assertions; run with `composer test` |

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 12 on PHP 8.2+ |
| Frontend | Vue 3.5 (Composition API) + Inertia.js 3 |
| Styling | Tailwind CSS 3 |
| Build | Vite 7 (`laravel-vite-plugin` + `@vitejs/plugin-vue`) |
| Database | MySQL + Eloquent ORM (tests use in-memory SQLite) |
| Tests | PHPUnit (Laravel feature tests) |

## Prerequisites (check before starting)

Open PowerShell and run each command. Expected results shown.

```powershell
git --version        # git version 2.x.x
php -v               # PHP 8.2.x
composer --version   # Composer version 2.x.x
node -v              # Node 20.19+ or 22.12+ (Vite 7 requirement)
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

## Day 1 — Foundation

### Files created on Day 1

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

### Day 1 verification checklist

- [ ] `php artisan migrate` completes ("Database migrations completed successfully")
- [ ] `php artisan test` — default tests pass (green)
- [ ] http://127.0.0.1:8000 shows "Welcome to the ERP Portal"
- [ ] `git status` lists project files but NOT `.env`, `vendor/`, or `node_modules/`
- [ ] First commit created: `chore: initialize ERP project foundation`
- [ ] Pushed to GitHub (`git push -u origin main`) and visible in the Commits page

## Day 2 — Authentication & roles

Day 2 turns the skeleton into an ERP you can sign in to. Everything below is live in this checkpoint.

### What Day 2 adds

- A dedicated ERP sign-in at `/erp/login`, separate from the public `/login` and `/register` pages
- Three core roles (Admin, Manager, Staff) with 13 permissions seeded into MySQL
- Middleware, policies and shared Inertia props so every screen knows who you are and what you may do
- A dashboard with four KPIs and permission-aware quick actions
- Full CRUD for roles and users, with the guards that keep an ERP safe: core roles and roles in use cannot be deleted, and nobody can delete their own account
- Soft-deleted users, so an account can be retired without losing its history
- A 39-test PHPUnit suite covering every screen, guard and validation rule above

### Database tables

| Table | Purpose |
| --- | --- |
| `users` | Accounts. Now includes `deleted_at` for soft deletes |
| `roles` | Name, slug, description. Admin / Manager / Staff plus any custom role |
| `permissions` | Name, slug, description. 13 seeded permissions |
| `role_user` | Pivot: which users hold which roles |
| `permission_role` | Pivot: which permissions each role grants |

Migrations run in this order: `0001_01_01_000000_create_users_table`, `2026_10_26_133218_create_roles_table`, `2026_10_26_133415_create_permissions_table`, `2026_10_26_133427_create_role_user_table`, `2026_10_26_133506_create_permission_role_table`.

### Seeded accounts

`php artisan migrate:fresh --seed` creates the roles, the 13 permissions and three demo accounts. Every demo password is `password`.

| Email | Role | What it can do |
| --- | --- | --- |
| `admin@erp.test` | Admin | Everything: all 13 permissions, including users and roles |
| `manager@erp.test` | Manager | Operate the business: 11 permissions, no user or role management |
| `staff@erp.test` | Staff | Read-only: 6 permissions, dashboard and module views |

### Permission matrix

| Permission | Admin | Manager | Staff |
| --- | :---: | :---: | :---: |
| `dashboard.view` | ✅ | ✅ | ✅ |
| `inventory.view` | ✅ | ✅ | ✅ |
| `inventory.manage` | ✅ | ✅ | — |
| `sales.view` | ✅ | ✅ | ✅ |
| `sales.manage` | ✅ | ✅ | — |
| `purchasing.view` | ✅ | ✅ | ✅ |
| `purchasing.manage` | ✅ | ✅ | — |
| `accounting.view` | ✅ | ✅ | ✅ |
| `accounting.manage` | ✅ | ✅ | — |
| `reports.view` | ✅ | ✅ | ✅ |
| `reports.export` | ✅ | ✅ | — |
| `users.manage` | ✅ | — | — |
| `roles.manage` | ✅ | — | — |

### Routes

Everything ERP lives under the `/erp` prefix with the `erp.` route-name prefix (`routes/admin.php`, required from `routes/web.php`).

| Method | URI | Name | Middleware | Controller action |
| --- | --- | --- | --- | --- |
| GET | `/erp/login` | `erp.login` | `admin.guest` | `Admin\AuthenticatedSessionController@create` |
| POST | `/erp/login` | — | `admin.guest` | `Admin\AuthenticatedSessionController@store` |
| POST | `/erp/logout` | `erp.logout` | `admin.auth` | `Admin\AuthenticatedSessionController@destroy` |
| GET | `/erp/dashboard` | `erp.dashboard` | `admin.auth` | `Admin\DashboardController@index` |
| GET | `/erp/roles` | `erp.roles.index` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@index` |
| GET | `/erp/roles/create` | `erp.roles.create` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@create` |
| POST | `/erp/roles` | `erp.roles.store` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@store` |
| GET | `/erp/roles/{role}/edit` | `erp.roles.edit` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@edit` |
| PUT | `/erp/roles/{role}` | `erp.roles.update` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@update` |
| DELETE | `/erp/roles/{role}` | `erp.roles.destroy` | `admin.auth`, `permission:roles.manage` | `Admin\RoleController@destroy` |
| GET | `/erp/users` | `erp.users.index` | `admin.auth`, `permission:users.manage` | `Admin\UserController@index` |
| GET | `/erp/users/create` | `erp.users.create` | `admin.auth`, `permission:users.manage` | `Admin\UserController@create` |
| POST | `/erp/users` | `erp.users.store` | `admin.auth`, `permission:users.manage` | `Admin\UserController@store` |
| GET | `/erp/users/{user}/edit` | `erp.users.edit` | `admin.auth`, `permission:users.manage` | `Admin\UserController@edit` |
| PUT | `/erp/users/{user}` | `erp.users.update` | `admin.auth`, `permission:users.manage` | `Admin\UserController@update` |
| DELETE | `/erp/users/{user}` | `erp.users.destroy` | `admin.auth`, `permission:users.manage` | `Admin\UserController@destroy` |

The public side (`routes/auth.php`) keeps `GET|POST /login`, `GET|POST /register` and `POST /logout`. Registration assigns the Staff role automatically.

### Access control

| Piece | Where | What it does |
| --- | --- | --- |
| `admin.auth` | `app/Http/Middleware/AdminAuthenticated.php` | Guests hitting an ERP page are redirected to `erp.login` |
| `admin.guest` | `app/Http/Middleware/AdminGuest.php` | Signed-in users hitting `/erp/login` go to `erp.dashboard` |
| `permission:<slug>` | `app/Http/Middleware/EnsurePermission.php` | Aborts 403 when the user's roles do not grant that permission |
| `RolePolicy` | `app/Policies/RolePolicy.php` | Core roles (`admin`, `manager`, `staff`) and roles with users attached cannot be deleted |
| `UserPolicy` | `app/Policies/UserPolicy.php` | A user cannot delete their own account |
| Shared props | `app/Http/Middleware/HandleInertiaRequests.php` | Sends `auth.user` (id, name, email, roles, permissions) and `flash` (success, error) to every page |
| `User` model | `app/Models/User.php` | `hasRole()`, `hasAnyRole()`, `isAdmin()`, `hasPermission()`, `assignRole()`, soft deletes |

Middleware aliases are registered in `bootstrap/app.php`, and `HandleInertiaRequests` is appended to the `web` group there.

### Files created on Day 2

| File | Purpose |
| --- | --- |
| `routes/admin.php`, `routes/auth.php` | ERP and public auth route files |
| `app/Http/Controllers/Admin/AuthenticatedSessionController.php` | ERP login and logout |
| `app/Http/Controllers/Admin/DashboardController.php` | Dashboard page + props |
| `app/Http/Controllers/Admin/RoleController.php` | Role index, create, edit, delete |
| `app/Http/Controllers/Admin/UserController.php` | User index, create, edit, delete |
| `app/Http/Middleware/AdminAuthenticated.php`, `AdminGuest.php`, `EnsurePermission.php` | The three ERP middleware |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared auth and flash props |
| `app/Http/Requests/Admin/RoleRequest.php`, `AdminUserRequest.php` | Role and user validation |
| `app/Http/Requests/Auth/LoginRequest.php`, `RegisterRequest.php` | Login (with rate limiting) and registration validation |
| `app/Models/Role.php`, `Permission.php` | Role and permission models, plus `isCore()` |
| `app/Policies/RolePolicy.php`, `UserPolicy.php` | Delete and edit guards |
| `app/Services/DashboardService.php` | KPI counts and quick actions |
| `database/migrations/2026_10_26_*` | Roles, permissions and the two pivot tables |
| `database/seeders/DatabaseSeeder.php` | 3 roles, 13 permissions, 3 demo accounts |
| `resources/js/Pages/Erp/Dashboard.vue` | KPI cards and quick actions |
| `resources/js/Pages/Erp/Roles/Index.vue`, `Form.vue` | Role list and role form |
| `resources/js/Pages/Erp/Users/Index.vue`, `Form.vue` | User list and user form |
| `resources/js/Pages/Admin/Login.vue` | ERP sign-in screen |
| `resources/js/Components/ErpLayout.vue` | Sidebar + topbar shell, modules for Days 3-7 shown disabled |
| `resources/js/Components/Cards/*`, `Forms/*`, `Buttons/*` | Card, StatCard, Input, InputLabel, InputError, buttons |
| `tests/Feature/Admin/*`, `tests/Feature/Auth/*` | The Day 2 test suite |

### Day 2 verification checklist

- [ ] `php artisan migrate:fresh --seed` completes and the three demo accounts exist
- [ ] `php artisan test` — 39 tests pass (193 assertions)
- [ ] http://127.0.0.1:8000/erp/login shows the ERP sign-in page
- [ ] `admin@erp.test` / `password` lands on the dashboard: 3 users, 3 roles, 13 permissions, 1 administrator, both quick actions enabled
- [ ] `staff@erp.test` / `password` sees both quick actions disabled, and `/erp/users` returns 403
- [ ] `/erp/roles` lists Admin, Manager, Staff with no delete button, and 13 permissions in the header
- [ ] Create a role with two permissions → it appears in the table and the Roles KPI becomes 4
- [ ] `/erp/users` → create a user, assign Manager, sign in as that user
- [ ] `/register` creates a user who holds the Staff role
- [ ] `git add . && git commit -m "feat: day 2 authentication, roles and users"` and push

### Tests

| File | Tests | Covers |
| --- | :---: | --- |
| `tests/Feature/Admin/AdminAuthenticationTest.php` | 6 | Login screen, dashboard requires auth, admin sign-in, signed-in dashboard, signed-in users bounced off `/erp/login`, logout |
| `tests/Feature/Admin/DashboardTest.php` | 5 | KPI props, seeded baseline counts, quick actions for an admin and for staff, roles and permissions added later |
| `tests/Feature/Admin/RolesTest.php` | 10 | Auth and permission gates, index, create form, create with permissions, required/unique name, edit + permission sync, core role blocked, role in use blocked, custom role delete |
| `tests/Feature/Admin/UsersTest.php` | 9 | Auth and permission gates, index, create form, create with roles, unique email and minimum password, update + role sync, cannot delete self, delete another (soft delete) |
| `tests/Feature/Auth/AuthenticationTest.php` | 4 | `/login` renders, sign-in, wrong password, logout |
| `tests/Feature/Auth/RegistrationTest.php` | 3 | `/register` renders, registration, new users start as Staff |
| `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` | 2 | Smoke tests |

Useful commands:

```powershell
php artisan test                      # whole suite
php artisan test --filter=RolesTest   # one file
php artisan test tests/Feature/Admin  # one folder
```

Tests run on SQLite `:memory:` (see `phpunit.xml`), so they never touch your MySQL data. `tests/TestCase.php` calls `$this->withoutVite()` so the suite does not need built assets.

## Day 3 — Inventory: categories, products and stock

Day 3 activates the Inventory area using the `inventory.view` and `inventory.manage` permissions created on Day 2. Admins, managers and staff with `inventory.view` can inspect inventory; admins and managers with `inventory.manage` can maintain catalog records and record stock movements.

### What Day 3 adds

- Categories with names, unique slugs, descriptions and soft deletes.
- Product catalog with optional category assignment, unique SKU, description, cost and selling prices, on-hand quantity, low-stock threshold, active status and soft deletes.
- Stock ledger for receipts, issues and stock-level adjustments. Each movement stores the signed quantity delta, previous and new quantities, reason, actor and timestamp.
- Atomic stock updates through `InventoryService`; an issue cannot take stock below zero.
- Inventory pages in the ERP sidebar: Products, Categories and Stock.
- Two inventory dashboard KPIs: total products and products at or below their low-stock threshold.
- Inventory seed data: five categories, 12 products and 12 opening stock transactions.

### Inventory database tables

| Table | Purpose |
| --- | --- |
| `categories` | Category name and slug (both unique), description and soft-delete timestamp |
| `products` | SKU and product details, optional `category_id`, prices, stock quantity, low-stock threshold, active flag and soft-delete timestamp |
| `stock_transactions` | Product movement type, signed quantity, previous/new quantities, reason and optional user who recorded it |

The migrations run in dependency order: `2026_10_30_101405_create_categories_table`, `2026_10_31_165326_create_products_table`, then `2026_10_31_165405_create_stock_transactions_table`. The `products` table is plural; `stock_transactions.product_id` references `products.id` and uses the same unsigned BIGINT key type. The optional `products.category_id` references `categories.id`.

### Inventory routes and permissions

All routes are under `/erp`, require ERP authentication, and use the existing permission middleware.

| Access | Routes | Purpose |
| --- | --- | --- |
| `inventory.view` | `GET /erp/inventory/products`, `/erp/inventory/categories`, `/erp/inventory/stock` | View the catalog, category list and stock ledger |
| `inventory.manage` | Product/category create, store, edit, update and delete routes; `GET /erp/inventory/stock/create`; `POST /erp/inventory/stock` | Maintain inventory records and receive, issue or adjust stock |

Managers and admins have `inventory.manage`; Staff has read-only `inventory.view`. The exact role-permission assignments are listed in the Day 2 permission matrix above.

### Stock movement rules

| Type | Effect |
| --- | --- |
| `in` | Adds the submitted quantity to on-hand stock |
| `out` | Subtracts the submitted quantity; rejected if it would make stock negative |
| `adjustment` | Treats the submitted quantity as the new on-hand total, including zero |

`InventoryService` applies the product update and ledger insert in one database transaction so the quantity and audit record stay in sync.

### Files added for Day 3

| Files | Purpose |
| --- | --- |
| `database/migrations/2026_10_30_101405_create_categories_table.php`, `2026_10_31_165326_create_products_table.php`, `2026_10_31_165405_create_stock_transactions_table.php` | Inventory schema and foreign keys |
| `app/Models/Category.php`, `Product.php`, `StockTransaction.php` | Inventory records, relationships, casts and stock helpers |
| `app/Http/Controllers/Admin/CategoryController.php`, `ProductController.php`, `StockController.php` | Inventory pages and actions |
| `app/Http/Requests/Admin/CategoryRequest.php`, `ProductRequest.php`, `StockRequest.php` | Validation for category, product and movement forms |
| `app/Services/InventoryService.php` | Transactional stock updates and non-negative stock guard |
| `database/seeders/InventorySeeder.php` | Five sample categories, 12 products and opening movements; called by `DatabaseSeeder` |
| `resources/js/Pages/Erp/Inventory/Categories/*`, `Products/*`, `Stock/*` | Inventory list and form pages |
| `tests/Feature/Admin/InventoryCategoriesTest.php`, `InventoryProductsTest.php`, `InventoryStockTest.php` | Inventory access, CRUD, validation and stock behavior tests |

### Day 3 verification

Run the full test suite from the project root:

```powershell
composer test
```

Or run a focused inventory suite:

```powershell
php artisan test --filter=InventoryCategoriesTest
php artisan test --filter=InventoryProductsTest
php artisan test --filter=InventoryStockTest
```

The Day 3 checkpoint had 70 test methods: the Day 2 suite plus 30 inventory tests and an inventory KPI test. The Day 4 checkpoint code contained 104 test methods, including the Sales and dashboard coverage. Tests use SQLite `:memory:` as configured in `phpunit.xml`; they do not use the MySQL database in `.env`.

For a disposable development database, `php artisan migrate:fresh --seed` rebuilds all tables and loads the demo accounts and inventory. **This drops every table and all data in the configured database. Do not use it on a database whose data you need.**

Day 3 checklist:

- [ ] Inventory migrations run in category → product → stock-transaction order
- [ ] `composer test` completes successfully
- [ ] Staff can view inventory; only users with `inventory.manage` can change it
- [ ] Product and category forms validate required fields and unique identifiers
- [ ] Receiving, issuing and adjusting stock update both the product quantity and stock ledger
- [ ] Issuing more than available stock is rejected
- [ ] Dashboard shows the product and low-stock KPIs

## Day 4 — Sales: customers, orders and invoices

Day 4 activates the Sales area with customer records, sales orders, stock-aware order processing and invoices. The module uses the `sales.view` and `sales.manage` permissions seeded on Day 2: Staff can read sales records, while Admins and Managers can make changes.

### What Day 4 adds

- Customer directory with contact and address details, search, create/edit and soft delete.
- Sales orders with one or more product lines. Unit prices are snapshotted from each product's current selling price; line totals, subtotal and order total are computed by the server.
- Transactional stock deduction through `InventoryService`. Product rows are locked while availability is checked, and every sale writes a stock-ledger movement. Orders exceeding available stock are rejected.
- A guarded order lifecycle: Draft → Confirmed → Completed, with cancellation allowed from Draft or Confirmed. Completed and Cancelled orders are terminal.
- Invoices generated for Confirmed or Completed orders. Each order can have one invoice; invoice numbers are unique (`INV-000001` style), and new invoices start as Unpaid.
- Sales screens in the ERP sidebar for Customers, Orders and Invoices, including order and invoice detail pages.
- Sales-aware dashboard stats (customers, completed-sales revenue and non-cancelled orders), a six-month revenue trend, recent stock/sales activity, and a permission-aware “Create sale” action.
- Demo sales data: four customers, six completed orders with invoices across the last six months, and one confirmed order for the sales desk.

### Sales database tables

| Table | Purpose |
| --- | --- |
| `customers` | Customer contact and address fields, with soft deletes |
| `sales_orders` | Customer, status, subtotal, total and order timestamp |
| `sales_order_items` | Product, quantity, unit-price snapshot and line total for each order line |
| `invoices` | One invoice per order, unique invoice number, issue date, total and payment status |

### Sales routes and permissions

| Access | Routes | Purpose |
| --- | --- | --- |
| `sales.view` | `GET /erp/sales/customers`, `/erp/sales/orders`, `/erp/sales/orders/{order}`, `/erp/sales/invoices`, `/erp/sales/invoices/{invoice}` | View customer, order and invoice records |
| `sales.manage` | Customer create/update/delete; order create and status changes; invoice generation | Maintain sales records and issue invoices |

Customer management, order creation/status transitions and invoice generation are restricted to `sales.manage`; sales listings and details require `sales.view`. The Day 2 permission matrix remains the source for role assignments.

### Files added for Day 4

| Files | Purpose |
| --- | --- |
| `database/migrations/2026_11_05_100041_create_customers_table.php`, `2026_11_05_100058_create_sales_orders_table.php`, `2026_11_05_100121_create_sales_order_items_table.php`, `2026_11_05_100131_create_invoices_table.php` | Customer, order, order-line and invoice schema |
| `app/Models/Customer.php`, `SalesOrder.php`, `SalesOrderItem.php`, `Invoice.php` | Sales models, relationships, status and invoice helpers |
| `app/Http/Controllers/Admin/CustomerController.php`, `SalesOrderController.php`, `InvoiceController.php`; `app/Http/Requests/Admin/CustomerRequest.php`, `SalesOrderRequest.php` | Sales pages, actions and validation |
| `app/Policies/SalesOrderPolicy.php`, `app/Services/SalesService.php` | Permission checks, transactional order/stock processing and invoice generation |
| `database/seeders/SalesSeeder.php`; `database/seeders/DatabaseSeeder.php` | Seed customers, demo orders and invoices |
| `resources/js/Pages/Erp/Sales/Customers/*`, `Orders/*`, `Invoices/*` | Sales list, form and detail pages |
| `tests/Feature/Admin/SalesCustomersTest.php`, `SalesOrdersTest.php`, `SalesInvoicesTest.php` | 31 sales tests for access, CRUD, validation, stock, status transitions and invoices |

### Day 4 verification

Run the full test suite from the project root:

```powershell
composer test
```

The Day 4 checkpoint contained 104 test methods. Tests use SQLite `:memory:` as configured in `phpunit.xml`; running them does not use the MySQL database in `.env`.

Day 4 checklist:

- [ ] Staff can view Sales records; only users with `sales.manage` can create or change them
- [ ] Customer create/edit validation works and deletion is soft
- [ ] Order totals use catalog selling prices and successful orders update both product stock and the stock ledger
- [ ] An order that exceeds available stock is rejected without a partial order
- [ ] Only the allowed order-status transitions succeed
- [ ] Only confirmed/completed orders can be invoiced, and an order cannot receive a second invoice
- [ ] Dashboard shows sales KPIs, revenue trend and recent stock/sales activity

## Day 5 — Purchasing: suppliers, purchase orders and receipts

Day 5 activates the Purchasing area with supplier records, purchase orders, receiving and a purchasing-aware dashboard. The module uses the `purchasing.view` and `purchasing.manage` permissions seeded on Day 2: Staff can read purchasing records, while Admins and Managers can maintain them and receive stock.

### What Day 5 adds

- Supplier directory with contact and address details, search, create/edit, soft delete and a count of purchase orders per supplier.
- Purchase orders with one or more product lines. Unit costs are snapshotted from each product's current `cost_price`; line totals, subtotal and total are computed by the server.
- Purchase orders never change stock when they are created. Stock is only raised when a receipt is recorded.
- A guarded purchase-order lifecycle: Draft → Confirmed → Received, with cancellation allowed from Draft or Confirmed. Received and Cancelled are terminal.
- Receiving against confirmed purchase orders. Partial receipts accumulate, over-receiving is rejected, and a fully received order closes automatically.
- Transactional receiving through `InventoryService`: each receipt line writes an `in` stock-ledger movement and updates the product quantity in the same database transaction.
- Purchasing screens in the ERP sidebar for Suppliers, Purchase Orders and Receipts, including order and receipt detail pages. Order details show received and remaining quantities plus receipt history.
- Purchasing-aware dashboard stats for open purchase orders and pending confirmed-order value, a permission-aware “Create purchase order” quick action, and purchase receipt entries in the activity feed.
- Demo purchasing data: five suppliers, five purchase orders (one fully received, two confirmed and two draft), and one receipt with three line items.

### Purchasing database tables

| Table | Purpose |
| --- | --- |
| `suppliers` | Supplier name, optional unique email, phone, address fields and soft deletes |
| `purchase_orders` | Supplier, status, order date, subtotal, total, notes and creator; soft deletes |
| `purchase_order_items` | Product, ordered quantity, catalog cost snapshot and line total for each order line |
| `purchase_receipts` | Purchase order, received timestamp, received total, notes and receiving user |
| `purchase_receipt_items` | Links each receipt to a purchase-order line and records the quantity received |

### Purchasing routes and permissions

| Access | Routes | Purpose |
| --- | --- | --- |
| `purchasing.view` | `GET /erp/purchasing/suppliers`, `/erp/purchasing/orders`, `/erp/purchasing/orders/{purchaseOrder}`, `/erp/purchasing/receipts`, `/erp/purchasing/receipts/{purchaseReceipt}` | View suppliers, purchase orders and receipts |
| `purchasing.manage` | Supplier create/store/edit/update/delete; purchase order create/store, status changes and delete; receipt store | Maintain suppliers and orders, and receive stock |

Purchasing list and detail screens require `purchasing.view`; all mutations require `purchasing.manage`. The Day 2 permission matrix remains the source for role assignments.

### Purchase-order lifecycle and receiving rules

| Current status | Allowed next status | Notes |
| --- | --- | --- |
| `draft` | `confirmed`, `cancelled` | New orders start as drafts |
| `confirmed` | `cancelled` | A direct change to `received` is rejected |
| `received` | — | Reached only by recording receipts; terminal and cannot be cancelled or deleted |
| `cancelled` | — | Terminal |

Receiving rules:

- Only confirmed purchase orders can be received; draft, cancelled and already-received orders are rejected.
- A line can never receive more than its ordered quantity, including quantities from earlier partial receipts.
- A partial receipt keeps the order `confirmed`; receiving every line in full moves it to `received`.
- Receipt line totals use the purchase-order cost snapshot, not the product's current cost price.
- Receiving locks the purchase order and product rows, updates stock and appends stock-ledger entries atomically.

### Files added for Day 5

| Files | Purpose |
| --- | --- |
| `database/migrations/2026_11_08_102012_create_suppliers_table.php`, `2026_11_08_102037_create_purchase_orders_table.php`, `2026_11_08_102050_create_purchase_order_items_table.php`, `2026_11_08_102111_create_purchase_receipts_table.php`, `2026_11_08_102120_create_purchase_receipt_items_table.php` | Supplier, order, order-line, receipt and receipt-line schema |
| `app/Models/Supplier.php`, `PurchaseOrder.php`, `PurchaseOrderItem.php`, `PurchaseReceipt.php`, `PurchaseReceiptItem.php` | Purchasing models, relationships, status constants and receipt helpers |
| `app/Http/Controllers/Admin/SupplierController.php`, `PurchaseOrderController.php`, `PurchaseReceiptController.php`; `app/Http/Requests/Admin/SupplierRequest.php`, `PurchaseOrderRequest.php`, `PurchaseReceiptRequest.php` | Purchasing pages, actions and validation |
| `app/Policies/SupplierPolicy.php`, `PurchaseOrderPolicy.php`, `PurchaseReceiptPolicy.php`; `app/Services/PurchasingService.php` | Permission checks and transactional order/receipt processing |
| `database/seeders/PurchasingSeeder.php`; `database/seeders/DatabaseSeeder.php` | Seed suppliers, demo purchase orders and the received TechSource order |
| `resources/js/Pages/Erp/Purchasing/Suppliers/*`, `Orders/*`, `Receipts/*` | Supplier list and form; purchase order list, form and detail; receipt list and detail |
| `database/factories/PurchaseOrderFactory.php`, `SupplierFactory.php` | Test factories for purchase orders (including confirmed/received states) and suppliers |
| `tests/Feature/Admin/PurchaseOrdersTest.php`, `PurchaseReceiptsTest.php`, `PurchasingSuppliersTest.php` | 37 purchasing tests for access, CRUD, pricing, status transitions, receiving, over-receiving and deletion guards |

### Day 5 verification

Run the full test suite from the project root:

```powershell
composer test
```

The Day 5 checkpoint contained 144 test methods and 815 assertions. Tests use SQLite `:memory:` as configured in `phpunit.xml`; running them does not use the MySQL database in `.env`.

Day 5 checklist:

- [ ] Staff can view Suppliers, Purchase Orders and Receipts; only users with `purchasing.manage` can change them
- [ ] Suppliers support search, create/edit, unique email validation and soft delete
- [ ] Purchase order totals use catalog cost prices, and creating an order does not move stock
- [ ] An order requires a supplier and at least one line item
- [ ] Only the allowed purchase-order status transitions succeed
- [ ] A confirmed order cannot be changed directly to `received`; it must be received through a receipt
- [ ] A draft or cancelled order cannot be received, and over-receiving is rejected
- [ ] Partial receipts stay confirmed and accumulate; a fully received order becomes `received` and raises stock with ledger entries
- [ ] Received purchase orders can never be cancelled or deleted
- [ ] Dashboard shows the open purchase-order count, pending confirmed-order value, purchase-order quick action and receipt activity

## Day 6 — Accounting: chart of accounts, journal, trial balance and ledger

Day 6 connects operational documents to a double-entry general ledger. Every balance is derived from posted journal lines rather than stored on an account, so the chart, reports and dashboard always agree. The module uses `accounting.view` and `accounting.manage`: Staff can inspect the ledger; Admins and Managers can maintain accounts, post entries and post sales/purchasing documents.

### What Day 6 adds

- Chart of accounts with a unique code, name, one of five types (asset, liability, equity, revenue, expense), active flag and description. Accounts can be searched by code/name, filtered by type, and show their derived balance and line count.
- Double-entry journal with at least two lines per entry. Each line references an active account and carries a debit, credit and optional memo.
- Atomic posting through `AccountingService`: a failed entry never leaves a partial journal, and entry numbers use the `JE-000001` pattern.
- Validation that rejects fewer than two lines, zero totals, inactive/unknown accounts and unbalanced entries (debits must equal credits).
- Trial balance showing every account's debit total, credit total and credits-minus-debits balance, plus balanced debit/credit totals.
- General ledger showing postings in journal-date order with a running balance per account, and an optional account filter.
- Sales/Purchasing integration on the invoice and receipt detail pages: post an invoice as Accounts Receivable / Sales Revenue and a purchase receipt as Inventory / Accounts Payable. Each document can be posted only once.
- Accounting-aware dashboard: Net Income KPI, “Post journal entry” quick action and accounting posting activity in the recent-activity feed.
- Seed data: 16 accounts covering all five types, 12 balanced sample entries, and postings for the two newest seeded invoices and the TechSource receipt.

### Accounting database tables

| Table | Purpose |
| --- | --- |
| `accounts` | Unique chart code, name, type, active flag and description |
| `journal_entries` | Entry number, journal date, description and posting user |
| `journal_entry_lines` | One account, debit, credit and memo per line; cascades with the entry and restricts account deletion |
| `invoices.journal_entry_id`, `invoices.posted_at` | Link a posted invoice to its ledger entry and posting timestamp |
| `purchase_receipts.journal_entry_id`, `purchase_receipts.posted_at` | Link a posted receipt to its ledger entry and posting timestamp |

Migrations run in dependency order: `2026_11_05_095900_create_journal_entries_table`, `2026_11_05_102121_add_accounting_post_columns_to_invoices_table`, `2026_11_08_102146_add_accounting_post_columns_to_purchase_receipts_table`, then `2026_11_17_100231_create_accounts_table` and `2026_11_17_100254_create_journal_entry_lines_table`.

### Accounting routes and permissions

| Access | Routes | Purpose |
| --- | --- | --- |
| `accounting.view` | `GET /erp/accounting/accounts`, `/erp/accounting/journal`, `/erp/accounting/reports/trial-balance`, `/erp/accounting/reports/ledger` | View accounts, journal history, trial balance and general ledger |
| `accounting.manage` | Account create/store/edit/update/delete; journal create/store; `POST /erp/sales/invoices/{invoice}/post`; `POST /erp/purchasing/receipts/{purchaseReceipt}/post` | Maintain accounts, post journal entries and post operational documents |

Admins and Managers hold `accounting.manage`; Staff have read-only `accounting.view`. Posting invoices and receipts lives under `accounting.manage` even though the buttons appear on Sales/Purchasing detail pages.

### Posting and balance rules

- An entry needs at least two lines; every line must reference an active account.
- Debits and credits are each rounded to two decimals; total debits must equal total credits and be greater than zero.
- Entries are created inside one database transaction. Account rows are locked while the entry is validated, and the `JE-` number is derived from the row id.
- Balances use the credits-minus-debits convention: revenue and liability/equity accounts are normally positive, assets/expenses normally negative.
- Net income is the sum of live revenue and expense account balances, recomputed from the journal lines on every call.
- Invoice posting: debit `1020 Accounts Receivable`, credit `4010 Sales Revenue` for the invoice total.
- Receipt posting: debit `1030 Inventory`, credit `2010 Accounts Payable` for the receipt total.
- Integration accounts are resolved by stable chart code. If a required account is missing or inactive, posting is rejected with a validation message.
- Already-posted documents are guarded inside a row lock, so a repeated click cannot create a second entry.

### Files added for Day 6

| Files | Purpose |
| --- | --- |
| `database/migrations/2026_11_05_095900_create_journal_entries_table.php`, `2026_11_17_100231_create_accounts_table.php`, `2026_11_17_100254_create_journal_entry_lines_table.php`, `2026_11_05_102121_add_accounting_post_columns_to_invoices_table.php`, `2026_11_08_102146_add_accounting_post_columns_to_purchase_receipts_table.php` | Accounting schema and document posting links |
| `app/Models/Account.php`, `JournalEntry.php`, `JournalEntryLine.php` | Chart, entry and line models, relationships and derived balances |
| `app/Services/AccountingService.php` | Transactional journal posting, invoice/receipt posting, trial balance, general ledger and net income |
| `app/Http/Controllers/Admin/AccountController.php`, `JournalEntryController.php`, `TrialBalanceController.php`, `GeneralLedgerController.php`; `app/Http/Requests/Admin/AccountRequest.php`, `JournalEntryRequest.php` | Accounting pages, actions and validation |
| `app/Policies/AccountPolicy.php`, `JournalEntryPolicy.php`; posting gates on `InvoicePolicy` / `PurchaseReceiptPolicy` | `accounting.view` / `accounting.manage` authorization |
| `database/seeders/AccountingSeeder.php`; `database/seeders/DatabaseSeeder.php` | Chart of accounts, sample journal history and document postings |
| `resources/js/Pages/Erp/Accounting/Accounts/*`, `Journal/*`, `Reports/TrialBalance.vue`, `Reports/GeneralLedger.vue` | Chart, journal, trial balance and ledger screens |
| `database/factories/AccountFactory.php`; `tests/Feature/Admin/AccountsTest.php`, `JournalEntriesTest.php`, `AccountingReportsTest.php`, `AccountingIntegrationTest.php` | 39 accounting tests plus dashboard coverage |

### Day 6 verification

Run the full suite or a focused accounting suite:

```powershell
composer test
php artisan test --filter=AccountsTest
php artisan test --filter=JournalEntriesTest
php artisan test --filter=AccountingReportsTest
php artisan test --filter=AccountingIntegrationTest
```

Day 6 adds 42 tests in total: 39 accounting module tests plus three accounting dashboard/feed tests, taking the suite from 144 at the Day 5 checkpoint to 186. Tests use SQLite `:memory:` as configured in `phpunit.xml`.

Day 6 checklist:

- [ ] Chart of accounts creates, edits and deletes accounts, with unique codes and all five types
- [ ] An account with journal lines cannot be deleted
- [ ] Journal posting rejects fewer than two lines, zero lines, inactive accounts and unbalanced entries
- [ ] A valid entry receives a unique `JE-` number and persists every line
- [ ] Trial balance totals balance and each account shows its derived balance
- [ ] General ledger shows a running balance and filters to one account
- [ ] Posting an invoice creates a balanced AR/Sales Revenue entry once
- [ ] Posting a receipt creates a balanced Inventory/AP entry once
- [ ] Staff can view accounting but cannot post or mutate accounts
- [ ] Dashboard Net Income, quick action and posting activity work

## Day 7 — Reporting: snapshot library, financial statements and CSV exports

Day 7 adds the report library on top of the operational and accounting data. A report run creates a snapshot, the queued `GenerateReport` job computes the payload, and the snapshot moves from pending to completed or failed. The module uses `reports.view` to browse and run reports and the separate `reports.export` permission for CSV downloads.

### What Day 7 adds

- Report library with five report types: Sales, Inventory, Income Statement, Balance Sheet and Purchasing. Each card shows the latest run's status.
- Asynchronous snapshot pipeline: `POST /erp/reports` stores a pending `report_snapshots` row and dispatches `GenerateReport`; the job calls `ReportingService`, stores the computed payload and marks the snapshot completed or failed with the error message.
- Optional `date_from` / `date_to` parameters for Sales, Purchasing and the two financial statements. Inventory reports always describe current stock.
- Report pages render summary cards, tables and the monthly revenue bar chart; pending snapshots cannot be opened (422).
- Snapshot history filtered by report type, showing status, generator, timestamp and failure message for every run.
- Regeneration: a completed or failed snapshot can be re-run with the same type and parameters, creating a new snapshot while preserving every earlier run. Pending snapshots cannot be regenerated.
- CSV export of completed snapshots, streamed with a UTF-8 BOM and report-specific column shapes.
- Financial statements reconcile to the ledger: the income statement nets to the dashboard Net Income, and the balance sheet balances assets against liabilities plus equity, including retained earnings.
- Reporting activity in the dashboard feed and an enabled Reports item in the sidebar.
- Seed data: one completed snapshot per report type, one failed sales run and a retried completed sales run.

### Report snapshot database table

| Column | Purpose |
| --- | --- |
| `type` | One of `sales`, `inventory`, `income_statement`, `balance_sheet`, `purchasing` |
| `title` | Human-readable report title |
| `parameters` | JSON `date_from` / `date_to` used for the run |
| `payload` | JSON report output computed at generation time |
| `status` | `pending`, `completed` or `failed` |
| `error` | Failure message for a failed run |
| `generated_by` | User who started the run |
| timestamps | Created/updated time used by history and the dashboard feed |

The migration is `2026_11_18_115533_create_report_snapshots_table`. Payloads are computed from live source tables and stored only on the snapshot; report generation never writes the source tables.

### Reporting routes and permissions

| Access | Routes | Purpose |
| --- | --- | --- |
| `reports.view` | `GET /erp/reports`, `/erp/reports/history`, `/erp/reports/{reportSnapshot}`, `POST /erp/reports`, `POST /erp/reports/{reportSnapshot}/regenerate` | Browse the library, run/regenerate reports and open completed snapshots |
| `reports.export` | `GET /erp/reports/{reportSnapshot}/export` | Download a completed snapshot as CSV |

There is no separate “reports.manage” permission: running a report writes only its snapshot, not source data. The export gate is separate because downloading data is a higher privilege; Staff have `reports.view` but not `reports.export`.

### Report types and output

| Report | Payload highlights | CSV shape |
| --- | --- | --- |
| Sales | Revenue by month, top products/customers, order and invoice counts, average order value | `Month`, `Revenue` |
| Inventory | Valuation by category, low-stock products and movement counts | `Category`, `Products`, `Units in stock`, `Valuation` |
| Income Statement | Revenue and expense accounts with subtotals and net income | `Section`, `Code`, `Account`, `Amount` |
| Balance Sheet | Assets, liabilities and equity, retained earnings, ledger totals and balanced flag | `Section`, `Code`, `Account`, `Amount` |
| Purchasing | Spend by supplier, PO value by status, received vs pending | `Supplier`, `Orders`, `PO value`, `Received value` |

### Generation, failure and regeneration rules

- A new run always starts as a pending snapshot; the job runs immediately with the `sync` queue (tests) or through the configured queue in production. A queue worker must be running in production, otherwise snapshots stay pending.
- Any exception during generation is caught and written to `error`; the snapshot becomes failed instead of disappearing.
- Only completed snapshots can be viewed or exported; a pending or failed snapshot returns 422.
- Regeneration copies the original type and parameters into a new pending snapshot. It never overwrites the history row.
- A pending snapshot cannot be regenerated because a run is already in flight.
- Date ranges are validated with `date_to` after or equal to `date_from`.

### Files added for Day 7

| Files | Purpose |
| --- | --- |
| `database/migrations/2026_11_18_115533_create_report_snapshots_table.php` | Snapshot schema and statuses |
| `app/Models/ReportSnapshot.php` | Type/status constants, payload casts, completion/failure helpers and export filename |
| `app/Services/ReportingService.php`, `ReportingExporter.php` | Deterministic generation for all five report types and CSV row serialization |
| `app/Jobs/GenerateReport.php` | Queued snapshot generation with failure capture |
| `app/Http/Controllers/Admin/ReportController.php`; `app/Http/Requests/Admin/ReportRequest.php`; `app/Policies/ReportPolicy.php` | Library, history, show, run, regenerate, export and authorization |
| `app/Providers/AppServiceProvider.php` | Binds `ReportPolicy` to `ReportSnapshot` |
| `database/seeders/ReportingSeeder.php`; `database/seeders/DatabaseSeeder.php` | Completed snapshots for every type plus a failed and retried sales run |
| `resources/js/Pages/Erp/Reporting/Index.vue`, `Sales.vue`, `Inventory.vue`, `IncomeStatement.vue`, `BalanceSheet.vue`, `Purchasing.vue`, `History.vue`; `resources/js/Components/Reporting/SnapshotActions.vue` | Report library, report pages, history and run/export/regenerate actions |
| `tests/Feature/Admin/ReportsTest.php`, `FinancialStatementsTest.php`, `ReportsExportTest.php` | 34 reporting tests plus dashboard feed coverage |

### Day 7 verification

Run the full suite or a focused reporting suite:

```powershell
composer test
php artisan test --filter=ReportsTest
php artisan test --filter=FinancialStatementsTest
php artisan test --filter=ReportsExportTest
```

Day 7 adds 36 tests (34 reporting tests plus two dashboard/feed tests). The complete suite now has **222 test methods and 1,514 assertions** and runs against SQLite `:memory:`.

Day 7 checklist:

- [ ] The report library requires authentication and `reports.view`, and lists all five report types
- [ ] Running a report stores a pending snapshot and dispatches `GenerateReport`
- [ ] A successful run stores the computed payload and marks the snapshot completed
- [ ] A failed run records the error and remains visible in history
- [ ] The Sales and Inventory reports match the seeded data
- [ ] The income statement nets to the seeded Net Income and the balance sheet balances
- [ ] The purchasing report matches seeded purchase orders and receipts
- [ ] Only completed snapshots can be opened or exported
- [ ] CSV exports have the correct filename, BOM, headers and rows for every type
- [ ] `reports.export` is required for downloads
- [ ] A failed or completed snapshot can be regenerated; a pending one cannot
- [ ] History lists every run with status, generator, timestamp and error
- [ ] Reporting activity appears in the dashboard feed

## Troubleshooting

| Symptom | Cause & fix |
| --- | --- |
| Page shows the default Laravel "welcome" screen | `routes/web.php` still returns `view('welcome')` — change it to `Inertia::render('Welcome')`, save, hard-refresh (Ctrl+F5) |
| Vue page loads but unstyled / asset 404 | `npm run dev` is not running — start it in the second terminal |
| `fatal: not a git repository` | Project not initialized — run `git init` inside `C:\code\erp` |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong credentials in `.env` — check `DB_USERNAME` / `DB_PASSWORD` |
| Port 8000 already in use | `php artisan serve --port=8001` and open that port instead |
| `SQLSTATE[HY000]: no such column: users.deleted_at` | The `users` table was created before soft deletes existed — add `$table->softDeletes();` to the users migration and run `php artisan migrate:fresh --seed` |
| MySQL errno 150 adding `stock_transactions_product_id_foreign` | The migration expects `products` (plural) and matching unsigned BIGINT IDs. Check the actual table name and `SHOW CREATE TABLE products`; do not rerun a partially failed migration without checking whether `stock_transactions` was left behind |
| `Vite manifest not found at .../public/build/manifest.json` during a test | `tests/TestCase.php` must keep `$this->withoutVite();` in `setUp()`. Alternatively run `npm run build` before testing |
| 403 on `/erp/roles` while signed in as an admin | The admin role lost its permissions (partial seed) — run `php artisan migrate:fresh --seed` |
| Signed in, but every page returns 500 | `npm run dev` is not running, or the Vue page for that component is missing |
| `Route [dashboard] not defined` | A leftover Breeze redirect — Day 2 redirects to `route('erp.dashboard')` |
| `Inertia page component file [Erp/Dashboard] does not exist` | Inertia's test page lookup uses `resources/js/pages` (lowercase) and Linux is case-sensitive. Add `config/inertia.php` with `'paths' => [resource_path('js/Pages')]` |
| Home page blank or 500 at `/` | `/` still renders the `Welcome` component, but `resources/js/Pages/Welcome.vue` no longer exists — restore the file or point `/` at `/erp/login` |

## Known gaps at this checkpoint

- `resources/js/Pages/Welcome.vue` was deleted while `/` still renders `Welcome`. Restore it or change the route.
- Breeze leftovers with no routes: `Auth\RegisteredUserController`, `Auth\NewPasswordController`, `Auth\PasswordController`, `Auth\ConfirmablePasswordController`, `Auth\VerifyEmailController`. `routes/auth.php` uses `Auth\RegisterController` instead.
- `User::permissions()` returns roles rather than permissions. Nothing calls it yet; use `roles()->with('permissions')` or `hasPermission()` until it is rewritten.
- Remaining inventory follow-ups: `DELETE /erp/inventory/stock` is registered but `StockController::destroy()` is not implemented; category deletion should explicitly guard categories that still have products.
- Sales orders deduct stock when created, including draft orders; cancelling an order changes its status but does not restore stock.
- Invoices default to Unpaid; a payment-recording action is not implemented yet.
- Only received purchase orders are blocked from deletion; confirmed and cancelled purchase orders can still be soft-deleted.
- Supplier deletion is a soft delete and does not guard suppliers that already have purchase orders.
- Received purchase receipts cannot be reversed or corrected yet.
- Journal entries are immutable after posting: there is no edit, void or reversal workflow, and the trial balance/general ledger have no date-range filter.
- Invoice posting uses a simple two-line Accounts Receivable / Sales Revenue entry; it does not relieve inventory, record COGS or post tax lines.
- Document posting depends on active accounts with codes `1020`, `1030`, `2010` and `4010`; renaming or deactivating those accounts blocks posting.
- Report snapshots are never deleted or pruned, exports are CSV-only, and the inventory report ignores date parameters.
- Reports run through the queue; without `php artisan queue:listen` or `queue:work`, new snapshots stay pending. There are no scheduled or emailed reports yet.

## Next step

All seven days are complete. Suggested hardening work: record invoice payments, add journal reversal and period-close workflows, add date filters to the trial balance and general ledger, prune old report snapshots, add PDF and scheduled reports, and run both `composer test` and `npm run build` in CI.
