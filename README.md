# 📊 Network Monitoring & PPPoE System

Sistem Network Operations Center (NOC) komprehensif yang memadukan Web Dashboard interaktif berbasis PHP dan Bot WhatsApp otomatis berbasis Node.js (`whatsapp-web.js`). Sistem ini terintegrasi langsung dengan MikroTik API untuk melacak status jaringan dan pelanggan secara *real-time*.

## ✨ Fitur Unggulan
* 🌐 **Topologi Visual:** Peta jaringan otomatis dan interaktif bergaya *The Dude*.
* 📈 **SNMP Metrik:** Membaca status *Uptime*, *CPU Load*, dan *Free RAM* router secara langsung.
* 👥 **PPPoE Live Tracking:** Menarik data pelanggan aktif (Nama, IP, Uptime, TX/RX bytes) dari MikroTik.
* 🔔 **WhatsApp Auto-Notif:** Broadcast pesan otomatis ke teknisi saat jaringan mengalami *DOWN* atau *UP*.
* 🤖 **WA Auto-Reply (`!cek`):** Teknisi dapat mengecek status spesifik pelanggan PPPoE langsung via WhatsApp tanpa perlu login ke web.
* 🔐 **RBAC System:** Keamanan sistem Login dengan hak akses Administrator (Full Access) dan Teknisi (Read-Only).

---

## 🚀 Panduan Instalasi (Ubuntu 20.04 / 22.04 LTS)

Ikuti langkah-langkah di bawah ini untuk menginstal sistem ini dari nol pada VPS Ubuntu Anda.

### Tahap 1: Instalasi Web Server & Database
Sistem dashboard membutuhkan lingkungan LAMP Stack (Linux, Apache, MySQL, PHP).

```bash
# 1. Update sistem Ubuntu
sudo apt update && sudo apt upgrade -y

# 2. Instal Apache, PHP, dan ekstensi yang dibutuhkan
sudo apt install apache2 php libapache2-mod-php php-mysql php-curl php-json php-mbstring -y

# 3. Instal MySQL Server
sudo apt install mysql-server -y

Tahap 2: Konfigurasi Database
Buat database untuk menyimpan data topologi dan akun. (Tabel akan dibuat secara otomatis oleh sistem saat web pertama kali diakses).

Bash
sudo mysql
Di dalam terminal MySQL, jalankan perintah ini:

SQL
CREATE DATABASE db_network;
-- Ganti 'root' dan password sesuai kebutuhan server Anda
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
FLUSH PRIVILEGES;
EXIT;
Tahap 3: Download Project (Git Clone)
Unduh repositori ini dan pindahkan ke folder publik web server Anda.

Bash
# 1. Pindah ke direktori web root
cd /var/www/html

# 2. Clone repositori ini (Ganti URL dengan link GitHub Anda)
sudo git clone [https://github.com/USERNAME_ANDA/network-worker.git](https://github.com/USERNAME_ANDA/network-worker.git) j2h-monitor

# 3. Beri hak akses ke folder tersebut
sudo chown -R www-data:www-data /var/www/html/j2h-monitor
sudo chmod -R 755 /var/www/html/j2h-monitor
Tahap 4: Instalasi Node.js & Bot WhatsApp
Bot WhatsApp bertugas sebagai worker di latar belakang untuk ping, SNMP, MikroTik API, dan Notifikasi.

Bash
# 1. Instal Node.js (Versi 18 LTS direkomendasikan)
curl -fsSL [https://deb.nodesource.com/setup_18.x](https://deb.nodesource.com/setup_18.x) | sudo -E bash -
sudo apt install -y nodejs

# 2. Instal dependensi Chromium (Wajib agar WhatsApp Web js bisa jalan di VPS)
sudo apt install -y chromium-browser libgbm-dev libnss3 libatk-bridge2.0-0 libxcomposite1 libxrandr2 libxdamage1 libasound2

# 3. Masuk ke folder proyek
cd /var/www/html/j2h-monitor

# 4. Instal library NPM yang dibutuhkan bot
npm install ping mysql2 net-snmp whatsapp-web.js qrcode-terminal node-routeros

# 5. Instal PM2 untuk menjalankan bot secara permanen
sudo npm install -g pm2
Tahap 5: Menjalankan Sistem
Sekarang kita akan menautkan nomor WhatsApp bot dan menjalankannya.

Bash
# 1. Jalankan bot untuk pertama kali (Scan QR Code dengan WhatsApp Anda)
node app.js
(Tunggu hingga muncul tulisan "BINGO! WhatsApp Bot Berhasil Terhubung!", lalu tekan Ctrl + C untuk mematikan sementara).

Bash
# 2. Jalankan bot secara permanen dengan PM2
pm2 start app.js --name "j2h-bot"

# 3. Simpan konfigurasi PM2 agar auto-start saat VPS direstart
pm2 save
pm2 startup
🖥️ Cara Akses & Penggunaan
Buka browser Anda dan akses IP VPS: http://IP_VPS_ANDA/j2h-monitor

Sistem akan otomatis mendeteksi instalasi baru dan membuat akun Admin.

Login menggunakan kredensial bawaan:

Username: admin

Password: admin123

Segera masuk ke menu User Manager untuk mengubah password Anda!

Daftarkan nomor WA teknisi di menu WhatsApp Notif agar bisa menggunakan perintah !cek [nama pelanggan].

Dikembangkan dengan ❤️ untuk J2H GROUP.
