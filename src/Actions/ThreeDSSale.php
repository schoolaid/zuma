<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\ThreeDSSaleRequest;

class ThreeDSSale extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new ThreeDSSaleRequest();
    }

    public function getReferenceId(): ?string
    {
        return $this->response['reference_id'] ?? null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->response['redirect_url'] ?? null;
    }

    public function getAccessToken(): ?string
    {
        return $this->response['access_token'] ?? null;
    }
}