<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\ReverseRequest;

class Reverse extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new ReverseRequest();
    }

    public function getTransactionId(): ?int
    {
        return $this->response['transaction_id'] ?? null;
    }
}
