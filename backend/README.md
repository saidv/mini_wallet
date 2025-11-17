# Mini Wallet - Backend API

A production-grade wallet API built with Laravel 11, implementing secure money transfers with atomic transactions, commission calculations, and real-time updates.

## 🏗️ Architecture

This project follows **Clean Architecture** principles with clear separation of concerns:

```
┌─────────────────────────────────────────┐
│           HTTP Request                   │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Controllers (HTTP Layer)          │
│  • AuthController                        │
│  • TransactionController                 │
│  Responsibility: Handle HTTP requests    │
│  and responses only                      │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      FormRequests (Validation)          │
│  • LoginRequest                          │
│  • RegisterRequest                       │
│  • TransferRequest                       │
│  • ValidateReceiverRequest               │
│  Responsibility: Validate incoming data  │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      Services (Business Logic)          │
│  • AuthService                           │
│  • TransferService                       │
│  Responsibility: Business rules and      │
│  complex operations                      │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│    Repositories (Data Access)           │
│  • UserRepository                        │
│  • TransactionRepository                 │
│  Responsibility: Database queries and    │
│  data persistence                        │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│          Models (Eloquent ORM)          │
│  • User                                  │
│  • Transaction                           │
│  • BalanceSnapshot                       │
│  Responsibility: Data representation     │
└──────────────────────────────────────────┘
```

## 📂 Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php          # Authentication endpoints
│   │   └── TransactionController.php       # Transaction endpoints
│   └── Requests/
│       ├── LoginRequest.php                # Login validation
│       ├── RegisterRequest.php             # Registration validation
│       ├── TransferRequest.php             # Transfer validation
│       └── ValidateReceiverRequest.php     # Email validation
├── Models/
│   ├── User.php                            # User model
│   ├── Transaction.php                     # Transaction model
│   ├── BalanceSnapshot.php                 # Balance history
│   └── TransactionOutbox.php               # Event outbox pattern
├── Services/
│   ├── AuthService.php                     # Authentication logic
│   └── TransferService.php                 # Transfer business logic
├── Repositories/
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php     # User data contract
│   │   └── TransactionRepositoryInterface.php # Transaction data contract
│   ├── UserRepository.php                  # User data access
│   └── TransactionRepository.php           # Transaction data access
└── Providers/
    └── AppServiceProvider.php              # Dependency injection bindings
```

## ✨ Key Features

### Security
- **Sanctum Authentication** - Token-based API authentication
- **Email Validation** - Secure receiver validation without exposing user list
- **Idempotency Keys** - Prevent duplicate transactions
- **Row-Level Locking** - Prevents race conditions

### Money Transfer
- **Atomic Transactions** - All-or-nothing guarantees
- **Commission Calculation** - 1.5% fee automatically calculated
- **Deterministic Locking** - Deadlock prevention with ordered locks
- **Balance Validation** - Ensures sufficient funds before transfer

### Data Integrity
- **Balance Snapshots** - Historical balance tracking
- **Transaction Outbox** - Reliable event delivery pattern
- **Append-Only Ledger** - Immutable transaction history
- **Integer Arithmetic** - All money stored as cents (no floats)

### Performance
- **Repository Pattern** - Optimized and reusable queries
- **Eager Loading** - Prevents N+1 queries
- **Pagination** - Cursor-based for large datasets
- **Database Indexing** - Optimized query performance

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

## Money Transfer Flow

```
1. Client → POST /api/transactions/validate-receiver
   ↓ (Validates email, returns user if exists)
   
2. Client → POST /api/transactions
   ↓ (With receiver_email, amount in cents)
   
3. TransferService:
   ├─ Check idempotency key (prevent duplicates)
   ├─ Lock users in deterministic order
   ├─ Validate sufficient balance
   ├─ Calculate commission (1.5%)
   ├─ Update balances atomically
   ├─ Create transaction record
   ├─ Create balance snapshots
   └─ Create outbox event
   
4. Response ← Success with new balances
```

## Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test
php artisan test --filter=TransactionTest
```

## 🛠️ Technology Stack

- **Laravel 11** - PHP framework
- **PHP 8.3** - Programming language
- **MySQL 8.0** - Database
- **Sanctum** - API authentication
- **Docker** - Containerization

## Business Rules

### Commission Calculation
```php
Commission = ceil(Amount × 0.015)
Total Debited = Amount + Commission
```

### Transfer Validation
- Amount must be > 0
- Cannot transfer to self
- Must have sufficient balance (including commission)
- Receiver must exist

### Concurrency Control
- Deterministic lock ordering by user ID
- Row-level locks with `lockForUpdate()`
- Automatic retry on deadlock (max 3 attempts)
- Exponential backoff between retries

## Security Features

### Authentication
- Token-based authentication with Laravel Sanctum
- Password hashing with bcrypt
- Token revocation on logout

### Data Protection
- Email validation prevents user enumeration
- Transaction authorization checks
- SQL injection protection via Eloquent
- CSRF protection

### Concurrency Safety
- Optimistic locking with idempotency keys
- Pessimistic locking with row-level locks
- Atomic database transactions

## Database Schema

### Users Table
```sql
id              BIGINT UNSIGNED PRIMARY KEY
name            VARCHAR(255)
email           VARCHAR(255) UNIQUE
password        VARCHAR(255)
balance         INTEGER DEFAULT 0      # In cents
initial_balance INTEGER DEFAULT 0
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Transactions Table
```sql
id              BIGINT UNSIGNED PRIMARY KEY
uuid            UUID UNIQUE
sender_id       BIGINT UNSIGNED → users.id
receiver_id     BIGINT UNSIGNED → users.id
amount          INTEGER                 # In cents
commission      INTEGER                 # In cents
total_debited   INTEGER GENERATED       # amount + commission
status          ENUM('pending', 'completed', 'failed')
idempotency_key VARCHAR(255) UNIQUE
metadata        JSON
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### Balance Snapshots Table
```sql
id                BIGINT UNSIGNED PRIMARY KEY
user_id           BIGINT UNSIGNED → users.id
balance           INTEGER
transaction_uuid  UUID → transactions.uuid
created_at        TIMESTAMP
```

## Deployment

### Docker
```bash
docker-compose up -d
docker-compose exec backend php artisan migrate --seed
```

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Generate secure `APP_KEY`
- [ ] Configure database credentials
- [ ] Set up queue workers
- [ ] Enable Redis caching
- [ ] Configure log rotation
- [ ] Set up monitoring (Sentry, New Relic)

## Development

### Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

### Code Style
```bash
./vendor/bin/pint  # Laravel Pint (PSR-12)
```

### Database
```bash
php artisan migrate         # Run migrations
php artisan migrate:fresh   # Fresh database
php artisan db:seed         # Seed test data
```

## Best Practices Implemented

✅ **SOLID Principles** - Single responsibility, dependency injection  
✅ **Repository Pattern** - Data access abstraction  
✅ **Service Layer** - Business logic separation  
✅ **FormRequests** - Validation centralization  
✅ **Interface Contracts** - Type safety and testability  
✅ **Idempotency** - Safe retry mechanisms  
✅ **Event Sourcing** - Outbox pattern for reliability  
✅ **Integer Money** - Precision in financial calculations  

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Repository Pattern](https://martinfowler.com/eaaCatalog/repository.html)
- [Transactional Outbox Pattern](https://microservices.io/patterns/data/transactional-outbox.html)
- [Idempotency Keys](https://stripe.com/docs/api/idempotent_requests)

## License