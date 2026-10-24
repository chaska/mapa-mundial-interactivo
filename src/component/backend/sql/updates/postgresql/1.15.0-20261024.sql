ALTER TABLE "#__mapaviajes_photos" ADD COLUMN IF NOT EXISTS "friends_only" smallint NOT NULL DEFAULT 0;
ALTER TABLE "#__mapaviajes_photos" ADD COLUMN IF NOT EXISTS "state" smallint NOT NULL DEFAULT 1;
CREATE TABLE IF NOT EXISTS "#__mapaviajes_friends" (
  "id" serial NOT NULL,
  "requester_id" bigint NOT NULL,
  "addressee_id" bigint NOT NULL,
  "status" smallint NOT NULL DEFAULT 0,
  "created" timestamp without time zone NOT NULL,
  "answered" timestamp without time zone NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_friends_uq" ON "#__mapaviajes_friends" ("requester_id", "addressee_id");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_friends_idx_addressee" ON "#__mapaviajes_friends" ("addressee_id", "status");
CREATE TABLE IF NOT EXISTS "#__mapaviajes_blocks" (
  "id" serial NOT NULL,
  "blocker_id" bigint NOT NULL,
  "blocked_id" bigint NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_blocks_uq" ON "#__mapaviajes_blocks" ("blocker_id", "blocked_id");
