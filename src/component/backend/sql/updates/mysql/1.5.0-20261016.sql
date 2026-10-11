ALTER TABLE `#__mapaviajes_photos` MODIFY `filename` varchar(120) NOT NULL;
ALTER TABLE `#__mapaviajes_trips` ADD COLUMN `trip_year` smallint unsigned NOT NULL DEFAULT 0;
