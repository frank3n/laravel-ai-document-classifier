# laravel-ai-document-classifier

> A Laravel proof-of-concept that uses the Claude API to classify and route incoming documents — no platform, no subscription, no lock-in.

![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue) ![Laravel](https://img.shields.io/badge/Laravel-11-red) ![License](https://img.shields.io/badge/license-MIT-green)

---

## What this solves

Every business gets documents it has to sort manually — invoices, contracts, support requests, intake forms. This PoC shows how to connect Claude to a Laravel app so incoming documents get classified automatically and routed to the right place. No SaaS platform involved. You own the logic, it runs on your server, and you can adapt it to whatever document types your business actually handles.

---

## What it does

- Accepts a document (text or `.txt`/`.pdf` file) via a **POST endpoint** or **Artisan command**
- Sends it to Claude with a configurable classification prompt
- Returns a category + confidence level + rationale
- Routes the result: **logs to DB**, fires a **webhook**, sends a **Slack notification** (each individually configurable)

---

## Demo

### Artisan command

```bash
php artisan classify:document --text="INVOICE #INV-2024-001. Billed to: Acme Corp. Services: Web development - \$2,500. Payment due: 30 days."
```

```
+------------+-----------------------------------------------------+
| Field      | Value                                               |
+------------+-----------------------------------------------------+
| Category   | Invoice                                             |
| Confidence | High                                                |
| Rationale  | The document contains an invoice number, a billed-  |
|            | to party, itemized service charges, and a payment   |
|            | due date — all hallmarks of an invoice.             |
| DB record  | #1                                                  |
| Webhook    | skipped                                             |
| Slack      | skipped                                             |
+------------+-----------------------------------------------------+
```

### POST endpoint (JSON response)

```json
{
  "success": true,
  "classification": {
    "category": "support_request",
    "confidence": "high",
    "rationale": "The document is a customer complaint requesting a refund for an order that has not arrived.",
    "category_label": "Support Request"
  },
  "metadata": {
    "classification_id": 2,
    "document_source": "api",
    "filename": null,
    "routing": {
      "logged": true,
      "webhook_fired": false,
      "slack_sent": false
    }
  }
}
```

---

## Technical overview

Built on Laravel 11, calls the Claude API directly via `Http::post()` (no SDK wrapper). Classification logic lives in `DocumentClassifierService`. Routing is handled by three independent action classes (`LogClassificationAction`, `FireWebhookAction`, `SendSlackNotificationAction`) orchestrated by `DocumentRouter` — swap in your own handlers without touching the classifier. Categories and prompt instructions are fully configurable via `config/classifier.php`.

---

## Setup

### Requirements

- PHP 8.2+
- Composer
- A Claude API key ([get one here](https://console.anthropic.com/))
- SQLite (built into PHP — zero infrastructure for local dev)

### Install

```bash
git clone https://github.com/yourname/laravel-ai-document-classifier.git
cd laravel-ai-document-classifier
composer install
cp .env.example .env
php artisan key:generate
```

### Configure

Edit `.env` and set your Claude API key:

```dotenv
ANTHROPIC_API_KEY=sk-ant-api03-...
```

Optional routing (leave disabled to just use DB logging):

```dotenv
WEBHOOK_ENABLED=true
WEBHOOK_URL=https://your-endpoint.example.com/webhook
WEBHOOK_SECRET=your-hmac-secret

SLACK_ENABLED=true
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

### Migrate

```bash
php artisan migrate
```

### Test it

```bash
# Via Artisan
php artisan classify:document --text="Please help, my order #12345 has not arrived after 3 weeks. I would like a refund."

# Via file
php artisan classify:document --file="storage/app/samples/invoice-sample.txt"

# Via HTTP (start server first)
php artisan serve

curl -X POST http://localhost:8000/api/classify \
  -H "Content-Type: application/json" \
  -d '{"text": "This agreement is entered into between Company A and Company B for the provision of software services."}'

# File upload
curl -X POST http://localhost:8000/api/classify \
  -F "file=@/path/to/document.pdf"
```

---

## Adapting this to your use case

The classifier prompt is the only thing that needs changing for most use cases. Open `config/classifier.php` and edit the `categories` array:

```php
'categories' => [
    'complaint'    => ['label' => 'Complaint',    'description' => 'A formal complaint from a customer.'],
    'inquiry'      => ['label' => 'Inquiry',      'description' => 'A general product or pricing question.'],
    'order_update' => ['label' => 'Order Update', 'description' => 'A message about an existing order status.'],
],
```

No code changes required. Want to add a new routing destination? Add an action class in `app/Actions/` and call it from `DocumentRouter::route()`.

You can also tune the prompt without redeploying by setting `CLASSIFIER_EXTRA_INSTRUCTIONS` in `.env`:

```dotenv
CLASSIFIER_EXTRA_INSTRUCTIONS="Focus on financial documents. When in doubt, prefer 'invoice'."
```

---

## Project structure

```
app/
  Actions/
    LogClassificationAction.php      # writes to DB
    FireWebhookAction.php            # HTTP POST with HMAC signature
    SendSlackNotificationAction.php  # Slack Block Kit message
  Console/Commands/
    ClassifyDocument.php             # php artisan classify:document
  DTOs/
    ClassificationResult.php         # immutable result value object
  Exceptions/
    ClassificationException.php
  Http/Controllers/
    ClassifyController.php           # POST /api/classify
  Models/
    Classification.php
  Services/
    DocumentClassifierService.php    # Claude API call + JSON parsing
    DocumentRouter.php               # chains the action classes
config/
  classifier.php                     # all configuration lives here
database/migrations/
  ..._create_classifications_table.php
routes/
  api.php                            # POST /api/classify
```

---

## What this isn't

- **Not production-ready as-is** — no auth on the endpoint, no rate limiting. Add those before exposing it publicly.
- **Not a SaaS product or platform** — it's a starting point you own entirely. Fork it, strip what you don't need, and build on top.

---

## License

MIT
