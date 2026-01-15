<?php

namespace SchoolAid\Zuma\Requests;

abstract class BaseRequest
{
    protected array $data = [];
    protected bool $reversible = false;

    abstract public function getEndpoint(): string;

    abstract public function getMethod(): string;

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isReversible(): bool
    {
        return $this->reversible;
    }

    public function validate(): void
    {
        $required = $this->getRequiredFields();

        foreach ($required as $field) {
            if (!isset($this->data[$field]) || empty($this->data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }
    }

    protected function getRequiredFields(): array
    {
        return [];
    }
}