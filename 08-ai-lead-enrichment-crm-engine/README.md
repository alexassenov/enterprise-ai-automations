# Autonomous AI Inbound Lead Enrichment, Dynamic Pitch & Follow-Up Engine
*Enterprise Inbound SDR Automation, Autonomous Web Scraping, Multi-Channel Dispatch & Thread-Aware Follow-Up Engine*

> **Workflow Name:** `AI Lead contact`  
> **Workflow ID:** `hbecMXNJx4PozV0r`  
> **Platform:** n8n (v1.x+)  
> **Core Stack:** `n8n`, `LangChain AI Agent`, `OpenAI GPT-4o`, `Gmail API`, `Slack API`, `Telegram Bot API`, `Google Sheets CRM`  
> **Architecture & Engineering:** Alex Assenov  

---

## 1. Executive Summary & Problem Context

Speed to lead and depth of personalization determine B2B conversion rates. According to industry benchmarks, responding to an inbound inquiry within **5 minutes** increases qualification rates by **9x**, while generic template responses result in immediate disengagement.

However, manually researching a prospect's website, diagnosing their specific bottleneck, drafting a customized technical proposal, and remembering to follow up 72 hours later requires substantial human bandwidth.

The **Autonomous AI Inbound Lead Enrichment & Follow-Up Engine** automates the entire inbound sales development representative (SDR) lifecycle:
1. **Instant Webhook Ingestion:** Captures prospect inquiries the second they submit a contact or consultation form.
2. **Deep Real-Time Context Scraping:** Autonomously crawls the prospect’s target website, sanitizes the raw HTML, and extracts core business positioning.
3. **Contextual AI Proposal Generation:** A LangChain AI agent cross-references the prospect's stated pain point with their live website data to formulate a tailored, hyper-specific solution pitch.
4. **Instant Multi-Channel Internal Alerts:** Dispatches full executive intelligence summaries to **Slack**, **Telegram**, and **Admin Email**, while logging structured records to Google Sheets CRM.
5. **Thread-Aware Autonomous Follow-Up:** Suspends execution for 72 hours, monitors the Gmail conversation thread for client responses, and automatically fires a contextual follow-up sequence if no reply is detected.

---

## 2. End-to-End System Architecture

```text
 ┌────────────────────────────────────────────────────────────────────────┐
 │                   STAGE 1: REAL-TIME LEAD ENRICHMENT                   │
 │  - Inbound Lead Webhook (Name, Email, Stated Problem, Website URL)     │
 │  - Headless HTTP Scraper crawls prospect's domain                      │
 │  - JavaScript Sanitizer cleans raw HTML into high-signal markdown text │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                 STAGE 2: AI STRATEGY & PROPOSAL ENGINE                 │
 │  - LangChain AI Agent (`gpt-4o`) evaluates:                            │
 │      * Stated bottleneck / goal                                        │
 │      * Live website context & company offerings                        │
 │  - Generates bespoke value proposition & concrete technical solution   │
 │  - Logs full case & structured payload into Google Sheets CRM          │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │           STAGE 3: DUAL-DISPATCH & MULTI-CHANNEL INTERNAL SYNC         │
 │  - Outbound Prospect Delivery: Dispatches personalized pitch via Gmail │
 │  - Internal Ops Intelligence: Pushes real-time alerts to:              │
 │      * Dedicated Slack Channel (`#inbound-leads`)                      │
 │      * Encrypted Telegram Ops Bot                                      │
 │      * Executive Admin Email Summary                                   │
 │  - Merges and verifies delivery status across all branches             │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │              STAGE 4: THREAD-AWARE AUTONOMOUS FOLLOW-UP                │
 │  - 72-Hour Execution Pause (`Wait` node)                               │
 │  - Thread Inspection via Gmail API: Did the client reply?               │
 │           ┌───────────────────────┴───────────────────────┐            │
 │           ▼                                               ▼            │
 │      [Client Replied]                             [No Reply Detected]  │
 │  - Mark Lead as "Engaged"                 - Auto-send Follow-Up Pitch  │
 │  - Alert team for human sales handover    - Update CRM status to Nurture
 └────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Core Architectural Highlights

