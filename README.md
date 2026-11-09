# Niwmi – User Profile Subsystem

<sub>🚧 In Development</sub>

An independent implementation of the user profile subsystem extracted and simplified from **Niwmi**, a social media platform I previously designed, built, and operated.

This repository focuses on backend engineering practices, including modular architecture, data consistency, concurrent operations, and asynchronous processing.

## What’s Inside?

The subsystem currently consists of four modules:

- **Authentication:** Cookie-based authentication using Laravel Sanctum `(v1)`.
- **User Profile:** User profile information and management.
- **Follow System:** Follow/unfollow operations and relationship management.
- **Notification System:** Asynchronous notification processing and activity aggregation.

## Engineering Highlights

- **Data Consistency:** Transactional database operations, relationship integrity, and Redis-backed counter synchronization.
- **Asynchronous Processing:** Queue-driven notification processing and aggregation.
- **Automated Testing:** Feature and integration tests covering validation, authorization, business logic, edge cases, and concurrent operations.

## Tech Stack

| Category | Technology |
|---|---|
| Backend | PHP 8.x, Laravel 12 |
| Database | PostgreSQL |
| Cache & Queue | Redis |
| Authentication | Laravel Sanctum |
| Testing | PHPUnit |
| CI | GitHub Actions |

## Development Approach

- Each module has its own development branch, routes, and test suite.
- Modules are developed and tested independently.
- Automated tests cover both expected behavior and potential failure scenarios.

## Documentation (Coming soon)

Detailed documentation covering architecture, module implementation, engineering decisions, and testing strategies will be maintained in the [`DOCUMENTATIONS/`](DOCUMENTATIONS/) directory.

---

**Project Status:** Actively under development.