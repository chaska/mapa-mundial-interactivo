ALTER TABLE "#__mapaviajes_photos" ALTER COLUMN "filename" TYPE character varying(120);
ALTER TABLE "#__mapaviajes_trips" ADD COLUMN IF NOT EXISTS "trip_year" smallint NOT NULL DEFAULT 0;
