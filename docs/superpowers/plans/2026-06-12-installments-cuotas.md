# E-commerce Installments (Cuotas) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional client-side-validated `payments` (installments / "cuotas") support to the `POST /commerce/payment/token` and `POST /commerce/3ds/sale` (step 1) requests, rejecting unsupported values before any network call.

**Architecture:** The body field already passes through `BaseAction::submit()` to the gateway untouched, so the only code needed is validation. A `public const ALLOWED_INSTALLMENTS` on `BaseRequest` holds the allowed counts (a normal class constant — trait constants would require PHP 8.2, but the package supports `^8.0`). A `ValidatesInstallments` trait holds the validation logic and is mixed into the two request classes, which override `validate()` to call `parent::validate()` then `validateInstallments()`. `ThreeDSContinueRequest` is left untouched.

**Tech Stack:** PHP 8.0+, Laravel (illuminate/support), GuzzleHTTP 7, PHPUnit 10, Mockery.

**Spec:** `docs/superpowers/specs/2026-06-12-installments-cuotas-design.md`

---

## File Structure

- **Create** `src/Requests/Concerns/ValidatesInstallments.php` — trait with `validateInstallments()`. Single responsibility: validate the optional `payments` field.
- **Modify** `src/Requests/BaseRequest.php` — add `public const ALLOWED_INSTALLMENTS = [3, 6, 10, 12, 18, 24];` (shared, inherited source of truth).
- **Modify** `src/Requests/PaymentTokenRequest.php` — `use ValidatesInstallments;` + `validate()` override.
- **Modify** `src/Requests/ThreeDSSaleRequest.php` — `use ValidatesInstallments;` + `validate()` override.
- **Create** `tests/InstallmentsValidationTest.php` — validation + pass-through + continue-ignores tests.
- **Modify** `CLAUDE.md` — document the `payments` field.

---

## Task 1: Constant + trait + PaymentTokenRequest wiring

**Files:**
- Create: `tests/InstallmentsValidationTest.php`
- Modify: `src/Requests/BaseRequest.php`
- Create: `src/Requests/Concerns/ValidatesInstallments.php`
- Modify: `src/Requests/PaymentTokenRequest.php`

- [ ] **Step 1: Write the failing test file**

Create `tests/InstallmentsValidationTest.php`:

```php
<?php

namespace SchoolAid\Zuma\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SchoolAid\Zuma\Requests\PaymentTokenRequest;

class InstallmentsValidationTest extends TestCase
{
    private function paymentTokenData(array $overrides = []): array
    {
        return array_merge([
            'amount' => 100.00,
            'token'  => 'pi_tok_123',
            'cvv'    => '123',
        ], $overrides);
    }

    public function test_allowed_installment_values_pass_for_payment_token(): void
    {
        foreach ([3, 6, 10, 12, 18, 24] as $value) {
            $request = (new PaymentTokenRequest())->setData($this->paymentTokenData(['payments' => $value]));
            $request->validate();
            $this->addToAssertionCount(1);
        }
    }

    public function test_single_payment_values_pass_for_payment_token(): void
    {
        foreach ([['payments' => 0], ['payments' => 1], ['payments' => null], []] as $override) {
            $request = (new PaymentTokenRequest())->setData($this->paymentTokenData($override));
            $request->validate();
            $this->addToAssertionCount(1);
        }
    }

    public function test_invalid_installment_values_throw_for_payment_token(): void
    {
        foreach ([2, 5, 9, 15] as $value) {
            $request = (new PaymentTokenRequest())->setData($this->paymentTokenData(['payments' => $value]));
            try {
                $request->validate();
                $this->fail("Expected InvalidArgumentException for payments={$value}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("Invalid installments value {$value}", $e->getMessage());
                $this->assertStringContainsString('3, 6, 10, 12, 18, 24', $e->getMessage());
            }
        }
    }

    public function test_non_integer_installments_throw_for_payment_token(): void
    {
        foreach (['x', 6.5] as $value) {
            $request = (new PaymentTokenRequest())->setData($this->paymentTokenData(['payments' => $value]));
            try {
                $request->validate();
                $this->fail('Expected InvalidArgumentException for non-integer payments');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('must be an integer', $e->getMessage());
            }
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter InstallmentsValidationTest`
Expected: FAIL — `test_invalid_installment_values_throw_for_payment_token` and `test_non_integer_installments_throw_for_payment_token` fail because `validate()` does not yet reject these values (no exception thrown → `$this->fail(...)` fires). The "pass" tests already pass since nothing rejects valid values.

