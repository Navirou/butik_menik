-- ============================================================
--  SISTEM INFORMASI BUTIK MENIK MODESTE
--  Database Schema v1.0
--  Charset: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS butik_menik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE butik_menik;

-- ============================================================
-- 1. USERS & ROLES
-- ============================================================
CREATE TABLE roles (
    id       TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name     ENUM('owner','staff','supplier','customer') NOT NULL UNIQUE,
    label    VARCHAR(50) NOT NULL
);
INSERT INTO roles (name, label) VALUES
    ('owner',    'Owner / Admin'),
    ('staff',    'Staff Produksi'),
    ('supplier', 'Supplier'),
    ('customer', 'Pelanggan');

CREATE TABLE users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id      TINYINT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL UNIQUE,
    phone        VARCHAR(20),
    password     VARCHAR(255) NOT NULL,          -- bcrypt hash
    avatar       VARCHAR(255),
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 2. KATALOG PRODUK
-- ============================================================
CREATE TABLE product_categories (
    id    SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(80) NOT NULL,
    slug  VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE products (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id  SMALLINT UNSIGNED,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    base_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
    image        VARCHAR(255),
    is_custom    TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = item custom / made-to-order
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
);

-- ============================================================
-- 3. PESANAN (ORDERS)
-- ============================================================
CREATE TABLE orders (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code          VARCHAR(20) NOT NULL UNIQUE,   -- e.g. BSN001181225
    customer_id         INT UNSIGNED NOT NULL,
    product_id          INT UNSIGNED,
    staff_id            INT UNSIGNED,                  -- assigned staff
    -- Detail pesanan custom
    size                VARCHAR(30),
    fabric_type         VARCHAR(100),
    color               VARCHAR(80),
    model_description   TEXT,
    design_file         VARCHAR(255),                  -- uploaded file path
    qty                 SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    total_price         DECIMAL(12,2) NOT NULL DEFAULT 0,
    dp_amount           DECIMAL(12,2) NOT NULL DEFAULT 0,
    dp_percentage       TINYINT UNSIGNED NOT NULL DEFAULT 50,
    -- Status workflow
    status              ENUM(
                            'menunggu_konfirmasi',
                            'dikonfirmasi',
                            'ditolak',
                            'dp_menunggu',
                            'dp_diverifikasi',
                            'pemeriksaan_stok',
                            'produksi',
                            'qc',
                            'jadwal_ambil',
                            'selesai',
                            'revisi',
                            'dibatalkan'
                        ) NOT NULL DEFAULT 'menunggu_konfirmasi',
    -- Jadwal
    estimated_done      DATE,
    pickup_date         DATE,
    pickup_time_start   TIME,
    pickup_time_end     TIME,
    owner_notes         TEXT,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id)    REFERENCES users(id)    ON DELETE SET NULL
);

-- ============================================================
-- 4. PRODUKSI
-- ============================================================
CREATE TABLE production_stages (
    id    TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  ENUM('dp_verified','stock_check','cutting','sewing','finishing','qc','done') NOT NULL UNIQUE,
    label VARCHAR(60) NOT NULL,
    seq   TINYINT UNSIGNED NOT NULL
);
INSERT INTO production_stages (name, label, seq) VALUES
    ('dp_verified',  'DP Diverifikasi',   1),
    ('stock_check',  'Pemeriksaan Stok',  2),
    ('cutting',      'Pemotongan',        3),
    ('sewing',       'Penjahitan',        4),
    ('finishing',    'Finishing',         5),
    ('qc',           'Quality Control',  6),
    ('done',         'Selesai',          7);

CREATE TABLE production_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    stage_id    TINYINT UNSIGNED NOT NULL,
    updated_by  INT UNSIGNED NOT NULL,   -- staff user
    progress    TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0-100 %
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id)   REFERENCES production_stages(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE qc_results (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL UNIQUE,
    checked_by   INT UNSIGNED NOT NULL,
    passed       TINYINT(1) NOT NULL DEFAULT 0,
    notes        TEXT,
    checked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id)
);

