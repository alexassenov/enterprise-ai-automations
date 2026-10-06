const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, Browsers } = require("@whiskeysockets/baileys");
const pino = require("pino");
const http = require("http");
const fs = require("fs");
const path = require("path");

const PORT = 8085;
const AUTH_DIR = path.join(__dirname, "auth_info");
const DEFAULT_NUMBER = "359886057528";

let sock = null;
let isConnected = false;
let currentPairingCode = null;
let userPhone = null;

async function startWhatsApp(targetNumber = DEFAULT_NUMBER) {
  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

  sock = makeWASocket({
    auth: state,
    printQRInTerminal: false,
    logger: pino({ level: "silent" }),
    browser: Browsers.macOS("Chrome")
  });

  sock.ev.on("creds.update", saveCreds);

  // Inbound messages listener -> forward to n8n AI webhook
  sock.ev.on("messages.upsert", async (m) => {
    try {
      if (m.type !== "notify") return;
      for (const msg of m.messages) {
        // Skip outgoing messages from us or status updates
        if (!msg.message || msg.key.fromMe || msg.key.remoteJid === "status@broadcast") continue;
        
        // Skip group messages for 1-on-1 CRM concierge
        if (msg.key.remoteJid.endsWith("@g.us")) continue;

        const senderJid = msg.key.remoteJid;
        let senderPhone = senderJid.replace(/[^0-9]/g, "");
        
        // If sender sent via LID (Meta WhatsApp Privacy ID), resolve to real phone number from auth store
        if (senderJid.endsWith("@lid")) {
          const revFile = path.join(AUTH_DIR, `lid-mapping-${senderPhone}_reverse.json`);
          if (fs.existsSync(revFile)) {
            try {
              senderPhone = JSON.parse(fs.readFileSync(revFile, "utf8"));
            } catch (e) {}
          }
        }

        const pushName = msg.pushName || "";
        
        // Extract text content from various message types
        const text = msg.message.conversation ||
                     msg.message.extendedTextMessage?.text ||
                     msg.message.imageMessage?.caption ||
                     msg.message.videoMessage?.caption ||
                     "";

        if (!text.trim()) continue;

        console.log(`[WHATSAPP INBOUND] Message from ${pushName} (+${senderPhone}): "${text}"`);

        // Forward to local n8n instance via HTTP
        const payload = JSON.stringify({
          phone: senderPhone,
          jid: senderJid,
          pushName: pushName,
          text: text,
          messageId: msg.key.id,
          timestamp: msg.messageTimestamp
        });

        const req = http.request("http://127.0.0.1:5678/webhook/kalimera-whatsapp-inbound", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Content-Length": Buffer.byteLength(payload)
          },
          timeout: 10000
        }, (res) => {
          console.log(`[WHATSAPP INBOUND -> n8n] Status: ${res.statusCode}`);
        });

        req.on("error", (err) => {
          console.error(`[WHATSAPP INBOUND -> n8n] Error forwarding:`, err.message);
        });

        req.write(payload);
        req.end();
      }
    } catch (err) {
      console.error("[WHATSAPP INBOUND] Error handling messages.upsert:", err);
    }
  });

  sock.ev.on("connection.update", async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (connection === "open") {
      isConnected = true;
      currentPairingCode = null;
      userPhone = sock.user?.id ? sock.user.id.split(":")[0] : targetNumber;
      console.log(`[WHATSAPP] Connected successfully as ${userPhone}!`);
    } else if (connection === "close") {
      isConnected = false;
      const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
      console.log(`[WHATSAPP] Connection closed. Reconnecting: ${shouldReconnect}...`);
      if (shouldReconnect) {
        setTimeout(() => startWhatsApp(targetNumber), 3000);
      }
    }
  });

  // If not already registered, generate pairing code after a short delay
  if (!sock.authState.creds.registered && targetNumber) {
    setTimeout(async () => {
      try {
        const cleanNumber = targetNumber.replace(/[^0-9]/g, "");
        currentPairingCode = await sock.requestPairingCode(cleanNumber);
        console.log(`👉 LIVE PAIRING CODE FOR ${cleanNumber}: ${currentPairingCode}`);
        fs.writeFileSync(path.join(__dirname, "pairing_code.txt"), currentPairingCode);
      } catch (err) {
        console.error("[WHATSAPP] Error requesting pairing code:", err.message);
      }
    }, 3000);
  }
}

// REST API Server
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  res.setHeader("Content-Type", "application/json");
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") {
    res.writeHead(200);
    return res.end();
  }

  // 1. GET STATUS
  if (url.pathname === "/status" && req.method === "GET") {
    res.writeHead(200);
    return res.end(JSON.stringify({
      connected: isConnected,
      user: isConnected ? sock.user : null,
      pairingCode: currentPairingCode,
      phone: userPhone
    }));
  }

  // 2. GET PAIRING CODE
  if (url.pathname === "/pair" && req.method === "GET") {
    const num = url.searchParams.get("number") || DEFAULT_NUMBER;
    if (isConnected) {
      res.writeHead(200);
      return res.end(JSON.stringify({ connected: true, message: "Already connected" }));
    }
    try {
      const cleanNumber = num.replace(/[^0-9]/g, "");
      currentPairingCode = await sock.requestPairingCode(cleanNumber);
      res.writeHead(200);
      return res.end(JSON.stringify({
        success: true,
        pairingCode: currentPairingCode,
        number: cleanNumber
      }));
    } catch (e) {
      res.writeHead(500);
      return res.end(JSON.stringify({ success: false, error: e.message }));
    }
  }

  // 3. POST SEND MESSAGE
  if (url.pathname === "/send" && req.method === "POST") {
    let body = "";
    req.on("data", chunk => body += chunk);
    req.on("end", async () => {
      try {
        const data = JSON.parse(body || "{}");
        const rawNum = data.number || data.phone || "";
        const text = data.text || data.message || "";
        let cleanNum = rawNum.replace(/[^0-9]/g, "");

        if (!cleanNum || !text) {
          res.writeHead(400);
          return res.end(JSON.stringify({ success: false, error: "Number and text are required" }));
        }

        if (!isConnected || !sock) {
          res.writeHead(503);
          return res.end(JSON.stringify({ success: false, error: "WhatsApp is not connected. Please pair your device first." }));
        }

        // If number is a LID, check reverse mapping to get actual phone
        const revFile = path.join(AUTH_DIR, `lid-mapping-${cleanNum}_reverse.json`);
        if (fs.existsSync(revFile)) {
          try {
            cleanNum = JSON.parse(fs.readFileSync(revFile, "utf8"));
          } catch (e) {}
        }

        const jid = `${cleanNum}@s.whatsapp.net`;
        const result = await sock.sendMessage(jid, { text: text });

        res.writeHead(200);
        return res.end(JSON.stringify({
          success: true,
          messageId: result.key.id,
          to: cleanNum
        }));
      } catch (err) {
        res.writeHead(500);
        return res.end(JSON.stringify({ success: false, error: err.message }));
      }
    });
    return;
  }

  res.writeHead(404);
  res.end(JSON.stringify({ error: "Endpoint not found" }));
});

server.listen(PORT, "0.0.0.0", () => {
  console.log(`[HTTP] WhatsApp Service listening on http://0.0.0.0:${PORT}`);
  startWhatsApp(DEFAULT_NUMBER);
});
