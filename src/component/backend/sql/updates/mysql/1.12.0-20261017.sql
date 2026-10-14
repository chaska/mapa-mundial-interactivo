CREATE TABLE IF NOT EXISTS `#__mapaviajes_follows` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int unsigned NOT NULL,
  `followed_id` int unsigned NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_follow` (`follower_id`, `followed_id`),
  KEY `idx_followed` (`followed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `#__mapaviajes_prefs` ADD COLUMN `digest` tinyint NOT NULL DEFAULT 0;
ALTER TABLE `#__mapaviajes_prefs` ADD COLUMN `follows_seen` datetime NULL;
ALTER TABLE `#__mapaviajes_prefs` ADD COLUMN `digest_sent` datetime NULL;
