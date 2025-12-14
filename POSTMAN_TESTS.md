# Postman Testing Guide - OLX Ads API

## 📋 Overview

This guide provides comprehensive test cases for the OLX Ads API assessment. All endpoints follow RESTful conventions and use Laravel Sanctum for authentication.

**Base URL:** `http://localhost:8000/api/v1`

**Important Notes:**
- `category_id` must be the **external_id** (string) from the OLX API, not the database auto-increment ID
- Dynamic fields are category-specific and validated based on the category's field definitions
- All protected endpoints require a Bearer token from authentication

---

## 🔐 1. AUTHENTICATION

### Test 1.1: Register New User

**Endpoint:** `POST /api/v1/register`

**Request:**
```http
POST http://localhost:8000/api/v1/register
Content-Type: application/json

{
  "name": "Test User",
  "email": "testuser@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Expected Response:** `201 Created`
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Test User",
      "email": "testuser@example.com"
    },
    "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

**Action:** Save the `access_token` for subsequent requests!

---

### Test 1.2: Login

**Endpoint:** `POST /api/v1/login`

**Request:**
```http
POST http://localhost:8000/api/v1/login
Content-Type: application/json

{
  "email": "testuser@example.com",
  "password": "password123"
}
```

**Expected Response:** `200 OK`
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Test User",
      "email": "testuser@example.com"
    },
    "access_token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

**Action:** Save the `access_token` as `{{token}}` in Postman environment variables.

---

### Test 1.3: Get Authenticated User

**Endpoint:** `GET /api/v1/me`

**Request:**
```http
GET http://localhost:8000/api/v1/me
Authorization: Bearer {{token}}
```

**Expected Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Test User",
      "email": "testuser@example.com",
      "phone": null,
      "created_at": "2025-12-14T10:00:00+00:00"
    }
  }
}
```

---

### Test 1.4: Logout

**Endpoint:** `POST /api/v1/logout`

**Request:**
```http
POST http://localhost:8000/api/v1/logout
Authorization: Bearer {{token}}
```

**Expected Response:** `200 OK`
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## 📝 2. CREATE AD (POST /api/v1/ads)

**Endpoint:** `POST /api/v1/ads`  
**Authentication:** Required (Bearer token)  
**Assignment Requirement:** Must accept `category_id` and all required dynamic fields specific to that category

### Test 2.1: Create Ad - Success with Dynamic Fields (Services Category)

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "241",
  "title": "Professional Web Development Services",
  "description": "Experienced web developer offering custom website development, e-commerce solutions, and maintenance services. Over 5 years of experience with modern frameworks.",
  "price": 1500.00,
  "fields": {
    "13316": "Web Development",
    "13317": "Full-stack development services",
    "13492": "Available immediately"
  }
}
```

**Expected Response:** `201 Created`
```json
{
  "data": {
    "id": 1,
    "title": "Professional Web Development Services",
    "description": "Experienced web developer offering custom website development...",
    "price": 1500.0,
    "status": "active",
    "views_count": 0,
    "published_at": "2025-12-14T12:00:00+00:00",
    "expires_at": null,
    "created_at": "2025-12-14T12:00:00+00:00",
    "updated_at": "2025-12-14T12:00:00+00:00",
    "category": {
      "id": 25,
      "external_id": "241",
      "name": "Services",
      "slug": "services",
      "description": null,
      "parent_id": null,
      "order": 0
    },
    "user": {
      "id": 1
    },
    "fields": [
      {
        "field_name": "13316",
        "field_label": "Service Type",
        "field_type": "text",
        "value": "Web Development",
        "display_value": "Web Development"
      },
      {
        "field_name": "13317",
        "field_label": "Description",
        "field_type": "text",
        "value": "Full-stack development services",
        "display_value": "Full-stack development services"
      }
    ]
  }
}
```

**Validation Points:**
- ✅ Ad created successfully
- ✅ Dynamic fields saved to `ad_field_values` table
- ✅ Response uses API Resource (not raw Eloquent model)
- ✅ All fields included in response

---

### Test 2.2: Create Ad - With Required Dynamic Field (Home Furniture)

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "5",
  "title": "Modern Sofa Set - Excellent Condition",
  "description": "Beautiful modern sofa set in excellent condition. Perfect for living room. Includes 3-seater sofa and 2 armchairs.",
  "price": 800.00,
  "fields": {
    "594": "Sofa Set",
    "593": "Modern",
    "14229": "Brown leather"
  }
}
```

