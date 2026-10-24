CREATE TABLE IF NOT EXISTS "#__mapaviajes_albums" (
  "id" serial NOT NULL,
  "title" character varying(150) NOT NULL,
  "description" text NULL,
  "published" smallint NOT NULL DEFAULT 1,
  "access" bigint NOT NULL DEFAULT 2,
  "allow_comments" smallint NOT NULL DEFAULT 1,
  "ordering" bigint NOT NULL DEFAULT 0,
  "created" timestamp without time zone NOT NULL,
  "created_by" bigint NOT NULL DEFAULT 0,
  "logo" character varying(40) NOT NULL DEFAULT '',
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__mapaviajes_albums_idx_published" ON "#__mapaviajes_albums" ("published");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_albums_idx_access" ON "#__mapaviajes_albums" ("access");

CREATE TABLE IF NOT EXISTS "#__mapaviajes_trips" (
  "id" serial NOT NULL,
  "album_id" bigint NOT NULL,
  "user_id" bigint NOT NULL,
  "title" character varying(80) NOT NULL,
  "place" character varying(120) NOT NULL DEFAULT '',
  "lat" numeric(9,6) NOT NULL,
  "lon" numeric(9,6) NOT NULL,
  "allow_comments" smallint NOT NULL DEFAULT 1,
  "notify" smallint NOT NULL DEFAULT 0,
  "published" smallint NOT NULL DEFAULT 0,
  "photo_count" smallint NOT NULL DEFAULT 0,
  "trip_year" smallint NOT NULL DEFAULT 0,
  "article_id" bigint NULL DEFAULT NULL,
  "asset_id" bigint NOT NULL DEFAULT 0,
  "created" timestamp without time zone NOT NULL,
  "modified" timestamp without time zone NULL DEFAULT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__mapaviajes_trips_idx_album_published" ON "#__mapaviajes_trips" ("album_id", "published");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_trips_idx_user_created" ON "#__mapaviajes_trips" ("user_id", "created");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_trips_idx_published_created" ON "#__mapaviajes_trips" ("published", "created");

CREATE TABLE IF NOT EXISTS "#__mapaviajes_photos" (
  "id" serial NOT NULL,
  "trip_id" bigint NOT NULL,
  "ordering" smallint NOT NULL DEFAULT 0,
  "filename" character varying(120) NOT NULL,
  "width" integer NOT NULL,
  "height" integer NOT NULL,
  "bytes" bigint NOT NULL,
  "caption" text NULL,
  "friends_only" smallint NOT NULL DEFAULT 0,
  "state" smallint NOT NULL DEFAULT 1,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_photos_uq_filename" ON "#__mapaviajes_photos" ("filename");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_photos_idx_trip" ON "#__mapaviajes_photos" ("trip_id", "ordering");

CREATE TABLE IF NOT EXISTS "#__mapaviajes_events" (
  "id" serial NOT NULL,
  "user_id" bigint NOT NULL DEFAULT 0,
  "event" character varying(40) NOT NULL,
  "detail" character varying(190) NOT NULL DEFAULT '',
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__mapaviajes_events_idx_created" ON "#__mapaviajes_events" ("created");

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

INSERT INTO "#__mapaviajes_albums" ("title", "description", "published", "access", "allow_comments", "ordering", "created", "created_by")
SELECT 'Nuestro mundo en fotos', '', 1, 2, 1, 1, NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM "#__mapaviajes_albums");

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
  "digest" smallint NOT NULL DEFAULT 0,
  "follows_seen" timestamp without time zone NULL,
  "digest_sent" timestamp without time zone NULL,
  PRIMARY KEY ("user_id")
);

CREATE TABLE IF NOT EXISTS "#__mapaviajes_follows" (
  "id" serial NOT NULL,
  "follower_id" bigint NOT NULL,
  "followed_id" bigint NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__mapaviajes_follows_uq" ON "#__mapaviajes_follows" ("follower_id", "followed_id");
CREATE INDEX IF NOT EXISTS "#__mapaviajes_follows_idx_followed" ON "#__mapaviajes_follows" ("followed_id");


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
