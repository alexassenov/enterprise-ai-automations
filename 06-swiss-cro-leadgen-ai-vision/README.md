# Autonomous B2B Lead Gen & AI Visual CRO Audit Pipeline
*Enterprise Architecture, Visual AI Audits & Automated Outreach Engine • Lexmation Production Release*

> **Platform:** Python 3.12, Playwright Headless Engine, PHP 8.3 FastCGI, Tailwind CSS  
> **Live Production Review Portal:** [joschua.lexmation.com](https://joschua.lexmation.com)  
> **Integration Ecosystem:** Anthropic Claude 3.5 Sonnet Vision, Official Google PageSpeed Insights (Lighthouse) API, Google Maps Local Graph, MySQL Database  
> **Client & Market:** Swiss Web & Performance Agency (Joschua R. / Zurich, Switzerland)  
> **Architecture & Delivery:** Alex Assenov / Lexmation ([lexmation.com](https://lexmation.com))  

---

## 1. Executive Summary & Business Context

Plumbing, heating, and emergency services in major metropolitan hubs like Zurich invest heavily in Google Ads, often paying **$15–$45 per click**. However, over 70% of these mobile landing pages suffer from critical performance bottlenecks, outdated layouts, broken UI components, and friction-filled contact pathways.

The **Swiss CRO Lead Gen & AI Visual Audit Pipeline** is an automated client acquisition machine designed to discover, audit, and convert these advertisers into high-ticket website redesign ($3,000–$10,000 CHF) and CRO optimization contracts.

Instead of sending generic automated cold templates, this pipeline:
1. **Identifies Live Advertisers:** Targets companies actively spending advertising capital on high-intent keywords.
2. **Captures Full High-Res Visuals:** Generates pixel-perfect full-page mobile (390px iPhone viewport) and desktop (1280px) screenshots using a headless browser.
3. **Executes Official Technical Audits:** Queries the official Google PageSpeed Insights (Lighthouse) API under simulated mobile 4G conditions, calculating true Core Web Vitals (LCP, FCP, Speed Index, Total Blocking Time) and byte-level technical waste.
4. **Applies Multimodal AI Visual Auditing:** Passes high-res mobile page screenshots directly to Claude 3.5 Sonnet Vision alongside technical diagnostics. The AI performs an aesthetic and conversion evaluation across 6 strict criteria, assigning a concrete **Design Rating (1–10)** and identifying specific visual bugs.
5. **Crafts Personalized Native Swiss Outreach:** Generates personalized outreach pitches in Swiss-German following a proven agency template: a standard framework with a dynamic middle highlighting the business's single most critical conversion blocker.
6. **Delivers a Client Review Portal:** A real-time web portal deployed at `joschua.lexmation.com` featuring bilingual audits (DE/EN), interactive mobile/desktop screenshot inspectors, 1-click pitch copying, and CSV export.

---

## 2. End-to-End System Architecture

```text
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      1. TARGET DISCOVERY (GOOGLE ADS)                  │
 │   - High-Intent Queries: "Sanitär Notfall Zürich", "Kanalreinigung"    │
 │   - Sourced: Landing Page URL, Verified Phone, Physical Address        │
 └───────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │              2. DUAL-VIEWPORT SCREENSHOT ENGINE (PLAYWRIGHT)           │
 │   - Mobile Viewport: 390x844 (iPhone 14 / Mobile First Ads Landing)    │
 │   - Desktop Viewport: 1280x800                                         │
 │   - Network & DOM Analysis: Tel link count, DOM node complexity        │
 └───────────────────┬───────────────────────────────┬────────────────────┘
                     │                               │
                     ▼                               ▼
 ┌──────────────────────────────────────┐ ┌───────────────────────────────┐
 │   3. OFFICIAL GOOGLE PAGESPEED API   │ │   4. MULTIMODAL CLAUDE VISION │
 │   - Mobile 4G Performance (0-100)    │ │   - Base64 Full-Page Capture  │
 │   - Core Web Vitals: LCP, FCP, TBT   │ │   - 6 Core Audit Pillars      │
 │   - SEO, Accessibility, Best Practice│ │   - Design Rating: 1 - 10     │
 │   - Unused JS/CSS & Image Savings    │ │   - Visual Bug Detection      │
 └───────────────────┬──────────────────┘ └──────────────┬────────────────┘
                     │                                   │
                     └─────────────────┬─────────────────┘
                                       │
                                       ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │             5. BILINGUAL OUTREACH GENERATION (DE / EN)                 │
 │   - Standard Swiss Agency Intro & Outro                                │
 │   - Dynamic Middle: 2-3 Concrete, Evidence-Based Visual/CRO Flaws      │
 │   - Low-Friction CTA (Unverbindliche Ansätze)                          │
 └─────────────────────────────────────┬──────────────────────────────────┘
                                       │
                                       ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │            6. PRIVATE CLIENT REVIEW PORTAL & DATABASE (MYSQL)          │
 │   - Live at: https://joschua.lexmation.com                             │
 │   - Side-by-side Mobile & Desktop High-Res Screenshot Viewer           │
 │   - 1-Click DE/EN Language Toggle                                      │
 │   - 1-Click CSV Export for Direct Outreach Campaigns                   │
 └────────────────────────────────────────────────────────────────────────┘
```

---

## 3. The 6 Core Audit Pillars

| Pillar | Measured Attributes | Business / Redesign Trigger |
|---|---|---|
| **1. Outdated Websites** | Outdated typography, 2010s layouts, lack of modern CSS structures, legacy tables | Triggers complete website redesign / relaunch pitch |
| **2. Missing Updates & Security** | Outdated copyright years (e.g., © 2017), missing SSL/HTTPS trust signals | Triggers trust & modern technical compliance offer |
| **3. Bugs & Technical Glitches** | Broken image links, empty logo/partner tiles, misaligned elements | Concrete proof of broken user experience |
| **4. Poor Design (Rating 1–10)** | Cluttered hierarchy, poor color contrast, lack of mobile-first layout | AI assigns concrete rating (e.g. *5/10 – High Redesign Potential*) |
| **5. Performance & Ad Waste** | Real Google Mobile 4G score, LCP > 3.5s, large unoptimized assets | Proves that mobile paid traffic bounces before page renders |
| **6. Conversion Optimization (CRO)**| Call-to-action visibility above the fold, multi-step contact friction | Direct pitch for high-converting emergency landing pages |

---

## 4. Repository Structure

```text
06-swiss-cro-leadgen-ai-vision/
├── README.md                          # Enterprise operations & architecture documentation
├── pipeline/
│   ├── lead_pipeline.py               # End-to-end Python engine (Playwright, Google API, Claude Vision)
│   ├── requirements.txt               # Python package dependencies
│   ├── schema.sql                     # Production MySQL schema
│   └── .env.example                   # Environment configuration template
└── portal/
    ├── index.php                      # Full-featured client dashboard & audit modal
    ├── login.php                      # Authentication gateway
    ├── logout.php                     # Session termination
    ├── export.php                     # UTF-8 BOM CSV export module
    ├── update_status.php              # Lead state progression endpoint (new, reviewed, contacted)
    └── config.example.php             # Production database & session template
```

---

## 5. Deployment & Configuration

### Prerequisites
* Python 3.10+ with `playwright` (`playwright install chromium`)
* PHP 8.1+ with PDO MySQL and FastCGI
* MySQL 8.0+ / MariaDB
* Google Cloud Console API Key with *PageSpeed Insights API* enabled
* Anthropic API Key (Claude 3.5 Sonnet)

### Pipeline Setup
```bash
cd pipeline/
pip install -r requirements.txt
playwright install chromium
cp .env.example .env
# Configure your ANTHROPIC_API_KEY, GOOGLE_PAGESPEED_API_KEY, and DB credentials in .env
python lead_pipeline.py
```

### Portal Setup
```bash
cd portal/
cp config.example.php config.php
# Configure DB credentials and PORTAL_PASSWORD in config.php
# Point Nginx / Apache root to portal/ with /screenshots directory writable
```

---

## 6. Live Deliverables & Verification

* **Live Review Portal:** [joschua.lexmation.com](https://joschua.lexmation.com)  
* **Production Dataset:** 15 active Zurich Google Ads advertisers fully profiled and visually audited.
* **Lead Record Sample:**
  * **Business:** Kanal One GmbH (Kanalreinigung Zurich)
  * **Google PageSpeed:** 76/100 (LCP: 4.2s, FCP: 2.7s, TBT: 140ms, Unused JS: 233 KiB)
  * **Google Rating:** ★ 4.9 (45 reviews)
  * **Design Rating:** 5/10 (High Redesign Potential)
  * **Identified Visual Flaw:** Empty placeholder tiles in the partner section on mobile, excessive page length, contact CTA detached from emergency services.
  * **Generated Pitch:** 100% human Swiss-German personalized outreach ready to send.
