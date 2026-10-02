# Smart Job Monitor & Lead Notification Pipeline

> **Platform:** n8n | Webhooks | HTML Template Engine  
> **Domain:** Lead Generation, Talent Acquisition, Web Scraping & Alerting  
> **Author:** Alex Assenov (Lexmation)

---

## 1. Executive Summary

This workflow automates the real-time monitoring and scraping of new listings from major job boards (Jobs.bg). It parses unstructured web listings, extracts key attributes (company, position, requirements, salary ranges, location), and compiles them into beautifully formatted, responsive HTML email digests.

## 2. Key Capabilities

- **Automated Polling & Extraction:** Periodically scrapes and filters target keyword queries with anti-blocking headers.
- **Deduplication Engine:** Compares incoming postings against existing records to prevent duplicate notifications.
- **Dynamic HTML Email Templates:** Compiles job cards with modern CSS styling, badges, and quick-apply action buttons.
- **Instant Dispatch:** Delivers clean email digests via SMTP / Gmail node immediately upon detection of high-value matches.

## 3. Workflow Architecture

```text
[Cron Trigger / Interval Poll]
               │
               ▼
[HTTP Request: Fetch Search Listings]
               │
               ▼
[HTML / JSON Parser & Attribute Extraction]
               │
               ▼
[Deduplication Check]
               │
               ▼
[HTML Digest Generator (jobs_bg_pretty_email)]
               │
               ▼
[SMTP / Email Delivery]
```
