<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->nullableMorphs('source');
            $table->string('title');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['type', 'source_type', 'source_id'], 'documents_type_source_unique');
        });

        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
            $table->index(['document_id', 'chunk_index']);
        });

        Schema::create('embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_chunk_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('openai')->index();
            $table->string('model')->nullable();
            $table->json('vector');
            $table->unsignedInteger('dimensions')->default(0);
            $table->string('content_hash', 64)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
