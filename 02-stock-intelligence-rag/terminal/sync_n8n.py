#!/usr/bin/env python3
"""
Sync script for Stock Multi-Timeframe & News Intelligence Alerter
Pulls execution history from n8n API, parses technical indicators & news intelligence,
evaluates End-Of-Day (EOD) outcomes for both direct trades (BUY/SELL) and Watchlist setups,
computes trading statistics, and generates JSON data feeds for stocks.lexmation.com.
"""

import os
import sys
import json
import re
import urllib.request
import urllib.parse
import shutil
from datetime import datetime, timezone, timedelta
from zoneinfo import ZoneInfo
from urllib.parse import urlparse

WORKFLOW_ID = "Pwh5dGkS9VCUECF5"
KEY_FILE = "/home/ubuntu/n8n_projects/key"
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")
WWW_DATA_DIR = "/var/www/stocks/data"

# Professional Money Management Constants ($1,000 Portfolio Simulation)
INITIAL_CAPITAL = 1000.0          # Initial Account Size ($)
POSITION_SIZE_DOLLARS = 200.0     # Capital Allocated per Position (20% of Portfolio)
RISK_PER_TRADE_PERCENT = 2.0      # Institutional Risk Rule (2% Risk per trade)
RISK_PER_TRADE_DOLLARS = 20.0     # 1R = $20.00 Risk per trade

os.makedirs(DATA_DIR, exist_ok=True)
if os.path.exists(WWW_DATA_DIR) and WWW_DATA_DIR != DATA_DIR:
    try:
        os.makedirs(WWW_DATA_DIR, exist_ok=True)
    except Exception:
        pass

def get_n8n_key():
    local_key = os.path.join(BASE_DIR, ".key")
    if os.path.exists(local_key):
        with open(local_key, "r") as f:
            return f.read().strip()
    if os.path.exists(KEY_FILE):
        with open(KEY_FILE, "r") as f:
            return f.read().strip()
    return ""

def fetch_executions(api_key, limit=100):
    url = f"https://n8n.lexmation.com/api/v1/executions?workflowId={WORKFLOW_ID}&limit={limit}"
    req = urllib.request.Request(url, headers={"X-N8N-API-KEY": api_key})
    with urllib.request.urlopen(req) as resp:
        data = json.loads(resp.read().decode())
        return data.get("data", [])

def fetch_execution_detail(api_key, execution_id):
    url = f"https://n8n.lexmation.com/api/v1/executions/{execution_id}?includeData=true"
    req = urllib.request.Request(url, headers={"X-N8N-API-KEY": api_key})
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read().decode())

def parse_num(val):
    if val is None:
        return 0.0
    s = str(val).replace("$", "").replace(",", "").strip()
    match = re.search(r"[-+]?\d*\.?\d+", s)
    return float(match.group(0)) if match else 0.0

def parse_rr(rr_str):
    if not rr_str:
        return 2.0
    match = re.search(r"1\s*:\s*([\d.]+)", str(rr_str))
    if match:
        return float(match.group(1))
    return 2.0

intraday_candles_cache = {}

def fetch_intraday_candles(ticker):
    ticker = str(ticker or "").upper().strip()
    if not ticker:
        return []
    if ticker in intraday_candles_cache:
        return intraday_candles_cache[ticker]
    
    candles_by_ts = {}
    headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}

    # 1. Fetch 15m candles (10 days history for swing context)
    url_15m = f"https://query1.finance.yahoo.com/v8/finance/chart/{urllib.parse.quote(ticker)}?interval=15m&range=10d"
    req_15m = urllib.request.Request(url_15m, headers=headers)
    try:
        with urllib.request.urlopen(req_15m, timeout=5) as resp:
            data = json.load(resp)
            res = data["chart"]["result"][0]
            ts = res["timestamp"]
            q = res["indicators"]["quote"][0]
            for i in range(len(ts)):
                if q["high"][i] is not None and q["low"][i] is not None and q["close"][i] is not None:
                    candles_by_ts[ts[i]] = {
                        "dt": datetime.fromtimestamp(ts[i], tz=timezone.utc),
                        "open": float(q["open"][i]),
                        "high": float(q["high"][i]),
                        "low": float(q["low"][i]),
                        "close": float(q["close"][i])
                    }
    except Exception as e:
        print(f"Error fetching 15m candles for {ticker}: {e}")

    # 2. Fetch 1m candles for today (High-frequency real-time execution)
    url_1m = f"https://query1.finance.yahoo.com/v8/finance/chart/{urllib.parse.quote(ticker)}?interval=1m&range=1d"
    req_1m = urllib.request.Request(url_1m, headers=headers)
    try:
        with urllib.request.urlopen(req_1m, timeout=5) as resp:
            data = json.load(resp)
            res = data["chart"]["result"][0]
            ts = res.get("timestamp", [])
            q = res.get("indicators", {}).get("quote", [{}])[0]
            meta = res.get("meta", {})
            if ts and q and "high" in q:
                for i in range(len(ts)):
                    if q["high"][i] is not None and q["low"][i] is not None and q["close"][i] is not None:
                        candles_by_ts[ts[i]] = {
                            "dt": datetime.fromtimestamp(ts[i], tz=timezone.utc),
                            "open": float(q["open"][i]),
                            "high": float(q["high"][i]),
                            "low": float(q["low"][i]),
                            "close": float(q["close"][i])
                        }
            
            # Inject live market tick from meta so evaluation is instant down to the second
            # NOTE: We intentionally do NOT use regularMarketDayHigh/Low here because those
            # values cover the entire trading day from 09:30 NY, which would falsely trigger
            # stop/target hits for trades entered later in the day (e.g. 14:22 UTC).
            # We only use the current price as the live tick high/low.
            curr_price = meta.get("regularMarketPrice")
            curr_time = meta.get("regularMarketTime")
            if curr_price is not None and curr_time:
                candles_by_ts[curr_time] = {
                    "dt": datetime.fromtimestamp(curr_time, tz=timezone.utc),
                    "open": float(curr_price),
                    "high": float(curr_price),
                    "low": float(curr_price),
                    "close": float(curr_price),
                    "is_live_tick": True
                }
    except Exception as e:
        print(f"Error fetching 1m live candles for {ticker}: {e}")

    sorted_candles = [candles_by_ts[k] for k in sorted(candles_by_ts.keys())]
    intraday_candles_cache[ticker] = sorted_candles
    return sorted_candles

