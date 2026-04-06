import 'dotenv/config';
import express from 'express';
import fs from 'fs';
import { makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import pino from 'pino';
import qrcode from 'qrcode-terminal';

// Suppress noise dari libsignal Bad MAC / session errors
process.on('unhandledRejection', (reason) => {
    const msg = String(reason);
    if (msg.includes('Bad MAC') || msg.includes('Key used already') || msg.includes('MessageCounter')) return;
    console.error('Unhandled rejection:', reason);
});

const app         = express();
const PORT        = process.env.WA_SERVER_PORT || 3000;
const SECRET      = process.env.WA_SECRET_KEY  || 'rahasia123';
const LARAVEL_URL = process.env.LARAVEL_URL    || `http://127.0.0.1:${process.env.LARAVEL_PORT || 8000}`;

app.use(express.json());

let sock        = null;
let isConnected = false;
let currentQR   = null;

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('./auth_info');
    const { version }          = await fetchLatestBaileysVersion();

    // Logger yang hanya tampilkan error fatal, suppress Bad MAC / session noise
    const logger = pino({ level: 'fatal' });

    sock = makeWASocket({
        version,
        logger,
        auth: state,
        printQRInTerminal: false,
        browser: ['Hotel Attendance', 'Chrome', '1.0.0'],
        getMessage: async () => undefined, // return undefined agar pesan lama di-skip
        shouldIgnoreJid: jid => jid.endsWith('@broadcast'), // abaikan broadcast
    });

    sock.ev.on('creds.update', saveCreds);

    // Handler pesan masuk
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        // Hanya proses pesan baru (notify), bukan history (append)
        if (type !== 'notify') return;

        for (const msg of messages) {
            // Abaikan pesan dari diri sendiri atau yang tidak punya konten
            if (msg.key.fromMe) continue;
            if (!msg.message) continue;

            const from = msg.key.remoteJid;

            // Abaikan pesan grup
            if (from.endsWith('@g.us')) continue;

            const text = (
                msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                msg.message.imageMessage?.caption ||
                ''
            ).trim().toLowerCase();

            if (!text) continue;

            console.log(`📩 Pesan dari ${from}: "${text}"`);

            // Kirim ke Laravel untuk diproses
            try {
                const res = await fetch(`${LARAVEL_URL}/wa-webhook`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'x-wa-secret': SECRET,
                    },
                    body: JSON.stringify({ from, text }),
                });

                // Cek content-type sebelum parse JSON
                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const body = await res.text();
                    console.error(`❌ Laravel return non-JSON (${res.status}):`, body.substring(0, 200));
                    return;
                }

                console.log(`📡 Laravel response status: ${res.status}`);
                const data = await res.json();
                console.log(`📡 Laravel response data:`, JSON.stringify(data));

                if (data.reply) {
                    // Support single string atau array of strings (multiple messages)
                    const replies = Array.isArray(data.reply) ? data.reply : [data.reply];
                    for (const replyMsg of replies) {
                        await sock.sendMessage(from, { text: replyMsg });
                        if (replies.length > 1) await new Promise(r => setTimeout(r, 500));
                    }
                    console.log(`↩️  Balas ke ${from}: ${replies[0].substring(0, 50)}...`);
                } else {
                    console.log(`🚫 Abaikan pesan dari nomor tidak terdaftar: ${from}`);
                }
            } catch (err) {
                console.error('Gagal proses pesan:', err.message);
            }
        }
    });

    sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
        if (qr) {
            currentQR   = qr;
            isConnected = false;
            console.log('\n========================================');
            console.log('  SCAN QR CODE INI DENGAN WHATSAPP KAMU');
            console.log('========================================\n');
            qrcode.generate(qr, { small: true });
            console.log('\nAtau buka browser: http://localhost:' + PORT + '/qr\n');
        }

        if (connection === 'open') {
            isConnected = true;
            currentQR   = null;
            console.log('✅ WhatsApp terhubung!');
        }

        if (connection === 'close') {
            isConnected = false;
            const statusCode = (lastDisconnect?.error instanceof Boom)
                ? lastDisconnect.error.output?.statusCode
                : null;

            console.log('⚠️  Koneksi terputus. Status:', statusCode);

            if (statusCode === DisconnectReason.loggedOut) {
                console.log('🔄 Sesi expired, hapus auth_info dan scan QR ulang...');
                if (fs.existsSync('./auth_info')) {
                    fs.rmSync('./auth_info', { recursive: true, force: true });
                }
            }

            // Selalu reconnect agar QR muncul lagi
            console.log('🔄 Reconnect dalam 3 detik...');
            setTimeout(connectToWhatsApp, 3000);
        }
    });
}

