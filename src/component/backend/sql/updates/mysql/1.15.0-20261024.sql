ALTER TABLE `#__mapaviajes_photos` ADD COLUMN `friends_only` tinyint NOT NULL DEFAULT 0;
ALTER TABLE `#__mapaviajes_photos` ADD COLUMN `state` tinyint NOT NULL DEFAULT 1;
CREATE TABLE IF NOT EXISTS `#__mapaviajes_friends` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `requester_id` int unsigned NOT NULL,
  `addressee_id` int unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `answered` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friend` (`requester_id`, `addressee_id`),
  KEY `idx_addressee` (`addressee_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__mapaviajes_blocks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `blocker_id` int unsigned NOT NULL,
  `blocked_id` int unsigned NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block` (`blocker_id`, `blocked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
