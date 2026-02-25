Debt Register CSV Import API

Overview

This project implements a Laravel based CSV import system with full
validation, partial imports, detailed error reporting, and pagination.
It follows clean architecture principles and includes a comprehensive
automated test suite.

The application exposes two main endpoints:

POST /api/import GET /api/customers

System Requirements

Docker Docker Compose

Docker Setup Instructions

1.  Copy environment file

cp .env.example .env

2.  Build and start containers

docker compose up -d --build

3.  Install dependencies

docker compose exec app composer install

4.  Generate application key

docker compose exec app php artisan key:generate

5.  Run database migrations

docker compose exec app php artisan migrate

The API will be available at:

http://localhost:8080

API Usage

Import Customers

Endpoint: POST http://localhost:8080/api/import

Expected CSV header:

name,email,date_of_birth,annual_income

Example valid CSV:

name,email,date_of_birth,annual_income John
Doe,john@example.com,1990-05-12,50000 Jane
Smith,jane@example.com,1985-01-01,75000

Response structure:

total_rows_processed imported_count failed_count imported errors

Each failed row includes:

row_number values errors list with field and message

Empty rows are skipped silently. Malformed rows are reported but do not
stop valid imports. Duplicate emails within the same file are flagged on
both rows. Duplicate emails already existing in the database are
rejected. All validation errors for a row are returned, not just the
first one.

List Customers

Endpoint: GET http://localhost:8080/api/customers

Query parameters:

per_page page

Example:

GET http://localhost:8080/api/customers?per_page=10&page=1

Testing With Postman

1.  Open Postman
2.  Create a new POST request
3.  Set URL to http://localhost:8080/api/import
4.  Go to Body
5.  Select form data
6.  Add key named file
7.  Change type to File
8.  Select your CSV file
9.  Add header Accept: application/json
10. Send request

Important: Do not manually set Content Type. Postman will automatically
set multipart form data.

Running Automated Tests

Run full test suite:

docker compose exec app php artisan test

Run specific test file:

docker compose exec app php artisan test --filter=ImportValidCsvTest

Development Commands

View logs:

docker compose logs -f

Reset database:

docker compose exec app php artisan migrate:fresh

Stop containers:

docker compose down

Architecture Notes

Controllers are thin and delegate logic to a service class. CSV row
validation rules are extracted into a dedicated class. Custom exceptions
are rendered into consistent JSON validation responses. Pagination uses
Laravel native paginator with API resources. Feature tests cover all
required scenarios from the specification.