function authMiddleware(req, res, next) {
    const key = req.headers['x-api-key'] || req.body?.api_key;
    if (key !== SECRET) {
        return res.status(401).json({ status: false, message: 'Unauthorized' });
    }
    next();
}

app.get('/status', (req, res) => {
    res.json({ status: true, connected: isConnected, hasQR: !!currentQR });
});

app.get('/qr', (req, res) => {
    if (isConnected) {
        return res.send('<h2 style="font-family:sans-serif;color:green">✅ WhatsApp sudah terhubung!</h2>');
    }
    if (!currentQR) {
        return res.send('<h2 style="font-family:sans-serif">⏳ Menunggu QR code...</h2><script>setTimeout(()=>location.reload(),3000)</script>');
    }
    const encoded = encodeURIComponent(currentQR);
    res.send(`<!DOCTYPE html><html>
    <head><title>Scan QR WhatsApp</title><meta http-equiv="refresh" content="30">
    <style>body{font-family:sans-serif;text-align:center;padding:40px;background:#f0f0f0}</style></head>
    <body>
        <h2>📱 Scan QR Code dengan WhatsApp kamu</h2>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encoded}"
             style="border:8px solid white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.2)">
        <p style="color:#666">Auto-refresh tiap 30 detik</p>
        <p style="color:#999;font-size:12px">WhatsApp → Linked Devices → Link a Device</p>
    </body></html>`);
});

app.post('/send', authMiddleware, async (req, res) => {
    const { phone, message } = req.body;
    if (!phone || !message) return res.status(400).json({ status: false, message: 'phone dan message wajib diisi' });
    if (!isConnected) return res.status(503).json({ status: false, message: 'WhatsApp belum terhubung. Scan QR dulu.' });

    try {
        let normalized = phone.replace(/\D/g, '');
        if (normalized.startsWith('0')) normalized = '62' + normalized.slice(1);
        await sock.sendMessage(normalized + '@s.whatsapp.net', { text: message });
        console.log(`✉️  Terkirim ke ${normalized}`);
        res.json({ status: true, message: 'Pesan berhasil dikirim', to: normalized });
    } catch (err) {
        console.error('Gagal kirim:', err.message);
        res.status(500).json({ status: false, message: err.message });
    }
});

app.post('/send-bulk', authMiddleware, async (req, res) => {
    const { targets } = req.body;
    if (!Array.isArray(targets) || !targets.length) return res.status(400).json({ status: false, message: 'targets harus array' });
    if (!isConnected) return res.status(503).json({ status: false, message: 'WhatsApp belum terhubung' });

    const results = [];
    for (const { phone, message } of targets) {
        try {
            let normalized = phone.replace(/\D/g, '');
            if (normalized.startsWith('0')) normalized = '62' + normalized.slice(1);
            await sock.sendMessage(normalized + '@s.whatsapp.net', { text: message });
            results.push({ phone: normalized, status: 'sent' });
            await new Promise(r => setTimeout(r, 1000));
        } catch (err) {
            results.push({ phone, status: 'failed', error: err.message });
        }
    }
    res.json({ status: true, results });
});

app.listen(PORT, () => {
    console.log('========================================');
    console.log('  Hotel Attendance - WA Server (Baileys)');
    console.log(`  http://localhost:${PORT}`);
    console.log('========================================');
    console.log('  /status  - cek koneksi');
    console.log('  /qr      - scan QR di browser');
    console.log('  /send    - kirim pesan');
    console.log('========================================\n');
});

connectToWhatsApp();
