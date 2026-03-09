SET foreign_key_checks = 0;

-- Clean up old multi-entity model resources (pre-3.4.12) if still present.
DELETE `value`
    FROM `value` LEFT JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
    WHERE `resource_type` IN (
        "Annotate\\Entity\\Annotation",
        "Annotate\\Entity\\AnnotationBody",
        "Annotate\\Entity\\AnnotationTarget"
    );
DELETE FROM `resource`
    WHERE `resource_type` IN (
        "Annotate\\Entity\\Annotation",
        "Annotate\\Entity\\AnnotationBody",
        "Annotate\\Entity\\AnnotationTarget"
    );

DROP TABLE IF EXISTS `annotation_value`;
DROP TABLE IF EXISTS `annotation`;

-- Drop old tables from multi-entity model (pre-3.4.12).
DROP TABLE IF EXISTS `annotation_part`;
DROP TABLE IF EXISTS `annotation_body`;
DROP TABLE IF EXISTS `annotation_target`;

SET foreign_key_checks = 1;
