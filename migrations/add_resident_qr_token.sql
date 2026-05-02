-- Migration: Add qr_token to residents table for secure QR ID cards
ALTER TABLE residents ADD COLUMN qr_token VARCHAR(64) UNIQUE DEFAULT NULL;

-- Backfill existing residents with unique tokens
-- SHA2-256 returns exactly 64 hex characters, compatible with MySQL 5.5+ / MariaDB
UPDATE residents SET qr_token = SHA2(CONCAT(id, NOW(), RAND()), 256) WHERE qr_token IS NULL;