- [ ] **Step 3: Add the shared constant to `BaseRequest`**

In `src/Requests/BaseRequest.php`, add the constant as the first member of the class body (immediately after `abstract class BaseRequest\n{`):

```php
    /**
     * Installment counts accepted by the epay e-commerce gateway
     * ("Visa en Cuotas" / VC## product). 0 or 1 means a single payment (contado).
     * Defined here (not on the trait) because trait constants require PHP 8.2,
     * while this package supports PHP ^8.0.
     */
    public const ALLOWED_INSTALLMENTS = [3, 6, 10, 12, 18, 24];

```

- [ ] **Step 4: Create the `ValidatesInstallments` trait**

Create `src/Requests/Concerns/ValidatesInstallments.php`:

```php
<?php

namespace SchoolAid\Zuma\Requests\Concerns;

use InvalidArgumentException;

trait ValidatesInstallments
{
    /**
     * Validate the optional `payments` (installments / "cuotas") field.
     *
     * Omitted, null, empty string, 0, or 1 all mean a single payment (contado)
     * and are accepted unchanged. Any other value must be one of
     * self::ALLOWED_INSTALLMENTS (inherited from BaseRequest).
     *
     * @throws InvalidArgumentException when `payments` is present but unsupported.
     */
    protected function validateInstallments(): void
    {
        if (!array_key_exists('payments', $this->data)) {
            return;
        }

        $payments = $this->data['payments'];

        if ($payments === null || $payments === '') {
            return;
        }

        if (!is_numeric($payments) || (int) $payments != $payments) {
            throw new InvalidArgumentException(
                "Invalid installments value: 'payments' must be an integer."
            );
        }

        $payments = (int) $payments;

        // 0 and 1 are single payment (contado).
        if ($payments === 0 || $payments === 1) {
            return;
        }

        if (!in_array($payments, self::ALLOWED_INSTALLMENTS, true)) {
            $allowed = implode(', ', self::ALLOWED_INSTALLMENTS);

            throw new InvalidArgumentException(
                "Invalid installments value {$payments}: allowed values are 1 (single payment), {$allowed}."
            );
        }
    }
}
```

- [ ] **Step 5: Wire the trait into `PaymentTokenRequest`**

Replace the entire contents of `src/Requests/PaymentTokenRequest.php` with:

```php
<?php

namespace SchoolAid\Zuma\Requests;

use SchoolAid\Zuma\Requests\Concerns\ValidatesInstallments;

class PaymentTokenRequest extends BaseRequest
{
    use ValidatesInstallments;

    protected bool $reversible = true;

    public function getEndpoint(): string
    {
        return '/commerce/payment/token';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    public function validate(): void
    {
        parent::validate();
        $this->validateInstallments();
    }

    protected function getRequiredFields(): array
    {
        return [
            'amount',
            'token',
        ];
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter InstallmentsValidationTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Requests/BaseRequest.php src/Requests/Concerns/ValidatesInstallments.php src/Requests/PaymentTokenRequest.php tests/InstallmentsValidationTest.php
git commit -m "feat: validate optional payments (installments) on PaymentToken"
```

---

## Task 2: Wire installments into `ThreeDSSaleRequest`

**Files:**
- Modify: `tests/InstallmentsValidationTest.php`
- Modify: `src/Requests/ThreeDSSaleRequest.php`

- [ ] **Step 1: Add failing tests for ThreeDSSaleRequest**

In `tests/InstallmentsValidationTest.php`, add `use SchoolAid\Zuma\Requests\ThreeDSSaleRequest;` to the imports (below the `PaymentTokenRequest` import), and add these methods inside the class (after the existing test methods):

