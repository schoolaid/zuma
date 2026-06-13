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
