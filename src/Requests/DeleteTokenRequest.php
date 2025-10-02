<?php

namespace SchoolAid\Zuma\Requests;

class DeleteTokenRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/delete/token';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return ['token'];
    }
}