```php
    private function threeDSSaleData(array $overrides = []): array
    {
        return array_merge([
            'type'         => 'tms',
            'amount'       => 100.00,
            'url_commerce' => 'https://merchant.example.com/callback',
            'token'        => 'pi_tok_123',
            'cvv'          => '123',
        ], $overrides);
    }

    public function test_allowed_installment_values_pass_for_three_ds_sale(): void
    {
        foreach ([3, 6, 10, 12, 18, 24] as $value) {
            $request = (new ThreeDSSaleRequest())->setData($this->threeDSSaleData(['payments' => $value]));
            $request->validate();
            $this->addToAssertionCount(1);
        }
    }

    public function test_single_payment_values_pass_for_three_ds_sale(): void
    {
        foreach ([['payments' => 0], ['payments' => 1], ['payments' => null], []] as $override) {
            $request = (new ThreeDSSaleRequest())->setData($this->threeDSSaleData($override));
            $request->validate();
            $this->addToAssertionCount(1);
        }
    }

    public function test_invalid_installment_values_throw_for_three_ds_sale(): void
    {
        foreach ([2, 5, 9, 15] as $value) {
            $request = (new ThreeDSSaleRequest())->setData($this->threeDSSaleData(['payments' => $value]));
            try {
                $request->validate();
                $this->fail("Expected InvalidArgumentException for payments={$value}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("Invalid installments value {$value}", $e->getMessage());
                $this->assertStringContainsString('3, 6, 10, 12, 18, 24', $e->getMessage());
            }
        }
    }
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `vendor/bin/phpunit --filter test_invalid_installment_values_throw_for_three_ds_sale`
Expected: FAIL — no exception is thrown yet for invalid values, so `$this->fail(...)` fires.

- [ ] **Step 3: Wire the trait into `ThreeDSSaleRequest`**

Replace the entire contents of `src/Requests/ThreeDSSaleRequest.php` with:

```php
<?php

namespace SchoolAid\Zuma\Requests;

use SchoolAid\Zuma\Requests\Concerns\ValidatesInstallments;

class ThreeDSSaleRequest extends BaseRequest
{
    use ValidatesInstallments;

    public function getEndpoint(): string
    {
        return '/commerce/3ds/sale';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    public function validate(): void
    {
        parent::validate();
        $this->validateInstallments();
    }

    protected function getRequiredFields(): array
    {
        $type = $this->data['type'] ?? null;

        $baseFields = ['type', 'amount', 'url_commerce'];

        if ($type === 'payment') {
            return array_merge($baseFields, [
                'card_number',
                'expiry_date',
                'cvv',
                'first_name',
                'last_name',
                'address1',
                'city',
                'state',
                'postal_code',
                'country',
                'email'
            ]);
        } elseif ($type === 'tms') {
            return array_merge($baseFields, [
                'token',
                'cvv'
            ]);
        }

        return $baseFields;
    }
}
```

- [ ] **Step 4: Run the full test file to verify it passes**

Run: `vendor/bin/phpunit --filter InstallmentsValidationTest`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add tests/InstallmentsValidationTest.php src/Requests/ThreeDSSaleRequest.php
git commit -m "feat: validate optional payments (installments) on ThreeDSSale"
```

---

## Task 3: Pass-through + continue-ignores tests

**Files:**
- Modify: `tests/InstallmentsValidationTest.php`

- [ ] **Step 1: Add a `tearDown` and the two behavioral tests**

In `tests/InstallmentsValidationTest.php`, add these imports below the existing `use` lines:

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use SchoolAid\Zuma\Actions\PaymentToken;
use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\ThreeDSContinueRequest;
```

Add a `tearDown` method and the two tests inside the class (after the existing methods):

```php
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payments_is_sent_in_payment_token_request_body(): void
    {
        Cache::shouldReceive('get')->andReturn('mock-token');
        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('forget')->andReturn(true);

        if (!function_exists('config')) {
            require_once __DIR__ . '/helpers.php';
        }

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'success'       => true,
                'code'          => '00',
                'message'       => 'Transaction approved',
                'transactionId' => 123,
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client('https://api.example.com', 'test-user', 'test-pass');

        $reflection = new \ReflectionClass(Client::class);

        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($client, new GuzzleClient([
            'handler'  => $stack,
            'base_uri' => 'https://api.example.com',
        ]));

        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($client, 'mock-jwt-token');

        PaymentToken::getInstance($client)
            ->setBody([
                'amount'   => 1200.00,
                'token'    => 'pi_tok_123',
                'cvv'      => '123',
                'payments' => 6,
            ])
            ->submit();

        $this->assertCount(1, $history);
        $sentBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayHasKey('payments', $sentBody);
        $this->assertSame(6, $sentBody['payments']);
    }

