# Installments (Cuotas) — Integration Notes

**Package:** `schoolaid/zuma`
**Since:** `1.0.0`
**Status:** Additive, backward-compatible. Omit `payments` and behavior is unchanged.

## What changed

An optional integer **`payments`** field was added to two actions, letting a cardholder
pay in installments ("cuotas"). The package validates the value **client-side, before any
HTTP call**, and otherwise passes it straight through to the gateway.

| Action | Endpoint | Accepts `payments`? |
|---|---|---|
| `PaymentToken` | `POST /commerce/payment/token` | ✅ yes |
| `ThreeDSSale` | `POST /commerce/3ds/sale` (step 1) | ✅ yes |
| `ThreeDSContinue` | `POST /commerce/3ds/continue` | ❌ never — inherited from step 1 |

## The `payments` field

| Property | Value |
|---|---|
| Type | integer |
| Required | No |
| Omitted / `null` / `0` / `1` | Single payment (contado) — unchanged behavior |
| Allowed installment counts | **`3, 6, 10, 12, 18, 24`** |
| Any other value (`2`, `5`, `9`, `15`, …) | Throws `\InvalidArgumentException` — no request sent |

Allowed counts are exposed for building a UI selector:

```php
SchoolAid\Zuma\Requests\BaseRequest::ALLOWED_INSTALLMENTS; // [3, 6, 10, 12, 18, 24]
```

## Usage

`payments` goes in the existing `setBody([...])` array — no new method:

```php
use SchoolAid\Zuma\Actions\PaymentToken;

$payment = PaymentToken::getInstance($client)
    ->setBody([
        'amount'   => 1200.00,   // FULL amount — do not pre-divide
        'token'    => 'pi_tok_123',
        'cvv'      => '123',
        'email'    => 'customer@example.com',
        'payments' => 6,
    ])
    ->submit();

$payment->isApproved(); // code "00"
```

3DS sale — send `payments` on **step 1 only**:

```php
use SchoolAid\Zuma\Actions\ThreeDSSale;

ThreeDSSale::getInstance($client)
    ->setBody([
        'type'         => 'tms',
        'amount'       => 1500.00,
        'token'        => 'pi_tok_123',
        'cvv'          => '123',
        'url_commerce' => 'https://merchant.example.com/callback',
        'payments'     => 12,
    ])
    ->submit();
```

Then drive `ThreeDSContinue` (steps 3/5) exactly as before — **no `payments`**. The gateway
re-associates the continuation with the step-1 sale and applies the installments.

## Rules for the integrating service

- **Visa only.** Installments are a Visa product ("Visa en Cuotas"). Offer cuotas only for
  Visa cards (PAN/BIN starting with `4`); for the token flow, only for tokens you know are
  Visa. The package does **not** enforce this — it cannot see the brand behind a token.
- **Full amount.** Send the whole transaction amount in `amount`; the gateway/issuer splits
  it across the installments ("a precio de contado"). Never pre-divide.
- **Step 1 only.** Send `payments` on `PaymentToken` or on `ThreeDSSale` (step 1). Never on
  `ThreeDSContinue`.
- **UI options.** Offer only `3, 6, 10, 12, 18, 24` (plus single payment). Note `9` is **not**
  accepted on this e-commerce path.

## Error handling

An unsupported value throws **before** any transaction is created:

```php
use InvalidArgumentException;

try {
    PaymentToken::getInstance($client)
        ->setBody(['amount' => 100, 'token' => 'pi_tok_123', 'cvv' => '123', 'payments' => 5])
        ->submit();
} catch (InvalidArgumentException $e) {
    // "Invalid installments value 5: allowed values are 1 (single payment), 3, 6, 10, 12, 18, 24."
}
```

Validate against `{1, 3, 6, 10, 12, 18, 24}` in your own UI first to give a friendlier
message; treat the exception as a fallback "installment count not supported".
