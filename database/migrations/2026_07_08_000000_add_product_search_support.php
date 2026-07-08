<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('average_rating', 3, 2)->default(0)->after('is_featured');
            $table->unsignedInteger('reviews_count')->default(0)->after('average_rating');
            $table->index(['status', 'average_rating', 'id'], 'products_status_rating_id_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_search_fulltext (name, sku, short_description, description)');
        }

        Schema::create('search_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('query');
        });

        Schema::create('popular_searches', function (Blueprint $table): void {
            $table->id();
            $table->string('query')->unique();
            $table->unsignedInteger('search_count')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->index(['search_count', 'last_searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_searches');
        Schema::dropIfExists('search_histories');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products DROP INDEX products_search_fulltext');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_status_rating_id_index');
            $table->dropColumn(['average_rating', 'reviews_count']);
        });
    }
};
