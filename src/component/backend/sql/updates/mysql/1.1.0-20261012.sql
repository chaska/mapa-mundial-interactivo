CREATE TABLE IF NOT EXISTS `#__mapaviajes_reactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `kind` varchar(8) NOT NULL,
  `active` tinyint NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `changed` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reaction` (`trip_id`, `user_id`, `kind`),
  KEY `idx_user_created` (`user_id`, `created`),
  KEY `idx_trip_kind` (`trip_id`, `kind`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
