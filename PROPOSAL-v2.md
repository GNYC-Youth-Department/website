# PROPOSAL: GNYC YOUTH DIGITAL TRANSFORMATION

**To:** Christian Genao, Director of Adventist Youth Ministries, Greater New York Conference

**From:** Digital Strategy Team

**Subject:** Establishing a Modular Digital Ecosystem

---

## I. Executive Summary

Ministry today requires a digital infrastructure that matches the excellence of our mission. Our goal is to move beyond static web pages to a **Modular Digital Ecosystem** that empowers local leaders, sustains discipleship year-round, and ensures that as leadership evolves, our organizational knowledge remains permanent.

This strategy outlines a transformation designed for speed, continuity, and measurable spiritual impact, with a **Minimum Viable Product WordPress site launch targeted for April 15th**.

Phase 2 will introduce an **AI Ministry Assistant** — a conversational tool that helps directors find resources, coaches new leaders through onboarding, and gives coordinators instant insight into club health across the conference. All systems are connected through a standardized integration layer, allowing us to add new capabilities (mobile app, AI assistant, push notifications) without rebuilding what already exists.

---

## II. Strategic Point of View (The "Why")

The objective is to build a **Modular Digital Ecosystem**. By utilizing a modular design, the Greater New York Conference achieves:

- **Total Content Ownership:** All manuals, laws, and resources reside in a single, conference-controlled central repository.

- **Integrated Media Strategy:** To ensure high performance on mobile devices, all video content will be embedded (hosted on platforms like YouTube or Vimeo) rather than self-hosted.

- **Mission Continuity:** The system ensures that even as personnel change, the organizational structure and resources remain immediately accessible to new directors.

- **Bilingual Readiness:** The ecosystem is built to support both English and Spanish from the start — reflecting the communities we serve across Greater New York.

---

## III. System Overview

The following diagram shows how every piece of the ecosystem connects — from the leaders and parents who use it, to the tools that power it behind the scenes.

```
┌──────────────────────────────────────────────────────────────────┐
│                      WHO USES THE SYSTEM                         │
│                                                                  │
│   👤 Directors        👥 Club Staff        👨‍👩‍👧 Parents/Guardians     │
│   Submit reports      View roster          Submit forms          │
│   Manage roster       View form status     Manage children       │
│   Contact directors   Submit reports       View form requests    │
│                                                                  │
│   📋 Coordinators     🛡️ Conference Admin   💬 Everyone            │
│   Approve reports     Manage content       Ask the AI Assistant  │
│   View club health    Manage store         Browse resources      │
│   View area data      Create form templates                      │
│                                                                  │
└──────────────────────────┬───────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                    gnycyouth.org (WordPress)                      │
│                                                                  │
│   🌐 Public Website     📊 Leader Portal      💬 AI Assistant     │
│   Pages, events,        Reports, roster,      Answers questions, │
│   resources, store      forms, dashboard      coaches, translates│
│                                                                  │
└──────────────────────────┬───────────────────────────────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
     ┌──────────────┐ ┌─────────┐ ┌──────────────┐
     │  WordPress   │ │ EspoCRM │ │ WooCommerce  │
     │              │ │         │ │              │
     │  Content,    │ │ People, │ │ Youth Store, │
     │  manuals,    │ │ clubs,  │ │ uniforms,    │
     │  videos,     │ │ children│ │ curriculum   │
     │  events      │ │ parents,│ │              │
     │              │ │ certs,  │ │              │
     │              │ │ reports │ │              │
     └──────────────┘ └─────────┘ └──────────────┘

     🔒 Protected by Cloudflare security + encrypted backups
     💰 Total cost: ~$60-90/month | All software: $0 licensing
```

---

## IV. Tactical Point of View (The "How")

We are utilizing an integrated WordPress architecture as the primary hub for content and commerce, with a dedicated CRM and reporting engine running alongside it.

### 1. The Component Stack

- **Content Management System:** WordPress will manage manuals, event photos, announcements, and the Educational Hub. Staff build and edit pages visually using Elementor — no coding required.

- **Customer Relationship Management (EspoCRM):** For the first time, the conference will have a **single dashboard showing every club's health** — active membership, community service hours, certifications in progress, and growth trends. EspoCRM tracks the full journey from Adventurers to Master Guides, manages leader certifications, and provides coordinators with real-time visibility into every club in their area.

- **E-commerce (WooCommerce):** WooCommerce will power the Youth Store. This is an open-source solution that allows the Greater New York Conference to own its storefront and data with zero licensing fees.

