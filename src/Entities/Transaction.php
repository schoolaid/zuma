<?php

namespace SchoolAid\Zuma\Entities;

class Transaction
{
    private ?int $transactionId;
    private float $amount;
    private string $status;
    private ?string $code;
    private ?string $message;
    private ?string $referenceId;
    private array $rawResponse;

    public function __construct(array $response)
    {
        $this->transactionId = $response['transaction_id'] ?? null;
        $this->amount = $response['amount'] ?? 0.0;
        $this->status = $response['success'] ? 'success' : 'failed';
        $this->code = $response['code'] ?? null;
        $this->message = $response['message'] ?? null;
        $this->referenceId = $response['reference_id'] ?? null;
        $this->rawResponse = $response;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isApproved(): bool
    {
        return $this->code === '00';
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'status' => $this->status,
            'code' => $this->code,
            'message' => $this->message,
            'reference_id' => $this->referenceId,
        ];
    }
}