**Note:** Field `594` is required for this category. If omitted, validation will fail.

**Expected Response:** `201 Created` (with all fields saved)

---

### Test 2.3: Create Ad - Missing Required Base Field (Description)

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "241",
  "title": "Test Service",
  "price": 100.00
}
```

**Expected Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "description": [
      "Please provide a description for your ad."
    ]
  }
}
```

**Validation Points:**
- ✅ Standardized JSON error response
- ✅ HTTP 422 status code
- ✅ Clear validation error messages

---

### Test 2.4: Create Ad - Missing Required Dynamic Field

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "5",
  "title": "Test Furniture",
  "description": "This is a detailed description that meets the minimum length requirements for validation purposes.",
  "price": 100.00,
  "fields": {
    "593": "Modern"
  }
}
```

**Expected Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "fields.594": [
      "The 594 field is required."
    ]
  }
}
```

**Validation Points:**
- ✅ Dynamic validation based on category fields
- ✅ Required field validation works correctly

---

### Test 2.5: Create Ad - Invalid Category ID

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "99999",
  "title": "Test Ad",
  "description": "This is a detailed description that meets the minimum length requirements.",
  "price": 100.00,
  "fields": {}
}
```

**Expected Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "category_id": [
      "The selected category is invalid. Please use the external_id from the OLX API."
    ]
  }
}
```

---

### Test 2.6: Create Ad - Invalid Data Type (Price as String)

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "category_id": "241",
  "title": "Test Service",
  "description": "This is a detailed description that meets the minimum length requirements.",
  "price": "not_a_number",
  "fields": {}
}
```

**Expected Response:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "price": [
      "The price must be a valid number."
    ]
  }
}
```

---

### Test 2.7: Create Ad - Without Authentication

**Request:**
```http
POST http://localhost:8000/api/v1/ads
Content-Type: application/json

{
  "category_id": "241",
  "title": "Test Ad",
  "description": "This is a detailed description that meets the minimum length requirements.",
  "price": 100.00,
  "fields": {}
}
```

**Expected Response:** `401 Unauthorized`
```json
{
  "message": "Unauthenticated."
}
```

**Validation Points:**
- ✅ Endpoint is protected by Sanctum
- ✅ Proper HTTP 401 status code

---

## 📋 3. LIST MY ADS (GET /api/v1/my-ads)

**Endpoint:** `GET /api/v1/my-ads`  
**Authentication:** Required (Bearer token)  
**Assignment Requirement:** List all ads posted by authenticated user, must be paginated

### Test 3.1: Get My Ads - Page 1 (Default)

**Request:**
```http
GET http://localhost:8000/api/v1/my-ads
Authorization: Bearer {{token}}
```

