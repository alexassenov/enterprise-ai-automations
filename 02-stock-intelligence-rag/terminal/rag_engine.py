#!/usr/bin/env python3
"""
RAG Engine for Lexmation Stock Terminal
Retrieves historical trade memory, catalyst continuity, and macro context for any ticker.
"""

import os
import json
from datetime import datetime, timezone

DATA_DIR = "/var/www/stocks/data"

def get_rag_context(ticker):
    ticker = str(ticker).strip().upper()
    
    # 1. Load data
    trades = []
    news = []
    brief = {}
    
    if os.path.exists(f"{DATA_DIR}/trades.json"):
        with open(f"{DATA_DIR}/trades.json") as f:
            trades = json.load(f)
            
    if os.path.exists(f"{DATA_DIR}/news.json"):
        with open(f"{DATA_DIR}/news.json") as f:
            news = json.load(f)
            
    if os.path.exists(f"{DATA_DIR}/market_brief.json"):
        with open(f"{DATA_DIR}/market_brief.json") as f:
            brief = json.load(f)

    # 2. Extract historical trades for this ticker
    ticker_trades = [t for t in trades if t.get("ticker") == ticker]
    
    # Calculate stats on this ticker
    completed = [t for t in ticker_trades if any(k in str(t.get("outcome", "")) for k in ["WIN", "LOSS"])]
    wins = [t for t in completed if "WIN" in str(t.get("outcome", ""))]
    losses = [t for t in completed if "LOSS" in str(t.get("outcome", ""))]
    pending = [t for t in ticker_trades if "PENDING" in str(t.get("outcome", ""))]
    
    win_rate = (len(wins) / len(completed) * 100) if completed else 0.0
    total_r = sum(float(t.get("pnl_r", 0) or 0) for t in completed)
    
    # 3. Format Historical Performance
    mem_lines = []
    mem_lines.append(f"=== HISTORICAL TRADE MEMORY FOR {ticker} ===")
    if ticker_trades:
        mem_lines.append(f"• Past Scans: {len(ticker_trades)} total | Completed: {len(completed)} (Wins: {len(wins)}, Losses: {len(losses)}) | Historical Win Rate: {win_rate:.1f}% | Net Realized PnL: {total_r:+.2f}R")
        mem_lines.append("• Recent Track Record:")
        for t in ticker_trades[:3]:
            dt = t.get("date_time", "")[:16]
            v = t.get("verdict", "")
            sc = t.get("ai_score", 0)
            out = t.get("outcome", "")
            r = t.get("pnl_r", 0)
            trig = t.get("trigger_price", 0)
            sl = t.get("stop_loss", 0)
            tp = t.get("target_price", 0)
            mem_lines.append(f"   [{dt}] {v} (Score: {sc}) -> {out} ({r:+.2f}R) | Trigger: ${trig} | SL: ${sl} | TP: ${tp}")
    else:
        mem_lines.append("• No prior historical trades on record for this ticker (First-time analysis).")

    # 4. News Catalyst Continuity
    ticker_news = [n for n in news if n.get("ticker") == ticker]
    mem_lines.append(f"\n=== CATALYST & NEWS CONTINUITY FOR {ticker} ===")
    if ticker_news:
        for idx, n in enumerate(ticker_news[:2], 1):
            dt = n.get("date_time", "")[:16]
            hl = n.get("headline", "")[:220].strip()
            mem_lines.append(f"• Scan #{idx} ({dt}): {hl}...")
    else:
        mem_lines.append("• No prior historical catalyst entries recorded in memory.")

    # 5. Global Macro Context from Market Brief
    mem_lines.append("\n=== CURRENT GLOBAL MACRO ENVIRONMENT ===")
    if brief and not brief.get("error"):
        sent = brief.get("overall_sentiment", "NEUTRAL")
        score = brief.get("sentiment_score", 0)
        summary = brief.get("executive_summary", "")
        mem_lines.append(f"• Overall Market Sentiment: {sent} (Score: {score})")
        mem_lines.append(f"• Macro Summary: {summary}")
        
        # Sector Sentiment if available
        hot_sectors = brief.get("equities", {}).get("hot_sectors", [])
        for s in hot_sectors:
            mem_lines.append(f"   - Sector {s.get('name')}: {s.get('sentiment')} ({s.get('reason')})")
            
        wl_impact = brief.get("watchlist_impact", "")
        if wl_impact:
            mem_lines.append(f"• Watchlist Directive: {wl_impact}")
    else:
        mem_lines.append("• Macro regime: Standard trading environment.")

    # 6. Strategic Execution Playbook Rules
    mem_lines.append("\n=== STRATEGIC RISK RULES TO ENFORCE ===")
    mem_lines.append("1. If previous trade hit SL due to late session whipsaw (e.g. CRDO), enforce min 2.0% stop buffer under 15m/1h structure.")
    mem_lines.append("2. If news catalyst is a recurring headline already priced in, require strong volume expansion (RVOL >= 1.5x) before issuing BUY/SELL.")
    mem_lines.append("3. For WATCH signals, ensure Trigger Price is placed strictly at real resistance/support breakout levels, not inside the choppy range.")

    return "\n".join(mem_lines)

if __name__ == "__main__":
    import sys
    ticker = sys.argv[1] if len(sys.argv) > 1 else "EOSE"
    print(get_rag_context(ticker))
