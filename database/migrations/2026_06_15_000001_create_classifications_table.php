<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->string('document_source', 50);
            $table->string('filename')->nullable();
            $table->text('document_excerpt')->nullable();
            $table->string('category', 100);
            $table->string('confidence', 20);
            $table->text('rationale');
            $table->json('raw_response');
            $table->boolean('webhook_fired')->default(false);
            $table->boolean('slack_sent')->default(false);
            $table->timestamps();

            $table->index('category');
            $table->index('confidence');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