**Expected Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "title": "Professional Web Development Services",
      "description": "Experienced web developer...",
      "price": 1500.0,
      "status": "active",
      "views_count": 0,
      "published_at": "2025-12-14T12:00:00+00:00",
      "created_at": "2025-12-14T12:00:00+00:00",
      "updated_at": "2025-12-14T12:00:00+00:00",
      "category": {
        "id": 25,
        "external_id": "241",
        "name": "Services"
      },
      "user": {
        "id": 1
      },
      "fields": [
        {
          "field_name": "13316",
          "field_label": "Service Type",
          "field_type": "text",
          "value": "Web Development",
          "display_value": "Web Development"
        }
      ]
    }
  ],
  "meta": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "links": {
    "first": "http://localhost:8000/api/v1/my-ads?page=1",
    "last": "http://localhost:8000/api/v1/my-ads?page=1",
    "prev": null,
    "next": null
  }
}
```

**Validation Points:**
- ✅ Only returns ads for authenticated user
- ✅ Pagination metadata included
- ✅ Uses API Resource Collection (not raw models)
- ✅ Includes dynamic fields in response

---

### Test 3.2: Get My Ads - Pagination (Page 2)

**Request:**
```http
GET http://localhost:8000/api/v1/my-ads?page=2
Authorization: Bearer {{token}}
```

**Expected Response:** `200 OK` (with page 2 data if available, or empty array)

---

### Test 3.3: Get My Ads - Custom Per Page

**Request:**
```http
GET http://localhost:8000/api/v1/my-ads?per_page=5&page=1
Authorization: Bearer {{token}}
```

**Expected Response:** `200 OK`
- `meta.per_page` should be `5`
- Maximum 5 items in `data` array

---

### Test 3.4: Get My Ads - Without Authentication

**Request:**
```http
GET http://localhost:8000/api/v1/my-ads
```

**Expected Response:** `401 Unauthorized`
```json
{
  "message": "Unauthenticated."
}
```

---

## 👁️ 4. VIEW SPECIFIC AD (GET /api/v1/ads/{id})

**Endpoint:** `GET /api/v1/ads/{id}`  
**Authentication:** Not required (public endpoint)  
**Assignment Requirement:** Retrieve single ad including main fields and all dynamic field values

### Test 4.1: Get Specific Ad - Valid ID

**Request:**
```http
GET http://localhost:8000/api/v1/ads/1
```

**Expected Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "title": "Professional Web Development Services",
    "description": "Experienced web developer offering custom website development...",
    "price": 1500.0,
    "status": "active",
    "views_count": 1,
    "published_at": "2025-12-14T12:00:00+00:00",
    "expires_at": null,
    "created_at": "2025-12-14T12:00:00+00:00",
    "updated_at": "2025-12-14T12:00:00+00:00",
    "category": {
      "id": 25,
      "external_id": "241",
      "name": "Services",
      "slug": "services",
      "description": null,
      "parent_id": null,
      "order": 0
    },
    "user": {
      "id": 1,
      "name": "Test User"
    },
    "fields": [
      {
        "field_name": "13316",
        "field_label": "Service Type",
        "field_type": "text",
        "value": "Web Development",
        "display_value": "Web Development"
      },
      {
        "field_name": "13317",
        "field_label": "Description",
        "field_type": "text",
        "value": "Full-stack development services",
        "display_value": "Full-stack development services"
      }
    ]
  }
}
```

**Validation Points:**
- ✅ Returns full ad resource with all relationships
- ✅ Includes all dynamic field values
- ✅ Uses API Resource (not raw model)
- ✅ `views_count` increments on each request

---

### Test 4.2: Get Specific Ad - Non-Existent ID

**Request:**
```http
GET http://localhost:8000/api/v1/ads/99999
```

**Expected Response:** `404 Not Found`

---

### Test 4.3: Get Specific Ad - Works Without Authentication

**Request:**
```http
GET http://localhost:8000/api/v1/ads/1
```

**Expected Response:** `200 OK` (public endpoint, no auth required)

---

## 🧪 5. COMPREHENSIVE TEST SCENARIOS

### Scenario 1: Complete Workflow

1. **Register/Login** → Get token
2. **Create Ad** with dynamic fields → Get ad ID
3. **View Ad** → Verify fields are saved
4. **List My Ads** → Verify ad appears in list
5. **View Ad Again** → Verify `views_count` increased

### Scenario 2: Dynamic Field Validation

1. **Get category fields** (check database or logs)
2. **Create ad with required field missing** → Should get 422
3. **Create ad with all required fields** → Should get 201
4. **Verify fields saved** in `ad_field_values` table

### Scenario 3: Category-Specific Fields

