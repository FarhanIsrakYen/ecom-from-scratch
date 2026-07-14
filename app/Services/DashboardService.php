<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_KEY = 'admin.dashboard.summary';

    private const CACHE_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'summary' => $this->summary(),
            'sales_chart' => [
                'daily' => $this->salesChart('daily'),
                'weekly' => $this->salesChart('weekly'),
                'monthly' => $this->salesChart('monthly'),
            ],
            'top_selling_products' => $this->topSellingProducts(),
            'top_customers' => $this->topCustomers(),
            'recent_orders' => $this->recentOrders(),
            'revenue_by_category' => $this->revenueByCategory(),
            'coupon_usage' => $this->couponUsage(),
            'inventory_alerts' => $this->inventoryAlerts(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(self::CACHE_SECONDS), function (): array {
            return [
                'total_sales' => $this->money(DB::table('orders')
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->sum('total')),
                'total_orders' => DB::table('orders')->count(),
                'total_customers' => DB::table('users')
                    ->join('role_user', 'role_user.user_id', '=', 'users.id')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->where('roles.name', RoleEnum::Customer->value)
                    ->distinct('users.id')
                    ->count('users.id'),
                'total_products' => DB::table('products')->count(),
                'pending_orders' => DB::table('orders')
                    ->where('status', OrderStatus::Pending->value)
                    ->count(),
                'low_stock_products' => DB::table('inventories')
                    ->where('low_stock_threshold', '>', 0)
                    ->whereColumn('available_stock', '<', 'low_stock_threshold')
                    ->distinct('product_id')
                    ->count('product_id'),
            ];
        });
    }

    /**
     * @return array<int, array{period: string, orders_count: int, revenue: float}>
     */
    private function salesChart(string $period): array
    {
        $periodExpression = $this->periodExpression($period);

        return DB::table('orders')
            ->selectRaw("{$periodExpression} as period")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'orders_count' => (int) $row->orders_count,
                'revenue' => $this->money($row->revenue),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|float|null>>
     */
    private function topSellingProducts(int $limit = 10): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->select([
                'order_items.product_id',
                'order_items.product_name',
                'order_items.sku',
                'products.slug',
            ])
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.sku', 'products.slug')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->product_name,
                'sku' => (string) $row->sku,
                'slug' => $row->slug,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => $this->money($row->revenue),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|float>>
     */
    private function topCustomers(int $limit = 10): array
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
            ])
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as total_spent')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'customer_id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'orders_count' => (int) $row->orders_count,
                'total_spent' => $this->money($row->total_spent),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|float>>
     */
    private function recentOrders(int $limit = 10): array
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.payment_status',
                'orders.total',
                'orders.created_at',
                'users.id as customer_id',
                'users.name as customer_name',
                'users.email as customer_email',
            ])
            ->latest('orders.created_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'order_number' => (string) $row->order_number,
                'status' => (string) $row->status,
                'payment_status' => (string) $row->payment_status,
                'total' => $this->money($row->total),
                'created_at' => (string) $row->created_at,
                'customer' => [
                    'id' => (int) $row->customer_id,
                    'name' => (string) $row->customer_name,
                    'email' => (string) $row->customer_email,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|float>>
     */
    private function revenueByCategory(): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
            ])
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $row): array => [
                'category_id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'quantity_sold' => (int) $row->quantity_sold,
                'orders_count' => (int) $row->orders_count,
                'revenue' => $this->money($row->revenue),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|float>>
     */
    private function couponUsage(int $limit = 10): array
    {
        return DB::table('coupon_usages')
            ->join('coupons', 'coupons.id', '=', 'coupon_usages.coupon_id')
            ->join('orders', 'orders.id', '=', 'coupon_usages.order_id')
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.type',
            ])
            ->selectRaw('COUNT(coupon_usages.id) as usage_count')
            ->selectRaw('COALESCE(SUM(coupon_usages.discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as revenue')
            ->groupBy('coupons.id', 'coupons.code', 'coupons.type')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'coupon_id' => (int) $row->id,
                'code' => (string) $row->code,
                'type' => (string) $row->type,
                'usage_count' => (int) $row->usage_count,
                'discount_amount' => $this->money($row->discount_amount),
                'revenue' => $this->money($row->revenue),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function inventoryAlerts(int $limit = 20): array
    {
        return DB::table('inventories')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->select([
                'inventories.id',
                'inventories.product_id',
                'inventories.product_variant_id',
                'inventories.available_stock',
                'inventories.reserved_stock',
                'inventories.low_stock_threshold',
                'products.name as product_name',
            ])
            ->selectRaw('COALESCE(product_variants.sku, products.sku) as sku')
            ->where('inventories.low_stock_threshold', '>', 0)
            ->whereColumn('inventories.available_stock', '<', 'inventories.low_stock_threshold')
            ->orderBy('inventories.available_stock')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'inventory_id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'product_variant_id' => $row->product_variant_id === null ? null : (int) $row->product_variant_id,
                'product_name' => (string) $row->product_name,
                'sku' => (string) $row->sku,
                'available_stock' => (int) $row->available_stock,
                'reserved_stock' => (int) $row->reserved_stock,
                'low_stock_threshold' => (int) $row->low_stock_threshold,
            ])
            ->all();
    }

    private function periodExpression(string $period): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => match ($period) {
                'weekly' => "strftime('%Y-W%W', created_at)",
                'monthly' => "strftime('%Y-%m', created_at)",
                default => 'date(created_at)',
            },
            'pgsql' => match ($period) {
                'weekly' => "to_char(created_at, 'IYYY-\"W\"IW')",
                'monthly' => "to_char(created_at, 'YYYY-MM')",
                default => 'created_at::date',
            },
            'sqlsrv' => match ($period) {
                'weekly' => "CONCAT(DATEPART(year, created_at), '-W', RIGHT(CONCAT('0', DATEPART(iso_week, created_at)), 2))",
                'monthly' => "FORMAT(created_at, 'yyyy-MM')",
                default => 'CAST(created_at AS date)',
            },
            default => match ($period) {
                'weekly' => "DATE_FORMAT(created_at, '%x-W%v')",
                'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => 'DATE(created_at)',
            },
        };
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
