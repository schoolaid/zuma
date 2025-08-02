<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\TokenizeRequest;

class Tokenize extends BaseAction
{
    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->request = new TokenizeRequest();
    }

    public function getCardTokenId(): ?string
    {
        return $this->response['card_token_id'] ?? null;
    }

    public function getPaymentInstrumentTokenId(): ?string
    {
        return $this->response['payment_instrument_token_id'] ?? null;
    }
}