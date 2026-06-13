<?php

namespace SchoolAid\Zuma\Requests;

abstract class BaseRequest
{
    /**
     * Installment counts accepted by the epay e-commerce gateway
     * ("Visa en Cuotas" / VC## product). 0 or 1 means a single payment (contado).
     * Defined here (not on the trait) because trait constants require PHP 8.2,
     * while this package supports PHP ^8.0.
     */
    public const ALLOWED_INSTALLMENTS = [3, 6, 10, 12, 18, 24];

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