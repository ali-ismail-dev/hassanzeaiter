# Postman Testing Guide - Complete API Test Cases

## Base URL
```
http://localhost:8000/api/v1
```

---

## 1️⃣ AUTHENTICATION TESTS

### Test 1: Register New User
```http
POST http://localhost:8000/api/auth/register
Content-Type: application/json

{
  "name": "Test User",
  "email": "testuser@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```
**Expected**: 201 Created + token

---

### Test 2: Login with Valid Credentials
```http
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
  "email": "ali@gmail.com",
  "password": "pass1234"
}
```
**Expected**: 200 OK + token
**Response**:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "your_sanctum_token_here"
  }
}
```
**SAVE TOKEN** for subsequent requests!

---

## 2️⃣ CREATE AD TESTS

### Test 3: POST Ad - Success with Valid Data
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {token_from_login}
Content-Type: application/json

{
  "category_id": 1,
  "title": "Beautiful Apartment in Downtown",
  "description": "Spacious, well-lit apartment with modern amenities",
  "price": 1500.00,
  "bedrooms": 3,
  "bathrooms": 2,
  "furnished": "partially"
}
```
**Expected**: 201 Created + ad resource with ID

---

### Test 4: POST Ad - Missing Required Field
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {token_from_login}
Content-Type: application/json

{
  "category_id": 1,
  "title": "Beautiful Apartment",
  "price": 1500.00
}
```
**Expected**: 422 Unprocessable Entity
**Error**:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "description": ["The description field is required."]
  }
}
```

---

### Test 5: POST Ad - Invalid Data Type
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {token_from_login}
Content-Type: application/json

{
  "category_id": "invalid_not_a_number",
  "title": "Test",
  "description": "Test",
  "price": "not_a_price"
}
```
**Expected**: 422 Unprocessable Entity with type validation errors

---

### Test 6: POST Ad - Without Authentication
```http
POST http://localhost:8000/api/v1/ads
Content-Type: application/json

{
  "category_id": 1,
  "title": "Test Ad",
  "description": "Test",
  "price": 1000
}
```
**Expected**: 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

---

## 3️⃣ LIST MY ADS TESTS

### Test 7: GET My Ads - Page 1
```http
GET http://localhost:8000/api/v1/my-ads
Authorization: Bearer {token_from_login}
```
**Expected**: 200 OK + paginated collection of ads
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Toyota Corolla 2020...",
      "price": 15000,
      "category": { ... },
      "field_values": [ ... ]
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 5,
    "per_page": 15
  }
}
```

---

### Test 8: GET My Ads - Pagination (Page 2)
```http
GET http://localhost:8000/api/v1/my-ads?page=2
Authorization: Bearer {token_from_login}
```
**Expected**: 200 OK + page 2 data (if available)

---

### Test 9: GET My Ads - Custom Per Page
```http
GET http://localhost:8000/api/v1/my-ads?per_page=5&page=1
Authorization: Bearer {token_from_login}
```
**Expected**: 200 OK + 5 items per page

---

### Test 10: GET My Ads - Without Auth
```http
GET http://localhost:8000/api/v1/my-ads
```
**Expected**: 401 Unauthorized

---

## 4️⃣ VIEW SPECIFIC AD TESTS

### Test 11: GET Specific Ad - Valid ID
```http
GET http://localhost:8000/api/v1/ads/1
Authorization: Bearer {token_from_login}
```
**Expected**: 200 OK + full ad resource
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Toyota Corolla 2020 - Great Condition",
    "description": "Well-maintained Toyota Corolla...",
    "price": 15000.00,
    "category": {
      "id": 1,
      "name": "Vehicles"
    },
    "field_values": [
      {
        "field": {
          "name": "Make",
          "type": "text"
        },
        "value": "Toyota"
      }
    ],
    "user": {
      "id": 1,
      "name": "Ali Ismail",
      "email": "ali@gmail.com"
    }
  }
}
```

---

### Test 12: GET Specific Ad - Non-Existent ID
```http
GET http://localhost:8000/api/v1/ads/99999
Authorization: Bearer {token_from_login}
```
**Expected**: 404 Not Found

---

### Test 13: GET Specific Ad - Without Auth
```http
GET http://localhost:8000/api/v1/ads/1
```
**Expected**: 401 Unauthorized

---

## 5️⃣ UPDATE AD TESTS (Optional)

### Test 14: PUT Update Ad - Own Ad
```http
PUT http://localhost:8000/api/v1/ads/1
Authorization: Bearer {token_from_login}
Content-Type: application/json

{
  "title": "Updated Title",
  "price": 16000.00
}
```
**Expected**: 200 OK + updated resource

---

### Test 15: PUT Update Ad - Different User's Ad
```http
PUT http://localhost:8000/api/v1/ads/2
Authorization: Bearer {different_user_token}
Content-Type: application/json

{
  "title": "Trying to hack",
  "price": 1.00
}
```
**Expected**: 403 Forbidden

---

## 6️⃣ DELETE AD TESTS (Optional)

### Test 16: DELETE Ad - Own Ad
```http
DELETE http://localhost:8000/api/v1/ads/1
Authorization: Bearer {token_from_login}
```
**Expected**: 204 No Content (or 200 OK with success message)

---

### Test 17: DELETE Ad - Different User's Ad
```http
DELETE http://localhost:8000/api/v1/ads/2
Authorization: Bearer {different_user_token}
```
**Expected**: 403 Forbidden

---

## Quick Test Sequence

Run tests in this order for best results:

1. **Test 2** - Login (save token)
2. **Test 3** - Create an ad
3. **Test 7** - List your ads
4. **Test 11** - View specific ad
5. **Test 1** - Register new user (optional)
6. **Test 4** - Test validation (try to create ad without description)

---

## Tips

- **Save responses**: Copy the `token` from Test 2 response
- **Use environment variables**: In Postman, set `{{token}}` variable from login response
- **Test different categories**: Get the category IDs from your database and test creating ads for each
- **Check field requirements**: Different categories have different required fields
- **Test pagination**: Use `?page=1&per_page=5` parameters on list endpoints
