# GNYC Adventist Youth Ministries — Digital Ecosystem Architecture

**Version:** 1.0
**Date:** March 18, 2026
**Status:** Draft — For Review
**Audience:** Director of AYM, Development Team, Stakeholders

---

## Table of Contents

1. [Design Principles](#1-design-principles)
2. [Architecture Overview](#2-architecture-overview)
3. [Component Selection & Rationale](#3-component-selection--rationale)
4. [System Architecture Diagram](#4-system-architecture-diagram)
5. [Data Model & Domain Boundaries](#5-data-model--domain-boundaries)
6. [API Gateway & Integration Layer](#6-api-gateway--integration-layer)
7. [Security Architecture](#7-security-architecture)
8. [HCI & User Experience Architecture](#8-hci--user-experience-architecture)
9. [Infrastructure & Deployment](#9-infrastructure--deployment)
10. [Integration Risk Assessment & Recommendations](#10-integration-risk-assessment--recommendations)
11. [Phase Roadmap](#11-phase-roadmap)
12. [Appendix: ADRs (Architecture Decision Records)](#appendix-architecture-decision-records)

---

## 1. Design Principles

These principles govern every technical decision in this document. They are ordered by priority.

| # | Principle | Implication |
|---|-----------|-------------|
| 1 | **Security by Default** | Zero-trust networking, encrypted at rest and in transit, least-privilege access, OWASP Top 10 mitigations baked in — not bolted on. |
| 2 | **Architecture First** | Every component communicates through well-defined contracts (OpenAPI specs). No direct database sharing between services. |
| 3 | **HCI-Centered** | Every interface is designed for the *actual user* (volunteer club directors on mobile, conference staff on desktop). Performance budgets, accessibility (WCAG 2.1 AA), and progressive disclosure are non-negotiable. |
| 4 | **Mission Continuity** | The system must survive leadership transitions. All institutional knowledge lives in structured data, not in people's heads. |
| 5 | **Future-Ready, Not Future-Bloated** | Design extension points now; build features only when needed. The architecture supports a mobile app, but we don't build one yet. |
| 6 | **Operational Simplicity** | A small team must maintain this. Fewer moving parts win. Managed services over self-hosted where the budget allows. |

---

## 2. Architecture Overview

The proposal calls for a **headless, API-first ecosystem** with four primary domains:

```
┌─────────────────────────────────────────────────────────────────────┐
│                        USERS / CHANNELS                            │
│  ┌──────────┐  ┌──────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │ Website  │  │  PWA /   │  │ Club Leader  │  │ Future Mobile │  │
│  │ (Public) │  │  Mobile  │  │   Portal     │  │     App       │  │
│  └────┬─────┘  └────┬─────┘  └──────┬───────┘  └───────┬───────┘  │
│       │              │               │                  │          │
│       └──────────────┴───────┬───────┴──────────────────┘          │
│                              │                                     │
│                     ┌────────▼────────┐                             │
│                     │   API GATEWAY   │  (Auth, Rate Limit, CORS)  │
│                     └────────┬────────┘                             │
│                              │                                     │
│       ┌──────────┬───────────┼───────────┬──────────┐              │
│       │          │           │           │          │              │
│  ┌────▼───┐ ┌────▼───┐ ┌────▼───┐ ┌─────▼────┐ ┌──▼──────────┐  │
│  │  CMS   │ │  CRM   │ │ Store  │ │ Events   │ │  Analytics  │  │
│  │  API   │ │  API   │ │  API   │ │   API    │ │   (GA4)     │  │
│  └────┬───┘ └────┬───┘ └────┬───┘ └─────┬────┘ └─────────────┘  │
│       │          │          │            │                         │
│  ┌────▼───┐ ┌────▼───┐ ┌────▼───┐ ┌─────▼────┐                   │
│  │WordPress│ │EspoCRM │ │WooCom │ │ HiEvents │                   │
│  │Headless│ │        │ │Headless│ │          │                   │
│  └────────┘ └────────┘ └────────┘ └──────────┘                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Architectural Decisions

- **Next.js 15 (App Router)** as the unified frontend — proven in the GNYC Youth Congress and AUC Camporee projects.
- **Custom Node.js/Express API Gateway** as the integration backbone (not a third-party API gateway — keeps operational complexity low).
- **Each backend system is a domain boundary** — the API Gateway is the *only* component that talks to WordPress, EspoCRM, WooCommerce, and HiEvents. The frontend never calls them directly.

---

## 3. Component Selection & Rationale

### 3.1 CMS — WordPress (Headless)

| Aspect | Detail |
|--------|--------|
| **Role** | Content authoring for manuals, announcements, event photos, educational resources, alert bar messages |
| **Mode** | Headless — admin panel for editors, REST/GraphQL API for consumption |
| **Why WordPress** | Staff familiarity, massive plugin ecosystem, easy media management |
| **API** | WP REST API v2 (built-in) + WPGraphQL plugin for typed queries |

**Recommendation:** Use **WPGraphQL** over the REST API. It allows the frontend to request exactly the fields it needs, reducing payload sizes by 40-60% on mobile. This directly supports HCI goals (faster loads on low-bandwidth connections common in field settings).

**Integration Risk — HIGH:**
> WordPress is the most attacked CMS on the internet. Running it headless reduces the attack surface significantly (no public-facing theme), but the admin panel (`/wp-admin`) is still exposed. See [Security Architecture §7.3](#73-wordpress-hardening) for mandatory mitigations.

### 3.2 CRM — EspoCRM

| Aspect | Detail |
|--------|--------|
| **Role** | People data: club rosters, leader certifications (Master Guide tracking), membership transitions (Adventurer → Pathfinder → AY Leader), monthly report ledger, coordinator approvals |
| **Mode** | Self-hosted, accessed exclusively through its REST API |
| **Why EspoCRM** | Open-source, PHP-based (low hosting cost), highly customizable entities, built-in workflow engine for approval chains |
| **API** | EspoCRM REST API with API key + HMAC authentication |

**Recommendation — CRITICAL:**
> EspoCRM is the **single source of truth for people data**. Do NOT allow WordPress or WooCommerce to independently manage user profiles. All person records originate in or sync back to EspoCRM. This prevents the #1 integration problem: duplicated, conflicting people data across systems.

**Integration Risk — MEDIUM:**
> EspoCRM's API does not support real-time push notifications (webhooks are limited). For the "Coordinator gets alerted when a report is submitted" workflow, implement a **polling worker** or use EspoCRM's built-in Workflow → Webhook action to POST to a notification microservice. Do not rely on the frontend polling EspoCRM directly.

### 3.3 E-Commerce — WooCommerce (Headless)

| Aspect | Detail |
|--------|--------|
| **Role** | Youth Store — uniforms, curriculum materials, event merchandise |
| **Mode** | Headless — WooCommerce backend + WooCommerce REST API, rendered by Next.js frontend |
| **Why WooCommerce** | Shares the WordPress installation (single admin panel for content + store), mature payment gateway integrations |
| **API** | WooCommerce REST API v3 with OAuth 1.0a consumer key/secret |

**Recommendation:**
> Since WooCommerce shares the WordPress installation, it inherits all of WordPress's security risks. Treat the WordPress instance as **high-value infrastructure** — it handles both content *and* financial transactions.

**Integration Risk — HIGH:**
> **Payment Card Industry (PCI) Compliance.** Even though WooCommerce delegates card processing to gateways (Stripe, PayPal), the server still handles order data. If clubs are purchasing with church funds, you may need to support invoicing/PO workflows in addition to card payments. Design the checkout API to be payment-method-agnostic from day one.

> **Inventory sync.** If physical goods (uniforms) are also sold at in-person events, you need a real-time inventory sync strategy or you'll oversell. Recommendation: treat the WooCommerce stock count as authoritative, and build a simple "event POS" interface that decrements stock via the API — same as the check-in pattern used in the GNYC Youth Congress project.

### 3.4 Events — HiEvents

| Aspect | Detail |
|--------|--------|
| **Role** | Event registration, ticketing, capacity management |
| **Mode** | Self-hosted or cloud, integrated via API |
| **API** | HiEvents REST API |

**Recommendation:**
> HiEvents is a newer, less battle-tested platform compared to alternatives. Before committing, validate:
> 1. **Waitlist support** — Camporees and Youth Congress events consistently exceed capacity.
> 2. **Custom fields on registration** — You need church, club name, conference, dietary restrictions, T-shirt size.
> 3. **Webhook support** — For auto-syncing registrants into EspoCRM.
> 4. **Multi-event management** — You run 15+ events/year across Pathfinders, Adventurers, AY, and Master Guide tracks.
>
> **Fallback:** If HiEvents gaps are discovered during Phase 0, consider building event registration directly into the Next.js app with the Express API (you already have this pattern from GNYC Youth Congress). This is more work but gives full control.

### 3.5 Frontend — Next.js 15 (App Router)

| Aspect | Detail |
|--------|--------|
| **Role** | All user-facing experiences: public website, leader portal, store, event registration |
| **Mode** | Server-side rendered (SSR) + static generation (SSG) hybrid |
| **Why Next.js** | Already proven in GNYC Youth Congress and AUC Camporee. Team has deep expertise. App Router enables React Server Components for zero-JS content pages. |

**Recommendation:**
> Use **route groups** to cleanly separate the public site, leader portal, and store:
> ```
> app/
> ├── (public)/          # Public-facing pages (SSG where possible)
> │   ├── page.tsx        # Home
> │   ├── resources/      # Educational hub
> │   └── events/         # Event listings
> ├── (portal)/           # Authenticated leader portal
> │   ├── layout.tsx      # DashboardLayout (reuse from GNYC Youth)
> │   ├── reports/        # Monthly report submission
> │   ├── my-club/        # Club roster & progress
> │   └── certifications/ # Master Guide tracking
> ├── (store)/            # Youth Store
> │   ├── products/
> │   ├── cart/
> │   └── checkout/
> └── (admin)/            # Conference staff dashboard
>     ├── clubs/
>     ├── reports/        # Report review & approval
>     └── analytics/
> ```

### 3.6 Analytics — GA4

| Aspect | Detail |
|--------|--------|
| **Role** | Engagement tracking, resource usage analytics, event funnel analysis |
| **Mode** | Client-side + server-side events via Measurement Protocol |

**Recommendation:**
> Implement **server-side GA4 events** for critical conversions (report submitted, store purchase, event registration) via the Measurement Protocol. Client-side tracking alone is blocked by 30-40% of users running ad blockers. For "missional" metrics that matter, don't rely solely on the browser.

---

## 4. System Architecture Diagram

```
                            ┌─────────────────────┐
                            │   Cloudflare CDN     │
                            │   WAF + DDoS + SSL   │
                            └──────────┬──────────┘
                                       │
                            ┌──────────▼──────────┐
                            │       Caddy          │
                            │   (Reverse Proxy)    │
                            │   Auto-TLS, Headers  │
                            └──────────┬──────────┘
                                       │
                    ┌──────────────────┬┴┬──────────────────┐
                    │                  │ │                  │
           ┌────────▼──────┐  ┌────────▼▼──────┐  ┌────────▼──────┐
           │   Next.js 15  │  │  Express API   │  │   WordPress   │
           │   Frontend    │  │   Gateway      │  │   (Headless)  │
           │   (SSR/SSG)   │  │                │  │  + WooCommerce│
           │   Port 3000   │  │   Port 4000    │  │   Port 8080   │
           └───────────────┘  └───────┬────────┘  └───────┬───────┘
                                      │                   │
                         ┌────────────┼────────────┐      │
                         │            │            │      │
                    ┌────▼───┐  ┌─────▼────┐  ┌────▼──────▼───┐
                    │EspoCRM │  │ HiEvents │  │   MariaDB 11  │
                    │  API   │  │   API    │  │  (Shared DB)  │
                    │Port 80 │  │ Port 80  │  │  Port 3306    │
                    └────┬───┘  └──────────┘  └───────────────┘
                         │
                    ┌────▼───┐
                    │MariaDB │  (EspoCRM's own DB)
                    │  :3307 │
                    └────────┘

                    ┌────────┐
                    │ Redis 7│  Session store, cache,
                    │  :6379 │  rate limiting, queue
                    └────────┘
```

### Container Inventory (Docker Compose)

| Service | Image | Port | Persistent Volume |
|---------|-------|------|-------------------|
| `app` | Next.js 15 (custom) | 3000 | — |
| `api` | Node.js/Express (custom) | 4000 | — |
| `wordpress` | wordpress:6-php8.2-fpm | 8080 | `wp_data`, `wp_uploads` |
| `espocrm` | espocrm/espocrm | 8081 | `espo_data` |
| `hievents` | hievents/hievents | 8082 | — |
| `db` | mariadb:11 | 3306 | `db_data` |
| `espo_db` | mariadb:11 | 3307 | `espo_db_data` |
| `redis` | redis:7-alpine | 6379 | `redis_data` |
| `caddy` | caddy:2-alpine | 80, 443 | `caddy_data`, `caddy_config` |

> **Note on database separation:** WordPress/WooCommerce and the Express API share one MariaDB instance (different databases). EspoCRM gets its own MariaDB instance. This prevents EspoCRM schema migrations from ever affecting the core application database and vice versa.

---

## 5. Data Model & Domain Boundaries

### 5.1 Domain Ownership Map

```
┌─────────────────────────────────────────────────────────┐
│                    DATA DOMAINS                          │
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐ │
│  │   CONTENT   │  │   PEOPLE    │  │    COMMERCE     │ │
│  │  (WordPress)│  │  (EspoCRM)  │  │  (WooCommerce)  │ │
│  │             │  │             │  │                 │ │
│  │ • Pages     │  │ • Contacts  │  │ • Products      │ │
│  │ • Posts     │  │ • Clubs     │  │ • Orders        │ │
│  │ • Media     │  │ • Churches  │  │ • Customers*    │ │
│  │ • Manuals   │  │ • Certs     │  │ • Inventory     │ │
│  │ • Alert Bar │  │ • Reports   │  │                 │ │
│  │ • Videos    │  │ • Approvals │  │  *linked to     │ │
│  │             │  │ • Scores    │  │   EspoCRM ID    │ │
│  └─────────────┘  └─────────────┘  └─────────────────┘ │
│                                                         │
│  ┌─────────────┐  ┌─────────────────────────────────┐   │
│  │   EVENTS    │  │        APPLICATION DB           │   │
│  │ (HiEvents)  │  │       (Express API)             │   │
│  │             │  │                                 │   │
│  │ • Events    │  │ • Users (auth only)             │   │
│  │ • Tickets   │  │ • Sessions                     │   │
│  │ • Attendees │  │ • Audit logs                   │   │
│  │ • Capacity  │  │ • API keys                     │   │
│  └─────────────┘  │ • Sync queue (offline)         │   │
│                    └─────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

### 5.2 Cross-Domain Linking Strategy

The **`espocrm_contact_id`** is the universal person identifier. Every system that deals with people stores this ID:

| System | Field | Purpose |
|--------|-------|---------|
| Express API `users` table | `espocrm_contact_id` | Links auth account to CRM profile |
| WooCommerce `customer` meta | `_espocrm_id` | Links store customer to CRM profile |
| HiEvents registrant | `external_id` (custom field) | Links event attendee to CRM profile |

**Sync direction:** EspoCRM → outward. EspoCRM is the master. All downstream systems reference the CRM ID. Never allow WooCommerce or HiEvents to create a person record that doesn't exist in EspoCRM.

### 5.3 Monthly Report Data Schema

```sql
-- In the Express API database (not EspoCRM)
-- Reports are composed here, then synced to EspoCRM on approval

CREATE TABLE monthly_reports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    club_id         INT NOT NULL,               -- FK to clubs table
    espocrm_club_id VARCHAR(24),                -- EspoCRM entity ID
    report_month    DATE NOT NULL,              -- First day of the reporting month
    submitted_by    INT NOT NULL,               -- FK to users (leader)
    submitted_at    TIMESTAMP DEFAULT NOW(),

    -- Membership
    registered_youth    INT NOT NULL DEFAULT 0,
    active_youth        INT NOT NULL DEFAULT 0,
    new_members         INT NOT NULL DEFAULT 0,
    adventurer_to_path  INT NOT NULL DEFAULT 0,  -- Transitions

    -- Service
    community_hours     DECIMAL(8,1) NOT NULL DEFAULT 0,
    outreach_events     INT NOT NULL DEFAULT 0,

    -- Education
    honors_completed    INT NOT NULL DEFAULT 0,
    master_guide_active INT NOT NULL DEFAULT 0,
    staff_count         INT NOT NULL DEFAULT 0,

    -- Workflow
    status              ENUM('draft', 'submitted', 'approved', 'revision_requested')
                        DEFAULT 'draft',
    coordinator_id      INT,                    -- FK to users (coordinator)
    reviewed_at         TIMESTAMP NULL,
    coordinator_notes   TEXT,

    -- Sync
    synced_to_crm       BOOLEAN DEFAULT FALSE,
    crm_sync_at         TIMESTAMP NULL,

    UNIQUE KEY uk_club_month (club_id, report_month)
);
```

---

## 6. API Gateway & Integration Layer

### 6.1 Gateway Responsibilities

The Express API Gateway is the **single entry point** for all frontend requests. It handles:

| Responsibility | Implementation |
|----------------|----------------|
| **Authentication** | JWT (access + refresh tokens), stored in `httpOnly` secure cookies |
| **Authorization** | Role-based (Director, Coordinator, Leader, Public). Middleware checks on every route. |
| **Rate Limiting** | Redis-backed sliding window. 100 req/min for authenticated, 30 req/min for public. |
| **Request Routing** | Proxies to WordPress API, EspoCRM API, WooCommerce API, HiEvents API |
| **Data Transformation** | Normalizes responses from different backends into a consistent JSON envelope |
| **Caching** | Redis cache for WordPress content (TTL: 5 min), product catalog (TTL: 15 min) |
| **Audit Logging** | Every write operation logged with user ID, timestamp, IP, and action |

### 6.2 API Route Structure

```
/api/v1/
├── /auth
│   ├── POST   /login
│   ├── POST   /logout
│   ├── POST   /refresh
│   └── POST   /forgot-password
│
├── /content                      # Proxies to WordPress
│   ├── GET    /pages/:slug
│   ├── GET    /posts
│   ├── GET    /announcements     # Alert bar
│   ├── GET    /resources/:category
│   └── GET    /media/:id
│
├── /people                       # Proxies to EspoCRM
│   ├── GET    /clubs
│   ├── GET    /clubs/:id/roster
│   ├── GET    /contacts/:id
│   ├── GET    /certifications/:contactId
│   └── PUT    /contacts/:id      # Limited fields
│
├── /reports
│   ├── POST   /                  # Submit monthly report
│   ├── GET    /:clubId           # Club's report history
│   ├── PUT    /:id/approve       # Coordinator action
│   ├── PUT    /:id/request-revision
│   └── GET    /dashboard         # Conference-wide analytics
│
├── /store                        # Proxies to WooCommerce
│   ├── GET    /products
│   ├── GET    /products/:id
│   ├── POST   /cart
│   ├── POST   /checkout
│   └── GET    /orders/:id
│
├── /events                       # Proxies to HiEvents
│   ├── GET    /upcoming
│   ├── GET    /:id
│   ├── POST   /:id/register
│   └── GET    /:id/capacity
│
└── /calendar
    └── GET    /yearly            # Yearly Mapper
```

### 6.3 Data Flow: Monthly Report Submission

```
Leader (Mobile)                    API Gateway              EspoCRM
     │                                │                        │
     │  POST /api/v1/reports          │                        │
     │  { club_id, month, data }      │                        │
     │ ──────────────────────────────>│                        │
     │                                │                        │
     │                                │  Validate + sanitize   │
     │                                │  INSERT into local DB  │
     │                                │  status = 'submitted'  │
     │                                │                        │
     │                                │  POST /api/v1/...      │
     │                                │──────────────────────> │
     │                                │  Create Activity note  │
     │                                │<────────────────────── │
     │                                │                        │
     │                                │  Notify coordinator    │
     │                                │  (email / push)        │
     │                                │                        │
     │  200 { report_id, status }     │                        │
     │ <──────────────────────────────│                        │
     │                                │                        │

Coordinator (Desktop)              API Gateway              EspoCRM
     │                                │                        │
     │  PUT /api/v1/reports/:id/      │                        │
     │      approve                   │                        │
     │ ──────────────────────────────>│                        │
     │                                │                        │
     │                                │  UPDATE status =       │
     │                                │  'approved'            │
     │                                │                        │
     │                                │  PATCH Contact scores  │
     │                                │──────────────────────> │
     │                                │  Update club metrics   │
     │                                │<────────────────────── │
     │                                │                        │
     │  200 { status: 'approved' }    │                        │
     │ <──────────────────────────────│                        │
```

---

## 7. Security Architecture

### 7.1 Authentication & Authorization

| Layer | Mechanism |
|-------|-----------|
| **User Auth** | JWT with short-lived access tokens (15 min) + long-lived refresh tokens (7 days) in `httpOnly`, `Secure`, `SameSite=Strict` cookies. Refresh tokens are rotated on use (one-time use). |
| **API-to-Backend Auth** | Service-to-service: API keys stored in environment variables, never in code. EspoCRM: HMAC-signed requests. WordPress: Application Passwords (per-service). WooCommerce: OAuth 1.0a consumer keys with read/write scoping. |
| **Role-Based Access** | Four tiers: `public` → `leader` → `coordinator` → `admin`. Each API route declares its minimum role. Middleware enforces. |
| **Session Management** | Redis-backed session store. Sessions invalidated on password change. Admin can force-logout any user. |

### 7.2 Defense in Depth

```
Layer 1: Cloudflare ──── WAF rules, DDoS mitigation, bot management
                         Rate limiting at edge, geo-blocking if needed
                         SSL termination (Full Strict mode)

Layer 2: Caddy ───────── Automatic HTTPS (internal TLS to containers)
                         Security headers (HSTS, CSP, X-Frame-Options)
                         Request size limits (10MB max body)

Layer 3: Express API ─── Input validation (Joi/Zod schemas on every route)
                         SQL injection prevention (parameterized queries only)
                         XSS prevention (DOMPurify on any user HTML)
                         CSRF protection (double-submit cookie pattern)
                         Rate limiting (Redis sliding window)

Layer 4: Databases ───── Encrypted at rest (MariaDB encryption plugin)
                         Network isolation (Docker internal network only)
                         No public port exposure
                         Principle of least privilege (per-service DB users)

Layer 5: Application ─── Content Security Policy (strict, report-uri)
                         Subresource Integrity (SRI) for external scripts
                         No inline scripts/styles in CSP
```

### 7.3 WordPress Hardening (Mandatory)

Since WordPress handles both content and e-commerce (financial data), these are **non-negotiable**:

1. **Disable XML-RPC** (`xmlrpc.php`) — Common brute-force vector. Block at Caddy level.
2. **Disable REST API user enumeration** — `wp-json/wp/v2/users` exposes usernames. Filter it.
3. **Hide `/wp-admin` behind IP allowlist** — Only conference office IPs and VPN. Caddy `handle` block.
4. **Two-Factor Authentication** — Mandatory for all WP admin accounts (use WP 2FA plugin).
5. **Disable file editing** — `define('DISALLOW_FILE_EDIT', true)` in `wp-config.php`.
6. **Disable plugin/theme installation from admin** — `define('DISALLOW_FILE_MODS', true)`. All changes through deployment pipeline only.
7. **Automatic security updates** — Enable for WordPress core and plugins.
8. **Database table prefix** — Change from default `wp_` to a random prefix.
9. **Separate DB user for WooCommerce** — Least-privilege: only access to WooCommerce tables.

### 7.4 Data Privacy & Compliance

| Concern | Mitigation |
|---------|------------|
| **Youth data (minors)** | All personally identifiable information (PII) for minors stored only in EspoCRM. Access restricted to `coordinator` and `admin` roles. Parental consent captured at registration. |
| **Payment data** | NEVER stored on our servers. Stripe/PayPal handles all card data. WooCommerce stores order metadata only. |
| **Data retention** | Monthly reports retained for 7 years (organizational policy). User accounts deactivated (not deleted) when a leader transitions out. |
| **Audit trail** | Every data mutation logged: who, what, when, from where. Logs retained 2 years. |
| **Backups** | Automated daily database backups to encrypted off-site storage. Retention: 30 days rolling + monthly snapshots for 1 year. |

---

## 8. HCI & User Experience Architecture

### 8.1 User Personas & Primary Tasks

| Persona | Device | Primary Task | Time Budget |
|---------|--------|-------------|-------------|
| **Club Director** | Mobile (85%) | Submit monthly report | < 3 minutes |
| **Conference Coordinator** | Desktop (70%) | Review & approve reports, view analytics | 10-15 min/day |
| **Conference Staff** | Desktop | Manage content, events, store | Ongoing |
| **Youth Member / Parent** | Mobile (90%) | View events, register, browse resources | Browsing |
| **New Director** | Mobile/Desktop | Onboarding journey — find manuals, understand structure | First 30 days |

### 8.2 Performance Budgets

| Metric | Target | Enforcement |
|--------|--------|-------------|
| Largest Contentful Paint (LCP) | < 2.5s on 4G | Lighthouse CI in deployment pipeline |
| First Input Delay (FID) | < 100ms | Bundle size limits in Next.js config |
| Cumulative Layout Shift (CLS) | < 0.1 | Image dimension attributes, font `swap` |
| Time to Interactive (TTI) | < 3.5s on 4G | Code splitting, lazy loading, SSR |
| JS bundle (per route) | < 100KB gzipped | `next/bundle-analyzer` in CI |
| Total page weight | < 500KB (above fold) | Image optimization (WebP/AVIF via Next.js Image) |

### 8.3 Mobile-First Report Submission UX

The report interface is the **highest-impact HCI surface** in the entire system. Design principles:

1. **Progressive Disclosure** — Don't show all fields at once. Step-through wizard: Membership → Service → Education → Review & Submit.
2. **Smart Defaults** — Pre-fill last month's membership count. Leader only updates deltas.
3. **Forgiving Input** — Large tap targets (min 48px), number steppers (not free-text for counts), sliders for hours.
4. **Instant Validation** — Inline, real-time. No "submit and see errors" pattern.
5. **Offline Capability** — Reports save to `localStorage` as drafts. When connectivity returns, sync automatically. Visual indicator shows sync status.
6. **Progress Persistence** — If the leader leaves mid-report (phone call, app switch), the draft is preserved exactly where they left off.
7. **Confirmation & Reward** — After submission: clear success state, progress bar toward "Pathfinder of the Year" benchmarks, and a summary they can screenshot for their records.

### 8.4 Accessibility Requirements (WCAG 2.1 AA)

| Requirement | Implementation |
|-------------|----------------|
| Color contrast | Minimum 4.5:1 for text, 3:1 for large text. Validate with axe-core. |
| Keyboard navigation | All interactive elements focusable and operable. Visible focus indicators. |
| Screen reader | Semantic HTML, ARIA labels where needed, live regions for dynamic updates. |
| Motion | Respect `prefers-reduced-motion`. No auto-playing animations. |
| Language | `lang="en"` on `<html>`. Future: `lang="es"` support for Spanish-speaking leaders (significant GNYC demographic). |
| Forms | Every input has a visible `<label>`. Error messages linked with `aria-describedby`. |

### 8.5 Yearly Mapper (Calendar Intelligence)

The "Yearly Mapper" from the proposal is implemented as a **state machine** in the API:

```
┌──────────┐    Congress ends     ┌─────────────┐    Date passes    ┌──────────┐
│ CURRENT  │ ──────────────────> │ TRANSITION  │ ───────────────> │ CURRENT  │
│  YEAR    │                     │   PERIOD    │                  │   YEAR   │
│ (events) │                     │ (both shown)│                  │ (events) │
└──────────┘                     └─────────────┘                  └──────────┘
```

- Events have a `program_year` field (e.g., `2026-2027`).
- A `transition_date` config value marks when the calendar flips.
- During the transition period (configurable, e.g., 2 weeks), both current and upcoming year events are shown.
- No manual intervention needed — the calendar auto-promotes.

---

## 9. Infrastructure & Deployment

### 9.1 Hosting

| Component | Hosting | Rationale |
|-----------|---------|-----------|
| Application stack | **VPS (Hetzner/Linode)** — Dedicated 8GB+ | Full control, proven with GNYC Youth & AUC projects |
| CDN + WAF | **Cloudflare (Free tier)** | DDoS protection, SSL, edge caching, bot management |
| DNS | **Cloudflare** | Integrated with CDN |
| Email (transactional) | **Resend** or **Amazon SES** | Report notifications, password resets, coordinator alerts |
| Backups | **Hetzner Storage Box** or **Backblaze B2** | Encrypted, off-site, automated |

### 9.2 Docker Compose Strategy

Reuse the proven pattern from GNYC Youth Congress and AUC Camporee:

```yaml
# Simplified structure — full compose.yml to be generated in Phase 1
services:
  caddy:
    image: caddy:2-alpine
    ports: ["80:80", "443:443"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
    # ⚠️ Caddyfile changes: docker compose up -d --force-recreate caddy

  app:
    build: ./app
    environment:
      - API_URL=http://api:4000
    depends_on: [api]

  api:
    build: ./api
    environment:
      - DB_HOST=db
      - REDIS_URL=redis://redis:6379
      - WP_API_URL=http://wordpress:8080
      - ESPOCRM_URL=http://espocrm:80
      - ESPOCRM_API_KEY=${ESPOCRM_API_KEY}
      - WC_CONSUMER_KEY=${WC_CONSUMER_KEY}
      - WC_CONSUMER_SECRET=${WC_CONSUMER_SECRET}
    depends_on: [db, redis]

  wordpress:
    image: wordpress:6-php8.2-fpm
    volumes:
      - wp_data:/var/www/html
    environment:
      - WORDPRESS_DB_HOST=db
      - WORDPRESS_DB_NAME=wp_gnyc_aym
    depends_on: [db]

  espocrm:
    image: espocrm/espocrm
    volumes:
      - espo_data:/var/www/html
    environment:
      - ESPOCRM_DATABASE_HOST=espo_db
    depends_on: [espo_db]

  db:
    image: mariadb:11
    volumes:
      - db_data:/var/lib/mysql
    environment:
      - MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}

  espo_db:
    image: mariadb:11
    volumes:
      - espo_db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
```

### 9.3 CI/CD Pipeline

```
Local Dev ──> Git Push ──> GitHub Actions ──> VPS Deploy
                               │
                    ┌──────────┼──────────┐
                    │          │          │
               Lint/Type   Unit Tests   Build
               Check       (Vitest)    Docker
                    │          │       Images
                    └──────────┼──────────┘
                               │
                          All pass?
                               │
                    ┌──────────▼──────────┐
                    │   SSH to VPS        │
                    │   docker compose    │
                    │   pull + up -d      │
                    └─────────────────────┘
```

### 9.4 Backup Strategy

| What | Frequency | Retention | Where |
|------|-----------|-----------|-------|
| MariaDB (all databases) | Every 6 hours | 30 days rolling | Encrypted off-site (B2/Storage Box) |
| WordPress uploads (`wp_uploads`) | Daily | 30 days | Off-site |
| EspoCRM data volume | Daily | 30 days | Off-site |
| Full server snapshot | Weekly | 4 weeks | VPS provider snapshots |
| Database export (SQL dump) | Monthly | 12 months | Off-site archive |

---

## 10. Integration Risk Assessment & Recommendations

### 10.1 Critical Risks

| # | Risk | Severity | Likelihood | Mitigation |
|---|------|----------|------------|------------|
| R1 | **WordPress becomes a security liability** — largest attack surface, handles financial data | HIGH | HIGH | Headless mode, IP-restricted admin, 2FA mandatory, automated patching, WAF rules. Consider separating WooCommerce onto its own WordPress instance if attack surface grows. |
| R2 | **EspoCRM API limitations block real-time workflows** — coordinator notifications, live dashboards | MEDIUM | HIGH | Build a lightweight notification service (Redis pub/sub + SSE/WebSocket). Don't depend on EspoCRM for real-time anything. Use it as a system of record, not a system of engagement. |
| R3 | **Data consistency across 4+ systems** — person records diverge between WP, EspoCRM, WooCommerce, HiEvents | HIGH | HIGH | Enforce single-source-of-truth (EspoCRM). All systems reference `espocrm_contact_id`. Build a nightly reconciliation job that flags orphaned records. |
| R4 | **HiEvents lacks maturity for your scale** — missing features discovered mid-implementation | MEDIUM | MEDIUM | Phase 0 validation checklist (see §3.4). Have fallback plan (build into Next.js/Express). |
| R5 | **WooCommerce + WordPress coupling** — upgrading one breaks the other, plugin conflicts | MEDIUM | MEDIUM | Pin versions, test upgrades in staging before production. Keep plugin count minimal (< 10). |
| R6 | **Operational complexity** — 7+ containers, 4 different backend systems, small team | HIGH | HIGH | Invest in monitoring (Uptime Kuma for health checks, Loki/Grafana for logs). Document runbooks for common failures. |
| R7 | **Offline report submission conflicts** — two leaders from same club submit offline, sync creates duplicates | MEDIUM | LOW | Unique constraint on `(club_id, report_month)`. Last-write-wins with conflict notification to both parties. |

### 10.2 Architectural Recommendations

**Recommendation 1: Consider Strapi or Directus instead of WordPress (Headless CMS)**

> WordPress is proposed for staff familiarity, which is valid. However, running WordPress headless means staff lose the WYSIWYG page builder (Gutenberg only works with WordPress themes). They'll be editing content in the admin panel, but the rendering is entirely controlled by Next.js. This can confuse non-technical editors who expect "what I see is what I get."
>
> **Alternative:** Strapi or Directus are purpose-built headless CMSs. They have cleaner admin UIs for structured content (manuals, resources, announcements), generate APIs automatically, and have a *much smaller attack surface* than WordPress. They also run on Node.js, matching your existing stack — one less runtime (no PHP needed).
>
> **Trade-off:** Staff would need to learn a new admin UI. But since WordPress headless already breaks their mental model (no themes, no page builder), the learning curve may be comparable.
>
> **Verdict:** If staff have never used WordPress before, use Strapi/Directus. If they actively use WordPress today and that familiarity is critical, keep WordPress but accept the security overhead.

**Recommendation 2: Build the Store In-House Instead of WooCommerce**

> WooCommerce is powerful, but it's overkill for a store selling 20-50 SKUs (uniforms, curriculum). Running WooCommerce means running WordPress (PHP + MySQL) even if you use Strapi for content. That's two CMS backends.
>
> **Alternative:** Build a lightweight store directly in the Express API + Next.js frontend. Use Stripe Checkout for payments (PCI compliance handled by Stripe). Product catalog in MariaDB. Order management in a simple admin page.
>
> **Advantages:** One less system to maintain, no PHP stack, no plugin update anxiety, full control over the checkout UX (critical for HCI goals), and Stripe Checkout is already PCI DSS Level 1 certified.
>
> **Trade-off:** No pre-built inventory management, shipping calculator, or discount engine. You'd build these as needed.
>
> **Verdict:** For your scale (< 50 products, single "shipping" method likely pickup), building in-house is the simpler, more secure path.

**Recommendation 3: Build Event Registration In-House**

> If HiEvents doesn't meet requirements after Phase 0 evaluation, don't search for another third-party tool. You have a working registration system in the GNYC Youth Congress project. Adapt it.
>
> This gives you: full control over custom fields, direct EspoCRM integration, consistent UX, and one less external dependency.

**Recommendation 4: Add a Notification Service Early**

> Multiple features need notifications: report submitted (notify coordinator), report approved (notify leader), event registration (confirmation), store order (confirmation), calendar reminders.
>
> Build a centralized notification service in the Express API from Phase 1:
> ```
> /api/v1/notifications
> ├── Email (via Resend/SES)
> ├── In-app (via Redis pub/sub + SSE)
> └── Future: Push notifications (via web-push for PWA)
> ```
> This prevents each feature from implementing its own notification logic.

**Recommendation 5: Internationalization (i18n) from Day One**

> GNYC serves a significant Spanish-speaking population. Don't bolt on i18n later — it's 10x harder to retrofit. Use `next-intl` or `next-i18next` from the start. Even if you only launch in English, the infrastructure is ready for Spanish.

**Recommendation 6: Implement a Health Dashboard**

> With 7+ containers and 4 backend systems, you need visibility. Deploy **Uptime Kuma** (lightweight, self-hosted) to monitor all service health endpoints. Set up alerts for: container down, API response time > 2s, database connection failures, SSL certificate expiration, disk usage > 80%.

---

## 11. Phase Roadmap

### Phase 0: Pre-Flight (Now — May 2026)

*Low-bandwidth planning while Camporee is primary focus.*

| Task | Owner | Deliverable |
|------|-------|-------------|
| Evaluate HiEvents against requirements checklist | Dev | Go/No-Go decision |
| Finalize CMS choice (WordPress vs. Strapi) | Dir + Dev | ADR document |
| Finalize store approach (WooCommerce vs. in-house) | Dir + Dev | ADR document |
| Define EspoCRM entity schema (Clubs, Contacts, Reports) | Dir + Dev | Entity diagram |
| Design monthly report form fields | Director | Wireframe / field list |
| Define "New Director" onboarding content structure | Director | Content outline |
| Provision VPS and configure base infrastructure | Dev | Running Docker Compose with Caddy + MariaDB + Redis |

### Phase 1: Foundation (June — August 2026)

*Core infrastructure and MVP features.*

| Task | Target |
|------|--------|
| Deploy Next.js frontend with public pages | End of June |
| Deploy Express API Gateway with auth (JWT + roles) | End of June |
| Deploy EspoCRM, configure entities, import existing club data | July |
| Build monthly report submission + coordinator approval flow | July |
| Deploy CMS (WordPress or Strapi), migrate existing content | August |
| Build Educational Hub (resources, manuals, videos) | August |
| Build alert bar / announcement system | August |
| Deploy Uptime Kuma monitoring | June (day 1) |
| Set up automated backups | June (day 1) |
| Set up CI/CD pipeline (GitHub Actions) | June |

### Phase 2: Engagement (September — November 2026)

*Store, events, and analytics.*

| Task | Target |
|------|--------|
| Build Youth Store (Stripe Checkout integration) | September |
| Build event registration (HiEvents or in-house) | September |
| Implement Yearly Mapper (calendar auto-promotion) | October |
| GA4 integration (client + server-side events) | October |
| Build conference-wide analytics dashboard | November |
| Performance audit + optimization | November |

### Phase 3: Intelligence (December 2026 — February 2027)

*Advanced features and polish.*

| Task | Target |
|------|--------|
| Club ranking / scoring system (based on monthly reports) | December |
| "New Director" onboarding wizard | January |
| Spanish language support (i18n) | January |
| PWA enhancements (offline support, push notifications) | February |
| Security audit (penetration testing) | February |

### Phase 4: Scale (March 2027+)

*Future capabilities — designed for but not built yet.*

- Native mobile app (React Native, consuming same API)
- Advanced analytics / BI dashboard
- Automated compliance reporting to Atlantic Union
- Inter-conference data sharing (federation)
- AI-powered resource recommendations

---

## Appendix: Architecture Decision Records

### ADR-001: API Gateway Pattern vs. Direct Backend Access

**Decision:** All frontend requests route through a custom Express API Gateway.
**Rationale:** Prevents frontend from needing credentials for 4 different backends. Enables consistent auth, caching, rate limiting, and audit logging in one place. Adds latency (~10-20ms per hop) but the security and maintainability benefits far outweigh it.
**Status:** Accepted.

### ADR-002: EspoCRM as Single Source of Truth for People Data

**Decision:** EspoCRM owns all person/contact records. Other systems reference `espocrm_contact_id`.
**Rationale:** Prevents the inevitable data divergence that occurs when multiple systems independently manage people records. With 4+ systems, this is the most common integration failure mode.
**Status:** Accepted.

### ADR-003: Separate Database Instances for EspoCRM

**Decision:** EspoCRM gets its own MariaDB instance, not shared with the application database.
**Rationale:** EspoCRM manages its own schema and migrations. Sharing a MariaDB instance risks schema conflicts during upgrades. Operational isolation means EspoCRM can be upgraded independently.
**Status:** Accepted.

### ADR-004: CMS Selection (Pending Phase 0)

**Decision:** TBD — WordPress (Headless) vs. Strapi vs. Directus.
**Rationale:** See Recommendation 1 in §10.2. Final decision depends on staff's current WordPress experience and willingness to adopt a new CMS.
**Status:** Pending.

### ADR-005: E-Commerce Approach (Pending Phase 0)

**Decision:** TBD — WooCommerce (Headless) vs. In-house (Express + Stripe Checkout).
**Rationale:** See Recommendation 2 in §10.2. For < 50 SKUs with simple fulfillment, in-house is likely the better choice.
**Status:** Pending.

### ADR-006: Event Registration Platform (Pending Phase 0)

**Decision:** TBD — HiEvents vs. In-house (adapted from GNYC Youth Congress).
**Rationale:** See §3.4 and Recommendation 3 in §10.2. Depends on HiEvents feature evaluation during Phase 0.
**Status:** Pending.

---

*This document is a living specification. All pending ADRs should be resolved before Phase 1 begins in June 2026. Each decision should be recorded with its rationale so future teams understand not just what was chosen, but why.*
