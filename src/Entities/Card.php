<?php

namespace SchoolAid\Zuma\Entities;

class Card
{
    private string $cardNumber;
    private string $expiryDate;
    private string $cvv;
    private string $firstName;
    private string $lastName;
    private ?string $company;
    private string $address1;
    private ?string $address2;
    private string $city;
    private string $state;
    private string $postalCode;
    private string $country;
    private string $email;
    private ?string $phone;

    public function __construct(array $data)
    {
        $this->cardNumber = $data['card_number'];
        $this->expiryDate = $data['expiry_date'];
        $this->cvv = $data['cvv'] ?? '';
        $this->firstName = $data['first_name'];
        $this->lastName = $data['last_name'];
        $this->company = $data['company'] ?? null;
        $this->address1 = $data['address1'];
        $this->address2 = $data['address2'] ?? null;
        $this->city = $data['city'];
        $this->state = $data['state'];
        $this->postalCode = $data['postal_code'];
        $this->country = $data['country'];
        $this->email = $data['email'];
        $this->phone = $data['phone'] ?? null;
    }

    public function toArray(): array
    {
        $data = [
            'card_number' => $this->cardNumber,
            'expiry_date' => $this->expiryDate,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'address1' => $this->address1,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'email' => $this->email,
        ];

        if ($this->cvv) {
            $data['cvv'] = $this->cvv;
        }

        if ($this->company) {
            $data['company'] = $this->company;
        }

        if ($this->address2) {
            $data['address2'] = $this->address2;
        }

        if ($this->phone) {
            $data['phone'] = $this->phone;
        }

        return $data;
    }

    public function getMaskedCardNumber(): string
    {
        $length = strlen($this->cardNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($this->cardNumber, -4);
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function getExpiryDate(): string
    {
        return $this->expiryDate;
    }

    public function getCvv(): string
    {
        return $this->cvv;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}