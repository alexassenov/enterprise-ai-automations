# Stock Intelligence & Market Brief Alerter (RAG & Multi-Source Synthesis)

> **Platform:** n8n (v1.x+) | Python | LLMs  
> **Domain:** Financial Markets, Quantitative Trade Intelligence, Portfolio Monitoring  
> **Architecture & Delivery:** Alex Assenov (Lexmation)

---

## 1. Executive Summary

The **Stock Intelligence & Market Brief System** is an automated financial analysis pipeline designed for trading desks and portfolio managers. It synthesizes real-time equity market data, macroeconomic events, and corporate earnings into actionable, structured morning and end-of-day (EOD) market intelligence.

## 2. Key Capabilities

- **Macro & Geopolitical Synthesis:** Aggregates live market feeds, central bank decisions (Federal Reserve), and commodities (energy/oil).
- **RAG & Knowledge Integration:** Queries vectorized market context to correlate breaking news with historical price action.
- **Strict Structured JSON Outputs:** Guarantees deterministic output parsing without markdown wrapping or hallucinated dates.
- **Dynamic Watchlist Alerts:** Generates discrete action items (Stop-to-Breakeven, Risk-Off adjustments) for open ticker positions and pending order blocks.
- **Multi-Channel Dispatch:** Direct distribution to Telegram channels and private trading groups.

## 3. Workflow Architecture

```text
[Scheduled Trigger: Pre-Market & Market Close]
                    │
                    ▼
[Market Data Ingestion (Quotes, Earnings, News Feeds)]
                    │
                    ▼
[RAG Context Retrieval (Historical Context & Watchlists)]
                    │
                    ▼
[Chief Trading Strategist LLM Prompt Engine]
                    │
                    ▼
[JSON Validator & Output Formatter]
                    │
                    ▼
[Telegram / Webhook Real-Time Broadcast]
```

## 4. Setup & Deployment

1. Import `market_brief_workflow.json` and `stock_alerter_workflow.json` into your n8n workspace.
2. Configure credentials in n8n for your LLM provider (OpenAI / Anthropic) and Telegram Bot token.
3. Use `sync_brief_prompt.py` to programmatically update and version-control analyst prompts via the n8n REST API.
