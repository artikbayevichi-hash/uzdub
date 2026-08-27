-- Add cover_photo column to users table (profile banner image)
-- Safe to run multiple times — uses ADD COLUMN IF NOT EXISTS pattern
ALTER TABLE `users` ADD COLUMN `cover_photo` VARCHAR(255) DEFAULT NULL AFTER `avatar`;
