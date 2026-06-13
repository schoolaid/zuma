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
