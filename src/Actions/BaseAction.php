<?php

namespace SchoolAid\Zuma\Actions;

use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Requests\BaseRequest;

abstract class BaseAction
{
    protected Client $client;
    protected BaseRequest $request;
    protected array $response = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function getInstance(Client $client): self
    {
        return new static($client);
    }

    public function setBody(array $data): self
    {
        $this->request->setData($data);
        return $this;
    }

    public function submit(): self
    {
        $this->request->validate();
        
        $this->response = $this->client->request(
            $this->request->getMethod(),
            $this->request->getEndpoint(),
            $this->request->getData()
        );
        
        return $this;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function isSuccess(): bool
    {
        return ($this->response['success'] ?? false) === true;
    }

    public function getCode(): ?string
    {
        return $this->response['code'] ?? null;
    }

    public function getMessage(): ?string
    {
        return $this->response['message'] ?? null;
    }
}