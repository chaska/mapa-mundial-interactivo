CREATE TABLE IF NOT EXISTS "#__mapaviajes_weeks" (
  "week_start" date NOT NULL,
  "closed" timestamp without time zone NOT NULL,
  PRIMARY KEY ("week_start")
);

CREATE TABLE IF NOT EXISTS "#__mapaviajes_ranking" (
  "id" serial NOT NULL,
  "week_start" date NOT NULL,
  "user_id" bigint NOT NULL,
  "position" smallint NOT NULL,
  "score" integer NOT NULL DEFAULT 0,
  "trips" smallint NOT NULL DEFAULT 0,
  "photos" smallint NOT NULL DEFAULT 0,
  "likes" smallint NOT NULL DEFAULT 0,
  "comments" smallint NOT NULL DEFAULT 0,
  "stars" smallint NOT NULL DEFAULT 0,
  "points" smallint NOT NULL DEFAULT 0,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_ranking_uq" ON "#__mapaviajes_ranking" ("week_start", "user_id");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_ranking_idx_user" ON "#__mapaviajes_ranking" ("user_id");

CREATE TABLE IF NOT EXISTS "#__mapaviajes_prefs" (
  "user_id" bigint NOT NULL,
  "hide_ranking" smallint NOT NULL DEFAULT 0,
  PRIMARY KEY ("user_id")
);
