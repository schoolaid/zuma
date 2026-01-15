# Commerce API Documentation

This document describes the Commerce API endpoints for the Payments API. All endpoints except `/commerce/login` require JWT authentication.

## Base URL
```
/commerce
```

## Authentication

Most endpoints require a JWT token obtained from the login endpoint. Include the token in the Authorization header:

```
Authorization: Bearer <JWT_TOKEN>
```

---

## Endpoints

### 1. Login
**Endpoint:** `POST /commerce/login`  
**Authentication:** None  
**Description:** Authenticates a user and returns a JWT token for subsequent requests.

#### Request
```json
{
  "username": "string",
  "password": "string"
}
```

#### Response
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

#### Error Responses
- `400 Bad Request`: Invalid request body or validation failed
- `401 Unauthorized`: Invalid credentials

---

### 2. Tokenize Card
**Endpoint:** `POST /commerce/tokenize`  
**Authentication:** Required  
**Description:** Tokenizes credit card information for secure storage and future transactions.

#### Request
```json
{
  "card_number": "4111111111111111",
  "expiry_date": "1225",
  "first_name": "John",
  "last_name": "Doe",
  "company": "ACME Corp",
  "address1": "123 Main St",
  "address2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "US",
  "email": "john.doe@example.com",
  "phone": "+1234567890"
}
```

