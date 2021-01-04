ALTER TABLE `annotation` DROP FOREIGN KEY FK_2E443EF2BF396750;
ALTER TABLE `annotation_body` DROP FOREIGN KEY FK_D819DB36BF396750;
ALTER TABLE `annotation_target` DROP FOREIGN KEY FK_9F53A3D6BF396750;
ALTER TABLE `annotation_part` DROP FOREIGN KEY FK_4ABEA042BF396750;
ALTER TABLE `annotation_part` DROP FOREIGN KEY FK_4ABEA042E075FC54;
DELETE `value`
    FROM `value` LEFT JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
    WHERE `resource_type` IN ("Annotate\\Entity\\Annotation", "Annotate\\Entity\\AnnotationBody", "Annotate\\Entity\\AnnotationTarget");
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\AnnotationTarget";
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\AnnotationBody";
DELETE FROM `resource` WHERE `resource_type` = "Annotate\\Entity\\Annotation";
DROP TABLE `annotation_target`;
DROP TABLE `annotation_body`;
DROP TABLE `annotation`;
DROP TABLE `annotation_part`;
