<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeBase\KnowledgeBaseSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly KnowledgeBaseSyncService $sync) {}

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sync_products' => ['sometimes', 'boolean'],
            'sync_platform_defaults' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'documents' => ['sometimes', 'array'],
            'documents.*.type' => ['required_with:documents', 'string', 'in:faq,refund_policy,delivery_policy,platform_policy'],
            'documents.*.title' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.content' => ['required_with:documents', 'string', 'min:2'],
            'documents.*.metadata' => ['sometimes', 'array'],
        ]);

        $syncedProducts = 0;
        $syncedDocuments = 0;

        if (($data['sync_products'] ?? false) === true) {
            $syncedProducts = isset($data['product_ids'])
                ? $this->sync->syncProducts($data['product_ids'])
                : $this->sync->syncAllProducts();
        }

        foreach ($data['documents'] ?? [] as $document) {
            $this->sync->syncPlatformDocument(
                $document['type'],
                $document['title'],
                $document['content'],
                $document['metadata'] ?? [],
            );
            $syncedDocuments++;
        }

        if (($data['sync_platform_defaults'] ?? false) === true) {
            $syncedDocuments += $this->sync->syncConfiguredPlatformDocuments();
        }

        return $this->success([
            'synced_products' => $syncedProducts,
            'synced_documents' => $syncedDocuments,
        ], 'Knowledge base sync queued.');
    }
}
