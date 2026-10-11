CREATE TABLE IF NOT EXISTS `#__mapaviajes_weeks` (
  `week_start` date NOT NULL,
  `closed` datetime NOT NULL,
  PRIMARY KEY (`week_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mapaviajes_ranking` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `week_start` date NOT NULL,
  `user_id` int unsigned NOT NULL,
  `position` tinyint unsigned NOT NULL,
  `score` int unsigned NOT NULL DEFAULT 0,
  `trips` smallint unsigned NOT NULL DEFAULT 0,
  `photos` smallint unsigned NOT NULL DEFAULT 0,
  `likes` smallint unsigned NOT NULL DEFAULT 0,
  `comments` smallint unsigned NOT NULL DEFAULT 0,
  `stars` tinyint unsigned NOT NULL DEFAULT 0,
  `points` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `#__mapaviajes_ranking_uq` (`week_start`, `user_id`),
  KEY `#__mapaviajes_ranking_idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mapaviajes_prefs` (
  `user_id` int unsigned NOT NULL,
  `hide_ranking` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
