# Ecommerce API

Production-grade Laravel backend foundation for a scalable e-commerce platform.

## Stack

- Laravel 13 and PHP 8.4 runtime in Docker
- MySQL 8.4
- Redis for cache and queues
- Laravel Sanctum token authentication
- PHPUnit feature tests

## Local Setup

```bash
cp .env.example .env
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

API base URL:

```text
http://localhost:8000/api
```

## Seeded Accounts

All seeded users use the password `Password123`.

- `superadmin@example.com`
- `admin@example.com`
- `customer@example.com`

## API Endpoints

- `GET /api/health`
- `GET /api/products`
- `GET /api/products/{slug}`
- `GET /api/categories`
- `GET /api/brands`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout` with `Authorization: Bearer <token>`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/me` with `Authorization: Bearer <token>`
- `GET /api/auth/email/verify/{id}/{hash}` signed email verification link
- `GET /api/super-admin/health` with `super_admin` role
- `GET /api/admin/health` with `admin` role, also accessible by `super_admin`
- `GET /api/customer/health` with `customer` role
- Admin catalog CRUD with `admin` or `super_admin` role:
  - `/api/admin/categories`
  - `/api/admin/brands`
  - `/api/admin/products`
  - `/api/admin/variants`
  - `/api/admin/images`

## Architecture

The API keeps controllers thin and pushes domain behavior into services and repositories.

- `app/Services` contains application services such as authentication orchestration.
- `app/Repositories` contains persistence access boundaries.
- `app/DTOs` carries validated request data into services.
- `app/Enums` stores stable domain constants such as role names.
- `app/Http/Requests` validates input.
- `app/Http/Resources` shapes API output.
- `app/Traits/ApiResponse.php` provides a consistent JSON response contract.
- `bootstrap/app.php` configures API exception rendering and route middleware aliases.

## Next Backend Milestones

- Product catalog bounded context: brands, categories, products, variants, media, attributes.
- Inventory reservations and stock movements with transactions.
- Cart and checkout aggregates with idempotency keys.
- Payment provider boundary with Stripe implementation later.
- Search indexing jobs and AI-assisted catalog search.
