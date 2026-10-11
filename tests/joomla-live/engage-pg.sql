-- TEST ONLY: Engage ships MySQL tables only. This is the same schema for the disposable PostgreSQL test site.
CREATE TABLE IF NOT EXISTS jt_engage_comments (
  id bigserial PRIMARY KEY, parent_id bigint NULL, asset_id bigint NOT NULL, body text NOT NULL,
  name varchar(255) NULL, email varchar(255) NULL, ip varchar(64) NULL, user_agent varchar(255) NOT NULL DEFAULT '',
  enabled smallint NOT NULL DEFAULT 0, created timestamp NULL, created_by bigint NULL, modified timestamp NULL, modified_by bigint NULL);
CREATE INDEX IF NOT EXISTS jt_engage_comments_asset ON jt_engage_comments (asset_id);
CREATE TABLE IF NOT EXISTS jt_engage_unsubscribe (asset_id bigint NOT NULL, email varchar(255) NOT NULL, PRIMARY KEY (asset_id, email));
CREATE TABLE IF NOT EXISTS jt_engage_reactions (id bigserial PRIMARY KEY, comment_id bigint NOT NULL, user_id bigint NOT NULL, type smallint NOT NULL, created timestamp NOT NULL, UNIQUE (comment_id, user_id, type));
-- Engage's queries use MySQL's IFNULL
CREATE OR REPLACE FUNCTION ifnull(a anyelement, b anyelement) RETURNS anyelement AS 'SELECT COALESCE(a, b)' LANGUAGE sql IMMUTABLE;
