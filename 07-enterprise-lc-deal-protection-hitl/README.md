# Enterprise Letter of Credit (LC) Deal Protection & Human-in-the-Loop (HITL) AI System
*Autonomous Dual-Agent Contract Risk Auditing, Manager Governance & Document Delivery Engine • Enterprise Production Architecture*

> **Workflow Name:** `alex_LC Deal Protection - Google Form Sheet Intake`  
> **Workflow ID:** `dOxoVfiVL0XS64xg`  
> **Platform:** n8n (v1.x+)  
> **Core Stack:** `n8n`, `Gemini 3.1 Pro Preview`, `Gemini 2.5 Flash`, `Supabase`, `Google Workspace` (Sheets, Drive, Docs, Gmail)  
> **Architecture & Engineering:** Alex Assenov  

---

## 1. Executive Summary & Problem Context

In international trade finance, **Letters of Credit (LCs)** and high-value supply contracts represent millions of dollars in transaction liability. Ambiguous clauses, non-standard compliance terms, delivery mismatches, and jurisdiction loopholes regularly lead to payment freezes, discrepancy disputes, and catastrophic legal exposure.

Manual review by legal and trade finance executives is slow (24–72 hours per document), costly, and prone to human oversight during peak transaction volume. Conversely, naïve single-prompt AI deployments are plagued by hallucinations, missed legal nuances, and zero auditability.

The **Enterprise LC Deal Protection System** solves this through an end-to-end autonomous pipeline incorporating:
1. **Zero-Friction Ingestion:** Automated webhook/sheet ingestion of transaction briefs and contract PDFs.
2. **Dual-Agent Verification Engine:** An **Analyst Agent** (`Gemini 3.1 Pro`) discovers risks, while a separate **Critic Agent** (`Gemini 2.5 Flash`) independently cross-examines findings against original contract clauses to eliminate hallucinations and false flags.
3. **Strict Human-in-the-Loop (HITL) Governance:** High-risk financial documents are never dispatched without explicit human sign-off. Reviews occur in a synchronized Google Sheets queue with instant Supabase logging.
4. **Dynamic Prompt Decoupling:** Business rules and prompt definitions are managed externally in a dedicated configuration sheet, enabling domain experts to refine audit policies without altering n8n workflow logic.
5. **Automated Publishing & Delivery:** Approved audits are compiled into formal executive reports, dynamically rendered into Google Docs templates, converted to PDF, and dispatched via Gmail.

---

## 2. End-to-End System Architecture

```text
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      1. TRANSACTION INGESTION & TRIGGER                │
 │  - Deal Request Form / Webhook ➔ Google Sheets Queue Row Added         │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                  2. INGESTION, PARSING & DATABASE LOG                  │
 │  - Generate Unique Case ID (e.g., LC-20261126-A8K2)                    │
 │  - Download PDF from Google Drive ➔ Extract Raw Contract Text          │
 │  - Upsert Initial Record into Supabase (`status: PENDING_AI`)          │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                 3. DUAL-AGENT AI RISK EVALUATION ENGINE                │
 │                                                                        │
 │   ┌───────────────────────┐              ┌────────────────────────┐    │
 │   │  Agent 1: AI Analyst  │              │  Agent 2: AI Critic    │    │
 │   │ (Gemini 3.1 Pro)      │─────────────▶│ (Gemini 2.5 Flash)     │    │
 │   │ Extracts clause risks │              │ Audits for accuracy    │    │
 │   │ & payment penalties   │              │ & eliminates halluc.   │    │
 │   └───────────────────────┘              └────────────────────────┘    │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                 4. DATABASE SYNC & HUMAN-IN-THE-LOOP (HITL)            │
 │  - Save Audited Findings into Supabase (Permanent Traceability)        │
 │  - Push Structured Findings into Manager Review Queue (Google Sheets)  │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      5. MANAGER AUDIT & GATEWAY                        │
 │  - Manager evaluates AI findings, provides feedback & decision:        │
 │       [STATUS: APPROVED]       │      [STATUS: REJECTED]               │
 └──────────────────┬─────────────┴─────────────────┬─────────────────────┘
                    │                               │
           (Approved Branch)                 (Rejected Branch)
                    │                               │
                    ▼                               ▼
 ┌──────────────────────────────────────┐  ┌──────────────────────────────┐
 │  6. DOCUMENT GENERATION & DISPATCH   │  │ Abort Pipeline & Log         │
 │  - Parse findings via JS Normalizer  │  │ Rejection Reason to Supabase │
 │  - Populate Google Docs Template     │  └──────────────────────────────┘
 │  - Render to Final PDF               │
 │  - Email PDF via Gmail API           │
 │  - Update Supabase to COMPLETED      │
 └──────────────────────────────────────┘
```

