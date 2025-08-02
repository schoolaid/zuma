<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\CancelRequest;

class Cancel extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new CancelRequest();
    }

    public function getTransactionId(): ?int
    {
        return $this->response['transaction_id'] ?? null;
    }
}