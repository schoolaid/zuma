<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\PaymentTokenRequest;

class PaymentToken extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new PaymentTokenRequest();
    }

    public function isApproved(): bool
    {
        return $this->getCode() === '00';
    }
}