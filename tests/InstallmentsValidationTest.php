<?php

namespace SchoolAid\Zuma\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use SchoolAid\Zuma\Actions\PaymentToken;
use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\PaymentTokenRequest;
use SchoolAid\Zuma\Requests\ThreeDSContinueRequest;
use SchoolAid\Zuma\Requests\ThreeDSSaleRequest;

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
}
