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

