# Autonomous Stock Intelligence & Multi-Timeframe Quant Alerter — Step-by-Step Configuration & Operations Guide
*Enterprise Configuration, Architecture & Operations Manual • Lexmation Production Release*

> **Workflow Name:** Stock Multi-Timeframe & News Intelligence Alerter  
> **Workflow ID:** `Pwh5dGkS9VCUECF5` (Execution Engine) & `DAES6UzkjhCTIEVO` (Market Brief Engine)  
> **Platform:** n8n (v1.x+), Python 3.10+, PHP 8.x (FastCGI), Nginx  
> **Live Web Terminal:** [stocks.lexmation.com](https://stocks.lexmation.com)  
> **Integration Ecosystem:** n8n, Python RAG Engine, OpenAI (GPT-4o) / Claude 3.5 Sonnet, Telegram Bot, Financial Market Feeds, Web Terminal Dashboard  
> **Architecture & Delivery:** Lexmation ([lexmation.com](https://lexmation.com))  

---

## 1. Executive Summary & Architecture

The **Lexmation Autonomous Stock Terminal & Intelligence Alerter** is an enterprise-grade quantitative AI trading system. It continuously ingests multi-timeframe price action, macroeconomic data feeds, breaking financial news, and institutional order-flow signals.

Unlike basic indicator bots, the system incorporates a **Live RAG (Retrieval-Augmented Generation) Memory Engine**, which maintains persistent historical trade memory, evaluates prior ticker outcomes (win rates, realized PnL in R-multiples), tracks news catalyst continuity, and enforces strict institutional risk management rules (2% / 1R rule on simulated portfolio equity).

### End-to-End System Architecture

```text
[Cron / Market Hours Trigger: 09:00 AM - 16:30 PM EST]
                           │
                           ▼
     [Financial Market Feeds & Intraday Ingestion]
     (Live Quotes, Volume Multipliers, Technical Indicators)
                           │
                           ▼
         [Python RAG Context Retrieval Engine]
  ┌────────────────────────┴────────────────────────┐
  ▼                                                 ▼
[Historical Trade Memory & Win Rates]     [Macro & Market Regime Context]
(Past PnL, Ticker Track Record)           (SPY 20 EMA, VIX, Sector Heatmap)
  └────────────────────────┬────────────────────────┘
                           │
                           ▼
          [Chief Trading Strategist LLM Agent]
  (Dual Prompt Architecture: Macro Synthesis + Action Plan)
                           │
                           ▼
        [Deterministic JSON Schema Validator]
  (Enforces strict numeric stops, targets, R:R and rationale)
                           │
                           ▼
          [Python Quant Sync & EOD Evaluator]
  ┌────────────────────────┴────────────────────────┐
  ▼                                                 ▼
[Telegram Priority Broadcast]             [Web Terminal API & Live DB]
(Instant BUY/SELL/WATCH alerts)          (stocks.lexmation.com JSON Feeds)
                                                    │
                                                    ▼
                                     [Interactive AI Chatbot Terminal]
                                     (Live RAG Chatbot: chat_api.php)
```

---

## 2. Ecosystem Matrix & Required Credentials

Ensure the following components and API credentials are configured in your n8n workspace and server environment:

| Service / Component | Role in Architecture | Required Credential / Setup | Environment / Node |
| :--- | :--- | :--- | :--- |
| **n8n Automation Engine** | Workflow orchestrator, data polling & LLM flow | Cloud or self-hosted (v1.x+) | `Schedule Trigger`, `HTTP Requests` |
| **Python 3.10+ Backend** | Quant analysis, EOD evaluation, RAG memory | Server local virtualenv | `sync_n8n.py`, `rag_engine.py` |
| **OpenAI / Anthropic** | Chief Trading Strategist LLM & Macro synthesis | API Key (`GPT-4o` / `Claude 3.5 Sonnet`) | `AI Agent / LLM Chat Node` |
| **Financial Data APIs** | Intraday price data, candles, volume | Polygon.io / Finnhub / Yahoo Finance | `Get Ticker Quotes & Indicators` |
| **Telegram Bot API** | Instant high-priority trade alerts to traders | Telegram Bot Token & Target Chat ID | `Telegram Alert Dispatcher` |
| **Nginx & PHP 8.x** | Hosts the live web terminal & streaming AI chat | FastCGI (`stocks.lexmation.com`) | `index.php`, `chat_api.php` |

---

## 3. Step 1: Workflow Import & Timezone Setup

1. In n8n, navigate to **Workflows** ➔ Click **Add Workflow (+)**.
2. Click the top-right **three dots menu (`...`)** ➔ Select **Import from File**.
3. Select `stock_alerter_workflow.json` (ID: `Pwh5dGkS9VCUECF5`) and `market_brief_workflow.json` (ID: `DAES6UzkjhCTIEVO`).
4. **Timezone Setting (MANDATORY):**
   - Click the **Workflow Settings** (gear icon) in the canvas top bar.
   - Set **Timezone** to `America/New_York (EST/EDT)`.
   - The US stock market operates 09:30 AM to 16:00 PM EST. Setting this timezone guarantees that pre-market briefs and trading alerts align with Wall Street trading sessions.

---

## 4. Step 2: The RAG Context & Historical Memory Engine (`rag_engine.py`)

The RAG engine is located at `/var/www/stocks/rag_engine.py` and `/home/ubuntu/stocks.lexmation.com/rag_engine.py`. Before any ticker is analyzed by the LLM, the system dynamically retrieves 4 contextual layers:

### Layer 1: Historical Ticker Performance Memory
* Retrieves past scans for the ticker from `trades.json`.
* Calculates exact historical metrics: **Total Scans, Completed Trades, Win Rate %, and Net Realized PnL in R-multiples**.
* Injects recent trade history (e.g. *"CRDO: Stopped out at -$20.00 (-1.0R) due to late-session whipsaw"*).

### Layer 2: News Catalyst Continuity
* Compares incoming headlines against `news.json`.
* Detects if the catalyst is fresh breaking news or a recurring story that is already priced into the stock.

### Layer 3: Global Macro Regime
* Ingests the latest `market_brief.json` output:
  * Overall Market Sentiment (`BULLISH`, `BEARISH`, `NEUTRAL`).
  * Sector Heatmap & Hot Sectors.
  * Chief Strategist directives (e.g., *"Move open stops to Breakeven, reduce position size on high-beta tech"*).

### Layer 4: Institutional Risk Execution Playbook
* Enforces hard risk rules directly inside the prompt context:
  1. Minimum **2.0% stop buffer** under structure if previous trade suffered a whipsaw.
  2. Relative Volume (**RVOL >= 1.5x**) mandatory before issuing active BUY/SELL on recurring headlines.
  3. Strict trigger levels at verified support/resistance for `WATCH` signals.

---

## 5. Step 3: Quant Backtesting & EOD Outcome Evaluator (`sync_n8n.py`)

The sync engine (`sync_n8n.py`) runs periodically via cron to audit historical performance and update live JSON feeds:

### Institutional Money Management Constants ($1,000 Model Portfolio):
```python
INITIAL_CAPITAL = 1000.0          # Starting Account Size ($)
POSITION_SIZE_DOLLARS = 200.0     # 20% Capital Allocation per Trade
RISK_PER_TRADE_PERCENT = 2.0      # Institutional 2% Risk Rule
RISK_PER_TRADE_DOLLARS = 20.0     # 1R = $20.00 Max Risk per Trade
```

### Evaluation Logic:
* Pulls actual intraday 1-minute and 5-minute candle data for each active trade.
* Evaluates whether **Target Price (Take Profit)** or **Stop Loss** was struck first.
* Calculates:
  * **Win Rate %** & **Profit Factor**.
  * **Net Realized PnL** in dollars and in R-multiples ($1R = $20).
  * **Daily PnL Calendar** (Green vs. Red days).
  * Generates `equity_curve.json` for live charting.

---

## 6. Step 4: AI Decision Framework & Structured JSON Taxonomy

The Chief Trading Strategist outputs deterministic, schema-enforced payloads:

| Field | Type | Description / Operational Standard |
| :--- | :--- | :--- |
| `ticker` | String | US Stock Symbol (e.g. `AAPL`, `CRDO`, `MU`, `APP`). |
| `verdict` | String | `BUY` (Immediate entry), `SELL` (Short entry), `WATCH` (Pending breakout trigger), `AVOID`. |
| `setup_grade` | String | `A+` (Elite multi-timeframe confluence), `A`, `B`, `C`. |
| `confidence` | Integer | 0 to 100 confidence score based on technical + catalyst alignment. |
| `trigger_price` | Float | Exact dollar price required to activate the trade. |
| `stop_loss` | Float | Structural invalidation price (strictly enforced). |
| `target_price` | Float | Primary Take-Profit target yielding minimum **1:2 Risk/Reward**. |
| `expected_rr` | String | Formatted Risk-to-Reward ratio (e.g. `1:2.5`). |
| `pnl_r` | Float | Realized or expected return in R units. |
| `key_catalyst` | String | Concise summary of the fundamental/news catalyst driving the setup. |

---

## 7. Step 5: Multi-Channel Alerting & Web Terminal Integration

### 1. Telegram Dispatch Template
High-confidence setups (`Score >= 75`, `Grade A/A+`) trigger an instant Telegram push:
```text
🚨 LEXMATION QUANT ALERT: $CRDO
━━━━━━━━━━━━━━━━━━━━
🎯 Verdict: BUY (Grade A+ | Conf: 85%)
💵 Entry Trigger: $128.50
🛑 Stop Loss: $124.00 (Risk: -$20.00 / 1R)
🎯 Target 1: $137.50 (Reward: +$40.00 / 2R)
⚖️ R:R Ratio: 1:2.0
📰 Catalyst: Q3 Earnings beat with +24% Datacenter revenue guidance.
🧠 RAG Note: Historical Win Rate on ticker: 66.7% (+2.1R net).
```

### 2. Live Web Terminal & AI Chatbot (`stocks.lexmation.com`)
* **Terminal Interface:** Built with high-performance responsive UI displaying the Live Portfolio Balance, ROI %, Win Rate, Daily PnL Calendar, and Active Trade Cards.
* **Streaming AI Chatbot (`chat_api.php`):** Powered by GPT-4o with real-time RAG context. Allows users to query:
  * *"What is our open exposure right now?"*
  * *"Why did we enter APP and what is our stop level?"*
  * *"Show me our performance track record on semiconductor stocks."*

---

## 8. Step 6: Testing, Verification & Operations

### 1. Testing n8n Workflows:
* Open workflow `Pwh5dGkS9VCUECF5` in n8n.
* Click **Test Step** on the initial market ingestion node.
* Verify that the LLM node returns valid JSON without markdown wrapping (` ```json `).

### 2. Testing the Quant Sync & RAG Engine:
Run the synchronization script directly from the server:
```bash
python3 /home/ubuntu/stocks.lexmation.com/sync_n8n.py
```
Expected output:
```text
Fetching executions from n8n API...
Processing historical trades...
Evaluating EOD candle outcomes...
Stats generated: Portfolio Balance: $1,213.76 | Total PnL: +$213.76 (+21.4% ROI).
Sync complete. Data feeds updated in /var/www/stocks/data/.
```

### 3. Verify Live Web Feeds:
Verify that JSON files in `/var/www/stocks/data/` are updated:
```bash
ls -lh /var/www/stocks/data/
# trades.json, stats.json, news.json, market_brief.json
```

---

## 9. 14-Day Complimentary Support & Warranty

> **2-WEEK POST-DEPLOYMENT HYPERCARE INCLUDED**  
> Your quantitative automation setup includes **14 days (2 full weeks) of complimentary engineering support** starting from deployment.  
> 
> **What is covered:**
> - Calibration of indicator parameters, scan intervals, and cron timings.
> - LLM prompt fine-tuning for custom trading strategies and risk tolerances.
> - Telegram Bot and Webhook integration testing.
> - Assistance with server deployment, Nginx FastCGI configuration, and domain routing.
> 
> For any technical assistance or feature requests, contact our engineering desk directly.

---

## 10. About Lexmation — AI SaaS & Automation Agency

This quantitative terminal and automation infrastructure was designed and deployed by **Lexmation**, an elite AI SaaS & Digital Engineering Agency specializing in autonomous multi-agent systems, institutional quantitative workflows, and sovereign cloud infrastructure.

| Service Line | Capabilities & Technologies | Business Impact |
| :--- | :--- | :--- |
| **Quantitative AI & Trading Bots** | Multi-timeframe algorithmic models, automated EOD backtesting, RAG trade memory | Institutional-grade decision intelligence & disciplined risk execution |
| **Autonomous AI Agents** | LangGraph, CrewAI, multi-agent validation loops, tool use | Complete replacement of repetitive analytical workflows |
| **n8n Enterprise Automation** | Resilient ETL pipelines, webhook architecture, error-recovery | Enterprise reliability with zero monthly software seat fees |
| **Custom AI Terminals & SaaS** | Custom web dashboards, streaming LLM chat with RAG, Vector DBs | Proprietary internal tools and branded client-facing portals |

Explore our enterprise solutions and client case studies at: **[https://lexmation.com](https://lexmation.com)**

---

## 11. 📋 Changelog & Operations Log

A complete chronological history of all bug fixes, engine optimizations, and architectural enhancements is maintained in **[CHANGELOG.md](./CHANGELOG.md)**.

* **Latest Release:** `[2026-10-02]` — EOD Workflow Overhaul, Post-Scan Daily Summary in Telegram & Live Web Audit Dashboard.