-- ============================================================
-- 5. PEMBAYARAN
-- ============================================================
CREATE TABLE payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    type            ENUM('dp','pelunasan') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    method          VARCHAR(50),                    -- e.g. Bank BCA, GoPay
    proof_file      VARCHAR(255),                   -- upload path
    status          ENUM('menunggu','diverifikasi','ditolak') NOT NULL DEFAULT 'menunggu',
    verified_by     INT UNSIGNED,
    verified_at     DATETIME,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- 6. REVISI / KELUHAN
-- ============================================================
CREATE TABLE revisions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,   -- customer
    description  TEXT NOT NULL,
    status       ENUM('open','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
    handled_by   INT UNSIGNED,            -- owner / staff
    handler_note TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)     REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (handled_by)   REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- 7. BAHAN BAKU & STOK
-- ============================================================
CREATE TABLE materials (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT UNSIGNED,               -- main supplier
    name          VARCHAR(100) NOT NULL,
    unit          VARCHAR(20) NOT NULL,       -- meter, spool, pcs
    current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_stock     DECIMAL(10,2) NOT NULL DEFAULT 0,  -- reorder point
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE material_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code  VARCHAR(20) NOT NULL UNIQUE,  -- e.g. REQ-2025-001
    material_id   INT UNSIGNED NOT NULL,
    requested_by  INT UNSIGNED NOT NULL,        -- owner
    supplier_id   INT UNSIGNED NOT NULL,
    qty_requested DECIMAL(10,2) NOT NULL,
    qty_received  DECIMAL(10,2),
    priority      ENUM('regular','urgent') NOT NULL DEFAULT 'regular',
    status        ENUM('pending','accepted','shipped','received','cancelled') NOT NULL DEFAULT 'pending',
    invoice_file  VARCHAR(255),
    notes         TEXT,
    supplier_note TEXT,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id)   REFERENCES materials(id),
    FOREIGN KEY (requested_by)  REFERENCES users(id),
    FOREIGN KEY (supplier_id)   REFERENCES users(id)
);

CREATE TABLE stock_ledger (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_id  INT UNSIGNED NOT NULL,
    type         ENUM('in','out','adjustment') NOT NULL,
    qty          DECIMAL(10,2) NOT NULL,
    reference    VARCHAR(50),               -- request_code or order_code
    notes        TEXT,
    recorded_by  INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- ============================================================
-- 8. NOTIFIKASI
-- ============================================================
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       TEXT NOT NULL,
    type       VARCHAR(50),                -- 'order_update','payment','pickup','revision'
    ref_id     INT UNSIGNED,               -- related order_id / payment_id
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 9. SAMPLE SEED DATA
-- ============================================================
-- Default owner account (password: Admin@1234)
INSERT INTO users (role_id, name, email, phone, password) VALUES
    (1, 'Menik Wijaya',    'owner@butikmenik.id',    '08111000001', '$2y$12$examplehashedpassword.owner'),
    (2, 'Rina Produksi',   'staff@butikmenik.id',    '08111000002', '$2y$12$examplehashedpassword.staff'),
    (3, 'CV Kain Nusantara','supplier@butikmenik.id', '08111000003', '$2y$12$examplehashedpassword.supplier'),
    (4, 'Siti Rahma',      'customer@butikmenik.id', '08111000004', '$2y$12$examplehashedpassword.customer');

INSERT INTO product_categories (name, slug) VALUES
    ('Kemeja Batik', 'kemeja-batik'),
    ('Gamis Custom', 'gamis-custom'),
    ('Blazer Formal', 'blazer-formal'),
    ('Celana Formal', 'celana-formal');

INSERT INTO products (category_id, name, base_price, is_custom, is_active) VALUES
    (1, 'Kemeja Batik Custom', 350000, 1, 1),
    (2, 'Gamis Syari Polos',   450000, 1, 1),
    (3, 'Blazer Slim Fit',     500000, 1, 1),
    (1, 'Kemeja Batik Parang', 280000, 0, 1);

INSERT INTO materials (supplier_id, name, unit, current_stock, min_stock) VALUES
    (3, 'Kain Katun Putih',   'meter',  45.5,  20),
    (3, 'Benang Sutra Biru',  'spul',  180,    50),
    (3, 'Kancing Silver',     'pcs',   420,   100),
    (3, 'Kain Batik Parang',  'meter',  12,    10);
