CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fb_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `smug_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fb_id` (`fb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;