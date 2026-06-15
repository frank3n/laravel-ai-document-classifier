<?php

namespace App\Actions;

use App\DTOs\ClassificationResult;
use App\Models\Classification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FireWebhookAction
{
    public function handle(ClassificationResult $result, Classification $record): void
    {
        $url    = config('classifier.routing.webhook.url');
        $secret = config('classifier.routing.webhook.secret');

        if (empty($url)) {
            Log::warning('Webhook enabled but WEBHOOK_URL is not set.');
            return;
        }

        $payload = array_merge($result->toArray(), [
            'classification_id' => $record->id,
            'document_source'   => $result->documentSource,
            'filename'          => $result->filename,
            'timestamp'         => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        try {
            Http::timeout(10)
                ->withHeaders(['X-Webhook-Signature' => $signature])
                ->post($url, $payload);

            $record->update(['webhook_fired' => true]);
        } catch (\Throwable $e) {
            Log::warning("Webhook delivery failed: {$e->getMessage()}");
        }
    }
}
