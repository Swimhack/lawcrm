-- Lead Gen Fields Migration
-- Adds source tracking, location, notes, and UTM fields to leads table

ALTER TABLE leads
  ADD COLUMN source VARCHAR(100) DEFAULT 'direct',
  ADD COLUMN city VARCHAR(100) DEFAULT '',
  ADD COLUMN state VARCHAR(2) DEFAULT '',
  ADD COLUMN notes TEXT DEFAULT NULL,
  ADD COLUMN utm_source VARCHAR(100) DEFAULT '',
  ADD COLUMN utm_medium VARCHAR(100) DEFAULT '',
  ADD COLUMN utm_campaign VARCHAR(100) DEFAULT '',
  ADD INDEX idx_source (source),
  ADD INDEX idx_state (state);
