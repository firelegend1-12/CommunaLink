-- Migration: Harden resident QR verification with token expiry and scan audit logging

ALTER TABLE residents
    ADD COLUMN IF NOT EXISTS qr_token_expires_at DATETIME DEFAULT NULL AFTER qr_token;

UPDATE residents
SET qr_token_expires_at = DATE_ADD(COALESCE(created_at, NOW()), INTERVAL 1 YEAR)
WHERE qr_token IS NOT NULL
  AND qr_token_expires_at IS NULL;

CREATE TABLE IF NOT EXISTS resident_qr_scans (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    resident_id INT(11) DEFAULT NULL,
    token_fingerprint CHAR(64) DEFAULT NULL,
    is_valid TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resident_qr_scans_resident (resident_id),
    KEY idx_resident_qr_scans_scanned_at (scanned_at),
    KEY idx_resident_qr_scans_valid (is_valid),
    CONSTRAINT fk_resident_qr_scans_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
