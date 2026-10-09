# 05: Web Landing Page & Automated AI SEO Blog Engine (Dr. V Meditation)

Production web architecture and automated content engine built for **Dr. V Meditation & Healing** (`https://drvmeditation.com`), designed to capture organic search traffic and drive mobile app downloads on iOS & Android.

---

## 🌟 Overview & Business Objective
- **Client**: Dr. V Meditation & Healing (`drvmeditation.com`)
- **Deliverables**:
  1. High-converting, responsive landing page showcasing the mobile app, guided meditation sessions, and *The Three Pillars* book.
  2. Automated AI SEO Content Engine (via n8n) generating long-form articles, structured data, and dynamic CTAs.
  3. Cloudflare Edge DNS, SSL/TLS full termination, and dedicated private cloud server deployment.
  4. Google Search Console setup, XML sitemaps, and robots.txt.

---

## 🏗️ Architecture & Tech Stack

```
   [Google Search / Users]
             │
             ▼
      [Cloudflare CDN & SSL]
             │
             ▼
     [Nginx Web Server (Ubuntu VPS)]
             │
     ┌───────┴───────────────────────┐
     │                               │
     ▼                               ▼
[Static Web Landing Page]    [Automated AI Blog Engine]
(Tailwind CSS, Fast Core)    (n8n Workflows + AI Writers)
```

- **Frontend**: Lightweight semantic HTML5, modern Tailwind CSS, responsive mobile-first UI.
- **DNS & CDN**: Cloudflare Proxied DNS with Edge SSL (HTTP/2 / HTTP/3).
- **Web Server**: Nginx with Let's Encrypt / Cloudflare SSL termination.
- **Automation Pipeline**: n8n workflows orchestrating automated keyword research, AI article generation, schema injection, and sitemap updates.

---

## 📂 Project Structure
```
05-ai-seo-blog-engine-drvmeditation/
├── index.html                   # Core web landing page
├── README.md                    # Project documentation & specs
├── workflows/                   # n8n AI SEO automated pipelines (WIP)
└── blog/                        # Blog templates and article engine
```

---

## 🚀 Deployment & Operations
- **Live URL**: [https://drvmeditation.com](https://drvmeditation.com)
- **Local Web Root**: `/var/www/drvmeditation/`
- **Nginx Config**: `/etc/nginx/sites-available/drvmeditation.com`
