CREATE TABLE IF NOT EXISTS "#__mapaviajes_reports" (
  "id" serial NOT NULL,
  "trip_id" bigint NOT NULL,
  "reporter_id" bigint NOT NULL,
  "reason" varchar(16) NOT NULL,
  "note" varchar(500) NOT NULL DEFAULT '',
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_reports_uq" ON "#__mapaviajes_reports" ("trip_id", "reporter_id");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_reports_idx_reporter" ON "#__mapaviajes_reports" ("reporter_id", "created");
