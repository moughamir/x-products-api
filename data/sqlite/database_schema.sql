-- SQLite Database Schema (SQL Format)
-- Generated from database_schema.md

-- ====================================================================
-- 1. `products` Table
-- The main table for storing normalized product data.
-- ====================================================================
CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    title TEXT,
    handle TEXT,
    body_html TEXT,
    vendor TEXT,
    product_type TEXT,
    created_at TEXT,
    updated_at TEXT,
    tags TEXT,
    source_domain TEXT,
    price REAL,
    compare_at_price REAL,
    in_stock INTEGER, -- 1 for true, 0 for false
    category TEXT,
    rating REAL DEFAULT 0.0,
    review_count INTEGER DEFAULT 0,
    bestseller_score REAL DEFAULT 0.0
);

-- ====================================================================
-- 2. `product_images` Table
-- Stores image metadata, linked to the `products` table.
-- ====================================================================
CREATE TABLE product_images (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    position INTEGER,
    src TEXT,
    width INTEGER,
    height INTEGER,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ====================================================================
-- 3. `products_fts` Virtual Table
-- FTS5 table for high-performance full-text search.
-- Links `rowid` directly to `products.id`.
-- ====================================================================
CREATE VIRTUAL TABLE products_fts USING fts5(
    title,
    body_html,
    content='products',
    content_rowid='id'
);
