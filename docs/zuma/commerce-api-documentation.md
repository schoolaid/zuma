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

### 3. Payment with Token
**Endpoint:** `POST /commerce/payment/token`  
**Authentication:** Required  
**Description:** Process a payment using a previously tokenized card.

#### Request
```json
{
  "user_id": 123,
  "amount": 100.50,
  "token": "pi_tok_1234567890",
  "cvv": "123"
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Transaction approved"
}
```

#### Response Codes
- `00`: Transaction approved
- Other codes indicate various failure reasons

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `500 Internal Server Error`: Payment processing failed

**Note:** If the payment fails, an automatic reversal is attempted.

---

### 4. Cancel Transaction
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

---

### 5. 3D Secure Payment - Step 1
**Endpoint:** `POST /commerce/3ds/sale`  
**Authentication:** Required  
**Description:** Initiates a 3D Secure payment flow.

#### Request (Payment Type)
```json
{
  "type": "payment",
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

#### Request (TMS Type - Using Token)
```json
{
  "type": "tms",
  "amount": 150.00,
  "token": "pi_tok_1234567890",
  "cvv": "123",
  "url_commerce": "https://merchant.example.com/callback"
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "3DS authentication initiated",
  "reference_id": "ref_123456",
  "redirect_url": "https://3ds.example.com/authenticate",
  "access_token": "3ds_access_token_123"
}
```

#### Error Responses
- `400 Bad Request`: Required fields missing
- `401 Unauthorized`: Missing or invalid JWT token
- `500 Internal Server Error`: 3DS initiation failed

---

### 6. 3D Secure Payment - Continue
**Endpoint:** `POST /commerce/3ds/continue`  
**Authentication:** Required  
**Description:** Continues the 3D Secure payment flow after user authentication.

#### Request
```json
{
  "step": "3",
  "reference_id": "ref_123456"
}
```

#### Response
```json
{
  "success": true,
  "code": "00",
  "message": "Transaction completed successfully",
  "reference_id": "ref_123456"
}
```

#### Error Responses
- `401 Unauthorized`: Missing or invalid JWT token
- `404 Not Found`: Reference ID not found
- `500 Internal Server Error`: Transaction processing failed

---

## Common Response Codes

| Code | Description |
|------|-------------|
| 00   | Approved/Success |
| 01   | Refer to card issuer |
| 03   | Invalid merchant |
| 04   | Pick up card |
| 05   | Do not honor |
| 12   | Invalid transaction |
| 13   | Invalid amount |
| 14   | Invalid card number |
| 30   | Format error |
| 51   | Insufficient funds |
| 54   | Expired card |
| 55   | Incorrect PIN |
| 61   | Exceeds withdrawal limit |
| 65   | Exceeds withdrawal frequency |
| 78   | No account |
| 91   | Issuer unavailable |
| 96   | System malfunction |

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

## Security Notes

1. Always use HTTPS in production
2. JWT tokens expire - implement token refresh logic in your client
3. Never store CVV values
4. Card numbers should be transmitted only when necessary and over secure connections
5. Implement proper rate limiting and fraud detection on the client side