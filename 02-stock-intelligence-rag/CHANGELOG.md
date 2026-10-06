# 📋 Changelog — Stock Multi-Timeframe & News Intelligence Terminal

All notable changes, architectural overhauls, and bug fixes for the **Autonomous Stock Intelligence Terminal (`Pwh5dGkS9VCUECF5`)** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2026-10-06] — Telegram Dual-Branch Decoupling & Independent EOD Delivery

### 🐛 Fixed (Отстранени грешки)
* **Telegram Duplicate Message & Workflow Cross-Trigger Bug:**
  * *Проблем:* Предишната архитектура свързваше нода `Sync Website Terminal` едновременно към цикъла за дневен анализ (`Loop Over Tickers`) и към вечерния таймер за край на сесията (`Market Close 23:30`). В резултат на това и двете събития задействаха едновременно `Send Daily Scan Summary Telegram` и `Send EOD Telegram Report`, изпращайки дублирани дневни списъци в 17:35 ч. и отново в 23:30 ч. вместо вечерния финансов отчет.
  * *Решение:* Разделихме потока на два напълно изолирани и независими клона:
    1. `Loop Over Tickers` (приключен) ➔ `Sync Website Terminal` (`action=daily_scan_summary`) ➔ `Send Daily Scan Summary Telegram` (само Топ 5 подбор за търговия).
    2. `Market Close (23:30)` & `Manual EOD Trigger` ➔ `Sync & Build EOD Summary` (`action=eod_summary`) ➔ `Send EOD Telegram Report` (само вечерен EOD отчет).

### 🚀 Added (Нови функции)
* **Нов нод в n8n (`Sync & Build EOD Summary`):**
  * Извиква специализирания сървърен ендпоинт `api.php?action=eod_summary` с увеличен тайм-аут (60 сек.) за надеждно извличане на реалните вътрешнодневни свещи.
  * Изпраща точно структуриран вечерен доклад с баланс на портфейла, ROI %, текущ Win Rate, списък на активните отворени суинг позиции с текущ плаващ PnL (R), приключените сделки за деня и чакащите тикери.
* **Ръчен EOD уебхук тригер (`trigger-eod-eval`):**
  * Позволява незабавна верификация и ръчно преизчисляване на вечерния отчет през уебхук при необходимост.

---

## [2026-10-02] — Market Close Overhaul, Daily Scan Summary & Audit Dashboard

### 🐛 Fixed (Отстранени грешки)
* **TwelveData 1-Day Candle False Stop-Out Bug:**
  * *Проблем:* Нодът за затваряне на пазара теглеше обобщена 1-дневна свещ (`interval=1day`), която включваше сутрешните спадове от 16:30 ч. БГ (преди сигналите от 17:15 ч. да съществуват). Това предизвикваше фалшиви стопове (`WATCH LOSS -1.0R`) при `NBIS`, `OUST`, `CRWV` и `CRDO`.
  * *Решение:* Заменихме нода с директно извикване на сървърния енджин `api.php?action=eod_summary`, който оценява сделките строго върху вътрешнодневни свещи **след часа на сигнала**.
* **Telegram 20-Message Spam on Market Close:**
  * *Проблем:* Тромавият цикъл в n8n изпращаше по едно съобщение за всеки чакащ ред (включително 10 стари реда от сутрешен тест), заливайки Telegram с 20 отделни нотификации.
  * *Решение:* Премахнахме цикъла. В 23:30 ч. n8n изпраща **точно 1 красиво форматиран, обобщен EOD отчет** с баланс, ROI и отворени суинг позиции.
* **Google Sheets Residual Duplicate Rows:**
  * Служебно изчистени редовете 181–190 от сутрешния предварителен скан като `CANCELLED (Duplicate)` с `0.0R` PnL.
  * Редове 191–200 актуализирани с реалните пазарни резултати (`APP +2.06R`, `OUST +1.09R`, `EOSE +0.33R`, `NBIS -0.62R`).
* **AI Geometry Sanity Guard:**
  * Вградена защита срещу противоречиви нива от AI (както при `CRDO` и `HIMS`). За Дълга се изисква задължително `SL < Entry < TP`, а за Къса `SL > Entry > TP`. Противоречиви сделки не се активират грешно.
* **Website Display Bug (Line 675 in `index.php`):**
  * Отстранен `TypeError: number_format()` при показване на вече форматирани низове за целеви цени.

### 🚀 Added (Нови функции)
* **Автоматичен обобщен пост в Telegram веднага след сканирането (17:26 ч.):**
  * Добавен нод `Send Daily Scan Summary Telegram` в края на сканиращия цикъл в n8n.
  * Веднага след приключване на 10-те акции изпраща прегледен пост: 🥇 Топ 5 за търговия + 📋 5-те резерви, вход, стоп, цел и обосновка.
* **Индикатор за статус на анализа на сайта (`stocks.lexmation.com`):**
  * Динамичен бадж в главното табло:  
    `🟢 Дневен AI Анализ: ЗАВЪРШЕН ДНЕС (10 от 10 акции анализирани в 17:25 ч.)`.
* **Нови API ендпоинти в `api.php`:**
  * `?action=daily_scan_summary` — връща JSON с Топ 5, Резерви и форматиран Telegram HTML текст.
  * `?action=eod_summary` — синхронизира пазара за 2 секунди и генерира вечерен EOD отчет.
* **Механизъм за управление на отворени позиции (Trailing Stop & Dynamic Target):**
  * Преместване на стопа на Breakeven при печалба над `+1.5R` (както при `#APP`), за да няма риск от загуба.
  * Разширяване на целта при продължаващ силен новинарски поток.

### ⚡ Changed (Архитектурни промени)
* **Преструктуриран n8n Workflow (`Pwh5dGkS9VCUECF5`):**
  * Премахнати 7 тромави нода (`Fetch EOD Daily Candle`, `Filter Pending Trades`, `Loop Pending Trades`, `Evaluate Outcome Logic`, `Update Outcome in Sheet`, `Wait 8 Seconds`).
  * Времето за изпълнение на вечерната EOD проверка спадна от **3 минути на 2 секунди**.
* **Пълен код на уеб терминала в GitHub:**
  * Добавена директория `terminal/` с всички работни файлове (`api.php`, `sync_n8n.py`, `rag_engine.py`, `chat_api.php`, `index.php`, `live_prices.php`, `fetch_news_brief.php`).

---

## [2026-10-01] — RAG Context & News Brief Synchronization
* Интегриран RAG контекстен модул за извличане на исторически track record по акции.
* Синхронизирани макро новини и автоматично генериране на `market_brief.json`.
* Фиксиран бъг с грешно изчислен стоп при `HIMS` и възстановени +1.0R / +$2.00 към портфолиото.
