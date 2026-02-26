
# CSV Import API

## Overview

This project implements a Laravel-based CSV import API with full
validation, partial imports, detailed error reporting, pagination,
batch processing, and automated test coverage.

The system is designed with clean architecture principles and follows
Laravel best practices, focusing on correctness, transparency, and
robust error handling.

---

## Features

✔ CSV upload endpoint with detailed import reporting  
✔ Partial imports — valid rows are never blocked by invalid ones  
✔ Duplicate detection within the same file and against the database  
✔ Case-insensitive header and email matching  
✔ Malformed row handling (wrong column counts)  
✔ All validation errors per row returned (not just first failure)  
✔ Batch-based database transactions for resilience  
✔ Protection against database failures mid-import  
✔ Consistent JSON validation responses  
✔ Pagination for imported customers  
✔ Comprehensive automated test coverage  

---

## System Requirements

- Docker
- Docker Compose

---

## Docker Setup


### 1. Clone the repository
```bash
git clone https://github.com/shubhamfernandes/debt-register.git
cd debt-register
```

### 2. Build and start containers

```bash
docker compose up -d --build
```

### 3. Install dependencies

```bash
docker compose exec app composer install
```

### 4. Create the environment file

```bash
docker compose exec app cp .env.example .env
```

### 5. Run database migrations

```bash
docker compose exec app php artisan key:generate
```

### 6. Run database migrations

```bash
docker compose exec app php artisan migrate
```

The API will be available at:

http://localhost:8080

---

## API Endpoints

### POST /api/import

Uploads a CSV file and returns an import report.

Expected CSV header (case-insensitive):

```csv
name,email,date_of_birth,annual_income
```

Example valid CSV:

```csv
name,email,date_of_birth,annual_income
John Doe,john@example.com,1990-05-12,50000
Jane Smith,jane@example.com,1985-01-01,75000
```

---

### Response Structure

```json
{
  "total_rows_processed": 2,
  "imported_count": 2,
  "failed_count": 0,
  "aborted": false,
  "fatal_error": null,
  "imported": [
    { "id": 1, "name": "John Doe" },
    { "id": 2, "name": "Jane Smith" }
  ],
  "errors": []
}
```

Each failed row includes:

- `row_number` — the exact line in the file (header = row 1)
- `values` — the submitted values for that row
- `errors` — array of `{ field, message }` objects (all validation failures)

Validation rules:

- Name and email are required
- Email must be valid format
- Email must be unique (within the file and against the database)
- Date of birth must be valid and not in the future
- Annual income must be numeric and positive

Empty rows are skipped silently.  
Malformed rows are reported without blocking valid rows.  
All validation errors per row are returned — not just the first.

---

### GET /api/customers

Returns a paginated list of imported customers.

```bash
GET http://localhost:8080/api/customers?per_page=10&page=1
```

`per_page` is clamped between 1 and 50.

---

## Error Handling Philosophy

This implementation strictly follows the brief:

• No silent failures  
• No corrupted partial commits  
• No hidden validation errors  

### File-level errors

If the file is:

- Empty
- Missing header
- Wrong header format

The API returns HTTP 422 before row processing begins.

### Row-level errors

If individual rows fail validation:

- They are reported
- Valid rows continue importing
- Each row includes all validation errors

### Database failure handling

Imports are processed in batches.  
If a fatal database failure occurs:

- Previously committed batches remain intact
- Remaining rows are marked as failed
- The response includes `aborted = true`
- A `fatal_error` message is returned

This ensures transparency and protects data integrity.

---

## Architecture Decisions

### Design Scope Decisions

Why no DTOs or background jobs?

This assignment focuses on file processing, validation design, error reporting, and data integrity. Introducing DTO layers or queue-based background processing would add architectural complexity without improving clarity or correctness for this specific brief.


### Thin Controllers

Controllers delegate business logic to a dedicated service class.  
This improves testability and separation of concerns.

### Dedicated Validation Layer

`CustomerRowValidation` centralises rules and messages, keeping the
service focused on orchestration logic.

### Batch Transactions

Imports are processed in batches rather than a single global transaction.

This prevents a late database failure from rolling back previously
successful rows, aligning with the partial import requirement.

### Prefetched Duplicate Detection

Emails are collected upfront and checked against the database using a
single `WHERE IN` query for performance efficiency.

### Case Normalisation

Headers and emails are trimmed and lowercased before comparison to
ensure consistency regardless of user input formatting.

### Error Deduplication

Duplicate error messages per field are removed to prevent noisy
responses.

---

## Testing

Run the full test suite:

```bash
docker compose exec app php artisan test
```

Test scenarios covered include:

- Fully valid CSV
- Mixed valid and invalid rows
- Duplicate emails within file
- Duplicate emails against database
- Every individual validation rule
- Empty file
- File with only headers
- Malformed CSV rows
- Multiple validation errors per row

---

## Testing with Postman

1. Open Postman
2. Create a new POST request
3. Set URL to `http://localhost:8080/api/import`
4. Go to the Body tab
5. Select form-data
6. Add a key named `file`
7. Change the type to File
8. Select your CSV file
9. Add header `Accept: application/json`
10. Send the request

## Alternative Testing Methods

If Postman is unavailable, the API can also be tested using:

### cURL

```bash
curl -X POST http://localhost:8080/api/import   -H "Accept: application/json"   -F "file=@customers.csv"
```

### Laravel HTTP Tests

Feature tests are included for automated verification.

---

## Sample CSV

Sample CSV files are included in:

```
samples/
```

These include both fully valid and mixed validation examples.

---

## Git History

A small number of clear, focused commits were used to demonstrate
incremental development and reasoning.

```
git log --oneline -10
```
