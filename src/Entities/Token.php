<?php

namespace SchoolAid\Zuma\Entities;

class Token
{
    private string $cardTokenId;
    private string $paymentInstrumentTokenId;
    private ?string $lastFourDigits;
    private ?string $expiryDate;
    private ?string $cardType;

    public function __construct(array $data)
    {
        $this->cardTokenId = $data['card_token_id'];
        $this->paymentInstrumentTokenId = $data['payment_instrument_token_id'];
        $this->lastFourDigits = $data['last_four_digits'] ?? null;
        $this->expiryDate = $data['expiry_date'] ?? null;
        $this->cardType = $data['card_type'] ?? null;
    }

    public function getCardTokenId(): string
    {
        return $this->cardTokenId;
    }

    public function getPaymentInstrumentTokenId(): string
    {
        return $this->paymentInstrumentTokenId;
    }

    public function getLastFourDigits(): ?string
    {
        return $this->lastFourDigits;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    public function toArray(): array
    {
        return [
            'card_token_id' => $this->cardTokenId,
            'payment_instrument_token_id' => $this->paymentInstrumentTokenId,
            'last_four_digits' => $this->lastFourDigits,
            'expiry_date' => $this->expiryDate,
            'card_type' => $this->cardType,
        ];
    }

    public function getDisplayString(): string
    {
        $display = '';
        
        if ($this->cardType) {
            $display .= $this->cardType . ' ';
        }
        
        if ($this->lastFourDigits) {
            $display .= '****' . $this->lastFourDigits;
        }
        
        if ($this->expiryDate) {
            $display .= ' (' . $this->expiryDate . ')';
        }
        
        return trim($display);
    }
}