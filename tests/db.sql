--
--  Simple management of users' permissions.
-- -----------------------------------------

-- ---- database settings:
SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ---- create acl table:
DROP TABLE IF EXISTS `acl`;
CREATE TABLE `acl` (
    `role_id` int(11) unsigned NOT NULL,
    `user_id` int(11) unsigned NOT NULL,
     KEY `user` (`user_id`),
     KEY `role` (`role_id`),
     CONSTRAINT `acl_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
     CONSTRAINT `acl_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- create permissions table:
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `role_id` int(11) unsigned NOT NULL,
    `resource_id` int(11) unsigned NOT NULL,
    `privilege_id` int(11) unsigned NOT NULL,
    `allowed` enum('no','yes') NOT NULL DEFAULT 'yes',
    PRIMARY KEY (`id`),
    KEY `resource` (`resource_id`),
    KEY `role` (`role_id`),
    KEY `privilege` (`privilege_id`),
    CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`),
    CONSTRAINT `permissions_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    CONSTRAINT `permissions_ibfk_3` FOREIGN KEY (`privilege_id`) REFERENCES `privileges` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- create permissions_roles_view view:
DROP VIEW IF EXISTS `permissions_roles_view`;
CREATE TABLE `permissions_roles_view` (
    `id` int(11) unsigned,
    `name` varchar(40),
    `parent` int(11)
);

-- ---- create permissions_view view:
DROP VIEW IF EXISTS `permissions_view`;
CREATE TABLE `permissions_view` (
    `id` int(11) unsigned,
    `resource` varchar(40),
    `privilege` varchar(40),
    `role` varchar(40),
    `allowed` enum('no','yes')
);

-- ---- create privileges table:
DROP TABLE IF EXISTS `privileges`;
CREATE TABLE `privileges` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(40) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- create resources table:
DROP TABLE IF EXISTS `resources`;
CREATE TABLE `resources` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(40) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- create roles table:
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(40) NOT NULL,
    `parent` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- insert values to roles table:
INSERT INTO `roles` (`id`, `name`, `parent`) VALUES
(1,	'guest',	0),
(2,	'member',	1);

-- ---- create trigger for roles table:
DELIMITER ;;

CREATE TRIGGER `insert` BEFORE INSERT ON `roles` FOR EACH ROW
BEGIN
    IF (new.name = 'admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This name is not allowed for the role.';
    END IF;
END;;

CREATE TRIGGER `update` BEFORE UPDATE ON `roles` FOR EACH ROW
BEGIN
    IF (new.name = 'admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The role is not allowed to change to this name.';
    END IF;
END;;

DELIMITER ;

-- ---- create users table:
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(50) NOT NULL,
    `password` varchar(60) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- create permissions_roles_view query:
DROP TABLE IF EXISTS `permissions_roles_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `permissions_roles_view` AS select `roles`.`id` AS `id`,`roles`.`name` AS `name`,`roles`.`parent` AS `parent`
from `roles` where `roles`.`id` in (select distinct `permissions`.`role_id` from `permissions` where `roles`.`name` <> 'admin');

-- ---- create permissions_view query:
DROP TABLE IF EXISTS `permissions_view`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `permissions_view` AS select `p`.`id` AS `id`,`r`.`name` AS `resource`,`p2`.`name` AS `privilege`,`r2`.`name` AS `role`,`p`.`allowed` AS `allowed`
from (((`permissions` `p`
    left join `resources` `r` on(`p`.`resource_id` = `r`.`id`))
    left join `privileges` `p2` on(`p`.`privilege_id` = `p2`.`id`))
    left join `roles` `r2` on(`p`.`role_id` = `r2`.`id`));
