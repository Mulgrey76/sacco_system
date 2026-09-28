# IMARA SACCO — Core Banking & CRM System

A full-stack SACCO (Savings and Credit Co-operative) management system built from a real-world Business Requirements Document, covering member onboarding, savings, shares, loans (full lifecycle), dividends, budgeting/expenses, communication, accounting, and reporting — with a genuine maker-checker approval engine enforced at both the API and UI level.

Built as a learning project, end-to-end: database design → backend API → frontend, with every layer manually tested before moving to the next.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Database | MySQL 8.0 |
| Backend | Laravel 12 (PHP 8.2), Sanctum for API authentication |
| Frontend | React 19 (Vite), Tailwind CSS v4, Framer Motion, Recharts, React Router |
| Payments | Safaricom Daraja API (M-Pesa sandbox) — OAuth + STK Push |

---

## What's Genuinely Implemented

**Core Banking**
- Multi-branch, org-scoped client onboarding with auto-generated, concurrency-safe account numbers
- Wallets, Savings, and Shares accounts — all with maker-checker account creation
- Full **Loan lifecycle**: product configuration → application → guarantor approval (OTP-style) → maker-checker approval (with approval-count scaling by loan amount) → disbursement → repayment schedule generation
- **Real M-Pesa integration** (sandbox): OAuth token exchange and STK Push for loan repayment, with a callback handler that applies payment against the oldest unpaid installment first and deposits any excess into the client's wallet
- Double-entry accounting (Chart of Accounts + Journal Entries) with balance validation enforced in the application layer (MySQL can't enforce cross-row constraints)
- Dividends — declared against real savings balances, paid out by crediting client accounts
- Budget allocations and a 3-stage expense workflow (origination → approval → authorization)
- Communication log (SMS/Email) with honest UI boundaries — WhatsApp/Push/attachments/scheduling are visible in the UI but clearly marked unavailable, since the backend doesn't support them yet
- A generic, reusable **maker-checker engine** — one implementation, reused across savings, shares, loans, and expenses, tested to correctly reject same-user approvals and accept different-user approvals

**Security**
- Real Sanctum token authentication — every route except login is protected
- Bcrypt password hashing, password-reuse prevention, login lockout after repeated failures

---

## Known Limitations (Honest Disclosure)

This is a learning project, not a production system. Specifically:

- **Single-tenant hardcoded**: the frontend assumes `org_id = 1` throughout rather than a real "current organization" concept
- **No automated tests** (PHPUnit or otherwise) — every feature was verified manually via Postman and the browser during development
- **M-Pesa is sandbox-only** — B2C disbursement via Daraja was not completed (requires generating an RSA-encrypted security credential, a separate piece of work); only STK Push repayment is wired to the real API
- **Dark mode** covers the app shell (sidebar, header) but not every individual page's content
- **No USSD channel**, despite being in the original BRD — would require a separate gateway integration
- Some pages (Approvals, Reports, Dividends, Expenses, and several detail pages) were not brought into the later UI redesign pass and still use earlier, simpler styling

---

## Project Structure

```
sacco_system/
├── sacco_solution/     # Laravel backend (API)
└── sacco_frontend/     # React frontend (Vite)
```

---

## Setup

### Prerequisites
- PHP 8.2+, Composer
- Node.js 18+, npm
- MySQL 8.0+

### Backend

```bash
cd sacco_solution
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials, then:

```bash
php artisan migrate
php artisan serve
```

The API runs at `http://127.0.0.1:8000/api`.

> Note: this repo does not include the full custom schema migrations described in the project's design docs (organizations, branches, loans, etc. — ~50 tables across 6 modules). These were built as raw SQL migration files during development; see the project history for the full schema if reconstructing this from scratch.

### Frontend

```bash
cd sacco_frontend
npm install
npm run dev
```

The app runs at `http://localhost:5173`.

### M-Pesa Sandbox (optional)

To test the STK Push repayment flow, register a free app at [developer.safaricom.co.ke](https://developer.safaricom.co.ke/) and add your Consumer Key/Secret to `sacco_solution/.env`:

```
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_key
MPESA_CONSUMER_SECRET=your_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
MPESA_CALLBACK_URL=https://example.com/api/mpesa/stk-callback
```

(The last two are Safaricom's publicly published sandbox test values, not secrets.)

---

## Design Notes Worth Mentioning

- **Generic maker-checker**: rather than building separate approval logic per module, one `approval_requests`/`approval_actions` pair — tagged by `entity_type` — handles savings, shares, loans, and expenses. Adding a new approvable action means adding one `if` branch, not a new subsystem.
- **Concurrency-safe account numbering**: uses row-level locking (`SELECT ... FOR UPDATE` / Laravel's `lockForUpdate()`) so two staff creating accounts simultaneously can never collide on the same number.
- **Idempotent payment processing**: a unique constraint on the external transaction reference makes double-posting the same M-Pesa payment structurally impossible, not just unlikely.

---

## Author's Note

This project was built iteratively, module by module, with each piece tested against real data before moving to the next — a deliberate choice over building everything at once and debugging blind. Several real bugs were found and fixed along the way (a missing auth-enforcement layer, table-naming mismatches, a transaction/rollback conflict, Vite dependency caching issues) — left visible in the project history as evidence of the actual development process rather than a polished-after-the-fact narrative.
