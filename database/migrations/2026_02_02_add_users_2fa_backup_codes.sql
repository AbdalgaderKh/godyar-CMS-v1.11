-- Add backup codes column (optional if already exists)
ALTER TABLE users
ADD COLUMN twofa_backup_codes TEXT NULL AFTER twofa_secret;

-- (Optional) index for faster lookups (if needed)
-- CREATE INDEX idx_users_twofa_enabled ON users(twofa_enabled);
