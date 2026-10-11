CREATE TABLE IF NOT EXISTS "#__mapaviajes_events" (
  "id" serial NOT NULL,
  "user_id" bigint NOT NULL DEFAULT 0,
  "event" character varying(40) NOT NULL,
  "detail" character varying(190) NOT NULL DEFAULT '',
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
