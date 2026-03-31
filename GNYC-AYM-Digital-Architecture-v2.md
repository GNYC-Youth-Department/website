# GNYC Adventist Youth Ministries — Digital Ecosystem Architecture

**Version:** 2.4
**Date:** March 18, 2026
**Status:** Draft — For Review
**Audience:** Director of AYM, Development Team, Stakeholders
**Previous Version:** [v1 — Headless Architecture](./GNYC-AYM-Digital-Architecture-v1.md)

---
## Changelog from v2.3

| Change | Rationale |
|--------|-----------|
| Expanded role matrix to 11 tiers	 | Added `Admin Assit.`, `Registrar`, `Instructor`, `Store Manager` and `Store Staff`  |

## Changelog from v2.2

| Change | Rationale |
|--------|-----------|
| Expanded role matrix to 6 tiers	 | Added `area coordinator` and renamed `coordinator` to `lead coordinator` |

## Changelog from v1

| Change | Rationale |
|--------|-----------|
| Removed headless WordPress requirement | Existing Elementor site is retained. No rework, no retraining for staff. |
| WordPress + Elementor is the rendering layer | Staff keep full visual control over public pages. |
| Replaced Next.js frontend with embedded React apps | Leader portal and chat widget are injected into WordPress pages. |
| Added MCP Server as the integration backbone | Standardized, model-agnostic tool layer. Plug any AI client in. |
| Added Gen AI Assistant (Claude) | Conversational AI with tool use — not just a FAQ bot. |
| Added WPGraphQL as internal API | Backend systems query WordPress content efficiently. Public site is unaffected. |
| Simplified infrastructure | Fewer containers, no separate frontend service. |
| Removed Strapi/Directus/Payload recommendations | Not needed — WordPress stays as the CMS with Elementor. |
| Replaced Caddy with Nginx | Team familiarity, widely documented, Cloudflare handles TLS so Caddy's auto-cert advantage is moot. |
| Added Staff Portal with role-based access | Director, Staff, and Parent/Guardian portals with distinct permissions. |
| Added Children & Guardian data model | New EspoCRM entities for tracking youth enrolled in clubs and their parent/guardian contacts. |
| Added Forms Engine | Conference-defined form templates (medical, consent, permission), parent submission, director status tracking. |
| Expanded role matrix to 5 tiers | Added `parent` and `staff` roles alongside existing `admin`, `coordinator`, `leader` (director). |

---

## Table of Contents

