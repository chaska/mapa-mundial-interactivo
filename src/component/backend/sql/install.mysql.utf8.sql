CREATE TABLE IF NOT EXISTS `#__mapaviajes_albums` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` text NULL,
  `published` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 2,
  `allow_comments` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `logo` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_published` (`published`),
  KEY `idx_access` (`access`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mapaviajes_trips` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `album_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `title` varchar(80) NOT NULL,
  `place` varchar(120) NOT NULL DEFAULT '',
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `allow_comments` tinyint NOT NULL DEFAULT 1,
  `notify` tinyint NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 0,
  `photo_count` tinyint unsigned NOT NULL DEFAULT 0,
  `article_id` int unsigned NULL DEFAULT NULL,
  `asset_id` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `modified` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_album_published` (`album_id`, `published`),
  KEY `idx_user_created` (`user_id`, `created`),
  KEY `idx_published_created` (`published`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mapaviajes_photos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int unsigned NOT NULL,
  `ordering` tinyint unsigned NOT NULL DEFAULT 0,
  `filename` varchar(40) NOT NULL,
  `width` smallint unsigned NOT NULL,
  `height` smallint unsigned NOT NULL,
  `bytes` int unsigned NOT NULL,
  `caption` text NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_filename` (`filename`),
  KEY `idx_trip` (`trip_id`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mapaviajes_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `event` varchar(40) NOT NULL,
  `detail` varchar(190) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO `#__mapaviajes_albums` (`title`, `description`, `published`, `access`, `allow_comments`, `ordering`, `created`, `created_by`)
SELECT 'Nuestro mundo en fotos', '', 1, 2, 1, 1, NOW(), 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__mapaviajes_albums`);

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
