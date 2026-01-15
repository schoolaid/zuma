<?php

namespace SchoolAid\Zuma\Requests;

class ReverseRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/reverse';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return [
            'transaction_id'
        ];
    }
}
