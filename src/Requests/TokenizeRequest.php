<?php

namespace SchoolAid\Zuma\Requests;

class TokenizeRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/tokenize';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return [
            'card_number',
            'expiry_date',
            'first_name',
            'last_name',
            'address1',
            'city',
            'state',
            'postal_code',
            'country',
            'email'
        ];
    }
}