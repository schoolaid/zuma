# SchoolAid Zuma Payment Integration

A Laravel package for integrating with the Zuma payment platform API.

## Installation

```bash
composer require schoolaid/zuma
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=zuma-config
```

Add the following environment variables to your `.env` file:

```env
ZUMA_BASE_URL=https://api.zuma.com
ZUMA_USERNAME=your_username
ZUMA_PASSWORD=your_password
```

## Usage

### Using the Facade

```php
use SchoolAid\Zuma\Facades\Zuma;
use SchoolAid\Zuma\Actions\{Tokenize, PaymentToken};

// Tokenize a card
$response = Tokenize::getInstance(Zuma::getFacadeRoot())
    ->setBody([
        'card_number' => '4111111111111111',
        'expiry_date' => '1225',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US',
        'email' => 'john@example.com'
    ])
    ->submit();

if ($response->isSuccess()) {
    $token = $response->getPaymentInstrumentTokenId();
}

// Process payment with token
$payment = PaymentToken::getInstance(Zuma::getFacadeRoot())
    ->setBody([
        'amount' => 100.50,
        'token' => $token,
        'cvv' => '123'
    ])
    ->submit();

if ($payment->isApproved()) {
    // Payment successful
}
```

### Direct Client Usage

```php
use SchoolAid\Zuma\Client;
use SchoolAid\Zuma\Actions\ThreeDSSale;

$client = new Client(
    'https://api.zuma.com',
    'username',
    'password'
);

// 3D Secure payment
$response = ThreeDSSale::getInstance($client)
    ->setBody([
        'type' => 'payment',
        'amount' => 150.00,
        'card_number' => '4111111111111111',
        'expiry_date' => '1225',
        'cvv' => '123',
        'url_commerce' => 'https://merchant.com/callback',
        // ... other required fields
    ])
    ->submit();

if ($response->isSuccess()) {
    $redirectUrl = $response->getRedirectUrl();
    $referenceId = $response->getReferenceId();
}
```

## Available Actions

- `Tokenize` - Tokenize credit card information
- `PaymentToken` - Process payment using a token
- `Cancel` - Cancel a transaction
- `ThreeDSSale` - Initiate 3D Secure payment
- `ThreeDSContinue` - Continue 3D Secure payment flow

For detailed documentation on each action including request/response formats, see [Actions Documentation](docs/actions.md).

## Response Codes

| Code | Description |
|------|-------------|
| 00   | Approved/Success |
| 05   | Do not honor |
| 51   | Insufficient funds |
| 54   | Expired card |

See the full list in the API documentation.

## Error Handling

```php
try {
    $response = PaymentToken::getInstance($client)
        ->setBody($data)
        ->submit();
        
    if (!$response->isApproved()) {
        // Handle declined payment
        $errorCode = $response->getCode();
        $errorMessage = $response->getMessage();
    }
} catch (Exception $e) {
    // Handle API errors
    logger()->error('Payment failed: ' . $e->getMessage());
}
```