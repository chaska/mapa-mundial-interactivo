CREATE TABLE IF NOT EXISTS "#__mapaviajes_follows" (
  "id" serial NOT NULL,
  "follower_id" bigint NOT NULL,
  "followed_id" bigint NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_follows_uq" ON "#__mapaviajes_follows" ("follower_id", "followed_id");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_follows_idx_followed" ON "#__mapaviajes_follows" ("followed_id");
ALTER TABLE "#__mapaviajes_prefs" ADD COLUMN IF NOT EXISTS "digest" smallint NOT NULL DEFAULT 0;
ALTER TABLE "#__mapaviajes_prefs" ADD COLUMN IF NOT EXISTS "follows_seen" timestamp without time zone NULL;
ALTER TABLE "#__mapaviajes_prefs" ADD COLUMN IF NOT EXISTS "digest_sent" timestamp without time zone NULL;