    public function test_three_ds_continue_does_not_reject_payments(): void
    {
        $request = (new ThreeDSContinueRequest())->setData([
            'step'           => 3,
            'reference_id'   => 'ref_123',
            'transaction_id' => 456,
            'payments'       => 999, // unsupported value; continue must NOT validate it
        ]);

        $request->validate();
        $this->addToAssertionCount(1);
    }
```

- [ ] **Step 2: Run the new tests**

Run: `vendor/bin/phpunit --filter InstallmentsValidationTest`
Expected: PASS (9 tests). The pass-through test proves `payments => 6` reaches the outgoing JSON body; the continue test proves `ThreeDSContinueRequest` does not validate `payments` (it would have thrown on `999` if it did).

- [ ] **Step 3: Commit**

```bash
git add tests/InstallmentsValidationTest.php
git commit -m "test: payments reaches request body and is ignored by 3ds/continue"
```

---

## Task 4: Document the `payments` field in `CLAUDE.md`

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update the PaymentToken endpoint description**

In `CLAUDE.md`, find the `**PaymentToken**` bullet block. After its existing `- Optional: \`email\` ...` line, add:

```markdown
  - Optional: `payments` (integer installments / "cuotas") — allowed: `3, 6, 10, 12, 18, 24`; omitted/`0`/`1` = single payment; any other value throws `\InvalidArgumentException` before the request
```

- [ ] **Step 2: Update the ThreeDSSale endpoint description**

In `CLAUDE.md`, find the `**ThreeDSSale**` block. After the `Returns: reference_id, redirect_url, ...` line (still inside the ThreeDSSale bullet), add:

```markdown
  - Optional: `payments` (integer installments / "cuotas") on the **sale/step-1 request only** — allowed: `3, 6, 10, 12, 18, 24`; omitted/`0`/`1` = single payment. Send the **full** `amount` (do not pre-divide). Offer installments **only for Visa** cards/tokens. Never send `payments` on `/commerce/3ds/continue` — the gateway inherits it from step 1
```

- [ ] **Step 3: Add an Installments subsection under "Important API Behavior"**

In `CLAUDE.md`, locate the `### Email Voucher` subsection inside `## Important API Behavior`. Immediately before it, insert:

```markdown
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

```

- [ ] **Step 4: Verify the full suite still passes**

Run: `vendor/bin/phpunit`
Expected: PASS — all suites green (3 pre-existing + 9 new = 12 tests).

- [ ] **Step 5: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: document optional payments (installments) field"
```

---

## Self-Review Notes

- **Spec coverage:** trait/constant (Task 1) ✓, PaymentToken wiring (Task 1) ✓, ThreeDSSale wiring (Task 2) ✓, ThreeDSContinue untouched (Task 3 test) ✓, validation rules incl. non-integer & 0/1 (Task 1 tests) ✓, pass-through (Task 3) ✓, docs (Task 4) ✓, no Visa enforcement / no amount split (validated by absence — nothing in the plan does either) ✓.
- **PHP 8.0 compatibility:** constant lives on `BaseRequest` (class const), not the trait (trait consts need 8.2). Captured in Task 1 Step 3.
- **Type consistency:** trait method `validateInstallments()` and constant `ALLOWED_INSTALLMENTS` are referenced identically everywhere; `validate()` override signature (`public function validate(): void`) matches `BaseRequest::validate()`.
