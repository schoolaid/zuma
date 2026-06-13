# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package providing integration with the Zuma payment platform API. The package handles authentication, tokenization, and various payment operations including 3D Secure payments.

## Core Architecture

### Three-Layer Pattern

The codebase follows a three-layer architecture:

1. **Client Layer** (`src/Client.php`): Handles HTTP communication, authentication, and token caching
   - Automatically authenticates via `/commerce/login` when needed
   - Caches authentication tokens using Laravel's Cache facade with configurable TTL
   - Auto-retries requests on 401 responses by re-authenticating

2. **Request Layer** (`src/Requests/`): Defines API endpoints, HTTP methods, and validates required fields
   - Each Request class extends `BaseRequest`
   - Implements `getEndpoint()`, `getMethod()`, and `getRequiredFields()`

3. **Action Layer** (`src/Actions/`): Public API surface that combines Client and Request
   - Each Action class extends `BaseAction`
   - Provides fluent interface: `->setBody()->submit()`
   - Contains response helper methods specific to each action type

### Usage Pattern

All actions follow this pattern:
```php
$response = ActionClass::getInstance($client)
    ->setBody(['field' => 'value'])
    ->submit();
```

The Action delegates to its Request for validation/endpoint info, then uses Client to execute.

## Development Commands

### Testing
```bash
composer test                    # Run all tests
vendor/bin/phpunit              # Direct PHPUnit execution
vendor/bin/phpunit --filter TestName  # Run specific test
```

### Installation & Setup
```bash
composer install                # Install dependencies
composer require schoolaid/zuma # Install in a Laravel app
php artisan vendor:publish --tag=zuma-config  # Publish config
```

### Code Quality
```bash
composer dump-autoload          # Regenerate autoload files
```

## Configuration

The package uses `config/zuma.php` for configuration, which is published to consuming Laravel apps. Configuration values are loaded from environment variables:

- `ZUMA_BASE_URL`: API base URL
- `ZUMA_USERNAME`: Authentication username
- `ZUMA_PASSWORD`: Authentication password
- `ZUMA_TIMEOUT`: HTTP request timeout (default: 30s)
- `ZUMA_VERIFY_SSL`: SSL verification flag (default: true)
- `ZUMA_TOKEN_TTL`: Auth token cache lifetime (default: 3600s)

## Service Provider Registration

The package auto-registers via Laravel's package discovery:
- Provider: `SchoolAid\Zuma\ZumaServiceProvider`
- Facade alias: `Zuma` → `SchoolAid\Zuma\Facades\Zuma`

The Client is bound as a singleton in the service container with key `'zuma'`.

## Available Actions & API Endpoints

Each action has a corresponding Request class that defines its endpoint and required fields:

### Core Payment Operations

- **Tokenize** (`POST /commerce/tokenize`): Tokenize card data for secure storage
  - Required: `card_number`, `expiry_date`, `first_name`, `last_name`, `address1`, `city`, `state`, `postal_code`, `country`, `email`
  - Returns: `card_token_id`, `payment_instrument_token_id`
  - Uses `TMS` operation credentials

- **PaymentToken** (`POST /commerce/payment/token`): Process payment using a tokenized card
  - Required: `amount`, `token`, `cvv`
  - Optional: `email` (sends payment voucher when provided and payment approved)
  - Optional: `payments` (integer installments / "cuotas") — allowed: `3, 6, 10, 12, 18, 24`; omitted/`0`/`1` = single payment; any other value throws `\InvalidArgumentException` before the request
  - Response code `00` indicates approval
  - Auto-reversal triggered on HTTP errors/timeouts (not business rejections)

- **Cancel** (`POST /commerce/cancel`): Cancel a completed transaction (020000 message type)
  - Required: `transaction_id`
  - Use for business-level cancellations of successful transactions
  - Auto-reversal triggered on HTTP errors/timeouts

- **DeleteToken** (`POST /commerce/delete/token`): Remove a tokenized payment instrument
  - Required: `token`
  - Returns success message: "PAYMENTINSTRUMENT DELETED SUCCESSFULLY"

### 3D Secure Operations

The 3DS endpoint automatically detects flow type based on request fields:

- **ThreeDSSale** (`POST /commerce/3ds/sale`): Initiate 3D Secure payment
  - **Payment Flow** (when `card_number` provided): Uses `Operation3DS` credentials
    - Required: `amount`, `card_number`, `expiry_date`, `url_commerce`, `first_name`, `last_name`, `address1`, `city`, `country`, `email`
  - **TMS Flow** (when `token` provided): Uses `Operation3DSTMS` credentials
    - Required: `amount`, `token`, `url_commerce`
    - Optional: `email` (sends payment voucher when provided and payment approved)
  - Returns: `reference_id`, `redirect_url`, `access_token`, `transactionId`
  - Optional: `payments` (integer installments / "cuotas") on the **sale/step-1 request only** — allowed: `3, 6, 10, 12, 18, 24`; omitted/`0`/`1` = single payment. Send the **full** `amount` (do not pre-divide). Offer installments **only for Visa** cards/tokens. Never send `payments` on `/commerce/3ds/continue` — the gateway inherits it from step 1
  - Type Operation 1: Direct approval/rejection without authentication
  - Type Operation 3: Requires user redirect for authentication (codes `00` or `10`)
  - Auto-reversal triggered on HTTP errors/timeouts

