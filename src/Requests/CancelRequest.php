<?php

namespace SchoolAid\Zuma\Requests;

class CancelRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/cancel';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return ['transaction_id'];
    }
}