const ping = require('ping');
const mysql = require('mysql2/promise');
const snmp = require('net-snmp');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const { RouterOSAPI } = require('node-routeros'); 

const dbConfig = { host: 'localhost', user: 'root', password: '', database: 'db_network' };

function getWaktuWIB() {
    let d = new Date(new Date().toLocaleString("en-US", {timeZone: "Asia/Jakarta"}));
    let pad = (n) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

console.log('--- Menyiapkan Bot WhatsApp ---');
const waClient = new Client({
    authStrategy: new LocalAuth(), 
    puppeteer: { headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'], timeout: 0 } 
});

let isWaReady = false;

waClient.on('qr', (qr) => { qrcode.generate(qr, { small: true }); });
waClient.on('ready', () => { console.log('\n✅ BINGO! WhatsApp Bot Berhasil Terhubung!'); isWaReady = true; });

// 🚀 FITUR WA AUTO-REPLY (ANTI @LID MULTI-DEVICE)
waClient.on('message', async (msg) => {
    const pesan = msg.body.toLowerCase().trim();

    if (pesan.startsWith('!cek ')) {
        const query = pesan.replace('!cek ', '').trim();
        if (!query) return msg.reply('❌ Masukkan nama pelanggan yang dicari.\nContoh: *!cek kurniawan*');

        try {
            // Ekstrak kontak asli untuk mem-bypass @lid
            const contact = await msg.getContact();
            const senderNumber = contact.id._serialized; 
            
            console.log(`[WA BOT] Menerima perintah "!cek ${query}" dari: ${senderNumber}`);

            const connection = await mysql.createConnection(dbConfig);
            
            // SECURITY CHECK
            const [contacts] = await connection.execute('SELECT name FROM wa_contacts WHERE phone_number = ?', [senderNumber]);
            if (contacts.length === 0) {
                await connection.end();
                console.log(`[WA BOT] Akses ditolak untuk ${senderNumber}`);
                return msg.reply(`⛔ *AKSES DITOLAK*\nNomor Anda (*${senderNumber}*) belum terdaftar sebagai Teknisi J2H GROUP di sistem.`);
            }

            const [devices] = await connection.execute('SELECT name, pppoe_list FROM devices WHERE pppoe_list IS NOT NULL');
            await connection.end();

            let foundUsers = [];

            devices.forEach(device => {
                if(device.pppoe_list && device.pppoe_list !== 'null') {
                    try {
                        const usersArray = JSON.parse(device.pppoe_list);
                        const matched = usersArray.filter(u => u.name.toLowerCase().includes(query));
                        matched.forEach(m => { foundUsers.push({ routerName: device.name, ...m }); });
                    } catch(e) {}
                }
            });

            if (foundUsers.length > 0) {
                let replyText = `🔍 *HASIL PENCARIAN PPPoE*\nKata kunci: _${query}_\n\n`;
                foundUsers.forEach((u, index) => {
                    replyText += `*${index + 1}. ${u.name.toUpperCase()}*\n`;
                    replyText += `📍 Router: ${u.routerName}\n`;
                    replyText += `🌐 IP Addr: ${u.ip}\n`;
                    replyText += `⏱️ Uptime: ${u.uptime}\n`;
                    replyText += `⇅ Trafik: ${u.txrx}\n\n`;
                });
                replyText += `_Sistem Monitoring J2H GROUP_`;
                msg.reply(replyText);
            } else {
                msg.reply(`❌ Klien dengan nama *${query}* tidak ditemukan atau sedang offline.`);
            }

        } catch (error) {
            console.error('Error Fitur WA Auto-Reply:', error.message);
            msg.reply('⚠️ Maaf, sistem sedang sibuk memproses data. Coba lagi.');
        }
    }
});

waClient.initialize();

async function broadcastNotifikasi(pesan) {
    if (!isWaReady) return;
    try {
        const connection = await mysql.createConnection(dbConfig);
        const [contacts] = await connection.execute('SELECT phone_number FROM wa_contacts');
        await connection.end();
        if(contacts.length === 0) return;
        for (let contact of contacts) {
            try {
                const numberId = await waClient.getNumberId(contact.phone_number);
                if (numberId) await waClient.sendMessage(numberId._serialized, pesan);
            } catch (err) {}
        }
    } catch (dbErr) {}
}

function getSnmpData(ipAddress, community = 'public') {
    return new Promise((resolve) => {
        const [targetIp, targetPort] = ipAddress.split(':');
        const portNumber = targetPort ? parseInt(targetPort) : 161;
        const session = snmp.createSession(targetIp, community, { port: portNumber, version: snmp.Version2c, timeouts: [1000] });
        const oids = ['1.3.6.1.2.1.1.3.0', '1.3.6.1.2.1.25.3.3.1.2.1', '1.3.6.1.4.1.14988.1.1.1.2.0'];
        let result = { uptime: '-', cpu: '-', ram: '-' };

        session.get(oids, (error, varbinds) => {
            if (!error) {
                varbinds.forEach((vb) => {
                    if (!snmp.isVarbindError(vb)) {
                        let oidStr = vb.oid.toString();
                        if (oidStr === '1.3.6.1.2.1.1.3.0') {
                            let sec = Math.floor(vb.value / 100); let h = Math.floor(sec / 3600); let m = Math.floor((sec % 3600) / 60);
                            result.uptime = `${h}j ${m}m`;
                        } else if (oidStr === '1.3.6.1.2.1.25.3.3.1.2.1') result.cpu = `${vb.value}%`;
                        else if (oidStr === '1.3.6.1.4.1.14988.1.1.1.2.0') result.ram = `${Math.floor(vb.value / (1024 * 1024))} MB`;
                    }
                });
            }
            session.close(); resolve(result);
        });
    });
}

const apiSessions = {}; 

async function getMikrotikPppoeData(ip, port, user, password) {
    if (!user || !password) return { count: '-', list: null };
    const targetIp = ip.split(':')[0];
    const apiPort = port ? parseInt(port) : 8728;
    const sessionKey = `${targetIp}:${apiPort}`;

    try {
        if (!apiSessions[sessionKey]) {
            apiSessions[sessionKey] = new RouterOSAPI({ host: targetIp, port: apiPort, user: user, password: password, timeout: 5000 });
            apiSessions[sessionKey].on('error', () => { delete apiSessions[sessionKey]; });
            apiSessions[sessionKey].on('timeout', () => { delete apiSessions[sessionKey]; });
            await apiSessions[sessionKey].connect();
        }

        const activeUsers = await apiSessions[sessionKey].write('/ppp/active/print');
        let interfaceData = [];
        try { interfaceData = await apiSessions[sessionKey].write('/interface/print', ['?type=pppoe-in']); } catch(e) {}

        const userList = activeUsers.map(u => {
            let txrx = "0/0 MB"; 
            if(interfaceData.length > 0) {
                const iface = interfaceData.find(i => i.name === `<pppoe-${u.name}>`);
                if(iface) {
                    const rx = (parseInt(iface['rx-byte'] || 0) / 1048576).toFixed(1);
                    const tx = (parseInt(iface['tx-byte'] || 0) / 1048576).toFixed(1);
                    txrx = `TX ${tx}M / RX ${rx}M`;
                }
            }
            return { name: u.name, ip: u.address || '-', uptime: u.uptime || '-', txrx: txrx };
        });

        return { count: `${activeUsers.length} User`, list: JSON.stringify(userList) };
    } catch (e) {
        if (apiSessions[sessionKey]) { apiSessions[sessionKey].close().catch(()=>{}); delete apiSessions[sessionKey]; }
        return { count: '-', list: null };
    }
}

let isProcessing = false;

async function monitorDevices() {
    if (isProcessing) return;
    isProcessing = true;

    try {
        const connection = await mysql.createConnection(dbConfig);
        const [devices] = await connection.execute('SELECT * FROM devices');

        for (const device of devices) {
            let ipForPing = device.ip_address.split(':')[0];
            let res = await ping.promise.probe(ipForPing);
            let currentStatus = res.alive ? 'UP' : 'DOWN';
            let timestamp = getWaktuWIB(); 
            let snmpData = { uptime: '-', cpu: '-', ram: '-' };
            let pppoeData = { count: '-', list: null };

            if (currentStatus === 'UP') {
                snmpData = await getSnmpData(device.ip_address, 'public');
                pppoeData = await getMikrotikPppoeData(device.ip_address, device.api_port, device.api_user, device.api_password);
            }

            if (isWaReady) {
                try {
                    if (currentStatus === 'DOWN' && device.notif_state !== 'DOWN_SENT') {
                        await connection.execute(`UPDATE devices SET notif_state = 'DOWN_SENT' WHERE id = ?`, [device.id]);
                        await broadcastNotifikasi(`🚨 *J2H ALERT: NETWORK DOWN* 🚨\n\n📌 *Nama:* ${device.name}\n🌐 *IP:* ${device.ip_address}\n🕒 *Waktu:* ${timestamp}`);
                    }
                    else if (currentStatus === 'UP' && device.notif_state === 'NONE') {
                        await connection.execute(`UPDATE devices SET notif_state = 'UP_SENT' WHERE id = ?`, [device.id]);
                        await broadcastNotifikasi(`ℹ️ *J2H INFO: PERANGKAT TERHUBUNG* ℹ️\n\n📌 *Nama:* ${device.name}\n🌐 *IP:* ${device.ip_address}\n⏱️ *Uptime:* ${snmpData.uptime}`);
                    }
                    else if (currentStatus === 'UP' && device.notif_state === 'DOWN_SENT') {
                        await connection.execute(`UPDATE devices SET notif_state = 'UP_SENT' WHERE id = ?`, [device.id]);
                        await broadcastNotifikasi(`✅ *J2H INFO: NETWORK PULIH* ✅\n\n📌 *Nama:* ${device.name}\n🌐 *IP:* ${device.ip_address}\n⏱️ *Uptime:* ${snmpData.uptime}`);
                    }
                } catch (waErr) {}
            }

            await connection.execute(
                `UPDATE devices SET status = ?, last_checked = ?, system_uptime = ?, cpu_load = ?, free_ram = ?, pppoe_active = ?, pppoe_list = ? WHERE id = ?`,
                [currentStatus, timestamp, snmpData.uptime, snmpData.cpu, snmpData.ram, pppoeData.count, pppoeData.list, device.id]
            );
        }
        await connection.end();
    } catch (error) {} finally { isProcessing = false; }
}

setInterval(monitorDevices, 10000);
monitorDevices();