#### Response
```json
{
  "success": true,
  "message": "Card tokenized successfully",
  "card_token_id": "tok_1234567890",
  "payment_instrument_token_id": "pi_tok_1234567890"
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Failed to retrieve Zuma credentials
- `500 Internal Server Error`: Tokenization failed

---

### 3. Delete Token
**Endpoint:** `POST /commerce/delete/token`
**Authentication:** Required
**Description:** Deletes a previously tokenized payment instrument from the vault.

#### Request
```json
{
  "token": "pi_tok_1234567890"
}
```

#### Response
```json
{
  "deleted": true,
  "message": "PAYMENTINSTRUMENT DELETED SUCCESSFULLY"
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Failed to retrieve Zuma credentials
- `500 Internal Server Error`: Token deletion failed

#### Response Messages
- Success: `"PAYMENTINSTRUMENT DELETED SUCCESSFULLY"`
- Failure: Various messages such as `"THE PAYMENTINSTRUMENTTOKENID DOES NOT ACTIVE IN VAULT, MUST BE CREATE NEW PAYMENTINSTRUMENT"`

---

### 4. Payment with Token
**Endpoint:** `POST /commerce/payment/token`
**Authentication:** Required
**Description:** Process a payment using a previously tokenized card. User ID is automatically extracted from JWT token.

#### Request
```json
{
  "amount": 100.50,
  "token": "pi_tok_1234567890",
  "cvv": "123",
  "email": "customer@example.com"
}
```

| Field  | Type   | Required | Description                                |
| ------ | ------ | -------- | ------------------------------------------ |
| amount | float  | Yes      | Payment amount                             |
| token  | string | Yes      | Payment instrument token from tokenization |
| cvv    | string | Yes      | Card CVV/security code                     |
| email  | string | No       | Email address to send payment voucher      |

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Transaction approved",
  "authorizationCode": "123456",
  "sequence": "000001",
  "referenceNumber": "REF123456789",
  "transactionId": 12345
}
```

#### Response Codes
- `00`: Transaction approved
- Other codes indicate various failure reasons

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `500 Internal Server Error`: Payment processing failed

**Note:** If the payment encounters an HTTP error or timeout (not a business rejection), an automatic reversal is attempted. Business rejections (response code != 00) do not trigger automatic reversals.

---

### 5. Cancel Transaction
**Endpoint:** `POST /commerce/cancel`  
**Authentication:** Required  
**Description:** Cancels a previously processed transaction.

#### Request
```json
{
  "transaction_id": 12345
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Transaction cancelled successfully",
  "transaction_id": 12345
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Transaction not found or access denied
- `500 Internal Server Error`: Cancel operation failed

**Note:** If the cancel request fails due to HTTP error/timeout, an automatic reversal is attempted.

---

### 6. Reverse Transaction
**Endpoint:** `POST /commerce/reverse`
**Authentication:** Required
**Description:** Performs a reversal (0400 message) on a previously processed transaction. Reversals are used to undo transactions that encountered technical failures (timeouts, connection errors, etc.). This endpoint has no restrictions and can be called for any transaction regardless of status.

#### Request
```json
{
  "transaction_id": 12345
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Reversal processed successfully",
  "transaction_id": 12345
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Transaction not found, access denied, or original transaction data not found
- `500 Internal Server Error`: Reversal operation failed

#### Important Notes

**Reversal vs Cancel:**
- **Reversal (0400)**: Used for technical failures (HTTP errors, timeouts, connection issues). Sent automatically when payment operations fail.
- **Cancel (020000)**: Used for business-level transaction cancellation of completed transactions.

**Automatic Reversal:**
The system automatically attempts reversals in the following scenarios:
- Payment with Token (`/commerce/payment/token`) encounters HTTP error/timeout
- Cancel Transaction (`/commerce/cancel`) encounters HTTP error/timeout
- 3DS Step 1 (`/commerce/3ds/sale`) encounters HTTP error/timeout
- 3DS Continue (`/commerce/3ds/continue`) encounters HTTP error/timeout

**Manual Reversal:**
Use this endpoint to manually reverse a transaction when:
- Automatic reversal failed but you need to retry
- You need to reverse a transaction for operational reasons
- You need to clean up failed transactions

**Credential Usage:**
Reversals use the exact same merchant credentials (terminal ID, affiliation, merchant user, merchant password) as the original transaction. This ensures consistency across different operation types (TMS, 3DS, etc.).

**Audit Trail:**
All reversal attempts (automatic and manual) are logged in the `transaction_request_response` table with:
- Original transaction request
- Reversal request sent to gateway
- Gateway response
- Success/failure status

---

### 7. 3D Secure Payment - Step 1
**Endpoint:** `POST /commerce/3ds/sale`  
**Authentication:** Required  
**Description:** Initiates a 3D Secure payment flow. The flow type is automatically detected based on request fields.

#### Payment Flow (Direct Card)
Automatically detected when `card_number` is provided. Uses `Operation3DS` credentials.

**Required Fields:** `amount`, `card_number`, `expiry_date`, `url_commerce`, `first_name`, `last_name`, `address1`, `city`, `country`, `email`

```json
{
  "amount": 150.00,
  "card_number": "4111111111111111",
  "expiry_date": "1225",
  "cvv": "123",
  "url_commerce": "https://merchant.example.com/callback",
  "first_name": "John",
  "last_name": "Doe",
  "company": "ACME Corp",
  "address1": "123 Main St",
  "address2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "US",
  "email": "john.doe@example.com",
  "phone": "+1234567890"
}
```

| Field        | Type   | Required | Description                           |
| ------------ | ------ | -------- | ------------------------------------- |
| amount       | float  | Yes      | Payment amount                        |
| card_number  | string | Yes      | Credit card number                    |
| expiry_date  | string | Yes      | Card expiry date (MMYY format)        |
| cvv          | string | No       | Card CVV/security code                |
| url_commerce | string | Yes      | Callback URL for 3DS redirect         |
| first_name   | string | Yes      | Cardholder first name                 |
| last_name    | string | Yes      | Cardholder last name                  |
| company      | string | No       | Company name                          |
| address1     | string | Yes      | Billing address line 1                |
| address2     | string | No       | Billing address line 2                |
| city         | string | Yes      | Billing city                          |
| state        | string | No       | Billing state                         |
| postal_code  | string | No       | Billing postal code                   |
| country      | string | Yes      | Billing country code                  |
| email        | string | Yes      | Email for billing and payment voucher |
| phone        | string | No       | Phone number                          |

#### TMS Flow (Tokenized)
Automatically detected when `token` is provided. Uses `Operation3DSTMS` credentials.

**Required Fields:** `amount`, `token`, `url_commerce`

```json
{
  "amount": 150.00,
  "token": "pi_tok_1234567890",
  "cvv": "123",
  "url_commerce": "https://merchant.example.com/callback",
  "email": "customer@example.com"
}
```

| Field        | Type   | Required | Description                                |
| ------------ | ------ | -------- | ------------------------------------------ |
| amount       | float  | Yes      | Payment amount                             |
| token        | string | Yes      | Payment instrument token from tokenization |
| cvv          | string | No       | Card CVV/security code                     |
| url_commerce | string | Yes      | Callback URL for 3DS redirect              |
| email        | string | No       | Email address to send payment voucher      |

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "3DS authentication initiated",
  "reference_id": "ref_123456",
  "redirect_url": "https://3ds.example.com/authenticate",
  "access_token": "3ds_access_token_123",
  "authorizationCode": "123456",
  "sequence": "000001",
  "referenceNumber": "REF123456789",
  "transactionId": 12345
}
```

#### Error Responses
- `400 Bad Request`: Required fields missing or invalid flow detection:
  - "Either token or card_number must be provided"
  - "Expiry date is required for payment flow"
  - "Required billing fields missing for payment flow"
- `401 Unauthorized`: Missing or invalid JWT token
- `500 Internal Server Error`: 3DS initiation failed

**Note:** If the 3DS Step 1 request encounters an HTTP error or timeout, an automatic reversal is attempted.

---

### 8. 3D Secure Payment - Continue
**Endpoint:** `POST /commerce/3ds/continue`
**Authentication:** Required
**Description:** Continues the 3D Secure payment flow after user authentication.

#### Request
```json
{
  "step": "3",
  "reference_id": "ref_123456",
  "transaction_id": 12345
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Transaction completed successfully",
  "reference_id": "ref_123456",
  "authorizationCode": "123456",
  "sequence": "000001",
  "referenceNumber": "REF123456789",
  "transactionId": 12345
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Reference ID not found
- `500 Internal Server Error`: Transaction processing failed

**Note:** If the 3DS Continue request encounters an HTTP error or timeout, an automatic reversal is attempted using the original transaction data.

---

## Common Response Codes

| Code | Description                  |
| ---- | ---------------------------- |
| 00   | Approved/Success             |
| 01   | Refer to card issuer         |
| 03   | Invalid merchant             |
| 04   | Pick up card                 |
| 05   | Do not honor                 |
| 12   | Invalid transaction          |
| 13   | Invalid amount               |
| 14   | Invalid card number          |
| 30   | Format error                 |
| 51   | Insufficient funds           |
| 54   | Expired card                 |
| 55   | Incorrect PIN                |
| 61   | Exceeds withdrawal limit     |
| 65   | Exceeds withdrawal frequency |
| 78   | No account                   |
| 91   | Issuer unavailable           |
| 96   | System malfunction           |

---

## Error Handling

All endpoints return appropriate HTTP status codes:
- `200 OK`: Request processed successfully (check response body for transaction status)
- `400 Bad Request`: Invalid request format or validation error
- `401 Unauthorized`: Authentication required or invalid token
- `404 Not Found`: Resource not found
- `500 Internal Server Error`: Server-side error

Error responses include a message field explaining the error:
```json
{
  "success": false,
  "message": "Description of the error"
}
```

---

## API Notes

1. **JWT Authentication**: JWT tokens automatically provide user context, eliminating the need to specify `user_id` in request bodies.

2. **Automatic Flow Detection**: The 3DS endpoint automatically detects the flow type:
   - **Payment Flow**: When `card_number` is provided (uses `Operation3DS` credentials)
   - **TMS Flow**: When `token` is provided (uses `Operation3DSTMS` credentials)

3. **Operation Types**: The system automatically uses appropriate Zuma credentials:
   - `TMS`: Standard tokenization and payment operations
   - `3DS`: 3D Secure authentication with direct card data  
   - `3DSTMS`: 3D Secure authentication with tokenized data

4. **Required Fields**:
   - **Payment Flow**: Requires complete billing information and card details
   - **TMS Flow**: Requires only `amount`, `token`, and `url_commerce`
   - **Both Flows**: `url_commerce` is always required for 3DS operations

5. **3D Secure Flow Behavior**:
   - **Type Operation 1**: Transaction is approved or rejected directly without additional authentication steps
   - **Type Operation 3**: Requires additional authentication steps (redirect to 3DS page)
     - Response codes `00` or `10` indicate authentication is required
     - Client must redirect user to `redirect_url` for authentication
     - After authentication, use `/commerce/3ds/continue` endpoint to complete the transaction

6. **Transaction Fields**:
   - All successful payment responses include `transactionId` for tracking and cancellation
   - `authorizationCode`: Authorization code from the payment processor
   - `sequence`: Transaction sequence number
   - `referenceNumber`: Reference number for tracking with the payment gateway

7. **Automatic Reversals**:
   - The system automatically attempts reversals (0400 message) when HTTP errors or timeouts occur
   - Reversals are NOT triggered by business logic failures (response code != 00)
   - All reversal attempts are logged in the database for audit purposes
   - Reversals use the exact same credentials as the original transaction
   - If automatic reversal fails, you can manually retry using `/commerce/reverse`

8. **Reversal vs Cancel**:
   - **Reversal (0400)**: Technical failure recovery - undoes transactions that didn't complete properly
   - **Cancel (020000)**: Business operation - cancels successfully completed transactions
   - Use `/commerce/reverse` for technical issues
   - Use `/commerce/cancel` for business-level cancellations

9. **Email Voucher**:
   - When `email` is provided and the payment is approved (response code `00`), a payment voucher is sent
   - Voucher is sent asynchronously (does not delay the API response)
   - Voucher includes: date/time, amount, cardholder name, masked card number, reference number, authorization code, affiliation, and auditory number
   - Invalid email addresses are silently ignored
   - A copy (BCC) is sent to info@zumapagos.com.gt

---

## Security Notes

1. Always use HTTPS in production
2. JWT tokens expire - implement token refresh logic in your client
3. Never store CVV values
4. Card numbers should be transmitted only when necessary and over secure connections
5. Implement proper rate limiting and fraud detection on the client side