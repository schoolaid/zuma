# Design — E-commerce Installments (Cuotas)

**Date:** 2026-06-12
**Status:** Approved
**Scope:** Additive, backward-compatible support for the optional `payments`
(installments / "cuotas") field on the two commerce payment endpoints.

## Background

`payments-api` 1.1.27 added an optional integer `payments` field to two commerce
endpoints. It lets a cardholder pay in installments ("cuotas"); internally the API
maps it to the epay gateway's `VC##` ("Visa en Cuotas") product. The integrating
service only sends the integer count.

| Endpoint | Request class | Installments? |
|---|---|---|
| `POST /commerce/payment/token` | `PaymentTokenRequest` | ✅ accepts `payments` |
| `POST /commerce/3ds/sale` | `ThreeDSSaleRequest` | ✅ accepts `payments` (step 1 only) |
| `POST /commerce/3ds/continue` | `ThreeDSContinueRequest` | ❌ never — inherited from step 1 |

The change is additive: sending nothing new keeps today's single-payment behavior.

## Key insight

`BaseAction::submit()` already forwards `$request->getData()` verbatim to the gateway
as the JSON body. A `payments` key placed in the `setBody([...])` array therefore
reaches the API with **no plumbing changes**. The only work this package needs to do
is **client-side validation** — rejecting unsupported values with a friendly message
*before* a network call, exactly as the integration guide recommends.

## API surface (decided)

The caller passes `payments` in the existing `setBody([...])` array, like every other
field. No new fluent helper method is added. A public constant exposes the allowed
installment counts so the consuming app can build its UI selector.

```php
PaymentToken::getInstance($client)
    ->setBody([
        'amount'   => 1200.00,
        'token'    => 'pi_tok_123',
        'cvv'      => '123',
        'payments' => 6,
    ])->submit();

// UI selector source:
PaymentTokenRequest::ALLOWED_INSTALLMENTS // [3, 6, 10, 12, 18, 24]
```

## Components

### 0. Shared constant — `BaseRequest::ALLOWED_INSTALLMENTS`

- `public const ALLOWED_INSTALLMENTS = [3, 6, 10, 12, 18, 24];` defined on `BaseRequest`.
  The six installment counts from the epay catalog (ePayServer v1.0.0.8). Note `9` is
  **not** accepted on this e-commerce path. Public so consuming apps can drive their UI
  via `PaymentTokenRequest::ALLOWED_INSTALLMENTS` (inherited).

  **Why on `BaseRequest` and not the trait:** PHP trait constants require PHP **8.2**,
  but this package declares `"php": "^8.0"`. A normal class constant on `BaseRequest`
  is inherited by both request classes, keeps a single source of truth (DRY), and works
  on every supported PHP version. The constant is data, not behavior, so it does not
  leak installments *logic* into the base class.

### 1. New trait — `src/Requests/Concerns/ValidatesInstallments.php`

Holds the installments validation *logic*, shared by the two requests that accept
`payments`, without touching the other requests. References the inherited
`self::ALLOWED_INSTALLMENTS` constant.

- `protected function validateInstallments(): void` — rules, in order:
  1. `payments` key absent, or value `null` / `''` → no-op (single payment / contado).
  2. Value is not an integer (e.g. `"abc"`, `6.5`) → throw `\InvalidArgumentException`.
  3. Value is `0` or `1` → valid (single payment / contado).
  4. Value is in `ALLOWED_INSTALLMENTS` → valid.
  5. Anything else (`2, 5, 9, 15, …`) → throw `\InvalidArgumentException` with message:
     `Invalid installments value 5: allowed values are 1 (single payment), 3, 6, 10, 12, 18, 24.`
     The message mirrors the gateway's wording so it is familiar, but it fails before
     any transaction is created.

"Integer" here means an `int`, or a numeric value with no fractional part
(`is_numeric($v) && (int) $v == $v`). A float like `6.0` is accepted as `6`; `6.5` is
rejected.

### 2. `PaymentTokenRequest` and `ThreeDSSaleRequest`

Each adds `use ValidatesInstallments;` and overrides `validate()`:

```php
public function validate(): void
{
    parent::validate();          // existing required-field checks
    $this->validateInstallments();
}
```

`ThreeDSSaleRequest` validates `payments` regardless of its `type` (`payment` vs `tms`);
the allowed-value rules are identical for both flows.

### 3. `ThreeDSContinueRequest`

Untouched. It never uses the trait, so the package neither validates nor encourages a
`payments` field there — the gateway re-associates the continuation with the step-1 sale
and applies the installments automatically.

## Error handling

Reuses the existing convention: `\InvalidArgumentException` thrown from `validate()`,
which runs inside `BaseAction::submit()` **before** the HTTP request. An invalid value
therefore never creates a transaction — matching the API's own
"HTTP 400 before any transaction is created" behavior, but without the round trip.

## Deliberately out of scope

- **No Visa-brand enforcement.** Installments are a Visa product. Restricting cuotas to
  Visa cards/tokens is a UI/business concern, and in the token flow the brand is hidden
  behind the token — the package cannot reliably know it. Documented as the caller's
  responsibility (guide checklist), not enforced here.
- **No amount splitting.** The full transaction amount is sent in `amount`, unchanged;
  the gateway/issuer splits it across installments.
- **No `setInstallments()` helper.** The body-key + constant surface is sufficient.

## Testing (TDD)

New `tests/InstallmentsValidationTest.php`:

- Each allowed value `{3, 6, 10, 12, 18, 24}` passes validation on both
  `PaymentTokenRequest` and `ThreeDSSaleRequest`.
- `0`, `1`, and an omitted `payments` key all pass (single payment).
- Each invalid value `{2, 5, 9, 15}` throws `\InvalidArgumentException` with the
  expected message.
- A non-integer value (`"x"`, `6.5`) throws `\InvalidArgumentException`.
- Pass-through: using a Guzzle history/mock handler, a `PaymentToken` submit with
  `payments => 6` produces an outgoing JSON body that contains `payments: 6`.
- `ThreeDSContinueRequest` does **not** reject a `payments` value (no validation there).

Tests follow the existing `tests/` style (PHPUnit `TestCase`, mocked `Cache` facade,
`config()` helper from `tests/helpers.php`, reflection-based Guzzle handler injection as
in `PaymentTokenTimeoutTest`).

## Documentation

Update `CLAUDE.md`:

- Add `payments` (optional) to the `PaymentToken` and `ThreeDSSale` (step-1) field lists,
  with the allowed set `{3, 6, 10, 12, 18, 24}` and single-payment note.
- State the Visa-only caller responsibility.
- State that `payments` must never be sent on `/commerce/3ds/continue`.
- Note the full-amount (no pre-divide) rule.