### 2. Monthly Reporting Engine

The reporting system is the backbone of conference-wide visibility. It replaces manual collection with a streamlined digital workflow:

- **Club Directors** submit monthly reports from their phone in under 3 minutes — membership counts, community service hours, honors progress, and staff ratios.
- **Area Coordinators** receive an automatic notification, review the submission, and approve or request revisions — all from a simple dashboard.
- **Conference Leadership** gets a live analytics view: which clubs are thriving, which need support, and how the conference is tracking toward its goals.
- **Reports are pre-filled** with last month's data. Directors only update what changed — reducing errors and saving time.
- **Offline support:** If a director has weak or no signal, the report saves locally and submits automatically when connectivity returns.

### 3. Staff Portal — Roster, Guardians & Forms

The Staff Portal gives directors, staff, and parents/guardians a self-service interface for managing the people and paperwork of club operations.

**For Directors:**
- View and manage all registered children in their club (Adventurer or Pathfinder)
- Add or modify children's records and parent/guardian information
- View parent/guardian contact information
- See at a glance which forms are complete, pending, or missing for every child
- Contact other Adventurer or Pathfinder directors via email

**For Club Staff:**
- View the club roster and guardian contact information
- View form completion status for children in their club
- Submit monthly reports alongside the director

**For Parents/Guardians:**
- Update their own contact information
- Register and manage their own children's profiles
- Submit required forms (medical authorization, photo consent, permission slips) on behalf of their children
- View the status of form requests — what's been submitted, what's pending, what's needed

**Key safeguard:** Parent/guardian accounts require director approval before they can access child data. Directors verify the parent-child relationship, either through an invitation link or manual approval.

### 4. Event Registration

Event registration is currently under evaluation between two approaches:

- **HiEvents:** A dedicated open-source event registration platform with ticketing and capacity management. Currently being evaluated for compatibility with our custom field requirements (church, club, conference, dietary restrictions, T-shirt size) and multi-event scale (15+ events/year).
- **Fallback — WooCommerce Bookings:** If HiEvents does not meet our needs, WooCommerce can handle event registration directly, keeping everything within the WordPress ecosystem.

A final decision will be made before Phase 1 begins in June.

### 5. Operational & Analytical Integrations

- **Google Analytics 4:** Specialized tracking to measure which resources are most valuable to our leaders and which events drive the most engagement.

---

## V. Before & After: What Changes for Your Team

| Today (Manual Process) | With the New System |
|---|---|
| Email a PDF to 50 directors, hope they save it, re-send when it's updated | Upload once — every director always sees the latest version |
| Call or text coordinators to check which clubs submitted reports | Dashboard shows all clubs at a glance — who's submitted, who hasn't |
| New director calls the office asking where to find the Pathfinder manual | AI assistant walks them through onboarding step by step |
| Collect paper forms from parents, track who's missing what by hand | Parents submit forms online — directors see completion status per child |
| Manually reconcile uniform orders from multiple events | WooCommerce tracks every sale with digital receipts automatically |
| Send group texts or emails for urgent announcements | Type a message into the Alert Bar — it appears instantly on the website for all visitors |
| Maintain spreadsheets of club membership and service hours | EspoCRM tracks every club's membership, service hours, and certifications in one place |
| Director has no signal at a remote campsite, can't submit report | Report saves on their phone and submits automatically when connectivity returns |

---

## VI. The Youth Store: Operations & Guidelines

The Youth Store will be built using WooCommerce to provide a robust marketplace for uniforms and curriculum.

- **Operational Setup:** The store is established using WooCommerce, an open-source platform ensuring the conference maintains full control over digital assets and product data.

- **Payment Systems:** We will connect the store to **Stripe** and **Square** for secure transactions. All card processing is handled by these providers — no payment data is ever stored on our servers.

- **In-Person Logistics:** For the initial phase, merchandise will be sold and distributed at in-person events until a dedicated team is established for weekly shipping.

- **Takeaway Action:** Schedule a strategic meeting with the Youth Store Group to finalize the initial inventory list and the on-site payment process for upcoming events.

---

## VII. The AI Ministry Assistant (Phase 2)

Beginning in September, the ecosystem will include a **conversational AI assistant** embedded directly into the website. This is not a basic FAQ bot — it is a generative AI tool that reasons, creates, and coaches using real conference data.

**What it can do:**

