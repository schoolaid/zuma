<?php

namespace SchoolAid\Zuma\Requests;

class PaymentTokenRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/payment/token';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return [
            'user_id',
            'amount',
            'token',
            'cvv'
        ];
    }
}