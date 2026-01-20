CREATE TABLE IF NOT EXISTS `PREFIX_zipysearch_sync_log` (
    `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `products_count` INT(11) NOT NULL DEFAULT 0,
    `status` ENUM('success', 'error') NOT NULL,
    `message` TEXT,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `id_shop` (`id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
