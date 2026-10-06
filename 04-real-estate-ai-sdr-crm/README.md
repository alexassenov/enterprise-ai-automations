# Autonomous Real Estate AI SDR & Custom CRM Pipeline (Kalimera)
*Enterprise Configuration, Architecture & Operations Manual • Lexmation Production Release*

> **Platform:** n8n (v1.x+), Python / PHP 8.x FastCGI, Alpine.js, Tailwind CSS  
> **Live Web CRM Terminal:** [kalimera.lexmation.com/crm](https://kalimera.lexmation.com/crm/)  
> **Integration Ecosystem:** n8n, Perplexity API, OpenAI (GPT-4o) / Claude 3.5 Sonnet, Custom CRM REST API, Telegram Alerts, WhatsApp / Viber Click-to-Chat  
> **Architecture & Delivery:** Alex Assenov / Lexmation ([lexmation.com](https://lexmation.com))  

---

## 1. Executive Summary & Business Context

The **Kalimera Real Estate AI SDR & CRM Pipeline** is an end-to-end outbound client acquisition system engineered for luxury vacation rental properties, boutique hotels, and cliffside villas across Greece (Chalkidiki, Thassos, Lefkada, Athens Riviera, and Crete).

Instead of relying on fragile spreadsheets or generic, bloated CRM platforms, this solution combines:
1. **Automated Regional Web Scraping:** Discovers independent property websites and direct booking domains.
2. **AI Vision & Hospitality Audit Agent:** Audits each property for critical operational gaps (missing multi-channel calendar synchronization, lack of vertical video reels, and friction in direct booking flows).
3. **Multi-Lingual AI Copywriting:** Generates hyper-personalized cold outreach emails and native Greek WhatsApp opening pitches.
4. **Proprietary Web CRM Kanban Dashboard:** A high-speed, responsive visual pipeline deployed directly on [kalimera.lexmation.com/crm](https://kalimera.lexmation.com/crm/) with 1-click WhatsApp/Viber actions, status progression, and revenue forecasting.

---

## 2. End-to-End System Architecture

```text
[Cron Trigger: Daily Regional Scan across Greece]
                         │
                         ▼
        [Perplexity AI / Web Scraper Engine]
 (Finds independent luxury villas in Chalkidiki, Thassos, Lefkada)
                         │
                         ▼
       [Deep Extraction: Property & Contact Data]
  (Property Name, Owner, WhatsApp/Phone, Email, Features)
                         │
                         ▼
       [AI Hospitality Auditor & SDR (GPT-4o)]
 ┌───────────────────────┴───────────────────────┐
 ▼                                               ▼
[Operational Gap Analysis]               [Personalized Copywriting]
• Calendar Sync Assessment               • High-Converting Cold Email
• Video Marketing Presence               • Native Greek WhatsApp Pitch
• Direct Booking Friction                • Lead Grade: A+ / A / B
 └───────────────────────┬───────────────────────┘
                         │
                         ▼
            [Kalimera CRM REST API Engine]
    (POST to https://kalimera.lexmation.com/crm/api.php)
                         │
                         ▼
         [Interactive Web Kanban Dashboard]
    (https://kalimera.lexmation.com/crm/ - 6 Stages)
 ┌───────────────────────┼───────────────────────┐
 ▼                       ▼                       ▼
[1-Click WhatsApp]      [Email Draft Preview]   [Pipeline Revenue Stats]
```

---

## 3. CRM Pipeline Stages & Data Taxonomy

The custom CRM organizes leads across 6 distinct operational stages:

| Stage | Identifier | Operational Action |
| :--- | :--- | :--- |
| **1. 📥 New Discovered** | `new_discovered` | Raw villa website extracted by web scraper; queued for audit. |
| **2. 🧠 AI Audited** | `ai_audited` | Vision & LLM audit completed; personalized copy & lead grade generated. |
| **3. 🚀 Outreach Sent** | `outreach_sent` | Initial cold email dispatched or approved by business development. |
| **4. ⏳ Follow-up Due** | `follow_up` | Automated 3-day / 6-day reminder cycle waiting for owner engagement. |
| **5. 💬 Replied / Warm** | `replied` | Property owner replied via WhatsApp or Email; scheduled for discovery. |
| **6. 🤝 Deal Closed** | `deal_closed` | Onboarded to Kalimera automation & video marketing services. |

---

## 4. Key Engineering Innovations

### 1. Zero-Friction 1-Click WhatsApp & Viber Action
In Mediterranean hospitality, over 80% of villa owners communicate through **WhatsApp and Viber** rather than traditional corporate email.
* Each card in the Kalimera CRM features a dynamic WhatsApp button (`https://wa.me/{phone}?text={encoded_ai_greek_message}`).
* Sales reps can open the CRM on their iPhone/Android, tap the WhatsApp icon, and immediately send a fluent, personalized Greek pitch in under 2 seconds.

### 2. Strict Deterministic JSON Schema & Grade Scoring
Leads are strictly evaluated against institutional criteria:
* **Grade A+ (€45,000+ est. season revenue):** Luxury private villas with private pool, sea view, and high OTA dependency.
* **Grade A (€30,000 - €45,000):** Multi-unit boutique suites with high tourist demand.
* **Grade B (€20,000 - €30,000):** Independent apartments needing basic automation.

### 3. Lightweight, Self-Hosted REST Architecture
* Built with zero bloat: FastCGI PHP 8.3 backend + file-locked JSON/SQLite storage.
* Loads in under **150ms** with zero monthly SaaS seat licensing fees.
* Includes REST API endpoints (`/crm/api.php`) for seamless two-way syncing with n8n, Make, or custom Python scripts.

---

## 5. Sample AI Audit & Outreach Output

### Discovered Lead: **Villa Kassandra Breeze (Chalkidiki)**
* **Estimated Seasonal Revenue:** `€45,000/season`
* **Lead Grade:** `A+`
* **Audit Findings:**
  * *Calendar Sync:* Missing (manual booking risks double bookings between Airbnb and direct).
  * *Video Marketing:* Zero vertical reels on Instagram or TikTok.
  * *Direct Booking:* Static inquiry form without automated WhatsApp confirmation.

#### Generated Cold Email:
> **Subject:** Quick question regarding direct bookings for Villa Kassandra Breeze  
> **Body:**  
> Hi Nikos,  
> I was admiring Villa Kassandra Breeze—your sea-view terrace and 4.9 rating on Google are exceptional.  
> While browsing your direct site, I noticed inquiries still go through a manual form, which typically loses 30-40% of impulse travelers who want instant confirmation.  
> At Kalimera, we install an automated AI guest responder and video marketing system for Greek luxury villas that syncs all calendars and captures direct WhatsApp reservations on 100% autopilot.  
> Would you be open to seeing a 2-minute video mockup of how this works for Kassandra Breeze?

#### Native Greek WhatsApp Pitch:
> *Γεια σας κ. Νίκο! Είδα την εξαιρετική Villa Kassandra Breeze στη Χαλκιδική. Ετοιμάσαμε ένα δωρεάν promo video mockup και αυτόματο σύστημα κρατήσεων για άμεσες κρατήσεις χωρίς προμήθειες πλατφορμών. Μπορώ να σας στείλω το 2λεπτο βίντεο εδώ;*

---

## 6. Real-Time WhatsApp Integration & 24/7 AI Concierge

The system features an autonomous, multi-tenant WhatsApp subsystem built on native `@whiskeysockets/baileys` and n8n:

```text
[Guest WhatsApp Message]
           │
           ▼
[Baileys Gateway (:8085)] ──► Resolves Meta LID privacy IDs to real phone numbers
           │
           ▼
[n8n AI Concierge Webhook]
   ├── 1. Fetch Conversation History (CRM Memory Buffer)
   ├── 2. Smart Intent Filter (Bypasses personal chats, triggers on hospitality intents)
   ├── 3. Lead Status Update (Updates CRM lead to 'replied')
   ├── 4. AI Concierge (Gemini 3.8 Flash):
   │       • Domain knowledge: elasa.assenov-solutions.com
   │       • Dynamic quote generator (€90/night for 2 guests, 7-night min)
   │       • Conversation continuity (never repeats greetings, remembers quoted dates)
   └── 5. Dispatch WhatsApp Reply (:8085/send)
```

### Components Included:
1. `whatsapp-gateway/server.js`: Zero-cost Baileys microservice running on port `8085` via `systemd`. Includes phone pairing code linking and automatic Meta LID reverse mapping.
2. `inbound_ai_whatsapp_concierge.json`: Full n8n workflow for conversational reservations, smart intent filtering, and context preservation.
3. `outbound_send_whatsapp.json`: 1-click outbound messaging pipeline triggered from the CRM.
4. `crm_api.php`: High-speed PHP API with memory endpoints (`get_conversation`, `append_conversation`).

---

## 7. Setup & Deployment Guide

### 1. Workflow Import:
1. In n8n, import `workflow.json` (Lead Discovery & Audit), `outbound_send_whatsapp.json` (Outreach), and `inbound_ai_whatsapp_concierge.json` (AI Auto-Responder).
2. Configure credentials: `OpenAI account 2` (or Gemini 3.8 Flash low).

### 2. WhatsApp Gateway Service:
1. Start the microservice: `node whatsapp-gateway/server.js` or via systemd (`kalimera-whatsapp.service`).
2. Link your WhatsApp number using pairing code via `/pair?number=<YOUR_PHONE>`.

### 3. CRM Endpoint Configuration:
The system synchronizes with the Kalimera CRM REST API:
```http
POST https://kalimera.lexmation.com/crm/api.php?action=webhook_update
GET  https://kalimera.lexmation.com/crm/api.php?action=get_conversation&phone=<PHONE>
POST https://kalimera.lexmation.com/crm/api.php?action=append_conversation
```

---

## 8. About Lexmation

Architected and deployed by **Lexmation** ([lexmation.com](https://lexmation.com)) — Custom AI Agents, Enterprise Automation Pipelines, and Sovereign CRM Infrastructure.
