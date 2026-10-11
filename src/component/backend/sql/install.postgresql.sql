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
  "article_id" bigint NULL DEFAULT NULL,
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
  "filename" character varying(40) NOT NULL,
  "width" integer NOT NULL,
  "height" integer NOT NULL,
  "bytes" bigint NOT NULL,
  "caption" text NULL,
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
