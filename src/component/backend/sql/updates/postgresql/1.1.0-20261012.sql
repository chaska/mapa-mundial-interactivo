CREATE TABLE IF NOT EXISTS "#__mapaviajes_reactions" (
  "id" serial NOT NULL,
  "trip_id" bigint NOT NULL,
  "user_id" bigint NOT NULL,
  "kind" character varying(8) NOT NULL,
  "active" smallint NOT NULL DEFAULT 1,
  "created" timestamp without time zone NOT NULL,
  "changed" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_reactions_uq" ON "#__mapaviajes_reactions" ("trip_id", "user_id", "kind");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_reactions_idx_user_created" ON "#__mapaviajes_reactions" ("user_id", "created");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_reactions_idx_trip_kind" ON "#__mapaviajes_reactions" ("trip_id", "kind", "active");
