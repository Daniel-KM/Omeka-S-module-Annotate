SET foreign_key_checks = 0;

DELETE `value`
    FROM `value` LEFT JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
    WHERE `resource_type` IN ("Annotate\\Entity\\Annotation", "Annotate\\Entity\\AnnotationBody", "Annotate\\Entity\\AnnotationTarget");
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\AnnotationTarget";
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\AnnotationBody";
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\Annotation";

DROP TABLE IF EXISTS `annotation_target`;
DROP TABLE IF EXISTS `annotation_body`;
DROP TABLE IF EXISTS `annotation`;
DROP TABLE IF EXISTS `annotation_part`;

SET foreign_key_checks = 1;