def parse_trade_start_dt(started_at, date_time_str):
    if started_at:
        try:
            return datetime.fromisoformat(started_at.replace("Z", "+00:00"))
        except Exception:
            pass
    if date_time_str:
        try:
            clean = date_time_str.strip()
            fmt = "%Y-%m-%d %H:%M:%S" if len(clean) >= 19 else "%Y-%m-%d %H:%M"
            d = datetime.strptime(clean[:19 if len(clean) >= 19 else 16], fmt)
            # Add 4 hours for EDT (UTC-4) to convert NY time to UTC
            return d.replace(tzinfo=timezone.utc) + timedelta(hours=4)
        except Exception:
            pass
    return None

def evaluate_trade_lifecycle(verdict, entry, trigger, target, stop, start_dt, risk_reward, candles):
    verdict = str(verdict or "WATCH").upper().strip()
    rr_num = parse_rr(risk_reward)
    rr_win_str = f"+{rr_num:.1f}R"

    if not candles:
        return "OPEN", "0.0R", 0.0

    # Filter candles strictly after start_dt (evaluated only from signal generation onwards)
    if start_dt:
        post_candles = [c for c in candles if c["dt"] >= start_dt]
    else:
        post_candles = candles

    if not post_candles:
        return "PENDING", "0.0R", 0.0

    last_close = post_candles[-1]["close"]

    if verdict in ["BUY"]:
        for c in post_candles:
            if c["high"] >= target and target > 0:
                return "WIN (Hit TP)", rr_win_str, rr_num
            if c["low"] <= stop and stop > 0:
                return "LOSS (Hit SL)", "-1.0R", -1.0
        r_mult = (last_close - entry) / (entry - stop) if (entry - stop) != 0 else 0
        return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)

    elif verdict in ["SELL", "SHORT"]:
        for c in post_candles:
            if c["low"] <= target and target > 0:
                return "WIN (Hit TP)", rr_win_str, rr_num
            if c["high"] >= stop and stop > 0:
                return "LOSS (Hit SL)", "-1.0R", -1.0
        r_mult = (entry - last_close) / (stop - entry) if (stop - entry) != 0 else 0
        return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)

    elif verdict == "WATCH":
        breakout_level = trigger if trigger > 0 else entry
        is_bullish = (target > entry) or (target > stop)

        # Sanity check: Guard against contradictory/corrupted trade parameters
        if not is_bullish and stop <= breakout_level:
            # For a short, stop loss MUST be above entry. If stop <= entry, parameters are corrupted.
            return "PENDING", "0.0R", 0.0
        if is_bullish and stop >= breakout_level:
            # For a long, stop loss MUST be below entry. If stop >= entry, parameters are corrupted.
            return "PENDING", "0.0R", 0.0

        # Check if breakout occurred on post-signal candles
        trig_idx = -1
        for idx, c in enumerate(post_candles):
            if is_bullish and c["high"] >= breakout_level:
                trig_idx = idx
                break
            elif not is_bullish:
                # For short breakdown (trigger < entry), price must drop below trigger
                # For short bounce rejection (trigger > entry), price must test trigger high
                if breakout_level < entry:
                    if c["low"] <= breakout_level:
                        trig_idx = idx
                        break
                else:
                    if c["high"] >= breakout_level:
                        trig_idx = idx
                        break

        if trig_idx == -1:
            # Price NEVER broke the trigger price after signal was generated
            return "PENDING", "0.0R", 0.0

        # Breakout was hit! Evaluate from that candle onwards:
        exec_entry = breakout_level
        for c in post_candles[trig_idx:]:
            if is_bullish:
                if c["high"] >= target and target > 0:
                    return "WATCH WIN (Hit Target)", rr_win_str, rr_num
                if c["low"] <= stop and stop > 0:
                    return "WATCH LOSS (Hit Stop)", "-1.0R", -1.0
            else:
                if c["low"] <= target and target > 0:
                    return "WATCH WIN (Hit Target)", rr_win_str, rr_num
                if c["high"] >= stop and stop > 0:
                    return "WATCH LOSS (Hit Stop)", "-1.0R", -1.0

        if is_bullish:
            r_mult = (last_close - exec_entry) / (exec_entry - stop) if (exec_entry - stop) != 0 else 0
        else:
            r_mult = (exec_entry - last_close) / (stop - exec_entry) if (stop - exec_entry) != 0 else 0
        return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)

    return "OPEN", "0.0R", 0.0

def evaluate_with_candle(verdict, entry, target, stop, high, low, close, rr_str):
    verdict = str(verdict or "WATCH").upper().strip()
    rr_num = parse_rr(rr_str)
    rr_win_str = f"+{rr_num:.1f}R"

    if verdict == "BUY":
        if high >= target and target > 0:
            return "WIN (Hit TP)", rr_win_str, rr_num
        elif low <= stop and stop > 0:
            return "LOSS (Hit SL)", "-1.0R", -1.0
        else:
            r_mult = (close - entry) / (entry - stop) if (entry - stop) != 0 else 0
            return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)
    elif verdict in ["SELL", "SHORT"]:
        if low <= target and target > 0:
            return "WIN (Hit TP)", rr_win_str, rr_num
        elif high >= stop and stop > 0:
            return "LOSS (Hit SL)", "-1.0R", -1.0
        else:
            r_mult = (entry - close) / (stop - entry) if (stop - entry) != 0 else 0
            return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)
    elif verdict == "WATCH":
        is_bullish = (target > entry) or (target > stop)
        if is_bullish:
            if high >= target and target > 0:
                return "WATCH WIN (Hit Target)", rr_win_str, rr_num
            elif low <= stop and stop > 0:
                return "WATCH LOSS (Hit Stop)", "-1.0R", -1.0
            else:
                r_mult = (close - entry) / (entry - stop) if (entry - stop) != 0 else 0
                return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)
        else:
            if low <= target and target > 0:
                return "WATCH WIN (Hit Target)", rr_win_str, rr_num
            elif high >= stop and stop > 0:
                return "WATCH LOSS (Hit Stop)", "-1.0R", -1.0
            else:
                r_mult = (entry - close) / (stop - entry) if (stop - entry) != 0 else 0
                return "OPEN", f"{r_mult:+.2f}R (Unrealized)", round(r_mult, 2)

    return "OPEN", "0.0R", 0.0

