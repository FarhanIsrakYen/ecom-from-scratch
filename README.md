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
- `POST /api/stripe/webhook` Stripe webhook endpoint
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
- Customer cart and checkout with `Authorization: Bearer <token>`:
  - `GET /api/cart`
  - `POST /api/cart/items`
  - `PUT /api/cart/items/{id}`
  - `DELETE /api/cart/items/{id}`
  - `DELETE /api/cart`
  - `POST /api/checkout`
  - `POST /api/payments/stripe/checkout-sessions`
- Admin catalog CRUD with `admin` or `super_admin` role:
  - `/api/admin/categories`
  - `/api/admin/brands`
  - `/api/admin/products`
  - `/api/admin/variants`
  - `/api/admin/images`
- Admin pricing CRUD with `admin` or `super_admin` role:
  - `/api/admin/coupons`
  - `/api/admin/delivery-zones`
  - `/api/admin/tax-settings`
- Admin inventory management with `admin` or `super_admin` role:
  - `GET /api/admin/inventory`
  - `POST /api/admin/inventory/adjustments`
- Versioned auth aliases:
  - `POST /api/v1/auth/register`
  - `POST /api/v1/auth/login`
  - `POST /api/v1/auth/forgot-password`
  - `POST /api/v1/auth/reset-password`
  - `GET /api/v1/auth/me` with `Authorization: Bearer <token>`
  - `POST /api/v1/auth/logout` with `Authorization: Bearer <token>`
  - `GET /api/v1/admin/health` with `admin` or `super_admin` role

## New Endpoint Payloads

All protected endpoints require `Authorization: Bearer <token>`.

### Cart and Checkout

`POST /api/cart/items`

```json
{
  "product_id": 1,
  "product_variant_id": 10,
  "quantity": 2
}
```

`PUT /api/cart/items/{id}`

```json
{
  "quantity": 4
}
```

`POST /api/checkout`

```json
{
  "coupon_code": "SAVE10",
  "shipping_address": {
    "name": "Jane Customer",
    "phone": "+15555550123",
    "address_line_1": "100 Market Street",
    "address_line_2": "Suite 2",
    "city": "Dhaka",
    "state": "Dhaka",
    "postal_code": "1207",
    "country": "Bangladesh"
  },
  "billing_address": {
    "name": "Jane Customer",
    "phone": "+15555550123",
    "address_line_1": "100 Market Street",
    "city": "Dhaka",
    "country": "Bangladesh"
  }
}
```

`coupon_code`, `billing_address`, `address_line_2`, `state`, and `postal_code` are optional. Delivery charge and tax are calculated by active delivery zones and tax settings; client-supplied pricing fields are ignored.

### Stripe Payments

`POST /api/payments/stripe/checkout-sessions`

```json
{
  "order_id": 123
}
```

`POST /api/stripe/webhook`

Stripe sends the raw JSON event payload to this endpoint. The request must include the `Stripe-Signature` header and match `STRIPE_WEBHOOK_SECRET`.

### Admin Coupons

Coupon CRUD endpoints support:

```text
GET    /api/admin/coupons
POST   /api/admin/coupons
GET    /api/admin/coupons/{coupon}
PUT    /api/admin/coupons/{coupon}
PATCH  /api/admin/coupons/{coupon}
DELETE /api/admin/coupons/{coupon}
```

Create payload:

```json
{
  "code": "SAVE10",
  "type": "percentage",
  "value": 10,
  "max_discount_amount": 15,
  "minimum_order_amount": 50,
  "usage_limit": 100,
  "usage_per_user": 1,
  "starts_at": "2026-07-01T00:00:00Z",
  "expires_at": "2026-07-31T23:59:59Z",
  "status": "active"
}
```

`type` must be `fixed` or `percentage`. `status` must be `active` or `inactive`. Update payloads may include any subset of the create fields.

### Admin Delivery Zones

Delivery zone CRUD endpoints support:

```text
GET    /api/admin/delivery-zones
POST   /api/admin/delivery-zones
GET    /api/admin/delivery-zones/{delivery_zone}
PUT    /api/admin/delivery-zones/{delivery_zone}
PATCH  /api/admin/delivery-zones/{delivery_zone}
DELETE /api/admin/delivery-zones/{delivery_zone}
```

Create payload:

```json
{
  "name": "Dhaka",
  "country": "Bangladesh",
  "state": "Dhaka",
  "city": "Dhaka",
  "postal_code": "1207",
  "charge": 9,
  "is_default": false,
  "status": "active"
}
```

`name` and `charge` are required. Location fields, `is_default`, and `status` are optional. Update payloads may include any subset of these fields.

### Admin Tax Settings

Tax setting CRUD endpoints support:

```text
GET    /api/admin/tax-settings
POST   /api/admin/tax-settings
GET    /api/admin/tax-settings/{tax_setting}
PUT    /api/admin/tax-settings/{tax_setting}
PATCH  /api/admin/tax-settings/{tax_setting}
DELETE /api/admin/tax-settings/{tax_setting}
```

Create payload:

```json
{
  "name": "VAT",
  "country": "Bangladesh",
  "state": "Dhaka",
  "city": "Dhaka",
  "rate": 7.5,
  "is_default": false,
  "status": "active"
}
```

`name` and `rate` are required. Location fields, `is_default`, and `status` are optional. Update payloads may include any subset of these fields.

### Admin Inventory Adjustments

`POST /api/admin/inventory/adjustments`

```json
{
  "product_id": 1,
  "product_variant_id": 10,
  "type": "stock_in",
  "quantity": 25,
  "reason": "Supplier restock",
  "reference_type": "purchase_order",
  "reference_id": 42,
  "low_stock_threshold": 5
}
```

`type` must be `stock_in`, `stock_out`, or `adjusted`. `product_variant_id`, `reason`, `reference_type`, `reference_id`, and `low_stock_threshold` are optional.

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