| Scenario | Example |
|---|---|
| **A new director needs help getting started** | *"I just got appointed as Pathfinder director. Where do I start?"* — The assistant walks them through their first 30 days: credentials, manuals, first staff meeting agenda, and how to submit their first report. |
| **A coordinator wants a pulse check** | *"How are my clubs doing this quarter?"* — The assistant pulls monthly report data and summarizes: 15 of 22 clubs submitted, total community service hours up 15%, 3 clubs on track for Pathfinder of the Year. It flags clubs that haven't reported and offers to draft a follow-up message. |
| **A director needs something in Spanish** | *"Draft a parent letter about the upcoming campout, and translate it to Spanish"* — The assistant writes both versions using the actual event details from the system. |

The assistant connects to all conference systems (content, CRM, store, events) through the integration layer. It only answers based on real data — it never invents dates, policies, or facts. If it doesn't know something, it directs the user to their coordinator.

**Estimated cost:** ~$15-25/month for AI usage at expected volume (500 conversations/month).

---

## VIII. Project Oversight & Roadmap

The site is substantially built and will continue to move forward toward the April 15th launch.

### Project Management

- **Implementation:** The "Compass" project management tool was implemented using OpenProject.
- **Tool Evaluation:** While OpenProject is a professional-grade tool designed to manage operations at scale, the team must be comfortable using it for it to be effective.
- **Next Steps:** A presentation should be scheduled to demonstrate OpenProject to the team to determine if it fits their needs. JanCarlos Reyes will also be looking into simpler alternatives to ensure the system enables growth rather than creating a burden.

### Phased Delivery

| Phase | Timeline | Deliverables |
|---|---|---|
| **MVP Launch** | April 15, 2026 | WordPress site live with Elementor pages, Educational Hub, alert bar, initial content |
| **Phase 1 — Foundation** | June — August 2026 | EspoCRM deployed, monthly reporting engine live, coordinator approval dashboard, director portal (roster + guardian management), parent portal (forms submission), notification system (email), security hardening |
| **Phase 2 — Intelligence & Commerce** | September — November 2026 | AI Ministry Assistant launched, Youth Store activated (Stripe), event registration finalized, conference-wide analytics dashboard, GA4 integration |
| **Phase 3 — Engagement** | December 2026 — February 2027 | Club ranking/scoring system, AI-powered new director onboarding, Spanish language support, offline report submission, security audit |
| **Phase 4 — Scale** | March 2027+ | Mobile app (consuming same backend), push notifications, advanced analytics for Union reporting, AI-powered resource recommendations |

---

## IX. Cost Summary

| Item | Monthly Cost |
|---|---|
| VPS hosting (Hetzner or Linode — Dedicated 8GB) | ~$40-60 |
| AI Ministry Assistant (Claude API) | ~$15-25 |
| Cloudflare CDN + security | $0 (free tier) |
| Transactional email (Resend — report notifications, password resets) | $0 (free tier — 3,000 emails/month) |
| Off-site backups | ~$5 |
| **Total monthly operating cost** | **~$60-90** |

**All software components are open-source with $0 licensing fees.** WordPress, WooCommerce, EspoCRM, and the AI integration layer are fully owned and controlled by the conference. There is no vendor lock-in — the conference can migrate, modify, or extend any component at any time.

---

## X. Operational Guidelines

To maintain "Mission Continuity" and security:

- **Single Source of Truth:** EspoCRM is the master record for all member data. WordPress and WooCommerce reference it — they do not maintain independent people records.

- **Security:** All administrative accounts must utilize Two-Factor Authentication. The WordPress admin panel will be restricted to conference office IP addresses and VPN access.

- **Financial Integrity:** We will implement custom reconciliation protocols to ensure digital sales match Greater New York Conference financial records.

- **Data Privacy:** All personally identifiable information for minors is stored only in EspoCRM with role-based access. Payment card data is never stored on our servers — Stripe and Square handle all card processing.

- **Bilingual Support:** The website and leader portal will support both English and Spanish. The AI Ministry Assistant translates on demand — no additional infrastructure required.

- **Backups:** Automated daily database backups to encrypted off-site storage. 30-day rolling retention plus monthly snapshots for one year.

- **Future-Ready Architecture:** All systems are connected through a standardized integration layer. This means we can add a mobile app, Slack notifications, or new AI capabilities in the future without rebuilding what already exists. If WordPress ever becomes a limitation, the backend can support a full migration to a custom frontend with zero rework on the data and integration layer.
