# CSV Import API

## Overview

This project implements a Laravel-based CSV import API with full
validation, partial imports, detailed error reporting, pagination, and
automated test coverage.

The system is designed with clean architecture principles and follows
Laravel best practices.

---

## Features

✔ CSV upload endpoint with detailed import reporting  
✔ Partial imports — valid rows are never blocked by invalid ones  
✔ Duplicate detection within the same file and against the database  
✔ Case-insensitive header and email matching  
✔ Malformed row handling  
✔ Consistent JSON validation responses  
✔ Pagination for imported customers  
✔ Comprehensive automated test coverage  

---

## System Requirements

- Docker
- Docker Compose

---

## Docker Setup

### 1. Copy environment file

```bash
cp .env.example .env
```

### 2. Build and start containers

```bash
docker compose up -d --build
```

### 3. Install dependencies

```bash
docker compose exec app composer install
```

### 4. Generate application key

```bash
docker compose exec app php artisan key:generate
```

### 5. Run database migrations

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

### Response Structure

```json
{
  "total_rows_processed": 2,
  "imported_count": 2,
  "failed_count": 0,
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
- `errors` — array of `{ field, message }` objects, one per validation failure

Validation rules:

- Name and email are required
- Email must be a valid format
- Email must be unique (within the file and against the database)
- Date of birth must be a valid date in the past
- Annual income must be numeric and positive

Empty rows are skipped silently.  
Malformed rows (wrong column count) are reported without blocking valid rows.  
All validation errors per row are returned — not just the first.

---

### GET /api/customers

Returns a paginated list of imported customers.

```bash
GET http://localhost:8080/api/customers?per_page=10&page=1
```

`per_page` is clamped between 1 and 50.

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

> **Important:** Do not manually set `Content-Type`. Postman automatically sets multipart form-data with the correct boundary.

---

## Running Automated Tests

Run the full test suite:

```bash
docker compose exec app php artisan test
```

Run a specific test class:

```bash
docker compose exec app php artisan test --filter=ImportValidCsvTest
```

---

## Development Commands

View logs:

```bash
docker compose logs -f
```

Reset database:

```bash
docker compose exec app php artisan migrate:fresh
```

Stop containers:

```bash
docker compose down
```

---

## Architecture Notes

**Thin controllers, dedicated service layer**  
The `ImportController` does nothing except receive the request and hand off to `CustomerImportService`. All business logic lives in the service, making it independently testable and replaceable.

**Validation extracted to a support class**  
`CustomerRowValidation` holds all validation rules and messages in one place. This keeps the service focused on orchestration rather than rule definitions, and makes it easy to extend or change rules without touching import logic.

**Custom exceptions rendered into consistent JSON responses**  
`InvalidCsvFileException` and `InvalidCsvHeaderException` both extend `UnprocessableEntityHttpException` and are caught in `bootstrap/app.php`. This means file-level errors return the same `{ message, errors }` shape as Laravel's standard validation responses, so API clients never need to handle a different error format.

**No global transaction around inserts**  
Each valid row is inserted independently. Wrapping all inserts in a single transaction would mean a DB error on row 50 could silently roll back the 49 rows already imported — directly contradicting the partial import requirement. Independent inserts mean each valid row is committed as soon as it passes validation.

**Single prefetch query for DB duplicate detection**  
Rather than running a uniqueness query per row (N queries for N rows), all emails from the file are collected upfront and checked against the database in one `WHERE IN` query. The result is cached in memory for the duration of the import. This keeps the import efficient regardless of file size.

**Case-insensitive header and email normalisation**  
CSV headers are lowercased and trimmed before comparison, so files exported from Excel or other tools with capitalised headers are accepted without error. Emails are also normalised to lowercase at parse time, ensuring duplicate detection and database lookups are consistent regardless of how the user typed the address.

**database error insertion**  
If a database error occurs during insertion of a specific row, that row is reported as failed while previously committed rows remain intact. The import process continues unless a fatal database failure prevents further processing.

---

## Sample CSV

A sample CSV file with a mix of valid and invalid rows is included at:

```
storage/samples/customers_sample.csv
```

This can be used directly with Postman or curl to demo the API.