- **ThreeDSContinue** (`POST /commerce/3ds/continue`): Complete 3DS flow after user authentication
  - Required: `step`, `reference_id`, `transaction_id`
  - Auto-reversal triggered on HTTP errors/timeouts

### Reversal Operation

- **Reverse** (`POST /commerce/reverse`): Manual reversal for technical failures (0400 message type)
  - Required: `transaction_id`
  - Use for undoing transactions with technical issues (timeouts, connection errors)
  - Uses same credentials as original transaction
  - No restrictions - can be called for any transaction status

## Response Handling

All actions inherit base response methods:
- `isSuccess()`: Check if `success === true` in response
- `getCode()`: Get response code (e.g., "00" for approved)
- `getMessage()`: Get response message
- `getResponse()`: Get full response array

Specific actions add specialized methods (e.g., `isApproved()` in PaymentToken checks for code "00").

### Common Response Codes

| Code | Description                  |
|------|------------------------------|
| 00   | Approved/Success             |
| 01   | Refer to card issuer         |
| 05   | Do not honor                 |
| 12   | Invalid transaction          |
| 13   | Invalid amount               |
| 14   | Invalid card number          |
| 51   | Insufficient funds           |
| 54   | Expired card                 |
| 91   | Issuer unavailable           |
| 96   | System malfunction           |

### Transaction Response Fields

Successful payment responses include:
- `transactionId`: For tracking and cancellation operations
- `authorizationCode`: Authorization code from payment processor
- `sequence`: Transaction sequence number
- `referenceNumber`: Gateway tracking reference

## Adding New Actions

To add a new payment action:

1. Create Request class in `src/Requests/` extending `BaseRequest`:
   - Implement `getEndpoint()` and `getMethod()`
   - Define `getRequiredFields()` if validation needed

2. Create Action class in `src/Actions/` extending `BaseAction`:
   - Pass Request instance in constructor
   - Add response helper methods as needed

3. The action automatically inherits the fluent interface from `BaseAction`

## Important API Behavior

### Authentication
- JWT tokens are obtained from `POST /commerce/login` and cached per username
- Client automatically re-authenticates on 401 responses
- User context is extracted from JWT token (no need to pass `user_id` in requests)

### Operation Types & Credentials
The Zuma API uses different credential sets based on operation type:
- **TMS**: Standard tokenization and payment operations
- **Operation3DS**: 3D Secure with direct card data
- **Operation3DSTMS**: 3D Secure with tokenized data

The system automatically selects credentials based on request fields.

### Automatic Reversal Logic
Reversals (0400 message) are automatically attempted when operations fail due to HTTP errors or timeouts:
- Triggered for: PaymentToken, Cancel, ThreeDSSale, ThreeDSContinue
- NOT triggered for business rejections (response code != `00`)
- Uses exact same credentials as the original transaction
- All attempts logged in `transaction_request_response` table
- Manual retry available via `/commerce/reverse` endpoint

### Reversal vs Cancel
- **Reversal (0400)**: Technical failure recovery - undoes incomplete transactions
- **Cancel (020000)**: Business operation - cancels successfully completed transactions

### 3D Secure Flow Types
- **Type Operation 1**: Immediate approval/rejection, no additional steps
- **Type Operation 3**: Requires user authentication
  - Response codes `00` or `10` indicate authentication needed
  - Redirect user to `redirect_url` for authentication
  - Complete flow with `/commerce/3ds/continue` using `reference_id`

### Installments (Cuotas)

The optional `payments` integer enables installment payments on `POST /commerce/payment/token`
and `POST /commerce/3ds/sale` (step 1 only). The package validates it client-side before any
network call:
- Allowed installment counts: `3, 6, 10, 12, 18, 24` (`BaseRequest::ALLOWED_INSTALLMENTS`).
- Omitted, `null`, `0`, or `1` mean a single payment (contado) — unchanged behavior.
- Any other value throws `\InvalidArgumentException` from `validate()` (no transaction created).
- Installments are a Visa product: the **caller** must restrict cuotas to Visa cards/tokens.
- Send the **full** amount; the gateway splits it across installments.
- Never send `payments` on `/commerce/3ds/continue`.

### Email Voucher
When `email` is provided and payment is approved (code `00`):
- Payment voucher sent asynchronously (doesn't delay API response)
- Includes: date/time, amount, cardholder name, masked card number, reference number, authorization code
- Invalid emails are silently ignored
- BCC copy sent to info@zumapagos.com.gt

### Security Considerations
- Never store CVV values
- Always use HTTPS in production
- JWT tokens expire - implement refresh logic
- Card numbers should only be transmitted over secure connections

## Package Requirements

- PHP 8.0+
- Laravel 8-12
- GuzzleHTTP 7.0+
