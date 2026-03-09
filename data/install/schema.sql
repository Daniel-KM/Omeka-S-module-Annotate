CREATE TABLE `annotation` (
    `id` INT NOT NULL,
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `annotation_value` (
    `id` INT NOT NULL,
    `annotation_id` INT NOT NULL,
    `field` VARCHAR(190) NOT NULL,
    `ordinal` SMALLINT NOT NULL DEFAULT 1,
    INDEX IDX_annotation_value_parts (`annotation_id`, `field`, `ordinal`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `annotation` ADD CONSTRAINT FK_2E443EF2BF396750 FOREIGN KEY (`id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
ALTER TABLE `annotation_value` ADD CONSTRAINT FK_annotation_value_annotation FOREIGN KEY (`annotation_id`) REFERENCES `annotation` (`id`) ON DELETE CASCADE;
ALTER TABLE `annotation_value` ADD CONSTRAINT FK_annotation_value_value FOREIGN KEY (`id`) REFERENCES `value` (`id`) ON DELETE CASCADE;
