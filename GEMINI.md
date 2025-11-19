# MiniWallet Gemini Markdown

This document provides a comprehensive overview of the MiniWallet project, its architecture, and development conventions to be used as instructional context for future interactions.

## Project Overview

MiniWallet is a full-stack web application that allows users to transfer money to each other. It is built with a modern tech stack and is designed to be scalable, reliable, and easy to use.

**Key Features:**

*   **Security:** Sanctum Authentication, Email Validation, Idempotency Keys, Row-Level Locking.
*   **Money Transfer:** Atomic Transactions, Commission Calculation (1.5% fee rounded up), Deterministic Locking, Balance Validation.
*   **Data Integrity:** Balance Snapshots, Transaction Outbox, Append-Only Ledger, Integer Arithmetic for money.
*   **Performance:** Repository Pattern, Eager Loading, Pagination, Database Indexing.

**Key Technologies:**

*   **Backend:**
    *   **Framework:** Laravel 11.x
    *   **Language:** PHP 8.3+
    *   **Database:** MySQL 8.0
    *   **Queue:** Redis
    *   **Broadcasting:** Pusher
    *   **Authentication:** Laravel Sanctum
*   **Frontend:**
    *   **Framework:** Vue 3 (Composition API)
    *   **Language:** TypeScript
    *   **Build Tool:** Vite
    *   **Styling:** Vuetify
    *   **HTTP Client:** Axios
    *   **Real-time:** Laravel Echo + Pusher
    *   **State Management:** Pinia
*   **Infrastructure:**
    *   **Containerization:** Docker + Docker Compose
    *   **Web Server:** Nginx
    *   **Process Manager:** Supervisor

## Architecture

The backend follows **Clean Architecture** principles with clear separation of concerns:

*   **Controllers (HTTP Layer):** Handle HTTP requests and responses.
*   **FormRequests (Validation):** Validate incoming data.
*   **Services (Business Logic):** Contain business rules and complex operations (e.g., `AuthService`, `TransferService`).
*   **Repositories (Data Access):** Handle database queries and data persistence.
*   **Models (Eloquent ORM):** Represent data.

**Money Transfer Flow:**

1.  Client sends `POST /api/transactions/validate-receiver` to validate receiver email.
2.  Client sends `POST /api/transactions` with `receiver_email` and `amount` in cents.
3.  `TransferService` handles the transaction:
    *   Checks idempotency key.
    *   Locks users in deterministic order.
    *   Validates sufficient balance.
    *   Calculates commission (1.5%).
    *   Updates balances atomically.
    *   Creates transaction record and balance snapshots.
    *   Creates an outbox event for reliable event delivery.
4.  Response is returned with new balances.

## Building and Running

The project is fully containerized and can be run with a single command.

**Prerequisites:**

*   Docker & Docker Compose
*   Node.js 18+ (for local frontend dev)
*   PHP 8.3+ (for local backend dev)

**One-Step Setup (Recommended):**

1.  Make the setup script executable:
    ```bash
    chmod +x setup.sh
    ```
2.  Run the setup script:
    ```bash
    ./setup.sh
    ```

This script will:

*   Check for dependencies.
*   Set up the environment.
*   Build and start the Docker containers.
*   Run database migrations.
*   Seed the database with sample data.
*   Run health checks.

**Docker Compose:**

```bash
docker-compose up -d
```

## Development Conventions

### Backend

*   **Coding Style:** The project follows the PSR-12 coding style guide. Use `vendor/bin/pint` to format the code.
*   **Testing:** The backend has a suite of tests written with PHPUnit. Run the tests with `php artisan test`.
*   **Database Migrations:** Database schema changes are managed with Laravel's migration system. Create new migrations with `php artisan make:migration`.
*   **API:** The backend provides a RESTful API. The API routes are defined in `backend/routes/api.php`.

### Frontend

*   **Coding Style:** The project uses ESLint to enforce a consistent coding style. Run the linter with `pnpm lint`.
*   **State Management:** The frontend uses Pinia for state management. The stores are defined in `frontend/src/stores`.
*   **Routing:** The frontend uses Vue Router for routing. The routes are defined in `frontend/src/router/index.ts`.
*   **Components:** The UI is built with Vuetify components. Custom components are located in `frontend/src/components`.

## API Endpoints

### Authentication
```http
POST   /api/auth/register        # Register new user
POST   /api/auth/login           # Login user
POST   /api/auth/logout          # Logout user
GET    /api/auth/user            # Get authenticated user
```

### Transactions
```http
POST   /api/transactions/validate-receiver   # Validate receiver email
POST   /api/transactions                      # Transfer money
GET    /api/transactions                      # List transactions (paginated)
GET    /api/transactions/stats                # Get transaction statistics
GET    /api/transactions/{uuid}               # Get single transaction
```

### Balance
```http
GET    /api/balance              # Get current balance
```