---

## 3. Core Architectural Modules

### A. Dual-Agent Verification (Analyst + Critic Pattern)
* **Agent 1 (Lead Analyst - `Gemini 3.1 Pro Preview`):**
  - Consumes full legal text and specialized prompts fetched from the central prompt sheet.
  - Pinpoints non-conforming conditions, incoterm mismatches, payment trigger discrepancies, and jurisdiction hazards.
  - Outputs structured risk findings categorized by severity.
* **Agent 2 (Quality Critic - `Gemini 2.5 Flash`):**
  - Receives both the original raw document text and the Analyst’s output.
  - Validates citations and verifies whether identified liabilities actually exist in the text or are speculative hallucinations.
  - Flags edge cases and guarantees factual consistency before any human sees the report.

### B. Human-in-the-Loop (HITL) Governance Protocol
* **Review Queue (`Pending Review`):** Findings are pushed directly into a human-friendly spreadsheet.
* **Granular Oversight:** The reviewer has complete authority to edit ratings, add manual recommendations, or change status to `Approved` or `Rejected`.
* **RLHF & RAG Groundwork:** Every reviewer approval, rejection, and commentary is immediately synchronized back into Supabase (`Update a row`). This persistent audit trail acts as gold-standard training and retrieval data for future document models.

### C. Automated Document & PDF Delivery
* **Dynamic Template Population:** Uses an official corporate Google Docs template, cloning it via Google Drive API and performing deep placeholder token replacement.
* **High-Fidelity PDF Export:** Converted into a print-ready PDF with zero manual formatting overhead.
* **Stakeholder Dispatch:** Automatically attached to a branded email summary sent via Gmail API directly to the client or deal sponsor.

---

## 4. Key Node Breakdown in n8n

| Node Name | Node Type | Purpose |
|---|---|---|
| `Google Sheet Row Added` | `googleSheetsTrigger` | Listens for new deal submissions from web intake form |
| `Prepare Case Data` | `n8n-nodes-base.code` | Generates case ID `LC-YYYYMMDDHHMMSS-XXXX` and normalizes headers |
| `Download PDF` | `googleDrive` | Fetches uploaded legal PDF from Google Drive storage |
| `Extract PDF Text` | `extractFromFile` | Extracts structured text from multi-page PDF documents |
| `Create Supabase Report` | `supabase` | Logs initial case state (`status: PENDING_AI`) |
| `Get Prompts` | `googleSheets` | Dynamically retrieves the latest Analyst and Critic system prompts |
| `Gemini Analyst` | `@n8n/googleGemini` | Reasoning model (`gemini-3.1-pro-preview`) evaluating legal risks |
| `Gemini Critic` | `@n8n/googleGemini` | Auditor model (`gemini-2.5-flash`) verifying claims vs. ground truth |
| `Pending Review` | `googleSheets` | Adds case to manager review tab with AI summaries |
| `Human Review Trigger` | `googleSheetsTrigger` | Triggers downstream execution upon manager status change |
| `If (Decision Check)` | `n8n-nodes-base.if` | Gates dispatch: routes `Approved` to doc rendering, `Rejected` to log |
| `Copy file` | `googleDrive` | Duplicates master Google Docs template for clean report rendering |
| `Update a document` | `googleDocs` | Injects parsed JSON findings into template tags |
| `Send a message` | `gmail` | Delivers generated PDF report and executive summary |
| `Update a row1` | `supabase` | Finalizes case audit in Supabase with completion timestamp |

---

## 5. Security, Reliability & Compliance

1. **Zero Secret Hardcoding:** Environment-level credentials for Google Service Accounts, Supabase, and Google Gemini API.
2. **Defensive Rejection Branching:** If a human reviewer rejects a draft, the workflow halts instantly, preventing unverified or disputed analyses from ever being delivered.
3. **Decoupled Architecture:** Workflows use asynchronous triggers and resilient error catching, allowing documents of any size to process without timeout failures.

---

## 6. How to Deploy & Import

1. Import `workflow.json` into your self-hosted or cloud n8n instance.
2. Configure Credentials:
   - **Google Gemini API** (Google AI Studio)
   - **Google Workspace OAuth2 / Service Account** (Drive, Sheets, Docs, Gmail)
   - **Supabase API** (`supabase` connection URL & Key)
3. Connect your Google Sheet intake form and set up the `cases` and `audit_logs` tables in Supabase.
4. Activate the intake trigger and manager review trigger.
