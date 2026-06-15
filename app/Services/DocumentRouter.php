<?php

namespace App\Services;

use App\Actions\FireWebhookAction;
use App\Actions\LogClassificationAction;
use App\Actions\SendSlackNotificationAction;
use App\DTOs\ClassificationResult;
use App\Models\Classification;

class DocumentRouter
{
    public function __construct(
        private LogClassificationAction     $logAction,
        private FireWebhookAction           $webhookAction,
        private SendSlackNotificationAction $slackAction,
    ) {}

    public function route(ClassificationResult $result): Classification
    {
        $record = $this->logAction->handle($result);

        if (config('classifier.routing.webhook.enabled')) {
            $this->webhookAction->handle($result, $record);
        }

        if (config('classifier.routing.slack.enabled')) {
            $this->slackAction->handle($result, $record);
        }

        return $record->fresh();
    }
}
