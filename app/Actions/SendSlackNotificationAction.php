<?php

namespace App\Actions;

use App\DTOs\ClassificationResult;
use App\Models\Classification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSlackNotificationAction
{
    public function handle(ClassificationResult $result, Classification $record): void
    {
        $webhookUrl = config('classifier.routing.slack.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('Slack enabled but SLACK_WEBHOOK_URL is not set.');
            return;
        }

        $label      = config("classifier.categories.{$result->category}.label", $result->category);
        $emoji      = match ($result->confidence) {
            'high'   => '🟢',
            'medium' => '🟡',
            default  => '🔴',
        };

        $payload = [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => 'Document Classified'],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Category:*\n{$label}"],
                        ['type' => 'mrkdwn', 'text' => "*Confidence:*\n{$emoji} " . ucfirst($result->confidence)],
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => "*Rationale:*\n{$result->rationale}"],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        ['type' => 'mrkdwn', 'text' => "Source: `{$result->documentSource}` | DB record: #{$record->id}"],
                    ],
                ],
            ],
        ];

        try {
            Http::timeout(10)->post($webhookUrl, $payload);
            $record->update(['slack_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning("Slack notification failed: {$e->getMessage()}");
        }
    }
}
