CREATE DATABASE IF NOT EXISTS db_network;
USE db_network;

-- Tabel Pengguna Login (Admin & Teknisi)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teknisi') DEFAULT 'teknisi'
);

-- Tabel Nomor WhatsApp Tujuan Notifikasi
CREATE TABLE IF NOT EXISTS wa_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(50) NOT NULL
);

-- Tabel Perangkat Induk (Router, Switch, OLT, dll)
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    status VARCHAR(20) DEFAULT 'DOWN',
    last_checked DATETIME NULL,
    system_uptime VARCHAR(50) DEFAULT '-',
    cpu_load VARCHAR(50) DEFAULT '-',
    free_ram VARCHAR(50) DEFAULT '-',
    pppoe_active VARCHAR(50) DEFAULT '-',
    pppoe_list LONGTEXT NULL,
    api_user VARCHAR(100) NULL,
    api_password VARCHAR(100) NULL,
    api_port INT DEFAULT 8728,
    notif_state VARCHAR(20) DEFAULT 'NONE',
    FOREIGN KEY (parent_id) REFERENCES devices(id) ON DELETE SET NULL
);

-- Insert Default Admin (Password: admin123)
INSERT IGNORE INTO users (username, password, role) 
VALUES ('admin', '$2y$10$Q1p/10A6Z.jR9b/10Z.jR.0b/10Z.jR9b/10Z.jR9b/10Z.jR9b/10', 'admin'); 
-- (Catatan: Password asli akan digenerate ulang otomatis oleh index.php jika tabel users kosong)