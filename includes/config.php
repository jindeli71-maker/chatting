<?php
/**
 * Database configuration
 *
 * For cPanel + phpMyAdmin (MySQL):
 *   1. Import cpanel_mysql.sql in phpMyAdmin
 *   2. Set USE_SQLITE = false below
 *   3. Fill DB_HOST / DB_NAME / DB_USER / DB_PASS from cPanel
 *
 * For SQLite (local XAMPP / simple upload of chatting.db):
 *   Keep USE_SQLITE = true
 */
const USE_SQLITE = true;

// --- MySQL (cPanel) — used only when USE_SQLITE = false ---
const DB_HOST = 'localhost';
const DB_NAME = 'your_cpanel_db_name';
const DB_USER = 'your_cpanel_db_user';
const DB_PASS = 'your_cpanel_db_password';
const DB_CHARSET = 'utf8mb4';

const SQLITE_PATH = __DIR__ . '/../database/chatting.db';