1. [Design Principles](#1-design-principles)
2. [Architecture Overview](#2-architecture-overview)
3. [Component Stack](#3-component-stack)
4. [System Architecture Diagram](#4-system-architecture-diagram)
5. [MCP Server — Integration Layer](#5-mcp-server--integration-layer)
6. [Gen AI Assistant](#6-gen-ai-assistant)
7. [Data Model & Domain Boundaries](#7-data-model--domain-boundaries)
8. [API Gateway](#8-api-gateway)
9. [Security Architecture](#9-security-architecture)
10. [HCI & User Experience Architecture](#10-hci--user-experience-architecture)
11. [Infrastructure & Deployment](#11-infrastructure--deployment)
12. [Integration Risk Assessment & Recommendations](#12-integration-risk-assessment--recommendations)
13. [Phase Roadmap](#13-phase-roadmap)
14. [Appendix: Architecture Decision Records](#appendix-architecture-decision-records)

---

## 1. Design Principles

These principles govern every technical decision in this document. They are ordered by priority.

| # | Principle | Implication |
|---|-----------|-------------|
| 1 | **Security by Default** | Zero-trust networking, encrypted at rest and in transit, least-privilege access, OWASP Top 10 mitigations baked in — not bolted on. |
| 2 | **Architecture First** | Every component communicates through well-defined contracts (APIs). No direct database sharing between services. |
| 3 | **HCI-Centered** | Every interface is designed for the *actual user* (volunteer club directors on mobile, conference staff on desktop). Performance budgets, accessibility (WCAG 2.1 AA), and progressive disclosure are non-negotiable. |
| 4 | **Mission Continuity** | The system must survive leadership transitions. All institutional knowledge lives in structured data, not in people's heads. |
| 5 | **Future-Ready, Not Future-Bloated** | Design extension points now; build features only when needed. The architecture supports a mobile app and headless migration in the future, but we don't build either yet. |
| 6 | **Operational Simplicity** | A small team must maintain this. Fewer moving parts win. Keep WordPress as the rendering engine. Build custom only where no plugin exists. |
| 7 | **Zero Additional Licensing Cost** | All components are open-source or free-tier. The only recurring cost is AI API usage (~$15-25/month). |

---

## 2. Architecture Overview

This architecture **retains WordPress + Elementor** as the public-facing website and content management system. Custom capabilities (CRM, reporting, AI assistant) are built as **companion services** that run alongside WordPress, not instead of it.

### Core Architectural Decisions

- **WordPress + Elementor remains the website.** Staff keep full visual control. No retraining.
- **WooCommerce (WordPress plugin) powers the Youth Store.** Already part of the WordPress ecosystem.
- **WPGraphQL is installed as a plugin.** It adds a `/graphql` endpoint for backend systems to query content efficiently. It does NOT change how the public site renders.
- **EspoCRM is the single source of truth for people data.** Self-hosted, open-source.
- **Express API (Node.js) is the custom backend.** Handles authentication, monthly reports, and serves as the bridge between all systems.
- **MCP Server wraps all integrations.** Any AI client (chatbot, dashboard, Claude Desktop, future mobile app) can connect to the same tool layer.
- **Gen AI Assistant (Claude) provides conversational intelligence.** Not a FAQ bot — it reasons, generates, coaches, and translates.
- **Embedded React apps** are injected into WordPress pages for the leader portal and chat widget. WordPress renders the page shell; React handles the interactive parts.

```
┌──────────────────────────────────────────────────────────────┐
│                    WHAT USERS SEE                             │
│                                                              │
│  Public Visitor          Club Director         Coordinator   │
│  sees Elementor          sees Portal           sees Dashboard│
│  pages + chat            (React in WP)         + AI insights │
│       │                       │                      │       │
└───────┼───────────────────────┼──────────────────────┼───────┘
        │                       │                      │
        ▼                       ▼                      ▼
┌──────────────────────────────────────────────────────────────┐
│              WordPress + Elementor + WooCommerce             │
│              (renders all pages — unchanged)                  │
│                                                              │
│  Plugins:  WPGraphQL | ACF | WooCommerce | WP 2FA           │
│  Embedded: Leader Portal (React) | Chat Widget (React)       │
└──────────────────────────┬───────────────────────────────────┘
                           │
                    ┌──────▼──────┐
                    │ Express API │ Auth, Reports, Chat endpoint
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ MCP Server  │ Standardized tool layer
                    └──┬───┬───┬──┘
                       │   │   │
              ┌────────┘   │   └────────┐
              ▼            ▼            ▼
         WPGraphQL     EspoCRM    WooCommerce
         (content)    (people)      (store)
```

---

## 3. Component Stack

### 3.1 WordPress + Elementor (Existing — Retained)

| Aspect | Detail |
|--------|--------|
| **Role** | Public website, content management, page design |
| **Mode** | Traditional WordPress — Elementor renders all public pages |
| **Staff workflow** | Unchanged. Drag-and-drop in Elementor, publish, done. |
| **What changes** | Three plugins added: WPGraphQL, ACF, WP 2FA. No visual impact. |

### 3.2 WPGraphQL (New Plugin — Internal Use Only)

| Aspect | Detail |
|--------|--------|
| **Role** | Provides a clean, efficient API for backend systems to query WordPress content |
| **Who uses it** | MCP Server and Express API only. Never the browser. Never the public. |
| **Why not REST API** | GraphQL returns only requested fields. A resource query drops from ~15KB (REST) to ~3KB (GraphQL). Faster server-to-server communication. |
| **Public impact** | None. Visitors see Elementor pages. GraphQL runs behind the scenes. |

Example — MCP Server querying resources:

```graphql
query GetResources($category: String!) {
  resources(where: { categoryName: $category }) {
    nodes {
      title
      slug
      content
      resourceFields {          # ACF custom fields
        fileDownload { mediaItemUrl }
        targetAudience
        summary
      }
      featuredImage {
        node { sourceUrl altText }
      }
    }
  }
}
```

### 3.3 ACF — Advanced Custom Fields (New Plugin)

| Aspect | Detail |
|--------|--------|
| **Role** | Adds structured data fields to WordPress content types |
| **Why** | Elementor handles layout. ACF handles *data* — the fields that the AI assistant and MCP server need to query reliably (PDF links, audience tags, certification requirements, etc.) |
| **Staff workflow** | When editing a resource page, staff see additional fields below the Elementor editor: upload PDF, select audience, add summary. These fields are what the backend systems consume. |

### 3.4 WooCommerce (Existing Plugin — Youth Store)

| Aspect | Detail |
|--------|--------|
| **Role** | Youth Store — uniforms, curriculum materials, event merchandise |
| **Mode** | Standard WooCommerce — runs inside WordPress with Elementor WooCommerce widgets |
| **Payment** | Stripe or PayPal gateway (card data never touches our server) |
| **MCP integration** | The MCP server queries the WooCommerce REST API so the AI assistant can answer questions about products, prices, and availability |

### 3.5 EspoCRM (New — Self-Hosted)

| Aspect | Detail |
|--------|--------|
| **Role** | People data: club rosters, leader certifications (Master Guide tracking), membership transitions (Adventurer → Pathfinder → AY Leader), monthly report ledger, coordinator approvals |
| **Mode** | Self-hosted, open-source. Accessed exclusively through its REST API via the MCP server. |
| **Why EspoCRM** | Free, highly customizable entities, built-in workflow engine for approval chains, PHP-based (low resource usage) |
| **API** | EspoCRM REST API with API key + HMAC authentication |

**Critical rule:** EspoCRM is the **single source of truth for people data.** All person records originate in or sync back to EspoCRM. WordPress user accounts and WooCommerce customers link to EspoCRM via `espocrm_contact_id`.

### 3.6 Express API (New — Custom Backend)

| Aspect | Detail |
|--------|--------|
| **Role** | Authentication (JWT), monthly report CRUD, chat endpoint, audit logging |
| **Stack** | Node.js / Express — same stack proven in GNYC Youth Congress and AUC Camporee |
| **Database** | MariaDB 11 (reports, users, sessions, audit logs) |
| **Cache/Sessions** | Redis 7 |

### 3.7 MCP Server (New — Integration Layer)

| Aspect | Detail |
|--------|--------|
| **Role** | Standardized tool layer that wraps all backend systems |
| **Protocol** | Model Context Protocol (MCP) — open standard |
| **Clients** | AI chatbot, future dashboard AI, Claude Desktop, future mobile app |
| **Cost** | $0 — open-source SDK, runs as a Node.js process alongside Express API |

See [Section 5](#5-mcp-server--integration-layer) for full specification.

### 3.8 Gen AI Assistant (New — Claude)

| Aspect | Detail |
|--------|--------|
| **Role** | Conversational AI — answers questions, generates content, coaches directors, analyzes reports, translates to Spanish |
| **Model** | Claude Sonnet (primary) / Claude Haiku (simple queries — cost optimization) |
| **Interface** | React chat widget embedded globally in WordPress (bottom-right corner) |
| **Cost** | ~$15-25/month at expected usage (500 conversations/month) |

See [Section 6](#6-gen-ai-assistant) for full specification.

### 3.9 Analytics — GA4

| Aspect | Detail |
|--------|--------|
| **Role** | Engagement tracking, resource usage, event funnel analysis |
| **Mode** | Client-side tracking script + server-side events via Measurement Protocol for critical conversions (report submitted, store purchase, event registration) |

---

## 4. System Architecture Diagram

```
                            ┌─────────────────────┐
                            │   Cloudflare CDN     │
                            │   WAF + DDoS + SSL   │
                            │   (Free Tier)        │
                            └──────────┬──────────┘
                                       │
                            ┌──────────▼──────────┐
                            │       Nginx          │
                            │   (Reverse Proxy)    │
                            │   Headers, Routing   │
                            └──────────┬──────────┘
                                       │
                         ┌─────────────┼─────────────┐
                         │             │             │
                ┌────────▼──────┐  ┌───▼──────┐  ┌───▼────────┐
                │   WordPress   │  │ Express  │  │  EspoCRM   │
                │ + Elementor   │  │   API    │  │            │
                │ + WooCommerce │  │          │  │  Port 8081 │
                │ + WPGraphQL   │  │ Port 4000│  └───┬────────┘
                │ + ACF         │  │          │      │
                │               │  └───┬──────┘  ┌───▼────────┐
                │  Port 8080    │      │         │ MariaDB    │
                └───────┬───────┘      │         │ (EspoCRM)  │
                        │              │         │  :3307     │
                        │         ┌────▼─────┐   └────────────┘
                        │         │MCP Server│
                        │         │(Node.js) │
                        │         └──┬──┬──┬─┘
                        │            │  │  │
                        └────────────┘  │  │
                           WPGraphQL    │  │
                                        │  │
                              EspoCRM───┘  └───WooCommerce
                              REST API         REST API

                ┌────────────┐   ┌─────────┐
                │ MariaDB 11 │   │ Redis 7 │
                │ (App DB)   │   │ Sessions│
                │   :3306    │   │ Cache   │
                └────────────┘   │  :6379  │
                                 └─────────┘
```

### Container Inventory (Docker Compose)

| Service | Image | Port | Persistent Volume |
|---------|-------|------|-------------------|
| `wordpress` | wordpress:6-php8.2-fpm + nginx | 8080 | `wp_data`, `wp_uploads` |
| `api` | Node.js/Express (custom) — includes MCP server | 4000 | — |
| `espocrm` | espocrm/espocrm | 8081 | `espo_data` |
| `db` | mariadb:11 | 3306 | `db_data` |
| `espo_db` | mariadb:11 | 3307 | `espo_db_data` |
| `redis` | redis:7-alpine | 6379 | `redis_data` |
| `nginx` | nginx:1.27-alpine | 80, 443 | `nginx_conf` |

**7 containers total.** v1 had 9. The MCP server runs inside the `api` container as a co-process — no separate container needed.

---

## 5. MCP Server — Integration Layer

### 5.1 Why MCP

The MCP (Model Context Protocol) server is the **central integration layer** between all backend systems and any AI-powered client. Instead of hardcoding tool integrations into the chatbot, the MCP server exposes standardized tools that any client can use.

| Without MCP | With MCP |
|---|---|
| Tools locked to one chat endpoint | Any client connects to the same tools |
| Swap AI model → rewrite tools | Swap AI model → change nothing |
| Add chatbot to mobile app → rebuild | Point new client at MCP server |
| Test tools → need the full chatbot running | Use MCP Inspector to test independently |
| Claude Desktop can't access your data | Add MCP server URL → full access |

### 5.2 Tool Definitions

```
MCP Server: gnyc-aym
Version: 1.0.0
Transport: stdio (co-process with Express API)

TOOLS:
──────────────────────────────────────────────────────────

wordpress://search_content
  Description: Search GNYC Youth Ministry pages, resources,
               manuals, and announcements
  Parameters:  query (string), category (string?), limit (int?)
  Source:      WPGraphQL → /graphql
  Auth:        WP Application Password (server-to-server)

wordpress://get_page
  Description: Get a specific page by slug with full content
  Parameters:  slug (string)
  Source:      WPGraphQL → /graphql

wordpress://get_announcements
  Description: Get current alert bar announcements
  Parameters:  none
  Source:      WPGraphQL → /graphql (custom post type)

wordpress://get_resources
  Description: Get educational resources filtered by type
  Parameters:  type (enum: manual, honor, video, guide),
               audience (enum: pathfinder, adventurer, ay, master_guide)
  Source:      WPGraphQL → /graphql + ACF fields

──────────────────────────────────────────────────────────

espocrm://search_contacts
  Description: Search people by name, church, club, or role
  Parameters:  query (string), role (string?), club_id (string?)
  Source:      EspoCRM REST API
  Auth:        HMAC-signed API key
  Guardrail:   Requires authenticated user. Results scoped
               to user's role (leaders see own club only).

espocrm://get_club
  Description: Get club details including roster count and
               coordinator assignment
  Parameters:  club_id (string)
  Source:      EspoCRM REST API

espocrm://get_reports
  Description: Get monthly reports for a club or across all clubs
  Parameters:  club_id (string?), date_range (string?),
               status (enum: draft, submitted, approved, revision_requested)
  Source:      Express API DB (reports table)

espocrm://get_certifications
  Description: Get certification progress for a contact
               (Master Guide, honors, etc.)
  Parameters:  contact_id (string)
  Source:      EspoCRM REST API

espocrm://get_coordinator
  Description: Get the area coordinator for a given club or church
  Parameters:  club_id (string) OR church (string)
  Source:      EspoCRM REST API

──────────────────────────────────────────────────────────

woocommerce://search_products
  Description: Search Youth Store products by keyword or category
  Parameters:  query (string?), category (string?),
               in_stock_only (boolean?)
  Source:      WooCommerce REST API v3
  Auth:        OAuth 1.0a consumer key/secret

woocommerce://get_product
  Description: Get product details including price, description,
               and stock status
  Parameters:  product_id (int)
  Source:      WooCommerce REST API v3

──────────────────────────────────────────────────────────

children://get_roster
  Description: Get children enrolled in a specific club
  Parameters:  club_id (string), status (enum: active, inactive, all)
  Source:      EspoCRM REST API → Child entity
  Auth:        Requires director, staff, or coordinator role.
               Scoped to user's club/area.
  Guardrail:   Never returns guardian contact info to AI.
               Only names, ages, and form completion status.

children://get_form_status
  Description: Get form completion status for children in a club
  Parameters:  club_id (string), form_template (string?)
  Source:      Express API DB (form_submissions + form_templates)
  Auth:        Requires director or staff role.

guardians://get_for_child
  Description: Get parent/guardian info for a specific child
  Parameters:  child_id (string)
  Source:      EspoCRM REST API → Parent/Guardian entity
  Auth:        Requires director or coordinator role.
  Guardrail:   Returns name and relationship only.
               Phone/email NOT exposed via AI.

──────────────────────────────────────────────────────────

events://get_upcoming
  Description: Get upcoming events with registration links
  Parameters:  type (enum: pathfinder, adventurer, ay, master_guide, all),
               months_ahead (int, default: 3)
  Source:      WordPress (Events Manager plugin or CPT) via WPGraphQL

events://get_event
  Description: Get full event details including capacity and
               registration status
  Parameters:  event_id (string)
  Source:      WordPress via WPGraphQL

──────────────────────────────────────────────────────────

calendar://get_yearly
  Description: Get the program year calendar with auto-promotion
               logic (Yearly Mapper)
  Parameters:  program_year (string, e.g. "2026-2027")
  Source:      WordPress CPT via WPGraphQL
  Logic:       Automatically includes next-year events during
               transition period
```

### 5.3 Implementation

The MCP server runs as a co-process inside the Express API container using the official MCP SDK:

```js
// mcp/server.js
import { McpServer } from "@modelcontextprotocol/sdk/server/index.js";
import { z } from "zod";

const server = new McpServer({
  name: "gnyc-aym",
  version: "1.0.0",
});

// WordPress content tool (via WPGraphQL)
server.tool(
  "search_content",
  "Search GNYC Youth Ministry resources, manuals, and announcements",
  {
    query: z.string().describe("Search query"),
    category: z.string().optional().describe("Content category filter"),
    limit: z.number().default(10).describe("Max results"),
  },
  async ({ query, category, limit }) => {
    const graphqlQuery = `
      query SearchContent($search: String!, $category: String, $first: Int) {
        posts(where: { search: $search, categoryName: $category }, first: $first) {
          nodes {
            title
            slug
            excerpt
            date
            categories { nodes { name } }
            resourceFields { targetAudience summary }
          }
        }
      }
    `;
    const result = await wpGraphQL(graphqlQuery, { search: query, category, first: limit });
    return {
      content: [{ type: "text", text: JSON.stringify(result.data.posts.nodes, null, 2) }],
    };
  }
);

// EspoCRM club tool
server.tool(
  "get_club",
  "Get club details including roster count and coordinator assignment",
  { club_id: z.string().describe("EspoCRM club entity ID") },
  async ({ club_id }) => {
    const club = await espoCRM(`/Club/${club_id}`);
    return {
      content: [{ type: "text", text: JSON.stringify(club, null, 2) }],
    };
  }
);

// ... additional tools follow same pattern
```

---

## 6. Gen AI Assistant

### 6.1 Overview

The AI assistant is a **conversational agent** powered by Claude. It connects to the MCP server to access all GNYC systems. It doesn't just retrieve — it **reasons, generates, coaches, analyzes, and translates.**

### 6.2 Capabilities

| Capability | Example |
|---|---|
| **Answer questions** | "When is the next Pathfinder camporee?" → pulls from events |
| **Coach new directors** | "I just got appointed, where do I start?" → step-by-step onboarding |
| **Generate content** | "Draft a parent letter about the upcoming campout" → writes it using event details |
| **Analyze reports** | "How are my clubs doing this quarter?" → summarizes trends, flags concerns |
| **Translate** | "Say that in Spanish" → instant translation |
| **Find resources** | "What honors can my club work on this quarter?" → searches educational hub |
| **Guide report submission** | "Help me fill out my monthly report" → walks through step by step |
| **Look up people** | "Who is my area coordinator?" → queries EspoCRM (auth-scoped) |
| **Check store** | "Do you have Pathfinder neckerchiefs in stock?" → queries WooCommerce |

### 6.3 Architecture

```
┌──────────────────────────────────────────────────────────┐
│  Chat Widget (React component — embedded in WordPress)    │
│                                                           │
│  ┌──────────────────────────────────────────────────┐     │
│  │  💬 GNYC Youth Assistant                    ─ ✕  │     │
│  │  ─────────────────────────────────────────────── │     │
│  │                                                  │     │
│  │  🤖 Hi! I'm the GNYC Youth Ministry assistant.  │     │
│  │     I can help with resources, events, reports,  │     │
│  │     and anything related to our ministries.      │     │
│  │                                                  │     │
│  │  👤 When is the next Master Guide weekend?       │     │
│  │                                                  │     │
│  │  🤖 The next Master Guide Intensive Weekend is   │     │
│  │     April 12-13, 2026 at Camp Berkshire.         │     │
│  │     Registration is open until April 5.          │     │
│  │     [Register here →]  [View details →]          │     │
│  │                                                  │     │
│  │  ┌──────────────────────────────────┐  [Send]   │     │
│  │  │ Type your question...            │           │     │
│  │  └──────────────────────────────────┘           │     │
│  └──────────────────────────────────────────────────┘     │
└──────────────────────────────────────────────────────────┘
```

### 6.4 Request Flow

```
Chat Widget (browser)
    │
    │  POST /api/v1/chat
    │  { message, conversation_id, auth_token? }
    │
    ▼
Express API (/api/v1/chat)
    │
    │  1. Validate input + rate limit
    │  2. Load conversation history from Redis
    │  3. Determine user role (public / leader / coordinator / admin)
    │
    ▼
Claude API (messages endpoint with tool_use)
    │
    │  System prompt:
    │  "You are the GNYC Youth Ministry assistant..."
    │
    │  Tools: (defined by MCP server)
    │  - search_content, get_resources, get_announcements
    │  - search_contacts, get_club, get_reports (if authenticated)
    │  - search_products, get_upcoming, get_event
    │
    │  Claude decides which tools to call based on the question.
    │
    ▼
MCP Server
    │
    │  Executes tool calls against WordPress, EspoCRM, WooCommerce
    │  Returns results to Claude
    │
    ▼
Claude generates response grounded in real data
    │
    ▼
Express API
    │
    │  Save conversation turn to Redis
    │  Return response to chat widget
    │
    ▼
Chat Widget renders response with citations and action links
```

### 6.5 System Prompt (Core)

```
You are the GNYC Youth Ministry Assistant — a helpful, knowledgeable
guide for the Greater New York Conference Adventist Youth Ministries.

ROLE:
- You help Pathfinder and Adventurer directors, AY leaders, Master Guide
  candidates, coordinators, youth, and parents.
- You answer questions about events, resources, certifications, reports,
  the Youth Store, and ministry operations.
- You can generate content (letters, announcements, plans) using real
  data from GNYC systems.
- You can translate any response to Spanish when asked.

RULES:
1. ONLY answer based on data from your tools. If you don't have the
   information, say so and suggest who to contact.
2. NEVER invent event dates, prices, or policies. Always use tool results.
3. For theological or doctrinal questions, point to official Adventist
   resources — do not generate theological opinions.
4. Include source links when referencing specific pages or resources.
5. If the user is authenticated, you may access their club data. If not,
   only return public information.
6. If a question is sensitive or you're unsure, offer to connect them
   with their area coordinator.
7. Be warm, encouraging, and professional. Reflect the ministry's values.
8. Keep responses concise. Use bullet points for lists.

CONTEXT:
- User role: {role}  (public | leader | coordinator | admin)
- User club: {club_name} (if authenticated)
- Current date: {date}
- Program year: {program_year}
```

### 6.6 Guardrails

| Guardrail | Implementation |
|---|---|
| **Scope restriction** | System prompt limits responses to GNYC AYM topics. Off-topic questions get a polite redirect. |
| **No theological opinions** | Explicitly instructed in system prompt. Tested during QA. |
| **Source citation** | Every factual response includes a link to the source page or document. |
| **Auth-scoped data** | MCP tools check user role before returning people data. Public users see public content only. Leaders see their own club. Coordinators see their area. |
| **No PII exposure** | Contact details (phone, email, address) are never returned in chat. Only names, roles, and club affiliations. |
| **Fallback to human** | If the question can't be answered or is sensitive, the assistant offers: "Would you like me to connect you with your area coordinator?" |
| **Rate limiting** | 20 messages per hour per user (public), 60 per hour (authenticated). Prevents abuse and cost overruns. |
| **Cost cap** | Monthly Claude API spend capped at $50. If reached, chatbot gracefully degrades to a "contact us" message. |

### 6.7 Cost Model

| Component | Provider | Monthly Cost |
|---|---|---|
| AI model (chat) | Claude Sonnet / Haiku | ~$15-25 |
| Embeddings | Not needed (tool-use pattern, not RAG) | $0 |
| Vector database | Not needed | $0 |
| MCP Server | Self-hosted (Node.js) | $0 |
| Chat widget | Custom React component | $0 |
| **Total** | | **~$15-25/month** |

Note: The tool-use pattern (MCP) eliminates the need for a vector database and embedding model. Claude calls tools on demand rather than searching pre-embedded content. This is simpler, cheaper, and always returns fresh data.

---

## 7. Data Model & Domain Boundaries

### 7.1 Domain Ownership Map

```
┌──────────────────────────────────────────────────────────────────┐
│                         DATA DOMAINS                              │
│                                                                   │
│  ┌─────────────┐  ┌──────────────────┐  ┌─────────────────┐      │
│  │   CONTENT   │  │     PEOPLE       │  │    COMMERCE     │      │
│  │ (WordPress) │  │    (EspoCRM)     │  │  (WooCommerce)  │      │
│  │             │  │                  │  │                 │      │
│  │ • Pages     │  │ • Contacts       │  │ • Products      │      │
│  │ • Posts     │  │   (Directors,    │  │ • Orders        │      │
│  │ • Media     │  │    Staff,        │  │ • Customers*    │      │
│  │ • Manuals   │  │    Coordinators) │  │ • Inventory     │      │
│  │ • Alert Bar │  │ • Children       │  │                 │      │
│  │ • Videos    │  │ • Parents/Guard. │  │  *linked to     │      │
│  │ • Events    │  │ • Clubs          │  │   EspoCRM ID    │      │
│  │             │  │ • Churches       │  └─────────────────┘      │
│  └─────────────┘  │ • Certifications │                           │
│                    │ • Scores         │                           │
│                    └──────────────────┘                           │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │              APPLICATION DB (Express API)                    │ │
│  │                                                              │ │
│  │ • Users (auth — profile lives in EspoCRM)                   │ │
│  │ • Sessions (Redis-backed)                                   │ │
│  │ • Monthly reports (composed here, synced to CRM on approval)│ │
│  │ • Form templates (conference-defined)                       │ │
│  │ • Form submissions (parent-submitted, per child)            │ │
│  │ • Audit logs                                                │ │
│  │ • Chat conversations (Redis, TTL 24h)                       │ │
│  └──────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
```

### 7.2 Cross-Domain Linking Strategy

The **`espocrm_contact_id`** is the universal person identifier:

| System | Field | Purpose |
|--------|-------|---------|
| Express API `users` table | `espocrm_contact_id` | Links auth account to CRM profile |
| WooCommerce `customer` meta | `_espocrm_id` | Links store customer to CRM profile |

**Sync direction:** EspoCRM → outward. EspoCRM is the master. All downstream systems reference the CRM ID. Never allow WooCommerce to create a person record that doesn't exist in EspoCRM.

### 7.3 Monthly Report Data Schema

```sql
-- In the Express API database (not EspoCRM)
-- Reports are composed here, then synced to EspoCRM on approval

CREATE TABLE monthly_reports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    club_id         INT NOT NULL,               -- FK to clubs table
    espocrm_club_id VARCHAR(24),                -- EspoCRM entity ID
    report_month    DATE NOT NULL,              -- First day of month
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
    coordinator_id      INT,                    -- FK to users
    reviewed_at         TIMESTAMP NULL,
    coordinator_notes   TEXT,

    -- Sync
    synced_to_crm       BOOLEAN DEFAULT FALSE,
    crm_sync_at         TIMESTAMP NULL,

    UNIQUE KEY uk_club_month (club_id, report_month)
);
```

### 7.4 EspoCRM Entity Relationships (Children & Guardians)

```
┌──────────────┐
│    Church     │
│  (EspoCRM)   │
└──────┬───────┘
       │ 1:many
       ▼
┌──────────────┐        ┌─────────────────┐
│     Club     │ 1:many │    Contact      │
│  (EspoCRM)   │───────>│   (EspoCRM)     │
│              │        │                 │
│  • name      │        │  • role:        │
│  • type:     │        │    director |   │
│    adv | pf  │        │    staff |      │
│  • church_id │        │    coordinator |│
│              │        │    admin        │
└──────┬───────┘        │  • club_id      │
       │                │  • church_id    │
       │ 1:many         └─────────────────┘
       ▼
┌──────────────┐        ┌─────────────────┐
│    Child     │ many:  │ Parent/Guardian │
│  (EspoCRM)   │ many   │   (EspoCRM)     │
│              │<──────>│                 │
│  • name      │        │  • name         │
│  • dob       │        │  • phone        │
│  • gender    │        │  • email        │
│  • club_id   │        │  • relationship │
│  • club_type │        │    (mother,     │
│    (adv|pf)  │        │     father,     │
│  • status    │        │     guardian)   │
│    (active | │        │  • can_login    │
│     inactive)│        │    (bool)       │
│              │        └─────────────────┘
└──────┬───────┘
       │ 1:many
       ▼
┌──────────────────┐
│ Form Submission  │
│  (Express API)   │
│                  │
│  • child_id      │
│  • template_id   │
│  • status        │
│  • submitted_by  │
│    (parent_id)   │
└──────────────────┘
```

### 7.5 Forms Engine Schema

```sql
-- Conference-defined form templates
CREATE TABLE form_templates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,          -- "Medical Authorization Form"
    slug            VARCHAR(100) NOT NULL UNIQUE,   -- "medical-authorization"
    description     TEXT,
    fields          JSON NOT NULL,                  -- Field definitions (see below)
    required        BOOLEAN DEFAULT TRUE,           -- Required for all enrolled children?
    applies_to      ENUM('all', 'adventurer', 'pathfinder') DEFAULT 'all',
    expires         ENUM('never', 'annually', 'per_event') DEFAULT 'annually',
    requires_review BOOLEAN DEFAULT FALSE,          -- Director must approve?
    active          BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW() ON UPDATE NOW()
);

-- Example fields JSON structure:
-- [
--   { "name": "allergies", "type": "textarea", "label": "Known Allergies", "required": true },
--   { "name": "medications", "type": "textarea", "label": "Current Medications", "required": false },
--   { "name": "insurance_provider", "type": "text", "label": "Insurance Provider", "required": true },
--   { "name": "insurance_policy", "type": "text", "label": "Policy Number", "required": true },
--   { "name": "emergency_contact", "type": "text", "label": "Emergency Contact Name", "required": true },
--   { "name": "emergency_phone", "type": "tel", "label": "Emergency Contact Phone", "required": true },
--   { "name": "parent_signature", "type": "signature", "label": "Parent/Guardian Signature", "required": true }
-- ]

-- Individual form submissions (one per child per template)
CREATE TABLE form_submissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    template_id     INT NOT NULL,                   -- FK to form_templates
    child_id        VARCHAR(24) NOT NULL,           -- EspoCRM Child entity ID
    club_id         INT NOT NULL,                   -- FK for scoping queries
    submitted_by    INT NOT NULL,                   -- FK to users (parent/guardian)
    data            JSON NOT NULL,                  -- Submitted field values
    status          ENUM('draft', 'submitted', 'approved', 'expired')
                    DEFAULT 'draft',
    reviewed_by     INT,                            -- FK to users (director) — if review required
    reviewed_at     TIMESTAMP NULL,
    reviewer_notes  TEXT,
    submitted_at    TIMESTAMP DEFAULT NOW(),
    expires_at      DATE,                           -- Calculated from template.expires

    UNIQUE KEY uk_child_template (child_id, template_id)
);

-- Track which forms are pending for a child (view for directors)
CREATE VIEW form_status_by_child AS
SELECT
    c.child_id,
    c.club_id,
    ft.id AS template_id,
    ft.name AS form_name,
    ft.required,
    COALESCE(fs.status, 'not_started') AS status,
    fs.submitted_at,
    fs.expires_at,
    CASE
        WHEN fs.status = 'approved' AND (fs.expires_at IS NULL OR fs.expires_at > CURDATE())
            THEN 'complete'
        WHEN fs.status = 'submitted'
            THEN 'pending_review'
        WHEN fs.status = 'expired' OR (fs.expires_at IS NOT NULL AND fs.expires_at <= CURDATE())
            THEN 'expired'
        ELSE 'missing'
    END AS effective_status
FROM form_templates ft
CROSS JOIN (SELECT DISTINCT child_id, club_id FROM form_submissions
            UNION SELECT id AS child_id, club_id FROM espocrm_children_cache) c
LEFT JOIN form_submissions fs
    ON fs.template_id = ft.id AND fs.child_id = c.child_id
WHERE ft.active = TRUE;
```

### 7.6 Yearly Mapper (Calendar Intelligence)

The "Yearly Mapper" is implemented as a WordPress Custom Post Type with date-based auto-promotion logic:

```
┌──────────┐    Congress ends     ┌─────────────┐    Date passes    ┌──────────┐
│ CURRENT  │ ──────────────────> │ TRANSITION  │ ───────────────> │ CURRENT  │
│  YEAR    │                     │   PERIOD    │                  │   YEAR   │
│ (events) │                     │ (both shown)│                  │ (events) │
└──────────┘                     └─────────────┘                  └──────────┘
```

- Events have a `program_year` ACF field (e.g., `2026-2027`).
- A `transition_date` option marks when the calendar flips.
- During the transition period (configurable, 2 weeks), both years shown.
- The MCP `calendar://get_yearly` tool handles this logic.

---

## 8. API Gateway

### 8.1 Express API Responsibilities

| Responsibility | Implementation |
|----------------|----------------|
| **Authentication** | JWT (access + refresh tokens), stored in `httpOnly` secure cookies |
| **Authorization** | Role-based: `public` → `leader` → `coordinator` → `admin` |
| **Rate Limiting** | Redis-backed sliding window. 100 req/min authenticated, 30 req/min public. |
| **Chat Endpoint** | Manages conversation state, calls Claude API with MCP tools |
| **Report CRUD** | Monthly report submission, review, approval workflow |
| **CRM Sync** | Approved reports synced to EspoCRM |
| **Audit Logging** | Every write operation logged: user, timestamp, IP, action |
| **Caching** | Redis cache for WordPress content (TTL: 5 min) |

### 8.2 API Route Structure

```
/api/v1/
├── /auth
│   ├── POST   /login
│   ├── POST   /logout
│   ├── POST   /refresh
│   └── POST   /forgot-password
│
├── /chat
│   ├── POST   /message              # Send message, get AI response
│   ├── GET    /conversations/:id    # Load conversation history
│   └── DELETE /conversations/:id    # Clear conversation
│
├── /reports
│   ├── POST   /                     # Submit monthly report
│   ├── GET    /:clubId              # Club's report history
│   ├── PUT    /:id/approve          # Coordinator action
│   ├── PUT    /:id/request-revision
│   └── GET    /dashboard            # Conference-wide analytics
│
├── /children
│   ├── GET    /                     # List children (scoped by role)
│   ├── POST   /                     # Add child (director or parent)
│   ├── GET    /:id                  # Child detail + form status
│   ├── PUT    /:id                  # Update child info
│   └── GET    /:id/forms            # All form statuses for child
│
├── /guardians
│   ├── GET    /                     # List guardians (scoped by role)
│   ├── POST   /                     # Add guardian (director or self-register)
│   ├── GET    /:id                  # Guardian detail + linked children
│   └── PUT    /:id                  # Update guardian info (self or director)
│
├── /forms
│   ├── GET    /templates            # List active form templates
│   ├── POST   /templates            # Create template (admin only)
│   ├── PUT    /templates/:id        # Update template (admin only)
│   ├── POST   /submit               # Parent submits form for child
│   ├── GET    /club/:clubId         # Form status grid for all children in club
│   ├── PUT    /:id/approve          # Director approves form (if review required)
│   └── GET    /my-children          # Parent: forms for own children
│
├── /directors
│   └── GET    /contact              # Directory of directors (for director-to-director email)
│
├── /notifications
│   ├── GET    /                     # User's notifications
│   ├── PUT    /:id/read             # Mark as read
│   └── POST   /send                 # Admin: send notification
│
└── /health                          # Service health check
```

### 8.3 Data Flow: Monthly Report Submission

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
     │                                │  Notify coordinator    │
     │                                │  (email via Resend)    │
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

## 9. Security Architecture

### 9.1 Authentication & Authorization

| Layer | Mechanism |
|-------|-----------|
| **User Auth** | JWT with short-lived access tokens (15 min) + long-lived refresh tokens (7 days) in `httpOnly`, `Secure`, `SameSite=Strict` cookies. Refresh tokens rotated on use. |
| **API-to-Backend Auth** | Service-to-service only. WPGraphQL: WP Application Passwords. EspoCRM: HMAC-signed API keys. WooCommerce: OAuth 1.0a consumer keys with read/write scoping. All keys in environment variables, never in code. |
| **Role-Based Access** | Five tiers: `public` → `parent` → `staff` → `director` → `coordinator` → `admin`. Each API route and MCP tool declares its minimum role. Middleware enforces. See §9.5 for full permissions matrix. |
| **Session Management** | Redis-backed. Sessions invalidated on password change. Admin can force-logout any user. |

### 9.2 Defense in Depth

```
Layer 1: Cloudflare ──── WAF rules, DDoS mitigation, bot management
                         Rate limiting at edge, geo-blocking if needed
                         SSL termination (Full Strict mode)

Layer 2: Nginx ───────── Security headers (HSTS, CSP, X-Frame-Options)
                         Request size limits (client_max_body_size 10M)
                         Reverse proxy with upstream health checks

Layer 3: Express API ─── Input validation (Zod schemas on every route)
                         SQL injection prevention (parameterized queries)
                         XSS prevention (DOMPurify on any user HTML)
                         CSRF protection (double-submit cookie pattern)
                         Rate limiting (Redis sliding window)

Layer 4: MCP Server ──── Auth-scoped tool results (role checked per call)
                         No PII in public-facing responses
                         Tool-level rate limiting

Layer 5: Databases ───── Encrypted at rest (MariaDB encryption plugin)
                         Network isolation (Docker internal network only)
                         No public port exposure
                         Per-service DB users (least privilege)

Layer 6: AI Layer ─────  System prompt guardrails
                         No theological opinion generation
                         Scope restriction to GNYC AYM topics
                         Monthly cost cap ($50 hard limit)
```

### 9.3 WordPress Hardening (Mandatory)

WordPress handles content and financial transactions (WooCommerce). These are non-negotiable:

1. **Disable XML-RPC** (`xmlrpc.php`) — block at Nginx level (`location ~ /xmlrpc.php { deny all; }`)
2. **Disable REST API user enumeration** — filter `wp-json/wp/v2/users`
3. **Hide `/wp-admin` behind IP allowlist** — only conference office IPs + VPN
4. **Two-Factor Authentication** — mandatory for all WP admin accounts (WP 2FA plugin)
5. **Disable file editing** — `define('DISALLOW_FILE_EDIT', true)`
6. **Disable plugin/theme install from admin** — `define('DISALLOW_FILE_MODS', true)`. All changes through deployment pipeline.
7. **Automatic security updates** — WordPress core and plugins
8. **Database table prefix** — change from default `wp_`
9. **Separate DB user for WooCommerce** — least-privilege, WooCommerce tables only
10. **WPGraphQL access control** — restrict introspection queries. Only allow queries from the MCP server's internal network IP.

### 9.4 Data Privacy & Compliance

| Concern | Mitigation |
|---------|------------|
| **Youth data (minors)** | All PII for minors stored in EspoCRM (profile) and Express API DB (form submissions). Access restricted by role — directors see own club, coordinators see area, admins see all. Parents see only their own children. AI assistant never surfaces PII — names and club affiliations only, never contact details or medical data. |
| **Form data (medical, consent)** | Medical authorization forms contain sensitive health information (allergies, medications, insurance). Stored encrypted in Express API DB. Access restricted to the child's director and coordinators. Parents can view/update their own submissions. Forms are never exposed through the AI assistant. |
| **Parent/Guardian data** | Contact information (phone, email) visible only to directors and staff within the same club, and coordinators for the area. Never returned by the AI assistant. Parents can update their own contact info only. |
| **Payment data** | NEVER stored on our servers. Stripe/PayPal handles all card data. WooCommerce stores order metadata only. |
| **AI conversations** | Stored in Redis with 24-hour TTL. No long-term storage of chat transcripts. No training data sent to AI providers. |
| **Data retention** | Monthly reports: 7 years. User accounts: deactivated (not deleted) on transition. Audit logs: 2 years. |
| **Backups** | Automated daily database backups to encrypted off-site storage. 30 days rolling + monthly snapshots for 1 year. |

### 9.5 Role-Based Permissions Matrix

| Action | Admin | Admin Asst. | Registrar | Instructor | Lead Coordinator | Area Coordinator| Director | Staff | Parent/Guardian | Store Manager | Store Staff|
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Own Profile** | | | | | | |
| Update own information | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| **Children & Roster** | | | | | |
| View registered children in club | All clubs | All clubs | | | Area clubs | Assigned clubs  | Own club | Own club | No | No | No |
| Add children in club | All clubs |  All clubs | | | Area clubs | Assigned clubs | Own club | No | Own children only | No | No |
| Modify children in club | All clubs |  All clubs | | | Area clubs |  Assigned clubs | Own club | Yes | Own children only | No | No |
| Create children (new enrollment) | Yes | Yes | | | | Assigned clubs | Yes | No | Yes — own children | No | No |
| **Parent/Guardian Records** | | | | | |
| Add parent/guardian | All | All | | | No| Assigned clubs | Own club | No | Own record only | No | No |
| Modify parent/guardian | All | All | | | No | Assigned clubs | Own club | Own club | Own record only | No | No |
| View parent/guardian contact info | All | All | | | Area | Assigned clubs | Own club | Own club | Own children only | No | No |
| **Forms** | | | | | |
| Create / manage form templates | Yes | Yes | | | No | No | No | No | No | No | No |
| View form status for children in club | All clubs | All clubs | | | Area clubs | Assigned clubs | Own club | Own club | No | No | No |
| Submit forms on behalf of children | No | No | No | No  | No | No | No | No | Yes — own children | No | No |
| View form requests and status | Yes | Yes  | No | No | No | No | Own club | Own club | Yes — own children  | No | No |
| **Club Reports** | | | | | |  |
| Submit monthly reports | No | No | No | No | No | No | Yes | Yes | No | No | No |
| Approve reports | All | No | | | | Area clubs  | Assigned clubs | No | No | No | No |
| Review reports | All | All | | | Area clubs  | Assigned clubs | No | No | No | No | No |
| View conference-wide analytics | Yes | Yes | Yes | | | No | No | No | No | No | No |
| **Coordinator Reports** | | | | | |  |
| Submit monthly reports | No | Yes  | Yes | No | No | No | No | No | No | No | No |
| Approve reports | Yes | No | | | | Area clubs  | No | No | No | No | No |
| Review reports | Yes | Yes | | | | Area clubs | No | No | No | No | No |
| View conference-wide analytics | Yes | Yes | | | | No  | No | No | No | No | No |
| **Communication** | | | | | | |
| Contact directors via email | Yes | Yes | | | Yes |  Yes | Own club | Own club |
| Send notifications | Yes | Yes | | | | Area clubs | Assigned clubs | No | No | No | No |
| **System** | | | | | | |
| Manage WordPress content | Yes | Limited | | | No  | No | No | No | No | No | No |
| Manage WooCommerce store | Yes | Yes | | | No  | No | No | No | No | Yes | Limited |
| Manage EspoCRM entities | Yes | Limited | | |Limited  | Limited | No | No | No | No | No |
| Force-logout users | Yes | Yes | | | No  | No | No | No | No | No | No | No | No |
---

## 10. HCI & User Experience Architecture

### 10.1 User Personas & Primary Tasks

| Persona | Device | Primary Tasks | Time Budget |
|---------|--------|-------------|-------------|
| **Club Director** | Mobile (85%) | Submit monthly report, manage roster (children + guardians), view form status, contact other directors | < 3 min (report), 5-10 min (roster) |
| **Club Staff** | Mobile (75%) | View roster, view guardian contacts, view form status, submit monthly reports | 5-10 min/week |
| **Parent/Guardian** | Mobile (90%) | Update own info, manage children's info, submit forms (medical, consent), view form requests | 5-10 min per form |
| **Coordinators** | Desktop (70%) | Review & approve reports, view club analytics, view area rosters | 10-15 min/day |
| **Conference Admin** | Desktop | Manage content (Elementor), manage store (WooCommerce), create form templates, manage EspoCRM | Ongoing |
| **New Director** | Mobile/Desktop | Onboarding — chatbot guides them through setup, roster import, first report | First 30 days |

### 10.2 Performance Budgets

| Metric | Target | Enforcement |
|--------|--------|-------------|
| Largest Contentful Paint (LCP) | < 2.5s on 4G | Cloudflare caching + WP caching plugin |
| First Input Delay (FID) | < 100ms | Minimize JS in embedded React apps |
| Cumulative Layout Shift (CLS) | < 0.1 | Image dimensions, font `swap` |
| Chat widget load | < 50KB gzipped | Lazy-load on first interaction (click to open) |
| Chat response time | < 3s for simple queries | Claude Haiku for straightforward lookups |

### 10.3 Mobile-First Report Submission UX

The report portal is an **embedded React app** inside a WordPress page. Design principles:

1. **Progressive Disclosure** — Step-through wizard: Membership → Service → Education → Review & Submit
2. **Smart Defaults** — Pre-fill last month's membership count. Leader only updates deltas.
3. **Forgiving Input** — Large tap targets (min 48px), number steppers (not free-text), sliders for hours
4. **Instant Validation** — Inline, real-time. No "submit and see errors" pattern.
5. **Offline Capability** — Reports save to `localStorage` as drafts. Sync when connectivity returns. Visual sync status indicator.
6. **Progress Persistence** — If the leader leaves mid-report, the draft is preserved exactly where they left off.
7. **Confirmation & Reward** — Success state with progress bar toward "Pathfinder of the Year" benchmarks.

### 10.4 Accessibility Requirements (WCAG 2.1 AA)

| Requirement | Implementation |
|-------------|----------------|
| Color contrast | Minimum 4.5:1 for text, 3:1 for large text |
| Keyboard navigation | All interactive elements focusable and operable |
| Screen reader | Semantic HTML, ARIA labels, live regions for chat |
| Motion | Respect `prefers-reduced-motion` |
| Language | `lang="en"` on `<html>`. Future: `lang="es"` for Spanish |
| Forms | Every input has a visible `<label>`. Errors linked with `aria-describedby`. |
| Chat widget | Accessible chat: ARIA live region for new messages, keyboard-navigable, screen reader announces responses |

### 10.5 Internationalization (i18n)

GNYC serves a significant Spanish-speaking population. Strategy:

- **Elementor pages:** Use Elementor's multilingual support or WPML plugin for Spanish versions of key pages
- **Embedded React apps (portal, chat):** Use `react-intl` or `i18next` for UI strings from day one. Even if only English at launch, the infrastructure is ready.
- **AI Assistant:** Claude translates on the fly. No translation infrastructure needed — "dime en español" works out of the box.

---

## 11. Infrastructure & Deployment

### 11.1 Hosting

| Component | Hosting | Cost |
|-----------|---------|------|
| Full application stack | **VPS (Hetzner/Linode)** — Dedicated 8GB+ | ~$40-60/month |
| CDN + WAF | **Cloudflare (Free tier)** | $0 |
| DNS | **Cloudflare** | $0 |
| Transactional email | **Resend** (free tier: 3,000 emails/month) | $0 |
| Backups | **Hetzner Storage Box** or **Backblaze B2** | ~$5/month |
| AI model | **Claude API** (Sonnet/Haiku) | ~$15-25/month |
| **Total** | | **~$60-90/month** |

### 11.2 Docker Compose

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports: ["80:80", "443:443"]
    volumes:
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    depends_on: [wordpress, api]
    # ⚠️ Config changes: docker compose up -d --force-recreate nginx

  wordpress:
    image: wordpress:6-php8.2-fpm
    volumes:
      - wp_data:/var/www/html
      - wp_uploads:/var/www/html/wp-content/uploads
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: wp_gnyc_aym
      WORDPRESS_DB_USER: wp_user
      WORDPRESS_DB_PASSWORD: ${WP_DB_PASSWORD}
    depends_on: [db]

  api:
    build: ./api
    environment:
      DB_HOST: db
      DB_NAME: gnyc_aym_api
      REDIS_URL: redis://redis:6379
      WP_GRAPHQL_URL: http://wordpress:8080/graphql
      WP_APP_PASSWORD: ${WP_APP_PASSWORD}
      ESPOCRM_URL: http://espocrm:80
      ESPOCRM_API_KEY: ${ESPOCRM_API_KEY}
      WC_CONSUMER_KEY: ${WC_CONSUMER_KEY}
      WC_CONSUMER_SECRET: ${WC_CONSUMER_SECRET}
      ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY}
      AI_MONTHLY_COST_CAP: "50.00"
    depends_on: [db, redis]

  espocrm:
    image: espocrm/espocrm
    volumes:
      - espo_data:/var/www/html
    environment:
      ESPOCRM_DATABASE_HOST: espo_db
      ESPOCRM_DATABASE_NAME: espocrm
      ESPOCRM_DATABASE_USER: espo_user
      ESPOCRM_DATABASE_PASSWORD: ${ESPO_DB_PASSWORD}
    depends_on: [espo_db]

  db:
    image: mariadb:11
    volumes:
      - db_data:/var/lib/mysql
    environment:
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MARIADB_DATABASE: wp_gnyc_aym

  espo_db:
    image: mariadb:11
    volumes:
      - espo_db_data:/var/lib/mysql
    environment:
      MARIADB_ROOT_PASSWORD: ${ESPO_DB_ROOT_PASSWORD}
      MARIADB_DATABASE: espocrm

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru

volumes:
  nginx_conf:
  wp_data:
  wp_uploads:
  espo_data:
  db_data:
  espo_db_data:
  redis_data:
```

### 11.3 Nginx Configuration

```nginx
# nginx/default.conf

upstream wordpress_backend {
    server wordpress:8080;
}

upstream api_backend {
    server api:4000;
}

server {
    listen 80;
    server_name gnycyouth.org www.gnycyouth.org;

    client_max_body_size 10M;

    # Security headers
    add_header X-Content-Type-Options    "nosniff" always;
    add_header X-Frame-Options           "SAMEORIGIN" always;
    add_header Referrer-Policy           "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy        "camera=(), microphone=(), geolocation=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Block XML-RPC (brute-force vector)
    location ~ /xmlrpc\.php$ {
        deny all;
        return 403;
    }

    # Block WP user enumeration via REST API
    location ~ /wp-json/wp/v2/users {
        deny all;
        return 403;
    }

    # Restrict wp-admin to allowed IPs (conference office + VPN)
    location ~ /wp-admin {
        # allow 203.0.113.0/24;    # Conference office IP range
        # allow 10.0.0.0/8;        # VPN range
        # deny all;
        proxy_pass http://wordpress_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Express API
    location /api/ {
        proxy_pass http://api_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket support (for future SSE/push notifications)
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # WordPress (everything else — Elementor renders all pages)
    location / {
        proxy_pass http://wordpress_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Static asset caching
    location ~* \.(js|css|png|jpg|jpeg|gif|webp|ico|svg|woff2?)$ {
        proxy_pass http://wordpress_backend;
        proxy_set_header Host $host;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

> **Note:** SSL termination is handled by Cloudflare (Full Strict mode). Nginx listens on port 80 inside the Docker network. Cloudflare encrypts the connection between the browser and the edge. If you need origin TLS (Cloudflare → Nginx), add a Cloudflare Origin Certificate and configure an `ssl` server block.

### 11.4 CI/CD Pipeline

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

### 11.5 Backup Strategy

| What | Frequency | Retention | Where |
|------|-----------|-----------|-------|
| MariaDB (all databases) | Every 6 hours | 30 days rolling | Encrypted off-site |
| WordPress uploads | Daily | 30 days | Off-site |
| EspoCRM data volume | Daily | 30 days | Off-site |
| Full server snapshot | Weekly | 4 weeks | VPS provider |
| Database SQL dump | Monthly | 12 months | Off-site archive |

### 11.6 Monitoring

Deploy **Uptime Kuma** (self-hosted, lightweight) to monitor:

- All container health endpoints
- API response time (alert if > 2s)
- Database connections
- SSL certificate expiration
- Disk usage (alert if > 80%)
- WordPress login page availability
- Claude API reachability and spend tracking

---

## 12. Integration Risk Assessment & Recommendations

### 12.1 Risk Register

| # | Risk | Severity | Likelihood | Mitigation |
|---|------|----------|------------|------------|
| R1 | **WordPress security** — largest attack surface, handles financial data | HIGH | HIGH | Headless NOT required, but: IP-restricted admin, 2FA mandatory, WAF rules, auto-patching, `DISALLOW_FILE_MODS`. See §9.3. |
| R2 | **EspoCRM API limitations** — no real-time push for coordinator notifications | MEDIUM | HIGH | Express API sends notifications directly (email via Resend). Don't depend on EspoCRM for real-time anything — use it as system of record. |
| R3 | **Data consistency** — person records diverge between WP, EspoCRM, WooCommerce | HIGH | HIGH | Single source of truth (EspoCRM). All systems reference `espocrm_contact_id`. Nightly reconciliation job flags orphaned records. |
| R4 | **Embedded React app conflicts with Elementor** — CSS/JS collisions | MEDIUM | MEDIUM | Isolate React apps with Shadow DOM or CSS Modules + prefixed class names. Load React bundle only on portal/chat pages, not site-wide. |
| R5 | **AI cost overruns** — chatbot goes viral or gets abused | LOW | MEDIUM | Hard monthly cap ($50). Rate limiting per user. Graceful degradation to "contact us" message when cap reached. |
| R6 | **AI hallucination** — chatbot invents event dates or policies | MEDIUM | MEDIUM | Tool-use pattern (not RAG) ensures responses are grounded in real data. System prompt explicitly prohibits invention. QA testing for common edge cases. |
| R7 | **WordPress plugin conflicts** — WPGraphQL, ACF, WooCommerce, Elementor interact poorly | MEDIUM | LOW | Pin plugin versions. Test updates in staging. Keep total plugin count under 15. |
| R8 | **Offline report submission conflicts** — two leaders submit for same club | MEDIUM | LOW | Unique constraint `(club_id, report_month)`. Last-write-wins with conflict notification. |
| R9 | **Form data sensitivity** — medical forms contain health info for minors | HIGH | LOW | Encrypt form submission data at rest. Restrict access to director + coordinator + parent of child. Never expose via AI assistant. Audit log all access. |
| R10 | **Parent self-registration abuse** — unauthorized person creates a parent account and links to a child | MEDIUM | MEDIUM | Parent accounts require director approval before accessing child data. Director verifies the parent-child relationship. Invitation-based flow preferred over open registration. |
| R11 | **EspoCRM entity complexity** — adding Children, Parents, and form tracking significantly increases CRM schema | MEDIUM | MEDIUM | Design EspoCRM entities during Phase 0. Test with sample data before production import. Keep form submissions in Express API DB (not EspoCRM) to avoid overloading CRM. |

### 12.2 Architectural Recommendations

**Recommendation 1: Shadow DOM for Embedded React Apps**

> When you embed a React app inside a WordPress page, Elementor's CSS can bleed into your React components and vice versa. Use Shadow DOM encapsulation to create a clean boundary:
>
> ```js
> // portal-loader.js (loaded on the WordPress portal page)
> const host = document.getElementById('leader-portal');
> const shadow = host.attachShadow({ mode: 'open' });
> const mountPoint = document.createElement('div');
> shadow.appendChild(mountPoint);
> ReactDOM.createRoot(mountPoint).render(<PortalApp />);
> ```
>
> This ensures zero CSS conflicts between Elementor and React.

**Recommendation 2: Lazy-Load the Chat Widget**

> The chat widget JS bundle should NOT load on page load. It should load when the user clicks the chat icon. This keeps page performance unaffected:
>
> ```js
> // chat-trigger.js (tiny — <1KB)
> document.getElementById('chat-trigger').addEventListener('click', () => {
>   import('./chat-widget.js').then(module => module.init());
> });
> ```

**Recommendation 3: Build a Notification Service Early**

> Multiple features need notifications: report submitted (notify coordinator), report approved (notify leader), event reminders, store order confirmations.
>
> Build a centralized notification service in the Express API from Phase 1:
> - Email (via Resend — free tier: 3,000/month)
> - In-app (stored in DB, shown in portal header)
> - Future: Push notifications (web-push for PWA)

**Recommendation 4: Internationalization (i18n) from Day One**

> GNYC serves a significant Spanish-speaking population. For Elementor pages, use WPML or Polylang. For embedded React apps, use `react-intl` from the start. The AI assistant handles Spanish natively via Claude — no extra infrastructure.

**Recommendation 5: Content Structure with ACF Before Building the AI**

> The AI assistant is only as good as the content it can access. Before building the chatbot, ensure WordPress content is structured with ACF fields (audience, category, summary, file attachments). Unstructured Elementor page content is harder for the AI to parse meaningfully.

**Recommendation 6: Health Dashboard from Day One**

> Deploy Uptime Kuma alongside the first `docker compose up`. Monitor everything from the start, not after the first outage.

---

## 13. Phase Roadmap

### Phase 0: Pre-Flight (Now — May 2026)

*Low-bandwidth planning while Camporee is primary focus.*

| Task | Owner | Deliverable |
|------|-------|-------------|
| Define EspoCRM entity schema (Clubs, Contacts, Children, Parents/Guardians, Certs, Reports) | Dir + Dev | Entity diagram |
| Design monthly report form fields and approval workflow | Director | Field list + workflow diagram |
| Define form templates needed (medical authorization, photo consent, permission slips) | Director | Template list + required fields per form |
| Design parent/guardian registration and approval workflow | Dir + Dev | Workflow diagram |
| Structure WordPress content with ACF fields (resources, manuals) | Dir + Dev | ACF field groups configured |
| Install WPGraphQL + ACF plugins on WordPress | Dev | Verified `/graphql` endpoint |
| Design "New Director" onboarding conversation flow | Director | Conversation script |
| Provision VPS, configure Docker Compose base | Dev | Running containers: Nginx, WP, MariaDB, Redis |

### Phase 1: Foundation (June — August 2026)

*CRM, reporting engine, and API layer.*

| Task | Target |
|------|--------|
| Deploy EspoCRM, configure entities, import existing club data | June |
| Build Express API with auth (JWT + roles) | June |
| Build MCP Server with WordPress + EspoCRM tools | June-July |
| Build monthly report submission portal (React, embedded in WP) | July |
| Build coordinator approval dashboard | July |
| Build children & guardian management (director portal) | July-August |
| Build parent/guardian self-service portal (registration, child management) | August |
| Build forms engine — templates, submission, status tracking | August |
| Build notification service (email via Resend) | July |
| Deploy Uptime Kuma monitoring | June (day 1) |
| Set up automated backups | June (day 1) |
| Set up CI/CD pipeline (GitHub Actions) | June |
| WordPress security hardening (full checklist §9.3) | June |

### Phase 2: Intelligence & Commerce (September — November 2026)

*AI assistant and Youth Store activation.*

| Task | Target |
|------|--------|
| Build Gen AI chat widget (React, embedded globally in WP) | September |
| Configure Claude system prompt, connect MCP tools | September |
| Test and refine AI guardrails | September-October |
| Activate WooCommerce Youth Store (Stripe integration) | September |
| Add WooCommerce tools to MCP Server | October |
| Implement Yearly Mapper (calendar auto-promotion) | October |
| GA4 integration (client + server-side events) | October |
| Build conference-wide analytics dashboard | November |
| Performance audit + optimization | November |

### Phase 3: Engagement (December 2026 — February 2027)

*Advanced features and polish.*

| Task | Target |
|------|--------|
| Club ranking / scoring system (based on monthly reports) | December |
| AI-powered onboarding for new directors | January |
| Spanish language support (i18n — Elementor pages + portal) | January |
| Offline report support (localStorage + sync) | February |
| Security audit (penetration testing) | February |

### Phase 4: Scale (March 2027+)

*Future capabilities — designed for but not built yet.*

- PWA with push notifications (web-push)
- Native mobile app (React Native, consuming same Express API + MCP tools)
- Advanced analytics / BI dashboard for Union reporting
- Inter-conference data sharing (federation)
- AI-powered resource recommendations based on club activity
- Headless migration (if WordPress becomes a bottleneck — architecture supports this without rework)

---

## Appendix: Architecture Decision Records

### ADR-001: Retain WordPress + Elementor (Not Headless)

**Decision:** Keep WordPress with Elementor as the public-facing website. Do not go headless.
**Rationale:** Staff have already built pages in Elementor. Going headless wastes that work, requires retraining, and adds a separate frontend service. The CRM, reporting, and AI features work as companion services alongside WordPress — they don't require replacing it.
**Trade-off:** Page performance is limited by WordPress (mitigated by Cloudflare caching). Layout control for new features (portal, chat) is code-driven, not drag-and-drop.
**Reversibility:** HIGH — the MCP server and Express API are completely decoupled from WordPress. If headless is desired later, replace WordPress rendering with Next.js and point it at the same API/MCP layer. Zero rework on the backend.
**Status:** Accepted.

### ADR-002: MCP Server as Integration Layer

**Decision:** All backend system integrations (WordPress, EspoCRM, WooCommerce) are exposed through an MCP server rather than hardcoded in the chat endpoint.
**Rationale:** MCP is model-agnostic and client-agnostic. The same tools serve the chatbot, any future dashboard AI, Claude Desktop access, and a future mobile app. Swapping AI models requires zero integration changes.
**Trade-off:** Slightly more upfront structure than direct tool definitions. ~20% more initial work for the tool layer.
**Status:** Accepted.

### ADR-003: EspoCRM as Single Source of Truth for People Data

**Decision:** EspoCRM owns all person/contact records. Other systems reference `espocrm_contact_id`.
**Rationale:** Prevents data divergence across WordPress, WooCommerce, and EspoCRM. With 3+ systems managing people, this is the most common integration failure mode.
**Status:** Accepted.

### ADR-004: Separate Database Instances for EspoCRM

**Decision:** EspoCRM gets its own MariaDB instance.
**Rationale:** EspoCRM manages its own schema and migrations. Sharing a MariaDB instance risks schema conflicts during upgrades. Operational isolation means EspoCRM can be upgraded independently.
**Status:** Accepted.

### ADR-005: Tool-Use Pattern over RAG for AI Assistant

**Decision:** The AI assistant uses Claude's tool-use capability (via MCP) rather than a RAG (Retrieval-Augmented Generation) pipeline.
**Rationale:** Tool-use eliminates the need for a vector database and embedding pipeline. Claude calls MCP tools on demand, getting fresh data every time. RAG requires maintaining an embedding index that can go stale. Tool-use is simpler, cheaper ($0 for embeddings/vector DB), and always current.
**Trade-off:** Each AI response may require 2-4 tool calls, adding ~1-2s latency vs. a pre-embedded RAG lookup. Acceptable for conversational UX.
**Status:** Accepted.

### ADR-006: Shadow DOM for Embedded React Apps

**Decision:** React apps embedded in WordPress pages use Shadow DOM for CSS/JS isolation.
**Rationale:** Elementor generates complex CSS that will conflict with React component styles. Shadow DOM creates a clean boundary with zero risk of style leakage in either direction.
**Status:** Accepted.

### ADR-007: Forms Engine in Express API DB (Not EspoCRM)

**Decision:** Form templates and submissions are stored in the Express API's MariaDB database, not in EspoCRM.
**Rationale:** EspoCRM is optimized for people records and relationship management, not dynamic form schemas with JSON field definitions. The forms engine requires features (JSON field validation, per-child-per-template uniqueness, expiration logic, status views) that are easier to implement and query in a relational database we fully control. EspoCRM retains ownership of people data (children, guardians); the Express API handles the transactional form workflow.
**Trade-off:** Form completion status is not visible inside the EspoCRM admin UI. Coordinators and directors view form status through the portal dashboard. A sync summary (% complete per club) can be pushed to EspoCRM as a custom field if needed.
**Status:** Accepted.

### ADR-008: Parent Accounts Require Director Approval

**Decision:** Parent/guardian accounts are invitation-based or require director approval before accessing child data.
**Rationale:** Open self-registration for parent accounts creates a risk that unauthorized individuals could link themselves to a child and access their information. An approval step ensures the director verifies the parent-child relationship before granting access.
**Trade-off:** Adds friction to onboarding. Mitigated by allowing directors to send invitation links directly to parents, which pre-approve the account upon registration.
**Status:** Accepted.

### ADR-009: Headless Migration Path Preserved

**Decision:** The architecture explicitly supports a future headless migration without backend rework.
**Rationale:** If WordPress becomes a performance bottleneck or Elementor becomes a limitation, the MCP server + Express API are fully decoupled. A Next.js frontend can replace WordPress rendering and connect to the exact same backend layer. This decision is documented so future teams know the path exists.
**Status:** Deferred — revisit if WordPress performance or editorial workflow becomes a pain point.

---

*This document is a living specification. Each ADR captures not just the decision but the rationale and trade-offs, so future teams understand the context. Update this document as decisions evolve.*
