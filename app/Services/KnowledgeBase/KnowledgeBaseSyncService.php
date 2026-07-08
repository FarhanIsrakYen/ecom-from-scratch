<?php

namespace App\Services\KnowledgeBase;

use App\Jobs\GenerateDocumentChunkEmbedding;
use App\Models\Document;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBaseSyncService
{
    public function syncAllProducts(): int
    {
        $count = 0;

        Product::query()
            ->with(['category', 'brand', 'variants', 'inventories'])
            ->where('status', 'active')
            ->chunkById(100, function (Collection $products) use (&$count): void {
                foreach ($products as $product) {
                    $this->syncProduct($product);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<int, int>  $productIds
     */
    public function syncProducts(array $productIds): int
    {
        return Product::query()
            ->with(['category', 'brand', 'variants', 'inventories'])
            ->whereIn('id', $productIds)
            ->get()
            ->each(fn (Product $product) => $this->syncProduct($product))
            ->count();
    }

    public function syncProduct(Product $product): Document
    {
        $product->loadMissing(['category', 'brand', 'variants', 'inventories']);
        $price = $product->sale_price ?? $product->base_price;
        $variantLines = $product->variants
            ->map(fn ($variant): string => 'Variant '.$variant->sku.' attributes '.json_encode($variant->attributes).' price '.$variant->price.' stock '.$variant->stock)
            ->implode("\n");

        $inventory = $product->inventories->sum('available_stock');
        $content = trim(implode("\n", array_filter([
            "Product: {$product->name}",
            "SKU: {$product->sku}",
            'Category: '.($product->category?->name ?? 'Uncategorized'),
            'Brand: '.($product->brand?->name ?? 'No brand'),
            "Price: {$price}",
            "Base price: {$product->base_price}",
            $product->sale_price !== null ? "Sale price: {$product->sale_price}" : null,
            "Available stock: {$inventory}",
            "Short description: {$product->short_description}",
            "Description: {$product->description}",
            $variantLines !== '' ? "Variants:\n{$variantLines}" : null,
        ])));

        return $this->upsertDocument(
            attributes: [
                'type' => 'product',
                'source_type' => Product::class,
                'source_id' => $product->id,
            ],
            values: [
                'title' => $product->name,
                'content' => $content,
                'metadata' => [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'slug' => $product->slug,
                    'category_id' => $product->category_id,
                    'brand_id' => $product->brand_id,
                    'price' => (float) $price,
                ],
                'status' => $product->status === 'active' ? 'active' : 'inactive',
            ],
        );
    }

    public function syncPlatformDocument(string $type, string $title, string $content, array $metadata = []): Document
    {
        return $this->upsertDocument(
            attributes: [
                'type' => $type,
                'source_type' => null,
                'source_id' => null,
                'title' => $title,
            ],
            values: [
                'content' => trim($content),
                'metadata' => $metadata,
                'status' => 'active',
            ],
        );
    }

    public function syncConfiguredPlatformDocuments(): int
    {
        $count = 0;

        foreach (config('knowledge_base.platform_documents', []) as $document) {
            if (! is_array($document)) {
                continue;
            }

            $this->syncPlatformDocument(
                (string) $document['type'],
                (string) $document['title'],
                (string) $document['content'],
                $document['metadata'] ?? [],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    private function upsertDocument(array $attributes, array $values): Document
    {
        return DB::transaction(function () use ($attributes, $values): Document {
            $document = Document::query()->updateOrCreate($attributes, $values);
            $document->chunks()->delete();

            foreach ($this->chunk($document->content) as $index => $chunk) {
                $documentChunk = $document->chunks()->create([
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'metadata' => ['content_hash' => hash('sha256', $chunk)],
                ]);

                GenerateDocumentChunkEmbedding::dispatch($documentChunk->id);
            }

            return $document->refresh();
        });
    }

    /**
     * @return array<int, string>
     */
    private function chunk(string $content): array
    {
        $chunkSize = (int) config('knowledge_base.chunk_size', 900);
        $sentences = preg_split('/(?<=[.!?])\s+|\R+/', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $candidate = trim($current === '' ? $sentence : $current.' '.$sentence);

            if (Str::length($candidate) > $chunkSize && $current !== '') {
                $chunks[] = trim($current);
                $current = trim($sentence);
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        if ($chunks === [] && trim($content) !== '') {
            $chunks[] = Str::limit(trim($content), $chunkSize, '');
        }

        return $chunks;
    }
}
