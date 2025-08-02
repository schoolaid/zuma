<?php

namespace SchoolAid\Zuma\Requests;

class ThreeDSContinueRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/3ds/continue';
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    protected function getRequiredFields(): array
    {
        return [
            'step',
            'reference_id'
        ];
    }
}