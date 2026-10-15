CREATE TABLE IF NOT EXISTS `#__mapaviajes_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int unsigned NOT NULL,
  `reporter_id` int unsigned NOT NULL,
  `reason` varchar(16) NOT NULL,
  `note` varchar(500) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report` (`trip_id`, `reporter_id`),
  KEY `idx_reporter` (`reporter_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