1. **Create ad for Services (241)** → Use field keys: `13316`, `13317`, etc.
2. **Create ad for Furniture (5)** → Use field keys: `593`, `594`, etc.
3. **Verify each category has different fields** in response

---

## 📊 6. TESTING CHECKLIST

### Assignment Requirements Coverage

- [x] **POST /api/v1/ads** - Accepts category_id and dynamic fields
- [x] **GET /api/v1/my-ads** - Lists authenticated user's ads (paginated)
- [x] **GET /api/v1/ads/{id}** - Returns single ad with all fields
- [x] **Authentication** - Sanctum token required for protected endpoints
- [x] **Dynamic Validation** - Based on category field definitions
- [x] **API Resources** - Responses use transformers (not raw models)
- [x] **Error Handling** - Standardized JSON errors (422, 401, 403)
- [x] **Pagination** - Implemented for my-ads endpoint

---

## 🔍 7. FIELD KEYS REFERENCE

### Services Category (external_id: "241")
- `13316` - text (optional)
- `13317` - text (optional)
- `13492` - text (optional)
- `13733` - text (optional)
- `13734` - text (optional)
- `13905` - text (optional)
- `14181` - text (optional)

### Home Furniture & Decor (external_id: "5")
- `593` - text (optional)
- `594` - text (**required**)
- `14229` - text (optional)
- `14068` - text (optional)
- `14230` - text (optional)

**Note:** Field keys are the `external_id` values from `category_fields` table. To get field keys for any category, check the database or sync categories with: `php artisan categories:sync --force`

---

## 💡 8. POSTMAN SETUP TIPS

### Environment Variables

Create a Postman environment with:
- `base_url`: `http://localhost:8000/api/v1`
- `token`: (set automatically from login response)

### Pre-request Script (for Login)

```javascript
// After login, save token automatically
if (pm.response.code === 200) {
    const jsonData = pm.response.json();
    if (jsonData.data && jsonData.data.access_token) {
        pm.environment.set("token", jsonData.data.access_token);
    }
}
```

### Collection Organization

Organize requests in folders:
1. **Authentication** (register, login, logout, me)
2. **Ads - Create** (success cases, validation errors)
3. **Ads - List** (my-ads with pagination)
4. **Ads - View** (single ad, 404 cases)

---

## ⚠️ 9. COMMON ISSUES & SOLUTIONS

### Issue: "The selected category is invalid"
**Solution:** Use `external_id` (string) from OLX API, not database `id`. Example: `"241"` not `241`.

### Issue: Fields not saving
**Solution:** 
1. Ensure category has fields synced: `php artisan categories:sync --force`
2. Use correct field keys (check `category_fields` table)
3. Field keys are the `external_id` values from category fields

### Issue: Validation errors for required fields
**Solution:** Check which fields are required for your category. Required fields must be included in the `fields` object.

### Issue: 401 Unauthorized
**Solution:** 
1. Login first to get token
2. Include `Authorization: Bearer {{token}}` header
3. Token may have expired - login again

---

## 📝 10. QUICK TEST SEQUENCE

For fastest testing, run in this order:

1. **Test 1.2** - Login → Save token
2. **Test 2.1** - Create ad with fields → Save ad ID
3. **Test 3.1** - List my ads → Verify ad appears
4. **Test 4.1** - View specific ad → Verify fields
5. **Test 2.3** - Test validation (missing description)
6. **Test 2.7** - Test authentication (no token)

---

## ✅ VERIFICATION CHECKLIST

Before submitting, verify:

- [ ] All three required endpoints work correctly
- [ ] Dynamic fields are saved to database
- [ ] Validation works for required fields
- [ ] API Resources are used (not raw models)
- [ ] Pagination works for my-ads
- [ ] Authentication protects POST and GET /my-ads
- [ ] Error responses are standardized JSON
- [ ] Field values appear in GET responses

---

**Last Updated:** 2025-12-14  
**API Version:** v1  
**Framework:** Laravel 12 with Sanctum
