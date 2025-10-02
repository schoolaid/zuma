<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\DeleteTokenRequest;

class DeleteToken extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new DeleteTokenRequest();
    }

    public function isDeleted(): bool
    {
        return ($this->response['deleted'] ?? false) === true;
    }
}
