# Zuma Actions Documentation

This document provides detailed information about all available actions in the Zuma payment integration package.

## Table of Contents
- [Tokenize](#tokenize)
- [PaymentToken](#paymenttoken)
- [Cancel](#cancel)
- [ThreeDSSale](#threedsssale)
- [ThreeDSContinue](#threedscontinue)

---

## Tokenize

Tokenizes credit card information for secure storage and future transactions.

### Usage

```php
use SchoolAid\Zuma\Actions\Tokenize;
use SchoolAid\Zuma\Facades\Zuma;

$response = Tokenize::getInstance(Zuma::getFacadeRoot())
    ->setBody($data)
    ->submit();
```

### Request Body

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `card_number` | string | Yes | Credit card number | `"4111111111111111"` |
| `expiry_date` | string | Yes | Card expiration date (MMYY) | `"1225"` |
| `first_name` | string | Yes | Cardholder's first name | `"John"` |
| `last_name` | string | Yes | Cardholder's last name | `"Doe"` |
| `company` | string | No | Company name | `"ACME Corp"` |
| `address1` | string | Yes | Primary address line | `"123 Main St"` |
| `address2` | string | No | Secondary address line | `"Apt 4B"` |
| `city` | string | Yes | City | `"New York"` |
| `state` | string | Yes | State/Province code | `"NY"` |
| `postal_code` | string | Yes | Postal/ZIP code | `"10001"` |
| `country` | string | Yes | 2-letter country code | `"US"` |
| `email` | string | Yes | Email address | `"john.doe@example.com"` |
| `phone` | string | No | Phone number | `"+1234567890"` |

### Example Request

```php
$tokenizeData = [
    'card_number' => '4111111111111111',
    'expiry_date' => '1225',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'company' => 'ACME Corp',
    'address1' => '123 Main St',
    'address2' => 'Apt 4B',
    'city' => 'New York',
    'state' => 'NY',
    'postal_code' => '10001',
    'country' => 'US',
    'email' => 'john.doe@example.com',
    'phone' => '+1234567890'
];

$response = Tokenize::getInstance($client)
    ->setBody($tokenizeData)
    ->submit();
```

### Response Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isSuccess()` | bool | Returns true if tokenization was successful |
| `getCardTokenId()` | ?string | Returns the card token ID |
| `getPaymentInstrumentTokenId()` | ?string | Returns the payment instrument token ID |
| `getMessage()` | ?string | Returns the response message |
| `getResponse()` | array | Returns the full response array |

### Example Response

```php
if ($response->isSuccess()) {
    $cardToken = $response->getCardTokenId(); // "tok_1234567890"
    $paymentToken = $response->getPaymentInstrumentTokenId(); // "pi_tok_1234567890"
}
```

---

## PaymentToken

Process a payment using a previously tokenized card.

### Usage

```php
use SchoolAid\Zuma\Actions\PaymentToken;
use SchoolAid\Zuma\Facades\Zuma;

$response = PaymentToken::getInstance(Zuma::getFacadeRoot())
    ->setBody($data)
    ->submit();
```

### Request Body

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `amount` | float | Yes | Transaction amount | `100.50` |
| `token` | string | Yes | Payment instrument token | `"pi_tok_1234567890"` |
| `cvv` | string | Yes | Card verification value | `"123"` |

**Note**: The user_id is automatically determined from the authentication token.

### Example Request

```php
$paymentData = [
    'amount' => 100.50,
    'token' => 'pi_tok_1234567890',
    'cvv' => '123'
];

$response = PaymentToken::getInstance($client)
    ->setBody($paymentData)
    ->submit();
```

### Response Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isSuccess()` | bool | Returns true if request was successful |
| `isApproved()` | bool | Returns true if payment was approved (code "00") |
| `getCode()` | ?string | Returns the response code |
| `getMessage()` | ?string | Returns the response message |
| `getResponse()` | array | Returns the full response array |

### Example Response

```php
if ($response->isApproved()) {
    echo "Payment approved!";
} else {
    $errorCode = $response->getCode(); // "51" (insufficient funds)
    $errorMessage = $response->getMessage(); // "Insufficient funds"
}
```

### Automatic Reversal

If a payment fails, the system automatically attempts to reverse the transaction to prevent partial charges.

---

## Cancel

Cancels a previously processed transaction.

### Usage

```php
use SchoolAid\Zuma\Actions\Cancel;
use SchoolAid\Zuma\Facades\Zuma;

$response = Cancel::getInstance(Zuma::getFacadeRoot())
    ->setBody($data)
    ->submit();
```

### Request Body

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `transaction_id` | integer | Yes | Transaction ID to cancel | `12345` |

### Example Request

```php
$cancelData = [
    'transaction_id' => 12345
];

$response = Cancel::getInstance($client)
    ->setBody($cancelData)
    ->submit();
```

### Response Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isSuccess()` | bool | Returns true if cancellation was successful |
| `getTransactionId()` | ?int | Returns the cancelled transaction ID |
| `getCode()` | ?string | Returns the response code |
| `getMessage()` | ?string | Returns the response message |
| `getResponse()` | array | Returns the full response array |

### Example Response

```php
if ($response->isSuccess()) {
    $transactionId = $response->getTransactionId(); // 12345
    echo "Transaction {$transactionId} cancelled successfully";
}
```

---

## ThreeDSSale

Initiates a 3D Secure payment flow. Supports two types: direct payment and token-based payment.

### Usage

```php
use SchoolAid\Zuma\Actions\ThreeDSSale;
use SchoolAid\Zuma\Facades\Zuma;

$response = ThreeDSSale::getInstance(Zuma::getFacadeRoot())
    ->setBody($data)
    ->submit();
```

### Request Body - Payment Type

For direct card payments:

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `type` | string | Yes | Must be "payment" | `"payment"` |
| `amount` | float | Yes | Transaction amount | `150.00` |
| `card_number` | string | Yes | Credit card number | `"4111111111111111"` |
| `expiry_date` | string | Yes | Card expiration (MMYY) | `"1225"` |
| `cvv` | string | Yes | Card verification value | `"123"` |
| `url_commerce` | string | Yes | Callback URL for 3DS | `"https://merchant.com/callback"` |
| `first_name` | string | Yes | Cardholder's first name | `"John"` |
| `last_name` | string | Yes | Cardholder's last name | `"Doe"` |
| `company` | string | No | Company name | `"ACME Corp"` |
| `address1` | string | Yes | Primary address line | `"123 Main St"` |
| `address2` | string | No | Secondary address line | `"Apt 4B"` |
| `city` | string | Yes | City | `"New York"` |
| `state` | string | Yes | State/Province code | `"NY"` |
| `postal_code` | string | Yes | Postal/ZIP code | `"10001"` |
| `country` | string | Yes | 2-letter country code | `"US"` |
| `email` | string | Yes | Email address | `"john.doe@example.com"` |
| `phone` | string | No | Phone number | `"+1234567890"` |

### Request Body - TMS Type

For token-based payments:

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `type` | string | Yes | Must be "tms" | `"tms"` |
| `amount` | float | Yes | Transaction amount | `150.00` |
| `token` | string | Yes | Payment instrument token | `"pi_tok_1234567890"` |
| `cvv` | string | Yes | Card verification value | `"123"` |
| `url_commerce` | string | Yes | Callback URL for 3DS | `"https://merchant.com/callback"` |

### Example Request - Payment Type

```php
$threeDSData = [
    'type' => 'payment',
    'amount' => 150.00,
    'card_number' => '4111111111111111',
    'expiry_date' => '1225',
    'cvv' => '123',
    'url_commerce' => 'https://merchant.example.com/callback',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'company' => 'ACME Corp',
    'address1' => '123 Main St',
    'address2' => 'Apt 4B',
    'city' => 'New York',
    'state' => 'NY',
    'postal_code' => '10001',
    'country' => 'US',
    'email' => 'john.doe@example.com',
    'phone' => '+1234567890'
];

$response = ThreeDSSale::getInstance($client)
    ->setBody($threeDSData)
    ->submit();
```

### Example Request - TMS Type

```php
$threeDSData = [
    'type' => 'tms',
    'amount' => 150.00,
    'token' => 'pi_tok_1234567890',
    'cvv' => '123',
    'url_commerce' => 'https://merchant.example.com/callback'
];

$response = ThreeDSSale::getInstance($client)
    ->setBody($threeDSData)
    ->submit();
```

### Response Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isSuccess()` | bool | Returns true if 3DS initiation was successful |
| `getReferenceId()` | ?string | Returns the reference ID for continuing the flow |
| `getRedirectUrl()` | ?string | Returns the URL to redirect user for authentication |
| `getAccessToken()` | ?string | Returns the access token for 3DS flow |
| `getCode()` | ?string | Returns the response code |
| `getMessage()` | ?string | Returns the response message |
| `getResponse()` | array | Returns the full response array |

### Example Response

```php
if ($response->isSuccess()) {
    $referenceId = $response->getReferenceId(); // "ref_123456"
    $redirectUrl = $response->getRedirectUrl(); // "https://3ds.example.com/authenticate"
    $accessToken = $response->getAccessToken(); // "3ds_access_token_123"
    
    // Store reference ID for later use
    session(['3ds_reference' => $referenceId]);
    
    // Redirect user to 3DS authentication
    return redirect($redirectUrl);
}
```

---

## ThreeDSContinue

Continues the 3D Secure payment flow after user authentication.

### Usage

```php
use SchoolAid\Zuma\Actions\ThreeDSContinue;
use SchoolAid\Zuma\Facades\Zuma;

$response = ThreeDSContinue::getInstance(Zuma::getFacadeRoot())
    ->setBody($data)
    ->submit();
```

### Request Body

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `step` | string | Yes | Step in 3DS flow | `"3"` |
| `reference_id` | string | Yes | Reference ID from ThreeDSSale | `"ref_123456"` |

### Example Request

```php
// After user returns from 3DS authentication
$continueData = [
    'step' => '3',
    'reference_id' => session('3ds_reference')
];

$response = ThreeDSContinue::getInstance($client)
    ->setBody($continueData)
    ->submit();
```

### Response Methods

| Method | Return Type | Description |
|--------|-------------|-------------|
| `isSuccess()` | bool | Returns true if request was successful |
| `isApproved()` | bool | Returns true if payment was approved (code "00") |
| `getReferenceId()` | ?string | Returns the reference ID |
| `getCode()` | ?string | Returns the response code |
| `getMessage()` | ?string | Returns the response message |
| `getResponse()` | array | Returns the full response array |

### Example Response

```php
if ($response->isApproved()) {
    echo "3D Secure payment completed successfully!";
    $referenceId = $response->getReferenceId();
} else {
    $errorCode = $response->getCode();
    $errorMessage = $response->getMessage();
    echo "Payment failed: {$errorMessage} (Code: {$errorCode})";
}
```

---

## Error Handling

All actions follow the same error handling pattern:

```php
use Exception;

try {
    $response = PaymentToken::getInstance($client)
        ->setBody($data)
        ->submit();
        
    if ($response->isSuccess()) {
        // Handle success
        if ($response->isApproved()) {
            // Payment approved
        } else {
            // Payment declined
            $code = $response->getCode();
            $message = $response->getMessage();
        }
    } else {
        // API request failed
        $error = $response->getMessage();
    }
} catch (InvalidArgumentException $e) {
    // Validation error - missing required fields
    echo "Validation error: " . $e->getMessage();
} catch (Exception $e) {
    // Network or API error
    echo "API error: " . $e->getMessage();
}
```

## Common Response Codes

| Code | Description |
|------|-------------|
| `00` | Approved/Success |
| `01` | Refer to card issuer |
| `03` | Invalid merchant |
| `04` | Pick up card |
| `05` | Do not honor |
| `12` | Invalid transaction |
| `13` | Invalid amount |
| `14` | Invalid card number |
| `30` | Format error |
| `51` | Insufficient funds |
| `54` | Expired card |
| `55` | Incorrect PIN |
| `61` | Exceeds withdrawal limit |
| `65` | Exceeds withdrawal frequency |
| `78` | No account |
| `91` | Issuer unavailable |
| `96` | System malfunction |

## Best Practices

1. **Always validate responses**: Check both `isSuccess()` and specific approval methods
2. **Store tokens securely**: Never log or expose payment tokens
3. **Handle all error cases**: Network errors, validation errors, and declined transactions
4. **Use appropriate timeouts**: Configure timeouts based on your needs
5. **Implement retry logic**: For network failures, but not for declined transactions
6. **Log transactions**: Keep audit trails of all payment attempts