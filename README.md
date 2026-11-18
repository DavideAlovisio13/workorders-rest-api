# workorders-rest-api

> A small, **framework-free REST API** (PHP 8) for managing **work orders / commesse**: full CRUD, **bearer-token auth with roles**, input validation, pagination, filtering & sorting, and a complete **OpenAPI 3** contract. SQLite-backed, zero external dependencies, with a dependency-free test suite.

[![License: MIT](https://img.shields.io/badge/License-MIT-informational.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![Spec](https://img.shields.io/badge/contract-OpenAPI%203.0-85ea2d)
![Tests](https://img.shields.io/badge/tests-31%2F31%20passing-brightgreen)

## Why

A clean REST API is bread-and-butter for any company's internal tooling. This one models **work orders** — the kind of job/commessa tracking found in manufacturing and services ERPs (a domain I've worked in) — and shows how to build a correct, well-documented HTTP API **without a framework**: routing, auth, validation, proper status codes and an OpenAPI contract, all explicit and easy to read. It's the "modern REST" counterpart to my contract-first [SOAP gateway](https://github.com/DavideAlovisio13).

## Features

- **CRUD** for work orders with correct HTTP semantics (`201` + `Location`, `204`, `404`, `405`, `409`, `422`).
- **Auth**: `Authorization: Bearer <key>` with roles — `admin` (read+write) and `viewer` (read-only).
- **Validation**: required fields, enum checks (status/priority), numeric & date validation → `422` with per-field details.
- **List**: pagination (`page`, `per_page`), filtering (`status`, `priority`, `q` search), sorting (`sort`, `order`) with a safe column allowlist.
- **OpenAPI 3** contract in [`openapi.yaml`](openapi.yaml) — import into Swagger UI / Postman / Insomnia.
- **Secure by default**: parameterized SQL everywhere, `X-Content-Type-Options: nosniff`, generic 500s (no internals leaked).

## Resource

`WorkOrder { id, code, title, customer, status, priority, assignee, hours_estimated, hours_spent, due_date, created_at, updated_at }`
`status ∈ {open, in_progress, on_hold, done, cancelled}` · `priority ∈ {low, medium, high, urgent}`

## Endpoints

| Method | Path | Role | Description |
|--------|------|------|-------------|
| GET | `/api/health` | — | Health check |
| GET | `/api/work-orders` | viewer | List (paginated/filterable) |
| POST | `/api/work-orders` | admin | Create |
| GET | `/api/work-orders/{id}` | viewer | Get one |
| PUT / PATCH | `/api/work-orders/{id}` | admin | Update |
| DELETE | `/api/work-orders/{id}` | admin | Delete |

## Quick start

Requires PHP 8.1+ with `pdo_sqlite` (bundled in most PHP builds).

```bash
php bin/seed.php                              # create + seed the SQLite store
php -S 127.0.0.1:8080 public/index.php        # run the API
```

Demo keys (change before any real use — see `config/config.php`):
`admin-key-123` (admin) · `viewer-key-abc` (viewer).

```bash
# list (read)
curl -H "Authorization: Bearer viewer-key-abc" \
     "http://127.0.0.1:8080/api/work-orders?status=open&per_page=5"

# create (admin)
curl -X POST -H "Authorization: Bearer admin-key-123" -H "Content-Type: application/json" \
     -d '{"code":"WO-2026-010","title":"New rig","customer":"Zeta","priority":"high"}' \
     http://127.0.0.1:8080/api/work-orders

# update
curl -X PATCH -H "Authorization: Bearer admin-key-123" -H "Content-Type: application/json" \
     -d '{"status":"done","hours_spent":12}' \
     http://127.0.0.1:8080/api/work-orders/1
```

### Docker

```bash
docker build -t workorders-rest-api .
docker run -p 8080:8080 workorders-rest-api
```

## Testing

```bash
php tests/run-tests.php
# All 31 tests passed.
```
Covers the repository, controller validation, RBAC, the router and token auth against a throwaway database — and it was verified end-to-end over HTTP (status codes, pagination, filters, auth).

## Project structure

```
workorders-rest-api/
├── openapi.yaml                 # OpenAPI 3 contract
├── public/index.php             # front controller (routing + auth middleware)
├── src/
│   ├── Support/                 # Request, Response, Router, Database
│   ├── Auth/TokenAuth.php       # bearer API-key auth + roles
│   ├── Domain/WorkOrderRepository.php
│   └── Http/WorkOrderController.php   # CRUD + validation
├── config/config.php
├── bin/seed.php
├── tests/run-tests.php
└── Dockerfile
```

---

<sub>Author: **Davide Alovisio** — Full-Stack Developer & IT · [portfolio](https://davidealovisioportfolio.com) · [GitHub](https://github.com/DavideAlovisio13)</sub>
