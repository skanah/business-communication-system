# Business Communication System

A **multi-tenant, omnichannel business communication platform** for managing contacts, audience segments, automated workflows, and marketing campaigns across **SMS and Email** — with a unified inbox, delivery/cost analytics, consent (GDPR) enforcement, and a modern Vue 3 dashboard.

Built as a decoupled application:

- **`backend/`** — REST API built with **Laravel 12** (PHP 8.2+)
- **`frontend/`** — Single-page application built with **Vue 3 + TypeScript + Vite**

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Environment Variables](#environment-variables)
- [Authentication](#authentication)
- [Multi-Tenancy](#multi-tenancy)
- [API Reference](#api-reference)
- [Scheduled Jobs](#scheduled-jobs)
- [Testing](#testing)
- [Database Schema Overview](#database-schema-overview)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features

### Contact Management
- Full CRUD for contacts with custom attributes, tags, timezone, and country code
- **Bulk import / export** of contacts
- **Consent tracking** (SMS + Email) with consent-given timestamps
- **GDPR tooling** — anonymize and hard-delete (right to erasure) endpoints
- Soft deletes for recoverable removal

### Segmentation
- Rule-based **dynamic segments** (`all` / `any` / `none` rule groups)
- Rule operators: equals, not-equals, in, not-in, contains, exists, and more
- **Segment preview** — see which contacts match before saving
- Test a single contact against a segment's rules
- Automatic contact-count recalculation

### Workflow Automation
- Visual **workflow graphs** (nodes + edges stored as JSON) for automation journeys
- Workflow validation, preview, and per-contact execution
- Workflow execution history and statistics
- Dynamic membership workflows and tagging

### Campaign Management
- Campaign types: **broadcast**, **drip**, and **triggered**
- Lifecycle: draft, scheduled, running, paused, completed, cancelled
- **Scheduling engine** (`CampaignScheduler`): scheduled, recurring, and event-triggered campaigns
- **Audience preview** — count/list contacts a campaign will reach before launch
- Start / pause / resume control endpoints
- **Statistics**: sent, delivered, opened, clicked, replied, bounced, unsubscribed + delivery/open/click/reply rates
- Campaign logging for an auditable send history

### SMS & Email Messaging
- Send SMS and Email via configurable providers
- Message history with search, filters, and pagination
- Delivery reports (SMS) and attachment handling (Email)
- Per-user **communication profiles** and preferences
- Cost tracking per message with currency support

### Unified Inbox
- All SMS + Email messages in **one normalized inbox**
- Filter by channel (sms/email), direction (incoming/outgoing), status, and date range
- Full-text **search** across content, subject, and sender/receiver
- Mark-as-read, delete, and **inbox statistics** (totals, unread, today, per-channel breakdown, cost)

### Multi-Tenancy & Security
- Row-level **tenant isolation** enforced by middleware on every request
- **API key authentication** (`sk-...` keys) with per-key permission scopes
- Laravel **Sanctum** token auth for the SPA dashboard
- Consent enforcement middleware — messages are blocked to contacts without valid consent
- Immutable **event logging** for contact and system events

### Billing Foundation
- `Wallet` and `Transaction` models per user for messaging-credit accounting (foundation for prepaid billing)

---

## Tech Stack

| Layer      | Technology                                                                 |
|------------|----------------------------------------------------------------------------|
| Backend    | PHP 8.2+, Laravel 12, Eloquent ORM, Sanctum, database-driven queues        |
| Database   | MySQL / MariaDB (configurable; SQLite supported out of the box for dev)    |
| Frontend   | Vue 3 (Composition API, `<script setup>`), TypeScript, Vite                |
| State      | Pinia                                                                       |
| Routing    | Vue Router 4                                                                |
| HTTP       | Axios (centralized API service with auth interceptors)                     |
| Testing    | PHPUnit (backend), Cypress E2E/component (frontend), vue-tsc type-checking   |
| Linting    | ESLint + Prettier + oxlint (frontend), Laravel Pint (backend)              |

---

## Project Structure

```
bcsystem/
├── backend/                          # Laravel 12 API
│   ├── app/
│   │   ├── Console/Commands/         # process-scheduled-campaigns command
│   │   ├── Http/
│   │   │   ├── Controllers/API/      # Auth, Contact, Segment, Workflow, Campaign,
│   │   │   │                         # SMS, Email, UnifiedInbox, Communication
│   │   │   └── Middleware/           # ApiKey, Tenant scoping, Consent enforcement
│   │   ├── Models/                   # Tenant, User, Contact, Segment, Workflow,
│   │   │                             # Campaign, SmsMessage, EmailMessage, Wallet...
│   │   └── Services/                # CampaignScheduler, ChannelRegistry, ConsentService
│   ├── database/
│   │   ├── migrations/               # Full schema incl. soft deletes & statistics
│   │   └── factories/                # Model factories for seeding/testing
│   ├── routes/api.php                # All REST endpoints
│   └── tests/                        # Feature (API) + Unit tests
│
├── frontend/                         # Vue 3 SPA
│   ├── src/
│   │   ├── views/
│   │   │   ├── auth/                 # Login, Register
│   │   │   ├── communication/        # Unified Inbox, Services, Preferences
│   │   │   ├── DashboardView.vue
│   │   │   └── SettingsView.vue
│   │   ├── stores/                   # Pinia stores (auth, communication)
│   │   ├── services/apiService.ts    # Axios wrapper with token + 401 handling
│   │   └── router/                   # Vue Router setup
│   └── cypress/                      # E2E/component tests
│
└── README.md
```

---

## Requirements

- **PHP** >= 8.2 (with `pdo_mysql`/`pdo_sqlite`, `mbstring`, `openssl`, `bcmath` recommended)
- **Composer** >= 2.x
- **Node.js** >= 20.19 (or >= 22.12) with npm
- **MySQL** 8+ / MariaDB 10.10+ (or use SQLite for local development)

---

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/skanah/business-communication-system.git
cd business-communication-system
```

### 2. Set up the backend (Laravel API)

```bash
cd backend

# Install PHP dependencies
composer install

# Create the environment file
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate

# Configure your database in .env, then migrate (add --seed for sample data)
php artisan migrate --seed

# Start the API server (defaults to http://localhost:8000)
php artisan serve
```

> **SQLite (zero-config dev):** Laravel 12 ships with `DB_CONNECTION=sqlite` — simply run
> `php artisan migrate` and you are ready. For MySQL, set `DB_CONNECTION=mysql` and the
> corresponding `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` values.

### 3. Set up the frontend (Vue SPA)

```bash
cd ../frontend

# Install Node dependencies
npm install

# Start the dev server (defaults to http://localhost:5173)
npm run dev
```

The frontend expects the API at `http://localhost:8000/api` — adjust `frontend/src/config/api.ts` if your backend runs elsewhere.

### 4. Register an account

Register from the SPA (`/register`) or via the API:

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","email":"jane@example.com","password":"secret123","password_confirmation":"secret123"}'
```

The SPA stores the returned token automatically when registering or logging in through the UI.

---

## Environment Variables

### Backend — `backend/.env`

| Variable | Description |
|-----------|-------------|
| `APP_NAME`, `APP_ENV`, `APP_KEY` | Standard Laravel app settings |
| `DB_CONNECTION` | `sqlite` (default) or `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database connection details |
| `QUEUE_CONNECTION` | `database` — used for queued campaign processing |
| `CACHE_STORE`, `SESSION_DRIVER` | `database` by default |

> ⚠️ `.env` files are git-ignored — never commit real credentials.

---

## Authentication

The platform supports **two authentication modes**:

### 1. Personal tokens (SPA dashboard) — Laravel Sanctum
Used by the Vue frontend. Login/register returns a token stored in `localStorage` and attached to every request by the Axios interceptor.

```http
Authorization: Bearer <sanctum-token>
```

### 2. API keys (server-to-server / integrations)
Tenant-scoped keys (`sk-...`) with granular permission scopes, validated by `ApiKeyMiddleware`. Used for all resource endpoints (contacts, segments, workflows, campaigns, SMS, email).

```http
Authorization: Bearer sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

Each API key is bound to a **tenant**, and every query is automatically scoped to that tenant — requests can never read or modify another tenant's data.

---

## Multi-Tenancy

- Every core record (contacts, segments, workflows, campaigns, API keys) carries a `tenant_id`.
- `TenantMiddleware` resolves the tenant from the authenticated API key and injects it into the request.
- Controllers query strictly by `tenant_id`; cross-tenant access returns **404**.
- Tenants have their own `domain` / `subdomain`, settings, and trial metadata.

---

## API Reference

All endpoints are prefixed with `/api`. Protected routes require the `Authorization` header described above.

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/register` | Register user (returns token) |
| POST | `/login` | Login (returns token) |
| POST | `/logout` | Invalidate current token |
| GET | `/me` | Current authenticated user |

### Contacts *(API key + tenant)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/contacts` | List (paginate, filter, search) |
| POST | `/contacts` | Create |
| GET / PUT / DELETE | `/contacts/{id}` | Show / update / soft-delete |
| POST | `/contacts/import` | Bulk import |
| GET | `/contacts/export` | Export |
| POST | `/contacts/{id}/anonymize` | GDPR anonymize |
| POST | `/contacts/{id}/gdpr-delete` | GDPR hard delete |

### Segments *(API key + tenant)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET / POST | `/segments` | List / create rule-based segment |
| GET / PUT / DELETE | `/segments/{id}` | Show / update / soft-delete |
| GET | `/segments/{id}/preview` | Matching-contact preview |
| POST | `/segments/{segmentId}/test-contact/{contactId}` | Test contact against rules |
| POST | `/segments/{id}/recalculate-count` | Refresh member count |

### Workflows *(API key + tenant)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET / POST | `/workflows` | List / create graph workflow |
| GET / PUT / DELETE | `/workflows/{id}` | Show / update / soft-delete |
| GET | `/workflows/{id}/preview` | Matching-contact preview |
| POST | `/workflows/{workflowId}/test-contact/{contactId}` | Test contact against workflow |
| POST | `/workflows/{id}/execute-contact/{contactId}` | Execute workflow for a contact |
| GET | `/workflows/{id}/executions` | Execution history |
| GET | `/workflows/{id}/stats` | Execution statistics |

### Campaigns *(API key + tenant)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET / POST | `/campaigns` | List / create (broadcast, drip, triggered) |
| GET / PUT / DELETE | `/campaigns/{id}` | Show / update / soft-delete |
| GET | `/campaigns/{id}/preview` | Audience preview |
| GET | `/campaigns/{id}/stats` | Full delivery/engagement statistics |
| POST | `/campaigns/{id}/start` · `/pause` · `/resume` | Lifecycle control |
| POST | `/campaigns/{id}/recalculate-count` | Refresh audience count |

### Messaging *(API key)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET / POST | `/sms/config` | Read / update SMS provider config |
| POST | `/sms/send` | Send SMS |
| GET | `/sms/messages` · `/sms/stats` · `/sms/reports` | History, statistics, delivery reports |
| GET / POST | `/email/config` | Read / update email provider config |
| POST | `/email/send` | Send email |
| GET | `/email/messages` · `/email/stats` | History & statistics |

### Unified Inbox *(Sanctum token)*
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/inbox/messages` | Combined SMS+Email. Filters: `channel`, `message_type`, `message_status`, `search`, `start_date`, `end_date`, `sort_by`, `sort_order`, `page`, `limit` |
| GET | `/inbox/messages/{id}` | Single message detail |
| PUT | `/inbox/messages/{id}/read` | Mark as read |
| DELETE | `/inbox/messages/{id}` | Delete message |
| GET | `/inbox/stats` | Inbox statistics |

### Response envelope

Successful responses:

```json
{
  "success": true,
  "data": { },
  "message": "..."
}
```

Paginated list responses include standard `data` / `links` / `meta` blocks:

```json
{
  "success": true,
  "data": {
    "data": [ ],
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
    "meta": { "current_page": 1, "last_page": 1, "total": 3, "per_page": 15 }
  }
}
```

Errors use `success: false` with a `message` and (for validation) an `errors` object, plus appropriate 4xx/5xx status codes.

---

## Scheduled Jobs

The backend ships with an artisan command for processing the campaign schedule:

```bash
php artisan campaigns:process-scheduled
```

Run it via the scheduler so scheduled and recurring campaigns launch automatically:

```bash
* * * * * cd /path/to/backend && php artisan schedule:work >> /dev/null 2>&1
```

---

## Testing

### Backend

```bash
cd backend
php artisan test          # full suite
php artisan test --compact # compact output
```

The suite covers contacts, segments, workflows, campaigns, unified inbox, authentication, and tenant isolation — **56 tests / 225 assertions**, all passing.

> Tests run against an isolated database (SQLite in-memory by default) and never touch your application data.

### Frontend

```bash
cd frontend
npm run type-check   # vue-tsc — must exit clean
npm run build        # production build (runs type-check too)
npm run lint         # ESLint + oxlint
npm run test:e2e     # Cypress end-to-end (requires preview server)
```

---

## Database Schema Overview

| Table | Purpose |
|-------|---------|
| `tenants` | Root organizations (unique domain, settings, trial) |
| `users` | Dashboard users scoped to tenants |
| `api_keys` | Per-tenant integration keys with permissions |
| `contacts` | Audience records (soft-deletable, consent, tags, attributes) |
| `segments` | Rule-based dynamic audiences (JSON rules) |
| `workflows` | Automation graphs (nodes/edges JSON, soft deletes) |
| `workflow_executions` | Per-contact workflow run history |
| `campaigns` | Broadcast/drip/triggered campaigns with scheduling & counters |
| `campaign_logs` | Per-message campaign audit trail |
| `sms_messages` / `email_messages` | Channel message stores with cost tracking |
| `conversations` / `messages` | Conversation threading |
| `call_logs` | Voice call records (foundation for telephony) |
| `wallets` / `transactions` | Per-user messaging-credit accounting |
| `communication_profiles` | Per-user channel preferences and configuration |
| `event_logs` | Immutable event audit trail |

---

## Roadmap

- **Voice/Telephony** — SIP and WebRTC controllers are scaffolded; routing and provider integrations are next
- **Prepaid billing** — wallet/transaction models are in place; top-up flows and spend policies pending
- **Visual workflow builder** — frontend canvas editor for the workflow graph engine
- **Real-time inbox** — websockets/broadcasting for live message delivery
- **More channels** — WhatsApp, push, and voice as first-class campaign channels

---

## License

Released under the MIT License.
