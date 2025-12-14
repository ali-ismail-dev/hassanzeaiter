# Test Data Seeders Documentation

## Test User Created
- **Email**: ali@gmail.com
- **Password**: pass1234
- **Name**: Ali Ismail
- **File**: `database/seeders/TestUserSeeder.php`

## Test Ads Created (5 Total)
All ads are created with the test user and seeded dynamically based on available categories:

### Ad 1: Toyota Corolla 2020
- **Category**: Vehicles (auto-detected)
- **Price**: $15,000.00
- **Status**: Active
- **Description**: Well-maintained Toyota Corolla 2020 with full service history

### Ad 2: Modern Apartment in Beirut
- **Category**: Properties (auto-detected)
- **Price**: $1,200.00 (monthly rental)
- **Status**: Active
- **Description**: Spacious 2-bedroom apartment with stunning city views

### Ad 3: Professional Soccer Ball
- **Category**: Ball Sports (auto-detected)
- **Price**: $45.00
- **Status**: Active
- **Description**: High-quality leather soccer ball, barely used

### Ad 4: Honda Civic 2019
- **Category**: Vehicles (auto-detected)
- **Price**: $13,500.00
- **Status**: Active
- **Description**: Reliable Honda Civic automatic transmission

### Ad 5: Luxury Villa in Achrafieh
- **Category**: Properties (auto-detected)
- **Price**: $3,500.00 (monthly rental)
- **Status**: Active
- **Description**: Beautiful villa with garden, swimming pool

**File**: `database/seeders/TestAdSeeder.php`

## Dynamic Field Values
- Ad field values are automatically populated based on available category fields
- The seeder intelligently matches field types:
  - **text/textarea** → Sample descriptive text
  - **integer/number** → Realistic numeric values
  - **decimal** → Price-like values
  - **date** → Past dates
  - **boolean** → Random true/false
  - **select/radio** → First available option from category

## Running the Seeders

```bash
# Seed all data (includes OLX API categories + test user + test ads)
php artisan db:seed --force

# Or run specific seeder
php artisan db:seed --class=TestUserSeeder --force
php artisan db:seed --class=TestAdSeeder --force
```

## Testing Credentials
Use these in Postman for testing:

### Login
```
POST /api/auth/login
{
  "email": "ali@gmail.com",
  "password": "pass1234"
}
```

### Expected Response
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

Use the returned token in subsequent requests:
```
Authorization: Bearer {token}
```

## Integration with Seeders
The DatabaseSeeder now runs in this order:
1. **OlxCategoriesSeeder** - Fetches live categories & fields from OLX API
2. **TestUserSeeder** - Creates test user (ali@gmail.com)
3. **TestAdSeeder** - Creates 5 sample ads with field values
4. **UserFactory** - Creates 10 additional random users for testing

This ensures all dependencies are properly set up before creating test ads.