### A. Real-Time Domain Scraping & Context Injection
Unlike standard CRM forms that rely solely on what the user types, this pipeline immediately scrapes the prospect's root domain:
* Normalizes URLs and handles redirects.
* Cleans scripts, styles, and boilerplate tags via custom JavaScript node (`Clean Scraped Text`).
* Injects clean contextual brand data directly into the AI prompt window, allowing the AI to understand exactly what the client sells and who their customers are.

### B. Intelligent AI Proposal Drafting
* Powered by LangChain Agent and OpenAI `gpt-4o`.
* Avoids generic sales fluff by adhering to strict prompt guidelines:
  1. Acknowledges the specific obstacle described in the form.
  2. References 1–2 exact features or services found on their live website.
  3. Presents a clear 3-step automation or AI roadmap tailored to their operations.
  4. Includes a low-friction call-to-action (e.g., booking a 15-minute architecture call).

### C. Multi-Channel Team Awareness
* **Slack & Telegram:** Sales engineers and leadership receive immediate mobile push notifications with lead name, website, stated problem, and the exact pitch that was sent.
* **Google Sheets CRM:** Acts as a lightweight, accessible database tracking lead source, timestamp, generated strategy, and interaction status.

### D. Zero-Human Follow-Up Engine
* Standard sales reps forget follow-ups up to 48% of the time.
* The workflow utilizes a persistent **72-hour delay** state.
* Upon resumption, it queries the Gmail thread using message/thread IDs.
* If a reply from the prospect exists, the automation gracefully terminates and marks the thread active. If silent, a non-intrusive, value-adding follow-up message is dispatched automatically.

---

## 4. Key Node Breakdown in n8n

| Node Name | Node Type | Purpose |
|---|---|---|
| `Webhook Trigger` | `n8n-nodes-base.webhook` | Ingestion endpoint receiving JSON payload from lead capture forms |
| `HTTP Request Scraper` | `n8n-nodes-base.httpRequest` | Fetches raw HTML from the submitted prospect website URL |
| `Clean Scraped Text` | `n8n-nodes-base.code` | Strips tags, navigation noise, and extracts clean textual context |
| `AI Agent` | `@n8n/langchain.agent` | LangChain autonomous reasoning agent constructing the custom pitch |
| `OpenAI Chat Model` | `@n8n/langchain.lmChatOpenAi` | LLM backbone (`gpt-4o`) generating human-grade proposals |
| `Format Lead Output` | `n8n-nodes-base.set` | Enforces structured data schema for downstream delivery |
| `Google Sheets Log` | `n8n-nodes-base.googleSheets` | Appends lead profile, scraped data, and AI pitch to CRM sheet |
| `Slack Alert` | `n8n-nodes-base.slack` | Real-time notification to internal Slack channel |
| `Telegram` | `n8n-nodes-base.telegram` | Instant mobile alert to leadership Telegram bot |
| `Send Pitch to Client` | `n8n-nodes-base.gmail` | Delivers initial personalized proposal to prospect inbox |
| `Send Email to Admin` | `n8n-nodes-base.gmail` | Sends complete case file and dispatch confirmation to admin |
| `Wait` | `n8n-nodes-base.wait` | Non-blocking 72-hour execution pause |
| `Check Client Reply` | `n8n-nodes-base.gmail` | Inspects thread message count to detect incoming customer response |
| `If (Has Replied?)` | `n8n-nodes-base.if` | Conditional branch routing based on customer engagement |
| `Send Follow-up Email` | `n8n-nodes-base.gmail` | Dispatches follow-up touchpoint if thread remains unanswered |

---

## 5. Security & Production Best Practices

* **No Hardcoded Secrets:** All OAuth tokens for Gmail, Slack Webhooks, Telegram Bot Tokens, and OpenAI API keys are isolated in n8n credential managers.
* **Non-Blocking Persistence:** The 72-hour wait state uses n8n's internal database execution storage, ensuring full resilience against server reboots or container restarts.
* **Rate-Limit Safe:** Domain scraping enforces short timeouts and safe user-agent headers to prevent hanging executions.

---

## 6. How to Deploy & Configure

1. Import `workflow.json` into your n8n workspace.
2. Connect your credentials:
   - **OpenAI API**
   - **Gmail OAuth2**
   - **Slack API / Webhook**
   - **Telegram API**
   - **Google Sheets OAuth2**
3. Configure the `Webhook Trigger` URL on your website contact form.
4. Activate the workflow.