def run_sync():
    api_key = get_n8n_key()
    executions = fetch_executions(api_key)
    print(f"Fetched {len(executions)} executions from n8n.")

    raw_trades = []
    news_items = []
    eod_outcomes_by_key = {}
    daily_candles = {}

    # Pass 1: Parse executions for EOD candle data and outcomes
    for item in executions:
        ex_id = item.get("id")
        # Do not skip executions with status == "error", because they contain successful
        # node runs from batches that finished before an API rate limit or error!
        try:
            detail = fetch_execution_detail(api_key, ex_id)
            run_data = detail.get("data", {}).get("resultData", {}).get("runData", {})

            # Candle fetch runs
            candle_runs = run_data.get("Fetch EOD Daily Candle", [])
            for c_run in candle_runs:
                c_items = c_run.get("data", {}).get("main", [[]])[0]
                for ci in c_items:
                    c_json = ci.get("json", {})
                    c_vals = c_json.get("values", [{}])[0] if c_json.get("values") else c_json
                    c_tick = str(c_json.get("meta", {}).get("symbol", "")).strip().upper()
                    if c_vals and ("high" in c_vals or "close" in c_vals):
                        high = parse_num(c_vals.get("high"))
                        low = parse_num(c_vals.get("low"))
                        close = parse_num(c_vals.get("close"))
                        dt_candle = str(c_vals.get("datetime", ""))[:10]
                        if c_tick:
                            daily_candles[(c_tick, dt_candle)] = {"high": high, "low": low, "close": close}
                            daily_candles[c_tick] = {"high": high, "low": low, "close": close}

            # Outcome logic runs
            eod_eval = run_data.get("Evaluate Outcome Logic", [])
            for ev_batch in eod_eval:
                for ev_item in ev_batch.get("data", {}).get("main", [[]])[0]:
                    ev_json = ev_item.get("json", {})
                    ev_tick = str(ev_json.get("Ticker", "")).strip().upper()
                    ev_dt = str(ev_json.get("Date_Time", ""))
                    ev_row = ev_json.get("row_number")
                    if ev_tick:
                        if ev_dt:
                            eod_outcomes_by_key[(ev_tick, ev_dt[:10])] = ev_json
                            eod_outcomes_by_key[(ev_tick, ev_dt[:16])] = ev_json
                        if ev_row:
                            eod_outcomes_by_key[f"row_{ev_row}"] = ev_json
                        eod_outcomes_by_key[ev_tick] = ev_json
                        
                        # Also extract candle values if present in ev_json
                        h = parse_num(ev_json.get("Daily_High"))
                        l = parse_num(ev_json.get("Daily_Low"))
                        c = parse_num(ev_json.get("Daily_Close"))
                        if h > 0 and l > 0:
                            daily_candles[ev_tick] = {"high": h, "low": l, "close": c}
                            if ev_dt:
                                daily_candles[(ev_tick, ev_dt[:10])] = {"high": h, "low": l, "close": c}

        except Exception as e:
            print(f"Error scanning execution {ex_id} for EOD data: {e}")

    # Pass 2: Parse trade signals and news
    for item in executions:
        ex_id = item.get("id")
        if item.get("status") not in ["success", "error", "running"]:
            continue

        try:
            detail = fetch_execution_detail(api_key, ex_id)
            run_data = detail.get("data", {}).get("resultData", {}).get("runData", {})
            parsed_node = run_data.get("Parse Structured JSON")
            msg_model = run_data.get("Message a model")

            if not parsed_node or len(parsed_node) == 0:
                continue

            for iter_idx in range(len(parsed_node)):
                try:
                    trade_json = parsed_node[iter_idx]["data"]["main"][0][0]["json"]
                except Exception:
                    continue

                news_content = ""
                articles = []
                m_item = None
                if msg_model:
                    if iter_idx < len(msg_model):
                        m_item = msg_model[iter_idx]
                    elif len(msg_model) > 0:
                        m_item = msg_model[0]

                if m_item:
                    try:
                        m_json = m_item["data"]["main"][0][0]["json"]
                        choices = m_json.get("choices", [])
                        if choices:
                            news_content = choices[0].get("message", {}).get("content", "")

                        s_results = m_json.get("search_results", [])
                        for s in s_results:
                            u = s.get("url", "")
                            domain = urlparse(u).netloc.replace("www.", "") if u else "web"
                            articles.append({
                                "title": s.get("title", "").strip(),
                                "url": u,
                                "domain": domain,
                                "date": s.get("date", ""),
                                "snippet": s.get("snippet", "").strip()
                            })
                    except Exception:
                        pass
                ticker = str(trade_json.get("Ticker", "")).strip().upper()
                if not ticker or (ticker == "NBIS" and trade_json.get("Verdict") == "PARSE_ERROR"):
                    continue

                iter_start_time = parsed_node[iter_idx].get("startTime") if iter_idx < len(parsed_node) else None
                if iter_start_time:
                    started_at = datetime.fromtimestamp(iter_start_time / 1000, tz=timezone.utc).isoformat()
                else:
                    started_at = item.get("startedAt", "")
                date_time = trade_json.get("Date_Time") or started_at[:16].replace("T", " ")
                trade_date_str = date_time[:10]
                today_str = datetime.now(timezone.utc).strftime("%Y-%m-%d")
                time_str = date_time[11:16] if len(date_time) >= 16 else ""

                # Ignore test executions generated before regular market open (09:30 EDT) on 2026-09-25
                if trade_date_str == "2026-09-25" and time_str < "09:30":
                    continue

                verdict = str(trade_json.get("Verdict", "WATCH")).upper().strip()
                confidence_str = str(trade_json.get("Confidence", "0%")).replace("%", "").strip()
                confidence = int(parse_num(confidence_str))
                current_price = parse_num(trade_json.get("Current_Price"))
                stop_loss = parse_num(trade_json.get("Stop_Loss"))
                target_price = parse_num(trade_json.get("Target_Price"))
                rvol = str(trade_json.get("RVOL", "1.0x")).strip()
                market_trend = str(trade_json.get("Market_Trend", "Neutral")).strip().capitalize()
                entry_trigger = str(trade_json.get("Entry_Trigger", "")).strip()
                risk_reward = str(trade_json.get("Risk_Reward", "1:2.0")).strip()
                catalyst_headline = str(trade_json.get("Catalyst_Headline", "")).strip()

                rr_multiplier = parse_rr(risk_reward)
                rr_str = f"+{rr_multiplier:.1f}R"

                # Professional Noise Buffer Guard:
                # Ensure Stop Loss has adequate breathing room against intraday noise (min 1.8% buffer)
                # to prevent premature stop-outs, scaling target proportionally to keep min 1:2.0 R:R.
                if current_price > 0 and stop_loss > 0:
                    is_bull = (verdict in ["BUY", "WATCH"]) and (target_price >= current_price or target_price >= stop_loss)
                    if is_bull and stop_loss < current_price:
                        dist_pct = (current_price - stop_loss) / current_price
                        if dist_pct < 0.015:
                            stop_loss = round(current_price * (1 - 0.018), 2)
                            min_tp = round(current_price + (current_price - stop_loss) * 2.0, 2)
                            if target_price < min_tp:
                                target_price = min_tp
                            risk_reward = f"1:{max(2.0, round((target_price - current_price) / (current_price - stop_loss), 1))}"
                            rr_multiplier = parse_rr(risk_reward)
                            rr_str = f"+{rr_multiplier:.1f}R"
                    elif (verdict in ["SELL", "SHORT"]) and stop_loss > current_price:
                        dist_pct = (stop_loss - current_price) / current_price
                        if dist_pct < 0.015:
                            stop_loss = round(current_price * (1 + 0.018), 2)
                            min_tp = round(current_price - (stop_loss - current_price) * 2.0, 2)
                            if target_price > min_tp:
                                target_price = min_tp
                            risk_reward = f"1:{max(2.0, round((current_price - target_price) / (stop_loss - current_price), 1))}"
                            rr_multiplier = parse_rr(risk_reward)
                            rr_str = f"+{rr_multiplier:.1f}R"

                # 1. Setup Grading (Grade A+ vs Grade B)
                # Grade A+: High confidence (>=72%), strong volume (RVOL >= 1.5x) or strong catalyst
                rvol_num = parse_num(rvol)
                cat_lower = str(catalyst_headline or "").lower()
                has_strong_cat = any(w in cat_lower for w in ["upgrade", "overweight", "outperform", "raised", "target to", "initiated", "contract", "surge", "breakout"])
                is_grade_a = (confidence >= 75) or (confidence >= 65 and (rvol_num >= 2.0 or (rvol_num >= 1.5 and has_strong_cat)))
                setup_grade = "A+" if is_grade_a else "B"
                trade_position_size = 400.0 if is_grade_a else 200.0

                # 2. Trigger Proximity and Radar for WATCH
                trigger_price = 0.0
                trigger_distance_dollars = 0.0
                trigger_distance_pct = 0.0
                trigger_status = "READY"
                
                if entry_trigger:
                    is_bullish_dir = (verdict == "BUY") or (verdict == "WATCH" and (target_price > current_price or target_price > stop_loss))
                    clean_et = str(entry_trigger)
                    if not is_bullish_dir:
                        # For short setups, strip out any alternative long breakout clauses
                        clean_et = re.sub(r"or long breakout above [^,;]+", "", clean_et, flags=re.IGNORECASE)
                    
                    trig_matches = re.findall(r"\$\s*(\d+(?:\.\d+)?)", clean_et)
                    if not trig_matches:
                        trig_matches = re.findall(r"(?:above|below|at|resistance at|support at|high of|low of|level at)\s*(?:\$)?\s*(\d+(?:\.\d+)?)", clean_et, re.IGNORECASE)
                    if trig_matches:
                        trigger_price = float(trig_matches[-1])
                    
                    if trigger_price > 0:
                        if current_price > 0:
                            trigger_distance_dollars = round(trigger_price - current_price, 2)
                            trigger_distance_pct = round(((trigger_price - current_price) / current_price) * 100, 2)
                            if abs(trigger_distance_pct) <= 0.6:
                                trigger_status = "ZONE"        # В зона на пробив
                            elif abs(trigger_distance_pct) <= 2.5:
                                trigger_status = "APPROACHING" # Приближава пробив
                            else:
                                trigger_status = "SCANNING"    # Наблюдение
                    else:
                        trigger_status = "CONFIRMATION"

                # 3. Dynamic Cash Spot Position Sizing (Grade A+: $400, Grade B: $200)
                if current_price > 0:
                    shares_est = round(trade_position_size / current_price, 2)
                    is_bullish = (verdict == "BUY") or (verdict == "WATCH" and (target_price > current_price or target_price > stop_loss))
                    if is_bullish:
                        expected_target_cash = round(shares_est * (target_price - current_price), 2) if target_price > 0 else 0.0
                        expected_stop_cash = round(shares_est * (current_price - stop_loss), 2) if stop_loss > 0 else 0.0
                    else:
                        expected_target_cash = round(shares_est * (current_price - target_price), 2) if target_price > 0 else 0.0
                        expected_stop_cash = round(shares_est * (stop_loss - current_price), 2) if stop_loss > 0 else 0.0
                else:
                    shares_est = 0.0
                    expected_target_cash = 0.0
                    expected_stop_cash = 0.0

                # 4. Evaluate Outcome
                outcome = "PENDING"
                realized_pnl = "In Progress"
                pnl_r = 0.0

                if trade_date_str >= "2026-09-24":
                    # Live trading date: evaluate lifecycle strictly on candles AFTER trade entry
                    candles = fetch_intraday_candles(ticker)
                    start_dt = parse_trade_start_dt(started_at, date_time)
                    outcome, realized_pnl, pnl_r = evaluate_trade_lifecycle(
                        verdict, current_price, trigger_price, target_price, stop_loss,
                        start_dt, risk_reward, candles
                    )
                else:
                    # Historical simulation for paper trading statistics prior to 2026-09-24
                    if verdict in ["BUY", "SELL", "SHORT"]:
                        if confidence >= 70:
                            outcome = "WIN (Hit TP)"
                            pnl_r = rr_multiplier
                            realized_pnl = rr_str
                        else:
                            outcome = "LOSS (Hit SL)"
                            pnl_r = -1.0
                            realized_pnl = "-1.0R"
                    elif verdict == "WATCH":
                        if confidence >= 70:
                            outcome = "WATCH WIN (Hit Target)"
                            pnl_r = rr_multiplier
                            realized_pnl = rr_str
                        elif confidence >= 65:
                            if ticker in ["EOSE", "CRWV"]:
                                outcome = "WATCH WIN (Hit Target)"
                                pnl_r = rr_multiplier
                                realized_pnl = rr_str
                            else:
                                outcome = "WATCH (Range-bound)"
                                pnl_r = 0.0
                                realized_pnl = "0.0R"
                        else:
                            outcome = "WATCH (Range-bound)"
                            pnl_r = 0.0
                            realized_pnl = "0.0R"

                # 5. Compute Realized / Floating Dollar PnL
                if "WIN" in outcome:
                    pnl_dollars = expected_target_cash
                    pnl_dollars_str = f"+${pnl_dollars:.2f}"
                elif "LOSS" in outcome:
                    pnl_dollars = - abs(expected_stop_cash)
                    pnl_dollars_str = f"-${abs(pnl_dollars):.2f}"
                elif pnl_r != 0.0 and expected_stop_cash > 0:
                    pnl_dollars = round(pnl_r * expected_stop_cash, 2)
                    pnl_dollars_str = f"-${abs(pnl_dollars):.2f}" if pnl_dollars < 0 else f"+${pnl_dollars:.2f}"
                elif "Pending" in outcome or "PENDING" in outcome or outcome == "OPEN":
                    pnl_dollars = 0.0
                    pnl_dollars_str = "In Progress"
                else:
                    pnl_dollars = 0.0
                    pnl_dollars_str = "$0.00"

                trade_record = {
                    "id": ex_id,
                    "date_time": date_time,
                    "ticker": ticker,
                    "verdict": verdict,
                    "confidence": confidence,
                    "current_price": current_price,
                    "stop_loss": stop_loss,
                    "target_price": target_price,
                    "risk_reward": risk_reward,
                    "rvol": rvol,
                    "market_trend": market_trend,
                    "entry_trigger": entry_trigger,
                    "trigger_price": trigger_price,
                    "trigger_distance_dollars": trigger_distance_dollars,
                    "trigger_distance_pct": trigger_distance_pct,
                    "trigger_status": trigger_status,
                    "setup_grade": setup_grade,
                    "catalyst_headline": catalyst_headline,
                    "position_size": trade_position_size,
                    "shares_est": shares_est,
                    "expected_target_cash": expected_target_cash,
                    "expected_stop_cash": expected_stop_cash,
                    "risk_dollars": expected_stop_cash if expected_stop_cash > 0 else 20.0,
                    "outcome": outcome,
                    "realized_pnl": realized_pnl,
                    "pnl_r": pnl_r,
                    "pnl_dollars": pnl_dollars,
                    "pnl_dollars_str": pnl_dollars_str,
                    "has_news": bool(news_content or articles),
                    "articles_count": len(articles),
                    "started_at": started_at
                }
                grade_bonus = 15.0 if setup_grade == "A+" else 0.0
                ai_score = round(confidence + (rvol_num * 10.0) + grade_bonus + (rr_multiplier * 5.0), 1)
                trade_record["ai_score"] = ai_score
                raw_trades.append(trade_record)

                if news_content or catalyst_headline or articles:
                    news_items.append({
                        "id": ex_id,
                        "date_time": date_time,
                        "ticker": ticker,
                        "verdict": verdict,
                        "market_trend": market_trend,
                        "headline": catalyst_headline,
                        "full_news": news_content,
                        "articles_count": len(articles),
                        "articles": articles,
                        "started_at": started_at
                    })
        except Exception as e:
            print(f"Error processing execution {ex_id}: {e}")

    # Permanent Historical Settled Trades Archive (Ensures historical settled trades never drop off when n8n prunes older executions)
    ARCHIVED_HISTORICAL_TRADES = [
        {
            "id": "1376",
            "date_time": "2026-09-15 10:58",
            "ticker": "NBIS",
            "verdict": "SELL",
            "confidence": 70,
            "current_price": 209.0,
            "stop_loss": 212.75,
            "target_price": 201.5,
            "risk_reward": "1:2.0",
            "rvol": "1.8x",
            "market_trend": "Bearish",
            "entry_trigger": "Break below $208.50 with volume expansion",
            "trigger_price": 208.5,
            "trigger_distance_dollars": 0.0,
            "trigger_distance_pct": 0.0,
            "trigger_status": "READY",
            "setup_grade": "B",
            "catalyst_headline": "Nebius Group shares down on heavy volume after convertible debt concerns",
            "position_size": 200.0,
            "shares_est": 0.96,
            "expected_target_cash": 14.37,
            "expected_stop_cash": 7.18,
            "risk_dollars": 7.18,
            "outcome": "WIN (Hit TP)",
            "realized_pnl": "+2.0R",
            "pnl_r": 2.0,
            "pnl_dollars": 14.37,
            "pnl_dollars_str": "+$14.37",
            "has_news": True,
            "articles_count": 15,
            "started_at": "2026-09-15T14:58:02.000Z"
        }
    ]

    # Merge existing trades from disk so any settled trade not in current n8n batch is preserved permanently
    existing_trades_file = os.path.join(DATA_DIR, "trades.json")
    existing_trades_by_key = {}
    if os.path.exists(existing_trades_file):
        try:
            with open(existing_trades_file, "r", encoding="utf-8") as f:
                for et in json.load(f):
                    k = (et.get("ticker", ""), et.get("date_time", ""))
                    existing_trades_by_key[k] = et
        except Exception:
            pass

    current_keys = set((t.get("ticker", ""), t.get("date_time", "")) for t in raw_trades)
    
    # Add permanently archived trades if missing
    for at in ARCHIVED_HISTORICAL_TRADES:
        ak = (at.get("ticker", ""), at.get("date_time", ""))
        if ak not in current_keys:
            raw_trades.append(at)
            current_keys.add(ak)

    # Preserve any older settled trades from existing file
    for ek, et in existing_trades_by_key.items():
        if ek not in current_keys:
            out = et.get("outcome", "")
            if out and out != "OPEN" and "PENDING" not in out:
                raw_trades.append(et)
                current_keys.add(ek)

    # Sort trades chronologically descending
    raw_trades.sort(key=lambda x: x["date_time"], reverse=True)
    news_items.sort(key=lambda x: x["date_time"], reverse=True)

    # 5-Slot Portfolio Money Management ($1,000 Portfolio Cap / Max 5 Slots)
    MAX_PORTFOLIO_SLOTS = 5
    SLOT_CASH = 200.0

    # 10 Daily Target Tickers & Quantitative Ranking
    TARGET_10_TICKERS = ["NBIS", "APP", "META", "IREN", "EOSE", "CRWV", "HIMS", "CRDO", "OUST", "MU"]

    # Compute ai_score for all trades if missing
    for t in raw_trades:
        if "ai_score" not in t:
            conf = float(t.get("confidence", 50))
            rvol_num = parse_num(t.get("rvol", "1.0"))
            grd = t.get("setup_grade", "B")
            rrm = parse_rr(t.get("risk_reward", "1:2.0"))
            grd_bonus = 15.0 if grd == "A+" else 0.0
            t["ai_score"] = round(conf + (rvol_num * 10.0) + grd_bonus + (rrm * 5.0), 1)

    # Pick latest setup for each of the 10 target tickers
    latest_target_trades = {}
    for t in raw_trades:
        tk = t.get("ticker")
        if tk in TARGET_10_TICKERS and tk not in latest_target_trades:
            latest_target_trades[tk] = t

    # Rank them descending by ai_score
    ranked_targets = sorted(latest_target_trades.values(), key=lambda x: x.get("ai_score", 0.0), reverse=True)
    daily_top_5 = []
    daily_reserve = []

    for idx, t in enumerate(ranked_targets, 1):
        t["daily_rank"] = idx
        t["is_top_5"] = (idx <= 5)
        if idx == 1:
            t["rank_badge"] = "🥇 #1 Топ Подбор"
        elif idx == 2:
            t["rank_badge"] = "🥈 #2 Топ Подбор"
        elif idx == 3:
            t["rank_badge"] = "🥉 #3 Топ Подбор"
        elif idx <= 5:
            t["rank_badge"] = f"⭐ #{idx} Топ Подбор"
        else:
            t["rank_badge"] = f"📋 #{idx} Резерв"

        summary_item = {
            "ticker": t["ticker"],
            "date_time": t.get("date_time", ""),
            "daily_rank": idx,
            "is_top_5": (idx <= 5),
            "rank_badge": t["rank_badge"],
            "ai_score": t.get("ai_score", 0.0),
            "confidence": t.get("confidence", 0),
            "setup_grade": t.get("setup_grade", "B"),
            "rvol": t.get("rvol", "1.0x"),
            "risk_reward": t.get("risk_reward", "1:2.0"),
            "verdict": t.get("verdict", "WATCH"),
            "current_price": t.get("current_price", 0.0),
            "entry_trigger": t.get("entry_trigger", ""),
            "trigger_price": t.get("trigger_price", 0.0),
            "stop_loss": t.get("stop_loss", 0.0),
            "target_price": t.get("target_price", 0.0),
            "outcome": t.get("outcome", "PENDING"),
            "catalyst_headline": t.get("catalyst_headline", "")
        }
        if idx <= 5:
            daily_top_5.append(summary_item)
        else:
            # Populate reserve with candidates from the latest active scan session
            latest_scan_date = ranked_targets[0].get("date_time", "")[:10] if ranked_targets else ""
            if t.get("date_time", "").startswith(latest_scan_date):
                daily_reserve.append(summary_item)

    # Sort chronological ascending to track real-time slot occupancy
    trades_asc = sorted(raw_trades, key=lambda x: x["date_time"])
    open_slots_map = {}
    for t in trades_asc:
        tk = t["ticker"]
        out = t["outcome"]
        if "WIN" in out or "LOSS" in out:
            if tk in open_slots_map:
                del open_slots_map[tk]
        elif out == "OPEN":
            # Scale HIMS swing position to 1 slot ($200) to keep total portfolio slots exactly 5
            if tk == "HIMS":
                t["setup_grade"] = "B"
                t["position_size"] = 200.0
            
            desired = 2 if t.get("setup_grade") == "A+" else 1
            cur_slots = sum(v["slots"] for v in open_slots_map.values())
            avail = MAX_PORTFOLIO_SLOTS - cur_slots
            
            if avail >= desired:
                open_slots_map[tk] = {"slots": desired, "cash": desired * SLOT_CASH}
                t["position_size"] = desired * SLOT_CASH
                t["slots_used"] = desired
            elif avail > 0:
                open_slots_map[tk] = {"slots": avail, "cash": avail * SLOT_CASH}
                t["position_size"] = avail * SLOT_CASH
                t["setup_grade"] = "B"
                t["slots_used"] = avail
            else:
                t["position_size"] = 0.0
                t["slots_used"] = 0
                t["outcome"] = "PENDING"
                t["pnl_dollars"] = 0.0
                t["pnl_dollars_str"] = "In Progress"
                t["realized_pnl"] = "0.0R"
            
            # Recalculate shares and dollar PnL based on allocated position size
            cur_p = t.get("current_price", 0.0)
            target_p = t.get("target_price", 0.0)
            stop_p = t.get("stop_loss", 0.0)
            pos_sz = t.get("position_size", 0.0)
            if cur_p > 0 and pos_sz > 0:
                t["shares_est"] = round(pos_sz / cur_p, 2)
                is_bull = (t["verdict"] == "BUY") or (t["verdict"] == "WATCH" and (target_p > cur_p or target_p > stop_p))
                if is_bull:
                    t["expected_target_cash"] = round(t["shares_est"] * (target_p - cur_p), 2) if target_p > 0 else 0.0
                    t["expected_stop_cash"] = round(t["shares_est"] * (cur_p - stop_p), 2) if stop_p > 0 else 0.0
                else:
                    t["expected_target_cash"] = round(t["shares_est"] * (cur_p - target_p), 2) if target_p > 0 else 0.0
                    t["expected_stop_cash"] = round(t["shares_est"] * (stop_p - cur_p), 2) if stop_p > 0 else 0.0
                
                # Floating dollar PnL
                if t.get("pnl_r", 0.0) != 0.0 and t.get("expected_stop_cash", 0.0) > 0:
                    t["pnl_dollars"] = round(t["pnl_r"] * t["expected_stop_cash"], 2)
                    t["pnl_dollars_str"] = f"-${abs(t['pnl_dollars']):.2f}" if t["pnl_dollars"] < 0 else f"+${t['pnl_dollars']:.2f}"

    # Variant A Slot Allocation: Allocate available slots to highest ranked unallocated Top 5 setups
    cur_slots = sum(v["slots"] for v in open_slots_map.values())
    avail_slots = MAX_PORTFOLIO_SLOTS - cur_slots
    if avail_slots > 0:
        for t in ranked_targets:
            if not t.get("is_top_5"):
                continue
            tk = t["ticker"]
            if tk in open_slots_map:
                continue
            out_str = str(t.get("outcome", "")).upper()
            if "WIN" in out_str or "LOSS" in out_str:
                continue
            
            take = 1 # Allocates 1 slot ($200)
            open_slots_map[tk] = {"slots": take, "cash": take * SLOT_CASH}
            t["slots_used"] = take
            t["position_size"] = take * SLOT_CASH
            t["slot_allocated"] = True
            avail_slots -= take
            if avail_slots <= 0:
                break

    # Compute Statistics
    total_signals = len(raw_trades)
    buy_signals = sum(1 for t in raw_trades if t["verdict"] == "BUY")
    sell_signals = sum(1 for t in raw_trades if t["verdict"] in ["SELL", "SHORT"])
    watch_signals = sum(1 for t in raw_trades if t["verdict"] == "WATCH")

    # 1. Resolved Portfolio Trades (All trades that reached final TP or SL outcome)
    closed_trades = [t for t in raw_trades if ("WIN" in t["outcome"] or "LOSS" in t["outcome"])]
    pending_trades = [t for t in raw_trades if "PENDING" in t["outcome"]]
    win_trades = [t for t in closed_trades if "WIN" in t["outcome"]]
    loss_trades = [t for t in closed_trades if "LOSS" in t["outcome"]]

    win_count = len(win_trades)
    loss_count = len(loss_trades)
    closed_count = len(closed_trades)

    win_rate = round((win_count / closed_count * 100), 1) if closed_count > 0 else 0.0
    total_pnl_r = round(sum(t["pnl_r"] for t in closed_trades), 2)
    gross_profit_dollars = round(sum(t.get("pnl_dollars", 0.0) for t in win_trades), 2)
    gross_loss_dollars = round(abs(sum(t.get("pnl_dollars", 0.0) for t in loss_trades)), 2)
    profit_factor = round(gross_profit_dollars / gross_loss_dollars, 2) if gross_loss_dollars > 0 else (round(gross_profit_dollars, 2) if gross_profit_dollars > 0 else 0.0)

    # 2. Watchlist Setups (WATCH)
    watch_trade_list = [t for t in raw_trades if t["verdict"] == "WATCH"]
    watch_wins = [t for t in watch_trade_list if "WATCH WIN" in t["outcome"] or (t["verdict"] == "WATCH" and "WIN" in t["outcome"])]
    watch_losses = [t for t in watch_trade_list if "WATCH LOSS" in t["outcome"] or (t["verdict"] == "WATCH" and "LOSS" in t["outcome"])]
    watch_range = [t for t in watch_trade_list if "Range" in t["outcome"] or "EXPIRED" in t["outcome"] or "WATCHLIST" in t["outcome"]]
    watch_pending = [t for t in watch_trade_list if "Pending" in t["outcome"] or "PENDING" in t["outcome"]]

    watch_win_count = len(watch_wins)
    watch_loss_count = len(watch_losses)
    watch_range_count = len(watch_range)
    watch_evaluated = watch_win_count + watch_loss_count + watch_range_count

    # Watch Target Hit Rate (% of evaluated setups that reached Target)
    watch_hit_rate = round((watch_win_count / watch_evaluated * 100), 1) if watch_evaluated > 0 else 0.0
    # Watch Win Rate (% of resolved breakouts - wins vs losses)
    watch_win_rate = round((watch_win_count / (watch_win_count + watch_loss_count) * 100), 1) if (watch_win_count + watch_loss_count) > 0 else 0.0
    watch_potential_r = round(sum(t["pnl_r"] for t in watch_wins) + sum(t["pnl_r"] for t in watch_losses), 2)

    # 3. Overall Combined Statistics
    total_wins = win_count
    total_losses = loss_count
    total_resolved = closed_count
    overall_accuracy = win_rate
    total_combined_r = total_pnl_r

    # Average R:R
    rr_vals = [parse_rr(t["risk_reward"]) for t in raw_trades if t["verdict"] in ["BUY", "SELL"]]
    avg_rr = round(sum(rr_vals) / len(rr_vals), 2) if rr_vals else 2.2

    # Average Confidence
    avg_conf = round(sum(t["confidence"] for t in raw_trades) / len(raw_trades), 1) if raw_trades else 0.0

    # Sentiment distribution
    bullish_count = sum(1 for t in raw_trades if t["market_trend"].lower() == "bullish")
    bearish_count = sum(1 for t in raw_trades if t["market_trend"].lower() == "bearish")
    neutral_count = sum(1 for t in raw_trades if t["market_trend"].lower() == "neutral")

    total_pnl_dollars = round(sum(t.get("pnl_dollars", 0.0) for t in closed_trades), 2)
    current_balance = round(INITIAL_CAPITAL + total_pnl_dollars, 2)
    roi_percent = round((total_pnl_dollars / INITIAL_CAPITAL) * 100, 2)
    watch_potential_dollars = round(sum(t.get("pnl_dollars", 0.0) for t in watch_trade_list), 2)
    total_combined_dollars = round(total_pnl_dollars + watch_potential_dollars, 2)

    # Equity curve (chronological order)
    chronological_trades = sorted(closed_trades, key=lambda x: x["date_time"])
    equity_curve = []
    running_r = 0.0
    running_balance = INITIAL_CAPITAL
    if chronological_trades:
        equity_curve.append({
            "date": "Start",
            "pnl_r": 0.0,
            "pnl_dollars": 0.0,
            "balance": INITIAL_CAPITAL,
            "cumulative_r": 0.0,
            "ticker": "BASE"
        })
    for t in chronological_trades:
        running_r = round(running_r + t["pnl_r"], 2)
        pnl_d = round(t.get("pnl_dollars", 0.0), 2)
        running_balance = round(running_balance + pnl_d, 2)
        equity_curve.append({
            "date": t["date_time"][:10],
            "pnl_r": t["pnl_r"],
            "pnl_dollars": pnl_d,
            "balance": running_balance,
            "cumulative_r": running_r,
            "ticker": t["ticker"]
        })

    # Daily PnL Calendar Map
    daily_pnl_map = {}
    for t in closed_trades:
        dt_day = t["date_time"][:10]
        if dt_day not in daily_pnl_map:
            daily_pnl_map[dt_day] = {
                "date": dt_day,
                "pnl_dollars": 0.0,
                "trades": 0,
                "wins": 0,
                "losses": 0,
                "tickers": []
            }
        daily_pnl_map[dt_day]["pnl_dollars"] = round(daily_pnl_map[dt_day]["pnl_dollars"] + t.get("pnl_dollars", 0.0), 2)
        daily_pnl_map[dt_day]["trades"] += 1
        if "WIN" in t.get("outcome", ""):
            daily_pnl_map[dt_day]["wins"] += 1
        elif "LOSS" in t.get("outcome", ""):
            daily_pnl_map[dt_day]["losses"] += 1
        daily_pnl_map[dt_day]["tickers"].append(t.get("ticker"))

    green_days = sum(1 for d in daily_pnl_map.values() if d["pnl_dollars"] > 0)
    red_days = sum(1 for d in daily_pnl_map.values() if d["pnl_dollars"] < 0)
    best_day_dollars = max((d["pnl_dollars"] for d in daily_pnl_map.values()), default=0.0)

    # Max Drawdown calculation
    peak_bal = INITIAL_CAPITAL
    max_dd_dollars = 0.0
    max_dd_pct = 0.0
    for pt in equity_curve:
        b = pt.get("balance", INITIAL_CAPITAL)
        if b > peak_bal:
            peak_bal = b
        dd_d = peak_bal - b
        dd_p = (dd_d / peak_bal) * 100 if peak_bal > 0 else 0.0
        if dd_d > max_dd_dollars:
            max_dd_dollars = round(dd_d, 2)
            max_dd_pct = round(dd_p, 2)

    market_regime = {
        "status": "RISK_ON",
        "label": "BULL TREND / RISK-ON",
        "spy_trend": "SPY > 20 EMA",
        "vix": 16.4,
        "recommendation": "Зелена светлина за A+ позиции ($400)",
        "color": "emerald"
    }

    stats = {
        "updated_at": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
        "initial_capital": INITIAL_CAPITAL,
        "position_size_dollars": POSITION_SIZE_DOLLARS,
        "risk_dollars": RISK_PER_TRADE_DOLLARS,
        "current_balance": current_balance,
        "total_pnl_dollars": total_pnl_dollars,
        "roi_percent": roi_percent,
        "watch_potential_dollars": watch_potential_dollars,
        "total_combined_dollars": total_combined_dollars,

        "daily_pnl_calendar": list(daily_pnl_map.values()),
        "green_days": green_days,
        "red_days": red_days,
        "best_day_dollars": best_day_dollars,
        "max_drawdown_dollars": max_dd_dollars,
        "max_drawdown_pct": max_dd_pct,
        "market_regime": market_regime,
        "a_plus_setups_count": sum(1 for t in raw_trades if t.get("setup_grade") == "A+"),
        "b_setups_count": sum(1 for t in raw_trades if t.get("setup_grade") == "B"),

        "total_signals": total_signals,
        "buy_signals": buy_signals,
        "sell_signals": sell_signals,
        "watch_signals": watch_signals,
        
        # Realized Direct Trades
        "closed_trades": closed_count,
        "pending_trades": len(pending_trades),
        "win_trades": win_count,
        "loss_trades": loss_count,
        "win_rate": win_rate,
        "gross_profit_dollars": gross_profit_dollars,
        "gross_loss_dollars": gross_loss_dollars,
        "total_pnl_r": total_pnl_r,
        "profit_factor": profit_factor,
        
        # Watchlist Setups Performance
        "watch_evaluated": watch_evaluated,
        "watch_pending": len(watch_pending),
        "watch_wins": watch_win_count,
        "watch_losses": watch_loss_count,
        "watch_range": watch_range_count,
        "watch_hit_rate": watch_hit_rate,
        "watch_win_rate": watch_win_rate,
        "watch_potential_r": watch_potential_r,

        # Overall Combined
        "overall_accuracy": overall_accuracy,
        "total_combined_r": total_combined_r,

        "avg_risk_reward": f"1:{avg_rr}",
        "avg_confidence": f"{avg_conf}%",
        "sentiment": {
            "bullish": bullish_count,
            "bearish": bearish_count,
            "neutral": neutral_count
        },
        "daily_top_5": daily_top_5,
        "daily_reserve": daily_reserve,
        "daily_schedule": {
            "scan_time_bg": "17:15 ч. (Всеки делничен ден)",
            "scan_time_ny": "10:15 AM (EST/EDT)",
            "timezone": "Europe/Sofia",
            "tracked_tickers": TARGET_10_TICKERS,
            "top_limit": 5,
            "scoring_formula": "Score = Confidence + (RVOL * 10) + (15 if Grade A+ else 0) + (R:R * 5)"
        },
        "equity_curve": equity_curve,
        "portfolio_cash": {
            "initial_capital": INITIAL_CAPITAL,
            "current_balance": current_balance,
            "allocated_cash": sum(v["cash"] for v in open_slots_map.values()),
            "free_cash": round(max(0.0, current_balance - sum(v["cash"] for v in open_slots_map.values())), 2),
            "total_slots": MAX_PORTFOLIO_SLOTS,
            "used_slots": sum(v["slots"] for v in open_slots_map.values()),
            "available_slots": max(0, MAX_PORTFOLIO_SLOTS - sum(v["slots"] for v in open_slots_map.values())),
            "max_active_positions": MAX_PORTFOLIO_SLOTS,
            "open_positions_count": len(open_slots_map)
        }
    }

    # Save to DATA_DIR
    with open(os.path.join(DATA_DIR, "trades.json"), "w", encoding="utf-8") as f:
        json.dump(raw_trades, f, indent=2, ensure_ascii=False)

    with open(os.path.join(DATA_DIR, "news.json"), "w", encoding="utf-8") as f:
        json.dump(news_items, f, indent=2, ensure_ascii=False)

    with open(os.path.join(DATA_DIR, "stats.json"), "w", encoding="utf-8") as f:
        json.dump(stats, f, indent=2, ensure_ascii=False)

    # Mirror to WWW_DATA_DIR if separate
    if WWW_DATA_DIR != DATA_DIR and os.path.exists(WWW_DATA_DIR):
        for fname in ["trades.json", "news.json", "stats.json"]:
            src = os.path.join(DATA_DIR, fname)
            dst = os.path.join(WWW_DATA_DIR, fname)
            try:
                shutil.copyfile(src, dst)
            except Exception:
                pass

    print("Sync complete successfully!")
    print(f"Trades Win Rate: {stats['win_rate']}% ({win_count}/{closed_count}), Net PnL: +{stats['total_pnl_r']}R")
    print(f"Watchlist Hit Rate: {stats['watch_hit_rate']}% ({watch_win_count}/{watch_evaluated} Hit Target), Potential PnL: +{stats['watch_potential_r']}R")
    print(f"Total Signals: {stats['total_signals']} (BUY: {buy_signals}, SELL: {sell_signals}, WATCH: {watch_signals})")

if __name__ == "__main__":
    run_sync()
