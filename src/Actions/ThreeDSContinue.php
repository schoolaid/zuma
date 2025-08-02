<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\ThreeDSContinueRequest;

class ThreeDSContinue extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new ThreeDSContinueRequest();
    }

    public function getReferenceId(): ?string
    {
        return $this->response['reference_id'] ?? null;
    }

    public function isApproved(): bool
    {
        return $this->getCode() === '00';
    }
}