<?php

namespace SchoolAid\Zuma\Requests;

class ThreeDSSaleRequest extends BaseRequest
{
    public function getEndpoint(): string
    {
        return '/commerce/3ds/sale';
    }

    public function getMethod(): string
    {
        return 'POST';
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