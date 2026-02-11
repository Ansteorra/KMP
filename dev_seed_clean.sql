/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: amp-seed
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0+deb12u2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activities_activities`
--

DROP TABLE IF EXISTS `activities_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `term_length` int(11) NOT NULL,
  `activity_group_id` int(11) NOT NULL,
  `grants_role_id` int(11) DEFAULT NULL,
  `minimum_age` int(11) DEFAULT NULL,
  `maximum_age` int(11) DEFAULT NULL,
  `num_required_authorizors` int(11) NOT NULL DEFAULT 1,
  `num_required_renewers` int(11) NOT NULL DEFAULT 1,
  `permission_id` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `activity_group_id` (`activity_group_id`),
  KEY `deleted` (`deleted`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `activities_activities_ibfk_1` FOREIGN KEY (`activity_group_id`) REFERENCES `activities_activity_groups` (`id`),
  CONSTRAINT `activities_activities_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities_activities`
--

LOCK TABLES `activities_activities` WRITE;
/*!40000 ALTER TABLE `activities_activities` DISABLE KEYS */;
INSERT INTO `activities_activities` VALUES
(1,'Armored',48,1,NULL,16,200,1,1,1001,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(2,'Fiberglass Spear',48,1,NULL,16,200,1,1,1002,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(3,'Armored Field Marshal',48,1,NULL,18,200,1,1,1003,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(4,'Rapier',48,2,NULL,16,200,1,1,1004,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(5,'Rapier Field Marshal',48,2,NULL,18,200,1,1,1005,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(6,'Cut And Thrust',48,3,NULL,16,200,1,1,1006,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(7,'Rapier Spear',48,2,NULL,16,200,1,1,1007,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(8,'Siege',48,4,NULL,16,200,1,1,1008,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(9,'Siege Marshal',48,4,NULL,16,200,1,1,1009,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(10,'Armored Combat Archery',48,4,NULL,16,200,1,1,1010,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(11,'Rapier Combat Archery',48,4,NULL,16,200,1,1,1011,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(12,'Combat Archery Marshal',48,4,NULL,18,200,1,1,1012,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(13,'Target Archery Marshal',48,4,NULL,18,200,1,1,1013,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(14,'Thrown Weapons Marshal',48,4,NULL,18,200,1,1,1014,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(15,'Youth Boffer 1',48,7,NULL,6,13,1,1,1015,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(16,'Youth Boffer 2',48,7,NULL,6,13,1,1,1016,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(17,'Youth Boffer 3',48,7,NULL,6,13,1,1,1017,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(18,'Youth Boffer Marshal',48,7,NULL,18,200,1,1,1018,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(19,'Youth Boffer Junior Marshal',48,7,NULL,13,17,1,1,1019,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(20,'Youth Armored Combat',48,7,NULL,13,17,1,1,1020,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(21,'Youth Armored Combat Two Weapons',48,7,NULL,13,17,1,1,1021,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(22,'Youth Armored Combat Spear',48,7,NULL,13,27,1,1,1022,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(23,'Youth Armored Combat Weapon Shield',48,7,NULL,13,17,1,1,1023,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(24,'Youth Armored Combat Grea Weapons',48,7,NULL,13,17,1,1,1024,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(25,'Youth Armored Field Marshal',48,7,NULL,18,200,1,1,1025,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(26,'Youth Armored Combat Junior Marshal',48,7,NULL,13,17,1,1,1026,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(27,'Youth Rapier Combat Foil',48,6,NULL,10,17,1,1,1027,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(28,'Youth Rapier Combat Epee',48,6,NULL,10,17,1,1,1028,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(29,'Youth Rapier Combat Heavy Rapier',48,6,NULL,12,17,1,1,1029,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(30,'Youth Rapier Combat Plastic Sword',48,6,NULL,6,13,1,1,1030,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(31,'Youth Rapier Combat Melee',48,6,NULL,13,17,1,1,1031,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(32,'Youth Rapier Combat Offensive Secondary',48,6,NULL,13,17,1,1,1032,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(33,'Youth Rapier Combat Defensive Secondary',48,6,NULL,13,17,1,1,1033,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(34,'Youth Rapier Field Marshal',48,6,NULL,18,200,1,1,1034,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(35,'Youth Rapier Combat Plastic Sword Marshal',48,6,NULL,18,200,1,1,1035,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(36,'Experimental: Rapier Spear',48,2,NULL,99,101,1,1,1036,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(37,'C&T 2 Handed Weapon',48,3,NULL,16,200,1,1,1037,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(38,'Equestrian Field Marshal',48,5,NULL,18,200,1,1,1038,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(39,'General Riding',48,5,NULL,5,200,1,1,1039,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(40,'Mounted Games',48,5,NULL,5,200,1,1,1040,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(41,'Mounted Combat',48,5,NULL,18,200,1,1,1041,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(42,'Foam Jousting',48,5,NULL,18,200,1,1,1042,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(43,'Driving',48,5,NULL,18,200,1,1,1043,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(44,'Wooden Lance',48,5,NULL,18,200,1,1,1044,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(45,'Mounted Archery',48,5,NULL,18,200,1,1,1045,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(50,'C&T - Historic Combat Experiment',48,3,NULL,16,200,1,1,1050,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(51,'Reduced Rapier Armor Experiment ',48,2,NULL,18,200,1,1,1051,NULL,'2024-09-29 15:47:04',1,NULL,NULL),
(52,'Deleted: Armored Authorizing Marshal',48,1,1001,18,200,1,1,1052,'2025-01-17 16:17:12','2024-09-29 15:47:04',1,1096,'2025-01-17 16:17:12'),
(53,'Deleted: Rapier Authorizing Marshal',48,2,1002,18,200,1,1,1053,'2025-01-17 16:26:36','2024-09-29 15:47:04',1,1096,'2025-01-17 16:26:36'),
(54,'Deleted: Target Archery Authorizing Marshal',48,4,1004,18,200,1,1,1054,'2025-01-17 16:27:15','2024-09-29 15:47:04',1,1096,'2025-01-17 16:27:15'),
(55,'Deleted: C&T Authorizing Marshal',48,3,1003,18,200,1,1,1055,'2025-01-17 16:56:09','2024-09-29 15:47:04',1,1096,'2025-01-17 16:56:09'),
(56,'Deleted: Equestrian Authorizing Marshal ',48,5,1005,18,200,1,1,1056,'2025-01-17 16:21:31','2024-09-29 15:47:04',1,1096,'2025-01-17 16:21:31'),
(57,'Deleted: Youth Armored Authorizing Marshal',48,7,1007,18,200,1,1,1057,'2025-01-17 16:28:41','2024-09-29 15:47:04',1,1096,'2025-01-17 16:28:41'),
(58,'Deleted: Youth Rapier Authorizing Marshal',48,6,1006,18,200,1,1,1058,'2025-01-17 16:29:04','2024-09-29 15:47:04',1,1096,'2025-01-17 16:29:04'),
(59,'Deleted: Siege Authorizing Marshal',48,4,1008,18,200,1,1,1059,'2025-01-17 16:27:25','2024-09-29 15:47:04',1,1096,'2025-01-17 16:27:25'),
(60,'Deleted: Rapier Spear Authorizing Marshal',48,2,1009,18,200,1,1,1060,'2025-01-17 16:27:03','2024-09-29 15:47:04',1,1096,'2025-01-17 16:27:03'),
(61,'Deleted: Thrown Weapons Authorizing Marshal',48,4,1010,18,200,1,1,1061,'2025-01-17 16:27:41','2024-09-29 15:47:04',1,1096,'2025-01-17 16:27:41'),
(62,'Deleted: Combat Archery Authorizing Marshal',48,4,1011,18,200,1,1,1062,'2025-01-17 16:21:24','2024-09-29 15:47:04',1,1096,'2025-01-17 16:21:24'),
(63,'Deleted: Two Handed C&T Authorizing Marshal',48,3,1012,18,200,1,1,1063,'2025-01-17 21:58:04','2024-09-29 15:47:04',1,1096,'2025-01-17 21:58:04'),
(64,'Deleted: Wooden Lance Authorizing Marshal',48,5,1013,18,200,1,1,1064,'2025-01-17 16:54:34','2024-09-29 15:47:04',1,1096,'2025-01-17 16:54:34'),
(65,'Deleted: Reduced Armor Experiement Authorizing Marshal',48,2,1014,18,200,1,1,1065,'2025-01-17 16:26:52','2024-09-29 15:47:04',1,1096,'2025-01-17 16:26:52'),
(66,'Deleted: C&T - Historic Combat Experiment Authorizing Marshal',48,3,1015,18,200,1,1,1066,'2025-01-17 21:58:09','2024-09-29 15:47:04',1,1096,'2025-01-17 21:58:09');
/*!40000 ALTER TABLE `activities_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activities_activity_groups`
--

DROP TABLE IF EXISTS `activities_activity_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities_activity_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities_activity_groups`
--

LOCK TABLES `activities_activity_groups` WRITE;
/*!40000 ALTER TABLE `activities_activity_groups` DISABLE KEYS */;
INSERT INTO `activities_activity_groups` VALUES
(1,'Armored Combat',NULL,'2024-09-29 15:47:04',1,1,NULL),
(2,'Rapier',NULL,'2024-09-29 15:47:04',1,1,NULL),
(3,'Cut & Thrust',NULL,'2024-09-29 15:47:04',1,1,NULL),
(4,'Missile',NULL,'2024-09-29 15:47:04',1,1,NULL),
(5,'Equestrian',NULL,'2024-09-29 15:47:04',1,1,NULL),
(6,'Youth Rapier',NULL,'2024-09-29 15:47:04',1,1,NULL),
(7,'Youth Armored',NULL,'2024-09-29 15:47:04',1,1,NULL);
/*!40000 ALTER TABLE `activities_activity_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activities_authorization_approvals`
--

DROP TABLE IF EXISTS `activities_authorization_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities_authorization_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `authorization_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `authorization_token` varchar(255) NOT NULL,
  `requested_on` datetime NOT NULL,
  `responded_on` datetime DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `approver_notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approver_id` (`approver_id`),
  KEY `authorization_id` (`authorization_id`),
  CONSTRAINT `activities_authorization_approvals_ibfk_1` FOREIGN KEY (`authorization_id`) REFERENCES `activities_authorizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_authorization_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9322 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities_authorization_approvals`
--

LOCK TABLES `activities_authorization_approvals` WRITE;
/*!40000 ALTER TABLE `activities_authorization_approvals` DISABLE KEYS */;
INSERT INTO `activities_authorization_approvals` VALUES
(9306,9311,1,'562b4c599fd7f065f09baa45be9ba0ac','2025-06-22 18:58:39','2025-06-22 19:00:16',0,'test deny'),
(9307,9312,1,'0df999ac1381d559b9627621ce856fcb','2025-06-22 19:10:19','2025-06-22 19:10:48',0,'Test Deny'),
(9308,9313,1,'c09f8cdd17e8dfc4b165e566c06a7a74','2025-06-22 19:11:32','2025-06-22 19:13:58',1,NULL),
(9312,9317,1,'cce9301aa36ce4290238fccfbf655c25','2025-10-31 00:50:43',NULL,0,NULL),
(9313,9318,1,'c55d390f1baa36012f54bf2d84001a4f','2025-12-27 19:32:27','2025-12-27 20:17:49',1,NULL),
(9314,9319,1,'fcdd26917b64d68ddae878551cde62ce','2025-12-27 20:17:25','2025-12-27 20:19:22',1,NULL),
(9315,9320,1,'3ceaf1d758bd1a738c9f3d6c7ab081c2','2025-12-27 20:17:33','2025-12-27 20:19:27',1,NULL),
(9316,9321,1,'dfe249bafd163c6a19631bdbac6bf81f','2025-12-27 20:35:01','2025-12-27 20:35:32',1,NULL),
(9317,9322,1,'721bd2ec481a1d21186526c9dc86e275','2025-12-29 20:26:32',NULL,0,NULL),
(9318,9323,1,'98a277af3d55ac5c0810a274495a9ef4','2025-12-29 20:35:04',NULL,0,NULL),
(9319,9324,1,'b9420851f2a58204f986ddd2545a39ef','2026-01-01 18:51:44',NULL,0,NULL),
(9320,9325,1,'40fb08197ee2b8222855619bcbc2bfe5','2026-01-16 02:24:38',NULL,0,NULL),
(9321,9326,1,'e45bd2b82411b323bc318b74842f1928','2026-01-17 20:36:30',NULL,0,NULL);
/*!40000 ALTER TABLE `activities_authorization_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activities_authorizations`
--

DROP TABLE IF EXISTS `activities_authorizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities_authorizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `granted_member_role_id` int(11) DEFAULT NULL,
  `expires_on` datetime DEFAULT NULL,
  `start_on` datetime DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_count` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `revoked_reason` varchar(255) DEFAULT '',
  `revoker_id` int(11) DEFAULT NULL,
  `is_renewal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`),
  KEY `member_id` (`member_id`),
  KEY `start_on` (`start_on`),
  KEY `expires_on` (`expires_on`),
  KEY `granted_member_role_id` (`granted_member_role_id`),
  CONSTRAINT `activities_authorizations_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_authorizations_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_authorizations_ibfk_3` FOREIGN KEY (`granted_member_role_id`) REFERENCES `member_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9327 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities_authorizations`
--

LOCK TABLES `activities_authorizations` WRITE;
/*!40000 ALTER TABLE `activities_authorizations` DISABLE KEYS */;
INSERT INTO `activities_authorizations` VALUES
(9311,2872,6,NULL,'2025-06-22 19:00:15','2025-06-22 19:00:15','2025-06-22 18:58:39',0,'Denied','test deny',1096,0),
(9312,2872,6,NULL,'2025-06-22 19:10:47','2025-06-22 19:10:47','2025-06-22 19:10:19',0,'Denied','Test Deny',1096,0),
(9313,2872,6,NULL,'2029-06-22 19:13:58','2025-06-22 19:13:58','2025-06-22 19:11:32',1,'Approved','',NULL,0),
(9317,1,4,NULL,NULL,NULL,'2025-10-31 00:50:38',0,'Pending','',NULL,0),
(9318,2878,6,NULL,'2029-12-27 20:17:49','2025-12-27 20:17:49','2025-12-27 19:32:10',1,'Approved','',NULL,0),
(9319,2886,50,NULL,'2029-12-27 20:19:22','2025-12-27 20:19:22','2025-12-27 20:17:09',1,'Approved','',NULL,0),
(9320,2886,37,NULL,'2029-12-27 20:19:27','2025-12-27 20:19:27','2025-12-27 20:17:17',1,'Approved','',NULL,0),
(9321,2878,37,NULL,'2029-12-27 20:35:32','2025-12-27 20:35:32','2025-12-27 20:34:45',1,'Approved','',NULL,0),
(9322,2878,4,NULL,NULL,NULL,'2025-12-29 20:26:15',0,'Pending','',NULL,0),
(9323,2878,2,NULL,NULL,NULL,'2025-12-29 20:34:47',0,'Pending','',NULL,0),
(9324,2878,1,NULL,NULL,NULL,'2026-01-01 18:51:27',0,'Pending','',NULL,0),
(9325,2878,3,NULL,NULL,NULL,'2026-01-16 02:24:38',0,'Pending','',NULL,0),
(9326,2878,6,NULL,NULL,NULL,'2026-01-17 20:36:30',0,'Pending','',NULL,0);
/*!40000 ALTER TABLE `activities_authorizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activities_phinxlog`
--

DROP TABLE IF EXISTS `activities_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities_phinxlog`
--

LOCK TABLES `activities_phinxlog` WRITE;
/*!40000 ALTER TABLE `activities_phinxlog` DISABLE KEYS */;
INSERT INTO `activities_phinxlog` VALUES
(20240614001010,'InitActivities','2024-09-29 15:47:03','2024-09-29 15:47:03',0),
(20250228144601,'MakeTermMonthsNotYears','2025-03-01 14:24:26','2025-03-01 14:24:26',0);
/*!40000 ALTER TABLE `activities_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES
(1,'Activity.SecretaryEmail','amp-secretary@webminister.ansteorra.org','2025-01-13 23:19:32','2024-09-29 15:47:04',1,NULL,NULL,1),
(2,'KMP.KingdomName','Ansteorra',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(3,'Activity.SecretaryName','Lady Megan Flower del Wall',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(4,'Members.AccountVerificationContactEmail','amp-secretary@webminister.ansteorra.org','2025-01-13 23:20:27','2024-09-29 15:47:04',1,NULL,NULL,1),
(5,'Members.AccountDisabledContactEmail','amp-secretary@webminister.ansteorra.org','2025-01-13 23:20:04','2024-09-29 15:47:04',1,NULL,NULL,1),
(6,'Email.SystemEmailFromAddress','donotreply@amp.ansteorra.org','2025-07-09 11:55:12','2024-09-29 15:47:04',1,NULL,NULL,1),
(7,'KMP.LongSiteTitle','Ansteorra Management Portal',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(8,'Members.NewMinorSecretaryEmail','amp-secretary@webminister.ansteorra.org','2025-01-13 23:20:49','2024-09-29 15:47:04',1,NULL,NULL,1),
(9,'KMP.ShortSiteTitle','UAT','2025-03-01 15:02:10','2024-09-29 15:47:04',1,NULL,NULL,1),
(10,'Member.ExternalLink.Order of Precedence','https://op.ansteorra.org/people/id/{{additional_info->OrderOfPrecedence_Id}}',NULL,'2024-09-29 15:47:04',1,1,NULL,0),
(11,'Member.AdditionalInfo.OrderOfPrecedence_Id','number|user',NULL,'2024-09-29 15:47:04',1,1,NULL,0),
(12,'Member.MobileCard.BgColor','gold',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(13,'Member.MobileCard.ThemeColor','gold',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(14,'Member.ViewCard.Graphic','auth_card_back.gif',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(15,'Member.ViewCard.HeaderColor','gold',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(16,'Member.ViewCard.Template','view_card',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(17,'Member.ViewMobileCard.Template','view_mobile_card',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(18,'Members.NewMemberSecretaryEmail','amp-secretary@webminister.ansteorra.org','2025-01-13 23:20:40','2024-09-29 15:47:04',1,NULL,NULL,1),
(19,'Plugin.Activities.Active','yes',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(20,'Plugin.Awards.Active','yes',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(21,'Plugin.GitHubIssueSubmitter.Active','no','2026-01-16 02:17:48','2024-09-29 15:47:04',1,NULL,NULL,1),
(22,'Plugin.Officers.Active','yes','2025-01-12 01:03:43','2024-09-29 15:47:04',1,NULL,NULL,1),
(23,'Awards.CallIntoCourtOptions','I do not know,Never,With Notice,Without Notice,With notice given to another person,With notice given to me,With notice given to me and another person','2024-09-30 01:51:18','2024-09-29 15:47:04',1,NULL,NULL,1),
(24,'Awards.CourtAvailabilityOptions','I do not know,None,Morning,Evening,Any',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(25,'Awards.RecButtonClass','btn-warning',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(26,'Email.SiteAdminSignature','Webminister',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(27,'KMP.AppSettings.HelpUrl','https://github.com/Ansteorra/KMP/wiki/App-Settings',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(28,'KMP.BannerLogo','badge.png',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(29,'KMP.EnablePublicRegistration','yes',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(30,'KMP.GitHub.Owner','Ansteorra',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(31,'KMP.GitHub.Project','KMP',NULL,'2024-09-29 15:47:04',1,1,NULL,1),
(32,'Member.AdditionalInfo.CallIntoCourt','select:Never,With Notice,Without Notice,With notice given to another person,With notice given to me,With notice given to me and another person|user|public','2024-09-30 01:51:08','2024-09-29 15:47:04',1,NULL,NULL,0),
(33,'Member.AdditionalInfo.PersonToGiveNoticeTo','text|user|public',NULL,'2024-09-29 15:47:04',1,1,NULL,0),
(34,'Member.AdditionalInfo.CourtAvailability','select:None,Morning,Evening,Any|user|public',NULL,'2024-09-29 15:47:04',1,1,NULL,0),
(35,'KMP.BranchInitRun','recovered','2024-09-29 16:55:23','2024-09-29 15:47:44',NULL,NULL,NULL,1),
(36,'KMP.Login.Graphic','populace_badge.png','2024-09-29 15:47:44','2024-09-29 15:47:44',NULL,NULL,NULL,1),
(37,'Activities.NextStatusCheck','2026-02-07','2026-02-06 03:36:32','2024-09-29 15:47:44',NULL,NULL,NULL,1),
(38,'Officer.NextStatusCheck','2026-02-07','2026-02-06 03:36:33','2024-09-29 15:47:44',NULL,NULL,NULL,1),
(50,'Awards.RecommendationStatesRequireCanViewHidden','---\n- No Action\n...\n','2024-10-31 23:16:05','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(51,'Awards.RecommendationStatuses','---\nIn Progress:\n- Submitted\n- In Consideration\n- Awaiting Feedback\n- Deferred till Later\n- King Approved\n- Queen Approved\nScheduling:\n- Need to Schedule\nTo Give:\n- Scheduled\n- Announced Not Given\nClosed:\n- Given\n- No Action\n...\n','2024-10-31 23:16:05','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(52,'Awards.RecommendationStateRules','---\nNeed to Schedule:\n  Visible:\n  - planToGiveBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\nScheduled:\n  Kanban Popup: selectEvent\n  Required:\n  - planToGiveEventTarget\n  Visible:\n  - planToGiveBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\nGiven:\n  Kanban Popup: selectGivenDate\n  Required:\n  - planToGiveEventTarget\n  - givenDateTarget\n  Visible:\n  - planToGiveBlockTarget\n  - givenBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\n  Set:\n    close_reason: Given\nNo Action:\n  Required:\n  - closeReasonTarget\n  Visible:\n  - closeReasonBlockTarget\n  - closeReasonTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - courtAvailabilityTarget\n  - callIntoCourtTarget\n  - scaMemberTarget\n...\n','2024-10-31 23:16:05','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(53,'Awards.ViewConfig.Default','---\ntable:\n  filter: []\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: true\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: true\n    Pronouns: true\n    Pronunciation: true\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: true\n    Contact Email: true\n    Contact Phone: true\n    Domain: true\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: true\n    Given Date: true\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(54,'Awards.ViewConfig.In Progress','---\ntable:\n  filter:\n    Recommendations->Status: In Progress\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: true\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: false\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: true\n    Pronouns: true\n    Pronunciation: true\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: true\n    Contact Email: true\n    Contact Phone: true\n    Domain: true\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: false\n    Gathering: false\n    State Date: true\n    Given Date: false\nboard:\n  use: true\n  states:\n  - Submitted\n  - In Consideration\n  - Awaiting Feedback\n  - Deferred till Later\n  - King Approved\n  - Queen Approved\n  - Need to Schedule\n  - No Action\n  hiddenByDefault:\n    lookback: 30\n    states:\n    - No Action\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(55,'Awards.ViewConfig.Scheduling','---\ntable:\n  filter:\n    Recommendations->Status: Scheduling\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: false\n    Gatherings: true\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: true\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: true\n    State Date: false\n    Given Date: false\nboard:\n  use: true\n  states:\n  - Need to Schedule\n  - Scheduled\n  hiddenByDefault:\n    lookback: 30\n    states: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(56,'Awards.ViewConfig.To Give','---\ntable:\n  filter:\n    Recommendations->Status: To Give\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: true\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: true\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: true\n    State Date: false\n    Given Date: false\nboard:\n  use: true\n  states:\n  - Scheduled\n  - Announced Not Given\n  - Given\n  hiddenByDefault:\n    lookback: 30\n    states:\n    - Given\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(57,'Awards.ViewConfig.Closed','---\ntable:\n  filter:\n    Recommendations->Status: Closed\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: true\n    Given Date: true\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: true\n    Given Date: true\nboard:\n  use: false\n  states: []\n  hiddenByDefault:\n    lookback: 30\n    states: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(58,'Awards.ViewConfig.Event','---\ntable:\n  filter:\n    Recommendations->gathering_id: -gathering_id-\n  optionalPermission: ViewGatheringRecommendations\n  use: true\n  enableExport: true\n  columns:\n    Submitted: false\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: false\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: false\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: false\n    For: true\n    For Herald: true\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: true\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: false\n    Notes: false\n    Status: false\n    State: true\n    Close Reason: false\n    Gathering: true\n    State Date: false\n    Given Date: false\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(59,'Awards.ViewConfig.SubmittedByMember','---\ntable:\n  filter:\n    Recommendations->requester_id: -member_id-\n  optionalPermission: ViewSubmittedByMember\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: false\n    Status: false\n    State: false\n    Close Reason: false\n    Gathering: false\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: false\n    Status: false\n    State: false\n    Close Reason: false\n    Gathering: false\n    State Date: false\n    Given Date: false\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(60,'Awards.ViewConfig.SubmittedForMember','---\ntable:\n  filter:\n    Recommendations->member_id: -member_id-\n  optionalPermission: ViewSubmittedForMember\n  use: true\n  enableExport: false\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: true\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: false\n    Status: false\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: false\n    Given Date: true\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: true\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Gatherings: true\n    Notes: false\n    Status: false\n    State: true\n    Close Reason: true\n    Gathering: true\n    State Date: false\n    Given Date: true\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n','2025-12-15 00:35:35','2024-10-31 23:16:05',NULL,NULL,'yaml',1),
(62,'KMP.HeaderLink. Support','https://discord.gg/bUrnUprz4T|bi bi-discord btn-outline-warning','2025-01-12 21:11:25','2024-11-26 22:28:38',NULL,NULL,NULL,0),
(63,'KMP.HeaderLink.GitHub.no-label','https://github.com/Ansteorra/KMP|bi bi-github','2026-02-01 00:12:06','2024-11-26 22:29:28',NULL,NULL,NULL,0),
(65,'KMP.FooterLink. What Is AMP','https://ansteorra.org/amp/|btn btn-sm btn-warning bi bi-patch-question-fill','2025-01-12 01:17:21','2025-01-10 13:12:36',NULL,NULL,NULL,0),
(66,'KMP.configVersion','25.11.05.a','2025-11-06 11:46:36','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(67,'Warrant.LastCheck','2026-02-04 18:02:38','2026-02-04 18:02:38','2025-01-12 01:02:18',NULL,NULL,NULL,0),
(68,'KMP.RequireActiveWarrantForSecurity','no','2025-01-12 01:03:28','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(69,'Warrant.RosterApprovalsRequired','1','2025-03-04 12:04:08','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(70,'Branches.Types','---\n- Kingdom\n- Principality\n- Region\n- Local Group\n- N/A\n...\n','2025-01-12 01:02:18','2025-01-12 01:02:18',NULL,NULL,'yaml',1),
(71,'GitHubIssueSubmitter.configVersion','25.01.11.a','2025-01-12 01:02:18','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(72,'Plugin.GitHubIssueSubmitter.PopupMessage','This Feedback form is anonymous and will be submitted to the KMP GitHub repository. Please do not include any private information or use this for support requests.  If you have any support needs please reach out over discord at https://discord.gg/AMKEAAX7','2025-01-12 01:20:17','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(73,'Activities.configVersion','25.01.11.c','2025-03-23 12:52:52','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(74,'Officer.configVersion','25.01.11.a','2025-01-12 01:02:18','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(75,'Awards.configVersion','25.11.30.a','2025-12-15 00:35:35','2025-01-12 01:02:18',NULL,NULL,NULL,1),
(76,'Queue.configVersion','0.0.0','2025-02-03 14:35:12','2025-02-03 14:35:12',NULL,NULL,NULL,1),
(77,'Plugin.Queue.Active','yes','2025-02-03 14:35:12','2025-02-03 14:35:12',NULL,NULL,NULL,1),
(79,'Activities.api_access.gw_gate','giE36JsEazhckMdFCbYfE9pgdTNLE9hdp8T2ZMgwTgFUZXAUyi','2025-02-23 20:29:34','2025-02-23 20:02:44',NULL,NULL,NULL,0),
(80,'Email.UseQueue','yes','2025-07-09 11:57:22','2025-07-09 11:49:38',NULL,NULL,NULL,1),
(81,'Waivers.configVersion','1.0.1','2025-10-30 21:03:01','2025-10-30 21:03:01',NULL,NULL,NULL,1),
(82,'Plugin.Waivers.Active','yes','2025-10-30 21:03:01','2025-10-30 21:03:01',NULL,NULL,NULL,1),
(83,'Plugin.Waivers.ShowInNavigation','yes','2025-10-30 21:03:01','2025-10-30 21:03:01',NULL,NULL,NULL,1),
(84,'Plugin.Waivers.HelloWorldMessage','Hello, World!','2025-10-30 21:03:01','2025-10-30 21:03:01',NULL,NULL,NULL,1),
(85,'Waivers.ComplianceDays','2','2025-10-30 21:03:01','2025-10-30 21:03:01',NULL,NULL,NULL,1),
(86,'GoogleMaps.ApiKey','REPLACE WITH REAL','2025-10-31 19:24:29','2025-10-31 19:23:50',NULL,NULL,NULL,0),
(87,'KMP.DefaultTimezone','America/Chicago','2025-11-06 11:46:36','2025-11-06 11:46:36',NULL,NULL,NULL,1),
(88,'KMP.HeaderLink. Submit Award Rec','https://amp-uat.ansteorra.org/awards/recommendations/add?|btn fs-6 bi bi-megaphone-fill mb-2  btn-warning text-dark','2026-02-01 00:14:42','2026-01-29 00:56:08',NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `award_gathering_activities`
--

DROP TABLE IF EXISTS `award_gathering_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `award_gathering_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `award_id` int(11) NOT NULL COMMENT 'FK to awards table',
  `gathering_activity_id` int(11) NOT NULL COMMENT 'FK to gathering_activities table',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_awact_unique` (`award_id`,`gathering_activity_id`),
  KEY `idx_awact_award` (`award_id`),
  KEY `idx_awact_activity` (`gathering_activity_id`),
  CONSTRAINT `fk_awact_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_awact_award` FOREIGN KEY (`award_id`) REFERENCES `awards_awards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `award_gathering_activities`
--

LOCK TABLES `award_gathering_activities` WRITE;
/*!40000 ALTER TABLE `award_gathering_activities` DISABLE KEYS */;
INSERT INTO `award_gathering_activities` VALUES
(1,1,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(2,2,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(3,3,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(4,4,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(5,5,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(6,6,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(7,7,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(8,8,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(9,9,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(10,10,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(11,11,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(12,12,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(13,13,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(14,14,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(15,15,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(16,16,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(17,17,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(18,18,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(19,19,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(20,20,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(21,22,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(22,23,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(23,24,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(24,25,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(25,26,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(26,27,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(27,28,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(28,29,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(29,30,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(30,31,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(31,32,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(32,33,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(33,34,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(34,35,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(35,36,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(36,37,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(37,38,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(38,39,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(39,40,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(40,41,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(41,42,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(42,43,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(43,44,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(44,45,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(45,46,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(46,47,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(47,48,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(48,49,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(49,50,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(50,51,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(51,52,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(52,53,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(53,54,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(54,55,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(55,56,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(56,57,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(57,58,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(58,59,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(59,60,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(60,61,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(61,62,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(62,63,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(63,64,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(64,65,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(65,66,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(66,67,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(67,68,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(68,69,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(69,70,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(70,71,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(71,72,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(72,73,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(73,74,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(74,75,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(75,76,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(76,77,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(77,78,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(78,79,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(79,80,1,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(81,82,1,'2025-12-30 18:36:14','2025-12-30 18:36:14',1,1),
(82,81,1,'2025-12-30 18:36:33','2025-12-30 18:36:33',1,1),
(83,83,1,'2025-12-30 18:36:38','2025-12-30 18:36:38',1,1),
(84,83,11,'2025-12-30 21:02:55','2025-12-30 21:02:55',1,1),
(85,82,11,'2025-12-30 21:03:06','2025-12-30 21:03:06',1,1),
(86,81,11,'2025-12-30 21:03:14','2025-12-30 21:03:14',1,1);
/*!40000 ALTER TABLE `award_gathering_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_awards`
--

DROP TABLE IF EXISTS `awards_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `specialties` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `insignia` text DEFAULT NULL,
  `badge` text DEFAULT NULL,
  `charter` text DEFAULT NULL,
  `domain_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `level_id` (`level_id`),
  KEY `domain_id` (`domain_id`),
  KEY `branch_id` (`branch_id`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `awards_awards_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `awards_domains` (`id`),
  CONSTRAINT `awards_awards_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `awards_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `awards_awards_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_awards`
--

LOCK TABLES `awards_awards` WRITE;
/*!40000 ALTER TABLE `awards_awards` DISABLE KEYS */;
INSERT INTO `awards_awards` VALUES
(1,'Award of the Sable Falcon','Falcon',NULL,'Given to those who have striven greatly to further their skill level and capabilities in heavy weapons combat. Often given for a single notable deed.','A cord braided sable and Or tied to a metal ring worn on the belt.','None','',1,1,2,'2024-06-25 22:21:14','2024-06-25 22:21:14',1,1,NULL),
(2,'Award of the Sable Talon of Ansteorra (Chivalric)','Talon','[]','Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly  for different martial activities. ','The badge worn as a medallion or pin','(Fieldless) An eagle’s leg erased à la quise sable.','',1,2,2,'2024-09-03 01:19:01','2024-06-25 22:22:00',1,4,NULL),
(3,'Order of the Centurions of the Sable Star of Ansteorra','Centurion',NULL,'Polling order. Confers a Grant of Arms. Given to those who have demonstrated exceptional leadership, skill and honor in chivalric combat.','A ribbon Or edged gules charged with an Ansteorran star (a mullet of five greater\nand five lesser points) sable worn as a garter, and/or the badge of the order prominently\ndisplayed on a red cloak.','On an eagle displayed wings inverted Or a mullet of five greater and five lesser points\nsable.','',1,3,2,'2024-06-25 22:22:42','2024-06-25 22:22:42',1,1,NULL),
(4,'Order of Chivalry','Order of Chivalry','[]','Polling order. The highest award for chivalric combat.','Knighthood : White belt and unadorned gold chain\r\nMaster at Arms: White baldric','Knighthood : (Fieldless) A white belt\r\nMaster at Arms: (Fieldless) A white baldric','',1,4,2,'2024-10-31 19:33:05','2024-06-25 22:24:06',1,1096,NULL),
(5,'Award of the Lilium Aureum of Ansteorra','Lilium Aurium','[\"Artwork\",\"Bardic Arts\",\"Calligraphy\",\"Chainmail\",\"Costuming\",\"Culinary Arts\",\"Dance\",\"Drumming\",\"Embroidery\",\"Fiber Arts\",\"Heraldic Arts\",\"Herbalism\",\"Illumination\",\"Instrumental Music\",\"Jewelry Making\",\"Kumihimo\",\"Leatherworking\",\"Metalworking\",\"Music\",\"Needlework\",\"Painting\",\"Period Cooking\",\"Period Gaming\",\"Poetry\",\"Pottery\",\"Research\",\"Scribal Arts\",\"Spinning\",\"Textile Arts\",\"Voice Heraldry\",\"Weaving\",\"Woodcarving\",\"Woodworking\"]','Given in recognition of achievements in Arts and Sciences by youth members of the Kingdom.','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable a fleur-de-lis Or','',10,1,2,'2024-11-26 19:12:51','2024-08-14 19:47:19',1,1096,NULL),
(6,'Award of the Sable Thistle of Ansteorra','Sable Thistle','[\"Applique\",\"Armor Making\",\"Artwork\",\"Banner Painting\",\"Bardic Arts\",\"Beadwork\",\"Blacksmithing\",\"Blackwork\",\"Bobbin Lace\",\"Brewing\",\"Calligraphy\",\"Carving\",\"Chainmail\",\"Costuming\",\"Crossbow Making\",\"Culinary Arts\",\"Dance\",\"Drumming\",\"Embroidery\",\"Equestrian Arts\",\"European Dance\",\"Fiber Arts\",\"Fletching\",\"Foolery\",\"Glasswork\",\"Haberdashery\",\"Heraldic Arts\",\"Herbalism\",\"Iconography\",\"Illumination\",\"Inkle Weaving\",\"Instrumental Music\",\"Jewelry Making\",\"Knife Making\",\"Kumihimo\",\"Lampwork Beads\",\"Leatherworking\",\"Metal Casting\",\"Metalworking\",\"Middle Eastern Dancing\",\"Music\",\"Needlework\",\"Painting\",\"Period Cooking\",\"Period Gaming\",\"Pewter Casting\",\"Poetry\",\"Pottery\",\"Research\",\"Scribal Arts\",\"Siege Engines\",\"Spinning\",\"Stained Glass\",\"Textile Arts\",\"Voice Heraldry\",\"Weaving\",\"Woodcarving\",\"Woodworking\"]','Given to those who exhibit outstanding work in any field of the arts and sciences. The Award may be given to an individual more than once, but only once for a particular field.','The badge worn as a medallion or pin','(Fieldless) A blue thistle sable, slipped and leaved Or. ','',3,2,2,'2024-09-05 12:06:42','2024-08-14 19:51:16',1,1,NULL),
(7,'Order of the Iris of Merit of Ansteorra','CIM',NULL,'A member of the kingdom who shows outstanding work in the arts and sciences, well above that which is expected of the citizens of Ansteorra, Knowledge of the courtly graces; and\nwho have shown consistent respect for the laws and customs of Ansteorra.','A ribbon tinctured in the spectrum of a natural rainbow (red, orange, yellow, green, blue, violet) worn on the left shoulder','Or, a mullet of five greater and five lesser points voided sable, surmounted by a natural rainbow proper.','',3,3,2,'2024-08-14 20:10:53','2024-08-14 19:54:19',1,1,NULL),
(8,'Order of the Laurel','OL',NULL,'A candidate must have attained the standard of excellence in skill and/or knowledge equal to that of his or her prospective peers in an area of the Arts or Sciences. The candidate must have applied this skill and/or knowledge for the instruction of members and service to the kingdom to an extent above and beyond that normally expected of members of the Society. this is the highest award given in the SCA for excellence in the Arts and Sciences.','Laurel wreath worn on the head and/or badge of the order worn as a medallion or pin. In Ansteorra, many members of the Order also wear a cloak that incorporates the badge.','(Fieldless) a Laurel Wreath','',3,4,2,'2024-08-14 19:57:57','2024-08-14 19:57:57',1,1,NULL),
(9,'Award of the Sable Flur of Ansteorra','Flur','[]','A Member who greatly impressed the crown with a singular act of extraordinary artistry, or general overall excellence in the arts and sciences.','A cord braided Vert and Argent tied to a metal ring worn on the belt.','none','',3,1,2,'2024-10-31 19:33:44','2024-08-14 20:00:25',1,1096,NULL),
(10,'Award of the Aquila Aurea of Ansteorra (Chivalric)','Aquila Aurea','[]','For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or','',10,1,2,'2024-11-26 19:13:16','2024-08-14 20:03:19',1,1096,NULL),
(11,'Award of the Aquila Aurea of Ansteorra (Equestrian)','Aquila Aurea','[]','For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or','',10,1,2,'2024-11-26 19:13:27','2024-08-14 20:05:29',1,1096,NULL),
(12,'Award of the Aquila Aurea of Ansteorra (Rapier and Steel)','Aquila Aurea','[]','For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or','',10,1,2,'2024-11-26 19:13:38','2024-08-14 20:06:10',1,1096,NULL),
(13,'Award of the Aquila Aurea of Ansteorra (Missile Weapons)','Aquila Aurea','[]','For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or','',10,1,2,'2024-11-26 19:13:49','2024-08-14 20:06:29',1,1096,NULL),
(14,'Award of the Sable Talon of Ansteorra (Rapier and Steel)','Talon','[\"Rapier\",\"Cut & Thrust\"]','Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.','The badge worn as a medallion or pin\n\n\n','(Fieldless) An eagle’s leg erased à la quise sable.','',4,2,2,'2024-09-27 15:36:51','2024-08-14 20:08:46',1,1,NULL),
(15,'Award of the Sable Talon of Ansteorra (Equestrian)','Talon',NULL,'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.','The badge worn as a medallion or pin\n\n\n','(Fieldless) An eagle’s leg erased à la quise sable.','',6,2,2,'2024-08-14 20:10:06','2024-08-14 20:09:01',1,1,NULL),
(16,'Award of the Sable Talon of Ansteorra (Missile)','Talon','[\"Combat Archery\",\"Archery\",\"Thrown Weapons\",\"Siege Weapons\"]','Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.','The badge worn as a medallion or pin\n\n\n','(Fieldless) An eagle’s leg erased à la quise sable.','',5,2,2,'2024-09-27 15:42:47','2024-08-14 20:09:12',1,1,NULL),
(17,'Order of the Golden Lance of Ansteorra','CGL',NULL,'persons who have demonstrated exceptional leadership, skill and honor on the equestrian field; Service to Ansteorra and its people; Knowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.','A ribbon sable edged Or charged with a lance Or worn as a garter, and/or a pennon bearing the badge of the order (once registered) displayed on a tournament lance','none','',6,3,2,'2024-08-14 20:14:43','2024-08-14 20:14:43',1,1,NULL),
(18,'Award of the Golden Bridle of Ansteorra','Golden Bridle',NULL,'persons who have striven greatly to further their skill level and capabilities on the equestrian field; have given of themselves to the furthering of equestrian arts; and shall be exemplars of courtly graces, manners, and chivalry.','A cord braided vert argent and Or tied to a metal ring worn on the belt.','None','',6,1,2,'2024-08-14 20:16:09','2024-08-14 20:16:09',1,1,NULL),
(19,'Order of the Blade of Merit of Ansteorra','CBM',NULL,'And award given to a member for exceptional skill and abilities on the duello field of combat.','A ribbon of five equal width stripes of sable, Or, gules, Or, and sable.','Sable, on a pale gules fimbriated, a rapier inverted Or.','',4,3,2,'2024-08-15 12:29:39','2024-08-15 12:29:39',1,1,NULL),
(20,'Order of Defense','OD',NULL,'This is the highest award given in the SCA for excellence in the fighting fields of either rapier and/or cut-and-thrust combat. The candidate must be considered the equal of their prospective peers with the basic weapons of rapier and/or cut-and-thrust combat. The candidate must have applied this skill and/or knowledge for the instruction of members and service to the kingdom to an extent above and beyond that normally expected of members of the Society.','The official insignia is a white livery collar. Most members of the Order wear a medallion bearing the badge of the order suspended from their collar. In Ansteorra, members of the Order also wear a white short cloak that incorporates the badge.','(Tinctureless) Three rapiers in pall inverted tips crossed','',4,4,2,'2024-08-15 12:31:29','2024-08-15 12:31:29',1,1,NULL),
(22,'Award of the Queen\'s Rapier of Ansteorra','Queen\'s Rapier',NULL,'Given to a member who has striven greatly to further their skill level and capabilities in rapier combat; involved in the skills of the arts and basic heraldry; and should display courtly graces, manners and chivalry. This award is given only by the Queen. ','A cord braided Gules and Argent tied to a metal ring worn on the belt.','None','',4,1,2,'2024-08-15 12:35:25','2024-08-15 12:35:25',1,1,NULL),
(23,'Order of the White Scarf of Ansteorra','WSA',NULL,'Given to persons who have demonstrated exceptional skill and chivalry in combat with weapons of the duello; service to Ansteorra and its people; knowledge of the courtly graces; and obedience to the laws and ideals of Ansteorra and of the Society for Creative Anachronism. ','A white scarf worn about the left shoulder or above the left elbow.','Sable, on a pale argent between two rapiers, guards to center, proper, in chief a mullet of five greater and five lesser points sable.','',4,1,2,'2024-08-15 12:37:43','2024-08-15 12:37:43',1,1,NULL),
(24,'Award of the Golden Bridge of Ansteorra','Golden Bridge',NULL,'Given to those members who have gone above and beyond in the areas of Diversity, Equity, Inclusion and Belonging. This award recognizes a member\'s efforts in making our game more inclusive and easier to access to those who have historically been marginalized or excluded. ','','','',9,1,2,'2024-08-15 12:42:48','2024-08-15 12:42:48',1,1,NULL),
(25,'Nobility of the Court','Court Barony',NULL,'An award given at the pleasure of the Crown for outstanding work in service, contribution and help in the kingdom, well above that which is expected of the citizens of Ansteorra;\nKnowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.','A flat-topped or engrailed coronet decorated with the arms, ensign, or other badges of the barony which they rule','none','',9,3,2,'2024-08-15 12:47:22','2024-08-15 12:47:22',1,1,NULL),
(26,'Order of the Sable Garland of Ansteorra','Sable Garland',NULL,'Patroned by a Rose of Ansteorra, the members of this Order shall have the strength of nature, the degree of martial skill, and the commitment to Ansteorra that they shall have obtained a chosen position in the Order of the Chivalry. They shall have served and promoted the honor and spirit of Ansteorra within its borders and beyond. They shall have been a vital part of the fabric of Ansteorra through service, counsel, knowledge, and shared knowledge.  They shall have used all talents and means to preserve and protect the Kingdom, the Crown, and the standards and ideals of their oaths.','A sable cloak with a border, Or, upon which lies a garland of mullets of five greater and five lesser points slipped and leaved sable.	','none','',1,2,2,'2024-08-15 12:52:50','2024-08-15 12:52:50',1,1,NULL),
(27,'Award of Arms','AoA',NULL,'A simple grant of arms, given in recognition of membership and participation in our game. This is usually, the first award given to a member. ','None officially. Many crowns present new armigers with a metal circlet/fillet of ½” height or less. Such circlets are not currently reserved or restricted insignia.	','none','',9,2,2,'2024-08-15 12:59:28','2024-08-15 12:59:28',1,1,NULL),
(28,'Award of Amicitia of Ansteorra','Amicitia',NULL,'Award given to residents of foreign lands for service to the Kingdom of Ansteorra. ','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater andfive lesser points sable a foi Or.','',9,1,2,'2024-08-15 13:01:54','2024-08-15 13:01:54',1,1,NULL),
(29,'Order of the Lion of Ansteorra','Lion',NULL,'Known as the Defenders of the Dream, These individuals are awarded the lion for exemplifying the ideals of the Society and serving as an inspiration to others.','Badge of the award worn as a medallion or pin','Or, a mullet of five greater and five lesser points sable, overall a lion rampant argent.','',9,1,2,'2024-08-15 13:03:09','2024-08-15 13:03:09',1,1,NULL),
(30,'Award of the Rising Star of Ansteorra','Rising Star','[]','Given to youth members for exceptional endeavors, whether in service, martial, or arts and sciences. ','Badge of the award worn as a medallion or pin','Or a mullet of five greater and five lesser points sable overall a point issuant from base gules.','',10,1,2,'2024-11-26 19:14:13','2024-08-15 13:05:31',1,1096,NULL),
(31,'Award of the Lyra Aurea of Ansteorra','Lyra Aurea','[]','Given to youth members for endeavors in service to their community. ','Badge of the award worn as a medallion or pin','(Fieldless) On a mullet of five greater and five lesser points sable a lyre Or.','',10,1,2,'2024-11-26 19:14:26','2024-08-15 13:07:27',1,1096,NULL),
(32,'Award of the Sable Crane of Ansteorra','Crane','[\"Community Building - Hospitality\",\"Community Building - Member Engagement\",\"Community Building - Recruitment\",\"Community Building - Retention\",\"Event Administration - Event Steward\",\"Event Administration - Feast Steward\",\"Event Operations - Feast Service\",\"Event Operations - Field Marshalling\",\"Event Operations - Land management\",\"Event Operations - Lyst Coordination\",\"Event Operations - Sanitation\",\"Event Operations - Security\",\"Event Operations - Voice Heraldry\",\"Event Operations - Youth Activities\",\"Member Development - Coaching\\/Mentorship\",\"Member Development - Teaching\",\"Officer - Chatelaine\",\"Officer - Chronicler\",\"Officer - College of Heralds\",\"Officer - College of Scribes\",\"Officer - Exchequer\",\"Officer - Historian\",\"Officer - Marshalate\",\"Officer - Minister of Arts and Sciences\",\"Officer - Seneschalate\",\"Officer - Web Minister\",\"Online Activities - Community Engagement\",\"Online Activities - Graphic Design\",\"Online Activities - Social Media\",\"Service to a SCA Segment - A&S Activities\",\"Service to a SCA Segment - Charter Illumination\",\"Service to a SCA Segment - Fighter Support\",\"Service to a SCA Segment - Heraldic Consulting\",\"Service to a SCA Segment - Insignia Creation\"]','Given by the Crown unto persons who have displayed outstanding service to Ansteorra.','The badge worn as a medallion or pin','Or, a crane in its vigilance sable, armed, orbed, membered, crested and throated Or, fimbriated sable, bearing in its dexter claw a mullet of five greater and five lesser points sable.','',2,2,2,'2024-09-05 12:09:26','2024-08-15 13:18:12',1,1,NULL),
(33,'Award of the Compass Rose of Ansteorra','Compass Rose',NULL,'Given by the crown unto a person for outstanding service to children of the Kingdom.','The badge worn as a medallion or pin','Per chevron Or and gules, a compass rose sable.','',2,2,2,'2024-08-15 13:19:55','2024-08-15 13:19:55',1,1,NULL),
(34,'Award of the Sable Comet of Ansteorra','Sable Comet',NULL,'Service to an official branch of the SCA other than a Barony.  These are usually shires, cantons, strongholds, chases etc.','The badge worn as a medallion or pin','(Fieldless) A comet headed of a mullet of five greater and five lesser points fesswise reversed sable.','',2,2,2,'2024-08-15 13:21:44','2024-08-15 13:21:44',1,1,NULL),
(35,'Order of the Star of Merit of Ansteorra','CSM',NULL,'the Crown shall select persons who have served their kingdom well and faithfully, well above that which is normally expected of the citizens of Ansteorra; Knowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.','A ribbon Or edged sable charged with an Ansteorran star (a mullet of five greater and five lesser points sable), worn above the left elbow or below the right knee.','Argent, on a fess Or fimbriated a mullet of five greater and five lesser points sable.','',2,3,2,'2024-08-15 13:23:27','2024-08-15 13:23:27',1,1,NULL),
(36,'Order of the Pelican','OP',NULL,'The highest award given for Service to the Kingdom. ','Cap of maintenance (chapeau) and/or badge of the order worn as a medallion or pin. The cap may be gules trimmed ermine or gules trimmed argent goutty de sang (red blood drops). In Ansteorra, many members of the Order also wear a cloak that incorporates the badge.','(Fieldless) a pelican in her piety Also: (Tinctureless) A pelican vulning itself. This means that the pelican can be shown without the nest and chicks.','',2,4,2,'2024-08-15 13:26:30','2024-08-15 13:26:30',1,1,NULL),
(37,'Award of the Golden Star of Ansteorra','Golden Star',NULL,'Given to a member who has been found to have served faithfully in attendance to the Crown for the duration for their reign.','A unique token chosen by the granting Crown, bearing their initials or other personal mark.','none','',2,1,2,'2024-08-15 13:27:36','2024-08-15 13:27:36',1,1,NULL),
(38,'Award of the Sable Sparrow of Ansteorra','Sable Sparrow',NULL,'Given by the crown unto a person who has greatly impressed them with a singular act of extraordinary service','A cord braided sable Gules and Or tied to a metal ring worn on the belt.','none','',2,1,2,'2024-08-15 13:29:03','2024-08-15 13:29:03',1,1,NULL),
(39,'Award of the King\'s Gauntlet of Ansteorra','King\'s Gauntlet','[]','Given by the Sovereign unto persons found to have served them well and faithfully, above and beyond what is normally expected of a citizen of Ansteorra. ','A leather or cloth gauntlet bearing an Ansteorran star (a mullet of five greater and five lesser points sable) and the granting king’s sigil/cypher.','none','',2,2,2,'2024-09-03 01:19:49','2024-08-15 13:31:01',1,4,NULL),
(40,'Award of the Queen\'s Glove of Ansteorra','Queen\'s Glove',NULL,'An Award given by the Consort unto persons found to have served them well and faithfully, above and beyond what is normally expected of a citizen of Ansteorra. ','A cloth or leather glove bearing the Queen’s Rose (a rose sable charged with another Or, thereon a mullet of five greater and five lesser points sable) and the granting Queen’s sigil/cypher.','None','',2,2,2,'2024-08-15 13:32:39','2024-08-15 13:32:39',1,1,NULL),
(41,'Order of the Arc d\'Or of Ansteorra','CAO',NULL,'persons who have demonstrated exceptional skill with missile weaponry, including archery, thrown weapons, and siege engines, on the target or martial fields; by their service to Ansteorra and its people; in the promotion of the art of missile weaponry ; by their knowledge of the courtly graces; and by obedience to the laws and ideals of Ansteorra and for the Society for Creative Anachronism .','A ribbon Or edged sable charged with an Ansteorran star (a mullet of five greater and five lesser points sable), worn above the left elbow or below the right knee.','Sable, on a fess argent a mullet of five greater and five lesser points sable, overall two bows addorsed Or.','',5,3,2,'2024-08-15 13:37:21','2024-08-15 13:35:01',1,1,NULL),
(42,'Award of the King\'s Archer of Ansteorra','King\'s Archer',NULL,'persons who have striven greatly to further their skill level and capabilities in combat or target archery, the arts, basic heraldry, and\nshall be exemplars of courtly graces, manners, and chivalry.','A cord braided Sable and Vert tied to a metal ring worn on the belt.','None','',5,1,2,'2024-08-15 13:36:44','2024-08-15 13:36:44',1,1,NULL),
(43,'Sodality of the Sentinels of the Stargate','SSG','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','Sable, two spears in saltire between two towers in fess argent.','',7,2,39,'2024-09-30 01:39:51','2024-09-27 19:09:22',1,1096,NULL),
(44,'Order of the Oak of the Steppes','OOS','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion	','Or, on a pale sable endorsed vert an oak leaf inverted Or.','',7,2,27,'2024-10-31 19:32:22','2024-09-27 19:10:06',1,1096,NULL),
(45,'Order of the Firebrand of Bjornsborg','OFB','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','(Fieldless) A wooden torch bendwise sinister flammant proper.','',7,2,41,'2024-09-30 01:40:09','2024-09-27 19:10:56',1,1096,NULL),
(46,'Order of the Silent Trumpet of Bordermarch','OSTB','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','A baldric gules singly striped and tasseled azure, worn over the left shoulder.','None*','',7,2,34,'2024-09-30 01:40:17','2024-09-27 19:12:08',1,1096,NULL),
(47,'Order of the Dreigiau Bryn','ODB','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of Order sewn on a ribbon sable triply striped argent.','Or, a wyvern erect gules maintaining a halberd palewise sable, overall a triple-peaked mountain, issuant from base, vert.','',7,2,33,'2024-09-30 01:40:24','2024-09-27 19:12:49',1,1096,NULL),
(48,'Order of the Heart of the Sable Storm','OHSS','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','(Fieldless) A pile wavy Or.','',7,2,18,'2024-09-30 01:40:32','2024-09-27 19:13:35',1,1096,NULL),
(49,'Order des Cotes Anciennes','OCA','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','Argent, a mountain of three peaks issuant from base gules.','',7,2,17,'2024-09-30 01:40:40','2024-09-27 19:16:21',1,1096,NULL),
(50,'Order of the Raven’s Wings	','','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','(Fieldless) A vol sable.','',7,2,40,'2024-09-30 01:40:49','2024-09-27 19:17:00',1,1096,NULL),
(51,'Order of the Azure Keystone of Elfsea','AKE','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion	','(Fieldless) A keystone embattled on its upper edge azure charged with two bars wavy Or.','',7,2,29,'2024-09-30 01:40:57','2024-09-27 19:17:27',1,1096,NULL),
(52,'Order of the Lanternarius of Wiesenfeuer','OLW','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion	','(Fieldless) On an annulet of flame sable an annulet Or.','',7,2,20,'2024-09-30 01:41:07','2024-09-27 19:18:41',1,1096,NULL),
(53,'Order of the Serpent’s Toils of Loch Soilleir','STLS','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','(Fieldless) A sea-serpent in annulo head to chief and vorant of its own tail vert.','',7,2,36,'2024-09-30 01:41:16','2024-09-27 19:19:20',1,1096,NULL),
(54,'Order of the Western Cross of Bonwicke','OWCB','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','None','None','',7,2,30,'2024-09-30 01:41:28','2024-09-27 19:20:25',1,1096,NULL),
(55,'Order of the Lions Paw of Kenmare','OLPK','\"\"','Baronial Service Award that grants an Award of Arms and as such requires Crown approval.','Badge of the order worn as a medallion','(Fieldless) In chevron a tower sable sustained by two lion’s gambes erased Or.','',7,2,25,'2024-09-30 01:41:34','2024-09-27 19:21:07',1,1096,NULL),
(56,'Order of the Mark','OM','\"\"','5th Peerage for Missile related activities in the SCA including Archery, Thrown Weapons and other activities.','','','',5,4,2,'2025-06-21 23:46:17','2024-12-18 13:47:54',1,2870,NULL),
(57,'Trees are Cool','TAC','\"\"','Award for people who love Bears','','','',7,1,27,'2025-07-02 23:08:18','2025-04-21 22:44:51',1,1096,NULL),
(58,'Order of the Bastion of Vindheim','Bastion','\"\"','Persons who exemplify the spirit of the Dream in Vindheim and inspire the populace to greater deeds and service. The candidate should be a person that Vindheim relies upon for the betterment of the Principality, using their skills and talents, and by whose absence the Principality would be greatly deprived of a valuable member.','','','',11,1,11,'2025-06-22 00:21:00','2025-06-21 23:51:50',1,2870,NULL),
(59,'Award of the Brazier of Vindheim','Brazier','[\"Martial Endeavors as Art\",\"Martial Endeavors as Service\"]','Persons who have used their martial skills to enhance artistry or assist the service community. The candidate should use their skills and talents to contribute to the betterment of Vindheim or its subordinate communities. The Award may be given to an individual more than once, but only for the reasons listed.','','','',11,1,11,'2025-06-23 23:56:33','2025-06-22 00:06:22',1,2870,NULL),
(60,'Award of the Key of Keys of Vindheim','Key of Keys','\"\"','Persons who have given especial service in attendance to the persons of the Vindheim Coronet(s). The candidate should exemplify the pinnacle of service and respect for the Vindheim Coronet(s), the laws of the Principality of Vindheim, and the Kingdom of Ansteorra.','','','',11,1,11,'2025-06-22 00:47:25','2025-06-22 00:47:25',1,2870,NULL),
(61,'Award of the Key of Vindheim','Key','\"\"','Persons who have faithfully served in attendance to the Vindheim Coronet(s). The candidate should exemplify faithful service and respect for the Vindheim Coronet(s), the laws of the Principality of Vindheim, and the Kingdom of Ansteorra.','','','',11,1,11,'2025-06-22 00:52:30','2025-06-22 00:52:30',1,2870,NULL),
(62,'Order of the Fountain of Vindheim','Fountain','[\"Art as Service\",\"Art in support of Marshallate\"]','Persons who have offered public service to others and the Vindheim community through the arts and science or a martial aspect, including but not limited to bardcraft, music, vocal heraldry, and dance. The candidate should use their skills and talents to contribute to the ambiance, atmosphere, and splendor of Vindheim in our public spaces.','','','',11,1,11,'2025-06-23 23:58:36','2025-06-22 00:54:39',1,2870,NULL),
(63,'Order of the Golden Comb of Vindheim','Golden Comb','\"\"','Younger members of the Society that have not reached their majority shall be recognized for service above that which is normally expected of them.','','','',11,1,11,'2025-06-22 01:00:29','2025-06-22 01:00:29',1,2870,NULL),
(64,'Order of the Goutte de Vin of Vindheim','Goutte','\"\"','Persons who have served the Principality through provision of original award scrolls.','','','',11,1,11,'2025-06-22 01:03:02','2025-06-22 01:02:41',1,2870,NULL),
(65,'Award of the Pillar of Vindheim','Pillar','[\"Service to the Marshallate\",\"Service to the Arts\"]','Persons who have offered notable service to artistic or martial communities of Vindheim through the gift of their time or organizational skills. Service done to enable the art community or the martial community or organizing a task so flawlessly it becomes an art. The candidate should use their skills and talents to contribute to the betterment of Vindheim or its subordinate communities.','','','',11,1,11,'2025-06-23 23:59:43','2025-06-22 01:05:14',1,2870,NULL),
(66,'Order of the Sanguine Bowl of Vindheim','Sanguine Bowl','\"\"','Persons who embody the spirit of Vindheim even though they are not residents thereof. The inductee should reside outside the borders of Vindheim and exhibit love, dedication, and service which truly embodies the Vindheim ideal.','','','',11,1,11,'2025-06-22 01:23:10','2025-06-22 01:23:10',1,2870,NULL),
(67,'Award of the River of Vindheim','River','\"\"','Persons who have demonstrated excellence in period style, display, or fidelity.','','','',11,1,11,'2025-06-22 01:24:17','2025-06-22 01:24:17',1,2870,NULL),
(68,'Award of the Thunderbolt of Vindheim','Thunderbolt','\"\"','Persons who have distinguished themselves to the Coronet in an extraordinary fashion. The candidate should show an example of phenomenal prowess, extraordinary artistry, or remarkable service.','','','',11,1,11,'2025-06-22 01:27:48','2025-06-22 01:27:48',1,2870,NULL),
(69,'Award of the Wellspring of Vindheim','Wellspring','\"\"','Persons who have served the Principality through static arts.','','','',11,1,11,'2025-06-22 01:30:32','2025-06-22 01:30:32',1,2870,NULL),
(70,'Award of the Sanguine Company of Vindheim','Sanguine Company','[\"Armoured Combat\",\"Rapier Combat\",\"Steel Combat\",\"Combat Archery\",\"Target Archery\",\"Siege Combat\",\"Thrown Weapons\",\"Youth Rapier Combat\",\"Youth Armoured Combat\",\"Equestrian Deeds of Arms\"]','Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in marshal activities. The Award may be given to an individual more than once, but only once for a particular marshallate field.','','','',11,1,11,'2025-06-24 00:03:16','2025-06-22 01:47:18',1,2870,NULL),
(71,'Award of the Sinople Company of Vindheim','Sinople Company','[\"Fiber Arts\",\"Culinary Arts\",\"Performance Arts\",\"Household Arts\",\"Metal Arts\",\"Research\",\"Tangible Heraldry\",\"Husbandry\"]','Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in art or science activities. The Award may be given to an individual more than once, but only once for a particular art or science field.','','','',11,1,11,'2025-06-24 00:04:59','2025-06-22 01:48:50',1,2870,NULL),
(72,'Award of the Tenne Company of Vindheim','Tenne Company','[\"Group Management\",\"Event Administration\",\"Infrastructure\",\"General Assistance\",\"Event Operations\"]','Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in service activities. The Award may be given to an individual more than once, but only once for a particular type of service.','','','',11,1,11,'2025-06-24 00:06:16','2025-06-22 01:50:23',1,2870,NULL),
(73,'Award of the Dragon\'s Egg','BGDE','\"\"','\"We honor the contributions of youth to our Barony of Bryn Gwlad. Hear now, young gentles, you are hereby named a recipient of the Dragon’s Egg, in recognition of courtesy, service, and enthusiasm, that you may continue to inspire others.\"','','','',7,1,33,'2025-07-08 20:44:24','2025-07-08 20:43:58',1,1073,NULL),
(74,'Award of the Cross Fleury','BGCF','\"\"','\"The warriors of Bryn Gwlad are admired for their skills and courage. These gentles have defended our lands and our people with great honor, and are hereby awarded the Cross Fleury of Bryn Gwlad.\"','','','',7,1,33,'2025-07-08 20:45:04','2025-07-08 20:45:04',1,1073,NULL),
(75,'Award of the Golden Martlet','BGGM','\"\"','\"The Golden Martlet is a non-armigerous baronial award to honor service to the Barony of Bryn Gwlad.\"','','','',7,1,33,'2025-07-08 20:45:34','2025-07-08 20:45:34',1,1073,NULL),
(76,'Award of the Muse','BGM','\"\"','\"The beauty of Bryn Gwlad is a gift for all who gather here. Recipients of this award are highly admired artisans whose skills have enriched our barony, and are hereby awarded the Muse of Bryn Gwlad.\"','','','',7,1,33,'2025-07-08 20:46:25','2025-07-08 20:46:25',1,1073,NULL),
(77,'Award of the Silver Chalice','BGSC','\"\"','\"The quality of one’s character is highly prized in our Society. The persona of these gentles has greatly enriched the Barony of Bryn Gwlad, and with these words are hereby awarded the Silver Chalice of Bryn Gwlad.\"','','','',7,1,33,'2025-07-08 20:46:49','2025-07-08 20:46:49',1,1073,NULL),
(78,'Keeper of the Crucible','HGKC','\"\"','\"The Keeper of the Crucible is an award given by the Lord and Lady of Hellsgate to recognize works of Arts and Sciences within our Stronghold of Hellsgate.\"','','','',7,1,38,'2025-07-08 20:47:26','2025-07-08 20:47:26',1,1073,NULL),
(79,'Keeper of the Flame','HGF','\"\"','\"The Keeper of the Flame is an award given by the Lord and Lady of Hellsgate to recognize acts of service to our Stronghold of Hellsgate.\"','','','',7,1,38,'2025-07-08 20:48:27','2025-07-08 20:47:48',1,1073,NULL),
(80,'Keeper of the Gate','HGG','\"\"','\"The Keeper of the Gate is an award given by the Lord and Lady of Hellsgate to recognize skill in combat shown within our Stronghold of Hellsgate.\"','','','',7,1,38,'2025-07-08 20:48:11','2025-07-08 20:48:11',1,1073,NULL),
(81,'Steppes Test Non Armig Award #1','Steppes Test 1','\"\"','','','','',7,1,27,'2025-12-30 18:22:28','2025-12-30 18:22:28',1,1,NULL),
(82,'Steppes Test Non Armig #2','Steppes Test 2','\"\"','','','','',7,1,27,'2025-12-30 18:22:55','2025-12-30 18:22:55',1,1,NULL),
(83,'Glaslyn Test Non Armig 1','Glaslyn Test 1','\"\"','','','','',7,1,28,'2025-12-30 18:23:15','2025-12-30 18:23:15',1,1,NULL);
/*!40000 ALTER TABLE `awards_awards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_domains`
--

DROP TABLE IF EXISTS `awards_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_domains`
--

LOCK TABLES `awards_domains` WRITE;
/*!40000 ALTER TABLE `awards_domains` DISABLE KEYS */;
INSERT INTO `awards_domains` VALUES
(1,'Chivalric','2024-06-25 15:10:11','2024-06-25 13:59:24',1,1,NULL),
(2,'Service','2024-06-25 13:59:33','2024-06-25 13:59:33',1,1,NULL),
(3,'Arts & Sciences','2024-06-25 13:59:49','2024-06-25 13:59:49',1,1,NULL),
(4,'Rapier & Steel Weapons','2024-06-25 13:59:59','2024-06-25 13:59:59',1,1,NULL),
(5,'Missile Weapons','2024-06-25 14:00:13','2024-06-25 14:00:13',1,1,NULL),
(6,'Equestrian','2024-06-25 14:00:20','2024-06-25 14:00:20',1,1,NULL),
(7,'Baronial','2024-06-25 14:00:36','2024-06-25 14:00:36',1,1,NULL),
(8,'Kingdom','2024-06-25 14:00:44','2024-06-25 14:00:44',1,1,NULL),
(9,'General','2024-08-14 19:38:50','2024-08-14 19:32:54',1,1,NULL),
(10,'Youth','2024-11-26 19:12:30','2024-11-26 19:12:30',1,1096,NULL),
(11,'Principality','2025-06-22 00:20:30','2025-06-22 00:20:30',1,2870,NULL);
/*!40000 ALTER TABLE `awards_domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_events`
--

DROP TABLE IF EXISTS `awards_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `closed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`),
  KEY `branch_id` (`branch_id`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `awards_events_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_events`
--

LOCK TABLES `awards_events` WRITE;
/*!40000 ALTER TABLE `awards_events` DISABLE KEYS */;
INSERT INTO `awards_events` VALUES
(1,'Namron Protectorate and Kingdom Coronation','https://ansteorra.org/namron/protectorate-xlviii/',18,'2024-10-04 00:00:00','2024-10-06 00:00:00','2024-11-11 00:21:57','2024-09-29 17:29:31',1,65,NULL,1),
(2,'Bjornsborg\'s Fall Event - Dance Macabre','https://ansteorra.org/bjornsborg/danse-macabre/',41,'2024-10-18 00:00:00','2024-10-20 00:00:00','2024-11-11 00:22:35','2024-09-29 17:31:28',1,65,NULL,1),
(3,'Diamond Wars','https://gleannabhann.net/event/diamond-wars/',10,'2024-10-18 00:00:00','2024-10-20 00:00:00','2024-11-14 20:44:37','2024-09-29 17:32:09',1,669,NULL,1),
(4,'Eldern Hills - Samhain','https://ansteorra.org/eldern-hills/events/',17,'2024-10-25 00:00:00','2024-10-27 00:00:00','2024-11-19 02:53:50','2024-09-29 17:33:16',1,65,NULL,1),
(5,'Seawind\'s Defender','https://ansteorra.org/seawinds/',37,'2024-10-25 00:00:00','2024-10-27 00:00:00','2024-11-19 02:54:46','2024-09-29 17:33:45',1,65,NULL,1),
(6,'Vindheim Missile Academy II',' https://sites.google.com/u/5/d/1QToqHTcjWDrReUs_OJp_wvDdQln3hduf/preview',2,'2024-11-01 00:00:00','2024-11-03 00:00:00','2024-11-14 20:44:11','2024-09-29 17:34:23',1,669,NULL,1),
(7,'A Toast to Absent Friends: A Dia de Los Muertos Event','https://ansteorra.org/shadowlands',35,'2024-11-01 00:00:00','2024-11-03 00:00:00','2024-11-19 03:01:49','2024-09-29 17:35:04',1,65,NULL,1),
(8,'Queen\'s Champion','https://ansteorra.org/events',20,'2024-11-09 00:00:00','2024-11-09 00:00:00','2025-01-12 23:45:53','2024-09-29 17:35:58',1,65,NULL,1),
(9,'Bryn Gwlad Fall Baronial','https://ansteorra.org/bryn-gwlad/bryn-gwlad-fall-baronial-2024/',33,'2024-11-15 00:00:00','2024-11-17 00:00:00','2025-01-13 00:38:53','2024-09-29 17:36:22',1,65,NULL,1),
(10,'Bordermarch War of the Rams','https://ansteorra.org/bordermarch',34,'2024-11-21 00:00:00','2024-11-24 00:00:00','2025-01-17 23:25:37','2024-09-29 17:36:54',1,65,NULL,1),
(11,'Winter Crown Tournament','https://ansteorra.org/events',29,'2024-12-07 00:00:00','2024-12-07 00:00:00','2024-12-17 16:29:20','2024-09-29 17:37:27',1,669,NULL,1),
(12,'Vindheim Winter Coronet','https://ansteorra.org/events',23,'2024-12-14 00:00:00','2024-12-14 00:00:00','2024-12-17 16:32:05','2024-09-29 17:37:49',1,669,NULL,1),
(13,'Stargate Yule','https://ansteorra.org/events',39,'2024-12-14 00:00:00','2024-12-14 00:00:00','2025-01-13 00:41:45','2024-09-29 17:38:10',1,65,NULL,1),
(14,'Wiesenfeuer Yule','https://ansteorra.org/wiesenfeuer',20,'2024-12-21 00:00:00','2024-12-21 00:00:00','2025-01-13 00:48:27','2024-09-29 17:38:37',1,65,NULL,1),
(15,'Steppes 12th Night','https://ansteorra.org/steppes',27,'2025-01-04 00:00:00','2025-01-04 00:00:00','2025-01-13 00:48:50','2024-09-29 17:38:59',1,65,NULL,1),
(16,'Elfsea\'s Yule','https://ansteorra.org/elfsea',29,'2025-01-11 00:00:00','2025-01-11 00:00:00','2025-01-13 00:49:07','2024-09-29 17:39:28',1,65,NULL,1),
(17,'Deleted: Winter Round Table','https://ansteorra.org/round-table',2,'2025-01-18 00:00:00','2025-01-18 00:00:00','2024-09-29 17:51:16','2024-09-29 17:39:51',1,1096,'2024-09-29 17:51:16',0),
(18,'Marata Midwinter Melees','https://ansteorra.org/events',19,'2025-01-25 00:00:00','2025-01-25 00:00:00','2024-11-26 21:54:10','2024-09-29 17:40:21',1,669,NULL,0),
(19,'Winterkingdom',' https://ansteorra.org/northkeep/activities/events/winterkingdom/winterkingdom-collegium-when-in-rome/',25,'2025-02-01 00:00:00','2025-02-01 00:00:00','2024-09-29 17:45:02','2024-09-29 17:40:52',1,1096,NULL,0),
(20,'Bryn Gwlad Candlemas','https://ansteorra.org/bryn-gwlad',33,'2025-02-01 00:00:00','2025-02-01 00:00:00','2024-09-29 17:41:41','2024-09-29 17:41:21',1,1096,NULL,0),
(21,'Laurel\'s Prize Tournament','https://ansteorra.org/events',2,'2025-02-08 00:00:00','2025-02-08 00:00:00','2024-09-29 17:42:17','2024-09-29 17:42:17',1,1073,NULL,0),
(22,'Battle of the Pines','https://ansteorra.org/graywood',31,'2025-02-15 00:00:00','2025-02-15 00:00:00','2024-09-29 17:43:00','2024-09-29 17:43:00',1,1073,NULL,0),
(23,'Gulf Wars XXXIII','https://gulfwars.org',10,'2025-03-08 00:00:00','2025-03-16 00:00:00','2024-09-29 17:43:32','2024-09-29 17:43:32',1,1073,NULL,0),
(24,'Commander\'s Crucible Anniversary','https://ansteorra.org/hellsgate',38,'2025-03-28 00:00:00','2025-03-30 00:00:00','2024-09-29 17:44:16','2024-09-29 17:44:16',1,1073,NULL,0),
(25,'Elfsea\'s Defender','https://ansteorra.org/elfsea',29,'2025-04-04 00:00:00','2025-04-06 00:00:00','2024-09-29 17:44:49','2024-09-29 17:44:49',1,1073,NULL,0),
(26,'Coronation','https://ansteorra.org/events',2,'2025-04-12 00:00:00','2025-04-12 00:00:00','2024-09-29 17:45:19','2024-09-29 17:45:19',1,1073,NULL,0),
(27,'Stargate\'s Baronial','https://ansteorra.org/stargate',39,'2025-04-18 00:00:00','2025-04-20 00:00:00','2024-09-29 17:45:48','2024-09-29 17:45:48',1,1073,NULL,0),
(28,'Wiesenfeuer\'s Baronial','https://ansteorra.org/wiesenfeuer',20,'2025-04-18 00:00:00','2025-04-20 00:00:00','2025-08-29 00:23:37','2024-09-29 17:46:40',1,2870,NULL,0),
(29,'Glaslyn\'s Defender on the Flame','https://ansteorra.org/glaslyn',28,'2025-04-25 00:00:00','2025-04-27 00:00:00','2024-09-29 17:47:24','2024-09-29 17:47:24',1,1073,NULL,0),
(30,'Loch Soilleir\'s Baronial','https://ansteorra.org/loch-soilleir',36,'2025-05-02 00:00:00','2025-05-04 00:00:00','2024-09-29 17:47:54','2024-09-29 17:47:54',1,1073,NULL,0),
(31,'Queen\'s Champion','https://ansteorra.org/events',2,'2025-05-10 00:00:00','2025-05-10 00:00:00','2024-09-29 17:48:15','2024-09-29 17:48:15',1,1073,NULL,0),
(32,'Northkeep\'s Castellan','https://ansteorra.org/northkeep',25,'2025-05-16 00:00:00','2025-05-18 00:00:00','2024-09-29 17:48:39','2024-09-29 17:48:39',1,1073,NULL,0),
(33,'Steppes Warlord','https://ansteorra.org/steppes',27,'2025-05-23 00:00:00','2025-05-25 00:00:00','2024-11-19 16:41:29','2024-09-29 17:49:02',1,1073,NULL,0),
(34,'Summer Crown Tournament','https://ansteorra.org/events',2,'2025-06-07 00:00:00','2025-06-07 00:00:00','2024-09-29 17:49:22','2024-09-29 17:49:22',1,1073,NULL,0),
(35,'Vindheim Summer Coronet','https://ansteorra.or/events',2,'2025-06-21 00:00:00','2025-06-21 00:00:00','2024-09-29 17:49:48','2024-09-29 17:49:48',1,1073,NULL,0),
(36,'Kingdom Collegium','https://ansteorra.org/events',2,'2025-07-12 00:00:00','2025-07-12 00:00:00','2024-09-29 17:50:07','2024-09-29 17:50:07',1,1073,NULL,0),
(37,'Deleted: Summer Round Table','https://ansteorra.org/round-table',2,'2025-07-19 00:00:00','2025-07-19 00:00:00','2024-09-29 17:51:38','2024-09-29 17:50:39',1,1096,'2024-09-29 17:51:38',0),
(38,'Pennsic','https://pennsic.org',10,'2025-07-25 00:00:00','2025-08-10 00:00:00','2024-09-29 17:51:11','2024-09-29 17:51:11',1,1073,NULL,0),
(39,'Steppes Artisan','https://ansteorra.org/steppes',27,'2025-08-16 00:00:00','2025-08-16 00:00:00','2024-09-29 17:51:35','2024-09-29 17:51:35',1,1073,NULL,0),
(40,'Serpent\'s Symposium VII','https://ansteorra.org/loch-soilleir',36,'2025-08-23 00:00:00','2025-08-23 00:00:00','2024-09-29 17:52:01','2024-09-29 17:52:01',1,1073,NULL,0),
(41,'Bonwicke\'s War of Legends','https://ansteorra.org/bonwicke',30,'2025-08-29 00:00:00','2025-08-31 00:00:00','2024-09-29 17:52:32','2024-09-29 17:52:32',1,1073,NULL,0),
(42,'Elfsea Baronial College','https://ansteorra.org/elfsea',29,'2025-09-07 00:00:00','2025-09-07 00:00:00','2024-09-29 17:52:55','2024-09-29 17:52:55',1,1073,NULL,0),
(43,'Kingdom Arts and Sciences','https://ansteorra.org/events',2,'2025-09-13 00:00:00','2025-09-13 00:00:00','2024-09-29 17:53:13','2024-09-29 17:53:13',1,1073,NULL,0),
(44,'Mooneschadowe\'s Triumphe of the Eclipse','https://ansteorra.org/mooneschadowe',22,'2025-09-19 00:00:00','2025-09-21 00:00:00','2024-09-29 17:53:40','2024-09-29 17:53:40',1,1073,NULL,0),
(45,'Raven\'s Fort Defender of the Fort','https://ansteorra.org/ravensfort',40,'2025-09-19 00:00:00','2025-09-21 00:00:00','2024-09-29 17:54:14','2024-09-29 17:54:14',1,1073,NULL,0),
(46,'Rosenfeld Champions and Three Things','https://ansteorra.org/rosenfeld',32,'2025-09-26 00:00:00','2025-09-28 00:00:00','2024-09-29 17:54:54','2024-09-29 17:54:44',1,1073,NULL,0),
(47,'Ffynnon Gath\'s War of Ages','https://ansteorra.org/ffynnon-gath',42,'2025-09-26 00:00:00','2025-09-28 00:00:00','2024-09-29 17:55:33','2024-09-29 17:55:33',1,1073,NULL,0),
(48,'Bjornsborg Spring Event','Bjornsborg\'s Spring Event',41,'2025-04-25 00:00:00','2025-04-27 00:00:00','2024-11-17 13:58:18','2024-11-17 13:58:18',1,1096,NULL,0),
(49,'A Day in the...','Ravens Fort Spring event',40,'2025-02-21 00:00:00','2025-03-23 00:00:00','2024-12-15 14:11:07','2024-12-15 14:11:07',1,1096,NULL,0),
(50,'Enchanted Conflict (Raven\'s Fort)','Raven\'s Fort\'s Spring Event',40,'2025-02-21 00:00:00','2025-02-23 00:00:00','2024-12-22 00:27:07','2024-12-22 00:27:07',1,1073,NULL,0),
(51,'Bordermarch Baronials','Spring Baronial Event',34,'2025-03-29 00:00:00','2025-03-30 00:00:00','2025-01-22 03:48:04','2025-01-22 03:48:04',1,65,NULL,0);
/*!40000 ALTER TABLE `awards_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_levels`
--

DROP TABLE IF EXISTS `awards_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `progression_order` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_levels`
--

LOCK TABLES `awards_levels` WRITE;
/*!40000 ALTER TABLE `awards_levels` DISABLE KEYS */;
INSERT INTO `awards_levels` VALUES
(1,'Non-Armigerous',0,'2024-06-25 13:53:55','2024-06-25 13:53:55',1,1,NULL),
(2,'Armigerous',1,'2024-06-25 13:54:15','2024-06-25 13:54:15',1,1,NULL),
(3,'Grant',2,'2024-06-25 13:55:21','2024-06-25 13:55:21',1,1,NULL),
(4,'Peerage',4,'2024-06-25 13:56:44','2024-06-25 13:55:32',1,1,NULL),
(5,'Nobility',3,'2024-06-25 13:56:55','2024-06-25 13:56:55',1,1,NULL);
/*!40000 ALTER TABLE `awards_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_phinxlog`
--

DROP TABLE IF EXISTS `awards_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_phinxlog`
--

LOCK TABLES `awards_phinxlog` WRITE;
/*!40000 ALTER TABLE `awards_phinxlog` DISABLE KEYS */;
INSERT INTO `awards_phinxlog` VALUES
(20240614001010,'InitAwards','2024-09-29 15:47:03','2024-09-29 15:47:04',0),
(20240912174050,'AddPersonToNotify','2024-09-29 15:47:04','2024-09-29 15:47:04',0),
(20241017085448,'AddEventClosedFlag','2024-10-31 23:13:10','2024-10-31 23:13:10',0),
(20241018230237,'AddNoActionReason','2024-10-31 23:13:10','2024-10-31 23:13:10',0),
(20241018231315,'RecommendationStates','2024-10-31 23:13:10','2024-10-31 23:13:10',0),
(20251025000000,'CreateAwardGatheringActivities','2025-10-30 21:03:03','2025-10-30 21:03:03',0),
(20251025214505,'AddGatheringIdToRecommendations','2025-10-30 21:03:03','2025-10-30 21:03:03',0),
(20251025214511,'AddGatheringIdToRecommendationsEvents','2025-10-30 21:03:03','2025-10-30 21:03:03',0),
(20251026133257,'RunMigrateAwardEvents','2025-10-30 21:03:03','2025-10-30 21:03:03',0),
(20251130230000,'MakeEventIdNullableInRecommendationsEvents','2025-12-15 00:35:22','2025-12-15 00:35:23',0);
/*!40000 ALTER TABLE `awards_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations`
--

DROP TABLE IF EXISTS `awards_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stack_rank` int(11) NOT NULL,
  `requester_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `award_id` int(11) NOT NULL,
  `specialty` varchar(255) DEFAULT NULL,
  `requester_sca_name` varchar(255) NOT NULL,
  `member_sca_name` varchar(255) NOT NULL,
  `contact_number` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `call_into_court` varchar(100) NOT NULL,
  `court_availability` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL DEFAULT 'submitted',
  `state_date` datetime DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `gathering_id` int(11) DEFAULT NULL,
  `given` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `person_to_notify` varchar(255) DEFAULT NULL,
  `no_action_reason` text DEFAULT NULL,
  `close_reason` text DEFAULT NULL,
  `state` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stack_rank` (`stack_rank`),
  KEY `deleted` (`deleted`),
  KEY `branch_id` (`branch_id`),
  KEY `requester_id` (`requester_id`),
  KEY `member_id` (`member_id`),
  KEY `award_id` (`award_id`),
  KEY `BY_GATHERING_ID` (`gathering_id`),
  CONSTRAINT `awards_recommendations_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `awards_recommendations_ibfk_2` FOREIGN KEY (`requester_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `awards_recommendations_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `awards_recommendations_ibfk_4` FOREIGN KEY (`award_id`) REFERENCES `awards_awards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recommendations_gathering_id` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=594 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_recommendations`
--

LOCK TABLES `awards_recommendations` WRITE;
/*!40000 ALTER TABLE `awards_recommendations` DISABLE KEYS */;
INSERT INTO `awards_recommendations` VALUES
(579,11,2878,2872,22,72,'Event Administration','Iris Demoer','Bryce Demoer','9999999999','iris@ampdemo.com','some cool reason','Not Set','Not Set','Closed','2025-08-29 00:20:33',28,27,'2025-08-23 00:00:00','2025-08-29 00:20:33','2025-07-02 21:18:49',1,2870,NULL,'',NULL,'','Given'),
(580,12,2878,2873,33,44,NULL,'Iris Demoer','Caroline Demoer','9999999999','iris@ampdemo.com','asdf','Not Set','Not Set','Closed','2025-11-06 00:59:00',NULL,NULL,'2025-11-08 00:00:00','2025-11-06 00:59:00','2025-07-02 21:19:06',1,1,NULL,'',NULL,'','Given'),
(581,13,2878,2872,22,48,NULL,'Iris Demoer','Bryce Demoer','9999999999','iris@ampdemo.com','asdf','Not Set','Not Set','In Progress','2025-07-02 21:19:19',NULL,NULL,NULL,'2025-07-02 21:19:19','2025-07-02 21:19:19',1,2878,NULL,'',NULL,NULL,'Submitted'),
(582,14,2878,2879,36,8,'Banner Painting','Iris Demoer','Jael Demoer','9999999999','iris@ampdemo.com','reason reason reason\r\n','Not Set','Not Set','To Give','2025-12-16 02:55:40',NULL,NULL,NULL,'2025-12-16 02:55:40','2025-07-02 21:20:47',1,1,NULL,'',NULL,'','Scheduled'),
(583,15,2878,2872,22,43,NULL,'Iris Demoer','Bryce Demoer','9999999999','iris@ampdemo.com','testing again!  bryce is great','Not Set','Not Set','In Progress','2025-12-16 02:49:22',NULL,NULL,NULL,'2025-12-16 02:49:22','2025-07-02 23:05:15',1,1,NULL,'',NULL,'','Submitted'),
(584,16,2878,2872,22,57,NULL,'Iris Demoer','Bryce Demoer','9999999999','iris@ampdemo.com','asdf','Not Set','Not Set','Closed','2025-07-16 23:45:57',39,37,NULL,'2025-07-16 23:45:57','2025-07-02 23:08:54',1,2880,NULL,'',NULL,'Given','Given'),
(586,17,1,2878,30,3,NULL,'Admin von Admin','Iris Basic User Demoer','555-555-5555','admin@amp.ansteorra.org','l;kjas;dlj;asklj;lkjasd;fjklasdf','Not Set','Not Set','To Give','2025-12-16 02:55:16',NULL,84,NULL,'2025-12-16 02:55:16','2025-09-12 03:04:44',1,1,NULL,'',NULL,'','Scheduled'),
(587,18,2882,2871,17,45,NULL,'Leonard Landed with Stronghold Demoer','Agatha Local MoAS Demoer','1212121212','leonard@ampdemo.com','she\'s stabby','With Notice','Evening','To Give','2025-12-16 02:56:08',NULL,84,NULL,'2025-12-16 02:56:08','2025-09-14 21:36:18',1,1,NULL,'Bryce Demoer',NULL,'','Scheduled'),
(588,19,2880,2878,30,81,NULL,'Kal Local Landed w Canton Demoer','Iris Basic User Demoer','1111111112','kal@ampdemo.com','iris is cool','With notice given to another person','Morning','Scheduling','2025-12-30 18:54:28',NULL,94,NULL,'2025-12-30 18:54:28','2025-12-30 18:25:26',1,2880,NULL,'',NULL,'','Need to Schedule'),
(589,20,2880,2874,27,82,NULL,'Kal Local Landed w Canton Demoer','Devon Regional Armored Demoer','1111111112','kal@ampdemo.com','devon is also cool','Not Set','Not Set','Scheduling','2026-01-01 20:06:33',NULL,94,NULL,'2026-01-01 20:06:33','2025-12-30 18:25:44',1,2880,NULL,'',NULL,'','Need to Schedule'),
(590,21,2880,2878,30,83,NULL,'Kal Local Landed w Canton Demoer','Iris Basic User Demoer','1111111112','kal@ampdemo.com','iris does stuff in glaslyn','With notice given to another person','Morning','To Give','2026-01-01 20:07:05',NULL,90,NULL,'2026-01-01 20:07:05','2025-12-30 18:26:06',1,2880,NULL,'',NULL,'','Scheduled'),
(591,22,2880,2872,22,81,NULL,'Kal Local Landed w Canton Demoer','Bryce Local Seneschal Demoer','1111111112','kal@ampdemo.com','something cool that i write here','Not Set','Not Set','Closed','2026-01-14 01:51:28',NULL,94,'2025-12-21 00:00:00','2026-01-14 01:51:28','2025-12-30 18:27:31',1,2880,NULL,'',NULL,'','Given'),
(592,23,2880,2879,36,83,NULL,'Kal Local Landed w Canton Demoer','Jael Principality Coronet Demoer','1111111112','kal@ampdemo.com','neat things','Not Set','Not Set','Closed','2026-01-12 02:35:27',NULL,94,'2026-01-10 00:00:00','2026-01-12 02:35:27','2025-12-30 18:30:48',1,2880,NULL,'',NULL,'','Given'),
(593,24,1,2873,33,2,NULL,'Admin von Admin','Caroline Regional Seneschal Demoer','555-555-5555','admin@amp.ansteorra.org','started to learn how to fight','Not Set','Not Set','To Give','2026-01-10 02:47:28',NULL,103,NULL,'2026-01-10 02:47:28','2026-01-10 02:46:47',1,1,NULL,'',NULL,'','Scheduled');
/*!40000 ALTER TABLE `awards_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations_events`
--

DROP TABLE IF EXISTS `awards_recommendations_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_recommendations_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recommendation_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `gathering_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recommendation_id` (`recommendation_id`),
  KEY `event_id` (`event_id`),
  KEY `BY_GATHERING_ID` (`gathering_id`),
  CONSTRAINT `awards_recommendations_events_ibfk_1` FOREIGN KEY (`recommendation_id`) REFERENCES `awards_recommendations` (`id`),
  CONSTRAINT `awards_recommendations_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `awards_events` (`id`),
  CONSTRAINT `fk_recommendations_events_gathering_id` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2096 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_recommendations_events`
--

LOCK TABLES `awards_recommendations_events` WRITE;
/*!40000 ALTER TABLE `awards_recommendations_events` DISABLE KEYS */;
INSERT INTO `awards_recommendations_events` VALUES
(2091,586,43,NULL),
(2092,586,44,NULL),
(2093,586,45,NULL),
(2094,587,47,NULL),
(2095,593,NULL,103);
/*!40000 ALTER TABLE `awards_recommendations_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations_states_logs`
--

DROP TABLE IF EXISTS `awards_recommendations_states_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_recommendations_states_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recommendation_id` int(11) NOT NULL,
  `from_state` varchar(255) NOT NULL,
  `to_state` varchar(255) NOT NULL,
  `from_status` varchar(255) NOT NULL,
  `to_status` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recommendation_id` (`recommendation_id`),
  CONSTRAINT `awards_recommendations_states_logs_ibfk_1` FOREIGN KEY (`recommendation_id`) REFERENCES `awards_recommendations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1205 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awards_recommendations_states_logs`
--

LOCK TABLES `awards_recommendations_states_logs` WRITE;
/*!40000 ALTER TABLE `awards_recommendations_states_logs` DISABLE KEYS */;
INSERT INTO `awards_recommendations_states_logs` VALUES
(1121,579,'New','Submitted','In Progress','In Progress','2025-07-02 21:18:49',1),
(1122,580,'New','Submitted','In Progress','In Progress','2025-07-02 21:19:06',1),
(1123,581,'New','Submitted','In Progress','In Progress','2025-07-02 21:19:19',1),
(1124,582,'New','Submitted','In Progress','In Progress','2025-07-02 21:20:47',1),
(1125,583,'New','Submitted','In Progress','In Progress','2025-07-02 23:05:15',1),
(1126,584,'New','Submitted','In Progress','In Progress','2025-07-02 23:08:54',1),
(1128,584,'Submitted','In Consideration','In Progress','In Progress','2025-07-08 21:59:32',1),
(1129,584,'In Consideration','Awaiting Feedback','In Progress','In Progress','2025-07-08 21:59:39',1),
(1130,584,'Submitted','Need to Schedule','In Progress','Scheduling','2025-07-16 22:54:39',1),
(1131,584,'Need to Schedule','Awaiting Feedback','Scheduling','In Progress','2025-07-16 23:12:10',1),
(1140,584,'Awaiting Feedback','King Approved','In Progress','In Progress','2025-07-16 23:44:05',1),
(1142,584,'King Approved','Queen Approved','In Progress','In Progress','2025-07-16 23:44:16',1),
(1145,584,'Queen Approved','Need to Schedule','In Progress','Scheduling','2025-07-16 23:44:47',1),
(1146,584,'Need to Schedule','Scheduled','Scheduling','To Give','2025-07-16 23:45:27',1),
(1147,584,'Scheduled','Announced Not Given','To Give','To Give','2025-07-16 23:45:51',1),
(1148,584,'Announced Not Given','Given','To Give','Closed','2025-07-16 23:45:57',1),
(1154,579,'Submitted','Scheduled','In Progress','To Give','2025-08-29 00:20:33',1),
(1158,586,'New','Submitted','In Progress','In Progress','2025-09-12 03:04:44',1),
(1159,587,'New','Submitted','In Progress','In Progress','2025-09-14 21:36:19',1),
(1160,587,'Submitted','Need to Schedule','In Progress','Scheduling','2025-09-14 21:36:28',1),
(1161,587,'Need to Schedule','In Consideration','Scheduling','In Progress','2025-09-14 21:36:34',1),
(1162,587,'In Consideration','Submitted','In Progress','In Progress','2025-09-14 21:36:37',1),
(1163,587,'Submitted','In Consideration','In Progress','In Progress','2025-09-14 21:36:38',1),
(1164,587,'In Consideration','Need to Schedule','In Progress','Scheduling','2025-09-14 21:36:49',1),
(1165,587,'Need to Schedule','In Consideration','Scheduling','In Progress','2025-09-14 21:37:59',1),
(1166,587,'In Consideration','King Approved','In Progress','In Progress','2025-09-14 21:38:04',1),
(1167,580,'Submitted','Given','In Progress','Closed','2025-11-06 00:59:00',1),
(1168,586,'Submitted','Scheduled','In Progress','To Give','2025-12-16 02:52:41',1),
(1169,587,'King Approved','Scheduled','In Progress','To Give','2025-12-16 02:53:16',1),
(1170,587,'Scheduled','Deferred till Later','To Give','In Progress','2025-12-16 02:54:03',1),
(1171,586,'Scheduled','In Consideration','To Give','In Progress','2025-12-16 02:54:26',1),
(1172,586,'In Consideration','Scheduled','In Progress','To Give','2025-12-16 02:54:45',1),
(1173,582,'Submitted','In Consideration','In Progress','In Progress','2025-12-16 02:55:08',1),
(1174,582,'In Consideration','Scheduled','In Progress','To Give','2025-12-16 02:55:40',1),
(1175,587,'Deferred till Later','Scheduled','In Progress','To Give','2025-12-16 02:56:08',1),
(1176,588,'New','Submitted','In Progress','In Progress','2025-12-30 18:25:26',1),
(1177,589,'New','Submitted','In Progress','In Progress','2025-12-30 18:25:44',1),
(1178,590,'New','Submitted','In Progress','In Progress','2025-12-30 18:26:06',1),
(1179,590,'Submitted','Need to Schedule','In Progress','Scheduling','2025-12-30 18:26:49',1),
(1180,591,'New','Submitted','In Progress','In Progress','2025-12-30 18:27:31',1),
(1181,591,'Submitted','Scheduled','In Progress','To Give','2025-12-30 18:28:08',1),
(1182,592,'New','Submitted','In Progress','In Progress','2025-12-30 18:30:48',1),
(1183,588,'Submitted','Need to Schedule','In Progress','Scheduling','2025-12-30 18:54:28',1),
(1184,592,'Submitted','Need to Schedule','In Progress','Scheduling','2025-12-30 20:56:15',1),
(1185,592,'Need to Schedule','Scheduled','Scheduling','To Give','2025-12-30 20:56:42',1),
(1186,592,'Scheduled','Given','To Give','Closed','2025-12-30 20:58:18',1),
(1187,589,'Submitted','Awaiting Feedback','In Progress','In Progress','2026-01-01 20:05:31',1),
(1188,589,'Awaiting Feedback','Need to Schedule','In Progress','Scheduling','2026-01-01 20:06:33',1),
(1189,590,'Need to Schedule','Scheduled','Scheduling','To Give','2026-01-01 20:07:05',1),
(1190,591,'Scheduled','Given','To Give','Closed','2026-01-01 20:08:35',1),
(1191,591,'Given','Submitted','Closed','In Progress','2026-01-01 20:08:43',1),
(1192,592,'Given','Submitted','Closed','In Progress','2026-01-01 20:08:54',1),
(1193,592,'Submitted','Need to Schedule','In Progress','Scheduling','2026-01-06 19:35:10',1),
(1194,592,'Need to Schedule','Scheduled','Scheduling','To Give','2026-01-06 19:35:35',1),
(1195,592,'Scheduled','Given','To Give','Closed','2026-01-06 19:36:45',1),
(1196,593,'New','Submitted','In Progress','In Progress','2026-01-10 02:46:47',1),
(1197,593,'Submitted','Scheduled','In Progress','To Give','2026-01-10 02:47:28',1),
(1198,592,'Given','Submitted','Closed','In Progress','2026-01-12 02:16:25',1),
(1199,592,'Submitted','Need to Schedule','In Progress','Scheduling','2026-01-12 02:33:03',1),
(1200,592,'Need to Schedule','Scheduled','Scheduling','To Give','2026-01-12 02:33:39',1),
(1201,592,'Scheduled','Given','To Give','Closed','2026-01-12 02:35:27',1),
(1202,591,'Submitted','Need to Schedule','In Progress','Scheduling','2026-01-14 01:49:29',1),
(1203,591,'Need to Schedule','Scheduled','Scheduling','To Give','2026-01-14 01:50:32',1),
(1204,591,'Scheduled','Given','To Give','Closed','2026-01-14 01:51:28',1);
/*!40000 ALTER TABLE `awards_recommendations_states_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(8) NOT NULL COMMENT 'Non-sequential public identifier safe for client exposure',
  `name` varchar(128) NOT NULL,
  `location` varchar(128) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `links` text DEFAULT NULL,
  `can_have_members` tinyint(1) NOT NULL DEFAULT 1,
  `lft` int(11) DEFAULT NULL,
  `rght` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `domain` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `idx_branches_public_id` (`public_id`),
  KEY `parent_id` (`parent_id`),
  KEY `lft` (`lft`),
  KEY `rght` (`rght`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES
(2,'mwnuttW8','Ansteorra','Texas & Oklahoma',NULL,'[{\"url\":\"https:\\/\\/anstorra.org\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/AnsteorraSCA\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/78670722996\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/www.youtube.com\\/channel\\/UC09oAqJbPGviT5ff9AAtBjQ\",\"type\":\"youtube\"},{\"url\":\"https:\\/\\/www.instagram.com\\/scakingdomofansteorra\\/\",\"type\":\"instagram\"},{\"url\":\"https:\\/\\/discord.gg\\/kPM52QgqK6\",\"type\":\"discord\"}]',0,1,66,'2025-04-21 22:30:47','2024-08-14 13:35:17',1,1,NULL,'Kingdom','ansteorra.org'),
(10,'gcKMYvbK','Out of Kingdom','Out of Kingdom',NULL,'[]',1,67,68,'2025-01-12 17:54:14','2024-08-14 13:35:18',1,1096,NULL,'N/A',''),
(11,'jSGAUtSr','Vindheim','Oklahoma & the Panhandle of Texas',2,'[{\"url\":\"https:\\/\\/ansteorra.org\\/vindheim\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/656962524780331\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/ansteorra.org\\/vindheim\\/calendar\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/discord.gg\\/PJbnsEMRmv\",\"type\":\"discord\"}]',0,2,27,'2025-03-04 15:43:47','2024-08-13 22:56:44',1,2866,NULL,'Principality','vindheim.ansteorra.org'),
(12,'hXPHxXM9','Central Region','Cities around the I-20 corridor',2,'[]',0,28,41,'2025-06-04 23:50:48','2024-08-13 22:58:37',1,2866,NULL,'Region','central.ansteorra.org'),
(13,'vjQ3iWfF','Southern Region','Southern half of Texas',2,'[]',0,42,63,'2025-06-04 23:48:17','2024-08-13 22:58:59',1,2866,NULL,'Region','southern.ansteorra.org'),
(14,'x67oKj3v','Shire of Adlersruhe','Amarillo, TX',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/adlersruhe\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40adlersruhe.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/412770745519031\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/gJ2hzbh4aa\",\"type\":\"discord\"}]',1,3,4,'2025-06-04 23:33:41','2024-08-13 23:01:05',1,2866,NULL,'Local Group','Adlersruhe.ansteorra.org'),
(15,'CMkfcdwV','Canton of Chemin Noir','Bartlesville, OK',25,'[{\"url\":\"https:\\/\\/ansteorra.org\\/chemin-noir\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=northkeep.ansteorra.org_hr396dqho0t6h9ltn5ji524ldc%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/scacheminnoir\",\"type\":\"facebook\"}]',1,20,21,'2025-06-04 23:39:13','2024-08-13 23:02:13',1,2866,NULL,'Local Group','chemin-noir.ansteorra.org'),
(16,'ZPChvZM7','Canton of Myrgenfeld','Guthrie, OK',20,'[{\"url\":\"https:\\/\\/ansteorra.org\\/myrgenfeld\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40myrgenfeld.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"}]',1,12,13,'2025-06-04 23:38:13','2024-08-13 23:02:58',1,2866,NULL,'Local Group','Myrgenfeld.ansteorra.org'),
(17,'dzBckaa4','Barony of Eldern Hills','Lawton, OK',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/eldern-hills\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/u\\/0\\/embed?src=webminister@eldern-hills.ansteorra.org&ctz=America\\/Chicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/278741078929172\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/ga6fEEkFxE\",\"type\":\"discord\"}]',1,5,6,'2025-06-04 23:41:12','2024-08-13 23:04:00',1,2866,NULL,'Local Group','eldern-hills.ansteorra.org'),
(18,'DVgHa3J4','Barony of Namron','Norman, OK',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/namron\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=l0harfs5tqu14a6ta8rt2ggvec%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/93652129248\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/eEyTuvjXre\",\"type\":\"discord\"}]',1,7,10,'2025-06-04 23:40:49','2024-08-13 23:05:07',1,2866,NULL,'Local Group','namron.ansteorra.org'),
(19,'G3RScKVy','Riding of Marata','Enid, OK',22,'[{\"url\":\"https:\\/\\/ansteorra.org\\/marata\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/SCAEnid\",\"type\":\"facebook\"}]',1,16,17,'2025-06-04 23:30:21','2024-08-14 18:59:29',1,2866,NULL,'Local Group','marata.ansteorra.org'),
(20,'UBtEu4sm','Barony of Wiesenfeuer','Oklahoma City, OK',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/wiesenfeuer\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=l0harfs5tqu14a6ta8rt2ggvec%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/272431286188558\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/eEyTuvjXre\",\"type\":\"discord\"}]',1,11,14,'2025-03-04 15:53:08','2024-08-14 19:01:30',1,2866,NULL,'Local Group','wiesenfeuer.ansteorra.org'),
(21,'5t8rdkht','Canton of Skorragarðr','Shawnee, OK',18,'[{\"url\":\"https:\\/\\/ansteorra.org\\/skorragardr\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40skorragardr.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/307430935797\",\"type\":\"facebook\"}]',1,8,9,'2025-06-04 23:40:29','2024-08-14 19:03:21',1,2866,NULL,'Local Group','skorragardr.ansteorra.org'),
(22,'Z4HpsXKi','Province of Mooneschadowe','Stillwater, OK',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/mooneschadowe\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=rkbhhf7m1vbhchtqjbq9iggpmk%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/2200765792\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/7jgdeE4hnr\",\"type\":\"discord\"}]',1,15,18,'2025-06-04 23:30:02','2024-08-14 19:04:21',1,2866,NULL,'Local Group','mooneschadowe.ansteorra.org'),
(23,'3XhrJEvp','Canton of Wyldewode','Tahlequah, OK',25,'[{\"url\":\"https:\\/\\/ansteorra.org\\/wyldewode\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=northkeep.ansteorra.org_m0q38jbcjdrmgsgp7u0lufri34%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Wyldewode\",\"type\":\"facebook\"}]',1,22,23,'2025-06-04 23:38:44','2024-08-14 19:06:35',1,2866,NULL,'Local Group','Wyldewode.ansteorra.org'),
(24,'5u6vkAws','Kingdom Land','All of Ansteorra not supported by a group.',2,'[]',1,64,65,'2025-01-12 17:50:51','2024-08-14 19:06:36',1,1096,NULL,'N/A',''),
(25,'GXomo4gx','Barony of Northkeep','Tulsa, OK',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/northkeep\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40northkeep.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/26587534453\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/sxGErTR2eq\",\"type\":\"discord\"}]',1,19,24,'2025-06-04 23:39:42','2024-08-14 19:07:27',1,2866,NULL,'Local Group','northkeep.ansteorra.org'),
(26,'nSS5McH6','Shire of Brad Leah','Wichita Falls, TX',11,'[{\"url\":\"https:\\/\\/ansteorra.org\\/brad-leah\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40brad-leah.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/390730670964114\",\"type\":\"facebook\"}]',1,25,26,'2025-06-04 23:33:21','2024-08-14 19:08:11',1,2866,NULL,'Local Group','brad-leah.ansteorra.org'),
(27,'nkYRKtB3','Barony of the Steppes','Dallas, TX',12,'[{\"url\":\"https:\\/\\/ansteorra.org\\/steppes\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=steppes.ansteorra.org_p99m0mt1654cmg959rea97j754%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"http:\\/\\/facebook.com\\/groups\\/baronyofthesteppes\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/6ncnjMs7M3\",\"type\":\"discord\"}]',1,29,32,'2025-06-04 23:49:31','2024-08-14 19:10:59',1,2866,NULL,'Local Group','steppes.ansteorra.org'),
(28,'bU7BnGjD','Canton of Glaslyn','Denton, TX',27,'[{\"url\":\"https:\\/\\/ansteorra.org\\/glaslyn\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=glaslyn.ansteorra.org_fsnt3kfgr4urpe3mkdbd35t6q8@group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/203611906388\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/dGM6qdkgsr\",\"type\":\"discord\"}]',1,30,31,'2025-06-04 23:49:15','2024-08-14 19:12:42',1,2866,NULL,'Local Group','glaslyn.ansteorra.org'),
(29,'63wqJoCH','Barony of Elfsea','Ft. Worth, TX',12,'[{\"url\":\"https:\\/\\/ansteorra.org\\/elfsea\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=elfsea.ansteorra.org_ghoosc4f2mg7ovhfdi4mpe5qng%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/119112885484\\/\",\"type\":\"facebook\"}]',1,33,34,'2025-06-04 23:49:46','2024-08-14 19:13:36',1,2866,NULL,'Local Group','elfsea.ansteorra.org'),
(30,'zA23RKNc','Barony of Bonwicke','Lubbock, TX',12,'[{\"url\":\"https:\\/\\/ansteorra.org\\/bonwicke\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=imm9goc03ounmuv9p0qd685dco%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Bonwicke\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/qqhCYN25Sk\",\"type\":\"discord\"}]',1,35,36,'2025-06-04 23:50:04','2024-08-14 19:14:39',1,2866,NULL,'Local Group','bonwicke.ansteorra.org'),
(31,'AELnDdDj','Shire of Graywood','Lufkin, TX',12,'[{\"url\":\"https:\\/\\/ansteorra.org\\/graywood\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/1791805071079120\\/\",\"type\":\"facebook\"}]',1,37,38,'2025-06-04 23:48:56','2024-08-14 19:15:18',1,2866,NULL,'Local Group','graywood.ansteorra.org'),
(32,'7RGXgvec','Shire of Rosenfeld','Tyler, TX',12,'[{\"url\":\"https:\\/\\/ansteorra.org\\/rosenfeld\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/rosenfeld\\/current-meetings-practices\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/shireofrosenfeld\",\"type\":\"facebook\"}]',1,39,40,'2025-06-04 23:48:38','2024-08-14 19:15:59',1,2866,NULL,'Local Group','rosenfeld.ansteorra.org'),
(33,'2JFxSsTH','Barony of Bryn Gwlad','Austin, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/bryn-gwlad\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40bryn-gwlad.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/BrynGwlad\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/kCT4fRu\",\"type\":\"discord\"}]',1,43,44,'2025-06-04 23:47:29','2024-08-14 19:17:03',1,2866,NULL,'Local Group','bryn-gwlad.ansteorra.org'),
(34,'Ee4Z7LdG','Barony of Bordermarch','Beaumont, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/bordermarch\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/bordermarch\\/cal\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/905851402869576\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/GGTBHNr76r\",\"type\":\"discord\"}]',1,45,46,'2025-01-12 17:51:17','2024-08-14 19:17:49',1,1096,NULL,'Local Group',''),
(35,'djPfSLn5','Shire of the Shadowlands','Bryan/College Station, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/shadowlands\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=shadowlands.seneschal%40gmail.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/140819382632871\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/zXNU7An\",\"type\":\"discord\"}]',1,47,48,'2025-06-04 23:42:31','2024-08-14 19:18:43',1,2866,NULL,'Local Group','shadowlands.ansteorra.org'),
(36,'u9u24p7g','Barony of Loch Soilleir','Clear Lake, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/loch-soilleir\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40loch-soilleir.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/134428422244\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/yD9GHvf5B8\",\"type\":\"discord\"}]',1,49,50,'2025-06-04 23:46:54','2024-08-14 19:19:33',1,2866,NULL,'Local Group','loch-soilleir.ansteorra.org'),
(37,'UDtKgFGq','Shire of Seawinds','Corpus Christi, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/seawinds\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=seneschal%40seawinds.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/117945061876595\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/VP37mr8r7Z\",\"type\":\"discord\"}]',1,51,52,'2025-06-04 23:43:38','2024-08-14 19:20:34',1,2866,NULL,'Local Group','seawinds.ansteorra.org'),
(38,'79SDzxfy','Stronghold of Hellsgate','Ft. Cavazos, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/hellsgate\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/hellsgate\\/practices-meetings\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/516103795088723\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/sMGa7TZZwn\",\"type\":\"discord\"}]',1,53,54,'2025-06-04 23:42:12','2024-08-14 19:21:37',1,2866,NULL,'Local Group','hellsgate.ansteorra.org'),
(39,'dTbykTXQ','Barony of Stargate','Houston, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/stargate\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister@stargate.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.instagram.com\\/baronyofstargate?igsh=MXdoc3g0b2V0eGk4OQ==\",\"type\":\"instagram\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/56697227816\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/dH3y9rfZ4Q\",\"type\":\"discord\"}]',1,55,56,'2025-06-04 23:44:18','2024-08-14 19:22:47',1,2866,NULL,'Local Group','stargate.ansteorra.org'),
(40,'Le5tyCFs','Barony of Raven’s Fort','Huntsville, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/ravensfort\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40ravens-fort.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/294764366381\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/znfQcwN9\",\"type\":\"discord\"}]',1,57,58,'2025-06-04 23:46:29','2024-08-14 19:23:53',1,2866,NULL,'Local Group','ravens-fort.ansteorra.org'),
(41,'nzX3yVYv','Barony of Bjornsborg','San Antonio, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/bjornsborg\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/bjornsborg\\/local_activities\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Bjornsborg\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/GGTBHNr76r\",\"type\":\"discord\"}]',1,59,60,'2025-03-01 14:58:28','2024-08-14 19:24:54',1,1096,NULL,'Local Group','bjornsborg.ansteorra.org'),
(42,'SkBeQfNo','Shire of Ffynnon Gath','San Marcos, TX',13,'[{\"url\":\"https:\\/\\/ansteorra.org\\/ffynnon-gath\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/ffynnon-gath\\/info\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/131275780322581\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/twGW2xYcky\",\"type\":\"discord\"}]',1,61,62,'2025-06-04 23:44:00','2024-08-14 19:25:46',1,2866,NULL,'Local Group','ffynnon-gath.ansteorra.org');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(100) NOT NULL COMMENT 'Polymorphic entity type (e.g., Waivers.GatheringWaivers, Members)',
  `entity_id` int(11) NOT NULL COMMENT 'Polymorphic entity ID',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original filename from upload',
  `stored_filename` varchar(255) NOT NULL COMMENT 'Sanitized filename for storage',
  `file_path` varchar(500) NOT NULL COMMENT 'Full path to file in storage',
  `mime_type` varchar(100) NOT NULL COMMENT 'File MIME type',
  `file_size` int(11) NOT NULL COMMENT 'File size in bytes',
  `checksum` varchar(64) NOT NULL COMMENT 'SHA-256 checksum for integrity verification',
  `storage_adapter` varchar(50) NOT NULL DEFAULT 'local' COMMENT 'Storage adapter used (local, s3, etc.)',
  `metadata` text DEFAULT NULL COMMENT 'JSON metadata (conversion info, etc.)',
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Member ID who uploaded the file (nullable - NULL if member deleted)',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'Member ID who created the record (nullable - may be system process)',
  `modified_by` int(11) DEFAULT NULL COMMENT 'Member ID who last modified the record (nullable - may be system process)',
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_documents_file_path` (`file_path`),
  KEY `idx_documents_entity` (`entity_type`,`entity_id`),
  KEY `idx_documents_checksum` (`checksum`),
  KEY `idx_documents_uploaded_by` (`uploaded_by`),
  KEY `idx_documents_created` (`created`),
  KEY `fk_documents_created_by` (`created_by`),
  KEY `fk_documents_modified_by` (`modified_by`),
  CONSTRAINT `fk_documents_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documents_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES
(1,'GatheringWaiver',1,'ChatGPT Image Aug 10, 2025, 08_41_19 AM.pdf','doc_69050d2168b738.55638455.pdf','waivers/doc_69050d2168b738.55638455.pdf','application/pdf',93003,'990a33a8cf2a44daf3850df6f0d7a51578b525138b99ad6cad41602a31ce4c5b','local','{\"source\":\"waiver_upload\",\"original_filename\":\"ChatGPT Image Aug 10, 2025, 08_41_19 AM.pdf\",\"original_size\":1704085,\"converted_size\":93003,\"conversion_date\":\"2025-10-31 19:25:21\",\"compression_ratio\":94.54,\"page_count\":1,\"is_multipage\":true}',1,'2025-10-31 19:25:21','2025-10-31 19:25:21',1,1,NULL),
(2,'GatheringWaiver',2,'Image (1).pdf','doc_690beaf8e6ad61.98950690.pdf','waivers/doc_690beaf8e6ad61.98950690.pdf','application/pdf',143944,'abd666fb2c1116337e0a49df43970072e63d7e6e02f6ab5876ed1e88ac2e2237','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Image (1).pdf\",\"original_size\":3052135,\"converted_size\":143944,\"conversion_date\":\"2025-11-06 00:25:28\",\"compression_ratio\":95.28,\"page_count\":2,\"is_multipage\":true}',1,'2025-11-06 00:25:28','2025-11-06 00:25:28',1,1,NULL),
(3,'GatheringWaiver',3,'272954271959.pdf','doc_690beb42708190.79206018.pdf','waivers/doc_690beb42708190.79206018.pdf','application/pdf',87457,'fd343525fbd19784bd8af1617044d8ed43d3ce917c2618229f2a2c9966d890aa','local','{\"source\":\"waiver_upload\",\"original_filename\":\"272954271959.pdf\",\"original_size\":1494999,\"converted_size\":87457,\"conversion_date\":\"2025-11-06 00:26:42\",\"compression_ratio\":94.15,\"page_count\":2,\"is_multipage\":true}',1,'2025-11-06 00:26:42','2025-11-06 00:26:42',1,1,NULL),
(4,'GatheringWaiver',4,'unnamed.pdf','doc_690bfbfb784f07.29988172.pdf','waivers/doc_690bfbfb784f07.29988172.pdf','application/pdf',25382,'bdaf0ae30c3bd0944559a9abf49ea8fc49b2735ee5e1778d18dcaeff232761b3','local','{\"source\":\"waiver_upload\",\"original_filename\":\"unnamed.pdf\",\"original_size\":5987,\"converted_size\":25382,\"conversion_date\":\"2025-11-06 01:38:03\",\"compression_ratio\":-323.95,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-06 01:38:03','2025-11-06 01:38:03',1,1,NULL),
(5,'GatheringWaiver',5,'unnamed (1).pdf','doc_690c019d8bf795.74244621.pdf','waivers/doc_690c019d8bf795.74244621.pdf','application/pdf',29174,'82433e54ecaf266d199cfbae33fe688a957ace7142ef2a48df172647f891fab1','local','{\"source\":\"waiver_upload\",\"original_filename\":\"unnamed (1).pdf\",\"original_size\":6372,\"converted_size\":29174,\"conversion_date\":\"2025-11-06 02:02:05\",\"compression_ratio\":-357.85,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-06 02:02:05','2025-11-06 02:02:05',1,1,NULL),
(6,'GatheringWaiver',6,'RomaniusDeviceTransparent.pdf','doc_690c01f4b354a1.83267744.pdf','waivers/doc_690c01f4b354a1.83267744.pdf','application/pdf',26488,'14ce73dc7162fde8463fc179f443cf466c9f95f39e75dfc4186ce298862f7e02','local','{\"source\":\"waiver_upload\",\"original_filename\":\"RomaniusDeviceTransparent.pdf\",\"original_size\":35820,\"converted_size\":26488,\"conversion_date\":\"2025-11-06 02:03:32\",\"compression_ratio\":26.05,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-06 02:03:32','2025-11-06 02:03:32',1,1,NULL),
(7,'GatheringWaiver',7,'vladarms.pdf','doc_690c0340d03cd9.57771384.pdf','waivers/doc_690c0340d03cd9.57771384.pdf','application/pdf',34004,'028b500bc770de2b021a972dd7f8548bb242b0a8bc3cd905db7a0938537d8a8f','local','{\"source\":\"waiver_upload\",\"original_filename\":\"vladarms.pdf\",\"original_size\":34863,\"converted_size\":34004,\"conversion_date\":\"2025-11-06 02:09:04\",\"compression_ratio\":2.46,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-06 02:09:04','2025-11-06 02:09:04',1,1,NULL),
(8,'GatheringWaiver',8,'2025 Gulf Wars Muster QR Code.pdf','doc_690c059937a374.44732403.pdf','waivers/doc_690c059937a374.44732403.pdf','application/pdf',51708,'50aa87354bcb5e38c14958aef3981d6cbe0d96bbce9611ddd68808c5f74aab67','local','{\"source\":\"waiver_upload\",\"original_filename\":\"2025 Gulf Wars Muster QR Code.pdf\",\"original_size\":135067,\"converted_size\":51708,\"conversion_date\":\"2025-11-06 02:19:05\",\"compression_ratio\":61.72,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-06 02:19:05','2025-11-06 02:19:05',1,1,NULL),
(9,'GatheringWaiver',9,'Dove_anglosaxon from gemini.pdf','doc_69168b6adf8120.07985558.pdf','waivers/doc_69168b6adf8120.07985558.pdf','application/pdf',55411,'8264d00e5e45641becf905a4a90da24fe0fcbf9d5da148c5067f4bd5d37f9146','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Dove_anglosaxon from gemini.pdf\",\"original_size\":1214098,\"converted_size\":55411,\"conversion_date\":\"2025-11-14 01:52:42\",\"compression_ratio\":95.44,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-14 01:52:42','2025-11-14 01:52:42',1,1,NULL),
(10,'GatheringWaiver',10,'vindheim-star-square-flat - Copy.pdf','doc_69168bcc3360c5.21420564.pdf','waivers/doc_69168bcc3360c5.21420564.pdf','application/pdf',15458,'752ae52aefc05647f0f7e1a1e6fe9894afab17c2a88a9b6dcef2cb75442813fb','local','{\"source\":\"waiver_upload\",\"original_filename\":\"vindheim-star-square-flat - Copy.pdf\",\"original_size\":40403,\"converted_size\":15458,\"conversion_date\":\"2025-11-14 01:54:20\",\"compression_ratio\":61.74,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-14 01:54:20','2025-11-14 01:54:20',1,1,NULL),
(11,'GatheringWaiver',11,'unnamed (1).pdf','doc_69168bf6482f03.77365347.pdf','waivers/doc_69168bf6482f03.77365347.pdf','application/pdf',29174,'82433e54ecaf266d199cfbae33fe688a957ace7142ef2a48df172647f891fab1','local','{\"source\":\"waiver_upload\",\"original_filename\":\"unnamed (1).pdf\",\"original_size\":6372,\"converted_size\":29174,\"conversion_date\":\"2025-11-14 01:55:02\",\"compression_ratio\":-357.85,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-14 01:55:02','2025-11-14 01:55:02',1,1,NULL),
(12,'GatheringWaiver',13,'Mazikeen and Tucker in Luna.pdf','doc_691bb8f70873c0.11180832.pdf','waivers/doc_691bb8f70873c0.11180832.pdf','application/pdf',61596,'602bc19f859dad4990bc2bbf162373710fbb45d17cfd9af3011cefd647f3f560','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Mazikeen and Tucker in Luna.pdf\",\"original_size\":275385,\"converted_size\":61596,\"conversion_date\":\"2025-11-18 00:08:23\",\"compression_ratio\":77.63,\"page_count\":1,\"is_multipage\":true}',2872,'2025-11-18 00:08:23','2025-11-18 00:08:23',1,2872,NULL),
(13,'GatheringWaiver',14,'Mazikeen 2.pdf','doc_691bbab5f1a9d1.15463124.pdf','waivers/doc_691bbab5f1a9d1.15463124.pdf','application/pdf',30388,'9497f19bad110926cceb4b10238072730a464b5de7ec89d818950efb2dcc4f80','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Mazikeen 2.pdf\",\"original_size\":60575,\"converted_size\":30388,\"conversion_date\":\"2025-11-18 00:15:49\",\"compression_ratio\":49.83,\"page_count\":1,\"is_multipage\":true}',2872,'2025-11-18 00:15:50','2025-11-18 00:15:50',1,2872,NULL),
(14,'GatheringWaiver',15,'Mermaid-preview.pdf','doc_691bd0a01c55f0.43523749.pdf','waivers/doc_691bd0a01c55f0.43523749.pdf','application/pdf',40261,'e2369737da37847d5fb5fb9834ac301c1962f246680d523b7597c3b97b06b6d9','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Mermaid-preview.pdf\",\"original_size\":375609,\"converted_size\":40261,\"conversion_date\":\"2025-11-18 01:49:20\",\"compression_ratio\":89.28,\"page_count\":2,\"is_multipage\":true}',1,'2025-11-18 01:49:20','2025-11-18 01:49:20',1,1,NULL),
(15,'GatheringWaiver',16,'Mermaid-preview.pdf','doc_691bd0ceb18a80.54897058.pdf','waivers/doc_691bd0ceb18a80.54897058.pdf','application/pdf',3977,'8455bcb511e41a92facc807917ac0a594d65b313e5a6d1f47c12b1715bbd0bc0','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Mermaid-preview.pdf\",\"original_size\":228660,\"converted_size\":3977,\"conversion_date\":\"2025-11-18 01:50:06\",\"compression_ratio\":98.26,\"page_count\":1,\"is_multipage\":true}',1,'2025-11-18 01:50:06','2025-11-18 01:50:06',1,1,NULL),
(16,'GatheringWaiver',19,'Mazikeen.pdf','doc_691bde13252ae3.87526335.pdf','waivers/doc_691bde13252ae3.87526335.pdf','application/pdf',151433,'f91e821f009e30eb347de8623217227ab3ecc08a395ccf8f342b288c0f8a16f6','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Mazikeen.pdf\",\"original_size\":233740,\"converted_size\":151433,\"conversion_date\":\"2025-11-18 02:46:43\",\"compression_ratio\":35.21,\"page_count\":2,\"is_multipage\":true}',2872,'2025-11-18 02:46:43','2025-11-18 02:46:43',1,2872,NULL),
(17,'GatheringWaiver',21,'traced-jpeg.pdf','doc_6930de32cf9ba9.71212775.pdf','waivers/doc_6930de32cf9ba9.71212775.pdf','application/pdf',35990,'edd03ec8e45ff658417fc5f3b6a7b6084920f2fc9a4592cb0e97b5d4594fe05f','local','{\"source\":\"waiver_upload\",\"original_filename\":\"traced-jpeg.pdf\",\"original_size\":1285250,\"converted_size\":35990,\"conversion_date\":\"2025-12-04 01:04:50\",\"compression_ratio\":97.2,\"page_count\":1,\"is_multipage\":true}',2880,'2025-12-04 01:04:50','2025-12-04 01:04:50',1,2880,NULL),
(18,'GatheringWaiver',22,'Zubeydah.pdf','doc_693485782f5717.19755391.pdf','waivers/doc_693485782f5717.19755391.pdf','application/pdf',56757,'155a4cc81d69c83927086a66c68b08c8d60ba25fb859736904201363f9e8b2df','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Zubeydah.pdf\",\"original_size\":1198437,\"converted_size\":56757,\"conversion_date\":\"2025-12-06 19:35:20\",\"compression_ratio\":95.26,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 19:35:20','2025-12-06 19:35:20',1,2884,NULL),
(19,'GatheringWaiver',23,'17650497566782029241283125471179.pdf','doc_693485d1b91e47.17491587.pdf','waivers/doc_693485d1b91e47.17491587.pdf','application/pdf',24017,'c16414ab74c0ef1b858f418dad5e613adf2ec6bfecd7638d14f9c988d820bc30','local','{\"source\":\"waiver_upload\",\"original_filename\":\"17650497566782029241283125471179.pdf\",\"original_size\":3431251,\"converted_size\":24017,\"conversion_date\":\"2025-12-06 19:36:49\",\"compression_ratio\":99.3,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 19:36:49','2025-12-06 19:36:49',1,2884,NULL),
(20,'GatheringWaiver',24,'17650498566491948027242074337813.pdf','doc_69348611b4a685.68215915.pdf','waivers/doc_69348611b4a685.68215915.pdf','application/pdf',22563,'f6f8c123f2fd05665e7c3b389d6e3ff1d1fec8bff734c99ee2e0675d7cc35269','local','{\"source\":\"waiver_upload\",\"original_filename\":\"17650498566491948027242074337813.pdf\",\"original_size\":2994899,\"converted_size\":22563,\"conversion_date\":\"2025-12-06 19:37:53\",\"compression_ratio\":99.25,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 19:37:53','2025-12-06 19:37:53',1,2884,NULL),
(21,'GatheringWaiver',26,'Theresa Halliburton_1.pdf','doc_69349761829ff7.95287656.pdf','waivers/doc_69349761829ff7.95287656.pdf','application/pdf',92896,'4855be6138806bad8da101789ed4610463dea90e1b2d3c679977c82c14e26789','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Theresa Halliburton_1.pdf\",\"original_size\":467880,\"converted_size\":92896,\"conversion_date\":\"2025-12-06 20:51:45\",\"compression_ratio\":80.15,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 20:51:45','2025-12-06 20:51:45',1,2884,NULL),
(22,'GatheringWaiver',28,'Melanie Gallon.pdf','doc_6934988e6d7df9.22895612.pdf','waivers/doc_6934988e6d7df9.22895612.pdf','application/pdf',91099,'ce11d2986bb54474e27b926f68249cb2ea6ef4cdf622fcc8741f0d26f1306dd0','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Melanie Gallon.pdf\",\"original_size\":689725,\"converted_size\":91099,\"conversion_date\":\"2025-12-06 20:56:46\",\"compression_ratio\":86.79,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 20:56:46','2025-12-06 20:56:46',1,2884,NULL),
(23,'GatheringWaiver',30,'1765055843336211450597425383020.pdf','doc_69349d7bb4d826.73354028.pdf','waivers/doc_69349d7bb4d826.73354028.pdf','application/pdf',24334,'f212785e0b9c3be0d1d5103f1e2bf29fb8e9728791d4fef4d3512225598d449a','local','{\"source\":\"waiver_upload\",\"original_filename\":\"1765055843336211450597425383020.pdf\",\"original_size\":2996470,\"converted_size\":24334,\"conversion_date\":\"2025-12-06 21:17:47\",\"compression_ratio\":99.19,\"page_count\":1,\"is_multipage\":true}',2884,'2025-12-06 21:17:47','2025-12-06 21:17:47',1,2884,NULL),
(24,'GatheringWaiver',31,'Theresa Halliburton_1.pdf','doc_6934a4b661c3c8.32750320.pdf','waivers/doc_6934a4b661c3c8.32750320.pdf','application/pdf',92896,'4855be6138806bad8da101789ed4610463dea90e1b2d3c679977c82c14e26789','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Theresa Halliburton_1.pdf\",\"original_size\":467880,\"converted_size\":92896,\"conversion_date\":\"2025-12-06 21:48:38\",\"compression_ratio\":80.15,\"page_count\":1,\"is_multipage\":true}',1,'2025-12-06 21:48:38','2025-12-06 21:48:38',1,1,NULL),
(25,'GatheringWaiver',33,'Screenshot 2025-12-08 at 7.35.49 AM.pdf','doc_694036f62ec0a5.45202474.pdf','waivers/doc_694036f62ec0a5.45202474.pdf','application/pdf',63459,'792d09bf37d93abb494b4fb075dfd6219c83fd02af72873ddc96344cccc7b6ec','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Screenshot 2025-12-08 at 7.35.49\\u202fAM.pdf\",\"original_size\":1280921,\"converted_size\":63459,\"conversion_date\":\"2025-12-15 16:27:34\",\"compression_ratio\":95.05,\"page_count\":3,\"is_multipage\":true}',1,'2025-12-15 16:27:34','2025-12-15 16:27:34',1,1,NULL),
(26,'GatheringWaiver',34,'Gemini_Generated_Image_774df1774df1774d.pdf','doc_6940b777406175.30739254.pdf','waivers/doc_6940b777406175.30739254.pdf','application/pdf',55411,'8264d00e5e45641becf905a4a90da24fe0fcbf9d5da148c5067f4bd5d37f9146','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Gemini_Generated_Image_774df1774df1774d.pdf\",\"original_size\":1214098,\"converted_size\":55411,\"conversion_date\":\"2025-12-16 01:35:51\",\"compression_ratio\":95.44,\"page_count\":1,\"is_multipage\":true}',1,'2025-12-16 01:35:51','2025-12-16 01:35:51',1,1,NULL),
(27,'GatheringWaiver',35,'362131036_762820742196418_221530743103875691_n.pdf','doc_6940b79237dd62.68629822.pdf','waivers/doc_6940b79237dd62.68629822.pdf','application/pdf',85840,'c4f4e9758215bc681a946850432e86c0be5c57df67725007f21681636770611b','local','{\"source\":\"waiver_upload\",\"original_filename\":\"362131036_762820742196418_221530743103875691_n.pdf\",\"original_size\":466082,\"converted_size\":85840,\"conversion_date\":\"2025-12-16 01:36:18\",\"compression_ratio\":81.58,\"page_count\":1,\"is_multipage\":true}',1,'2025-12-16 01:36:18','2025-12-16 01:36:18',1,1,NULL),
(28,'GatheringWaiver',37,'jp-llama.pdf','doc_69434c0d5fb4c5.11984530.pdf','waivers/doc_69434c0d5fb4c5.11984530.pdf','application/pdf',101331,'1c593dd2175e3039788e35f7c5493f4432f09860f2eb8cd4a9f0395b9efc71e3','local','{\"source\":\"waiver_upload\",\"original_filename\":\"jp-llama.pdf\",\"original_size\":305596,\"converted_size\":101331,\"conversion_date\":\"2025-12-18 00:34:21\",\"compression_ratio\":66.84,\"page_count\":2,\"is_multipage\":true}',1,'2025-12-18 00:34:21','2025-12-18 00:34:21',1,1,NULL),
(29,'GatheringWaiver',39,'Camp Binders (2).pdf','doc_6952d350b3f5d9.23980778.pdf','waivers/doc_6952d350b3f5d9.23980778.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:15:28\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:15:28','2025-12-29 19:15:28',1,2872,NULL),
(30,'GatheringWaiver',40,'Camp Binders (2).pdf','doc_6952d3762f4b09.29835214.pdf','waivers/doc_6952d3762f4b09.29835214.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:16:06\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:16:06','2025-12-29 19:16:06',1,2872,NULL),
(31,'GatheringWaiver',41,'Camp Binders (2).pdf','doc_6952d40e2771f5.32985895.pdf','waivers/doc_6952d40e2771f5.32985895.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:18:38\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:18:38','2025-12-29 19:18:38',1,2872,NULL),
(32,'GatheringWaiver',42,'Camp Binders (2).pdf','doc_6952d4279a98b3.70836744.pdf','waivers/doc_6952d4279a98b3.70836744.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:19:03\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:19:03','2025-12-29 19:19:03',1,2872,NULL),
(33,'GatheringWaiver',44,'Camp Binders (2).pdf','doc_6952d483e24541.79428037.pdf','waivers/doc_6952d483e24541.79428037.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:20:35\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:20:35','2025-12-29 19:20:35',1,2872,NULL),
(34,'GatheringWaiver',45,'Camp Binders (2).pdf','doc_6952d4a1a5f4a1.81766541.pdf','waivers/doc_6952d4a1a5f4a1.81766541.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:21:05\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:21:05','2025-12-29 19:21:05',1,2872,NULL),
(35,'GatheringWaiver',47,'Camp Binders (2).pdf','doc_6952d4fe8b5cd4.19876278.pdf','waivers/doc_6952d4fe8b5cd4.19876278.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:22:38\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:22:38','2025-12-29 19:22:38',1,2872,NULL),
(36,'GatheringWaiver',48,'Camp Binders (2).pdf','doc_6952d50d72c996.03041191.pdf','waivers/doc_6952d50d72c996.03041191.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:22:53\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:22:53','2025-12-29 19:22:53',1,2872,NULL),
(37,'GatheringWaiver',49,'Camp Binders (2).pdf','doc_6952d519a35d56.61128674.pdf','waivers/doc_6952d519a35d56.61128674.pdf','application/pdf',58820,'1ab3c9036678130249551c22cbecfcf38d4b7a8706b3fb355bb67870d7b1e444','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Camp Binders (2).pdf\",\"original_size\":360449,\"converted_size\":58820,\"conversion_date\":\"2025-12-29 19:23:05\",\"compression_ratio\":83.68,\"page_count\":1,\"is_multipage\":true}',2872,'2025-12-29 19:23:05','2025-12-29 19:23:05',1,2872,NULL),
(38,'GatheringWaiver',52,'Zubeydah.pdf','doc_6961b97fee74f3.16507380.pdf','waivers/doc_6961b97fee74f3.16507380.pdf','application/pdf',98478,'aeb31f66e2b6f52379a0c3706f573f6852a3c7c6c83b181426edfe1c0a7e450f','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Zubeydah.pdf\",\"original_size\":1198437,\"converted_size\":98478,\"conversion_date\":\"2026-01-10 02:29:19\",\"compression_ratio\":91.78,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:29:20','2026-01-10 02:29:20',1,1,NULL),
(39,'GatheringWaiver',53,'Kasjan_2.pdf','doc_6961bc4a4a10a9.55865103.pdf','waivers/doc_6961bc4a4a10a9.55865103.pdf','application/pdf',42228,'28fa011e2d0f0a499e73c44eacc426d366b51d65407adc26d419601ed9c23bad','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Kasjan_2.pdf\",\"original_size\":537353,\"converted_size\":42228,\"conversion_date\":\"2026-01-10 02:41:14\",\"compression_ratio\":92.14,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:41:14','2026-01-10 02:41:14',1,1,NULL),
(40,'GatheringWaiver',55,'Kasjan_1.pdf','doc_6961bcc9be1b77.11971452.pdf','waivers/doc_6961bcc9be1b77.11971452.pdf','application/pdf',46293,'f7525bae571230a0eeca95bc4370e02fed947904c7c949312d54be3dc2bf617c','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Kasjan_1.pdf\",\"original_size\":617442,\"converted_size\":46293,\"conversion_date\":\"2026-01-10 02:43:21\",\"compression_ratio\":92.5,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:43:21','2026-01-10 02:43:21',1,1,NULL),
(41,'GatheringWaiver',56,'Theresa Halliburton_1.pdf','doc_6961bce4503522.32905673.pdf','waivers/doc_6961bce4503522.32905673.pdf','application/pdf',92896,'4855be6138806bad8da101789ed4610463dea90e1b2d3c679977c82c14e26789','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Theresa Halliburton_1.pdf\",\"original_size\":467880,\"converted_size\":92896,\"conversion_date\":\"2026-01-10 02:43:48\",\"compression_ratio\":80.15,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:43:48','2026-01-10 02:43:48',1,1,NULL),
(42,'GatheringWaiver',57,'Zubeydah.pdf','doc_6961bd457b9941.00435827.pdf','waivers/doc_6961bd457b9941.00435827.pdf','application/pdf',98478,'aeb31f66e2b6f52379a0c3706f573f6852a3c7c6c83b181426edfe1c0a7e450f','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Zubeydah.pdf\",\"original_size\":1198437,\"converted_size\":98478,\"conversion_date\":\"2026-01-10 02:45:25\",\"compression_ratio\":91.78,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:45:25','2026-01-10 02:45:25',1,1,NULL),
(43,'GatheringWaiver',60,'jp-llama.pdf','doc_6961bf3e899831.66968164.pdf','waivers/doc_6961bf3e899831.66968164.pdf','application/pdf',47054,'13409b3387d56ce97fd2106d295a9e6404c09e95b8de1ed6a6d5a8e9b19eb4ba','local','{\"source\":\"waiver_upload\",\"original_filename\":\"jp-llama.pdf\",\"original_size\":104913,\"converted_size\":47054,\"conversion_date\":\"2026-01-10 02:53:50\",\"compression_ratio\":55.15,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-10 02:53:50','2026-01-10 02:53:50',1,1,NULL),
(44,'GatheringWaiver',62,'hawkswingcloak.pdf','doc_6961c7f4106a30.12678895.pdf','waivers/doc_6961c7f4106a30.12678895.pdf','application/pdf',54510,'cecf7a6f0b000b4a0a40824e121a337a87977f1ca75f337c82d66b56c0bd3be5','local','{\"source\":\"waiver_upload\",\"original_filename\":\"hawkswingcloak.pdf\",\"original_size\":200683,\"converted_size\":54510,\"conversion_date\":\"2026-01-10 03:31:00\",\"compression_ratio\":72.84,\"page_count\":1,\"is_multipage\":true}',2889,'2026-01-10 03:31:00','2026-01-10 03:31:00',1,2889,NULL),
(45,'GatheringWaiver',63,'JP-sword-holder.pdf','doc_6961c80cabacd7.45522319.pdf','waivers/doc_6961c80cabacd7.45522319.pdf','application/pdf',46110,'2198141b2ad35ea7c2556c174cc34f10f7afe5e3dcdeba7e5666a3c74e98e277','local','{\"source\":\"waiver_upload\",\"original_filename\":\"JP-sword-holder.pdf\",\"original_size\":492706,\"converted_size\":46110,\"conversion_date\":\"2026-01-10 03:31:24\",\"compression_ratio\":90.64,\"page_count\":1,\"is_multipage\":true}',2889,'2026-01-10 03:31:24','2026-01-10 03:31:24',1,2889,NULL),
(46,'GatheringWaiver',65,'ChatGPT Image Nov 3, 2025, 07_17_06 PM.pdf','doc_6961c8333aa669.56880998.pdf','waivers/doc_6961c8333aa669.56880998.pdf','application/pdf',29848,'fa83909c8104f103785ce99bd42b6184fb7344f169f9909499904a903f5655cd','local','{\"source\":\"waiver_upload\",\"original_filename\":\"ChatGPT Image Nov 3, 2025, 07_17_06 PM.pdf\",\"original_size\":1152616,\"converted_size\":29848,\"conversion_date\":\"2026-01-10 03:32:03\",\"compression_ratio\":97.41,\"page_count\":1,\"is_multipage\":true}',2889,'2026-01-10 03:32:03','2026-01-10 03:32:03',1,2889,NULL),
(47,'GatheringWaiver',66,'Sable-Soldier-Image.pdf','doc_6961c84567c8b2.28074368.pdf','waivers/doc_6961c84567c8b2.28074368.pdf','application/pdf',50803,'a110ff79c018042499a9a79723b4550b5ee3f94f01802dc7f87b2764195d713a','local','{\"source\":\"waiver_upload\",\"original_filename\":\"Sable-Soldier-Image.pdf\",\"original_size\":111170,\"converted_size\":50803,\"conversion_date\":\"2026-01-10 03:32:21\",\"compression_ratio\":54.3,\"page_count\":1,\"is_multipage\":true}',2889,'2026-01-10 03:32:21','2026-01-10 03:32:21',1,2889,NULL),
(48,'GatheringWaiver',67,'470222927_10162089746776665_8854101087714528760_n.pdf','doc_6965b640d59178.87953993.pdf','waivers/doc_6965b640d59178.87953993.pdf','application/pdf',55493,'0576b4c69ab75264c6af754c2f52d4ffe63b0a571cad5b86df6a4336f3305190','local','{\"source\":\"waiver_upload\",\"original_filename\":\"470222927_10162089746776665_8854101087714528760_n.pdf\",\"original_size\":452063,\"converted_size\":55493,\"conversion_date\":\"2026-01-13 03:04:32\",\"compression_ratio\":87.72,\"page_count\":1,\"is_multipage\":true}',2889,'2026-01-13 03:04:32','2026-01-13 03:04:32',1,2889,NULL),
(49,'GatheringWaiver',68,'362131036_762820742196418_221530743103875691_n.pdf','doc_6965bd34196212.04571169.pdf','waivers/doc_6965bd34196212.04571169.pdf','application/pdf',85840,'c4f4e9758215bc681a946850432e86c0be5c57df67725007f21681636770611b','local','{\"source\":\"waiver_upload\",\"original_filename\":\"362131036_762820742196418_221530743103875691_n.pdf\",\"original_size\":466082,\"converted_size\":85840,\"conversion_date\":\"2026-01-13 03:34:12\",\"compression_ratio\":81.58,\"page_count\":1,\"is_multipage\":true}',1,'2026-01-13 03:34:12','2026-01-13 03:34:12',1,1,NULL),
(50,'GatheringWaiver',69,'362131036_762820742196418_221530743103875691_n.pdf','doc_6965bdb295d976.62680984.pdf','waivers/doc_6965bdb295d976.62680984.pdf','application/pdf',124272,'7185270c4eb10616e10b504f7b47b8549d7108c03eafd78cdaacd2b3372acb35','local','{\"source\":\"waiver_upload\",\"original_filename\":\"362131036_762820742196418_221530743103875691_n.pdf\",\"original_size\":1556488,\"converted_size\":124272,\"conversion_date\":\"2026-01-13 03:36:18\",\"compression_ratio\":92.02,\"page_count\":2,\"is_multipage\":true}',1,'2026-01-13 03:36:18','2026-01-13 03:36:18',1,1,NULL),
(51,'GatheringWaiver',71,'jp-llama.pdf','doc_6965cca7f0df28.42709566.pdf','waivers/doc_6965cca7f0df28.42709566.pdf','application/pdf',47054,'13409b3387d56ce97fd2106d295a9e6404c09e95b8de1ed6a6d5a8e9b19eb4ba','local','{\"source\":\"waiver_upload\",\"original_filename\":\"jp-llama.pdf\",\"original_size\":104913,\"converted_size\":47054,\"conversion_date\":\"2026-01-13 04:40:07\",\"compression_ratio\":55.15,\"page_count\":1,\"is_multipage\":true}',2872,'2026-01-13 04:40:08','2026-01-13 04:40:08',1,2872,NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mailer_class` varchar(255) NOT NULL COMMENT 'Fully qualified class name of the Mailer (e.g., App\\Mailer\\KMPMailer)',
  `action_method` varchar(255) NOT NULL COMMENT 'Method name in the Mailer class (e.g., resetPassword)',
  `subject_template` varchar(500) NOT NULL COMMENT 'Email subject line template with variable placeholders',
  `html_template` text DEFAULT NULL COMMENT 'HTML version of email template',
  `text_template` text DEFAULT NULL COMMENT 'Plain text version of email template',
  `available_vars` text DEFAULT NULL COMMENT 'JSON array of available variables for this template',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this template is active and should be used',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'Member ID who created this template',
  `modified_by` int(11) DEFAULT NULL COMMENT 'Member ID who last modified this template',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_mailer_action_unique` (`mailer_class`,`action_method`),
  KEY `is_active` (`is_active`),
  KEY `fk_email_templates_created_by` (`created_by`),
  KEY `fk_email_templates_modified_by` (`modified_by`),
  CONSTRAINT `fk_email_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_email_templates_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templates`
--

LOCK TABLES `email_templates` WRITE;
/*!40000 ALTER TABLE `email_templates` DISABLE KEYS */;
INSERT INTO `email_templates` VALUES
(1,'App\\Mailer\\KMPMailer','resetPassword','Reset password','Someone has requested a password reset for the Ansteorra Marshallet account with the email address of {{email}}.\nPlease use the link below to reset your account password.\n\n{{passwordResetUrl}}\n\nThis link will be good for 1 day. If you do not set your password within that time frame you will need to request a new\npassword reset email from the \"forgot password\" link on the login page.\n\nIf you did not make this request, you are free to disregard this message.\n\n\nThank you\n{{siteAdminSignature}}.','Someone has requested a password reset for the Ansteorra Marshallet account with the email address of {{email}}.\nPlease use the link below to reset your account password.\n\n{{passwordResetUrl}}\n\nThis link will be good for 1 day. If you do not set your password within that time frame you will need to request a new\npassword reset email from the \"forgot password\" link on the login page.\n\nIf you did not make this request, you are free to disregard this message.\n\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"email\",\"description\":\"Email\"},{\"name\":\"passwordResetUrl\",\"description\":\"Password reset url\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(2,'App\\Mailer\\KMPMailer','mobileCard','Your Mobile Card URL','Below is a link to your mobile card. This link will take you to a page where you can view your mobile card. You can also\ninstall this card on your phone\'s home screen for easy access both online and offline.\n\n{{mobileCardUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','Below is a link to your mobile card. This link will take you to a page where you can view your mobile card. You can also\ninstall this card on your phone\'s home screen for easy access both online and offline.\n\n{{mobileCardUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"email\",\"description\":\"Email\"},{\"name\":\"mobileCardUrl\",\"description\":\"Mobile card url\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(3,'App\\Mailer\\KMPMailer','newRegistration','Welcome {{memberScaName}} to {{portalName}}','Welcome, {{memberScaName}}!\r\n\r\nTo verify your email address please use the link below to set your password.\r\n\r\n{{passwordResetUrl}}\r\n\r\nThis link will be good for 1 day. If you do not set your password within that time frame you will need to request a new\r\npassword reset email from the \"forgot password\" link on the login page.\r\n\r\n\r\n\r\nThank you\r\n{{siteAdminSignature}}.','Welcome, {{memberScaName}}!\r\n\r\nTo verify your email address please use the link below to set your password.\r\n\r\n{{passwordResetUrl}}\r\n\r\nThis link will be good for 1 day. If you do not set your password within that time frame you will need to request a new\r\npassword reset email from the \"forgot password\" link on the login page.\r\n\r\n\r\n\r\nThank you\r\n{{siteAdminSignature}}.','[{\"name\":\"email\",\"description\":\"Email\"},{\"name\":\"passwordResetUrl\",\"description\":\"Password reset url\"},{\"name\":\"portalName\",\"description\":\"Portal name\"},{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:25',NULL,NULL),
(4,'App\\Mailer\\KMPMailer','notifySecretaryOfNewMember','New Member Registration','Good day,\n\n{{memberScaName}} has recently registered. They have been emailed to set their password and their membership card\nwas <?= $memberCardPresent ? \"uploaded\" : \"not uploaded\" ?>.\n\nYou can view their information at the link below:\n{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','Good day,\n\n{{memberScaName}} has recently registered. They have been emailed to set their password and their membership card\nwas <?= $memberCardPresent ? \"uploaded\" : \"not uploaded\" ?>.\n\nYou can view their information at the link below:\n{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberViewUrl\",\"description\":\"Member view url\"},{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"memberCardPresent\",\"description\":\"Member card present\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(5,'App\\Mailer\\KMPMailer','notifySecretaryOfNewMinorMember','New Minor Member Registration','Good day,\n\nA new minor named {{memberScaName}} has recently registered. Their account is currently inaccessable and they have\nbeen notified you will follow up. Their membership card\nwas <?= $memberCardPresent ? \"uploaded\" : \"not uploaded\" ?> at the time of registration.\n\nYou can view their information at the link below:\n{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','Good day,\n\nA new minor named {{memberScaName}} has recently registered. Their account is currently inaccessable and they have\nbeen notified you will follow up. Their membership card\nwas <?= $memberCardPresent ? \"uploaded\" : \"not uploaded\" ?> at the time of registration.\n\nYou can view their information at the link below:\n{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberViewUrl\",\"description\":\"Member view url\"},{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"memberCardPresent\",\"description\":\"Member card present\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(6,'App\\Mailer\\KMPMailer','notifyOfWarrant','Warrant Issued: {{warrantName}}','Good day {{memberScaName}}\n\nThe \"{{warrantName}}\" warrant has been issued and is valid from {{warrantStart}} to\n{{warrantExpires}}.\n\nIf this warrant is for an office that extends passed this warrant date, new warrants will be issued as needed.\n\nThis new warrant supersedes any previous warrants issued for for the subjects this warrant covers.\n\nThank you\n{{siteAdminSignature}}.','Good day {{memberScaName}}\n\nThe \"{{warrantName}}\" warrant has been issued and is valid from {{warrantStart}} to\n{{warrantExpires}}.\n\nIf this warrant is for an office that extends passed this warrant date, new warrants will be issued as needed.\n\nThis new warrant supersedes any previous warrants issued for for the subjects this warrant covers.\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"warrantName\",\"description\":\"Warrant name\"},{\"name\":\"warrantExpires\",\"description\":\"Warrant expires\"},{\"name\":\"warrantStart\",\"description\":\"Warrant start\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(7,'Officers\\Mailer\\OfficersMailer','notifyOfHire','Appointment Notification: {{officeName}}','Good day {{memberScaName}}\n\nFirst we would like to thank you for your offer of service in the office of {{officeName}} for {{branchName}}.\nWe are pleased to inform you that your offer has been accepted and you have been appointed and can start in the role on\n{{hireDate}}.\n\n<?= $requiresWarrant ? \"Please note that this office requires a warrant. A reqest for that warrent has been forwarded to the Crown for approval.\" : \"\" ?>\n\n<table>\n    <tr>\n        <td>Office:</td>\n        <td>{{officeName}}</td>\n    </tr>\n    <tr>\n        <td>Branch:</td>\n        <td>{{branchName}}</td>\n    </tr>\n    <tr>\n        <td>Start Date:</td>\n        <td>{{hireDate}}</td>\n    </tr>\n    <tr>\n        <td>End Date:</td>\n        <td>{{endDate}}</td>\n    </tr>\n</table>\n\nThank you\n{{siteAdminSignature}}.','Good day {{memberScaName}}\n\nFirst we would like to thank you for your offer of service in the office of {{officeName}} for {{branchName}}.\nWe are pleased to inform you that your offer has been accepted and you have been appointed and can start in the role on\n{{hireDate}}.\n\n<?= $requiresWarrant ? \"Please note that this office requires a warrant. A reqest for that warrent has been forwarded to the Crown for approval.\" : \"\" ?>\n\n<table>\n    <tr>\n        <td>Office:</td>\n        <td>{{officeName}}</td>\n    </tr>\n    <tr>\n        <td>Branch:</td>\n        <td>{{branchName}}</td>\n    </tr>\n    <tr>\n        <td>Start Date:</td>\n        <td>{{hireDate}}</td>\n    </tr>\n    <tr>\n        <td>End Date:</td>\n        <td>{{endDate}}</td>\n    </tr>\n</table>\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"officeName\",\"description\":\"Office name\"},{\"name\":\"branchName\",\"description\":\"Branch name\"},{\"name\":\"hireDate\",\"description\":\"Hire date\"},{\"name\":\"endDate\",\"description\":\"End date\"},{\"name\":\"requiresWarrant\",\"description\":\"Requires warrant\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(8,'Officers\\Mailer\\OfficersMailer','notifyOfRelease','Release from Office Notification: {{officeName}}','Good day {{memberScaName}}\n\nWe regret to inform you that you have been released from the office of {{officeName}} for {{branchName}} as of\n{{releaseDate}}.\n\nThe reason for this release is: {{reason}}.\n\nWe thank you for your service and hope that you will continue to offer your service in other capacities.\n\nThank you\n{{siteAdminSignature}}.','Good day {{memberScaName}}\n\nWe regret to inform you that you have been released from the office of {{officeName}} for {{branchName}} as of\n{{releaseDate}}.\n\nThe reason for this release is: {{reason}}.\n\nWe thank you for your service and hope that you will continue to offer your service in other capacities.\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"officeName\",\"description\":\"Office name\"},{\"name\":\"branchName\",\"description\":\"Branch name\"},{\"name\":\"reason\",\"description\":\"Reason\"},{\"name\":\"releaseDate\",\"description\":\"Release date\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(9,'Activities\\Mailer\\ActivitiesMailer','notifyApprover','Authorization Approval Request','Good day {{approverScaName}}\n\n{{memberScaName}} has requested your authorization in the fine and noble art of {{activityName}}. If\nyou could go to the following link to respond to the request, that would be most kind and helpful.\n\n{{authorizationResponseUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','Good day {{approverScaName}}\n\n{{memberScaName}} has requested your authorization in the fine and noble art of {{activityName}}. If\nyou could go to the following link to respond to the request, that would be most kind and helpful.\n\n{{authorizationResponseUrl}}\n\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"authorizationResponseUrl\",\"description\":\"Authorization response url\"},{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"approverScaName\",\"description\":\"Approver sca name\"},{\"name\":\"activityName\",\"description\":\"Activity name\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL),
(10,'Activities\\Mailer\\ActivitiesMailer','notifyRequester','Update on Authorization Request','Good day {{memberScaName}}\n\n{{approverScaName}} has responded to your request and the authorization is now {{status}} for\n{{activityName}}.\n\n\n{{#if status == \"Pending\"}}\nYour request has been forwarded to {{nextApproverScaName}} for additional approval.\n{{/if}}\n\n{{#if status == \"Denied\"}}\nIf you feel this decision was made in error please reach out to {{approverScaName}} for more information.\n{{/if}}\n\n{{#if status == \"Revoked\"}}\nIf you feel this decision was made in error please reach out to {{approverScaName}} for more information.\n{{/if}}\n\n\n{{#if status == \"Approved\" || status == \"Revoked\"}}\nYou may view your updated member card at the following URL:\n\n{{memberCardUrl}}\n{{/if}}\n\nThank you\n{{siteAdminSignature}}.','Good day {{memberScaName}}\n\n{{approverScaName}} has responded to your request and the authorization is now {{status}} for\n{{activityName}}.\n\n\n{{#if status == \"Pending\"}}\nYour request has been forwarded to {{nextApproverScaName}} for additional approval.\n{{/if}}\n\n{{#if status == \"Denied\"}}\nIf you feel this decision was made in error please reach out to {{approverScaName}} for more information.\n{{/if}}\n\n{{#if status == \"Revoked\"}}\nIf you feel this decision was made in error please reach out to {{approverScaName}} for more information.\n{{/if}}\n\n\n{{#if status == \"Approved\" || status == \"Revoked\"}}\nYou may view your updated member card at the following URL:\n\n{{memberCardUrl}}\n{{/if}}\n\nThank you\n{{siteAdminSignature}}.','[{\"name\":\"memberScaName\",\"description\":\"Member sca name\"},{\"name\":\"approverScaName\",\"description\":\"Approver sca name\"},{\"name\":\"status\",\"description\":\"Status\"},{\"name\":\"activityName\",\"description\":\"Activity name\"},{\"name\":\"memberCardUrl\",\"description\":\"Member card url\"},{\"name\":\"nextApproverScaName\",\"description\":\"Next approver sca name\"},{\"name\":\"siteAdminSignature\",\"description\":\"Site admin signature\"}]',1,'2025-10-30 21:11:03','2025-10-30 21:11:03',NULL,NULL);
/*!40000 ALTER TABLE `email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_activities`
--

DROP TABLE IF EXISTS `gathering_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Name of the activity (e.g., Heavy Combat, Archery, A&S Display)',
  `description` text DEFAULT NULL COMMENT 'Description of the activity',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_activities_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_activities`
--

LOCK TABLES `gathering_activities` WRITE;
/*!40000 ALTER TABLE `gathering_activities` DISABLE KEYS */;
INSERT INTO `gathering_activities` VALUES
(1,'Kingdom Court','Official Kingdom Court sessions where awards and recognitions are presented','2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL,NULL),
(2,'Gate','Include for any event that needs a waiver','2025-11-02 16:17:45','2025-10-31 19:15:23',1,1,NULL),
(3,'Armored Combat','People engaging in armored combat activites','2025-11-02 16:19:03','2025-11-02 02:00:19',1,1,NULL),
(4,'Steel Combat','People engaging in Rapier or Cut and Thrust Activies.','2025-11-02 02:02:22','2025-11-02 02:00:46',1,1,NULL),
(5,'Equestrian ','People engaging in activities with horses.','2025-11-02 02:05:08','2025-11-02 02:05:08',1,1,NULL),
(6,'Guild Meeting','Any kind of non-martial, focused get together.','2025-11-06 01:44:42','2025-11-06 01:08:34',1,1,NULL),
(7,'Target Archery','Target Archery martial activity','2025-11-06 01:44:17','2025-11-06 01:44:17',1,1,NULL),
(8,'Thrown Weapons','Throwing knives, axes, spears, etc','2025-11-06 01:45:38','2025-11-06 01:45:38',1,1,NULL),
(9,'Youth Armored Combat','Armored combat for minors','2025-11-06 01:46:28','2025-11-06 01:46:28',1,1,NULL),
(10,'Youth Rapier Combat','Rapier combat for minors','2025-11-06 01:46:48','2025-11-06 01:46:48',1,1,NULL),
(11,'Baronial Court','Official Baronial Court sessions where non-armigerous Baronial awards and recognitions are presented','2025-12-30 18:47:38','2025-12-30 18:47:38',1,1,NULL);
/*!40000 ALTER TABLE `gathering_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_attendances`
--

DROP TABLE IF EXISTS `gathering_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_attendances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'The gathering being attended',
  `member_id` int(11) NOT NULL COMMENT 'The member attending the gathering',
  `public_note` text DEFAULT NULL COMMENT 'Public note the member wants to share about their attendance',
  `share_with_kingdom` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Share attendance with kingdom officers',
  `share_with_hosting_group` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Share attendance with the hosting group',
  `share_with_crown` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Share attendance with the crown',
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Make attendance public (SCA name only)',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_attendances_unique` (`gathering_id`,`member_id`,`deleted`),
  KEY `idx_gathering_attendances_gathering` (`gathering_id`),
  KEY `idx_gathering_attendances_member` (`member_id`),
  KEY `idx_gathering_attendances_created_by` (`created_by`),
  KEY `fk_gathering_attendances_modified_by` (`modified_by`),
  CONSTRAINT `fk_gathering_attendances_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gathering_attendances_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gathering_attendances_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gathering_attendances_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_attendances`
--

LOCK TABLES `gathering_attendances` WRITE;
/*!40000 ALTER TABLE `gathering_attendances` DISABLE KEYS */;
INSERT INTO `gathering_attendances` VALUES
(1,63,1,'',0,1,1,1,'2025-11-06 00:44:33','2025-11-07 11:49:10',1,1,NULL),
(2,63,2872,'Hopefully the weather will be nice.',1,1,1,1,'2025-11-06 00:45:55','2025-11-11 02:44:46',1,2872,NULL),
(3,58,1,'',0,1,0,0,'2025-11-06 01:17:20','2025-11-06 01:17:20',1,1,NULL),
(4,58,2871,'I need to make knees.',0,1,0,0,'2025-11-11 02:38:56','2025-11-11 02:38:56',1,2871,NULL),
(5,63,2871,'Looking forward to a great event!',1,1,1,1,'2025-11-11 02:39:24','2025-11-11 02:39:24',1,2871,NULL),
(6,55,2872,'It\'s in my backyard!',0,1,1,0,'2025-11-11 02:43:59','2025-11-11 02:43:59',1,2872,NULL),
(7,60,2878,'',0,1,0,0,'2025-11-17 23:57:55','2025-11-17 23:57:55',1,2878,NULL),
(8,63,2878,'',1,1,1,1,'2025-11-18 02:07:40','2025-11-18 02:07:40',1,2878,NULL),
(9,88,2878,'yay!',0,0,1,1,'2025-12-27 19:48:43','2025-12-27 19:48:43',1,2878,NULL),
(10,90,2878,'',0,0,0,0,'2025-12-29 20:32:59','2025-12-29 20:33:43',1,2878,'2025-12-29 20:33:43'),
(11,95,2878,'',0,0,1,0,'2026-01-01 18:48:41','2026-01-01 18:49:22',1,2878,NULL),
(12,90,2878,'',0,1,1,0,'2026-01-01 18:50:33','2026-01-01 18:50:33',1,2878,NULL),
(13,111,1,'don\'t show anyone',1,0,0,0,'2026-01-13 04:48:57','2026-01-13 04:49:05',1,1,NULL),
(14,108,2878,'',0,0,1,0,'2026-01-16 02:27:34','2026-01-16 02:27:34',1,2878,NULL),
(15,114,2872,'',0,0,1,0,'2026-01-17 17:43:58','2026-01-17 17:44:51',1,2872,NULL),
(16,114,2878,'',0,0,1,0,'2026-01-17 20:32:01','2026-01-17 20:33:14',1,2878,NULL),
(17,94,2878,'',0,0,0,0,'2026-01-17 20:34:00','2026-01-17 20:34:00',1,2878,NULL);
/*!40000 ALTER TABLE `gathering_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_scheduled_activities`
--

DROP TABLE IF EXISTS `gathering_scheduled_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_scheduled_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'FK to gatherings table - which gathering this schedule belongs to',
  `gathering_activity_id` int(11) DEFAULT NULL COMMENT 'FK to gathering_activities table - null for "other" activities',
  `start_datetime` datetime NOT NULL COMMENT 'When the scheduled activity begins',
  `end_datetime` datetime DEFAULT NULL COMMENT 'When the scheduled activity ends (optional for activities with only start time)',
  `has_end_time` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this scheduled activity has an end time',
  `display_title` varchar(255) NOT NULL COMMENT 'Custom title for this scheduled activity',
  `description` text DEFAULT NULL COMMENT 'Custom description for this scheduled activity',
  `pre_register` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether pre-registration is required/available',
  `is_other` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is an "other" activity (not linked to gathering_activity)',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'FK to members table - who created this',
  `modified_by` int(11) DEFAULT NULL COMMENT 'FK to members table - who last modified this',
  PRIMARY KEY (`id`),
  KEY `idx_gathering_scheduled_activities_gathering` (`gathering_id`),
  KEY `idx_gathering_scheduled_activities_activity` (`gathering_activity_id`),
  KEY `idx_gathering_scheduled_activities_start` (`start_datetime`),
  KEY `idx_gathering_scheduled_activities_end` (`end_datetime`),
  KEY `idx_gathering_scheduled_activities_created_by` (`created_by`),
  KEY `idx_gathering_scheduled_activities_modified_by` (`modified_by`),
  CONSTRAINT `fk_gathering_scheduled_activities_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_gathering_scheduled_activities_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_gathering_scheduled_activities_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gathering_scheduled_activities_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_scheduled_activities`
--

LOCK TABLES `gathering_scheduled_activities` WRITE;
/*!40000 ALTER TABLE `gathering_scheduled_activities` DISABLE KEYS */;
INSERT INTO `gathering_scheduled_activities` VALUES
(1,63,NULL,'2025-11-20 09:00:00',NULL,0,'Gate Opens','',0,1,'2025-11-06 00:38:19','2025-11-06 00:38:19',1,1),
(2,63,1,'2025-11-20 10:00:00',NULL,0,'Opening Court','',0,0,'2025-11-06 00:38:33','2025-11-06 00:38:33',1,1),
(3,63,4,'2025-11-20 21:00:00',NULL,0,'Rapier Tourney','',1,0,'2025-11-06 00:40:09','2025-11-17 21:30:45',1,1),
(4,63,3,'2025-11-22 09:00:00',NULL,0,'Melee activities','',1,0,'2025-11-06 00:40:51','2025-11-17 21:29:27',1,1),
(5,58,6,'2025-11-17 19:30:00','2025-11-17 22:30:00',1,'Armoring','',1,0,'2025-11-06 01:12:56','2025-11-06 01:16:43',1,1),
(6,58,6,'2025-11-17 19:30:00','2025-11-17 22:30:00',1,'Leatherworking','',1,0,'2025-11-06 01:13:22','2025-11-06 01:16:48',1,1),
(7,63,1,'2025-11-23 00:00:00',NULL,0,'Big Court','Big court in the big tent',0,0,'2025-11-12 01:54:09','2025-11-12 01:54:09',1,1),
(8,63,4,'2025-11-21 17:00:00',NULL,0,'Rapier Castle Melee','Melee fun at the Castle.',0,0,'2025-11-17 21:29:11','2025-11-17 21:30:22',1,1),
(9,63,5,'2025-11-21 15:00:00',NULL,0,'Site Trot','Rome around the site with your Horsey buddies',0,0,'2025-11-17 21:30:03','2025-11-17 21:30:03',1,1),
(10,103,3,'2026-01-10 16:00:00',NULL,0,'Coronet List','',1,0,'2026-01-10 02:41:54','2026-01-10 02:42:42',1,1),
(11,103,3,'2026-01-10 19:00:00',NULL,0,'Prince\'s Champion','',0,0,'2026-01-10 02:42:15','2026-01-10 02:42:15',1,1),
(12,103,4,'2026-01-10 19:00:00',NULL,0,'Princess\' Champion','',1,0,'2026-01-10 02:42:37','2026-01-10 02:42:56',1,1),
(13,111,3,'2026-03-19 03:45:00',NULL,0,'Diamond Tournament','',1,0,'2026-01-13 04:47:04','2026-01-13 04:47:04',1,1);
/*!40000 ALTER TABLE `gathering_scheduled_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_staff`
--

DROP TABLE IF EXISTS `gathering_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'The gathering this staff member is associated with',
  `member_id` int(11) DEFAULT NULL COMMENT 'AMP member account (null for non-AMP staff)',
  `sca_name` varchar(255) DEFAULT NULL COMMENT 'SCA name for non-AMP staff members',
  `role` varchar(100) NOT NULL COMMENT 'Role name (e.g., "Steward", "Herald", "List Master")',
  `is_steward` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this staff member is a steward',
  `show_on_public_page` tinyint(1) NOT NULL DEFAULT 0,
  `email` varchar(255) DEFAULT NULL COMMENT 'Contact email (copied from member for stewards, editable)',
  `phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone (copied from member for stewards, editable)',
  `contact_notes` text DEFAULT NULL COMMENT 'Contact preferences (e.g., "text only", "no calls after 9pm")',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order (stewards first, then others)',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gathering_id` (`gathering_id`),
  KEY `member_id` (`member_id`),
  KEY `is_steward` (`is_steward`),
  KEY `sort_order` (`sort_order`),
  KEY `deleted` (`deleted`),
  CONSTRAINT `gathering_staff_ibfk_1` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gathering_staff_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_staff`
--

LOCK TABLES `gathering_staff` WRITE;
/*!40000 ALTER TABLE `gathering_staff` DISABLE KEYS */;
INSERT INTO `gathering_staff` VALUES
(1,63,2872,NULL,'Steward',1,1,'bryce@ampdemo.com','222-222-2222','He\'s not the autocrat of Bordermarch, so it\'s ok.',100,'2025-11-05 00:56:16','2025-11-05 00:56:33',1,1,NULL),
(2,63,NULL,'Jack Smith','Feast Steward',0,1,'','','',100,'2025-11-06 00:43:35','2025-11-06 00:43:35',1,1,NULL),
(3,83,2878,NULL,'Steward',1,1,'iris@ampdemo.com','','',0,'2025-11-18 02:23:52','2025-11-18 02:23:52',1,2872,NULL),
(4,84,2878,NULL,'Steward',1,1,'iris@ampdemo.com','','',0,'2025-11-18 02:25:35','2025-11-18 02:25:51',1,2872,'2025-11-18 02:25:51'),
(5,84,2871,NULL,'Marshal',0,0,'agatha@ampdemo.com','111-111-1111','',100,'2025-11-18 02:26:23','2025-11-18 02:26:23',1,2872,NULL),
(6,97,2878,NULL,'Seneschal',0,0,'iris@ampdemo.com','','',100,'2026-01-10 02:23:37','2026-01-10 02:23:37',1,1,NULL),
(7,98,2871,NULL,'Leader',0,0,'agatha@ampdemo.com','111-111-1111','',100,'2026-01-10 02:33:13','2026-01-10 02:33:13',1,1,NULL),
(8,100,2871,NULL,'Leader',0,0,'agatha@ampdemo.com','111-111-1111','',100,'2026-01-10 02:35:12','2026-01-10 02:35:12',1,1,NULL),
(9,101,2871,NULL,'Leader',0,0,'agatha@ampdemo.com','111-111-1111','',100,'2026-01-10 02:35:18','2026-01-10 02:35:18',1,1,NULL),
(10,102,2871,NULL,'Leader',0,0,'agatha@ampdemo.com','111-111-1111','',100,'2026-01-10 02:35:24','2026-01-10 02:35:24',1,1,NULL),
(11,103,2872,NULL,'Autocrat',1,1,'bryce@ampdemo.com','222-222-2222','',0,'2026-01-10 02:40:23','2026-01-10 02:40:23',1,1,NULL),
(12,104,2872,NULL,'autocrat',1,1,'bryce@ampdemo.com','222-222-2222','',0,'2026-01-10 03:06:08','2026-01-10 03:06:08',1,1,NULL),
(13,111,2872,NULL,'Autocrat',1,1,'bryce@ampdemo.com','222-222-2222','',0,'2026-01-13 04:49:36','2026-01-13 04:49:36',1,1,NULL),
(14,111,2890,NULL,'Waterbearer Lead',0,1,'sierra@sierramist.com','456-789-0123','',100,'2026-01-13 04:50:31','2026-01-13 04:50:31',1,1,NULL),
(15,109,2878,NULL,'Coordinator',1,1,'iris@ampdemo.com','','',0,'2026-02-01 00:11:13','2026-02-01 00:11:13',1,1,NULL),
(16,115,2878,NULL,'Coordinator',1,1,'iris@ampdemo.com','','',0,'2026-02-01 14:49:49','2026-02-01 14:49:49',1,1,NULL);
/*!40000 ALTER TABLE `gathering_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_type_gathering_activities`
--

DROP TABLE IF EXISTS `gathering_type_gathering_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_type_gathering_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_type_id` int(11) NOT NULL COMMENT 'FK to gathering_types table',
  `gathering_activity_id` int(11) NOT NULL COMMENT 'FK to gathering_activities table',
  `not_removable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, this activity cannot be removed from gatherings of this type',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gtgact_unique` (`gathering_type_id`,`gathering_activity_id`),
  KEY `idx_gtgact_type` (`gathering_type_id`),
  KEY `idx_gtgact_activity` (`gathering_activity_id`),
  KEY `idx_gtgact_not_removable` (`not_removable`),
  CONSTRAINT `fk_gtgact_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gtgact_type` FOREIGN KEY (`gathering_type_id`) REFERENCES `gathering_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_type_gathering_activities`
--

LOCK TABLES `gathering_type_gathering_activities` WRITE;
/*!40000 ALTER TABLE `gathering_type_gathering_activities` DISABLE KEYS */;
INSERT INTO `gathering_type_gathering_activities` VALUES
(5,1,1,1,'2025-11-02 16:08:47','2025-11-02 16:08:47',1,1),
(6,1,2,1,'2025-11-02 16:10:37','2025-11-02 16:10:37',1,1),
(7,2,3,0,'2025-11-02 16:12:22','2025-11-02 16:12:22',1,1),
(8,2,4,0,'2025-11-02 16:12:28','2025-11-02 16:12:28',1,1),
(9,2,5,0,'2025-11-06 01:43:14','2025-11-06 01:43:14',1,1),
(10,2,7,0,'2025-11-06 01:47:07','2025-11-06 01:47:07',1,1),
(11,2,8,0,'2025-11-06 01:47:12','2025-11-06 01:47:12',1,1),
(12,2,9,0,'2025-11-06 01:47:15','2025-11-06 01:47:15',1,1),
(13,2,10,0,'2025-11-06 01:47:19','2025-11-06 01:47:19',1,1),
(14,5,4,0,'2025-11-06 01:52:15','2025-11-06 01:52:15',1,1),
(15,5,10,0,'2025-11-06 01:52:20','2025-11-06 01:52:20',1,1),
(16,6,5,1,'2025-11-06 01:54:46','2025-11-06 01:54:46',1,1),
(17,7,8,0,'2025-11-06 01:56:10','2025-11-06 01:56:10',1,1),
(18,7,7,0,'2025-11-06 01:56:17','2025-11-06 01:56:17',1,1),
(19,8,3,0,'2026-01-13 03:19:02','2026-01-13 03:19:02',1,1),
(20,10,3,0,'2026-01-13 03:20:42','2026-01-13 03:20:42',1,1),
(21,10,4,0,'2026-01-13 03:20:51','2026-01-13 03:20:51',1,1);
/*!40000 ALTER TABLE `gathering_type_gathering_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gathering_types`
--

DROP TABLE IF EXISTS `gathering_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gathering_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Name of the gathering type (e.g., Tournament, Practice, Feast)',
  `description` text DEFAULT NULL COMMENT 'Description of this gathering type',
  `color` varchar(7) NOT NULL DEFAULT '#0d6efd' COMMENT 'Hex color code for calendar display',
  `clonable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this type can be used as a template for new gatherings',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_types_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gathering_types`
--

LOCK TABLES `gathering_types` WRITE;
/*!40000 ALTER TABLE `gathering_types` DISABLE KEYS */;
INSERT INTO `gathering_types` VALUES
(1,'Kingdom Calendar Event','Official Kingdom calendar events','#e5dd06',1,'2025-11-02 16:07:34','2025-10-30 21:03:03',NULL,1,NULL),
(2,'Local Martial Practice','All of the marshal activities in one gathering.','#fd0d0d',1,'2025-11-06 01:55:29','2025-10-31 19:23:44',1,1,NULL),
(3,'Local Meeting','Local activies that don\'t typically have a martial aspect to them.','#fd790d',1,'2025-11-02 01:48:13','2025-11-02 01:48:13',1,1,NULL),
(4,'Kingdom Event','This is the default gathering to use for Kingdom Events.','#bdfd0d',1,'2025-11-02 16:06:43','2025-11-02 15:56:21',1,1,'2025-11-02 16:06:43'),
(5,'Fencing Practice','A gathering focused on rapier or cut-and-thrust fighting.','#0da1fd',1,'2025-11-06 01:52:06','2025-11-06 01:52:06',1,1,NULL),
(6,'Equestrian Practice','A gathering focused on equine activities.','#910dfd',1,'2025-11-06 01:53:20','2025-11-06 01:53:20',1,1,NULL),
(7,'Missile Practice','A gathering focused on missile activities.','#0d6efd',1,'2025-11-06 01:56:05','2025-11-06 01:56:05',1,1,NULL),
(8,'Local Martial Practice - Armored Only','Fighter practice that only has armored combat.','#fd550d',1,'2026-01-13 03:18:56','2026-01-13 03:18:56',1,1,NULL),
(9,'Local Martial Practice - Rapier Only','Local fighter practice with only rapier combat','#459702',1,'2026-01-13 03:19:56','2026-01-13 03:19:56',1,1,NULL),
(10,'Local Martial Practice - Armored and Rapier','Local Fighter Practice that has both armored combat and rapier combat','#a65e5e',1,'2026-01-13 03:20:29','2026-01-13 03:20:29',1,1,NULL),
(11,'Virtual Kingdom Calendar Event','For events (like Round Table) that are completely virtual and do not require waivers because there is no in-person attendance','#000000',1,'2026-01-13 03:27:02','2026-01-13 03:27:02',1,1,NULL);
/*!40000 ALTER TABLE `gathering_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gatherings`
--

DROP TABLE IF EXISTS `gatherings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gatherings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(8) NOT NULL COMMENT 'Non-sequential public identifier safe for client exposure',
  `branch_id` int(11) NOT NULL COMMENT 'Branch hosting this gathering',
  `gathering_type_id` int(11) NOT NULL COMMENT 'Type of gathering',
  `name` varchar(255) NOT NULL COMMENT 'Name of the gathering',
  `description` text DEFAULT NULL COMMENT 'Description of the gathering',
  `start_date` datetime NOT NULL COMMENT 'Start date and time of the gathering (stored in UTC)',
  `end_date` datetime NOT NULL COMMENT 'End date and time of the gathering (stored in UTC)',
  `location` varchar(255) DEFAULT NULL COMMENT 'Location of the gathering',
  `timezone` varchar(50) DEFAULT NULL COMMENT 'IANA timezone identifier for the event location (e.g., America/Chicago)',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Latitude coordinate from Google Maps geocoding',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Longitude coordinate from Google Maps geocoding',
  `public_page_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the public landing page is enabled for this gathering',
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gatherings_public_id` (`public_id`),
  KEY `idx_gatherings_branch` (`branch_id`),
  KEY `idx_gatherings_type` (`gathering_type_id`),
  KEY `idx_gatherings_created_by` (`created_by`),
  KEY `idx_gatherings_start_date` (`start_date`),
  KEY `idx_gatherings_end_date` (`end_date`),
  KEY `idx_gatherings_cancelled_at` (`cancelled_at`),
  CONSTRAINT `fk_gatherings_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_gatherings_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_gatherings_type` FOREIGN KEY (`gathering_type_id`) REFERENCES `gathering_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gatherings`
--

LOCK TABLES `gatherings` WRITE;
/*!40000 ALTER TABLE `gatherings` DISABLE KEYS */;
INSERT INTO `gatherings` VALUES
(1,'UGf6KdRg',18,1,'Namron Protectorate and Kingdom Coronation','https://ansteorra.org/namron/protectorate-xlviii/','2024-10-04 00:00:00','2024-10-06 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(2,'F9NLfuzR',41,1,'Bjornsborg\'s Fall Event - Dance Macabre','https://ansteorra.org/bjornsborg/danse-macabre/','2024-10-18 00:00:00','2024-10-20 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(3,'ZCw5xZPh',10,1,'Diamond Wars','https://gleannabhann.net/event/diamond-wars/','2024-10-18 00:00:00','2024-10-20 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(4,'yxVUuBQ7',17,1,'Eldern Hills - Samhain','https://ansteorra.org/eldern-hills/events/','2024-10-25 00:00:00','2024-10-27 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(5,'xgEYwgTs',37,1,'Seawind\'s Defender','https://ansteorra.org/seawinds/','2024-10-25 00:00:00','2024-10-27 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(6,'r9Q3qQNR',2,1,'Vindheim Missile Academy II',' https://sites.google.com/u/5/d/1QToqHTcjWDrReUs_OJp_wvDdQln3hduf/preview','2024-11-01 00:00:00','2024-11-03 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(7,'92xXhajY',35,1,'A Toast to Absent Friends: A Dia de Los Muertos Event','https://ansteorra.org/shadowlands','2024-11-01 00:00:00','2024-11-03 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(8,'VfNz5MRX',20,1,'Queen\'s Champion','https://ansteorra.org/events','2024-11-09 00:00:00','2024-11-09 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(9,'dq637oZD',33,1,'Bryn Gwlad Fall Baronial','https://ansteorra.org/bryn-gwlad/bryn-gwlad-fall-baronial-2024/','2024-11-15 00:00:00','2024-11-17 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(10,'4bvg3BYm',34,1,'Bordermarch War of the Rams','https://ansteorra.org/bordermarch','2024-11-21 00:00:00','2024-11-24 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(11,'5Ems5d9b',29,1,'Winter Crown Tournament','https://ansteorra.org/events','2024-12-07 00:00:00','2024-12-07 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(12,'pz7tQeeW',23,1,'Vindheim Winter Coronet','https://ansteorra.org/events','2024-12-14 00:00:00','2024-12-14 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(13,'6Jcpc5mf',39,1,'Stargate Yule','https://ansteorra.org/events','2024-12-14 00:00:00','2024-12-14 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(14,'Yti8uqcp',20,1,'Wiesenfeuer Yule','https://ansteorra.org/wiesenfeuer','2024-12-21 00:00:00','2024-12-21 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(15,'hPLXWaes',27,1,'Steppes 12th Night','https://ansteorra.org/steppes','2025-01-04 00:00:00','2025-01-04 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(16,'9WooFTsr',29,1,'Elfsea\'s Yule','https://ansteorra.org/elfsea','2025-01-11 00:00:00','2025-01-11 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(17,'NDBAZV5n',19,1,'Marata Midwinter Melees','https://ansteorra.org/events','2025-01-25 00:00:00','2025-01-25 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(18,'MJAtE282',25,1,'Winterkingdom',' https://ansteorra.org/northkeep/activities/events/winterkingdom/winterkingdom-collegium-when-in-rome/','2025-02-01 00:00:00','2025-02-01 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(19,'LheD7vNh',33,1,'Bryn Gwlad Candlemas','https://ansteorra.org/bryn-gwlad','2025-02-01 00:00:00','2025-02-01 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(20,'fTbJYdx5',2,1,'Laurel\'s Prize Tournament','https://ansteorra.org/events','2025-02-08 00:00:00','2025-02-08 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(21,'wAq8NVNy',31,1,'Battle of the Pines','https://ansteorra.org/graywood','2025-02-15 00:00:00','2025-02-15 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(22,'yirTNG4K',10,1,'Gulf Wars XXXIII','https://gulfwars.org','2025-03-08 00:00:00','2025-03-16 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(23,'r6sHecro',38,1,'Commander\'s Crucible Anniversary','https://ansteorra.org/hellsgate','2025-03-28 00:00:00','2025-03-30 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(24,'zqqYJWRK',29,1,'Elfsea\'s Defender','https://ansteorra.org/elfsea','2025-04-04 00:00:00','2025-04-06 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(25,'h3DhQUf3',2,1,'Coronation','https://ansteorra.org/events','2025-04-12 00:00:00','2025-04-12 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(26,'pvzhVAho',39,1,'Stargate\'s Baronial','https://ansteorra.org/stargate','2025-04-18 00:00:00','2025-04-20 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(27,'ypiVDLxx',20,1,'Wiesenfeuer\'s Baronial','https://ansteorra.org/wiesenfeuer','2025-04-18 00:00:00','2025-04-20 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(28,'qX6P4Ki6',28,1,'Glaslyn\'s Defender on the Flame','https://ansteorra.org/glaslyn','2025-04-25 00:00:00','2025-04-27 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(29,'feaXDRBd',36,1,'Loch Soilleir\'s Baronial','https://ansteorra.org/loch-soilleir','2025-05-02 00:00:00','2025-05-04 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(30,'3uUrSTJ7',2,1,'Queen\'s Champion','https://ansteorra.org/events','2025-05-10 00:00:00','2025-05-10 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(31,'qTFpokMY',25,1,'Northkeep\'s Castellan','https://ansteorra.org/northkeep','2025-05-16 00:00:00','2025-05-18 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(32,'AHudihKH',27,1,'Steppes Warlord','https://ansteorra.org/steppes','2025-05-23 00:00:00','2025-05-25 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(33,'ASyPYBnJ',2,1,'Summer Crown Tournament','https://ansteorra.org/events','2025-06-07 00:00:00','2025-06-07 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(34,'xQT9r9S7',2,1,'Vindheim Summer Coronet','https://ansteorra.or/events','2025-06-21 00:00:00','2025-06-21 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(35,'rv2AHkso',2,1,'Kingdom Collegium','https://ansteorra.org/events','2025-07-12 00:00:00','2025-07-12 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(36,'SdtRVbaP',10,1,'Pennsic','https://pennsic.org','2025-07-25 00:00:00','2025-08-10 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(37,'R9BvqAid',27,1,'Steppes Artisan','https://ansteorra.org/steppes','2025-08-16 00:00:00','2025-08-16 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(38,'X7wpnVcj',36,1,'Serpent\'s Symposium VII','https://ansteorra.org/loch-soilleir','2025-08-23 00:00:00','2025-08-23 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(39,'CUcojXLK',30,1,'Bonwicke\'s War of Legends','https://ansteorra.org/bonwicke','2025-08-29 00:00:00','2025-08-31 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(40,'ap2ySBbJ',29,1,'Elfsea Baronial College','https://ansteorra.org/elfsea','2025-09-07 00:00:00','2025-09-07 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(41,'YYcmpZnE',2,1,'Kingdom Arts and Sciences','https://ansteorra.org/events','2025-09-13 00:00:00','2025-09-13 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(42,'XgmRS3nr',22,1,'Mooneschadowe\'s Triumphe of the Eclipse','https://ansteorra.org/mooneschadowe','2025-10-17 00:00:00','2025-10-19 00:00:00','',NULL,'2025-11-02 01:51:00','2025-10-30 21:03:03',1,1,NULL,NULL,NULL,1,NULL,NULL),
(43,'gnCJCp9B',40,1,'Raven\'s Fort Defender of the Fort','https://ansteorra.org/ravensfort','2025-09-19 00:00:00','2025-09-21 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(44,'nCboAo93',32,1,'Rosenfeld Champions and Three Things','https://ansteorra.org/rosenfeld','2025-09-26 00:00:00','2025-09-28 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(45,'h5xmCPtY',42,1,'Ffynnon Gath\'s War of Ages','https://ansteorra.org/ffynnon-gath','2025-09-26 00:00:00','2025-09-28 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(46,'qhK4PHxp',41,1,'Bjornsborg Spring Event','Bjornsborg\'s Spring Event','2025-04-25 00:00:00','2025-04-27 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(47,'XiYsnjUS',40,1,'A Day in the...','Ravens Fort Spring event','2025-02-21 00:00:00','2025-03-23 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(48,'vnQjqNzV',40,1,'Enchanted Conflict (Raven\'s Fort)','Raven\'s Fort\'s Spring Event','2025-02-21 00:00:00','2025-02-23 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(49,'YtMvezi6',34,1,'Bordermarch Baronials','Spring Baronial Event','2025-03-29 00:00:00','2025-03-30 00:00:00',NULL,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL),
(50,'nLuKcqxQ',41,2,'Local Practice','','2025-12-24 01:00:00','2025-12-24 03:00:00','501 El Portal Dr, San Antonio, TX 78232, USA','','2025-12-18 01:09:41','2025-10-31 19:24:54',1,1,NULL,29.60004300,-98.49723020,1,NULL,NULL),
(51,'vTLfd8ow',22,2,'Fighter Practice - Armored and Rapier','We are usually at Boomer Lake, Pavilion #2. \r\n\r\nLocation may be variable. When in doubt, ask on the Facebook group: HailMOG.','2025-11-06 00:00:00','2025-11-06 00:00:00','Boomer Lake Drive, Stillwater, OK, USA',NULL,'2025-11-02 02:04:15','2025-11-02 01:24:37',1,1,'2025-11-02 02:04:15',NULL,NULL,1,NULL,NULL),
(52,'bSHB5NLY',22,2,'Fighter Practice - Armored and Rapier','We are usually at Boomer Lake, Pavilion #2. \r\n\r\nLocation may be variable. When in doubt, ask on the Facebook group: HailMOG.','2025-11-13 00:00:00','2025-11-13 00:00:00','Boomer Lake Drive, Stillwater, OK, USA',NULL,'2025-11-02 02:03:57','2025-11-02 01:28:07',1,1,'2025-11-02 02:03:57',NULL,NULL,1,NULL,NULL),
(53,'2o5LnZNR',22,2,'Fighter Practice - Armored and Rapier','We are usually at Boomer Lake, Pavilion #2. \r\n\r\nLocation may be variable. When in doubt, ask on the Facebook group: HailMOG.','2025-11-20 00:00:00','2025-11-20 00:00:00','Boomer Lake Drive, Stillwater, OK, USA',NULL,'2025-11-02 02:03:45','2025-11-02 01:28:15',1,1,'2025-11-02 02:03:45',NULL,NULL,1,NULL,NULL),
(54,'PBvULp89',22,2,'Fighter Practice - Armored and Rapier','We are usually at Boomer Lake, Pavilion #2. \r\n\r\nLocation may be variable. When in doubt, ask on the Facebook group: HailMOG.','2025-11-27 00:00:00','2025-11-27 00:00:00','Boomer Lake Drive, Stillwater, OK, USA',NULL,'2026-01-10 03:02:28','2025-11-02 01:28:20',1,1,'2026-01-10 03:02:28',NULL,NULL,1,NULL,NULL),
(55,'CMRaFkvK',39,1,'Queen\'s Champion','Herman Sons Life Hall – 6785 FM 111, Deanville, TX 77852\r\nFall Queen’s Championship Tournament, AS LX NEW DATE: November 15, 2025   Hosted by the Barony of Stargate in Deanville, TX\r\n\r\nStargate invites the populace and Ansteorra’s Rapier Community to celebrate the return of Her Stellar Majesty Cristyana to her homeland on Saturday, November 15, 2025. Join us for a day of tournaments and festivities to choose Her Majesty’s champions.\r\n\r\nIn the morning, her majesty will be hosting a tournament for those who have not risen to the membership of the Order of the White Scarf of Ansteorra or the Order of the Masters of Defense. The winner will have the honor of being one of Ansteorra’s champions in the champions’ battle at Gulf War.\r\n\r\nIn the midday hours, her majesty will be hosting a tournament for our youth rapier fighters. There will be an upper and lower division if the attendance warrants that. The winner will be proclaimed the Queen’s Hope.\r\n\r\nIn the afternoon, her majesty will be hosting the prestigious Ansteorra Queen’s Championship tournament to select the best of the best for her majesty’s personal champion.','2025-11-15 00:00:00','2025-11-15 00:00:00','6785 FM 111, Deanville, TX 77852, USA',NULL,'2026-01-10 03:02:17','2025-11-02 01:36:40',1,1,'2026-01-10 03:02:17',30.42972270,-96.75915790,1,NULL,NULL),
(56,'gUgWZPu4',22,3,'Armorers and Leather Working','Please contact Sir Jean Paul if you are interested in armoring.\r\n\r\nRequires prior sign-up to attend.\r\n\r\nSign ups here:\r\n\r\nhttps://www.signupgenius.com/go/9040848A4A829A4FA7-armor\r\n\r\n \r\nShop openings may be affected by the weather. Watch HailMOG for updates.','2025-11-03 00:00:00','2025-11-03 00:00:00','1520 McMurtry Rd, Stillwater, OK 74075, USA',NULL,'2025-11-06 01:13:59','2025-11-02 01:49:00',1,1,'2025-11-06 01:13:59',36.19022300,-97.07837000,1,NULL,NULL),
(57,'8xTiTWdJ',22,3,'Armorers and Leather Working','Please contact Sir Jean Paul if you are interested in armoring.\r\n\r\nRequires prior sign-up to attend.\r\n\r\nSign ups here:\r\n\r\nhttps://www.signupgenius.com/go/9040848A4A829A4FA7-armor\r\n\r\n \r\nShop openings may be affected by the weather. Watch HailMOG for updates.','2025-11-10 00:00:00','2025-11-10 00:00:00','1520 McMurtry Rd, Stillwater, OK 74075, USA',NULL,'2025-11-06 01:14:09','2025-11-02 01:49:15',1,1,'2025-11-06 01:14:09',NULL,NULL,1,NULL,NULL),
(58,'eypJTvmy',22,3,'Armorers and Leather Working','Please contact Sir Jean Paul if you are interested in armoring.\r\n\r\nRequires prior sign-up to attend.\r\n\r\nSign ups here:\r\n\r\nhttps://www.signupgenius.com/go/9040848A4A829A4FA7-armor\r\n\r\n \r\nShop openings may be affected by the weather. Watch HailMOG for updates.','2025-11-17 00:00:00','2025-11-17 00:00:00','1520 McMurtry Rd, Stillwater, OK 74075, USA',NULL,'2025-11-02 01:49:25','2025-11-02 01:49:25',1,1,NULL,NULL,NULL,1,NULL,NULL),
(59,'fYfx8qmD',22,3,'November Populace','The monthly business meeting for the Province of Mooneschadowe. Garb is encouraged, but not required.\r\n\r\nWatch HailMOG (or Discord) for changes.\r\n\r\nCurrent rooms is 202 Morrill hall','2025-11-04 00:00:00','2025-11-04 00:00:00','205 Morrill Ave, Stillwater, OK 74075, USA',NULL,'2025-11-02 01:56:10','2025-11-02 01:55:15',1,1,NULL,36.12201360,-97.06646690,1,NULL,NULL),
(60,'e6EjCMYF',22,3,'December Populace ','The monthly business meeting for the Province of Mooneschadowe. Garb is encouraged, but not required.\r\n\r\nWatch HailMOG (or Discord) for changes.\r\n\r\nCurrent rooms is 202 Morrill hall','2025-12-02 00:00:00','2025-12-02 00:00:00','205 Morrill Ave, Stillwater, OK 74075, USA',NULL,'2025-11-02 01:55:56','2025-11-02 01:55:56',1,1,NULL,NULL,NULL,1,NULL,NULL),
(61,'nvxXAqVQ',34,1,'War of the Rams','Ansteorra welcomes Gleann Abhann\r\nTwo FULL days of fighting and more Merriment than that!\r\nCome for the Battles, stay for the friends','2025-11-20 00:00:00','2025-11-23 00:00:00','5754 Recreational Rd 255, Colmesneil, TX 75938, USA',NULL,'2025-11-02 16:15:23','2025-11-02 01:58:31',1,1,'2025-11-02 16:15:23',30.96360180,-94.32611300,1,NULL,NULL),
(62,'rxhjppBk',41,1,'Local Practice','','2025-11-30 00:00:00','2025-11-30 00:00:00','',NULL,'2025-11-02 11:02:23','2025-11-02 11:02:23',1,1,NULL,NULL,NULL,1,NULL,NULL),
(63,'zNJsnDqq',34,1,'War of the Rams','Ansteorra welcomes Gleann Abhann Two FULL days of fighting and more Merriment than that! Come for the Battles, stay for the friends','2025-11-20 00:00:00','2025-11-23 00:00:00','5754 Recreational Rd 255, Colmesneil, TX 75938, USA',NULL,'2026-01-10 03:02:59','2025-11-02 16:13:58',1,1,'2026-01-10 03:02:59',30.96360180,-94.32611300,1,NULL,NULL),
(64,'J3tvejMd',41,1,'Local Practice','','2025-11-30 00:00:00','2025-11-30 00:00:00','',NULL,'2025-11-02 16:17:38','2025-11-02 16:17:30',1,1,'2025-11-02 16:17:38',NULL,NULL,1,NULL,NULL),
(65,'teBEvxED',41,1,'Local Practice','','2025-11-02 00:00:00','2025-11-02 01:00:00','','','2026-01-10 02:50:29','2025-11-02 16:50:28',1,1,'2026-01-10 02:50:29',NULL,NULL,1,NULL,NULL),
(66,'GpCpHCrj',41,2,'Local Practice','','2025-11-05 00:00:00','2025-11-05 00:00:00','701 El Portal Dr, San Antonio, TX 78232, USA',NULL,'2026-01-10 03:00:35','2025-11-06 00:07:53',1,1,'2026-01-10 03:00:35',29.59975110,-98.50061150,1,NULL,NULL),
(67,'sNjJgsR6',41,2,'Local Practice','','2025-11-12 00:00:00','2025-11-12 00:00:00','701 El Portal Dr, San Antonio, TX 78232, USA',NULL,'2026-01-10 03:02:02','2025-11-06 00:33:16',1,1,'2026-01-10 03:02:02',NULL,NULL,1,NULL,NULL),
(68,'UpYM7dJV',41,2,'Local Practice','','2025-11-19 00:00:00','2025-11-19 00:00:00','701 El Portal Dr, San Antonio, TX 78232, USA',NULL,'2026-01-10 03:02:42','2025-11-06 00:33:21',1,1,'2026-01-10 03:02:42',NULL,NULL,1,NULL,NULL),
(69,'7Wz4puvz',22,2,'Fighting Practice','Armored and Rapier practice','2025-11-06 00:00:00','2025-11-06 00:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA',NULL,'2026-01-10 03:00:59','2025-11-06 01:06:29',1,1,'2026-01-10 03:00:59',36.15237930,-97.06778060,0,NULL,NULL),
(70,'fxQzqJKx',22,2,'Fighting Practice','Armored and Rapier practice','2025-11-13 00:00:00','2025-11-13 00:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA',NULL,'2026-01-10 03:02:10','2025-11-06 01:07:01',1,1,'2026-01-10 03:02:10',NULL,NULL,0,NULL,NULL),
(71,'8rF8cgRV',22,3,'Armorers and Leather Working','Please contact Sir Jean Paul if you are interested in armoring.\r\n\r\nRequires prior sign-up to attend.\r\n\r\nSign ups here:\r\n\r\nhttps://www.signupgenius.com/go/9040848A4A829A4FA7-armor\r\n\r\n \r\nShop openings may be affected by the weather. Watch HailMOG for updates.','2025-11-10 00:00:00','2025-11-10 00:00:00','1520 McMurtry Rd, Stillwater, OK 74075, USA',NULL,'2026-01-10 03:01:36','2025-11-06 01:14:34',1,1,'2026-01-10 03:01:36',NULL,NULL,1,NULL,NULL),
(72,'6nkfa3WA',22,3,'Armorers and Leather Working','Please contact Sir Jean Paul if you are interested in armoring.\r\n\r\nRequires prior sign-up to attend.\r\n\r\nSign ups here:\r\n\r\nhttps://www.signupgenius.com/go/9040848A4A829A4FA7-armor\r\n\r\n \r\nShop openings may be affected by the weather. Watch HailMOG for updates.','2025-11-24 00:00:00','2025-11-24 00:00:00','1520 McMurtry Rd, Stillwater, OK 74075, USA',NULL,'2025-11-06 01:14:44','2025-11-06 01:14:44',1,1,NULL,NULL,NULL,1,NULL,NULL),
(73,'PnLCZXf8',29,2,'Fencing Practice','','2025-11-07 00:00:00','2025-11-07 00:00:00','',NULL,'2026-01-10 02:49:24','2025-11-06 01:36:53',1,1,'2026-01-10 02:49:24',NULL,NULL,0,NULL,NULL),
(74,'XjZy3DLA',17,2,'Armored Practice','','2025-11-06 00:00:00','2025-11-06 00:00:00','',NULL,'2025-11-06 01:40:42','2025-11-06 01:40:42',1,1,NULL,NULL,NULL,0,NULL,NULL),
(75,'95wA32Hx',27,2,'Rapier Practice','','2025-11-07 00:00:00','2025-11-07 00:00:00','',NULL,'2025-11-06 01:50:03','2025-11-06 01:50:03',1,1,NULL,NULL,NULL,0,NULL,NULL),
(76,'b6LpH78g',40,2,'Equestrian Practice','','2025-11-08 00:00:00','2025-11-08 00:00:00','',NULL,'2026-01-10 03:01:12','2025-11-06 01:51:10',1,1,'2026-01-10 03:01:12',NULL,NULL,1,NULL,NULL),
(77,'h967fLpT',28,6,'Mule Riding Rodeo','','2025-11-08 00:00:00','2025-11-08 00:00:00','',NULL,'2026-01-10 03:01:25','2025-11-06 01:53:49',1,1,'2026-01-10 03:01:25',NULL,NULL,0,NULL,NULL),
(78,'GSippcNR',23,7,'Yeet-a-thon','','2025-11-07 00:00:00','2025-11-07 00:00:00','',NULL,'2026-01-10 03:01:05','2025-11-06 01:56:50',1,1,'2026-01-10 03:01:05',NULL,NULL,0,NULL,NULL),
(79,'Bd8wgJ3T',15,2,'Mounted Yeeting','','2025-11-03 00:00:00','2025-11-03 00:00:00','',NULL,'2025-11-06 02:00:40','2025-11-06 02:00:40',1,1,NULL,NULL,NULL,0,NULL,NULL),
(80,'AYMxp4WW',42,2,'All Martial Activities Practice','','2025-11-03 00:00:00','2025-11-03 00:00:00','',NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1,NULL,NULL,NULL,0,NULL,NULL),
(81,'xQXFDq2t',39,2,'Combined Practice','','2025-11-09 19:00:00','2025-11-09 23:00:00','','','2025-11-11 02:56:35','2025-11-11 02:56:35',1,1,NULL,NULL,NULL,0,NULL,NULL),
(82,'NMxxudJ5',39,2,'Combined Practice','','2025-11-16 19:00:00','2025-11-16 19:00:00','','','2026-01-10 03:02:34','2025-11-11 02:57:28',1,1,'2026-01-10 03:02:34',NULL,NULL,0,NULL,NULL),
(83,'73h5ZUv2',39,1,'Dogs vs Horses','Which is better?','2025-11-20 02:22:00','2025-11-20 05:22:00','Woods Park, Lincoln, NE 68510, USA','','2026-01-10 03:03:22','2025-11-18 02:23:25',1,1,'2026-01-10 03:03:22',40.80859150,-96.67717750,1,NULL,NULL),
(84,'vuwnA9at',39,1,'Dogs vs Horses','Which is better?','2025-12-18 02:22:00','2025-12-18 02:22:00','Woods Park, Lincoln, NE 68510, USA','','2026-01-10 02:50:09','2025-11-18 02:25:35',1,1,'2026-01-10 02:50:09',NULL,NULL,1,NULL,NULL),
(85,'Zyx4gLCj',39,2,'Big Practice','','2025-11-19 02:41:00','2025-11-19 02:41:00','','','2026-01-10 03:02:51','2025-11-18 02:41:54',1,1,'2026-01-10 03:02:51',NULL,NULL,0,NULL,NULL),
(86,'9crSkXfS',39,5,'Fighting Practice','','2025-11-20 02:43:00','2025-11-20 02:43:00','','','2026-01-10 03:03:15','2025-11-18 02:43:51',1,1,'2026-01-10 03:03:15',NULL,NULL,1,NULL,NULL),
(87,'SWrAhz3s',39,5,'Fencing Practice','','2025-12-05 00:47:00','2025-12-05 02:47:00','','','2025-12-04 00:47:59','2025-12-04 00:47:59',1,1,NULL,NULL,NULL,0,NULL,NULL),
(88,'4w4FW8vb',22,3,'Boxing Day','','2025-12-27 00:58:00','2025-12-27 00:58:00','1520 W. MCMURTRY','','2025-12-18 00:58:41','2025-12-18 00:58:41',1,1,NULL,NULL,NULL,0,NULL,NULL),
(89,'XVHvB7VQ',39,3,'Spring Festival','Super cool spring festival, but waaaay over in austin','2026-03-23 22:36:00','2026-03-24 22:36:00','Hyde Park, Austin, TX, USA','','2025-12-28 23:37:03','2025-12-28 23:37:03',1,2872,NULL,30.30995450,-97.73115040,1,NULL,NULL),
(90,'Abqjnms2',39,1,'Stargate Something','','2026-01-03 07:30:00','2026-01-03 09:30:00','','','2025-12-30 18:29:29','2025-12-29 08:31:03',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(91,'mGReH83P',39,2,'Fighter Practice','','2025-12-31 19:12:00','2025-12-31 21:12:00','','','2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(92,'J4HUpYaJ',39,2,'Cancelled: practice 2.0','','2026-01-01 19:19:00','2026-01-01 21:19:00','','','2026-01-29 00:46:52','2025-12-29 19:19:28',1,1,NULL,NULL,NULL,1,NULL,NULL),
(93,'Brh3hp43',39,2,'practice 3.0','','2026-01-02 19:21:00','2026-01-02 21:21:00','','','2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(94,'wjEeNQcS',39,1,'Baronial Court Test Event','','2026-01-27 14:00:00','2026-01-28 15:00:00','','','2025-12-30 18:51:15','2025-12-30 18:51:15',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(95,'H6SvF3YV',32,1,'Rosenfeld Big Event','','2026-01-30 19:03:00','2026-02-01 19:03:00','','','2025-12-30 19:03:18','2025-12-30 19:03:18',1,1,NULL,NULL,NULL,1,NULL,NULL),
(96,'mXc7Ksyo',32,5,'Rosenfeld Practice Test','','2026-01-02 19:04:00','2026-01-02 21:04:00','','','2025-12-30 19:04:15','2025-12-30 19:04:15',1,1,NULL,NULL,NULL,1,NULL,NULL),
(97,'Rz3zwjVv',22,3,'Populace','Standard Populace Meeting','2026-01-14 00:30:00','2026-01-14 02:00:00','219 Student Union, Stillwater, OK 74078, USA','','2026-01-10 02:23:00','2026-01-10 02:22:38',1,1,NULL,36.12118520,-97.06833950,1,NULL,NULL),
(98,'2KkM3DoY',22,3,'Armorer\'s and Leatheworker\'s Meeting','Get together and work on armor and leather workers','2026-01-13 01:30:00','2026-01-13 04:30:00','1520 W. MCMURTRY','','2026-01-10 02:30:17','2026-01-10 02:30:17',1,1,NULL,NULL,NULL,1,NULL,NULL),
(99,'TFEPiwHD',39,2,'Fighting Practice','','2026-01-07 02:31:00','2026-01-07 04:31:00','','','2026-01-10 02:31:56','2026-01-10 02:31:56',1,1,NULL,NULL,NULL,0,NULL,NULL),
(100,'DcDLVREF',22,3,'Armorer\'s and Leatheworker\'s Meeting','Get together and work on armor and leather workers','2026-01-20 01:30:00','2026-01-20 01:30:00','1520 W. MCMURTRY','','2026-01-10 02:35:12','2026-01-10 02:35:12',1,1,NULL,NULL,NULL,1,NULL,NULL),
(101,'UHSDczaE',22,3,'Armorer\'s and Leatheworker\'s Meeting','Get together and work on armor and leather workers','2026-01-27 01:30:00','2026-01-27 01:30:00','1520 W. MCMURTRY','','2026-01-10 02:35:18','2026-01-10 02:35:18',1,1,NULL,NULL,NULL,1,NULL,NULL),
(102,'tSz7NZLo',22,3,'Armorer\'s and Leatheworker\'s Meeting','Get together and work on armor and leather workers','2026-02-03 01:30:00','2026-02-03 01:30:00','1520 W. MCMURTRY','','2026-01-10 02:35:24','2026-01-10 02:35:24',1,1,NULL,NULL,NULL,1,NULL,NULL),
(103,'8DdQTrr7',11,1,'Vindheim Coronet','Vindheim\'s 7th Coronet','2026-01-10 14:00:00','2026-01-11 04:00:00','2720 W McElroy Rd, Stillwater, OK 74075, USA','','2026-01-10 02:39:26','2026-01-10 02:39:26',1,1,NULL,36.13038370,-97.09273770,1,NULL,NULL),
(104,'3FiadaSh',34,1,'War of the Rams Again','Testing creating a historical event','2025-11-22 14:00:00','2025-11-25 14:00:00','Beaumont, TX, USA','','2026-01-10 03:05:32','2026-01-10 03:05:32',1,1,NULL,30.08017400,-94.12655620,1,NULL,NULL),
(105,'JVrip8n6',22,10,'Combined Fighter Practice','We gather at the second pavilion on the road','2026-01-16 01:00:00','2026-01-16 04:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA','','2026-01-13 03:22:20','2026-01-13 03:22:20',1,1,NULL,36.15237930,-97.06778060,1,NULL,NULL),
(106,'xDencSzd',22,10,'Combined Fighter Practice','We gather at the second pavilion on the road','2026-01-23 01:00:00','2026-01-23 04:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA','','2026-01-13 03:22:46','2026-01-13 03:22:46',1,1,NULL,NULL,NULL,1,NULL,NULL),
(107,'UGtSeM3T',22,10,'Combined Fighter Practice','We gather at the second pavilion on the road','2026-01-30 01:00:00','2026-01-30 04:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA','','2026-01-13 03:23:02','2026-01-13 03:23:02',1,1,NULL,NULL,NULL,1,NULL,NULL),
(108,'u3tBeQUu',2,11,'Winter Round TAble','Our Periodic Kingdom Round Table Event','2026-01-17 14:00:00','2026-01-17 23:00:00','Virtual','','2026-01-13 03:28:08','2026-01-13 03:28:08',1,1,NULL,NULL,NULL,1,NULL,NULL),
(109,'jrq9UCFN',22,10,'Combined Fighter Practice','We gather at the second pavilion on the road.  Changed by Iris on 2/1/2026','2026-02-06 01:00:00','2026-02-06 04:30:00','Boomer Lake Dr, Stillwater, OK 74075, USA','','2026-02-01 14:51:38','2026-01-13 04:42:16',1,2878,NULL,NULL,NULL,1,'2026-02-01 14:51:38','Testing.'),
(110,'6odnRdcP',22,3,'Heraldry','','2026-01-21 00:30:00','2026-01-21 02:30:00','','','2026-01-13 04:43:14','2026-01-13 04:43:14',1,1,NULL,NULL,NULL,0,NULL,NULL),
(111,'LCZobPqP',2,1,'Gulf Wars','','2026-03-17 03:45:00','2026-03-22 03:45:00','King\'s Hwy, Mississippi, USA','','2026-01-13 04:45:57','2026-01-13 04:45:57',1,1,NULL,30.91859350,-89.45588310,1,NULL,NULL),
(112,'nPXN38MJ',39,2,'Local FP Test','','2026-01-16 00:20:00','2026-01-16 05:20:00','','','2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(113,'ruTxHH8b',39,2,'FP Test Today','','2026-01-16 08:26:00','2026-01-16 10:26:00','','','2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(114,'Zu2DLiNd',39,2,'Test AMP for Seneschals Practice','','2026-01-19 17:00:00','2026-01-19 19:00:00','','','2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872,NULL,NULL,NULL,1,NULL,NULL),
(115,'dvkhHdha',22,10,'Combined Fighter Practice','We gather at the second pavilion on the road','2026-02-13 01:00:00','2026-02-13 04:00:00','Boomer Lake Dr, Stillwater, OK 74075, USA','','2026-02-01 14:49:49','2026-02-01 14:49:49',1,1,NULL,NULL,NULL,1,NULL,NULL);
/*!40000 ALTER TABLE `gatherings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gatherings_gathering_activities`
--

DROP TABLE IF EXISTS `gatherings_gathering_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gatherings_gathering_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'FK to gatherings table',
  `gathering_activity_id` int(11) NOT NULL COMMENT 'FK to gathering_activities table',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order of activities within a gathering',
  `not_removable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, this activity cannot be removed from this gathering',
  `custom_description` text DEFAULT NULL COMMENT 'Optional custom description that overrides the default activity description for this specific gathering',
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ggact_unique` (`gathering_id`,`gathering_activity_id`),
  KEY `idx_ggact_gathering` (`gathering_id`),
  KEY `idx_ggact_activity` (`gathering_activity_id`),
  KEY `idx_ggact_sort` (`sort_order`),
  KEY `fk_ggact_created_by` (`created_by`),
  KEY `idx_ggact_not_removable` (`not_removable`),
  CONSTRAINT `fk_ggact_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ggact_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_ggact_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gatherings_gathering_activities`
--

LOCK TABLES `gatherings_gathering_activities` WRITE;
/*!40000 ALTER TABLE `gatherings_gathering_activities` DISABLE KEYS */;
INSERT INTO `gatherings_gathering_activities` VALUES
(1,1,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(2,2,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(3,3,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(4,4,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(5,5,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(6,6,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(7,7,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(8,8,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(9,9,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(10,10,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(11,11,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(12,12,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(13,13,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(14,14,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(15,15,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(16,16,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(17,17,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(18,18,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(19,19,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(20,20,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(21,21,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(22,22,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(23,23,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(24,24,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(25,25,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(26,26,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(27,27,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(28,28,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(29,29,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(30,30,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(31,31,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(32,32,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(33,33,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(34,34,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(35,35,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(36,36,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(37,37,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(38,38,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(39,39,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(40,40,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(41,41,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(42,42,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(43,43,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(44,44,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(45,45,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(46,46,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(47,47,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(48,48,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(49,49,1,0,0,NULL,'2025-10-30 21:03:03','2025-10-30 21:03:03',NULL,NULL),
(50,50,2,999,0,NULL,'2025-10-31 19:24:59','2025-10-31 19:24:59',1,1),
(51,51,2,999,0,NULL,'2025-11-02 01:27:39','2025-11-02 01:27:39',1,1),
(52,52,2,999,0,NULL,'2025-11-02 01:28:07','2025-11-02 01:28:07',1,1),
(53,53,2,999,0,NULL,'2025-11-02 01:28:15','2025-11-02 01:28:15',1,1),
(55,55,1,999,0,NULL,'2025-11-02 01:38:21','2025-11-02 01:38:21',1,1),
(56,61,2,999,0,NULL,'2025-11-02 01:58:39','2025-11-02 01:58:39',1,1),
(57,61,1,999,0,NULL,'2025-11-02 01:58:44','2025-11-02 01:58:44',1,1),
(58,54,3,999,0,NULL,'2025-11-02 02:03:04','2025-11-02 02:03:04',1,1),
(59,54,4,999,0,NULL,'2025-11-02 02:03:10','2025-11-02 02:03:10',1,1),
(60,61,3,999,0,NULL,'2025-11-02 02:06:48','2025-11-02 02:06:48',1,1),
(61,61,5,999,0,NULL,'2025-11-02 02:06:53','2025-11-02 02:06:53',1,1),
(62,61,4,999,0,NULL,'2025-11-02 02:06:58','2025-11-02 02:06:58',1,1),
(63,63,1,1,0,NULL,'2025-11-02 16:13:58','2025-11-02 16:13:58',1,1),
(64,63,2,2,0,NULL,'2025-11-02 16:13:58','2025-11-02 16:13:58',1,1),
(65,64,1,1,0,NULL,'2025-11-02 16:17:30','2025-11-02 16:17:30',1,1),
(66,64,2,2,0,NULL,'2025-11-02 16:17:30','2025-11-02 16:17:30',1,1),
(67,65,1,1,1,NULL,'2025-11-02 16:50:28','2025-11-02 16:50:28',1,1),
(68,65,2,2,1,NULL,'2025-11-02 16:50:28','2025-11-02 16:50:28',1,1),
(70,63,3,999,0,'Tournaments, Melees in the Castle','2025-11-02 16:55:22','2025-11-05 00:57:55',1,1),
(72,63,4,999,0,'Tournaments, team, and full melees','2025-11-02 16:55:59','2025-11-05 00:58:29',1,1),
(73,55,4,999,0,'Queen\'s Champion Tournament','2025-11-02 16:57:20','2025-11-02 16:57:20',1,1),
(74,55,2,999,0,NULL,'2025-11-02 17:00:03','2025-11-02 17:00:03',1,1),
(75,66,3,1,0,NULL,'2025-11-06 00:07:53','2025-11-06 00:07:53',1,1),
(76,66,4,2,0,NULL,'2025-11-06 00:07:53','2025-11-06 00:07:53',1,1),
(77,66,5,999,0,NULL,'2025-11-06 00:09:01','2025-11-06 00:09:01',1,1),
(78,67,3,1,0,NULL,'2025-11-06 00:33:16','2025-11-06 00:33:16',1,1),
(79,67,4,2,0,NULL,'2025-11-06 00:33:16','2025-11-06 00:33:16',1,1),
(80,67,5,999,0,NULL,'2025-11-06 00:33:16','2025-11-06 00:33:16',1,1),
(81,68,3,1,0,NULL,'2025-11-06 00:33:21','2025-11-06 00:33:21',1,1),
(82,68,4,2,0,NULL,'2025-11-06 00:33:21','2025-11-06 00:33:21',1,1),
(83,68,5,999,0,NULL,'2025-11-06 00:33:21','2025-11-06 00:33:21',1,1),
(84,69,3,1,0,NULL,'2025-11-06 01:06:29','2025-11-06 01:06:29',1,1),
(85,69,4,2,0,NULL,'2025-11-06 01:06:29','2025-11-06 01:06:29',1,1),
(86,70,3,1,0,NULL,'2025-11-06 01:07:01','2025-11-06 01:07:01',1,1),
(87,70,4,2,0,NULL,'2025-11-06 01:07:01','2025-11-06 01:07:01',1,1),
(90,58,6,999,0,NULL,'2025-11-06 01:12:27','2025-11-06 01:12:27',1,1),
(91,71,6,999,0,NULL,'2025-11-06 01:14:34','2025-11-06 01:14:34',1,1),
(92,72,6,999,0,NULL,'2025-11-06 01:14:44','2025-11-06 01:14:44',1,1),
(93,73,3,1,0,NULL,'2025-11-06 01:36:53','2025-11-06 01:36:53',1,1),
(94,73,4,2,0,NULL,'2025-11-06 01:36:53','2025-11-06 01:36:53',1,1),
(95,74,3,1,0,NULL,'2025-11-06 01:40:42','2025-11-06 01:40:42',1,1),
(98,75,4,2,0,NULL,'2025-11-06 01:50:03','2025-11-06 01:50:03',1,1),
(103,75,10,7,0,NULL,'2025-11-06 01:50:03','2025-11-06 01:50:03',1,1),
(104,76,3,1,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(105,76,4,2,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(106,76,5,3,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(107,76,7,4,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(108,76,8,5,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(109,76,9,6,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(110,76,10,7,0,NULL,'2025-11-06 01:51:10','2025-11-06 01:51:10',1,1),
(111,77,5,999,0,NULL,'2025-11-06 01:54:04','2025-11-06 01:54:04',1,1),
(112,78,7,1,0,NULL,'2025-11-06 01:56:50','2025-11-06 01:56:50',1,1),
(113,78,8,2,0,NULL,'2025-11-06 01:56:50','2025-11-06 01:56:50',1,1),
(116,79,5,3,0,NULL,'2025-11-06 02:00:40','2025-11-06 02:00:40',1,1),
(117,79,7,4,0,NULL,'2025-11-06 02:00:40','2025-11-06 02:00:40',1,1),
(118,79,8,5,0,NULL,'2025-11-06 02:00:40','2025-11-06 02:00:40',1,1),
(121,80,3,1,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(122,80,4,2,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(123,80,5,3,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(124,80,7,4,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(125,80,8,5,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(126,80,9,6,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(127,80,10,7,0,NULL,'2025-11-06 02:07:56','2025-11-06 02:07:56',1,1),
(128,71,3,999,0,NULL,'2025-11-06 21:10:24','2025-11-06 21:10:24',1,1),
(129,81,3,1,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(130,81,4,2,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(132,81,7,4,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(133,81,8,5,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(134,81,9,6,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(135,81,10,7,0,NULL,'2025-11-11 02:56:35','2025-11-11 02:56:35',1,1),
(136,82,3,1,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(137,82,4,2,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(138,82,5,3,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(139,82,7,4,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(140,82,8,5,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(141,82,9,6,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(142,82,10,7,0,NULL,'2025-11-11 02:57:28','2025-11-11 02:57:28',1,1),
(143,83,1,1,1,NULL,'2025-11-18 02:23:25','2025-11-18 02:23:25',1,2872),
(144,83,2,2,1,NULL,'2025-11-18 02:23:25','2025-11-18 02:23:25',1,2872),
(145,83,5,999,0,NULL,'2025-11-18 02:24:44','2025-11-18 02:24:44',1,2872),
(146,84,1,1,1,NULL,'2025-11-18 02:25:35','2025-11-18 02:25:35',1,2872),
(147,84,2,2,1,NULL,'2025-11-18 02:25:35','2025-11-18 02:25:35',1,2872),
(148,84,5,999,0,NULL,'2025-11-18 02:25:35','2025-11-18 02:25:35',1,2872),
(149,85,3,1,0,NULL,'2025-11-18 02:41:54','2025-11-18 02:41:54',1,2872),
(150,85,4,2,0,NULL,'2025-11-18 02:41:54','2025-11-18 02:41:54',1,2872),
(152,85,7,4,0,NULL,'2025-11-18 02:41:54','2025-11-18 02:41:54',1,2872),
(153,85,8,5,0,NULL,'2025-11-18 02:41:54','2025-11-18 02:41:54',1,2872),
(156,86,4,1,0,NULL,'2025-11-18 02:43:52','2025-11-18 02:43:52',1,2872),
(157,86,10,2,0,NULL,'2025-11-18 02:43:52','2025-11-18 02:43:52',1,2872),
(158,87,4,1,0,NULL,'2025-12-04 00:47:59','2025-12-04 00:47:59',1,1),
(159,87,10,2,0,NULL,'2025-12-04 00:47:59','2025-12-04 00:47:59',1,1),
(160,90,3,1,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(161,90,4,2,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(162,90,5,3,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(163,90,7,4,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(164,90,8,5,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(165,90,9,6,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(166,90,10,7,0,NULL,'2025-12-29 08:31:03','2025-12-29 08:31:03',1,2872),
(167,91,3,1,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(168,91,4,2,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(170,91,7,4,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(171,91,8,5,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(172,91,9,6,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(173,91,10,7,0,NULL,'2025-12-29 19:12:40','2025-12-29 19:12:40',1,2872),
(174,91,2,999,0,NULL,'2025-12-29 19:13:18','2025-12-29 19:13:18',1,2872),
(175,92,3,1,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(176,92,4,2,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(178,92,7,4,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(179,92,8,5,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(180,92,9,6,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(181,92,10,7,0,NULL,'2025-12-29 19:19:28','2025-12-29 19:19:28',1,2872),
(182,92,2,999,0,NULL,'2025-12-29 19:19:39','2025-12-29 19:19:39',1,2872),
(183,93,3,1,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(184,93,4,2,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(186,93,7,4,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(187,93,8,5,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(188,93,9,6,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(189,93,10,7,0,NULL,'2025-12-29 19:21:55','2025-12-29 19:21:55',1,2872),
(190,93,2,999,0,NULL,'2025-12-29 19:22:01','2025-12-29 19:22:01',1,2872),
(191,90,1,8,1,NULL,'2025-12-30 18:29:29','2025-12-30 18:29:29',1,2872),
(192,90,2,9,1,NULL,'2025-12-30 18:29:29','2025-12-30 18:29:29',1,2872),
(193,94,1,1,1,NULL,'2025-12-30 18:51:15','2025-12-30 18:51:15',1,2872),
(194,94,2,2,1,NULL,'2025-12-30 18:51:15','2025-12-30 18:51:15',1,2872),
(195,94,11,999,0,NULL,'2025-12-30 18:52:36','2025-12-30 18:52:36',1,2872),
(196,94,3,999,0,NULL,'2025-12-30 18:52:43','2025-12-30 18:52:43',1,2872),
(197,94,4,999,0,NULL,'2025-12-30 18:52:50','2025-12-30 18:52:50',1,2872),
(198,95,1,1,1,NULL,'2025-12-30 19:03:18','2025-12-30 19:03:18',1,1),
(199,95,2,2,1,NULL,'2025-12-30 19:03:18','2025-12-30 19:03:18',1,1),
(200,95,4,999,0,NULL,'2025-12-30 19:03:25','2025-12-30 19:03:25',1,1),
(201,96,4,1,0,NULL,'2025-12-30 19:04:15','2025-12-30 19:04:15',1,1),
(202,96,10,2,0,NULL,'2025-12-30 19:04:15','2025-12-30 19:04:15',1,1),
(203,98,6,999,0,'Making Armor and shaping leather','2026-01-10 02:31:03','2026-01-10 02:31:03',1,1),
(204,99,3,1,0,NULL,'2026-01-10 02:31:56','2026-01-10 02:31:56',1,1),
(205,99,4,2,0,NULL,'2026-01-10 02:31:56','2026-01-10 02:31:56',1,1),
(211,100,6,999,0,NULL,'2026-01-10 02:35:12','2026-01-10 02:35:12',1,1),
(212,101,6,999,0,NULL,'2026-01-10 02:35:18','2026-01-10 02:35:18',1,1),
(213,102,6,999,0,NULL,'2026-01-10 02:35:24','2026-01-10 02:35:24',1,1),
(214,103,1,1,1,NULL,'2026-01-10 02:39:26','2026-01-10 02:39:26',1,1),
(215,103,2,2,1,NULL,'2026-01-10 02:39:26','2026-01-10 02:39:26',1,1),
(216,103,3,999,0,'Coronet List','2026-01-10 02:39:39','2026-01-10 02:40:55',1,1),
(217,103,4,999,0,NULL,'2026-01-10 02:39:51','2026-01-10 02:39:51',1,1),
(218,104,1,1,1,NULL,'2026-01-10 03:05:32','2026-01-10 03:05:32',1,1),
(219,104,2,2,1,NULL,'2026-01-10 03:05:32','2026-01-10 03:05:32',1,1),
(220,104,3,999,0,NULL,'2026-01-10 03:06:17','2026-01-10 03:06:17',1,1),
(221,104,8,999,0,NULL,'2026-01-10 03:06:25','2026-01-10 03:06:25',1,1),
(222,104,4,999,0,NULL,'2026-01-10 03:06:35','2026-01-10 03:06:35',1,1),
(223,104,5,999,0,NULL,'2026-01-10 03:06:44','2026-01-10 03:06:44',1,1),
(224,105,3,1,0,NULL,'2026-01-13 03:22:20','2026-01-13 03:22:20',1,1),
(225,105,4,2,0,NULL,'2026-01-13 03:22:20','2026-01-13 03:22:20',1,1),
(226,106,3,1,0,NULL,'2026-01-13 03:22:46','2026-01-13 03:22:46',1,1),
(227,106,4,2,0,NULL,'2026-01-13 03:22:46','2026-01-13 03:22:46',1,1),
(228,107,3,1,0,NULL,'2026-01-13 03:23:02','2026-01-13 03:23:02',1,1),
(229,107,4,2,0,NULL,'2026-01-13 03:23:02','2026-01-13 03:23:02',1,1),
(230,109,3,1,0,NULL,'2026-01-13 04:42:16','2026-01-13 04:42:16',1,1),
(231,109,4,2,0,NULL,'2026-01-13 04:42:16','2026-01-13 04:42:16',1,1),
(232,111,1,1,1,NULL,'2026-01-13 04:45:57','2026-01-13 04:45:57',1,1),
(233,111,2,2,1,NULL,'2026-01-13 04:45:57','2026-01-13 04:45:57',1,1),
(234,111,3,999,0,NULL,'2026-01-13 04:46:24','2026-01-13 04:46:24',1,1),
(235,111,5,999,0,NULL,'2026-01-13 04:46:30','2026-01-13 04:46:30',1,1),
(236,108,6,999,0,NULL,'2026-01-15 02:18:54','2026-01-15 02:18:54',1,2891),
(237,108,2,999,0,NULL,'2026-01-15 02:19:02','2026-01-15 02:19:02',1,2891),
(238,112,3,1,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(239,112,4,2,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(240,112,5,3,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(241,112,7,4,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(242,112,8,5,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(243,112,9,6,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(244,112,10,7,0,NULL,'2026-01-15 04:21:14','2026-01-15 04:21:14',1,2872),
(245,112,11,999,0,NULL,'2026-01-15 04:21:36','2026-01-15 04:21:36',1,2872),
(246,112,2,999,0,NULL,'2026-01-15 04:21:41','2026-01-15 04:21:41',1,2872),
(247,113,3,1,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(248,113,4,2,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(250,113,7,4,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(251,113,8,5,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(252,113,9,6,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(253,113,10,7,0,NULL,'2026-01-15 04:27:35','2026-01-15 04:27:35',1,2872),
(254,113,2,999,0,NULL,'2026-01-15 04:28:20','2026-01-15 04:28:20',1,2872),
(255,114,3,1,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(256,114,4,2,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(258,114,7,4,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(259,114,8,5,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(260,114,9,6,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(261,114,10,7,0,NULL,'2026-01-17 17:28:41','2026-01-17 17:28:41',1,2872),
(262,114,2,999,0,NULL,'2026-01-17 17:29:23','2026-01-17 17:29:23',1,2872),
(263,115,3,1,0,NULL,'2026-02-01 14:49:49','2026-02-01 14:49:49',1,1),
(264,115,4,2,0,NULL,'2026-02-01 14:49:49','2026-02-01 14:49:49',1,1);
/*!40000 ALTER TABLE `gatherings_gathering_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grid_view_preferences`
--

DROP TABLE IF EXISTS `grid_view_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grid_view_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL COMMENT 'Member owning the preference record',
  `grid_key` varchar(100) NOT NULL COMMENT 'Unique identifier for the grid instance (e.g., Members.index.main)',
  `grid_view_id` int(11) DEFAULT NULL COMMENT 'Preferred view ID; supports user views',
  `grid_view_key` varchar(100) DEFAULT NULL COMMENT 'Preferred system view key (string); supports system views by name',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `modified` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'Audit: member who created the record',
  `modified_by` int(11) DEFAULT NULL COMMENT 'Audit: member who last modified the record',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grid_view_preferences_member_grid` (`member_id`,`grid_key`),
  KEY `idx_grid_view_preferences_view` (`grid_view_id`),
  KEY `idx_grid_view_preferences_grid` (`grid_key`),
  KEY `created_by` (`created_by`),
  KEY `modified_by` (`modified_by`),
  CONSTRAINT `grid_view_preferences_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `grid_view_preferences_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `grid_view_preferences_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `grid_view_preferences_ibfk_4` FOREIGN KEY (`grid_view_id`) REFERENCES `grid_views` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grid_view_preferences`
--

LOCK TABLES `grid_view_preferences` WRITE;
/*!40000 ALTER TABLE `grid_view_preferences` DISABLE KEYS */;
INSERT INTO `grid_view_preferences` VALUES
(4,2880,'Awards.Recommendations.index.main',5,NULL,'2026-01-14 01:51:57','2026-01-14 01:51:57',1,2880);
/*!40000 ALTER TABLE `grid_view_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grid_views`
--

DROP TABLE IF EXISTS `grid_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grid_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grid_key` varchar(100) NOT NULL COMMENT 'Unique identifier for grid instance (e.g., Members.index.main)',
  `member_id` int(11) DEFAULT NULL COMMENT 'Owner of view; NULL for system-wide defaults',
  `name` varchar(100) NOT NULL COMMENT 'User-friendly name for the view',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is the user''s default view for this grid',
  `is_system_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is the system-wide default (member_id must be NULL)',
  `config` text NOT NULL COMMENT 'JSON configuration: filters, sort, columns, pageSize',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `modified` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'Member who created this view',
  `modified_by` int(11) DEFAULT NULL COMMENT 'Member who last modified this view',
  `deleted` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_grid_views_grid_key` (`grid_key`),
  KEY `idx_grid_views_member_grid` (`member_id`,`grid_key`),
  KEY `idx_grid_views_grid_default` (`grid_key`,`is_default`),
  KEY `idx_grid_views_system_default` (`grid_key`,`is_system_default`),
  KEY `idx_grid_views_deleted` (`deleted`),
  KEY `created_by` (`created_by`),
  KEY `modified_by` (`modified_by`),
  CONSTRAINT `grid_views_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `grid_views_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `grid_views_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grid_views`
--

LOCK TABLES `grid_views` WRITE;
/*!40000 ALTER TABLE `grid_views` DISABLE KEYS */;
INSERT INTO `grid_views` VALUES
(1,'Awards.Recommendations.index.main',1,'k in p',0,0,'{\"filters\":[{\"field\":\"status\",\"operator\":\"in\",\"value\":[\"In Progress\"]}],\"sort\":[],\"columns\":[{\"key\":\"created\",\"visible\":true,\"order\":0},{\"key\":\"member_sca_name\",\"visible\":true,\"order\":1},{\"key\":\"op_links\",\"visible\":true,\"order\":2},{\"key\":\"branch_name\",\"visible\":true,\"order\":3},{\"key\":\"domain_name\",\"visible\":true,\"order\":4},{\"key\":\"award_name\",\"visible\":true,\"order\":5},{\"key\":\"reason\",\"visible\":true,\"order\":6},{\"key\":\"notes\",\"visible\":true,\"order\":7},{\"key\":\"state\",\"visible\":true,\"order\":8}],\"pageSize\":25}','2025-12-16 01:52:10','2026-01-21 04:20:28',1,1,'2026-01-21 04:20:28'),
(2,'Gatherings.index.main',2878,'Iris Filter',0,0,'{\"filters\":[{\"field\":\"start_date\",\"operator\":\"dateRange\",\"value\":[\"2026-01-01\",\"2026-01-31\"]}],\"sort\":[],\"columns\":[{\"key\":\"name\",\"visible\":true,\"order\":0},{\"key\":\"branch_id\",\"visible\":true,\"order\":1},{\"key\":\"gathering_type_id\",\"visible\":true,\"order\":2},{\"key\":\"start_date\",\"visible\":true,\"order\":3},{\"key\":\"end_date\",\"visible\":true,\"order\":4}],\"pageSize\":25}','2025-12-27 19:28:13','2025-12-27 19:28:13',1,2878,NULL),
(3,'Gatherings.index.main',2872,'My Custom Filter',0,0,'{\"filters\":[{\"field\":\"start_date\",\"operator\":\"dateRange\",\"value\":[\"2025-12-01\",\"2025-12-31\"]}],\"sort\":[],\"columns\":[{\"key\":\"name\",\"visible\":true,\"order\":0},{\"key\":\"branch_id\",\"visible\":true,\"order\":1},{\"key\":\"gathering_type_id\",\"visible\":true,\"order\":2},{\"key\":\"start_date\",\"visible\":true,\"order\":3},{\"key\":\"end_date\",\"visible\":true,\"order\":4}],\"pageSize\":25}','2025-12-29 19:11:12','2025-12-29 19:11:12',1,2872,NULL),
(4,'Awards.Recommendations.index.main',2880,'steppes filter',0,0,'{\"filters\":[{\"field\":\"status\",\"operator\":\"in\",\"value\":[\"Closed\"]}],\"sort\":[],\"columns\":[{\"key\":\"created\",\"visible\":true,\"order\":0},{\"key\":\"member_sca_name\",\"visible\":true,\"order\":1},{\"key\":\"branch_name\",\"visible\":true,\"order\":2},{\"key\":\"award_name\",\"visible\":true,\"order\":3},{\"key\":\"reason\",\"visible\":true,\"order\":4},{\"key\":\"notes\",\"visible\":true,\"order\":5},{\"key\":\"state\",\"visible\":true,\"order\":6},{\"key\":\"close_reason\",\"visible\":true,\"order\":7},{\"key\":\"assigned_gathering\",\"visible\":true,\"order\":8},{\"key\":\"state_date\",\"visible\":true,\"order\":9},{\"key\":\"given\",\"visible\":true,\"order\":10}],\"pageSize\":25}','2025-12-30 20:58:34','2025-12-30 20:58:34',1,2880,NULL),
(5,'Awards.Recommendations.index.main',2880,'my custom filter',0,0,'{\"filters\":[{\"field\":\"status\",\"operator\":\"in\",\"value\":[\"Closed\"]}],\"sort\":[],\"columns\":[{\"key\":\"created\",\"visible\":true,\"order\":0},{\"key\":\"member_sca_name\",\"visible\":true,\"order\":1},{\"key\":\"branch_name\",\"visible\":true,\"order\":2},{\"key\":\"award_name\",\"visible\":true,\"order\":3},{\"key\":\"reason\",\"visible\":true,\"order\":4},{\"key\":\"notes\",\"visible\":true,\"order\":5},{\"key\":\"state\",\"visible\":true,\"order\":6},{\"key\":\"close_reason\",\"visible\":true,\"order\":7},{\"key\":\"assigned_gathering\",\"visible\":true,\"order\":8},{\"key\":\"state_date\",\"visible\":true,\"order\":9},{\"key\":\"given\",\"visible\":true,\"order\":10}],\"pageSize\":25}','2026-01-12 02:35:53','2026-01-12 02:35:53',1,2880,NULL),
(6,'Awards.Recommendations.index.main',1,'test',0,0,'{\"filters\":[{\"field\":\"branch_type\",\"operator\":\"in\",\"value\":[\"Kingdom\"]}],\"sort\":[],\"columns\":[{\"key\":\"created\",\"visible\":true,\"order\":0},{\"key\":\"member_sca_name\",\"visible\":true,\"order\":1},{\"key\":\"op_links\",\"visible\":true,\"order\":2},{\"key\":\"branch_id\",\"visible\":true,\"order\":3},{\"key\":\"domain_name\",\"visible\":true,\"order\":4},{\"key\":\"award_name\",\"visible\":true,\"order\":5},{\"key\":\"reason\",\"visible\":true,\"order\":6},{\"key\":\"gatherings\",\"visible\":true,\"order\":7},{\"key\":\"notes\",\"visible\":true,\"order\":8},{\"key\":\"status\",\"visible\":true,\"order\":9},{\"key\":\"state\",\"visible\":true,\"order\":10},{\"key\":\"close_reason\",\"visible\":true,\"order\":11},{\"key\":\"assigned_gathering\",\"visible\":true,\"order\":12}],\"pageSize\":25}','2026-01-21 04:21:07','2026-01-29 00:27:45',1,1,'2026-01-29 00:27:45'),
(7,'Awards.Recommendations.index.main',1,'test2',0,0,'{\"filters\":[{\"field\":\"branch_type\",\"operator\":\"in\",\"value\":[\"Kingdom\"]},{\"field\":\"gatherings\",\"operator\":\"in\",\"value\":[\"103\"]}],\"sort\":[],\"columns\":[{\"key\":\"created\",\"visible\":true,\"order\":0},{\"key\":\"member_sca_name\",\"visible\":true,\"order\":1},{\"key\":\"op_links\",\"visible\":true,\"order\":2},{\"key\":\"branch_id\",\"visible\":true,\"order\":3},{\"key\":\"domain_name\",\"visible\":true,\"order\":4},{\"key\":\"award_name\",\"visible\":true,\"order\":5},{\"key\":\"reason\",\"visible\":true,\"order\":6},{\"key\":\"gatherings\",\"visible\":true,\"order\":7},{\"key\":\"notes\",\"visible\":true,\"order\":8},{\"key\":\"status\",\"visible\":true,\"order\":9},{\"key\":\"state\",\"visible\":true,\"order\":10},{\"key\":\"close_reason\",\"visible\":true,\"order\":11},{\"key\":\"assigned_gathering\",\"visible\":true,\"order\":12}],\"pageSize\":25}','2026-01-21 04:21:32','2026-01-21 04:21:32',1,1,NULL);
/*!40000 ALTER TABLE `grid_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `impersonation_action_logs`
--

DROP TABLE IF EXISTS `impersonation_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `impersonation_action_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `impersonator_id` int(11) NOT NULL,
  `impersonated_member_id` int(11) NOT NULL,
  `operation` varchar(20) NOT NULL,
  `table_name` varchar(191) NOT NULL,
  `entity_primary_key` varchar(191) NOT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` varchar(512) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_impersonation_logs_created` (`created`),
  KEY `idx_impersonation_logs_table` (`table_name`),
  KEY `fk_impersonation_logs_impersonator` (`impersonator_id`),
  KEY `fk_impersonation_logs_impersonated_member` (`impersonated_member_id`),
  CONSTRAINT `fk_impersonation_logs_impersonated_member` FOREIGN KEY (`impersonated_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_impersonation_logs_impersonator` FOREIGN KEY (`impersonator_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `impersonation_action_logs`
--

LOCK TABLES `impersonation_action_logs` WRITE;
/*!40000 ALTER TABLE `impersonation_action_logs` DISABLE KEYS */;
INSERT INTO `impersonation_action_logs` VALUES
(1,1,2871,'create','impersonation_session_logs','1','POST','/members/impersonate/2871','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-16 02:07:50'),
(2,1,2878,'create','impersonation_session_logs','3','POST','/members/impersonate/2878','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-16 02:14:32'),
(3,1,2886,'create','impersonation_session_logs','5','POST','/members/impersonate/2886','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:22:26'),
(4,1,2875,'create','impersonation_session_logs','7','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:30:48'),
(5,1,2875,'create','impersonation_session_logs','9','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:31:24'),
(6,1,2875,'create','app_settings','67','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:04'),
(7,1,2875,'create','officers_officers','963','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:05'),
(8,1,2875,'create','officers_officers','963','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:05'),
(9,1,2875,'create','officers_officers','963','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:05'),
(10,1,2875,'create','warrant_rosters','424','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:05'),
(11,1,2875,'create','queued_jobs','797','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:05'),
(12,1,2875,'create','app_settings','67','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(13,1,2875,'create','officers_officers','964','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(14,1,2875,'create','officers_officers','964','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(15,1,2875,'create','officers_officers','964','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(16,1,2875,'create','warrant_rosters','425','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(17,1,2875,'create','queued_jobs','798','POST','/officers/officers/assign','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:17'),
(18,1,2886,'create','impersonation_session_logs','11','POST','/members/impersonate/2886','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:33:52'),
(19,1,2878,'create','impersonation_session_logs','13','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:34:27'),
(20,1,2878,'create','activities_authorizations','9321','POST','/activities/authorizations/add','136.62.48.154','{\"alias\":\"Authorizations\",\"primaryKeyField\":\"id\"}','2025-12-27 20:34:45'),
(21,1,2878,'create','activities_authorization_approvals','9316','POST','/activities/authorizations/add','136.62.48.154','{\"alias\":\"AuthorizationApprovals\",\"primaryKeyField\":\"id\"}','2025-12-27 20:34:45'),
(22,1,2886,'create','impersonation_session_logs','15','POST','/members/impersonate/2886','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:34:56'),
(23,1,2886,'create','activities_authorization_approvals','9316','POST','/activities/authorization-approvals/approve/9316','136.62.48.154','{\"alias\":\"AuthorizationApprovals\",\"primaryKeyField\":\"id\"}','2025-12-27 20:35:15'),
(24,1,2886,'create','activities_authorizations','9321','POST','/activities/authorization-approvals/approve/9316','136.62.48.154','{\"alias\":\"Authorizations\",\"primaryKeyField\":\"id\"}','2025-12-27 20:35:15'),
(25,1,2886,'create','activities_authorizations','9321','POST','/activities/authorization-approvals/approve/9316','136.62.48.154','{\"alias\":\"Authorizations\",\"primaryKeyField\":\"id\"}','2025-12-27 20:35:15'),
(26,1,2878,'create','impersonation_session_logs','17','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:35:38'),
(27,1,2886,'create','impersonation_session_logs','19','POST','/members/impersonate/2886','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:36:30'),
(28,1,2886,'update','members','2886','PUT','/members/partial-edit/2886','136.62.48.154','{\"alias\":\"Members\",\"primaryKeyField\":\"id\"}','2025-12-27 20:36:42'),
(29,1,2872,'create','impersonation_session_logs','21','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:38:10'),
(30,1,2872,'create','app_settings','67','POST','/officers/officers/release','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-27 20:39:35'),
(31,1,2872,'create','officers_officers','962','POST','/officers/officers/release','136.62.48.154','{\"alias\":\"Officers\",\"primaryKeyField\":\"id\"}','2025-12-27 20:39:35'),
(32,1,2872,'create','queued_jobs','799','POST','/officers/officers/release','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:39:35'),
(33,1,2887,'create','impersonation_session_logs','22','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:48:57'),
(34,1,2887,'create','impersonation_session_logs','24','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:51:41'),
(35,1,2886,'create','impersonation_session_logs','26','POST','/members/impersonate/2886','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:53:19'),
(36,1,2887,'create','impersonation_session_logs','28','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:54:12'),
(37,1,2874,'create','impersonation_session_logs','30','POST','/members/impersonate/2874','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:55:51'),
(38,1,2876,'create','impersonation_session_logs','32','POST','/members/impersonate/2876','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 20:57:29'),
(39,1,2876,'update','members','2876','PUT','/members/partial-edit/2876','136.62.48.154','{\"alias\":\"Members\",\"primaryKeyField\":\"id\"}','2025-12-27 20:58:46'),
(40,1,2883,'create','impersonation_session_logs','34','POST','/members/impersonate/2883','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 21:01:16'),
(41,1,2875,'create','impersonation_session_logs','36','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 21:03:14'),
(42,1,2877,'create','impersonation_session_logs','38','POST','/members/impersonate/2877','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 21:05:16'),
(43,1,2877,'create','impersonation_session_logs','40','POST','/members/impersonate/2877','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 21:05:59'),
(44,1,2876,'create','impersonation_session_logs','42','POST','/members/impersonate/2876','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-27 21:06:38'),
(45,1,2874,'create','impersonation_session_logs','43','POST','/members/impersonate/2874','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-28 21:18:24'),
(46,1,2873,'create','impersonation_session_logs','45','POST','/members/impersonate/2873','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-28 21:20:29'),
(47,1,2875,'create','impersonation_session_logs','47','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-28 21:21:29'),
(48,1,2883,'create','impersonation_session_logs','49','POST','/members/impersonate/2883','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-28 21:27:21'),
(49,1,2872,'create','impersonation_session_logs','51','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-28 23:35:10'),
(50,1,2872,'create','impersonation_session_logs','53','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 08:03:34'),
(51,1,2888,'create','impersonation_session_logs','55','POST','/members/impersonate/2888','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 08:25:58'),
(52,1,2872,'create','impersonation_session_logs','57','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 08:29:30'),
(53,1,2872,'create','impersonation_session_logs','58','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 08:29:51'),
(54,1,2872,'create','impersonation_session_logs','60','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 08:34:04'),
(55,1,2872,'create','impersonation_session_logs','62','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 18:29:16'),
(56,1,2872,'create','impersonation_session_logs','63','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 19:27:28'),
(57,1,2878,'create','impersonation_session_logs','64','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 20:13:36'),
(58,1,2878,'create','impersonation_session_logs','65','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-29 20:18:02'),
(59,1,2880,'create','impersonation_session_logs','66','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:03:15'),
(60,1,2881,'create','impersonation_session_logs','68','POST','/members/impersonate/2881','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:44'),
(61,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/430','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:53'),
(62,1,2881,'create','warrant_roster_approvals','617','POST','/warrant-rosters/approve/430','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:53'),
(63,1,2881,'create','queued_jobs','817','POST','/warrant-rosters/approve/430','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:53'),
(64,1,2881,'create','warrant_rosters','430','POST','/warrant-rosters/approve/430','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:53'),
(65,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/429','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:57'),
(66,1,2881,'create','warrant_roster_approvals','618','POST','/warrant-rosters/approve/429','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:57'),
(67,1,2881,'create','queued_jobs','818','POST','/warrant-rosters/approve/429','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:57'),
(68,1,2881,'create','warrant_rosters','429','POST','/warrant-rosters/approve/429','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:23:57'),
(69,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/425','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:04'),
(70,1,2881,'create','warrant_roster_approvals','619','POST','/warrant-rosters/approve/425','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:04'),
(71,1,2881,'create','queued_jobs','819','POST','/warrant-rosters/approve/425','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:04'),
(72,1,2881,'create','warrant_rosters','425','POST','/warrant-rosters/approve/425','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:04'),
(73,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/424','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:11'),
(74,1,2881,'create','warrant_roster_approvals','620','POST','/warrant-rosters/approve/424','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:11'),
(75,1,2881,'create','queued_jobs','820','POST','/warrant-rosters/approve/424','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:11'),
(76,1,2881,'create','warrant_rosters','424','POST','/warrant-rosters/approve/424','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:11'),
(77,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/423','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:15'),
(78,1,2881,'create','warrant_roster_approvals','621','POST','/warrant-rosters/approve/423','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:15'),
(79,1,2881,'create','warrant_rosters','423','POST','/warrant-rosters/approve/423','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:15'),
(80,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/426','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:21'),
(81,1,2881,'create','warrant_roster_approvals','622','POST','/warrant-rosters/approve/426','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:21'),
(82,1,2881,'create','queued_jobs','821','POST','/warrant-rosters/approve/426','136.62.48.154','{\"alias\":\"QueuedJobs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:21'),
(83,1,2881,'create','warrant_rosters','426','POST','/warrant-rosters/approve/426','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:21'),
(84,1,2881,'create','app_settings','67','POST','/warrant-rosters/approve/427','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:25'),
(85,1,2881,'create','warrant_roster_approvals','623','POST','/warrant-rosters/approve/427','136.62.48.154','{\"alias\":\"WarrantRosterApprovals\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:25'),
(86,1,2881,'create','warrant_rosters','427','POST','/warrant-rosters/approve/427','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:25'),
(87,1,2880,'create','impersonation_session_logs','70','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:24:41'),
(88,1,2880,'create','awards_recommendations_states_logs','1176','POST','/awards/recommendations/add','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:25:09'),
(89,1,2880,'create','awards_recommendations_states_logs','1177','POST','/awards/recommendations/add','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:25:27'),
(90,1,2880,'create','awards_recommendations_states_logs','1178','POST','/awards/recommendations/add','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:25:49'),
(91,1,2880,'create','awards_recommendations_states_logs','1179','POST','/awards/recommendations/edit/590','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:26:32'),
(92,1,2880,'create','awards_recommendations_states_logs','1180','POST','/awards/recommendations/add','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:27:14'),
(93,1,2880,'create','awards_recommendations_states_logs','1181','POST','/awards/recommendations/edit/591','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:27:51'),
(94,1,2881,'create','impersonation_session_logs','72','POST','/members/impersonate/2881','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:28:09'),
(95,1,2872,'create','impersonation_session_logs','74','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:28:48'),
(96,1,2880,'create','impersonation_session_logs','76','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:29:30'),
(97,1,2880,'create','awards_recommendations_states_logs','1182','POST','/awards/recommendations/add','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:30:31'),
(98,1,2880,'create','impersonation_session_logs','78','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:37:30'),
(99,1,2872,'create','impersonation_session_logs','80','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:48:20'),
(100,1,2872,'create','impersonation_session_logs','81','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:48:46'),
(101,1,2880,'create','impersonation_session_logs','82','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:53:49'),
(102,1,2880,'create','awards_recommendations_states_logs','1183','POST','/awards/recommendations/edit/588','136.62.48.154','{\"alias\":\"RecommendationsStatesLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 18:54:11'),
(103,1,2887,'create','impersonation_session_logs','84','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 19:00:47'),
(104,1,2887,'create','impersonation_session_logs','86','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 19:03:15'),
(105,1,2887,'create','impersonation_session_logs','88','POST','/members/impersonate/2887','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 19:04:06'),
(106,1,2880,'create','impersonation_session_logs','90','POST','/members/impersonate/2880','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2025-12-30 19:39:55'),
(107,1,2878,'create','impersonation_session_logs','91','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-01 18:29:49'),
(108,1,2872,'create','impersonation_session_logs','92','POST','/members/impersonate/2872','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 02:56:38'),
(109,1,2871,'create','impersonation_session_logs','94','POST','/members/impersonate/2871','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 02:57:09'),
(110,1,2878,'create','impersonation_session_logs','96','POST','/members/impersonate/2878','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:20:14'),
(111,1,2876,'create','impersonation_session_logs','98','POST','/members/impersonate/2876','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:21:05'),
(112,1,2881,'create','impersonation_session_logs','100','POST','/members/impersonate/2881','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:21:40'),
(113,1,2889,'create','impersonation_session_logs','102','POST','/members/impersonate/2889','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:24:27'),
(114,1,2889,'create','impersonation_session_logs','104','POST','/members/impersonate/2889','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:25:32'),
(115,1,2885,'create','impersonation_session_logs','106','POST','/members/impersonate/2885','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:27:07'),
(116,1,2875,'create','impersonation_session_logs','108','POST','/members/impersonate/2875','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:28:13'),
(117,1,2889,'create','impersonation_session_logs','110','POST','/members/impersonate/2889','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-10 03:29:52'),
(118,1,2889,'create','impersonation_session_logs','112','POST','/members/impersonate/2889','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:00:02'),
(119,1,2889,'create','impersonation_session_logs','114','POST','/members/impersonate/2889','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:03:57'),
(120,1,2888,'create','impersonation_session_logs','116','POST','/members/impersonate/2888','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:05:03'),
(121,1,2884,'create','impersonation_session_logs','118','POST','/members/impersonate/2884','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:05:34'),
(122,1,2890,'create','impersonation_session_logs','120','POST','/members/impersonate/2890','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:10:19'),
(123,1,2875,'create','impersonation_session_logs','122','POST','/members/impersonate/2875','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:10:46'),
(124,1,2872,'create','impersonation_session_logs','124','POST','/members/impersonate/2872','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:33:47'),
(125,1,2884,'create','impersonation_session_logs','126','POST','/members/impersonate/2884','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:53:27'),
(126,1,2872,'create','impersonation_session_logs','128','POST','/members/impersonate/2872','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:54:03'),
(127,1,2880,'create','impersonation_session_logs','130','POST','/members/impersonate/2880','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:54:47'),
(128,1,2884,'create','impersonation_session_logs','132','POST','/members/impersonate/2884','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 03:55:21'),
(129,1,2872,'create','impersonation_session_logs','134','POST','/members/impersonate/2872','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-13 04:39:21'),
(130,1,2875,'create','impersonation_session_logs','136','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-14 21:55:53'),
(131,1,2875,'create','impersonation_session_logs','137','POST','/members/impersonate/2875','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-14 21:58:43'),
(132,1,2875,'create','app_settings','67','POST','/officers/rosters/create-roster','136.62.48.154','{\"alias\":\"AppSettings\",\"primaryKeyField\":\"id\"}','2026-01-14 22:20:37'),
(133,1,2875,'create','warrant_rosters','434','POST','/officers/rosters/create-roster','136.62.48.154','{\"alias\":\"WarrantRosters\",\"primaryKeyField\":\"id\"}','2026-01-14 22:20:37'),
(134,1,2891,'create','impersonation_session_logs','138','POST','/members/impersonate/2891','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-15 01:56:42'),
(135,1,2891,'create','impersonation_session_logs','140','POST','/members/impersonate/2891','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-15 02:00:11'),
(136,1,2891,'create','impersonation_session_logs','142','POST','/members/impersonate/2891','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-15 02:14:51'),
(137,1,2891,'update','notes','804','PUT','/waivers/gathering-waivers/change-type-activities/71','23.127.169.28','{\"alias\":\"AuditNotes\",\"primaryKeyField\":\"id\"}','2026-01-15 02:16:08'),
(138,1,2872,'create','impersonation_session_logs','143','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-15 04:20:13'),
(139,1,2891,'create','impersonation_session_logs','144','POST','/members/impersonate/2891','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-16 00:04:41'),
(140,1,2891,'update','notes','805','PUT','/waivers/gathering-waivers/change-type-activities/69','23.127.169.28','{\"alias\":\"AuditNotes\",\"primaryKeyField\":\"id\"}','2026-01-16 00:05:27'),
(141,1,2891,'create','impersonation_session_logs','146','POST','/members/impersonate/2891','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-16 00:15:27'),
(142,1,2874,'create','impersonation_session_logs','148','POST','/members/impersonate/2874','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-16 17:13:18'),
(143,1,2876,'create','impersonation_session_logs','150','POST','/members/impersonate/2876','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-16 17:16:21'),
(144,1,2872,'create','impersonation_session_logs','151','POST','/members/impersonate/2872','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-16 17:35:35'),
(145,1,2872,'create','impersonation_session_logs','152','POST','/members/impersonate/2872','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-17 16:58:01'),
(146,1,2878,'create','impersonation_session_logs','153','POST','/members/impersonate/2878','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-17 20:02:29'),
(147,1,2878,'create','impersonation_session_logs','154','POST','/members/impersonate/2878','23.127.169.28','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-01-29 00:53:34'),
(148,1,2878,'create','impersonation_session_logs','156','POST','/members/impersonate/2878','104.151.144.132','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-02-01 14:50:20'),
(149,1,2879,'create','impersonation_session_logs','158','POST','/members/impersonate/2879','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-02-04 18:01:10'),
(150,1,2879,'create','impersonation_session_logs','160','POST','/members/impersonate/2879','136.62.48.154','{\"alias\":\"ImpersonationSessionLogs\",\"primaryKeyField\":\"id\"}','2026-02-04 18:02:48');
/*!40000 ALTER TABLE `impersonation_action_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `impersonation_session_logs`
--

DROP TABLE IF EXISTS `impersonation_session_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `impersonation_session_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `impersonator_id` int(11) NOT NULL,
  `impersonated_member_id` int(11) NOT NULL,
  `event` varchar(16) NOT NULL,
  `request_url` varchar(512) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_impersonation_session_created` (`created`),
  KEY `fk_impersonation_session_impersonator` (`impersonator_id`),
  KEY `fk_impersonation_session_impersonated_member` (`impersonated_member_id`),
  CONSTRAINT `fk_impersonation_session_impersonated_member` FOREIGN KEY (`impersonated_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_impersonation_session_impersonator` FOREIGN KEY (`impersonator_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `impersonation_session_logs`
--

LOCK TABLES `impersonation_session_logs` WRITE;
/*!40000 ALTER TABLE `impersonation_session_logs` DISABLE KEYS */;
INSERT INTO `impersonation_session_logs` VALUES
(1,1,2871,'start','/members/impersonate/2871','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2025-12-16 02:07:50'),
(2,1,2871,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2025-12-16 02:08:20'),
(3,1,2878,'start','/members/impersonate/2878','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2025-12-16 02:14:32'),
(4,1,2878,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2025-12-16 02:15:05'),
(5,1,2886,'start','/members/impersonate/2886','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:22:26'),
(6,1,2886,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:23:38'),
(7,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:30:48'),
(8,1,2875,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:31:21'),
(9,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:31:24'),
(10,1,2875,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:33:36'),
(11,1,2886,'start','/members/impersonate/2886','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:33:52'),
(12,1,2886,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:34:20'),
(13,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:34:27'),
(14,1,2878,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:34:49'),
(15,1,2886,'start','/members/impersonate/2886','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:34:56'),
(16,1,2886,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:35:32'),
(17,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:35:38'),
(18,1,2878,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:36:12'),
(19,1,2886,'start','/members/impersonate/2886','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:36:30'),
(20,1,2886,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:38:03'),
(21,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:38:09'),
(22,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:48:57'),
(23,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:49:04'),
(24,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:51:41'),
(25,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:53:04'),
(26,1,2886,'start','/members/impersonate/2886','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:53:19'),
(27,1,2886,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:53:55'),
(28,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:54:12'),
(29,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:54:21'),
(30,1,2874,'start','/members/impersonate/2874','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:55:51'),
(31,1,2874,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:57:16'),
(32,1,2876,'start','/members/impersonate/2876','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 20:57:29'),
(33,1,2876,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:00:56'),
(34,1,2883,'start','/members/impersonate/2883','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:01:16'),
(35,1,2883,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:03:07'),
(36,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:03:14'),
(37,1,2875,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:05:04'),
(38,1,2877,'start','/members/impersonate/2877','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:05:16'),
(39,1,2877,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:05:20'),
(40,1,2877,'start','/members/impersonate/2877','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:05:59'),
(41,1,2877,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:06:30'),
(42,1,2876,'start','/members/impersonate/2876','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-27 21:06:38'),
(43,1,2874,'start','/members/impersonate/2874','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:18:24'),
(44,1,2874,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:20:19'),
(45,1,2873,'start','/members/impersonate/2873','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:20:29'),
(46,1,2873,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:21:16'),
(47,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:21:29'),
(48,1,2875,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:26:50'),
(49,1,2883,'start','/members/impersonate/2883','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:27:21'),
(50,1,2883,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 21:27:27'),
(51,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 23:35:10'),
(52,1,2872,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-28 23:44:42'),
(53,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:03:34'),
(54,1,2872,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:20:15'),
(55,1,2888,'start','/members/impersonate/2888','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:25:58'),
(56,1,2888,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:29:06'),
(57,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:29:30'),
(58,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:29:51'),
(59,1,2872,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:33:48'),
(60,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:34:04'),
(61,1,2872,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 08:34:50'),
(62,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 18:29:16'),
(63,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 19:27:28'),
(64,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 20:13:36'),
(65,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-29 20:18:02'),
(66,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:03:15'),
(67,1,2880,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:21:30'),
(68,1,2881,'start','/members/impersonate/2881','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:23:44'),
(69,1,2881,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:24:32'),
(70,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:24:41'),
(71,1,2880,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:28:02'),
(72,1,2881,'start','/members/impersonate/2881','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:28:09'),
(73,1,2881,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:28:33'),
(74,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:28:48'),
(75,1,2872,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:29:23'),
(76,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:29:30'),
(77,1,2880,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:34:29'),
(78,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:37:30'),
(79,1,2880,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:39:19'),
(80,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:48:20'),
(81,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:48:46'),
(82,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 18:53:49'),
(83,1,2880,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:00:25'),
(84,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:00:47'),
(85,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:00:58'),
(86,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:03:15'),
(87,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:03:21'),
(88,1,2887,'start','/members/impersonate/2887','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:04:06'),
(89,1,2887,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:05:30'),
(90,1,2880,'start','/members/impersonate/2880','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2025-12-30 19:39:55'),
(91,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-01 18:29:49'),
(92,1,2872,'start','/members/impersonate/2872','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 02:56:38'),
(93,1,2872,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 02:56:49'),
(94,1,2871,'start','/members/impersonate/2871','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 02:57:09'),
(95,1,2871,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 02:59:58'),
(96,1,2878,'start','/members/impersonate/2878','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:20:14'),
(97,1,2878,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:20:47'),
(98,1,2876,'start','/members/impersonate/2876','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:21:05'),
(99,1,2876,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:21:32'),
(100,1,2881,'start','/members/impersonate/2881','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:21:40'),
(101,1,2881,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:22:03'),
(102,1,2889,'start','/members/impersonate/2889','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:24:27'),
(103,1,2889,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:24:35'),
(104,1,2889,'start','/members/impersonate/2889','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:25:32'),
(105,1,2889,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:26:43'),
(106,1,2885,'start','/members/impersonate/2885','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:27:07'),
(107,1,2885,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:27:27'),
(108,1,2875,'start','/members/impersonate/2875','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:28:13'),
(109,1,2875,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:29:37'),
(110,1,2889,'start','/members/impersonate/2889','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:29:52'),
(111,1,2889,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-10 03:32:44'),
(112,1,2889,'start','/members/impersonate/2889','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:00:02'),
(113,1,2889,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:00:16'),
(114,1,2889,'start','/members/impersonate/2889','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:03:57'),
(115,1,2889,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:04:45'),
(116,1,2888,'start','/members/impersonate/2888','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:05:03'),
(117,1,2888,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:05:18'),
(118,1,2884,'start','/members/impersonate/2884','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:05:34'),
(119,1,2884,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:05:41'),
(120,1,2890,'start','/members/impersonate/2890','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:10:19'),
(121,1,2890,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:10:25'),
(122,1,2875,'start','/members/impersonate/2875','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:10:46'),
(123,1,2875,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:10:52'),
(124,1,2872,'start','/members/impersonate/2872','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:33:47'),
(125,1,2872,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:35:21'),
(126,1,2884,'start','/members/impersonate/2884','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:53:27'),
(127,1,2884,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:53:45'),
(128,1,2872,'start','/members/impersonate/2872','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:54:03'),
(129,1,2872,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:54:27'),
(130,1,2880,'start','/members/impersonate/2880','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:54:47'),
(131,1,2880,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:54:54'),
(132,1,2884,'start','/members/impersonate/2884','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:55:21'),
(133,1,2884,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 03:55:42'),
(134,1,2872,'start','/members/impersonate/2872','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 04:39:21'),
(135,1,2872,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-13 04:40:18'),
(136,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-14 21:55:53'),
(137,1,2875,'start','/members/impersonate/2875','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-14 21:58:43'),
(138,1,2891,'start','/members/impersonate/2891','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-15 01:56:42'),
(139,1,2891,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-15 01:57:05'),
(140,1,2891,'start','/members/impersonate/2891','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-15 02:00:11'),
(141,1,2891,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-15 02:14:28'),
(142,1,2891,'start','/members/impersonate/2891','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-15 02:14:51'),
(143,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-15 04:20:13'),
(144,1,2891,'start','/members/impersonate/2891','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 00:04:41'),
(145,1,2891,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 00:15:07'),
(146,1,2891,'start','/members/impersonate/2891','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 00:15:27'),
(147,1,2891,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 00:15:46'),
(148,1,2874,'start','/members/impersonate/2874','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 17:13:18'),
(149,1,2874,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 17:13:29'),
(150,1,2876,'start','/members/impersonate/2876','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 17:16:21'),
(151,1,2872,'start','/members/impersonate/2872','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0','2026-01-16 17:35:34'),
(152,1,2872,'start','/members/impersonate/2872','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-17 16:58:01'),
(153,1,2878,'start','/members/impersonate/2878','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-01-17 20:02:29'),
(154,1,2878,'start','/members/impersonate/2878','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0','2026-01-29 00:53:34'),
(155,1,2878,'stop','/members/stop-impersonating','23.127.169.28','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0','2026-01-29 00:54:39'),
(156,1,2878,'start','/members/impersonate/2878','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-02-01 14:50:20'),
(157,1,2878,'stop','/members/stop-impersonating','104.151.144.132','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36','2026-02-01 17:28:20'),
(158,1,2879,'start','/members/impersonate/2879','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-04 18:01:10'),
(159,1,2879,'stop','/members/stop-impersonating','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-04 18:01:23'),
(160,1,2879,'start','/members/impersonate/2879','136.62.48.154','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36','2026-02-04 18:02:48');
/*!40000 ALTER TABLE `impersonation_session_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_roles`
--

DROP TABLE IF EXISTS `member_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `expires_on` datetime DEFAULT NULL,
  `start_on` datetime NOT NULL DEFAULT current_timestamp(),
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `approver_id` int(11) NOT NULL,
  `revoker_id` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `role_id` (`role_id`),
  KEY `approver_id` (`approver_id`),
  KEY `start_on` (`start_on`),
  KEY `expires_on` (`expires_on`),
  KEY `granting_id` (`entity_id`),
  KEY `granting_model` (`entity_type`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `member_roles_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_roles_ibfk_3` FOREIGN KEY (`approver_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_roles_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=396 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_roles`
--

LOCK TABLES `member_roles` WRITE;
/*!40000 ALTER TABLE `member_roles` DISABLE KEYS */;
INSERT INTO `member_roles` VALUES
(1,1,1,NULL,'2024-05-30 01:22:55','Direct Grant',NULL,1,NULL,NULL,'2024-09-29 15:47:03',1,NULL,NULL),
(362,2875,1116,'2025-08-30 21:10:49','2025-06-22 19:38:58','Officers.Officers',928,1,1073,'2025-08-30 21:10:50','2025-06-22 19:38:59',1,1073,2),
(363,2874,1118,'2025-08-30 21:10:02','2025-06-22 20:12:55','Officers.Officers',930,1,1073,'2025-08-30 21:10:03','2025-06-22 20:12:55',1,1073,12),
(364,2874,1118,'2027-06-22 20:35:28','2025-06-22 20:35:28','Officers.Officers',933,1,NULL,'2025-06-22 20:35:28','2025-06-22 20:35:28',1,2875,13),
(365,2879,200,'2026-01-02 21:16:11','2025-07-02 21:16:11','Officers.Officers',935,1,NULL,'2025-07-02 21:16:11','2025-07-02 21:16:11',1,1073,11),
(366,2880,1117,NULL,'2025-07-02 21:17:24','Officers.Officers',936,1,NULL,'2025-07-02 21:17:24','2025-07-02 21:17:24',1,1073,27),
(367,2871,1117,'2025-08-30 21:06:28','2025-07-02 21:17:46','Officers.Officers',937,1,1073,'2025-08-30 21:06:29','2025-07-02 21:17:46',1,1073,27),
(368,2881,100,'2026-07-02 21:28:40','2025-07-02 21:28:40','Officers.Officers',938,1,NULL,'2025-07-02 21:28:40','2025-07-02 21:28:40',1,1073,2),
(369,2880,1117,NULL,'2025-07-09 21:57:17','Officers.Officers',939,1,NULL,'2025-07-09 21:57:17','2025-07-09 21:57:17',1,1073,28),
(370,2874,1117,'2025-12-27 20:55:59','2025-07-09 21:59:26','Officers.Officers',940,1,1,'2025-12-27 20:56:00','2025-07-09 21:59:26',1,1,33),
(371,2874,1117,'2025-12-27 20:55:53','2025-07-09 21:59:47','Officers.Officers',941,1,1,'2025-12-27 20:55:54','2025-07-09 21:59:47',1,1,38),
(372,2882,1109,'2025-08-30 21:12:11','2025-08-07 21:10:56','Officers.Officers',943,1,1073,'2025-08-30 21:12:12','2025-08-07 21:10:56',1,1073,2),
(374,2872,1118,'2025-11-11 21:18:21','2025-08-30 21:08:09','Officers.Officers',949,1,1,'2025-11-11 21:18:22','2025-08-30 21:08:09',1,1,39),
(375,2873,1118,'2027-08-30 21:09:16','2025-08-30 21:09:16','Officers.Officers',950,1,NULL,'2025-08-30 21:09:16','2025-08-30 21:09:16',1,1073,12),
(376,2875,1116,'2027-08-30 21:11:04','2025-08-30 21:11:04','Officers.Officers',952,1,NULL,'2025-08-30 21:11:04','2025-08-30 21:11:04',1,1073,2),
(377,2876,1109,'2027-08-30 21:12:12','2025-08-30 21:12:12','Officers.Officers',953,1,NULL,'2025-08-30 21:12:12','2025-08-30 21:12:12',1,1073,2),
(378,2882,1117,NULL,'2025-08-30 21:15:22','Officers.Officers',954,1,NULL,'2025-08-30 21:15:22','2025-08-30 21:15:22',1,1073,33),
(379,2882,1117,NULL,'2025-08-30 21:15:45','Officers.Officers',955,1,NULL,'2025-08-30 21:15:45','2025-08-30 21:15:45',1,1073,38),
(380,1,1001,'2029-09-12 02:51:12','2025-09-12 02:51:12','Officers.Officers',958,1,NULL,'2025-09-12 02:51:12','2025-09-12 02:51:12',1,1,2),
(381,2872,1119,'2027-08-30 21:08:09','2025-11-11 21:18:22','Officers.Officers',949,1,NULL,'2025-11-11 21:18:22','2025-11-11 21:18:22',1,1,39),
(384,2880,1117,NULL,'2025-12-04 00:38:29','Officers.Officers',961,1,NULL,'2025-12-04 00:38:29','2025-12-04 00:38:29',1,1,39),
(385,2872,1116,'2025-12-27 20:39:51','2025-12-16 01:54:50','Officers.Officers',962,1,2872,'2025-12-27 20:39:52','2025-12-16 01:54:50',1,2872,2),
(386,2886,1012,'2029-12-27 20:33:21','2025-12-27 20:33:21','Officers.Officers',963,1,NULL,'2025-12-27 20:33:21','2025-12-27 20:33:21',1,2875,2),
(387,2886,1003,'2029-12-27 20:33:33','2025-12-27 20:33:33','Officers.Officers',964,1,NULL,'2025-12-27 20:33:33','2025-12-27 20:33:33',1,2875,2),
(388,2887,1120,'2025-12-27 20:54:58','2025-12-27 20:54:24','Officers.Officers',966,1,1,'2025-12-27 20:54:59','2025-12-27 20:54:24',1,1,32),
(389,2877,1116,'2027-12-27 21:05:53','2025-12-27 21:05:53','Officers.Officers',967,1,NULL,'2025-12-27 21:05:53','2025-12-27 21:05:53',1,1,2),
(395,2879,200,'2026-08-04 18:02:38','2026-02-04 18:02:38','Officers.Officers',975,1,NULL,'2026-02-04 18:02:38','2026-02-04 18:02:38',1,1,11);
/*!40000 ALTER TABLE `member_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_id` varchar(8) NOT NULL COMMENT 'Non-sequential public identifier safe for client exposure',
  `password` varchar(512) NOT NULL,
  `sca_name` varchar(50) NOT NULL,
  `first_name` varchar(30) NOT NULL,
  `middle_name` varchar(30) DEFAULT NULL,
  `last_name` varchar(30) NOT NULL,
  `street_address` varchar(75) DEFAULT NULL,
  `city` varchar(30) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zip` varchar(5) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `email_address` varchar(50) NOT NULL,
  `timezone` varchar(50) DEFAULT NULL COMMENT 'User preferred timezone (IANA identifier, e.g., America/Chicago)',
  `membership_number` varchar(50) DEFAULT NULL,
  `membership_expires_on` date DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `background_check_expires_on` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `verified_date` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `mobile_card_token` varchar(255) DEFAULT NULL,
  `password_token` varchar(255) DEFAULT NULL,
  `password_token_expires_on` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_failed_login` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT NULL,
  `birth_month` int(11) DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `additional_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT _utf8mb3'{}' CHECK (json_valid(`additional_info`)),
  `membership_card_path` varchar(256) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `pronouns` varchar(50) DEFAULT NULL,
  `pronunciation` varchar(255) DEFAULT NULL,
  `warrantable` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_address` (`email_address`),
  UNIQUE KEY `idx_members_public_id` (`public_id`),
  KEY `deleted` (`deleted`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2892 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES
(1,'ZaC6LYhW','','Admin von Admin','Addy','','Min','Fake Data','a city','TX','00000','555-555-5555','admin@amp.ansteorra.org',NULL,'AdminAccount','2100-01-01',24,NULL,'verified',NULL,NULL,NULL,'9cf9fd5c389304f85d5ade102a9c9119',NULL,NULL,'2026-02-06 03:37:05',NULL,0,4,1977,'{\"CallIntoCourt\": \"Never\", \"CourtAvailability\": \"None\", \"OrderOfPrecedence_Id\": \"-1000\", \"PersonToGiveNoticeTo\": \"No one this is a system account\"}',NULL,'2025-10-30 21:15:16','2024-09-29 15:47:03',NULL,1,NULL,NULL,NULL,NULL,1),
(2871,'E3b52yyK','','Agatha Local MoAS Demoer','Agatha','','Demoer','123 street st','cyti','OK','11111','111-111-1111','agatha@ampdemo.com',NULL,'1111111','2029-07-25',17,NULL,'verified','2025-08-30 21:24:24',1073,NULL,'1949ccc7b67dced1',NULL,NULL,'2025-11-18 00:19:12','2025-12-27 19:19:19',1,4,1987,'{\"CallIntoCourt\": \"With Notice\", \"CourtAvailability\": \"Evening\", \"PersonToGiveNoticeTo\": \"Bryce Demoer\"}',NULL,'2025-09-01 18:45:03','2025-06-22 18:38:03',NULL,1,NULL,'','','',1),
(2872,'zahGo6tU','','Bryce Local Seneschal Demoer','Bryce','','Demoer','234 street st','other cyti','ok','22222','222-222-2222','bryce@ampdemo.com',NULL,'222222222','2029-07-25',22,NULL,'verified','2025-06-22 18:52:58',1073,NULL,'c9e30b0d3bc71f41',NULL,NULL,'2025-12-30 18:49:58',NULL,0,12,1982,'{}',NULL,'2025-09-01 18:45:15','2025-06-22 18:51:53',NULL,1,NULL,'','','',1),
(2873,'5f57rxFn','','Caroline Regional Seneschal Demoer','Caroline','','Demoer','333 street st','some other syti','tx','33333','333-333-3333','caroline@ampdemo.com',NULL,'333333333','2028-07-22',33,NULL,'verified','2025-06-22 19:31:21',1073,NULL,'5b6e6e11d147d237',NULL,NULL,'2025-11-18 01:35:02',NULL,0,5,1965,'{}',NULL,'2025-09-01 18:45:26','2025-06-22 19:29:32',NULL,1,NULL,'','','',1),
(2874,'tCE5uS7i','','Devon Regional Armored Demoer','Devon','','Demoer','444 street st','somewhere','tx','44444','444-444-4444','devon@ampdemo.com',NULL,'44444444','2025-12-30',27,NULL,'verified','2025-06-22 19:38:02',1073,NULL,'380100f2b4794b42',NULL,NULL,'2025-12-27 20:13:18',NULL,0,9,2002,'{}',NULL,'2025-09-01 18:45:35','2025-06-22 19:32:33',NULL,1,NULL,'','','',1),
(2875,'miR33yZU','','Eirik Kingdom Seneschal Demoer','Eirik','','Demoer','555 street st','That City','tx','55555','555-555-5555','eirik@ampdemo.com','','555555555','2029-09-23',36,NULL,'verified','2025-06-22 19:37:29',1073,NULL,'fe2857083862a393',NULL,NULL,'2025-12-27 20:30:19',NULL,0,12,2004,'{}',NULL,'2025-12-27 20:30:59','2025-06-22 19:34:27',NULL,1,NULL,'','','',1),
(2876,'seP5Lzim','','Garun Kingdom Rapier Marshal Demoer','Garun','','Demoer','777 street st','there','tx','77777','777-777-7777','garun@ampdemo.com',NULL,'777777777','2029-11-24',38,NULL,'verified','2025-06-25 02:07:01',1073,NULL,'30f2b7dde6d6c0e4',NULL,NULL,'2026-01-16 17:17:09',NULL,0,7,1989,'{}',NULL,'2025-12-27 20:59:03','2025-06-25 02:05:31',NULL,2876,NULL,'','','',1),
(2877,'uzrqH6Lx','','Haylee Kingdom MoAS Deputy Demoer','Haylee','','Demoer','888 street st','here','tx','88888','888-888-8888','haylee@ampdemo.com',NULL,'88888888','2030-11-24',38,NULL,'verified','2025-06-25 02:32:20',1073,NULL,'fbe83c090b3204bf',NULL,NULL,'2026-01-14 21:54:19',NULL,0,4,2006,'{}',NULL,'2025-09-01 18:46:16','2025-06-25 02:30:06',NULL,1,NULL,'','','',1),
(2878,'aRr34TAw','','Iris Basic User Demoer','Iris','','Demoer','','','','','','iris@ampdemo.com',NULL,'',NULL,30,NULL,'active',NULL,NULL,NULL,'800c45bec8b22900',NULL,NULL,'2026-01-17 20:20:31',NULL,0,6,1988,'{\"CallIntoCourt\": \"With notice given to another person\", \"CourtAvailability\": \"Morning\", \"PersonToGiveNoticeTo\": \"Person 1, Person 2\"}',NULL,'2026-01-17 20:26:55','2025-07-02 21:10:45',NULL,2878,NULL,'','','',0),
(2879,'yG5SqDzi','','Jael Principality Coronet Demoer','Jael','','Demoer','10 street st','joke','ok','10101','1010101010','jael@ampdemo.com',NULL,'12121','2029-11-21',36,NULL,'verified','2025-07-02 21:21:56',1073,NULL,'a457c4abcac54e6d',NULL,NULL,'2025-09-16 00:47:30',NULL,0,8,1948,'{}',NULL,'2025-09-01 18:46:33','2025-07-02 21:13:19',NULL,1,NULL,'','','',1),
(2880,'w6fqV3Kk','','Kal Local Landed w Canton Demoer','Kal','','Demoer','11 street st','asdf','tx','11011','1111111112','kal@ampdemo.com',NULL,'asdfasdf','2029-11-23',19,NULL,'verified','2025-07-02 21:31:55',1073,NULL,'82d588407d14dbc7',NULL,NULL,'2026-01-13 23:03:44',NULL,0,8,2006,'{}',NULL,'2025-09-01 18:46:43','2025-07-02 21:14:50',NULL,1,NULL,'','','',1),
(2881,'X4WXDb6f','','Forest Crown Demoer','Forest','','Demoer','6666 street st','nowhere','tx','66666','6666666666','forest@ampdemo.com',NULL,'66666666','2029-01-09',33,NULL,'verified','2025-07-02 21:28:16',1073,NULL,'d12e846934665088',NULL,NULL,'2025-11-11 03:06:01',NULL,0,10,1987,'{}',NULL,'2025-09-01 18:45:50','2025-07-02 21:27:27',NULL,1,NULL,'','','',1),
(2882,'EAW9nT5g','','Leonard Landed with Stronghold Demoer','Leonard','','Demoer','12 street st','place','bi','12000','1212121212','leonard@ampdemo.com',NULL,'120000','2029-07-18',22,NULL,'verified','2025-08-07 21:08:44',1073,NULL,'663b5d190bb0e70c',NULL,NULL,'2025-09-14 21:35:37',NULL,0,9,1982,'{}',NULL,'2025-09-01 18:46:50','2025-08-07 21:05:57',NULL,1,NULL,'','','',1),
(2883,'SsvLgcWR','','Mel Local Exch and Kingdom Social Demoer','Mel','','Demoer','13 street way','there','ah','13131','111-333-0000','mel@ampdemo.com',NULL,'13','2028-11-22',24,NULL,'verified','2025-08-30 21:30:56',1073,NULL,'7267a94cd6a316ae',NULL,NULL,'2025-08-30 21:29:32',NULL,0,3,1995,'{}',NULL,'2025-09-01 18:46:57','2025-08-30 21:27:38',NULL,1,NULL,NULL,NULL,NULL,1),
(2886,'qoyyjb7k','','Nester At Large C&T Auth Marshal Demoer','Nester','','Demoer','mwahaha ln apt 6','asdf','ta','99999','000-000-0000','nester@ampdemo.com',NULL,'11111','2027-10-11',33,NULL,'verified','2025-12-27 20:16:21',1,NULL,'4a9d2e45ff4bb19f',NULL,NULL,'2025-12-27 20:27:36',NULL,0,12,1990,'{}',NULL,'2025-12-27 20:36:59','2025-12-27 20:14:52',NULL,2886,NULL,'','','',1),
(2887,'7EberQqJ','','Olivia Local Chatelaine Demoer','Olivia','','Demoer','1 aster ln','here','tn','72727','111-111-1111','olivia@ampdemo.com','','111111','2028-11-02',32,NULL,'verified','2025-12-27 20:51:31',1,NULL,'afae72fba566d166',NULL,NULL,NULL,NULL,0,3,1986,'{}',NULL,'2025-12-27 20:51:31','2025-12-27 20:48:36',NULL,1,NULL,'','','',1),
(2888,'usRZuQTx','','Pam Local Seneschal Deputy Stargate','Pam','','Demoer','yay st','here','th','99999','999-999-9999','pam@ampdemo.com',NULL,'11111','2029-01-02',39,NULL,'verified','2025-12-29 08:25:42',1,NULL,'7dc1df01c29c1ada',NULL,NULL,'2025-12-29 08:25:09',NULL,0,1,2003,'{}',NULL,'2025-12-29 08:25:42','2025-12-29 08:24:43',NULL,1,NULL,NULL,NULL,NULL,1);
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `private` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `topic_id` (`entity_id`),
  KEY `topic_model` (`entity_type`)
) ENGINE=InnoDB AUTO_INCREMENT=806 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officers_departments`
--

DROP TABLE IF EXISTS `officers_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `officers_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `domain` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officers_departments`
--

LOCK TABLES `officers_departments` WRITE;
/*!40000 ALTER TABLE `officers_departments` DISABLE KEYS */;
INSERT INTO `officers_departments` VALUES
(1,'Nobility','2025-01-02 14:08:14','2025-01-02 14:08:14',1,1,NULL,''),
(2,'Seneschallate','2025-01-16 17:28:27','2025-01-02 14:08:26',1,1096,NULL,''),
(3,'Marshallate','2025-01-06 01:15:36','2025-01-02 14:08:35',1,1,NULL,''),
(4,'Webministry','2025-01-15 02:26:41','2025-01-02 14:08:48',1,1073,NULL,''),
(5,'Arts & Sciences','2025-01-02 14:08:56','2025-01-02 14:08:56',1,1,NULL,''),
(6,'Treasury','2025-01-13 20:39:29','2025-01-02 14:09:38',1,1073,NULL,''),
(7,'Chatelaine','2025-01-13 20:41:32','2025-01-02 14:10:13',1,1096,NULL,''),
(8,'Historian','2025-01-02 14:10:24','2025-01-02 14:10:24',1,1,NULL,''),
(9,'Chronicler','2025-01-02 14:11:02','2025-01-02 14:11:02',1,1,NULL,''),
(10,'College of Heralds','2025-01-02 14:11:34','2025-01-02 14:11:34',1,1,NULL,''),
(11,'College of Scribes','2025-01-02 14:11:54','2025-01-02 14:11:54',1,1,NULL,''),
(12,'Youth and Family Office','2025-01-13 20:04:21','2025-01-02 16:01:21',1,1096,NULL,'');
/*!40000 ALTER TABLE `officers_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officers_officers`
--

DROP TABLE IF EXISTS `officers_officers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `officers_officers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `granted_member_role_id` int(11) DEFAULT NULL,
  `expires_on` datetime DEFAULT NULL,
  `start_on` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `deputy_description` varchar(255) DEFAULT NULL,
  `revoked_reason` varchar(255) DEFAULT '',
  `revoker_id` int(11) DEFAULT NULL,
  `approver_id` int(11) NOT NULL,
  `approval_date` datetime NOT NULL,
  `reports_to_branch_id` int(11) DEFAULT NULL,
  `reports_to_office_id` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deputy_to_branch_id` int(11) DEFAULT NULL,
  `deputy_to_office_id` int(11) DEFAULT NULL,
  `email_address` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `office_id` (`office_id`),
  KEY `member_id` (`member_id`),
  KEY `start_on` (`start_on`),
  KEY `expires_on` (`expires_on`),
  KEY `reports_to_branch_id` (`reports_to_branch_id`),
  KEY `reports_to_office_id` (`reports_to_office_id`),
  KEY `deputy_to_branch_id` (`deputy_to_branch_id`),
  KEY `deputy_to_office_id` (`deputy_to_office_id`),
  CONSTRAINT `officers_officers_ibfk_1` FOREIGN KEY (`reports_to_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `officers_officers_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `officers_officers_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `officers_officers_ibfk_4` FOREIGN KEY (`office_id`) REFERENCES `officers_offices` (`id`),
  CONSTRAINT `officers_officers_ibfk_5` FOREIGN KEY (`reports_to_office_id`) REFERENCES `officers_offices` (`id`),
  CONSTRAINT `officers_officers_ibfk_6` FOREIGN KEY (`deputy_to_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `officers_officers_ibfk_7` FOREIGN KEY (`deputy_to_office_id`) REFERENCES `officers_offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=976 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officers_officers`
--

LOCK TABLES `officers_officers` WRITE;
/*!40000 ALTER TABLE `officers_officers` DISABLE KEYS */;
INSERT INTO `officers_officers` VALUES
(928,2875,2,10,362,'2025-08-30 21:10:49','2025-06-22 19:38:58','Released',NULL,'cleaning up demo users',1073,1,'2025-06-22 19:38:58',NULL,NULL,'2025-08-30 21:10:50','2025-06-22 19:38:59',1,1073,NULL,NULL,''),
(929,2874,12,13,NULL,'2025-06-22 20:12:36','2025-06-22 00:00:00','Released',NULL,'test release',2875,1,'2025-06-22 19:51:00',2,10,'2025-06-22 20:12:37','2025-06-22 19:51:00',1,2875,NULL,NULL,''),
(930,2874,12,13,363,'2025-08-30 21:10:02','2025-06-22 20:12:55','Released',NULL,'cleaning up demo users',1073,1,'2025-06-22 20:12:55',2,10,'2025-08-30 21:10:03','2025-06-22 20:12:55',1,1073,NULL,NULL,''),
(931,2872,30,14,NULL,'2025-08-30 21:06:57','2025-06-22 20:33:41','Released',NULL,'cleaning up demo users',1073,1,'2025-06-22 20:33:41',12,13,'2025-08-30 21:06:58','2025-06-22 20:33:41',1,1073,NULL,NULL,''),
(932,2871,31,14,NULL,'2027-06-22 20:34:23','2025-06-22 20:34:23','Current',NULL,'',NULL,1,'2025-06-22 20:34:23',12,13,'2025-06-22 20:34:23','2025-06-22 20:34:23',1,2874,NULL,NULL,''),
(933,2874,13,13,364,'2027-06-22 20:35:28','2025-06-22 20:35:28','Current',NULL,'',NULL,1,'2025-06-22 20:35:28',2,10,'2025-06-22 20:35:28','2025-06-22 20:35:28',1,2875,NULL,NULL,''),
(934,2877,2,12,NULL,'2025-12-27 21:06:06','2025-06-25 02:32:54','Released','Demoer Deputy','fixing uat office assignments',1,1,'2025-06-25 02:32:54',2,10,'2025-12-27 21:06:07','2025-06-25 02:32:54',1,1,2,10,''),
(935,2879,11,93,365,'2026-01-02 21:16:11','2025-07-02 21:16:11','Expired',NULL,'',NULL,1,'2025-07-02 21:16:11',2,NULL,'2025-07-02 21:16:11','2025-07-02 21:16:11',1,1073,NULL,NULL,'prince@vindheim.ansteorra.org'),
(936,2880,27,95,366,NULL,'2025-07-02 21:17:24','Current',NULL,'',NULL,1,'2025-07-02 21:17:24',2,1,'2025-07-02 21:17:24','2025-07-02 21:17:24',1,1073,NULL,NULL,'baron@steppes.ansteorra.org'),
(937,2871,27,95,367,'2025-08-30 21:06:28','2025-07-02 21:17:46','Released',NULL,'cleaning up demo users',1073,1,'2025-07-02 21:17:46',2,1,'2025-08-30 21:06:29','2025-07-02 21:17:46',1,1073,NULL,NULL,'baroness@steppes.ansteorra.org'),
(938,2881,2,1,368,'2026-07-02 21:28:40','2025-07-02 21:28:40','Current',NULL,'',NULL,1,'2025-07-02 21:28:40',NULL,NULL,'2025-07-02 21:28:40','2025-07-02 21:28:40',1,1073,NULL,NULL,'trm@ansteorra.org'),
(939,2880,28,95,369,NULL,'2025-07-09 21:57:17','Current',NULL,'',NULL,1,'2025-07-09 21:57:17',2,1,'2025-12-04 00:39:07','2025-07-09 21:57:17',1,1,NULL,NULL,'baron@steppes.ansteorra.org'),
(940,2874,33,95,370,'2025-12-27 20:55:59','2025-07-09 21:59:26','Released',NULL,'cleaning up offices for regional armoured',1,1,'2025-07-09 21:59:26',2,1,'2025-12-27 20:56:00','2025-07-09 21:59:26',1,1,NULL,NULL,'baron@bryn-gwlad.ansteorra.org'),
(941,2874,38,95,371,'2025-12-27 20:55:53','2025-07-09 21:59:47','Released',NULL,'cleaning up offices for regional armoured',1,1,'2025-07-09 21:59:47',2,1,'2025-12-27 20:55:54','2025-07-09 21:59:47',1,1,NULL,NULL,'baron@bryn-gwlad.ansteorra.org'),
(942,2882,11,4,NULL,'2025-08-30 21:14:37','2025-08-07 21:09:06','Released',NULL,'cleaning up demo users',1073,1,'2025-08-07 21:09:06',2,3,'2025-08-30 21:14:38','2025-08-07 21:09:06',1,1073,NULL,NULL,''),
(943,2882,2,3,372,'2025-08-30 21:12:11','2025-08-07 21:10:56','Replaced','','Replaced by new officer',1073,1,'2025-08-07 21:10:56',2,2,'2025-08-30 21:12:12','2025-08-07 21:10:56',1,1073,2,2,''),
(949,2872,39,31,381,'2027-08-30 21:08:09','2025-08-30 21:08:09','Current',NULL,'',NULL,1,'2025-08-30 21:08:09',13,30,'2025-11-11 21:18:22','2025-08-30 21:08:09',1,1,NULL,NULL,'seneschal@stargate.ansteorra.org'),
(950,2873,12,30,375,'2027-08-30 21:09:16','2025-08-30 21:09:16','Current',NULL,'',NULL,1,'2025-08-30 21:09:16',2,28,'2025-08-30 21:09:16','2025-08-30 21:09:16',1,1073,NULL,NULL,''),
(951,2874,12,16,NULL,'2027-08-30 21:10:12','2025-08-30 21:10:12','Current',NULL,'',NULL,1,'2025-08-30 21:10:12',2,17,'2025-08-30 21:10:12','2025-08-30 21:10:12',1,1073,NULL,NULL,''),
(952,2875,2,28,376,'2027-08-30 21:11:04','2025-08-30 21:11:04','Current',NULL,'',NULL,1,'2025-08-30 21:11:04',NULL,NULL,'2025-08-30 21:11:04','2025-08-30 21:11:04',1,1073,NULL,NULL,''),
(953,2876,2,3,377,'2027-08-30 21:12:12','2025-08-30 21:12:12','Current','','',NULL,1,'2025-08-30 21:12:12',2,2,'2025-08-30 21:12:12','2025-08-30 21:12:12',1,1073,2,2,''),
(954,2882,33,95,378,NULL,'2025-08-30 21:15:22','Current',NULL,'',NULL,1,'2025-08-30 21:15:22',2,1,'2025-08-30 21:15:22','2025-08-30 21:15:22',1,1073,NULL,NULL,'baron@bryn-gwlad.ansteorra.org'),
(955,2882,38,95,379,NULL,'2025-08-30 21:15:45','Current',NULL,'',NULL,1,'2025-08-30 21:15:45',2,1,'2025-08-30 21:15:45','2025-08-30 21:15:45',1,1073,NULL,NULL,'baron@bryn-gwlad.ansteorra.org'),
(956,2883,37,76,NULL,'2027-08-30 21:31:13','2025-08-30 21:31:13','Current',NULL,'',NULL,1,'2025-08-30 21:31:13',13,77,'2025-08-30 21:31:13','2025-08-30 21:31:13',1,1073,NULL,NULL,''),
(957,2883,2,50,NULL,'2027-08-30 21:31:30','2025-08-30 21:31:30','Current','','',NULL,1,'2025-08-30 21:31:30',2,28,'2025-08-30 21:31:30','2025-08-30 21:31:30',1,1073,2,28,''),
(958,1,2,34,380,'2029-09-12 02:51:12','2025-09-12 02:51:12','Current','','',NULL,1,'2025-09-12 02:51:12',2,17,'2025-09-12 02:51:12','2025-09-12 02:51:12',1,1,2,17,''),
(961,2880,39,95,384,NULL,'2025-12-04 00:38:29','Current',NULL,'',NULL,1,'2025-12-04 00:38:29',2,1,'2025-12-04 00:38:29','2025-12-04 00:38:29',1,1,NULL,NULL,'baron@stargate.ansteorra.org'),
(962,2872,2,45,385,'2025-12-27 20:39:51','2025-12-16 01:54:50','Released',NULL,'need Bryce Local Sen to not have a Kingdom office for testing',2872,1,'2025-12-16 01:54:50',NULL,NULL,'2025-12-27 20:39:52','2025-12-16 01:54:50',1,2872,NULL,NULL,''),
(963,2886,2,36,386,'2029-12-27 20:33:21','2025-12-27 20:33:21','Current','','',NULL,1,'2025-12-27 20:33:21',2,25,'2025-12-27 20:33:21','2025-12-27 20:33:21',1,2875,2,25,''),
(964,2886,2,26,387,'2029-12-27 20:33:33','2025-12-27 20:33:33','Current','','',NULL,1,'2025-12-27 20:33:33',2,25,'2025-12-27 20:33:33','2025-12-27 20:33:33',1,2875,2,25,''),
(965,2887,32,47,NULL,'2027-12-27 20:51:51','2025-12-27 20:51:51','Current',NULL,'',NULL,1,'2025-12-27 20:51:51',12,46,'2025-12-27 20:51:51','2025-12-27 20:51:51',1,1,NULL,NULL,''),
(966,2887,32,15,388,'2025-12-27 20:54:58','2025-12-27 20:54:24','Released',NULL,'need olivia to only have local chatelaine, this was a test appointment',1,1,'2025-12-27 20:54:24',12,16,'2025-12-27 20:54:59','2025-12-27 20:54:24',1,1,NULL,NULL,''),
(967,2877,2,10,389,'2027-12-27 21:05:53','2025-12-27 21:05:53','Current',NULL,'',NULL,1,'2025-12-27 21:05:53',NULL,NULL,'2025-12-27 21:05:53','2025-12-27 21:05:53',1,1,NULL,NULL,''),
(968,2888,39,49,NULL,'2027-12-29 08:26:07','2025-12-29 08:26:07','Current','','',NULL,1,'2025-12-29 08:26:07',39,31,'2025-12-29 08:26:07','2025-12-29 08:26:07',1,1,39,31,''),
(969,2874,39,49,NULL,'2026-04-30 00:00:00','2025-12-30 00:00:00','Current','Fall Baronial Event Steward','',NULL,1,'2025-12-29 19:10:10',39,31,'2025-12-29 19:10:10','2025-12-29 19:10:10',1,2872,39,31,''),
(975,2879,11,94,395,'2026-08-04 18:02:38','2026-02-04 18:02:38','Current',NULL,'',NULL,1,'2026-02-04 18:02:38',NULL,NULL,'2026-02-04 18:02:38','2026-02-04 18:02:38',1,1,NULL,NULL,'princess@vindheim.ansteorra.org');
/*!40000 ALTER TABLE `officers_officers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officers_offices`
--

DROP TABLE IF EXISTS `officers_offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `officers_offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `requires_warrant` tinyint(1) NOT NULL DEFAULT 0,
  `required_office` tinyint(1) NOT NULL DEFAULT 0,
  `can_skip_report` tinyint(1) NOT NULL DEFAULT 0,
  `only_one_per_branch` tinyint(1) NOT NULL DEFAULT 0,
  `deputy_to_id` int(11) DEFAULT NULL,
  `grants_role_id` int(11) DEFAULT NULL,
  `term_length` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `applicable_branch_types` varchar(255) DEFAULT NULL,
  `reports_to_id` int(11) DEFAULT NULL,
  `default_contact_address` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `department_id` (`department_id`),
  KEY `deleted` (`deleted`),
  KEY `grants_role_id` (`grants_role_id`),
  KEY `deputy_to_id` (`deputy_to_id`),
  KEY `reports_to_id` (`reports_to_id`),
  CONSTRAINT `officers_offices_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `officers_departments` (`id`),
  CONSTRAINT `officers_offices_ibfk_2` FOREIGN KEY (`grants_role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `officers_offices_ibfk_3` FOREIGN KEY (`deputy_to_id`) REFERENCES `officers_offices` (`id`),
  CONSTRAINT `officers_offices_ibfk_4` FOREIGN KEY (`reports_to_id`) REFERENCES `officers_offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officers_offices`
--

LOCK TABLES `officers_offices` WRITE;
/*!40000 ALTER TABLE `officers_offices` DISABLE KEYS */;
INSERT INTO `officers_offices` VALUES
(1,'Crown',1,0,1,0,0,NULL,100,12,'2025-04-21 22:30:58','2025-01-02 14:17:56',1,1,NULL,'\"Kingdom\"',NULL,'trm'),
(2,'Kingdom Earl Marshal',3,1,1,0,1,NULL,1108,24,'2025-01-12 17:05:36','2025-01-02 14:37:55',1,1096,NULL,'\"Kingdom\"',NULL,''),
(3,'Kingdom Rapier Marshal',3,1,1,0,1,2,1109,24,'2025-01-12 17:05:14','2025-01-02 14:38:32',1,1096,NULL,'\"Kingdom\"',2,''),
(4,'Regional Rapier Marshal',3,1,1,0,1,NULL,1118,24,'2025-12-04 00:36:31','2025-01-02 15:32:17',1,1,NULL,'\"Principality\",\"Region\"',3,''),
(5,'Local Rapier Marshal',3,1,1,0,1,NULL,NULL,24,'2025-01-12 17:04:27','2025-01-02 15:32:44',1,1096,NULL,'\"Local Group\"',4,''),
(6,'Kingdom Chronicler',9,1,1,0,1,NULL,1116,24,'2025-01-12 17:17:35','2025-01-02 15:43:54',1,1096,NULL,'\"Kingdom\"',NULL,''),
(7,'Kingdom Chronicler Deputy',9,1,0,0,0,6,NULL,24,'2025-01-02 15:44:26','2025-01-02 15:44:26',1,1,NULL,'\"Kingdom\"',6,''),
(8,'Regional Chronicler',9,1,1,0,1,NULL,NULL,24,'2025-01-12 17:04:38','2025-01-02 15:45:03',1,1096,NULL,'\"Principality\",\"Region\"',6,''),
(9,'Local Chronicler',9,1,1,0,1,NULL,NULL,24,'2025-01-02 15:52:01','2025-01-02 15:52:01',1,1,NULL,'\"Local Group\"',8,''),
(10,'Kingdom MoAS',5,1,1,0,1,NULL,1116,24,'2025-01-12 17:23:37','2025-01-02 16:01:49',1,1096,NULL,'\"Kingdom\"',NULL,''),
(11,'Kingdom Earl Marshal Deputy',3,1,0,0,0,2,NULL,24,'2025-01-02 16:03:03','2025-01-02 16:03:03',1,1,NULL,'\"Kingdom\"',2,''),
(12,'Kingdom MoAS Deputy',5,1,0,0,0,10,NULL,24,'2025-01-02 16:03:41','2025-01-02 16:03:41',1,1,NULL,'\"Kingdom\"',10,''),
(13,'Regional MoAS',5,1,1,0,1,NULL,1118,24,'2025-06-22 20:00:21','2025-01-02 16:20:33',1,1073,NULL,'\"Principality\",\"Region\"',10,''),
(14,'Local MoAS',5,1,1,0,1,NULL,NULL,24,'2025-01-02 16:21:02','2025-01-02 16:21:02',1,1,NULL,'\"Local Group\"',13,''),
(15,'Local Armored Marshal',3,1,1,0,1,NULL,1120,24,'2025-12-04 00:30:10','2025-01-06 01:12:23',1,1,NULL,'\"Local Group\"',16,''),
(16,'Regional Armored Marshal',3,1,1,0,1,NULL,NULL,24,'2025-01-13 15:30:05','2025-01-06 01:16:28',1,1096,NULL,'\"Region\"',17,''),
(17,'Kingdom Armored Marshal',3,1,1,0,1,2,1111,24,'2025-01-12 18:33:08','2025-01-06 01:20:16',1,1096,NULL,'\"Kingdom\"',2,''),
(18,'Kingdom Webminister',4,1,1,0,1,NULL,1116,24,'2025-01-12 17:18:04','2025-01-07 16:08:48',1,1096,NULL,'\"Kingdom\"',NULL,''),
(19,'Deleted: Application Admin',4,1,0,0,0,18,NULL,24,'2025-01-13 14:48:19','2025-01-07 16:11:56',1,1096,'2025-01-13 14:48:19','\"Kingdom\"',18,''),
(20,'Kingdom Webminister Deputy',4,1,0,0,0,18,NULL,24,'2025-01-13 14:48:51','2025-01-07 16:22:45',1,1096,NULL,'\"Kingdom\"',18,''),
(21,'Local Webminister',4,1,0,0,1,NULL,NULL,24,'2025-01-07 16:38:21','2025-01-07 16:38:21',1,1,NULL,'\"Local Group\"',18,''),
(22,'Kingdom Webminister - AMP Admin',4,1,0,0,0,18,10,24,'2025-01-13 14:49:14','2025-01-07 21:23:35',1,1096,NULL,'\"Kingdom\"',18,''),
(23,'At Large: Rapier Authorizing Marshal',3,1,0,0,0,3,1002,48,'2025-01-13 17:17:04','2025-01-12 17:07:25',1,1096,NULL,'\"Kingdom\"',3,''),
(24,'Kingdom Missile Marshal',3,1,0,0,1,2,1110,24,'2025-01-12 17:13:59','2025-01-12 17:09:02',1,1096,NULL,'\"Kingdom\"',2,''),
(25,'Kingdom C&T Marshal',3,1,0,0,1,3,1003,24,'2025-01-12 17:13:38','2025-01-12 17:10:26',1,1096,NULL,'\"Kingdom\"',3,''),
(26,'At Large: C&T Authorizing Marshal',3,1,0,0,0,25,1003,48,'2025-01-13 17:16:51','2025-01-12 17:14:22',1,1096,NULL,'\"Kingdom\"',25,''),
(27,'Deleted: At Large: Authorizing Target Archery Marshal',3,1,0,0,0,58,1004,48,'2025-01-13 17:17:27','2025-01-12 17:16:05',1,1096,'2025-01-13 17:17:27','\"Kingdom\"',58,''),
(28,'Kingdom Seneschal',2,1,1,0,1,NULL,1116,24,'2025-01-12 17:22:23','2025-01-12 17:20:13',1,1096,NULL,'\"Kingdom\"',NULL,''),
(29,'Kingdom Seneschal Deputy',2,1,0,0,0,28,NULL,24,'2025-01-12 17:20:52','2025-01-12 17:20:52',1,1096,NULL,'\"Kingdom\"',28,''),
(30,'Regional Seneschal',2,1,1,0,1,NULL,1118,24,'2025-06-22 00:16:36','2025-01-12 17:21:31',1,2866,NULL,'\"Principality\",\"Region\"',28,''),
(31,'Local Seneschal',2,1,1,0,1,NULL,1119,24,'2025-11-11 21:18:22','2025-01-12 17:21:57',1,1,NULL,'\"Local Group\"',30,'seneschal'),
(32,'Kingdom Rapier Marshal Deputy',3,1,0,0,0,NULL,NULL,24,'2025-01-12 17:36:25','2025-01-12 17:36:25',1,1096,NULL,'\"Kingdom\"',3,''),
(33,'Kingdom Missile Marshal Deputy',3,1,0,0,0,24,NULL,24,'2025-01-12 17:36:59','2025-01-12 17:36:59',1,1096,NULL,'\"Kingdom\"',24,''),
(34,'At Large: Armored Authorizing Marshal',3,1,0,0,0,17,1001,48,'2025-01-13 17:16:22','2025-01-12 17:38:20',1,1096,NULL,'\"Kingdom\"',17,''),
(35,'Kingdom Equestrian Marshal',3,1,0,0,0,2,1112,24,'2025-01-12 17:39:07','2025-01-12 17:39:07',1,1096,NULL,'\"Kingdom\"',2,''),
(36,'At Large: C&T 2 Handed Weapons Authorizing Marshal',3,1,0,0,0,25,1012,48,'2025-01-13 17:16:34','2025-01-12 17:41:11',1,1096,NULL,'\"Kingdom\"',25,''),
(37,'At Large: Equestrian Authorizing Marshal',3,1,0,0,0,35,1005,48,'2025-01-18 21:22:20','2025-01-12 17:44:06',1,1096,NULL,'\"Kingdom\"',35,''),
(38,'At Large: Wooden Lance Authorizing Marshal',3,1,0,0,0,35,1013,48,'2025-01-12 17:45:44','2025-01-12 17:45:44',1,1096,NULL,'\"Kingdom\"',35,''),
(39,'Kingdom Youth Rapier Marshal',3,1,0,0,1,3,1114,24,'2025-01-21 01:43:23','2025-01-12 18:05:03',1,1096,NULL,'\"Kingdom\"',3,''),
(40,'Kingdom Youth Armored Marshal',3,1,0,0,1,17,1113,24,'2025-01-21 01:43:10','2025-01-12 18:05:53',1,1096,NULL,'\"Kingdom\"',17,''),
(41,'Star Principal Herald',10,1,1,0,1,NULL,1116,24,'2025-01-12 18:07:11','2025-01-12 18:07:11',1,1096,NULL,'\"Kingdom\"',NULL,''),
(42,'Kingdom Herald Deputy',10,1,0,0,0,41,NULL,24,'2025-01-15 01:10:30','2025-01-12 18:08:35',1,1073,NULL,'\"Kingdom\"',41,''),
(43,'Regional Herald',10,1,1,0,1,NULL,NULL,24,'2025-01-12 18:09:37','2025-01-12 18:09:37',1,1096,NULL,'\"Principality\",\"Region\"',41,''),
(44,'Local Herald',10,1,1,0,1,NULL,NULL,24,'2025-01-13 19:36:40','2025-01-12 18:09:56',1,1073,NULL,'\"Local Group\"',43,''),
(45,'Kingdom Chatelaine',7,1,1,0,1,NULL,1116,24,'2025-01-13 20:40:12','2025-01-12 21:55:13',1,1096,NULL,'\"Kingdom\"',NULL,''),
(46,'Regional Chatelaine',7,1,1,0,1,NULL,NULL,24,'2025-01-13 20:41:21','2025-01-12 21:55:41',1,1096,NULL,'\"Principality\",\"Region\"',45,''),
(47,'Local Chatelaine',7,1,1,0,1,NULL,NULL,24,'2025-01-13 20:40:49','2025-01-12 21:56:03',1,1096,NULL,'\"Local Group\"',46,''),
(48,'Kingdom Chatelaine Deputy',7,1,0,0,0,45,NULL,24,'2025-01-13 20:40:28','2025-01-12 21:56:30',1,1096,NULL,'\"Kingdom\"',45,''),
(49,'Local Seneschal Deputy',2,0,0,0,0,31,NULL,24,'2025-01-13 14:21:26','2025-01-13 14:21:26',1,1096,NULL,'\"Local Group\"',31,''),
(50,'Kingdom Social Media Officer',2,1,1,0,1,28,NULL,24,'2025-01-13 14:28:54','2025-01-13 14:28:54',1,1096,NULL,'\"Kingdom\"',28,''),
(51,'Kingdom Youth and Family Officer',12,1,1,0,1,NULL,1116,24,'2025-01-13 20:02:54','2025-01-13 14:33:31',1,1096,NULL,'\"Kingdom\"',NULL,''),
(52,'Regional Youth and Family Officer',12,1,1,0,1,NULL,NULL,24,'2025-01-13 20:03:38','2025-01-13 14:35:07',1,1096,NULL,'\"Principality\",\"Region\"',51,''),
(53,'Local Youth and Family Officer',12,1,1,0,1,NULL,NULL,24,'2025-01-13 20:03:21','2025-01-13 14:35:31',1,1096,NULL,'\"Local Group\"',52,''),
(54,'Local Social Media Officer',2,1,1,1,1,NULL,NULL,24,'2025-01-13 19:50:45','2025-01-13 14:36:05',1,1096,NULL,'\"Local Group\"',50,''),
(55,'Local Rapier Marshal Deputy',3,0,0,0,0,5,NULL,24,'2025-01-13 15:28:28','2025-01-13 15:28:28',1,1096,NULL,'\"Local Group\"',5,''),
(56,'Local Armored Marshal Deputy',3,0,0,0,0,15,NULL,24,'2025-01-13 15:29:23','2025-01-13 15:29:23',1,1096,NULL,'\"Local Group\"',15,''),
(57,'Local Heraldry Deputy',10,0,0,0,0,43,NULL,24,'2025-01-13 19:34:38','2025-01-13 15:31:05',1,1073,NULL,'\"Local Group\"',43,''),
(58,'Kingdom Target Archery Marshal',3,1,0,0,1,24,1004,24,'2025-01-13 17:08:04','2025-01-13 17:06:57',1,1096,NULL,'\"Kingdom\"',24,''),
(59,'Kingdom Thrown Weapons Marshal',3,1,0,0,1,24,1010,24,'2025-01-13 17:07:41','2025-01-13 17:07:41',1,1096,NULL,'\"Kingdom\"',24,''),
(60,'Kingdom Siege Weapons Marshal',3,1,0,0,1,24,1008,24,'2025-01-13 17:10:47','2025-01-13 17:10:47',1,1096,NULL,'\"Kingdom\"',24,''),
(61,'Kingdom Combat Archery Marshal',3,1,0,0,1,24,1011,24,'2025-01-13 17:11:20','2025-01-13 17:11:20',1,1096,NULL,'\"Kingdom\"',24,''),
(62,'At Large: Target Archery Authorizing Marshal',3,1,0,0,0,58,1004,48,'2025-01-13 17:12:11','2025-01-13 17:12:11',1,1096,NULL,'\"Kingdom\"',58,''),
(63,'At Large: Combat Archery Authorizing Marshal',3,1,0,0,0,61,1011,48,'2025-01-13 17:13:30','2025-01-13 17:13:30',1,1096,NULL,'\"Kingdom\"',61,''),
(64,'At Large: Thrown Weapons Authorizing Marshal',3,1,0,0,0,59,1010,48,'2025-01-13 17:14:08','2025-01-13 17:14:08',1,1096,NULL,'\"Kingdom\"',59,''),
(65,'At Large: Siege Weapons Authorizing Marshal',3,1,0,0,0,60,1008,48,'2025-01-13 17:15:32','2025-01-13 17:15:32',1,1096,NULL,'\"Kingdom\"',60,''),
(66,'At Large: Youth Rapier Authorizing Marshal',3,1,0,0,0,39,1006,48,'2025-01-13 17:26:32','2025-01-13 17:26:32',1,1096,NULL,'\"Kingdom\"',39,''),
(67,'At Large: Youth Armored Authorizing Marshal',3,1,0,0,0,40,1007,48,'2025-01-13 17:27:18','2025-01-13 17:27:18',1,1096,NULL,'\"Kingdom\"',40,''),
(68,'Kingdom Armored Marshal Deputy',3,1,0,0,0,17,NULL,24,'2025-01-13 17:31:06','2025-01-13 17:31:06',1,1096,NULL,'\"Kingdom\"',17,''),
(69,'Regional Seneschal Deputy',2,1,0,0,0,30,NULL,24,'2025-01-13 19:19:09','2025-01-13 19:19:09',1,1096,NULL,'\"Principality\",\"Region\"',30,''),
(70,'Regional Webminister',4,1,0,0,1,NULL,NULL,24,'2025-01-13 20:01:02','2025-01-13 20:01:02',1,1073,NULL,'\"Principality\"',18,''),
(71,'Regional Herald Deputy',10,1,0,0,0,43,NULL,24,'2025-01-13 20:11:32','2025-01-13 20:11:32',1,1096,NULL,'\"Principality\",\"Region\"',43,''),
(72,'Local Chatelaine Deputy',7,0,0,0,0,47,NULL,24,'2025-01-13 20:41:08','2025-01-13 20:34:40',1,1096,NULL,'\"Local Group\"',47,''),
(73,'Kingdom Treasurer',6,1,1,0,1,NULL,NULL,24,'2025-01-13 20:37:59','2025-01-13 20:37:38',1,1073,NULL,'\"Kingdom\"',NULL,''),
(74,'Kingdom Treasurer Deputy',6,1,0,0,0,73,NULL,24,'2025-01-13 20:39:20','2025-01-13 20:38:42',1,1073,NULL,'\"Kingdom\"',73,''),
(75,'Deleted: Regional Treasurer',6,1,1,0,1,73,NULL,24,'2025-01-13 20:46:18','2025-01-13 20:40:26',1,1073,'2025-01-13 20:46:18','\"Principality\",\"Region\"',73,''),
(76,'Local Treasurer',6,1,1,0,1,NULL,NULL,24,'2025-01-13 21:03:50','2025-01-13 20:41:04',1,1096,NULL,'\"Local Group\"',77,''),
(77,'Regional Treasurer',6,1,1,0,1,NULL,NULL,24,'2025-01-13 20:47:07','2025-01-13 20:47:07',1,1073,NULL,'\"Principality\",\"Region\"',73,''),
(78,'Kingdom Social Media Deputy',2,1,0,0,0,50,NULL,24,'2025-01-13 21:56:33','2025-01-13 21:56:33',1,1096,NULL,'\"Kingdom\"',50,''),
(79,'Regional Chronicler Deputy',9,1,0,0,0,8,NULL,24,'2025-01-13 22:03:17','2025-01-13 22:03:17',1,1073,NULL,'\"Principality\",\"Region\"',8,''),
(80,'Kingdom Earl Marshal Deputy - Secretary ',3,1,0,0,0,2,20,24,'2025-01-14 19:27:30','2025-01-14 19:27:30',1,1096,NULL,'\"Kingdom\"',2,''),
(81,'Local Thrown Weapons Marshal',3,1,0,0,1,NULL,NULL,24,'2025-01-14 20:40:55','2025-01-14 19:32:14',1,1073,NULL,'\"Local Group\"',59,''),
(82,'Regional Rapier Marshal Deputy',3,1,0,0,0,4,NULL,24,'2025-01-14 19:48:16','2025-01-14 19:48:16',1,1073,NULL,'\"Principality\",\"Region\"',4,''),
(83,'Local Target Archery Marshal',3,1,0,0,1,NULL,NULL,24,'2025-01-14 20:41:00','2025-01-14 20:01:40',1,1073,NULL,'\"Local Group\"',58,''),
(84,'Principality Earl Marshal',3,1,1,0,1,NULL,NULL,24,'2025-01-14 20:08:25','2025-01-14 20:08:25',1,1073,NULL,'\"Principality\"',2,''),
(85,'Regional Target Archery Marshal',3,1,0,0,1,NULL,NULL,24,'2025-01-14 20:35:55','2025-01-14 20:35:12',1,1941,NULL,'\"Principality\",\"Region\"',24,''),
(86,'Coronet',1,1,0,0,0,NULL,NULL,12,'2025-01-15 02:28:09','2025-01-15 02:28:09',1,1073,NULL,'\"Kingdom\"',NULL,''),
(87,'Local Target Archery Marshal Deputy',3,0,0,0,0,83,NULL,24,'2025-01-15 16:31:05','2025-01-15 16:31:05',1,1941,NULL,'\"Local Group\"',83,''),
(88,'At Large: Rapier Spear Authorizing Marshal',3,1,0,0,0,3,1009,48,'2025-01-17 13:29:10','2025-01-17 13:29:10',1,1096,NULL,'\"Kingdom\"',3,''),
(89,'At Large: Rapier Reduced Armor Experiment Authorizing Marshal',3,1,0,0,0,3,1014,48,'2025-01-17 13:31:25','2025-01-17 13:31:25',1,1096,NULL,'\"Kingdom\"',3,''),
(90,'At Large: C&T - Historic Combat Experiment Authorizing Marshal',3,1,0,0,0,25,1015,48,'2025-01-17 17:03:02','2025-01-17 17:00:59',1,1096,NULL,'\"Kingdom\"',25,''),
(91,'Regional Youth Rapier Marshal',3,0,0,0,1,NULL,NULL,24,'2025-01-21 01:48:06','2025-01-21 01:48:06',1,1941,NULL,'\"Principality\",\"Region\"',39,''),
(92,'Deleted: Landed Nobility',1,1,1,0,0,1,1117,0,'2025-04-21 14:51:33','2025-03-01 15:02:57',1,1,'2025-04-21 14:51:33','\"Local Group\"',1,'baron'),
(93,'Principality Sovereign',1,0,1,0,1,NULL,200,6,'2025-03-04 15:42:58','2025-03-04 15:35:09',1,2866,NULL,'\"Principality\"',NULL,'prince'),
(94,'Principality Consort',6,1,1,0,1,NULL,200,6,'2025-03-04 15:43:12','2025-03-04 15:38:01',1,2866,NULL,'\"Principality\"',NULL,'princess'),
(95,'Local Landed',1,1,1,1,0,NULL,1117,0,'2025-12-04 00:39:07','2025-04-21 14:50:19',1,1,NULL,'\"Local Group\"',1,'baron'),
(96,'Waiver Secretary',2,0,0,0,0,28,1121,24,'2026-01-15 01:56:11','2026-01-15 01:56:11',1,1,NULL,'\"Kingdom\"',28,'');
/*!40000 ALTER TABLE `officers_offices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `officers_phinxlog`
--

DROP TABLE IF EXISTS `officers_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `officers_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `officers_phinxlog`
--

LOCK TABLES `officers_phinxlog` WRITE;
/*!40000 ALTER TABLE `officers_phinxlog` DISABLE KEYS */;
INSERT INTO `officers_phinxlog` VALUES
(20240614000951,'InitOffices','2024-09-29 15:47:03','2024-09-29 15:47:03',0),
(20241231161659,'RefactorOfficeHierarchy','2025-01-12 01:02:22','2025-01-12 01:02:22',0),
(20250124204321,'AddViewOfficersPermission','2025-02-03 14:35:12','2025-02-03 14:35:12',0),
(20250227230922,'AddDomainToDepartment','2025-03-01 14:35:53','2025-03-01 14:35:53',0),
(20250228133830,'MakeOfficerTermMonthsNotYears','2025-03-01 14:35:53','2025-03-01 14:35:53',0);
/*!40000 ALTER TABLE `officers_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permission_policies`
--

DROP TABLE IF EXISTS `permission_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) NOT NULL,
  `policy_class` varchar(255) NOT NULL,
  `policy_method` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `permission_policies_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1048 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permission_policies`
--

LOCK TABLES `permission_policies` WRITE;
/*!40000 ALTER TABLE `permission_policies` DISABLE KEYS */;
INSERT INTO `permission_policies` VALUES
(1,2,'App\\Policy\\RolePolicy','canAdd'),
(2,2,'App\\Policy\\RolePolicy','canAddPermission'),
(3,2,'App\\Policy\\RolePolicy','canDelete'),
(4,2,'App\\Policy\\RolePolicy','canDeletePermission'),
(5,2,'App\\Policy\\RolePolicy','canEdit'),
(6,2,'App\\Policy\\RolePolicy','canIndex'),
(7,2,'App\\Policy\\RolePolicy','canView'),
(8,2,'App\\Policy\\RolePolicy','canViewPrivateNotes'),
(9,2,'App\\Policy\\RolesTablePolicy','canAdd'),
(10,2,'App\\Policy\\RolesTablePolicy','canAddPermission'),
(11,2,'App\\Policy\\RolesTablePolicy','canDelete'),
(12,2,'App\\Policy\\RolesTablePolicy','canDeletePermission'),
(13,2,'App\\Policy\\RolesTablePolicy','canEdit'),
(14,2,'App\\Policy\\RolesTablePolicy','canIndex'),
(15,2,'App\\Policy\\RolesTablePolicy','canSearchMembers'),
(16,2,'App\\Policy\\RolesTablePolicy','canView'),
(17,2,'App\\Policy\\RolesTablePolicy','canViewPrivateNotes'),
(18,3,'App\\Policy\\PermissionPolicy','canAdd'),
(19,3,'App\\Policy\\PermissionPolicy','canDelete'),
(20,3,'App\\Policy\\PermissionPolicy','canEdit'),
(21,3,'App\\Policy\\PermissionPolicy','canIndex'),
(22,3,'App\\Policy\\PermissionPolicy','canUpdatePolicy'),
(23,3,'App\\Policy\\PermissionPolicy','canView'),
(24,3,'App\\Policy\\PermissionPolicy','canViewPrivateNotes'),
(25,3,'App\\Policy\\PermissionsTablePolicy','canAdd'),
(26,3,'App\\Policy\\PermissionsTablePolicy','canDelete'),
(27,3,'App\\Policy\\PermissionsTablePolicy','canEdit'),
(28,3,'App\\Policy\\PermissionsTablePolicy','canIndex'),
(29,3,'App\\Policy\\PermissionsTablePolicy','canMatrix'),
(30,3,'App\\Policy\\PermissionsTablePolicy','canView'),
(31,3,'App\\Policy\\PermissionsTablePolicy','canViewPrivateNotes'),
(32,4,'App\\Policy\\BranchPolicy','canAdd'),
(33,4,'App\\Policy\\BranchPolicy','canDelete'),
(34,4,'App\\Policy\\BranchPolicy','canEdit'),
(35,4,'App\\Policy\\BranchPolicy','canIndex'),
(36,4,'App\\Policy\\BranchPolicy','canView'),
(37,4,'App\\Policy\\BranchPolicy','canViewPrivateNotes'),
(38,4,'App\\Policy\\BranchesTablePolicy','canAdd'),
(39,4,'App\\Policy\\BranchesTablePolicy','canDelete'),
(40,4,'App\\Policy\\BranchesTablePolicy','canEdit'),
(41,4,'App\\Policy\\BranchesTablePolicy','canIndex'),
(42,4,'App\\Policy\\BranchesTablePolicy','canView'),
(43,4,'App\\Policy\\BranchesTablePolicy','canViewPrivateNotes'),
(44,5,'App\\Policy\\AppSettingPolicy','canAdd'),
(45,5,'App\\Policy\\AppSettingPolicy','canDelete'),
(46,5,'App\\Policy\\AppSettingPolicy','canEdit'),
(47,5,'App\\Policy\\AppSettingPolicy','canIndex'),
(48,5,'App\\Policy\\AppSettingPolicy','canView'),
(49,5,'App\\Policy\\AppSettingPolicy','canViewPrivateNotes'),
(56,6,'App\\Policy\\MemberPolicy','canAdd'),
(57,6,'App\\Policy\\MemberPolicy','canAddNote'),
(58,6,'App\\Policy\\MemberPolicy','canChangePassword'),
(59,6,'App\\Policy\\MemberPolicy','canDelete'),
(60,6,'App\\Policy\\MemberPolicy','canEdit'),
(61,6,'App\\Policy\\MemberPolicy','canEditAdditionalInfo'),
(62,6,'App\\Policy\\MemberPolicy','canImportExpirationDates'),
(63,6,'App\\Policy\\MemberPolicy','canIndex'),
(64,6,'App\\Policy\\MemberPolicy','canPartialEdit'),
(65,6,'App\\Policy\\MemberPolicy','canSendMobileCardEmail'),
(66,6,'App\\Policy\\MemberPolicy','canVerifyMembership'),
(67,6,'App\\Policy\\MemberPolicy','canVerifyQueue'),
(68,6,'App\\Policy\\MemberPolicy','canView'),
(69,6,'App\\Policy\\MemberPolicy','canViewCard'),
(70,6,'App\\Policy\\MemberPolicy','canViewCardJson'),
(71,6,'App\\Policy\\MemberPolicy','canViewPrivateNotes'),
(79,6,'App\\Policy\\MembersTablePolicy','canAdd'),
(80,6,'App\\Policy\\MembersTablePolicy','canDelete'),
(81,6,'App\\Policy\\MembersTablePolicy','canEdit'),
(82,6,'App\\Policy\\MembersTablePolicy','canIndex'),
(83,6,'App\\Policy\\MembersTablePolicy','canVerifyQueue'),
(84,6,'App\\Policy\\MembersTablePolicy','canView'),
(85,6,'App\\Policy\\MembersTablePolicy','canViewPrivateNotes'),
(90,7,'App\\Policy\\ReportsControllerPolicy','canPermissionsWarrantsRoster'),
(91,7,'App\\Policy\\ReportsControllerPolicy','canRolesList'),
(94,8,'App\\Policy\\MemberPolicy','canIndex'),
(95,8,'App\\Policy\\MemberPolicy','canView'),
(96,8,'App\\Policy\\MemberPolicy','canViewCard'),
(97,8,'App\\Policy\\MemberPolicy','canViewCardJson'),
(98,11,'Activities\\Policy\\ActivitiesTablePolicy','canAdd'),
(99,11,'Activities\\Policy\\ActivitiesTablePolicy','canDelete'),
(100,11,'Activities\\Policy\\ActivitiesTablePolicy','canEdit'),
(101,11,'Activities\\Policy\\ActivitiesTablePolicy','canIndex'),
(102,11,'Activities\\Policy\\ActivitiesTablePolicy','canView'),
(103,11,'Activities\\Policy\\ActivitiesTablePolicy','canViewPrivateNotes'),
(104,11,'Activities\\Policy\\ActivityGroupPolicy','canAdd'),
(105,11,'Activities\\Policy\\ActivityGroupPolicy','canDelete'),
(106,11,'Activities\\Policy\\ActivityGroupPolicy','canEdit'),
(107,11,'Activities\\Policy\\ActivityGroupPolicy','canIndex'),
(108,11,'Activities\\Policy\\ActivityGroupPolicy','canView'),
(109,11,'Activities\\Policy\\ActivityGroupPolicy','canViewPrivateNotes'),
(110,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canAdd'),
(111,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canDelete'),
(112,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canEdit'),
(113,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canIndex'),
(114,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canView'),
(115,11,'Activities\\Policy\\ActivityGroupsTablePolicy','canViewPrivateNotes'),
(116,11,'Activities\\Policy\\ActivityPolicy','canAdd'),
(117,11,'Activities\\Policy\\ActivityPolicy','canDelete'),
(118,11,'Activities\\Policy\\ActivityPolicy','canEdit'),
(119,11,'Activities\\Policy\\ActivityPolicy','canIndex'),
(120,11,'Activities\\Policy\\ActivityPolicy','canView'),
(121,11,'Activities\\Policy\\ActivityPolicy','canViewPrivateNotes'),
(122,11,'Activities\\Policy\\ReportsControllerPolicy','canAuthorizations'),
(123,12,'Activities\\Policy\\AuthorizationPolicy','canRevoke'),
(124,13,'Activities\\Policy\\AuthorizationApprovalPolicy','canView'),
(125,13,'Activities\\Policy\\AuthorizationApprovalPolicy','canMyQueue'),
(126,13,'Activities\\Policy\\AuthorizationApprovalPolicy','canIndex'),
(127,14,'Activities\\Policy\\ReportsControllerPolicy','canAuthorizations'),
(134,21,'Officers\\Policy\\OfficePolicy','canAdd'),
(135,21,'Officers\\Policy\\OfficePolicy','canDelete'),
(136,21,'Officers\\Policy\\OfficePolicy','canEdit'),
(137,21,'Officers\\Policy\\OfficePolicy','canIndex'),
(138,21,'Officers\\Policy\\OfficePolicy','canView'),
(139,21,'Officers\\Policy\\OfficePolicy','canViewPrivateNotes'),
(140,21,'Officers\\Policy\\OfficesTablePolicy','canAdd'),
(141,21,'Officers\\Policy\\OfficesTablePolicy','canDelete'),
(142,21,'Officers\\Policy\\OfficesTablePolicy','canEdit'),
(143,21,'Officers\\Policy\\OfficesTablePolicy','canIndex'),
(144,21,'Officers\\Policy\\OfficesTablePolicy','canView'),
(145,21,'Officers\\Policy\\OfficesTablePolicy','canViewPrivateNotes'),
(146,22,'Officers\\Policy\\OfficerPolicy','canAssign'),
(147,22,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(148,22,'Officers\\Policy\\OfficerPolicy','canEdit'),
(149,22,'Officers\\Policy\\OfficerPolicy','canOfficers'),
(150,22,'Officers\\Policy\\OfficerPolicy','canOfficersByWarrantStatus'),
(151,22,'Officers\\Policy\\OfficerPolicy','canRelease'),
(152,22,'Officers\\Policy\\OfficerPolicy','canRequestWarrant'),
(153,22,'Officers\\Policy\\OfficerPolicy','canWorkWithAllOfficers'),
(154,22,'Officers\\Policy\\OfficerPolicy','canWorkWithOfficerDeputies'),
(155,22,'Officers\\Policy\\OfficerPolicy','canWorkWithOfficerDirectReports'),
(156,22,'Officers\\Policy\\OfficerPolicy','canWorkWithOfficerReportingTree'),
(157,23,'Officers\\Policy\\DepartmentPolicy','canAdd'),
(158,23,'Officers\\Policy\\DepartmentPolicy','canDelete'),
(159,23,'Officers\\Policy\\DepartmentPolicy','canEdit'),
(160,23,'Officers\\Policy\\DepartmentPolicy','canIndex'),
(161,23,'Officers\\Policy\\DepartmentPolicy','canView'),
(162,23,'Officers\\Policy\\DepartmentPolicy','canViewPrivateNotes'),
(163,24,'Officers\\Policy\\ReportsControllerPolicy','canDepartmentOfficersRoster'),
(170,31,'Awards\\Policy\\AwardPolicy','canAdd'),
(171,31,'Awards\\Policy\\AwardPolicy','canDelete'),
(172,31,'Awards\\Policy\\AwardPolicy','canEdit'),
(173,31,'Awards\\Policy\\AwardPolicy','canIndex'),
(174,31,'Awards\\Policy\\AwardPolicy','canView'),
(175,31,'Awards\\Policy\\AwardPolicy','canViewPrivateNotes'),
(176,31,'Awards\\Policy\\AwardsTablePolicy','canAdd'),
(177,31,'Awards\\Policy\\AwardsTablePolicy','canDelete'),
(178,31,'Awards\\Policy\\AwardsTablePolicy','canEdit'),
(179,31,'Awards\\Policy\\AwardsTablePolicy','canIndex'),
(180,31,'Awards\\Policy\\AwardsTablePolicy','canView'),
(181,31,'Awards\\Policy\\AwardsTablePolicy','canViewPrivateNotes'),
(182,32,'Awards\\Policy\\RecommendationPolicy','canIndex'),
(183,32,'Awards\\Policy\\RecommendationPolicy','canView'),
(184,32,'Awards\\Policy\\RecommendationPolicy','canViewEventRecommendations'),
(185,32,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedByMember'),
(186,32,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedForMember'),
(187,33,'Awards\\Policy\\RecommendationPolicy','canAdd'),
(188,33,'Awards\\Policy\\RecommendationPolicy','canAddNote'),
(189,33,'Awards\\Policy\\RecommendationPolicy','canApproveLevelArmigerous'),
(190,33,'Awards\\Policy\\RecommendationPolicy','canApproveLevelGrant'),
(191,33,'Awards\\Policy\\RecommendationPolicy','canApproveLevelNobility'),
(192,33,'Awards\\Policy\\RecommendationPolicy','canApproveLevelNon-Armigerous'),
(193,33,'Awards\\Policy\\RecommendationPolicy','canApproveLevelPeerage'),
(194,33,'Awards\\Policy\\RecommendationPolicy','canDelete'),
(195,33,'Awards\\Policy\\RecommendationPolicy','canEdit'),
(196,33,'Awards\\Policy\\RecommendationPolicy','canExport'),
(197,33,'Awards\\Policy\\RecommendationPolicy','canIndex'),
(198,33,'Awards\\Policy\\RecommendationPolicy','canUpdateStates'),
(199,33,'Awards\\Policy\\RecommendationPolicy','canUseBoard'),
(200,33,'Awards\\Policy\\RecommendationPolicy','canView'),
(201,33,'Awards\\Policy\\RecommendationPolicy','canViewEventRecommendations'),
(202,33,'Awards\\Policy\\RecommendationPolicy','canViewHidden'),
(203,33,'Awards\\Policy\\RecommendationPolicy','canViewPrivateNotes'),
(204,33,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedByMember'),
(205,33,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedForMember'),
(206,1067,'App\\Policy\\WarrantPolicy','canAllWarrants'),
(207,1067,'App\\Policy\\WarrantPolicy','canIndex'),
(208,1067,'App\\Policy\\WarrantPolicy','canView'),
(209,1067,'App\\Policy\\WarrantsTablePolicy','canIndex'),
(210,1067,'App\\Policy\\WarrantsTablePolicy','canView'),
(211,1067,'App\\Policy\\WarrantRostersTablePolicy','canView'),
(212,1067,'App\\Policy\\WarrantRostersTablePolicy','canIndex'),
(213,1067,'App\\Policy\\WarrantRostersTablePolicy','canAllRosters'),
(214,1068,'App\\Policy\\WarrantRosterPolicy','canAdd'),
(215,1068,'App\\Policy\\WarrantRosterPolicy','canAllRosters'),
(216,1068,'App\\Policy\\WarrantRosterPolicy','canApprove'),
(217,1068,'App\\Policy\\WarrantRosterPolicy','canDecline'),
(218,1068,'App\\Policy\\WarrantRosterPolicy','canDelete'),
(219,1068,'App\\Policy\\WarrantRosterPolicy','canEdit'),
(220,1068,'App\\Policy\\WarrantRosterPolicy','canIndex'),
(221,1068,'App\\Policy\\WarrantRosterPolicy','canView'),
(222,1068,'App\\Policy\\WarrantRosterPolicy','canViewPrivateNotes'),
(223,1067,'App\\Policy\\WarrantRosterPolicy','canAllRosters'),
(224,1067,'App\\Policy\\WarrantRosterPolicy','canIndex'),
(225,1067,'App\\Policy\\WarrantRosterPolicy','canView'),
(226,1068,'App\\Policy\\WarrantRostersTablePolicy','canAdd'),
(227,1068,'App\\Policy\\WarrantRostersTablePolicy','canAllRosters'),
(228,1068,'App\\Policy\\WarrantRostersTablePolicy','canDelete'),
(229,1068,'App\\Policy\\WarrantRostersTablePolicy','canEdit'),
(230,1068,'App\\Policy\\WarrantRostersTablePolicy','canIndex'),
(231,1068,'App\\Policy\\WarrantRostersTablePolicy','canView'),
(232,1068,'App\\Policy\\WarrantRostersTablePolicy','canViewPrivateNotes'),
(233,1068,'App\\Policy\\WarrantPolicy','canAdd'),
(234,1068,'App\\Policy\\WarrantPolicy','canAllWarrants'),
(235,1068,'App\\Policy\\WarrantPolicy','canDeactivate'),
(236,1068,'App\\Policy\\WarrantPolicy','canDeclineWarrantInRoster'),
(237,1068,'App\\Policy\\WarrantPolicy','canDelete'),
(238,1068,'App\\Policy\\WarrantPolicy','canEdit'),
(239,1068,'App\\Policy\\WarrantPolicy','canIndex'),
(240,1068,'App\\Policy\\WarrantPolicy','canView'),
(241,1068,'App\\Policy\\WarrantPolicy','canViewPrivateNotes'),
(242,1069,'App\\Policy\\BranchPolicy','canView'),
(243,1069,'App\\Policy\\BranchPolicy','canIndex'),
(244,1070,'Officers\\Policy\\OfficerPolicy','canAssign'),
(245,1070,'Officers\\Policy\\OfficerPolicy','canWorkWithAllOfficers'),
(247,1070,'Officers\\Policy\\OfficesTablePolicy','canView'),
(248,1070,'Officers\\Policy\\OfficesTablePolicy','canIndex'),
(249,1071,'Officers\\Policy\\OfficesTablePolicy','canIndex'),
(250,1071,'Officers\\Policy\\OfficesTablePolicy','canView'),
(251,1071,'Officers\\Policy\\OfficerPolicy','canWorkWithAllOfficers'),
(253,1070,'App\\Policy\\BranchPolicy','canIndex'),
(254,1070,'App\\Policy\\BranchPolicy','canView'),
(255,1071,'App\\Policy\\BranchPolicy','canIndex'),
(256,1071,'App\\Policy\\BranchPolicy','canView'),
(257,1070,'Officers\\Policy\\OfficerPolicy','canRequestWarrant'),
(258,1072,'Officers\\Policy\\OfficerPolicy','canRequestWarrant'),
(259,1072,'Officers\\Policy\\RostersControllerPolicy','canAdd'),
(260,1072,'Officers\\Policy\\RostersControllerPolicy','canCreateRoster'),
(261,1072,'Officers\\Policy\\RostersControllerPolicy','canView'),
(262,1072,'Officers\\Policy\\RostersControllerPolicy','canIndex'),
(263,1075,'Awards\\Policy\\RecommendationsTablePolicy','canView'),
(264,1075,'Awards\\Policy\\RecommendationsTablePolicy','canIndex'),
(265,1075,'Awards\\Policy\\RecommendationPolicy','canUseBoard'),
(266,1075,'Awards\\Policy\\RecommendationPolicy','canExport'),
(267,1075,'Awards\\Policy\\RecommendationPolicy','canViewEventRecommendations'),
(268,1075,'Awards\\Policy\\RecommendationPolicy','canView'),
(269,1075,'Awards\\Policy\\RecommendationPolicy','canEdit'),
(270,1075,'Awards\\Policy\\RecommendationPolicy','canIndex'),
(271,1075,'Awards\\Policy\\RecommendationPolicy','canUpdateStates'),
(272,1075,'Awards\\Policy\\RecommendationPolicy','canAddNote'),
(273,1075,'Awards\\Policy\\RecommendationPolicy','canViewPrivateNotes'),
(275,1075,'Awards\\Policy\\RecommendationPolicy','canAdd'),
(276,1075,'Awards\\Policy\\RecommendationPolicy','canDelete'),
(277,1075,'Awards\\Policy\\RecommendationPolicy','canViewHidden'),
(278,1075,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedForMember'),
(279,1075,'Awards\\Policy\\RecommendationPolicy','canViewSubmittedByMember'),
(281,1075,'App\\Policy\\MemberPolicy','canIndex'),
(283,1075,'App\\Policy\\MembersTablePolicy','canIndex'),
(284,1075,'Awards\\Policy\\RecommendationsTablePolicy','canViewPrivateNotes'),
(301,1076,'App\\Policy\\BranchesTablePolicy','canView'),
(302,1076,'App\\Policy\\BranchesTablePolicy','canIndex'),
(304,1076,'Officers\\Policy\\OfficerPolicy','canRelease'),
(305,1076,'Officers\\Policy\\OfficerPolicy','canAssign'),
(306,1076,'Officers\\Policy\\OfficerPolicy','canRequestWarrant'),
(307,1076,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(308,1076,'Officers\\Policy\\OfficerPolicy','canOfficersByWarrantStatus'),
(309,1076,'App\\Policy\\BranchPolicy','canView'),
(311,1076,'App\\Policy\\BranchPolicy','canIndex'),
(312,1076,'Officers\\Policy\\OfficesTablePolicy','canIndex'),
(313,1076,'Officers\\Policy\\OfficesTablePolicy','canView'),
(315,1077,'App\\Policy\\PermissionPolicy','canUpdatePolicy'),
(316,1077,'App\\Policy\\PermissionPolicy','canAdd'),
(317,1077,'App\\Policy\\PermissionPolicy','canEdit'),
(318,1077,'App\\Policy\\PermissionPolicy','canDelete'),
(319,1077,'App\\Policy\\PermissionPolicy','canView'),
(320,1077,'App\\Policy\\PermissionPolicy','canIndex'),
(321,1077,'App\\Policy\\PermissionPolicy','canViewPrivateNotes'),
(322,1077,'App\\Policy\\WarrantPolicy','canAllWarrants'),
(323,1077,'App\\Policy\\WarrantPolicy','canDeactivate'),
(324,1077,'App\\Policy\\WarrantPolicy','canDeclineWarrantInRoster'),
(325,1077,'App\\Policy\\WarrantPolicy','canAdd'),
(326,1077,'App\\Policy\\WarrantPolicy','canEdit'),
(327,1077,'App\\Policy\\WarrantPolicy','canDelete'),
(328,1077,'App\\Policy\\WarrantPolicy','canView'),
(329,1077,'App\\Policy\\WarrantPolicy','canIndex'),
(330,1077,'App\\Policy\\WarrantPolicy','canViewPrivateNotes'),
(331,1077,'App\\Policy\\BranchPolicy','canAdd'),
(332,1077,'App\\Policy\\BranchPolicy','canEdit'),
(333,1077,'App\\Policy\\BranchPolicy','canDelete'),
(334,1077,'App\\Policy\\BranchPolicy','canView'),
(335,1077,'App\\Policy\\BranchPolicy','canIndex'),
(336,1077,'App\\Policy\\BranchPolicy','canViewPrivateNotes'),
(337,1077,'App\\Policy\\BranchesTablePolicy','canAdd'),
(338,1077,'App\\Policy\\BranchesTablePolicy','canEdit'),
(339,1077,'App\\Policy\\BranchesTablePolicy','canDelete'),
(340,1077,'App\\Policy\\BranchesTablePolicy','canView'),
(341,1077,'App\\Policy\\BranchesTablePolicy','canIndex'),
(342,1077,'App\\Policy\\BranchesTablePolicy','canViewPrivateNotes'),
(343,1077,'App\\Policy\\MemberPolicy','canView'),
(344,1077,'App\\Policy\\MemberPolicy','canPartialEdit'),
(345,1077,'App\\Policy\\MemberPolicy','canViewCard'),
(346,1077,'App\\Policy\\MemberPolicy','canSendMobileCardEmail'),
(347,1077,'App\\Policy\\MemberPolicy','canAddNote'),
(348,1077,'App\\Policy\\MemberPolicy','canChangePassword'),
(349,1077,'App\\Policy\\MemberPolicy','canViewCardJson'),
(350,1077,'App\\Policy\\MemberPolicy','canDelete'),
(351,1077,'App\\Policy\\MemberPolicy','canImportExpirationDates'),
(352,1077,'App\\Policy\\MemberPolicy','canVerifyMembership'),
(353,1077,'App\\Policy\\MemberPolicy','canVerifyQueue'),
(354,1077,'App\\Policy\\MemberPolicy','canEditAdditionalInfo'),
(355,1077,'App\\Policy\\MemberPolicy','canAdd'),
(356,1077,'App\\Policy\\MemberPolicy','canEdit'),
(357,1077,'App\\Policy\\MemberPolicy','canIndex'),
(358,1077,'App\\Policy\\MemberPolicy','canViewPrivateNotes'),
(359,1077,'App\\Policy\\MemberRolePolicy','canDeactivate'),
(360,1077,'App\\Policy\\MemberRolePolicy','canAdd'),
(361,1077,'App\\Policy\\MemberRolePolicy','canEdit'),
(362,1077,'App\\Policy\\MemberRolePolicy','canDelete'),
(363,1077,'App\\Policy\\MemberRolePolicy','canView'),
(364,1077,'App\\Policy\\MemberRolePolicy','canIndex'),
(365,1077,'App\\Policy\\MemberRolePolicy','canViewPrivateNotes'),
(366,1077,'App\\Policy\\MemberRolesTablePolicy','canDeactivate'),
(367,1077,'App\\Policy\\MemberRolesTablePolicy','canAdd'),
(368,1077,'App\\Policy\\MemberRolesTablePolicy','canEdit'),
(369,1077,'App\\Policy\\MemberRolesTablePolicy','canDelete'),
(370,1077,'App\\Policy\\MemberRolesTablePolicy','canView'),
(371,1077,'App\\Policy\\MemberRolesTablePolicy','canIndex'),
(372,1077,'App\\Policy\\MemberRolesTablePolicy','canViewPrivateNotes'),
(373,1077,'App\\Policy\\MembersTablePolicy','canVerifyQueue'),
(374,1077,'App\\Policy\\MembersTablePolicy','canAdd'),
(375,1077,'App\\Policy\\MembersTablePolicy','canEdit'),
(376,1077,'App\\Policy\\MembersTablePolicy','canDelete'),
(377,1077,'App\\Policy\\MembersTablePolicy','canView'),
(378,1077,'App\\Policy\\MembersTablePolicy','canIndex'),
(379,1077,'App\\Policy\\MembersTablePolicy','canViewPrivateNotes'),
(380,1077,'App\\Policy\\NotePolicy','canAdd'),
(381,1077,'App\\Policy\\NotePolicy','canEdit'),
(382,1077,'App\\Policy\\NotePolicy','canDelete'),
(383,1077,'App\\Policy\\NotePolicy','canView'),
(384,1077,'App\\Policy\\NotesTablePolicy','canAdd'),
(385,1077,'App\\Policy\\NotesTablePolicy','canEdit'),
(386,1077,'App\\Policy\\NotesTablePolicy','canDelete'),
(387,1077,'App\\Policy\\NotesTablePolicy','canView'),
(388,1077,'App\\Policy\\PermissionsTablePolicy','canMatrix'),
(389,1077,'App\\Policy\\PermissionsTablePolicy','canAdd'),
(390,1077,'App\\Policy\\PermissionsTablePolicy','canEdit'),
(391,1077,'App\\Policy\\PermissionsTablePolicy','canDelete'),
(392,1077,'App\\Policy\\PermissionsTablePolicy','canView'),
(393,1077,'App\\Policy\\PermissionsTablePolicy','canIndex'),
(394,1077,'App\\Policy\\PermissionsTablePolicy','canViewPrivateNotes'),
(395,1077,'App\\Policy\\ReportsControllerPolicy','canRolesList'),
(396,1077,'App\\Policy\\ReportsControllerPolicy','canPermissionsWarrantsRoster'),
(397,1077,'App\\Policy\\ReportsControllerPolicy','canAdd'),
(398,1077,'App\\Policy\\ReportsControllerPolicy','canEdit'),
(399,1077,'App\\Policy\\ReportsControllerPolicy','canDelete'),
(400,1077,'App\\Policy\\ReportsControllerPolicy','canView'),
(401,1077,'App\\Policy\\ReportsControllerPolicy','canIndex'),
(402,1077,'App\\Policy\\ReportsControllerPolicy','canViewPrivateNotes'),
(403,1077,'App\\Policy\\RolePolicy','canDeletePermission'),
(404,1077,'App\\Policy\\RolePolicy','canAddPermission'),
(405,1077,'App\\Policy\\RolePolicy','canAdd'),
(406,1077,'App\\Policy\\RolePolicy','canEdit'),
(407,1077,'App\\Policy\\RolePolicy','canDelete'),
(408,1077,'App\\Policy\\RolePolicy','canView'),
(409,1077,'App\\Policy\\RolePolicy','canIndex'),
(410,1077,'App\\Policy\\RolePolicy','canViewPrivateNotes'),
(411,1077,'App\\Policy\\RolesTablePolicy','canDeletePermission'),
(412,1077,'App\\Policy\\RolesTablePolicy','canAddPermission'),
(413,1077,'App\\Policy\\RolesTablePolicy','canSearchMembers'),
(414,1077,'App\\Policy\\RolesTablePolicy','canAdd'),
(415,1077,'App\\Policy\\RolesTablePolicy','canEdit'),
(416,1077,'App\\Policy\\RolesTablePolicy','canDelete'),
(417,1077,'App\\Policy\\RolesTablePolicy','canView'),
(418,1077,'App\\Policy\\RolesTablePolicy','canIndex'),
(419,1077,'App\\Policy\\RolesTablePolicy','canViewPrivateNotes'),
(420,1077,'App\\Policy\\WarrantPeriodPolicy','canAdd'),
(421,1077,'App\\Policy\\WarrantPeriodPolicy','canEdit'),
(422,1077,'App\\Policy\\WarrantPeriodPolicy','canDelete'),
(423,1077,'App\\Policy\\WarrantPeriodPolicy','canView'),
(424,1077,'App\\Policy\\WarrantPeriodPolicy','canIndex'),
(425,1077,'App\\Policy\\WarrantPeriodPolicy','canViewPrivateNotes'),
(426,1077,'App\\Policy\\WarrantRosterPolicy','canAllRosters'),
(427,1077,'App\\Policy\\WarrantRosterPolicy','canApprove'),
(428,1077,'App\\Policy\\WarrantRosterPolicy','canDecline'),
(429,1077,'App\\Policy\\WarrantRosterPolicy','canAdd'),
(430,1077,'App\\Policy\\WarrantRosterPolicy','canEdit'),
(431,1077,'App\\Policy\\WarrantRosterPolicy','canDelete'),
(432,1077,'App\\Policy\\WarrantRosterPolicy','canView'),
(433,1077,'App\\Policy\\WarrantRosterPolicy','canIndex'),
(434,1077,'App\\Policy\\WarrantRosterPolicy','canViewPrivateNotes'),
(435,1077,'App\\Policy\\WarrantRostersTablePolicy','canView'),
(436,1077,'App\\Policy\\WarrantRostersTablePolicy','canIndex'),
(437,1077,'App\\Policy\\WarrantRostersTablePolicy','canAllRosters'),
(438,1077,'App\\Policy\\WarrantRostersTablePolicy','canAdd'),
(439,1077,'App\\Policy\\WarrantRostersTablePolicy','canEdit'),
(440,1077,'App\\Policy\\WarrantRostersTablePolicy','canDelete'),
(441,1077,'App\\Policy\\WarrantRostersTablePolicy','canViewPrivateNotes'),
(442,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canAdd'),
(443,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canEdit'),
(444,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canDelete'),
(445,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canView'),
(446,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canIndex'),
(447,1077,'App\\Policy\\WarrantPeriodsTablePolicy','canViewPrivateNotes'),
(448,1077,'App\\Policy\\WarrantsTablePolicy','canView'),
(449,1077,'App\\Policy\\WarrantsTablePolicy','canIndex'),
(450,1077,'App\\Policy\\WarrantsTablePolicy','canDeclineWarrantInRoster'),
(451,1077,'App\\Policy\\WarrantsTablePolicy','canDeactivate'),
(452,1077,'App\\Policy\\WarrantsTablePolicy','canAdd'),
(453,1077,'App\\Policy\\WarrantsTablePolicy','canEdit'),
(454,1077,'App\\Policy\\WarrantsTablePolicy','canDelete'),
(455,1077,'App\\Policy\\WarrantsTablePolicy','canViewPrivateNotes'),
(456,1077,'App\\Policy\\AppSettingPolicy','canAdd'),
(457,1077,'App\\Policy\\AppSettingPolicy','canEdit'),
(458,1077,'App\\Policy\\AppSettingPolicy','canDelete'),
(459,1077,'App\\Policy\\AppSettingPolicy','canView'),
(460,1077,'App\\Policy\\AppSettingPolicy','canIndex'),
(461,1077,'App\\Policy\\AppSettingPolicy','canViewPrivateNotes'),
(462,1077,'App\\Policy\\AppSettingsTablePolicy','canAdd'),
(463,1077,'App\\Policy\\AppSettingsTablePolicy','canEdit'),
(464,1077,'App\\Policy\\AppSettingsTablePolicy','canDelete'),
(465,1077,'App\\Policy\\AppSettingsTablePolicy','canView'),
(466,1077,'App\\Policy\\AppSettingsTablePolicy','canIndex'),
(467,1077,'App\\Policy\\AppSettingsTablePolicy','canViewPrivateNotes'),
(468,1077,'Activities\\Policy\\ActivitiesTablePolicy','canAdd'),
(469,1077,'Activities\\Policy\\ActivitiesTablePolicy','canEdit'),
(470,1077,'Activities\\Policy\\ActivitiesTablePolicy','canDelete'),
(471,1077,'Activities\\Policy\\ActivitiesTablePolicy','canView'),
(472,1077,'Activities\\Policy\\ActivitiesTablePolicy','canIndex'),
(473,1077,'Activities\\Policy\\ActivitiesTablePolicy','canViewPrivateNotes'),
(474,1077,'Activities\\Policy\\ActivityGroupPolicy','canAdd'),
(475,1077,'Activities\\Policy\\ActivityGroupPolicy','canEdit'),
(476,1077,'Activities\\Policy\\ActivityGroupPolicy','canDelete'),
(477,1077,'Activities\\Policy\\ActivityGroupPolicy','canView'),
(478,1077,'Activities\\Policy\\ActivityGroupPolicy','canIndex'),
(479,1077,'Activities\\Policy\\ActivityGroupPolicy','canViewPrivateNotes'),
(480,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canAdd'),
(481,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canEdit'),
(482,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canDelete'),
(483,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canView'),
(484,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canIndex'),
(485,1077,'Activities\\Policy\\ActivityGroupsTablePolicy','canViewPrivateNotes'),
(486,1077,'Activities\\Policy\\ActivityPolicy','canAdd'),
(487,1077,'Activities\\Policy\\ActivityPolicy','canEdit'),
(488,1077,'Activities\\Policy\\ActivityPolicy','canDelete'),
(489,1077,'Activities\\Policy\\ActivityPolicy','canView'),
(490,1077,'Activities\\Policy\\ActivityPolicy','canIndex'),
(491,1077,'Activities\\Policy\\ActivityPolicy','canViewPrivateNotes'),
(492,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canApprove'),
(493,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canDeny'),
(494,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canView'),
(495,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canMyQueue'),
(496,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canAvailableApproversList'),
(497,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canAdd'),
(498,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canEdit'),
(499,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canDelete'),
(500,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canIndex'),
(501,1077,'Activities\\Policy\\AuthorizationApprovalPolicy','canViewPrivateNotes'),
(502,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canMyQueue'),
(503,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canAdd'),
(504,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canEdit'),
(505,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canDelete'),
(506,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canView'),
(507,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canIndex'),
(508,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canViewPrivateNotes'),
(509,1077,'Activities\\Policy\\AuthorizationPolicy','canRevoke'),
(510,1077,'Activities\\Policy\\AuthorizationPolicy','canAdd'),
(511,1077,'Activities\\Policy\\AuthorizationPolicy','canRenew'),
(512,1077,'Activities\\Policy\\AuthorizationPolicy','canMemberAuthorizations'),
(513,1077,'Activities\\Policy\\AuthorizationPolicy','canEdit'),
(514,1077,'Activities\\Policy\\AuthorizationPolicy','canDelete'),
(515,1077,'Activities\\Policy\\AuthorizationPolicy','canView'),
(516,1077,'Activities\\Policy\\AuthorizationPolicy','canIndex'),
(517,1077,'Activities\\Policy\\AuthorizationPolicy','canViewPrivateNotes'),
(518,1077,'Activities\\Policy\\ReportsControllerPolicy','canActivityWarrantsRoster'),
(519,1077,'Activities\\Policy\\ReportsControllerPolicy','canAuthorizations'),
(520,1077,'Activities\\Policy\\ReportsControllerPolicy','canAdd'),
(521,1077,'Activities\\Policy\\ReportsControllerPolicy','canEdit'),
(522,1077,'Activities\\Policy\\ReportsControllerPolicy','canDelete'),
(523,1077,'Activities\\Policy\\ReportsControllerPolicy','canView'),
(524,1077,'Activities\\Policy\\ReportsControllerPolicy','canIndex'),
(525,1077,'Activities\\Policy\\ReportsControllerPolicy','canViewPrivateNotes'),
(526,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canAdd'),
(527,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canEdit'),
(528,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canDelete'),
(529,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canView'),
(530,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canIndex'),
(531,1077,'Awards\\Policy\\RecommendationsStatesLogTablePolicy','canViewPrivateNotes'),
(532,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canAdd'),
(533,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canEdit'),
(534,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canDelete'),
(535,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canView'),
(536,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canIndex'),
(537,1077,'Awards\\Policy\\RecommendationsStatesLogPolicy','canViewPrivateNotes'),
(557,1077,'Awards\\Policy\\AwardPolicy','canAdd'),
(558,1077,'Awards\\Policy\\AwardPolicy','canEdit'),
(559,1077,'Awards\\Policy\\AwardPolicy','canDelete'),
(560,1077,'Awards\\Policy\\AwardPolicy','canView'),
(561,1077,'Awards\\Policy\\AwardPolicy','canIndex'),
(562,1077,'Awards\\Policy\\AwardPolicy','canViewPrivateNotes'),
(563,1077,'Awards\\Policy\\AwardsTablePolicy','canAdd'),
(564,1077,'Awards\\Policy\\AwardsTablePolicy','canEdit'),
(565,1077,'Awards\\Policy\\AwardsTablePolicy','canDelete'),
(566,1077,'Awards\\Policy\\AwardsTablePolicy','canView'),
(567,1077,'Awards\\Policy\\AwardsTablePolicy','canIndex'),
(568,1077,'Awards\\Policy\\AwardsTablePolicy','canViewPrivateNotes'),
(569,1077,'Awards\\Policy\\DomainPolicy','canAdd'),
(570,1077,'Awards\\Policy\\DomainPolicy','canEdit'),
(571,1077,'Awards\\Policy\\DomainPolicy','canDelete'),
(572,1077,'Awards\\Policy\\DomainPolicy','canView'),
(573,1077,'Awards\\Policy\\DomainPolicy','canIndex'),
(574,1077,'Awards\\Policy\\DomainPolicy','canViewPrivateNotes'),
(575,1077,'Awards\\Policy\\DomainsTablePolicy','canAdd'),
(576,1077,'Awards\\Policy\\DomainsTablePolicy','canEdit'),
(577,1077,'Awards\\Policy\\DomainsTablePolicy','canDelete'),
(578,1077,'Awards\\Policy\\DomainsTablePolicy','canView'),
(579,1077,'Awards\\Policy\\DomainsTablePolicy','canIndex'),
(580,1077,'Awards\\Policy\\DomainsTablePolicy','canViewPrivateNotes'),
(581,1077,'Awards\\Policy\\EventPolicy','canAllEvents'),
(582,1077,'Awards\\Policy\\EventPolicy','canAdd'),
(583,1077,'Awards\\Policy\\EventPolicy','canEdit'),
(584,1077,'Awards\\Policy\\EventPolicy','canDelete'),
(585,1077,'Awards\\Policy\\EventPolicy','canView'),
(586,1077,'Awards\\Policy\\EventPolicy','canIndex'),
(587,1077,'Awards\\Policy\\EventPolicy','canViewPrivateNotes'),
(588,1077,'Awards\\Policy\\EventsTablePolicy','canAdd'),
(589,1077,'Awards\\Policy\\EventsTablePolicy','canEdit'),
(590,1077,'Awards\\Policy\\EventsTablePolicy','canDelete'),
(591,1077,'Awards\\Policy\\EventsTablePolicy','canView'),
(592,1077,'Awards\\Policy\\EventsTablePolicy','canIndex'),
(593,1077,'Awards\\Policy\\EventsTablePolicy','canViewPrivateNotes'),
(594,1077,'Awards\\Policy\\LevelPolicy','canAdd'),
(595,1077,'Awards\\Policy\\LevelPolicy','canEdit'),
(596,1077,'Awards\\Policy\\LevelPolicy','canDelete'),
(597,1077,'Awards\\Policy\\LevelPolicy','canView'),
(598,1077,'Awards\\Policy\\LevelPolicy','canIndex'),
(599,1077,'Awards\\Policy\\LevelPolicy','canViewPrivateNotes'),
(600,1077,'Awards\\Policy\\LevelsTablePolicy','canAdd'),
(601,1077,'Awards\\Policy\\LevelsTablePolicy','canEdit'),
(602,1077,'Awards\\Policy\\LevelsTablePolicy','canDelete'),
(603,1077,'Awards\\Policy\\LevelsTablePolicy','canView'),
(604,1077,'Awards\\Policy\\LevelsTablePolicy','canIndex'),
(605,1077,'Awards\\Policy\\LevelsTablePolicy','canViewPrivateNotes'),
(606,1077,'Awards\\Policy\\RecommendationsTablePolicy','canAdd'),
(607,1077,'Awards\\Policy\\RecommendationsTablePolicy','canEdit'),
(608,1077,'Awards\\Policy\\RecommendationsTablePolicy','canDelete'),
(609,1077,'Awards\\Policy\\RecommendationsTablePolicy','canView'),
(610,1077,'Awards\\Policy\\RecommendationsTablePolicy','canIndex'),
(611,1077,'Awards\\Policy\\RecommendationsTablePolicy','canViewPrivateNotes'),
(612,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canSubmit'),
(613,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canAdd'),
(614,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canEdit'),
(615,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canDelete'),
(616,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canView'),
(617,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canIndex'),
(618,1077,'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy','canViewPrivateNotes'),
(619,1077,'Officers\\Policy\\DepartmentPolicy','canAdd'),
(620,1077,'Officers\\Policy\\DepartmentPolicy','canEdit'),
(621,1077,'Officers\\Policy\\DepartmentPolicy','canDelete'),
(622,1077,'Officers\\Policy\\DepartmentPolicy','canView'),
(623,1077,'Officers\\Policy\\DepartmentPolicy','canIndex'),
(624,1077,'Officers\\Policy\\DepartmentPolicy','canViewPrivateNotes'),
(636,1077,'Officers\\Policy\\OfficePolicy','canAdd'),
(637,1077,'Officers\\Policy\\OfficePolicy','canEdit'),
(638,1077,'Officers\\Policy\\OfficePolicy','canDelete'),
(639,1077,'Officers\\Policy\\OfficePolicy','canView'),
(640,1077,'Officers\\Policy\\OfficePolicy','canIndex'),
(641,1077,'Officers\\Policy\\OfficePolicy','canViewPrivateNotes'),
(642,1077,'Officers\\Policy\\OfficesTablePolicy','canAdd'),
(643,1077,'Officers\\Policy\\OfficesTablePolicy','canEdit'),
(644,1077,'Officers\\Policy\\OfficesTablePolicy','canDelete'),
(645,1077,'Officers\\Policy\\OfficesTablePolicy','canView'),
(646,1077,'Officers\\Policy\\OfficesTablePolicy','canIndex'),
(647,1077,'Officers\\Policy\\OfficesTablePolicy','canViewPrivateNotes'),
(648,1077,'Officers\\Policy\\ReportsControllerPolicy','canDepartmentOfficersRoster'),
(649,1077,'Officers\\Policy\\ReportsControllerPolicy','canAdd'),
(650,1077,'Officers\\Policy\\ReportsControllerPolicy','canEdit'),
(651,1077,'Officers\\Policy\\ReportsControllerPolicy','canDelete'),
(652,1077,'Officers\\Policy\\ReportsControllerPolicy','canView'),
(653,1077,'Officers\\Policy\\ReportsControllerPolicy','canIndex'),
(654,1077,'Officers\\Policy\\ReportsControllerPolicy','canViewPrivateNotes'),
(655,1077,'Officers\\Policy\\RostersControllerPolicy','canAdd'),
(656,1077,'Officers\\Policy\\RostersControllerPolicy','canCreateRoster'),
(657,1077,'Officers\\Policy\\RostersControllerPolicy','canEdit'),
(658,1077,'Officers\\Policy\\RostersControllerPolicy','canDelete'),
(659,1077,'Officers\\Policy\\RostersControllerPolicy','canView'),
(660,1077,'Officers\\Policy\\RostersControllerPolicy','canIndex'),
(661,1077,'Officers\\Policy\\RostersControllerPolicy','canViewPrivateNotes'),
(662,1077,'Queue\\Policy\\QueuedJobPolicy','canAddJob'),
(663,1077,'Queue\\Policy\\QueuedJobPolicy','canResetJob'),
(664,1077,'Queue\\Policy\\QueuedJobPolicy','canRemoveJob'),
(665,1077,'Queue\\Policy\\QueuedJobPolicy','canProcesses'),
(666,1077,'Queue\\Policy\\QueuedJobPolicy','canReset'),
(667,1077,'Queue\\Policy\\QueuedJobPolicy','canFlush'),
(668,1077,'Queue\\Policy\\QueuedJobPolicy','canHardReset'),
(669,1077,'Queue\\Policy\\QueuedJobPolicy','canStats'),
(670,1077,'Queue\\Policy\\QueuedJobPolicy','canViewClasses'),
(671,1077,'Queue\\Policy\\QueuedJobPolicy','canImport'),
(672,1077,'Queue\\Policy\\QueuedJobPolicy','canData'),
(673,1077,'Queue\\Policy\\QueuedJobPolicy','canExecute'),
(674,1077,'Queue\\Policy\\QueuedJobPolicy','canTest'),
(675,1077,'Queue\\Policy\\QueuedJobPolicy','canMigrate'),
(676,1077,'Queue\\Policy\\QueuedJobPolicy','canTerminate'),
(677,1077,'Queue\\Policy\\QueuedJobPolicy','canCleanup'),
(678,1077,'Queue\\Policy\\QueuedJobPolicy','canAdd'),
(679,1077,'Queue\\Policy\\QueuedJobPolicy','canEdit'),
(680,1077,'Queue\\Policy\\QueuedJobPolicy','canDelete'),
(681,1077,'Queue\\Policy\\QueuedJobPolicy','canView'),
(682,1077,'Queue\\Policy\\QueuedJobPolicy','canIndex'),
(683,1077,'Queue\\Policy\\QueuedJobPolicy','canViewPrivateNotes'),
(684,1077,'App\\Policy\\PermissionPolicy','canMatrix'),
(685,1077,'App\\Policy\\NotePolicy','canIndex'),
(686,1077,'App\\Policy\\NotePolicy','canViewPrivateNotes'),
(687,1077,'Activities\\Policy\\AuthorizationApprovalsTablePolicy','canAllQueues'),
(692,1077,'Officers\\Policy\\DepartmentPolicy','canSeeAllDepartments'),
(693,1077,'Officers\\Policy\\DepartmentsTablePolicy','canAdd'),
(694,1077,'Officers\\Policy\\DepartmentsTablePolicy','canEdit'),
(695,1077,'Officers\\Policy\\DepartmentsTablePolicy','canDelete'),
(696,1077,'Officers\\Policy\\DepartmentsTablePolicy','canView'),
(697,1077,'Officers\\Policy\\DepartmentsTablePolicy','canIndex'),
(698,1077,'Officers\\Policy\\DepartmentsTablePolicy','canViewPrivateNotes'),
(699,1077,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(700,1077,'Officers\\Policy\\OfficerPolicy','canWorkWithAllOfficers'),
(704,1077,'Officers\\Policy\\OfficerPolicy','canRelease'),
(705,1077,'Officers\\Policy\\OfficerPolicy','canRequestWarrant'),
(706,1077,'Officers\\Policy\\OfficerPolicy','canOfficersByWarrantStatus'),
(707,1077,'Officers\\Policy\\OfficerPolicy','canEdit'),
(708,1077,'Officers\\Policy\\OfficerPolicy','canOfficers'),
(709,1077,'Officers\\Policy\\OfficerPolicy','canAssign'),
(710,1077,'Officers\\Policy\\OfficerPolicy','canAdd'),
(711,1077,'Officers\\Policy\\OfficerPolicy','canDelete'),
(712,1077,'Officers\\Policy\\OfficerPolicy','canView'),
(713,1077,'Officers\\Policy\\OfficerPolicy','canIndex'),
(714,1077,'Officers\\Policy\\OfficerPolicy','canViewPrivateNotes'),
(715,1076,'Officers\\Policy\\OfficerPolicy','canWorkWithOfficerReportingTree'),
(718,33,'Awards\\Policy\\RecommendationsTablePolicy','canAdd'),
(719,33,'Awards\\Policy\\RecommendationsTablePolicy','canEdit'),
(720,33,'Awards\\Policy\\RecommendationsTablePolicy','canDelete'),
(721,33,'Awards\\Policy\\RecommendationsTablePolicy','canView'),
(722,33,'Awards\\Policy\\RecommendationsTablePolicy','canIndex'),
(723,33,'Awards\\Policy\\RecommendationsTablePolicy','canViewPrivateNotes'),
(724,8,'App\\Policy\\MembersTablePolicy','canView'),
(725,8,'App\\Policy\\MembersTablePolicy','canIndex'),
(735,1,'App\\Policy\\WarrantPolicy','canIndex'),
(736,1,'App\\Policy\\WarrantPolicy','canView'),
(745,1,'App\\Policy\\WarrantsTablePolicy','canIndex'),
(746,1,'App\\Policy\\WarrantsTablePolicy','canView'),
(747,1,'App\\Policy\\WarrantRostersTablePolicy','canIndex'),
(748,1,'App\\Policy\\WarrantRostersTablePolicy','canView'),
(749,1,'App\\Policy\\WarrantRosterPolicy','canIndex'),
(750,1,'App\\Policy\\WarrantRosterPolicy','canView'),
(751,1069,'App\\Policy\\BranchesTablePolicy','canView'),
(752,1069,'App\\Policy\\BranchesTablePolicy','canIndex'),
(753,1071,'App\\Policy\\BranchesTablePolicy','canIndex'),
(754,1071,'App\\Policy\\BranchesTablePolicy','canView'),
(755,1071,'Officers\\Policy\\OfficerPolicy','canRelease'),
(756,1071,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(757,1070,'App\\Policy\\BranchesTablePolicy','canIndex'),
(758,1070,'App\\Policy\\BranchesTablePolicy','canView'),
(759,1070,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(760,1070,'Officers\\Policy\\OfficerPolicy','canOfficersByWarrantStatus'),
(761,1072,'Officers\\Policy\\OfficerPolicy','canWorkWithAllOfficers'),
(762,1072,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(763,1072,'App\\Policy\\WarrantRostersTablePolicy','canIndex'),
(764,1072,'App\\Policy\\WarrantRostersTablePolicy','canView'),
(765,1072,'App\\Policy\\WarrantRostersTablePolicy','canAdd'),
(766,1072,'App\\Policy\\WarrantRosterPolicy','canIndex'),
(767,1072,'App\\Policy\\WarrantRosterPolicy','canView'),
(768,1072,'App\\Policy\\WarrantRosterPolicy','canAdd'),
(769,1072,'App\\Policy\\WarrantRosterPolicy','canAllRosters'),
(770,1072,'App\\Policy\\WarrantRostersTablePolicy','canAllRosters'),
(772,7,'Officers\\Policy\\ReportsControllerPolicy','canDepartmentOfficersRoster'),
(773,7,'Officers\\Policy\\ReportsControllerPolicy','canView'),
(774,7,'Officers\\Policy\\ReportsControllerPolicy','canIndex'),
(775,1077,'Officers\\Policy\\OfficerPolicy','canMemberOfficers'),
(776,1075,'App\\Policy\\BranchPolicy','canView'),
(777,1075,'App\\Policy\\BranchesTablePolicy','canView'),
(778,1075,'App\\Policy\\BranchesTablePolicy','canIndex'),
(779,1075,'App\\Policy\\BranchPolicy','canIndex'),
(780,1075,'Officers\\Policy\\OfficerPolicy','canBranchOfficers'),
(781,1075,'Officers\\Policy\\OfficerPolicy','canMemberOfficers'),
(782,1075,'Awards\\Policy\\RecommendationPolicy','canApproveLevelNon-Armigerous'),
(783,1078,'App\\Policy\\GatheringPolicy','canIndex'),
(784,1078,'App\\Policy\\GatheringPolicy','canViewAttendance'),
(785,1078,'App\\Policy\\GatheringPolicy','canQuickView'),
(786,1078,'App\\Policy\\GatheringPolicy','canCalendar'),
(787,1078,'App\\Policy\\GatheringPolicy','canAdd'),
(788,1078,'App\\Policy\\GatheringPolicy','canEdit'),
(789,1078,'App\\Policy\\GatheringPolicy','canDelete'),
(790,1078,'App\\Policy\\GatheringPolicy','canView'),
(792,1078,'App\\Policy\\GatheringsTablePolicy','canIndex'),
(793,1078,'App\\Policy\\GatheringsTablePolicy','canAdd'),
(794,1078,'App\\Policy\\GatheringsTablePolicy','canEdit'),
(795,1078,'App\\Policy\\GatheringsTablePolicy','canDelete'),
(796,1078,'App\\Policy\\GatheringsTablePolicy','canView'),
(798,1078,'Waivers\\Policy\\GatheringWaiverPolicy','canViewGatheringWaivers'),
(802,1078,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canUpload'),
(807,1078,'Waivers\\Policy\\GatheringWaiverPolicy','canNeedingWaivers'),
(808,1078,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canNeedingWaivers'),
(809,1078,'Waivers\\Policy\\GatheringWaiverPolicy','canUploadWaivers'),
(810,1078,'Waivers\\Policy\\GatheringWaiversTablePolicy','canNeedingWaivers'),
(841,1079,'Waivers\\Policy\\GatheringWaiversTablePolicy','canNeedingWaivers'),
(848,1079,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canNeedingWaivers'),
(849,1079,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canUpload'),
(861,1079,'Waivers\\Policy\\GatheringWaiverPolicy','canNeedingWaivers'),
(862,1079,'Waivers\\Policy\\GatheringWaiverPolicy','canUploadWaivers'),
(870,1079,'Waivers\\Policy\\GatheringWaiverPolicy','canAdd'),
(871,1079,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canAdd'),
(872,1078,'App\\Policy\\GatheringPolicy','canGridData'),
(873,1078,'App\\Policy\\GatheringsTablePolicy','canCalendar'),
(874,1078,'App\\Policy\\GatheringsTablePolicy','canCalendarGridData'),
(875,1078,'App\\Policy\\GatheringsTablePolicy','canGridData'),
(876,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canAdd'),
(877,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canEdit'),
(878,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canDelete'),
(879,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canView'),
(880,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canIndex'),
(881,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canGridData'),
(882,1080,'Waivers\\Policy\\WaiverTypesTablePolicy','canViewPrivateNotes'),
(883,1080,'Waivers\\Policy\\WaiverTypePolicy','canToggleActive'),
(884,1080,'Waivers\\Policy\\WaiverTypePolicy','canDownloadTemplate'),
(885,1080,'Waivers\\Policy\\WaiverTypePolicy','canAdd'),
(886,1080,'Waivers\\Policy\\WaiverTypePolicy','canEdit'),
(887,1080,'Waivers\\Policy\\WaiverTypePolicy','canDelete'),
(888,1080,'Waivers\\Policy\\WaiverTypePolicy','canView'),
(889,1080,'Waivers\\Policy\\WaiverTypePolicy','canIndex'),
(890,1080,'Waivers\\Policy\\WaiverTypePolicy','canGridData'),
(891,1080,'Waivers\\Policy\\WaiverTypePolicy','canViewPrivateNotes'),
(892,1080,'Waivers\\Policy\\WaiverPolicy','canAdd'),
(893,1080,'Waivers\\Policy\\WaiverPolicy','canEdit'),
(894,1080,'Waivers\\Policy\\WaiverPolicy','canDelete'),
(895,1080,'Waivers\\Policy\\WaiverPolicy','canView'),
(896,1080,'Waivers\\Policy\\WaiverPolicy','canIndex'),
(897,1080,'Waivers\\Policy\\WaiverPolicy','canGridData'),
(898,1080,'Waivers\\Policy\\WaiverPolicy','canViewPrivateNotes'),
(899,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canNeedingWaivers'),
(900,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canAdd'),
(901,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canEdit'),
(902,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canDelete'),
(903,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canView'),
(904,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canIndex'),
(905,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canGridData'),
(906,1080,'Waivers\\Policy\\GatheringWaiversTablePolicy','canViewPrivateNotes'),
(907,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canNeedingWaivers'),
(908,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canUpload'),
(909,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canChangeWaiverType'),
(910,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canChangeActivities'),
(911,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canDashboard'),
(912,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canAdd'),
(913,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canEdit'),
(914,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canDelete'),
(915,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canView'),
(916,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canIndex'),
(917,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canGridData'),
(918,1080,'Waivers\\Policy\\GatheringWaiversControllerPolicy','canViewPrivateNotes'),
(932,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canAdd'),
(933,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canEdit'),
(934,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canDelete'),
(935,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canView'),
(936,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canIndex'),
(937,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canGridData'),
(938,1080,'Waivers\\Policy\\GatheringActivityWaiversTablePolicy','canViewPrivateNotes'),
(939,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canAdd'),
(940,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canEdit'),
(941,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canDelete'),
(942,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canView'),
(943,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canIndex'),
(944,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canGridData'),
(945,1080,'Waivers\\Policy\\GatheringActivityWaiverPolicy','canViewPrivateNotes'),
(946,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canEdit'),
(947,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canAdd'),
(948,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canView'),
(949,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canIndex'),
(950,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canGridData'),
(951,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canViewPrivateNotes'),
(952,1080,'App\\Policy\\GatheringActivitiesTablePolicy','canDelete'),
(953,1080,'App\\Policy\\GatheringActivityPolicy','canAdd'),
(954,1080,'App\\Policy\\GatheringActivityPolicy','canEdit'),
(955,1080,'App\\Policy\\GatheringActivityPolicy','canDelete'),
(956,1080,'App\\Policy\\GatheringActivityPolicy','canView'),
(957,1080,'App\\Policy\\GatheringActivityPolicy','canIndex'),
(958,1080,'App\\Policy\\GatheringActivityPolicy','canGridData'),
(959,1080,'App\\Policy\\GatheringActivityPolicy','canViewPrivateNotes'),
(960,1080,'App\\Policy\\GatheringAttendancePolicy','canView'),
(961,1080,'App\\Policy\\GatheringPolicy','canIndex'),
(962,1080,'App\\Policy\\GatheringPolicy','canViewAttendance'),
(963,1080,'App\\Policy\\GatheringPolicy','canQuickView'),
(964,1080,'App\\Policy\\GatheringPolicy','canCalendar'),
(965,1080,'App\\Policy\\GatheringPolicy','canAdd'),
(966,1080,'App\\Policy\\GatheringPolicy','canEdit'),
(967,1080,'App\\Policy\\GatheringPolicy','canDelete'),
(968,1080,'App\\Policy\\GatheringPolicy','canView'),
(969,1080,'App\\Policy\\GatheringPolicy','canGridData'),
(970,1080,'App\\Policy\\GatheringPolicy','canViewPrivateNotes'),
(971,1080,'App\\Policy\\GatheringTypePolicy','canAdd'),
(972,1080,'App\\Policy\\GatheringTypePolicy','canEdit'),
(973,1080,'App\\Policy\\GatheringTypePolicy','canDelete'),
(974,1080,'App\\Policy\\GatheringTypePolicy','canView'),
(975,1080,'App\\Policy\\GatheringTypePolicy','canIndex'),
(976,1080,'App\\Policy\\GatheringTypePolicy','canGridData'),
(977,1080,'App\\Policy\\GatheringTypePolicy','canViewPrivateNotes'),
(978,1080,'App\\Policy\\GatheringTypesTablePolicy','canAdd'),
(979,1080,'App\\Policy\\GatheringTypesTablePolicy','canEdit'),
(980,1080,'App\\Policy\\GatheringTypesTablePolicy','canDelete'),
(981,1080,'App\\Policy\\GatheringTypesTablePolicy','canView'),
(982,1080,'App\\Policy\\GatheringTypesTablePolicy','canIndex'),
(983,1080,'App\\Policy\\GatheringTypesTablePolicy','canGridData'),
(984,1080,'App\\Policy\\GatheringTypesTablePolicy','canViewPrivateNotes'),
(985,1080,'App\\Policy\\GatheringsTablePolicy','canIndex'),
(986,1080,'App\\Policy\\GatheringsTablePolicy','canCalendar'),
(987,1080,'App\\Policy\\GatheringsTablePolicy','canCalendarGridData'),
(988,1080,'App\\Policy\\GatheringsTablePolicy','canGridData'),
(989,1080,'App\\Policy\\GatheringsTablePolicy','canAdd'),
(990,1080,'App\\Policy\\GatheringsTablePolicy','canEdit'),
(991,1080,'App\\Policy\\GatheringsTablePolicy','canDelete'),
(992,1080,'App\\Policy\\GatheringsTablePolicy','canView'),
(993,1080,'App\\Policy\\GatheringsTablePolicy','canViewPrivateNotes'),
(994,1080,'App\\Policy\\DocumentPolicy','canAdd'),
(995,1080,'App\\Policy\\DocumentPolicy','canEdit'),
(996,1080,'App\\Policy\\DocumentPolicy','canDelete'),
(997,1080,'App\\Policy\\DocumentPolicy','canView'),
(998,1080,'App\\Policy\\DocumentPolicy','canIndex'),
(999,1080,'App\\Policy\\DocumentPolicy','canGridData'),
(1000,1080,'App\\Policy\\DocumentPolicy','canViewPrivateNotes'),
(1015,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canDownload'),
(1016,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canPreview'),
(1017,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canChangeWaiverType'),
(1018,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canViewGatheringWaivers'),
(1019,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canNeedingWaivers'),
(1020,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canUploadWaivers'),
(1021,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canCloseWaivers'),
(1022,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canDecline'),
(1023,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canAdd'),
(1024,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canEdit'),
(1025,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canDelete'),
(1026,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canView'),
(1027,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canIndex'),
(1028,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canGridData'),
(1029,1080,'Waivers\\Policy\\GatheringWaiverPolicy','canViewPrivateNotes'),
(1030,1081,'Activities\\Policy\\AuthorizationPolicy','canMemberAuthorizations'),
(1031,1081,'Officers\\Policy\\DepartmentPolicy','canView'),
(1032,1081,'Officers\\Policy\\DepartmentPolicy','canIndex'),
(1033,1081,'Officers\\Policy\\DepartmentsTablePolicy','canView'),
(1034,1081,'Officers\\Policy\\DepartmentsTablePolicy','canIndex'),
(1035,1081,'Officers\\Policy\\OfficePolicy','canView'),
(1036,1081,'Officers\\Policy\\OfficePolicy','canIndex'),
(1037,1081,'Officers\\Policy\\OfficerPolicy','canView'),
(1038,1081,'Officers\\Policy\\OfficerPolicy','canIndex'),
(1046,1081,'Officers\\Policy\\OfficesTablePolicy','canView'),
(1047,1081,'Officers\\Policy\\OfficesTablePolicy','canIndex');
/*!40000 ALTER TABLE `permission_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `require_active_membership` tinyint(1) NOT NULL DEFAULT 0,
  `require_active_background_check` tinyint(1) NOT NULL DEFAULT 0,
  `require_min_age` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_super_user` tinyint(1) NOT NULL DEFAULT 0,
  `requires_warrant` tinyint(1) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `scoping_rule` varchar(255) NOT NULL DEFAULT 'Global',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=1082 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES
(1,'Is Super User',1,0,0,1,1,0,'2025-02-18 01:23:22','2024-09-29 15:47:03',1,1096,NULL,'Global'),
(2,'Can Manage Roles',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(3,'Can Manage Permissions',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(4,'Can Manage Branches',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(5,'Can Manage Settings',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(6,'Can Manage Members',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(7,'Can View Core Reports',0,0,0,1,0,0,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(8,'Can View Members',1,0,0,1,0,0,'2024-10-02 22:37:15','2024-10-02 22:37:15',NULL,NULL,NULL,'Global'),
(11,'Can Manage Activities',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(12,'Can Revoke Authorizations',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(13,'Can Manage Authorization Queues',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(14,'Can View Activity Reports',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(21,'Can Manage Offices',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(22,'Can Manage Officers',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(23,'Can Manage Departments',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(24,'Can View Officer Reports',1,0,0,1,0,1,NULL,'2024-09-29 15:47:03',1,NULL,NULL,'Global'),
(31,'Can Manage Awards',1,0,0,1,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(32,'Can View Recommendations',1,0,0,1,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(33,'Can Manage Recommendations',1,0,0,1,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1001,' Approve Armored',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1002,' Approve Fiberglass Spear',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1003,' Approve Armored Field Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1004,' Approve Rapier',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1005,' Approve Rapier Field Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1006,' Approve Cut And Thrust',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1007,' Approve Rapier Spear',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1008,' Approve Siege',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1009,' Approve Siege Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1010,' Approve Armored Combat Archery',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1011,' Approve Rapier Combat Archery',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1012,' Approve Combat Archery Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1013,' Approve Target Archery Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1014,' Approve Thrown Weapons Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1015,' Approve Youth Boffer 1',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1016,' Approve Youth Boffer 2',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1017,' Approve Youth Boffer 3',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1018,' Approve Youth Boffer Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1019,' Approve Youth Boffer Junior Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1020,' Approve Youth Armored Combat',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1021,' Approve Youth Armored Combat Two Weapons',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1022,' Approve Youth Armored Combat Spear',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1023,' Approve Youth Armored Combat Weapon Shield',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1024,' Approve Youth Armored Combat Grea Weapons',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1025,' Approve Youth Armored Field Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1026,' Approve Youth Armored Combat Junior Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1027,' Approve Youth Rapier Combat Foil',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1028,' Approve Youth Rapier Combat Epee',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1029,' Approve Youth Rapier Combat Heavy Rapier',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1030,' Approve Youth Rapier Combat Plastic Sword',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1031,' Approve Youth Rapier Combat Melee',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1032,' Approve Youth Rapier Combat Offensive Secondary',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1033,' Approve Youth Rapier Combat Defensive Secondary',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1034,' Approve Youth Rapier Field Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1035,' Approve Youth Rapier Combat Plastic Sword Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1036,' Approve Experimental: Rapier Spear',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1037,' Approve C&T 2 Handed Weapon',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1038,' Approve Equestrian Field Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1039,' Approve General Riding',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1040,' Approve Mounted Games',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1041,' Approve Mounted Combat',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1042,' Approve Foam Jousting',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1043,' Approve Driving',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1044,' Approve Wooden Lance',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1045,' Approve Mounted Archery',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1050,' Approve C&T - Historic Combat Experiment',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1051,' Approve Reduced Rapier Armor Experiment ',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1052,' Approve Armored Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1053,' Approve Rapier Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1054,' Approve Target Archery Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1055,' Approve C&T Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1056,' Approve Equestrian Authorizing Marshal ',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1057,' Approve Youth Armored Authorizing Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1058,' Approve Youth Rapier Authorizing Marshal',1,1,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1059,' Approve Siege Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1060,' Approve Rapier Spear Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1061,' Approve Thrown Weapons Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1062,' Approve Combat Archery Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1063,' Approve Two Handed C&T Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1064,' Approve Wooden Lance Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1065,' Approve Reduced Armor Experiement Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1066,' Approve C&T - Historic Combat Experiment Authorizing Marshal',1,0,18,0,0,1,NULL,'2024-09-29 15:47:04',1,NULL,NULL,'Global'),
(1067,'Can View Warrants',1,0,0,1,0,1,NULL,'2025-01-12 01:02:18',1,NULL,NULL,'Global'),
(1068,'Can Manage Warrants',1,0,0,1,0,1,NULL,'2025-01-12 01:02:18',1,NULL,NULL,'Global'),
(1069,'Can View Branches',1,0,0,1,0,1,NULL,'2025-01-12 01:02:18',1,NULL,NULL,'Global'),
(1070,'Can Assign Officers',1,0,0,1,0,1,NULL,'2025-01-12 01:02:22',1,NULL,NULL,'Global'),
(1071,'Can Release Officers',1,0,0,1,0,1,NULL,'2025-01-12 01:02:22',1,NULL,NULL,'Global'),
(1072,'Can Create Officer Roster',1,0,0,1,0,1,NULL,'2025-01-12 01:02:22',1,NULL,NULL,'Global'),
(1073,'Can Manage Queue Engine',0,0,0,1,0,0,'2025-02-03 14:35:12','2025-02-03 14:35:12',NULL,NULL,NULL,'Global'),
(1074,'Can View Officers',0,0,0,1,0,0,'2025-02-03 14:35:12','2025-02-03 14:35:12',NULL,NULL,NULL,'Global'),
(1075,'Branch Non-Armiguous Recommendation Manager',1,0,0,0,0,0,'2025-07-09 21:53:22','2025-04-21 14:45:20',1,1073,NULL,'Branch and Children'),
(1076,'Manage Officers And Deputies Under Me',1,0,0,0,0,0,'2025-04-21 22:47:04','2025-04-21 22:47:04',1,1,NULL,'Branch and Children'),
(1077,'Can Do All the Thingz',1,0,0,0,0,0,'2025-05-14 23:44:04','2025-05-14 23:44:04',1,1,NULL,'Global'),
(1078,'Manage Gatherings For Branch and Branches Below Me',1,0,0,0,0,1,'2025-11-11 21:13:41','2025-11-11 21:13:41',1,1,NULL,'Branch and Children'),
(1079,'View and Submit Waivers (Branch and Children)',1,0,0,0,0,1,'2025-12-04 00:26:34','2025-12-04 00:26:34',1,1,NULL,'Branch and Children'),
(1080,'Waiver Management',1,0,0,0,0,0,'2026-01-15 01:50:28','2026-01-15 01:50:28',1,1,NULL,'Global'),
(1081,'GW Service Permissions',0,0,0,0,0,0,'2026-02-06 03:38:01','2026-02-06 03:38:01',1,1,NULL,'Global');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phinxlog`
--

LOCK TABLES `phinxlog` WRITE;
/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */;
INSERT INTO `phinxlog` VALUES
(20230511170042,'Init','2024-09-29 15:47:02','2024-09-29 15:47:03',0),
(20241001141705,'AddViewMembersPermission','2024-10-02 22:37:15','2024-10-02 22:37:15',0),
(20241009145957,'AddTitlePronounsPronunciationToMembers','2024-10-31 23:13:10','2024-10-31 23:13:10',0),
(20241024125311,'ChangeAppSettingValueToText','2024-10-31 23:13:10','2024-10-31 23:13:10',0),
(20241204160759,'Warrants','2025-01-12 01:02:18','2025-01-12 01:02:18',0),
(20241207172311,'AddWarrantableToMembers','2025-01-12 01:02:18','2025-01-12 01:02:22',0),
(20241225192403,'RefactorAgnosticJoinFields','2025-01-12 01:02:22','2025-01-12 01:02:22',0),
(20241231164137,'AddTypeToBranches','2025-01-12 01:02:22','2025-01-12 01:02:22',0),
(20250108190610,'AddRequiredToAppSetting','2025-01-12 01:02:22','2025-01-12 01:02:22',0),
(20250227173909,'AddScopeToMemberRoles','2025-03-01 14:24:26','2025-03-01 14:24:26',0),
(20250227230531,'AddDomainToBranch','2025-03-01 14:24:26','2025-03-01 14:24:26',0),
(20250328010857,'PermissionPolicies','2025-04-10 19:40:02','2025-04-10 19:40:02',0),
(20250415203922,'ConvertAppSettingsToSingleRecord','2025-04-21 14:17:19','2025-04-21 14:17:19',0),
(20251021000001,'CreateGatheringTypes','2025-10-30 21:03:01','2025-10-30 21:03:01',0),
(20251021000002,'CreateGatheringActivities','2025-10-30 21:03:01','2025-10-30 21:03:01',0),
(20251021164755,'CreateDocuments','2025-10-30 21:03:01','2025-10-30 21:03:02',0),
(20251021165329,'CreateGatherings','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251023000000,'CreateGatheringsGatheringActivities','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251024000000,'AddCustomDescriptionToGatheringsGatheringActivities','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251027000001,'CreateGatheringAttendances','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251029230939,'CreateEmailTemplates','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251030000001,'AddColorToGatheringTypes','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251030140457,'AddLatLongToGatherings','2025-10-30 21:03:02','2025-10-30 21:03:02',0),
(20251102000001,'CreateGatheringTypeGatheringActivities','2025-11-02 15:51:15','2025-11-02 15:51:15',0),
(20251102000002,'AddNotRemovableToGatheringsGatheringActivities','2025-11-02 15:51:15','2025-11-02 15:51:15',0),
(20251103000000,'CreateGatheringScheduledActivities','2025-11-04 16:47:34','2025-11-04 16:47:34',0),
(20251103120000,'CreateGatheringStaff','2025-11-04 16:47:34','2025-11-04 16:47:34',0),
(20251103140000,'AddPublicIdToMembersAndGatherings','2025-11-04 16:47:34','2025-11-04 16:47:34',0),
(20251103205723,'AddShowOnPublicPageToGatheringStaff','2025-11-04 16:47:34','2025-11-04 16:47:35',0),
(20251103215023,'AddPublicPageEnabledToGatherings','2025-11-04 16:47:35','2025-11-04 16:47:35',0),
(20251104000000,'MakeScheduledActivityEndTimeOptional','2025-11-04 16:47:35','2025-11-04 16:47:35',0),
(20251104010000,'AddHasEndTimeToScheduledActivities','2025-11-04 16:47:35','2025-11-04 16:47:35',0),
(20251105000000,'AddTimezoneToMembers','2025-11-06 11:46:36','2025-11-06 11:46:36',0),
(20251105000001,'AddTimezoneToGatherings','2025-11-06 11:46:36','2025-11-06 11:46:37',0),
(20251105000002,'ConvertGatheringDatesToDatetime','2025-11-06 11:46:37','2025-11-06 11:46:37',0),
(20251119000000,'CreateGridViews','2025-12-15 00:35:21','2025-12-15 00:35:21',0),
(20251121090000,'CreateGridViewPreferences','2025-12-15 00:35:21','2025-12-15 00:35:22',0),
(20251121120000,'AddGridViewKeyToGridViewPreferences','2025-12-15 00:35:22','2025-12-15 00:35:22',0),
(20251205120000,'CreateImpersonationActionLogs','2025-12-15 00:35:22','2025-12-15 00:35:22',0),
(20251205121000,'CreateImpersonationSessionLogs','2025-12-15 00:35:22','2025-12-15 00:35:22',0),
(20260131000001,'AddCancelledToGatherings','2026-02-01 00:09:55','2026-02-01 00:09:56',0),
(20260202200000,'CreateServicePrincipals','2026-02-06 03:36:31','2026-02-06 03:36:31',0),
(20260206000000,'AddPublicIdToBranches','2026-02-06 03:36:31','2026-02-06 03:36:31',0);
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_phinxlog`
--

DROP TABLE IF EXISTS `queue_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_phinxlog`
--

LOCK TABLES `queue_phinxlog` WRITE;
/*!40000 ALTER TABLE `queue_phinxlog` DISABLE KEYS */;
INSERT INTO `queue_phinxlog` VALUES
(20240307154751,'MigrationQueueInitV8','2025-02-03 14:35:12','2025-02-03 14:35:12',0),
(20250129194018,'AddQueueEngineManagerPermission','2025-02-03 14:35:12','2025-02-03 14:35:12',0);
/*!40000 ALTER TABLE `queue_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_processes`
--

DROP TABLE IF EXISTS `queue_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_processes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` varchar(40) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `terminate` tinyint(1) NOT NULL DEFAULT 0,
  `server` varchar(90) DEFAULT NULL,
  `workerkey` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workerkey` (`workerkey`),
  UNIQUE KEY `pid` (`pid`,`server`)
) ENGINE=InnoDB AUTO_INCREMENT=158451 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_processes`
--

LOCK TABLES `queue_processes` WRITE;
/*!40000 ALTER TABLE `queue_processes` DISABLE KEYS */;
INSERT INTO `queue_processes` VALUES
(158450,'1388316','2026-02-06 13:50:02','2026-02-06 13:50:52',0,'iad1-shared-b8-16','9892392d872fc605f6680eb02d8417ca72dc8c31');
/*!40000 ALTER TABLE `queue_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queued_jobs`
--

DROP TABLE IF EXISTS `queued_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queued_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_task` varchar(90) NOT NULL,
  `data` text DEFAULT NULL,
  `job_group` varchar(190) DEFAULT NULL,
  `reference` varchar(190) DEFAULT NULL,
  `created` datetime NOT NULL,
  `notbefore` datetime DEFAULT NULL,
  `fetched` datetime DEFAULT NULL,
  `completed` datetime DEFAULT NULL,
  `progress` float unsigned DEFAULT NULL,
  `attempts` tinyint(3) unsigned DEFAULT 0,
  `failure_message` text DEFAULT NULL,
  `workerkey` varchar(45) DEFAULT NULL,
  `status` varchar(190) DEFAULT NULL,
  `priority` int(10) unsigned NOT NULL DEFAULT 5,
  PRIMARY KEY (`id`),
  KEY `completed` (`completed`),
  KEY `job_task` (`job_task`)
) ENGINE=InnoDB AUTO_INCREMENT=830 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queued_jobs`
--

LOCK TABLES `queued_jobs` WRITE;
/*!40000 ALTER TABLE `queued_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `queued_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=1124 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'Admin',1,NULL,'2024-09-29 15:47:03',1,NULL,NULL),
(10,'System Administrator',0,'2025-05-14 23:45:00','2024-09-29 15:47:04',1,1,NULL),
(20,'Site Secretary',0,'2025-01-14 05:46:42','2024-09-29 15:47:04',1,840,NULL),
(30,'Awards Secretary',0,'2024-09-29 16:55:07','2024-09-29 15:47:04',1,1,NULL),
(100,'Ansteorran Crown',0,'2025-01-12 01:06:09','2024-09-29 15:47:04',1,1096,NULL),
(110,'Ansteorran Coronet',0,'2024-10-08 02:14:42','2024-09-29 15:47:04',1,1096,NULL),
(200,'Vindheim Coronet',0,'2025-06-22 00:53:40','2024-09-29 15:47:04',1,2866,NULL),
(1001,'Armored Combat Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1002,'Rapier Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1003,'Cut & Thrust Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1004,'Target Archery Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1005,'Equestrian Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1006,'Youth Rapier Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1007,'Youth Armored Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1008,'Siege Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1009,'Rapier Spear Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1010,'Thrown Weapons Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1011,'Combat Archery Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1012,'Two Handed Cut & Thrust Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1013,'Wooden Lance Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1014,'Reduced Armor Experiment Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1015,'C&T - Historic Combat Experiment Authorizer',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1101,'Armored Combat Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1102,'Rapier Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1103,'Cut & Thrust Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1104,'Missile Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1105,'Equestrian Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1106,'Youth Rapier Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1107,'Youth Armored Authorizing Marshal Manager',0,NULL,'2024-09-29 15:47:04',1,1,NULL),
(1108,'Kingdom Earl Marshal',0,'2025-01-12 17:18:57','2024-09-29 17:00:20',1,1096,NULL),
(1109,'Kingdom Rapier Marshal',0,'2024-09-29 17:03:02','2024-09-29 17:03:02',1,1,NULL),
(1110,'Kingdom Missile Marshal',0,'2024-09-29 17:04:30','2024-09-29 17:04:30',1,1,NULL),
(1111,'Kingdom Armored Marshal',0,'2024-09-29 17:05:51','2024-09-29 17:05:51',1,1,NULL),
(1112,'Kingdom Equestrian Marshal',0,'2024-09-29 17:07:19','2024-09-29 17:07:19',1,1,NULL),
(1113,'Kingdom Youth Armored Marshal',0,'2024-09-29 17:08:57','2024-09-29 17:08:57',1,1,NULL),
(1114,'Kingdom Youth Rapier Marshal',0,'2024-09-29 17:09:28','2024-09-29 17:09:28',1,1,NULL),
(1115,'Thrown Weapons Authorizing Marshal Manager',0,'2024-12-06 16:29:24','2024-12-06 16:29:24',1,1096,NULL),
(1116,'Greater Officer of State',0,'2025-06-22 20:20:25','2025-01-12 17:17:18',1,1096,NULL),
(1117,'Local Landed Crown Repersenative',0,'2025-12-04 00:39:55','2025-04-21 14:50:45',1,1,NULL),
(1118,'Regional Officer Management',0,'2025-12-04 00:36:57','2025-04-21 22:46:12',1,1,NULL),
(1119,'Local Seneschal',0,'2025-11-11 21:17:13','2025-11-11 21:17:13',1,1,NULL),
(1120,'Warranted Officer',0,'2025-12-04 00:28:59','2025-12-04 00:28:59',1,1,NULL),
(1121,'Waiver Secretary',0,'2026-01-15 01:52:35','2026-01-15 01:52:35',1,1,NULL),
(1122,'Local Warranted Officer',0,'2026-01-16 00:47:53','2026-01-16 00:47:53',1,1,NULL),
(1123,'GW Service Role',0,'2026-02-06 03:40:36','2026-02-06 03:40:36',1,1,NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles_permissions`
--

DROP TABLE IF EXISTS `roles_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `roles_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roles_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=358 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles_permissions`
--

LOCK TABLES `roles_permissions` WRITE;
/*!40000 ALTER TABLE `roles_permissions` DISABLE KEYS */;
INSERT INTO `roles_permissions` VALUES
(1,1,1,'2024-09-29 15:47:03',1),
(2,1001,1001,'2024-09-29 15:47:04',1),
(3,1002,1001,'2024-09-29 15:47:04',1),
(4,1003,1001,'2024-09-29 15:47:04',1),
(5,1004,1002,'2024-09-29 15:47:04',1),
(6,1005,1002,'2024-09-29 15:47:04',1),
(7,1006,1003,'2024-09-29 15:47:04',1),
(8,1007,1009,'2024-09-29 15:47:04',1),
(9,1008,1008,'2024-09-29 15:47:04',1),
(10,1009,1008,'2024-09-29 15:47:04',1),
(11,1010,1011,'2024-09-29 15:47:04',1),
(12,1011,1011,'2024-09-29 15:47:04',1),
(13,1012,1011,'2024-09-29 15:47:04',1),
(14,1013,1004,'2024-09-29 15:47:04',1),
(15,1014,1010,'2024-09-29 15:47:04',1),
(16,1015,1007,'2024-09-29 15:47:04',1),
(17,1016,1007,'2024-09-29 15:47:04',1),
(18,1017,1007,'2024-09-29 15:47:04',1),
(19,1018,1007,'2024-09-29 15:47:04',1),
(20,1019,1007,'2024-09-29 15:47:04',1),
(21,1020,1007,'2024-09-29 15:47:04',1),
(22,1021,1007,'2024-09-29 15:47:04',1),
(23,1022,1007,'2024-09-29 15:47:04',1),
(24,1023,1007,'2024-09-29 15:47:04',1),
(25,1024,1007,'2024-09-29 15:47:04',1),
(26,1025,1007,'2024-09-29 15:47:04',1),
(27,1026,1007,'2024-09-29 15:47:04',1),
(28,1027,1006,'2024-09-29 15:47:04',1),
(29,1028,1006,'2024-09-29 15:47:04',1),
(30,1029,1006,'2024-09-29 15:47:04',1),
(31,1030,1006,'2024-09-29 15:47:04',1),
(32,1031,1006,'2024-09-29 15:47:04',1),
(33,1032,1006,'2024-09-29 15:47:04',1),
(34,1033,1006,'2024-09-29 15:47:04',1),
(35,1034,1006,'2024-09-29 15:47:04',1),
(36,1035,1006,'2024-09-29 15:47:04',1),
(37,1037,1003,'2024-09-29 15:47:04',1),
(38,1038,1005,'2024-09-29 15:47:04',1),
(39,1039,1005,'2024-09-29 15:47:04',1),
(40,1040,1005,'2024-09-29 15:47:04',1),
(41,1041,1005,'2024-09-29 15:47:04',1),
(42,1042,1005,'2024-09-29 15:47:04',1),
(43,1043,1005,'2024-09-29 15:47:04',1),
(44,1044,1005,'2024-09-29 15:47:04',1),
(45,1045,1005,'2024-09-29 15:47:04',1),
(46,1050,1003,'2024-09-29 15:47:04',1),
(47,1051,1014,'2024-09-29 15:47:04',1),
(48,1052,1101,'2024-09-29 15:47:04',1),
(49,1053,1102,'2024-09-29 15:47:04',1),
(50,1054,1104,'2024-09-29 15:47:04',1),
(51,1055,1103,'2024-09-29 15:47:04',1),
(52,1056,1105,'2024-09-29 15:47:04',1),
(53,1057,1107,'2024-09-29 15:47:04',1),
(54,1058,1106,'2024-09-29 15:47:04',1),
(55,1059,1104,'2024-09-29 15:47:04',1),
(56,1060,1102,'2024-09-29 15:47:04',1),
(57,1061,1104,'2024-09-29 15:47:04',1),
(58,1062,1104,'2024-09-29 15:47:04',1),
(59,1063,1103,'2024-09-29 15:47:04',1),
(60,1064,1105,'2024-09-29 15:47:04',1),
(61,1065,1102,'2024-09-29 15:47:04',1),
(62,1066,1103,'2024-09-29 15:47:04',1),
(63,31,100,'2024-09-29 15:47:04',1),
(64,33,100,'2024-09-29 15:47:04',1),
(65,31,30,'2024-09-29 15:47:04',1),
(66,4,20,'2024-09-29 15:47:04',1),
(67,6,20,'2024-09-29 15:47:04',1),
(68,7,20,'2024-09-29 15:47:04',1),
(69,31,20,'2024-09-29 15:47:04',1),
(70,11,20,'2024-09-29 15:47:04',1),
(71,12,20,'2024-09-29 15:47:04',1),
(72,13,20,'2024-09-29 15:47:04',1),
(73,14,20,'2024-09-29 15:47:04',1),
(74,21,20,'2024-09-29 15:47:04',1),
(75,22,20,'2024-09-29 15:47:04',1),
(76,23,20,'2024-09-29 15:47:04',1),
(77,24,20,'2024-09-29 15:47:04',1),
(78,2,10,'2024-09-29 15:47:04',1),
(79,3,10,'2024-09-29 15:47:04',1),
(80,4,10,'2024-09-29 15:47:04',1),
(81,5,10,'2024-09-29 15:47:04',1),
(82,6,10,'2024-09-29 15:47:04',1),
(83,7,10,'2024-09-29 15:47:04',1),
(84,11,10,'2024-09-29 15:47:04',1),
(85,12,10,'2024-09-29 15:47:04',1),
(86,13,10,'2024-09-29 15:47:04',1),
(87,14,10,'2024-09-29 15:47:04',1),
(88,21,10,'2024-09-29 15:47:04',1),
(89,22,10,'2024-09-29 15:47:04',1),
(90,23,10,'2024-09-29 15:47:04',1),
(91,24,10,'2024-09-29 15:47:04',1),
(92,31,10,'2024-09-29 15:47:04',1),
(157,32,110,'2024-09-29 16:50:33',1),
(158,32,100,'2024-09-29 16:50:57',1),
(159,7,1108,'2024-09-29 17:00:20',1),
(160,12,1108,'2024-09-29 17:00:20',1),
(161,13,1108,'2024-09-29 17:00:20',1),
(162,14,1108,'2024-09-29 17:00:20',1),
(163,1001,1108,'2024-09-29 17:00:20',1),
(164,1002,1108,'2024-09-29 17:00:20',1),
(165,1003,1108,'2024-09-29 17:00:20',1),
(166,1004,1108,'2024-09-29 17:00:20',1),
(167,1005,1108,'2024-09-29 17:00:20',1),
(168,1006,1108,'2024-09-29 17:00:20',1),
(169,1007,1108,'2024-09-29 17:00:21',1),
(170,1008,1108,'2024-09-29 17:00:21',1),
(171,1009,1108,'2024-09-29 17:00:21',1),
(172,1010,1108,'2024-09-29 17:00:21',1),
(173,1011,1108,'2024-09-29 17:00:21',1),
(174,1012,1108,'2024-09-29 17:00:21',1),
(175,1013,1108,'2024-09-29 17:00:21',1),
(176,1014,1108,'2024-09-29 17:00:21',1),
(177,1015,1108,'2024-09-29 17:00:21',1),
(178,1016,1108,'2024-09-29 17:00:21',1),
(179,1017,1108,'2024-09-29 17:00:21',1),
(180,1018,1108,'2024-09-29 17:00:21',1),
(181,1019,1108,'2024-09-29 17:00:21',1),
(182,1020,1108,'2024-09-29 17:00:21',1),
(183,1021,1108,'2024-09-29 17:00:21',1),
(184,1022,1108,'2024-09-29 17:00:21',1),
(185,1023,1108,'2024-09-29 17:00:21',1),
(186,1024,1108,'2024-09-29 17:00:21',1),
(187,1025,1108,'2024-09-29 17:00:21',1),
(188,1026,1108,'2024-09-29 17:00:21',1),
(189,1027,1108,'2024-09-29 17:00:21',1),
(190,1028,1108,'2024-09-29 17:00:21',1),
(191,1029,1108,'2024-09-29 17:00:21',1),
(192,1030,1108,'2024-09-29 17:00:21',1),
(193,1031,1108,'2024-09-29 17:00:21',1),
(194,1032,1108,'2024-09-29 17:00:21',1),
(195,1033,1108,'2024-09-29 17:00:21',1),
(196,1034,1108,'2024-09-29 17:00:21',1),
(197,1035,1108,'2024-09-29 17:00:21',1),
(198,1036,1108,'2024-09-29 17:00:21',1),
(199,1037,1108,'2024-09-29 17:00:21',1),
(200,1038,1108,'2024-09-29 17:00:21',1),
(201,1039,1108,'2024-09-29 17:00:21',1),
(202,1040,1108,'2024-09-29 17:00:21',1),
(203,1041,1108,'2024-09-29 17:00:21',1),
(204,1042,1108,'2024-09-29 17:00:21',1),
(205,1043,1108,'2024-09-29 17:00:21',1),
(206,1044,1108,'2024-09-29 17:00:21',1),
(207,1045,1108,'2024-09-29 17:00:21',1),
(208,1050,1108,'2024-09-29 17:00:21',1),
(209,1051,1108,'2024-09-29 17:00:21',1),
(210,1052,1108,'2024-09-29 17:00:21',1),
(211,1053,1108,'2024-09-29 17:00:21',1),
(212,1054,1108,'2024-09-29 17:00:21',1),
(213,1055,1108,'2024-09-29 17:00:21',1),
(214,1056,1108,'2024-09-29 17:00:21',1),
(215,1057,1108,'2024-09-29 17:00:21',1),
(216,1058,1108,'2024-09-29 17:00:21',1),
(217,1059,1108,'2024-09-29 17:00:21',1),
(218,1060,1108,'2024-09-29 17:00:21',1),
(219,1061,1108,'2024-09-29 17:00:21',1),
(220,1062,1108,'2024-09-29 17:00:21',1),
(221,1063,1108,'2024-09-29 17:00:21',1),
(222,1064,1108,'2024-09-29 17:00:21',1),
(223,1065,1108,'2024-09-29 17:00:21',1),
(224,1066,1108,'2024-09-29 17:00:21',1),
(225,7,1109,'2024-09-29 17:03:02',1),
(226,14,1109,'2024-09-29 17:03:02',1),
(227,1004,1109,'2024-09-29 17:03:02',1),
(228,1005,1109,'2024-09-29 17:03:02',1),
(229,1006,1109,'2024-09-29 17:03:02',1),
(230,1007,1109,'2024-09-29 17:03:02',1),
(231,1037,1109,'2024-09-29 17:03:02',1),
(232,1050,1109,'2024-09-29 17:03:02',1),
(233,1051,1109,'2024-09-29 17:03:02',1),
(234,1053,1109,'2024-09-29 17:03:02',1),
(235,1055,1109,'2024-09-29 17:03:02',1),
(236,1060,1109,'2024-09-29 17:03:02',1),
(237,1063,1109,'2024-09-29 17:03:02',1),
(238,1065,1109,'2024-09-29 17:03:02',1),
(239,1066,1109,'2024-09-29 17:03:02',1),
(240,7,1110,'2024-09-29 17:04:30',1),
(241,14,1110,'2024-09-29 17:04:30',1),
(242,1008,1110,'2024-09-29 17:04:30',1),
(243,1009,1110,'2024-09-29 17:04:30',1),
(244,1010,1110,'2024-09-29 17:04:30',1),
(245,1011,1110,'2024-09-29 17:04:30',1),
(246,1012,1110,'2024-09-29 17:04:30',1),
(247,1013,1110,'2024-09-29 17:04:30',1),
(248,1014,1110,'2024-09-29 17:04:30',1),
(249,1054,1110,'2024-09-29 17:04:30',1),
(250,1059,1110,'2024-09-29 17:04:30',1),
(251,1061,1110,'2024-09-29 17:04:30',1),
(252,1062,1110,'2024-09-29 17:04:30',1),
(253,7,1111,'2024-09-29 17:05:51',1),
(254,14,1111,'2024-09-29 17:05:51',1),
(255,1001,1111,'2024-09-29 17:05:51',1),
(256,1002,1111,'2024-09-29 17:05:51',1),
(257,1003,1111,'2024-09-29 17:05:51',1),
(258,1052,1111,'2024-09-29 17:05:51',1),
(259,7,1112,'2024-09-29 17:07:19',1),
(260,14,1112,'2024-09-29 17:07:19',1),
(261,1038,1112,'2024-09-29 17:07:19',1),
(262,1039,1112,'2024-09-29 17:07:19',1),
(263,1040,1112,'2024-09-29 17:07:19',1),
(264,1041,1112,'2024-09-29 17:07:19',1),
(265,1042,1112,'2024-09-29 17:07:19',1),
(266,1043,1112,'2024-09-29 17:07:19',1),
(267,1044,1112,'2024-09-29 17:07:19',1),
(268,1045,1112,'2024-09-29 17:07:19',1),
(269,1056,1112,'2024-09-29 17:07:19',1),
(270,1064,1112,'2024-09-29 17:07:19',1),
(271,7,1113,'2024-09-29 17:08:57',1),
(272,14,1113,'2024-09-29 17:08:57',1),
(273,1015,1113,'2024-09-29 17:08:57',1),
(274,1016,1113,'2024-09-29 17:08:57',1),
(275,1017,1113,'2024-09-29 17:08:57',1),
(276,1018,1113,'2024-09-29 17:08:57',1),
(277,1019,1113,'2024-09-29 17:08:57',1),
(278,1020,1113,'2024-09-29 17:08:57',1),
(279,1021,1113,'2024-09-29 17:08:57',1),
(280,1022,1113,'2024-09-29 17:08:57',1),
(281,1023,1113,'2024-09-29 17:08:57',1),
(282,1024,1113,'2024-09-29 17:08:57',1),
(283,1025,1113,'2024-09-29 17:08:57',1),
(284,1026,1113,'2024-09-29 17:08:57',1),
(285,1057,1113,'2024-09-29 17:08:57',1),
(286,1027,1114,'2024-09-29 17:09:28',1),
(287,1028,1114,'2024-09-29 17:09:28',1),
(288,1029,1114,'2024-09-29 17:09:28',1),
(289,1030,1114,'2024-09-29 17:09:28',1),
(290,1031,1114,'2024-09-29 17:09:28',1),
(291,1032,1114,'2024-09-29 17:09:28',1),
(292,1033,1114,'2024-09-29 17:09:28',1),
(293,1034,1114,'2024-09-29 17:09:28',1),
(294,1035,1114,'2024-09-29 17:09:28',1),
(295,1058,1114,'2024-09-29 17:09:28',1),
(296,8,110,'2024-10-02 22:37:59',1),
(297,8,100,'2024-10-02 22:38:17',1),
(298,7,100,'2024-10-08 02:12:14',1),
(299,7,110,'2024-10-08 02:12:45',1),
(300,14,110,'2024-10-08 02:13:02',1),
(301,24,100,'2024-10-08 02:13:28',1),
(302,14,100,'2024-10-08 02:13:36',1),
(303,24,110,'2024-10-08 02:14:42',1),
(308,2,20,'2024-12-06 16:25:34',1),
(309,1061,1115,'2024-12-06 16:29:24',1),
(310,1067,10,'2025-01-12 01:04:41',1),
(311,1070,10,'2025-01-12 01:04:49',1),
(312,1071,10,'2025-01-12 01:04:57',1),
(313,1072,10,'2025-01-12 01:05:05',1),
(314,1067,100,'2025-01-12 01:05:41',1),
(315,1068,100,'2025-01-12 01:05:52',1),
(316,1069,100,'2025-01-12 01:05:56',1),
(317,1070,100,'2025-01-12 01:06:00',1),
(318,1071,100,'2025-01-12 01:06:04',1),
(319,1072,100,'2025-01-12 01:06:09',1),
(320,8,1116,'2025-01-12 17:17:18',1),
(321,1067,1116,'2025-01-12 17:17:18',1),
(322,1069,1116,'2025-01-12 17:17:18',1),
(323,1070,1116,'2025-01-12 17:17:18',1),
(324,1071,1116,'2025-01-12 17:17:18',1),
(325,1072,1116,'2025-01-12 17:17:18',1),
(326,8,1108,'2025-01-12 17:18:34',1),
(327,1067,1108,'2025-01-12 17:18:40',1),
(328,1069,1108,'2025-01-12 17:18:45',1),
(329,1070,1108,'2025-01-12 17:18:48',1),
(330,1071,1108,'2025-01-12 17:18:53',1),
(331,1072,1108,'2025-01-12 17:18:57',1),
(332,1068,10,'2025-01-12 17:47:00',1),
(333,1070,20,'2025-01-14 05:46:10',1),
(334,1071,20,'2025-01-14 05:46:32',1),
(335,1072,20,'2025-01-14 05:46:42',1),
(336,1073,10,'2025-02-03 14:37:17',1),
(337,1075,1117,'2025-04-21 14:50:45',1),
(338,1076,1118,'2025-04-21 22:57:04',1),
(339,8,10,'2025-05-14 23:40:55',1),
(340,32,10,'2025-05-14 23:41:00',1),
(341,33,10,'2025-05-14 23:41:03',1),
(342,1069,10,'2025-05-14 23:41:12',1),
(343,1074,10,'2025-05-14 23:41:17',1),
(344,1075,10,'2025-05-14 23:41:25',1),
(345,1076,10,'2025-05-14 23:41:29',1),
(346,1077,10,'2025-05-14 23:45:00',1),
(348,1075,200,'2025-06-22 00:53:40',1),
(349,7,1116,'2025-06-22 20:20:25',1),
(350,1076,1119,'2025-11-11 21:17:13',1),
(351,1078,1119,'2025-11-11 21:17:13',1),
(352,1079,1120,'2025-12-04 00:28:59',1),
(353,1079,1118,'2025-12-04 00:36:57',1),
(354,1079,1117,'2025-12-04 00:39:55',1),
(355,1080,1121,'2026-01-15 01:52:35',1),
(356,1079,1122,'2026-01-16 00:47:53',1),
(357,1081,1123,'2026-02-06 03:40:36',1);
/*!40000 ALTER TABLE `roles_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_principal_audit_logs`
--

DROP TABLE IF EXISTS `service_principal_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_principal_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_principal_id` int(11) NOT NULL,
  `token_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `endpoint` varchar(512) NOT NULL,
  `http_method` varchar(10) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `request_summary` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sp_audit_created` (`created`),
  KEY `idx_sp_audit_principal_created` (`service_principal_id`,`created`),
  KEY `fk_sp_audit_token` (`token_id`),
  CONSTRAINT `fk_sp_audit_service_principal` FOREIGN KEY (`service_principal_id`) REFERENCES `service_principals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_audit_token` FOREIGN KEY (`token_id`) REFERENCES `service_principal_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_principal_audit_logs`
--

LOCK TABLES `service_principal_audit_logs` WRITE;
/*!40000 ALTER TABLE `service_principal_audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_principal_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_principal_roles`
--

DROP TABLE IF EXISTS `service_principal_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_principal_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_principal_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `start_on` date NOT NULL,
  `expires_on` date DEFAULT NULL,
  `entity_type` varchar(255) DEFAULT 'Direct Grant',
  `entity_id` int(11) DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `revoked_on` datetime DEFAULT NULL,
  `revoker_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `modified` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sp_roles_principal_role` (`service_principal_id`,`role_id`),
  KEY `idx_sp_roles_active_window` (`start_on`,`expires_on`),
  KEY `fk_sp_roles_role` (`role_id`),
  KEY `fk_sp_roles_branch` (`branch_id`),
  KEY `fk_sp_roles_approver` (`approver_id`),
  KEY `fk_sp_roles_revoker` (`revoker_id`),
  CONSTRAINT `fk_sp_roles_approver` FOREIGN KEY (`approver_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sp_roles_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sp_roles_revoker` FOREIGN KEY (`revoker_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sp_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_roles_service_principal` FOREIGN KEY (`service_principal_id`) REFERENCES `service_principals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_principal_roles`
--

LOCK TABLES `service_principal_roles` WRITE;
/*!40000 ALTER TABLE `service_principal_roles` DISABLE KEYS */;
INSERT INTO `service_principal_roles` VALUES
(1,1,1123,NULL,'2026-02-06',NULL,'Direct Grant',NULL,1,NULL,NULL,1,1,'2026-02-06 03:40:47','2026-02-06 03:40:47');
/*!40000 ALTER TABLE `service_principal_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_principal_tokens`
--

DROP TABLE IF EXISTS `service_principal_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_principal_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_principal_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sp_tokens_hash` (`token_hash`),
  KEY `idx_sp_tokens_expiry` (`expires_at`),
  KEY `fk_sp_tokens_service_principal` (`service_principal_id`),
  KEY `fk_sp_tokens_created_by` (`created_by`),
  CONSTRAINT `fk_sp_tokens_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sp_tokens_service_principal` FOREIGN KEY (`service_principal_id`) REFERENCES `service_principals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_principal_tokens`
--

LOCK TABLES `service_principal_tokens` WRITE;
/*!40000 ALTER TABLE `service_principal_tokens` DISABLE KEYS */;
INSERT INTO `service_principal_tokens` VALUES
(1,1,'a73a1f4aa4d44c190e5e50e35b35d568e25d7eb9cccac5a9311be2fc01c98604','Initial Token',NULL,NULL,NULL,'2026-02-06 03:37:21');
/*!40000 ALTER TABLE `service_principal_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_principals`
--

DROP TABLE IF EXISTS `service_principals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_principals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` varchar(64) NOT NULL,
  `client_secret_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `ip_allowlist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ip_allowlist`)),
  `last_used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `modified` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_service_principals_client_id` (`client_id`),
  KEY `idx_service_principals_active` (`is_active`),
  KEY `fk_service_principals_created_by` (`created_by`),
  KEY `fk_service_principals_modified_by` (`modified_by`),
  CONSTRAINT `fk_service_principals_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_service_principals_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_principals`
--

LOCK TABLES `service_principals` WRITE;
/*!40000 ALTER TABLE `service_principals` DISABLE KEYS */;
INSERT INTO `service_principals` VALUES
(1,'GW Service Principle','','kmp_sp_a9d4dc39a24469b2e6d2b4d6','$2y$10$UsevWcNZlskEq6Vz5/TAJ.uOiqEcU2YlBZcErTigT7GwdEhwrs7jW',1,NULL,NULL,1,1,'2026-02-06 03:37:21','2026-02-06 03:37:21');
/*!40000 ALTER TABLE `service_principals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(20) NOT NULL COMMENT 'e.g.:activate,reactivate',
  `token_key` varchar(60) NOT NULL,
  `content` varchar(255) DEFAULT NULL COMMENT 'can transport some information',
  `used` int(11) NOT NULL DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_key` (`token_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tokens`
--

LOCK TABLES `tokens` WRITE;
/*!40000 ALTER TABLE `tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tools_phinxlog`
--

DROP TABLE IF EXISTS `tools_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tools_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tools_phinxlog`
--

LOCK TABLES `tools_phinxlog` WRITE;
/*!40000 ALTER TABLE `tools_phinxlog` DISABLE KEYS */;
INSERT INTO `tools_phinxlog` VALUES
(20200430170235,'MigrationToolsTokens','2025-02-03 14:35:12','2025-02-03 14:35:12',0);
/*!40000 ALTER TABLE `tools_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waivers_gathering_activity_waivers`
--

DROP TABLE IF EXISTS `waivers_gathering_activity_waivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_gathering_activity_waivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_activity_id` int(11) NOT NULL COMMENT 'Gathering activity this waiver requirement applies to',
  `waiver_type_id` int(11) NOT NULL COMMENT 'Type of waiver required for this activity',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_activity_waivers_unique` (`gathering_activity_id`,`waiver_type_id`,`deleted`),
  KEY `idx_gathering_activity_waivers_activity` (`gathering_activity_id`),
  KEY `idx_gathering_activity_waivers_type` (`waiver_type_id`),
  CONSTRAINT `fk_gathering_activity_waivers_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`),
  CONSTRAINT `fk_gathering_activity_waivers_type` FOREIGN KEY (`waiver_type_id`) REFERENCES `waivers_waiver_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waivers_gathering_activity_waivers`
--

LOCK TABLES `waivers_gathering_activity_waivers` WRITE;
/*!40000 ALTER TABLE `waivers_gathering_activity_waivers` DISABLE KEYS */;
INSERT INTO `waivers_gathering_activity_waivers` VALUES
(1,2,1,'2025-10-31 19:15:29','2025-10-31 19:15:29',1,1,NULL),
(2,3,1,'2025-11-02 02:08:01','2025-11-02 02:08:01',1,1,NULL),
(3,5,2,'2025-11-02 02:08:17','2025-11-02 02:08:17',1,1,NULL),
(4,1,3,'2025-11-02 16:08:11','2025-11-02 02:15:06',1,1,'2025-11-02 16:08:11'),
(5,1,4,'2025-11-02 16:08:14','2025-11-02 02:15:10',1,1,'2025-11-02 16:08:14'),
(6,1,1,'2025-11-02 16:08:08','2025-11-02 02:15:14',1,1,'2025-11-02 16:08:08'),
(7,2,4,'2025-11-02 16:10:13','2025-11-02 16:10:13',1,1,NULL),
(8,2,3,'2025-11-02 16:10:19','2025-11-02 16:10:19',1,1,NULL),
(9,4,1,'2025-11-02 16:18:08','2025-11-02 16:18:08',1,1,NULL),
(10,3,3,'2025-11-06 00:10:06','2025-11-06 00:10:06',1,1,NULL),
(11,4,3,'2025-11-06 00:10:35','2025-11-06 00:10:35',1,1,NULL),
(12,2,5,'2025-11-06 01:33:12','2025-11-06 01:33:12',1,1,NULL),
(13,8,1,'2025-11-06 01:58:52','2025-11-06 01:58:52',1,1,NULL),
(14,10,3,'2025-11-06 01:59:06','2025-11-06 01:59:06',1,1,NULL),
(15,9,3,'2025-11-06 01:59:20','2025-11-06 01:59:20',1,1,NULL),
(16,7,1,'2025-11-06 01:59:30','2025-11-06 01:59:30',1,1,NULL),
(17,5,6,'2025-11-14 02:07:14','2025-11-06 02:16:38',1,1,'2025-11-14 02:07:14');
/*!40000 ALTER TABLE `waivers_gathering_activity_waivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waivers_gathering_waiver_closures`
--

DROP TABLE IF EXISTS `waivers_gathering_waiver_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_gathering_waiver_closures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'Gathering with waivers closed to new uploads',
  `closed_at` datetime DEFAULT NULL COMMENT 'Timestamp when waiver collection was closed by secretary',
  `closed_by` int(11) DEFAULT NULL COMMENT 'Member who closed waiver collection (waiver secretary)',
  `ready_to_close_at` datetime DEFAULT NULL COMMENT 'Timestamp when gathering was marked ready to close',
  `ready_to_close_by` int(11) DEFAULT NULL COMMENT 'Member who marked gathering ready to close',
  `modified` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_waiver_closures_gathering` (`gathering_id`),
  KEY `idx_gathering_waiver_closures_closed_by` (`closed_by`),
  KEY `idx_gathering_waiver_closures_closed_at` (`closed_at`),
  KEY `idx_gathering_waiver_closures_ready_at` (`ready_to_close_at`),
  KEY `idx_gathering_waiver_closures_ready_by` (`ready_to_close_by`),
  CONSTRAINT `fk_gathering_waiver_closures_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION,
  CONSTRAINT `fk_gathering_waiver_closures_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gathering_waiver_closures_ready_by` FOREIGN KEY (`ready_to_close_by`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waivers_gathering_waiver_closures`
--

LOCK TABLES `waivers_gathering_waiver_closures` WRITE;
/*!40000 ALTER TABLE `waivers_gathering_waiver_closures` DISABLE KEYS */;
INSERT INTO `waivers_gathering_waiver_closures` VALUES
(4,96,'2026-01-16 00:06:07',2891,NULL,NULL,'2026-01-16 00:06:07','2026-01-16 00:06:07');
/*!40000 ALTER TABLE `waivers_gathering_waiver_closures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waivers_gathering_waivers`
--

DROP TABLE IF EXISTS `waivers_gathering_waivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_gathering_waivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_id` int(11) NOT NULL COMMENT 'Gathering this waiver is for',
  `waiver_type_id` int(11) NOT NULL COMMENT 'Type of waiver (declared at upload time)',
  `document_id` int(11) DEFAULT NULL COMMENT 'Document entity containing the actual waiver file',
  `is_exemption` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True if this is an exemption (attestation waiver not needed)',
  `exemption_reason` varchar(500) DEFAULT NULL COMMENT 'Reason why waiver was not required (only set for exemptions)',
  `retention_date` date NOT NULL COMMENT 'Date when this waiver can be deleted (calculated at upload time)',
  `status` varchar(50) NOT NULL DEFAULT 'active' COMMENT 'Status: active, expired, deleted',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about this waiver',
  `declined_at` datetime DEFAULT NULL COMMENT 'Timestamp when waiver was declined/rejected',
  `declined_by` int(11) DEFAULT NULL COMMENT 'Member ID who declined the waiver',
  `decline_reason` text DEFAULT NULL COMMENT 'Reason for declining the waiver',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_waivers_document` (`document_id`),
  KEY `idx_gathering_waivers_gathering` (`gathering_id`),
  KEY `idx_gathering_waivers_type` (`waiver_type_id`),
  KEY `idx_gathering_waivers_retention` (`retention_date`),
  KEY `idx_gathering_waivers_status` (`status`),
  KEY `idx_gathering_waivers_created` (`created`),
  KEY `idx_gathering_waivers_created_by` (`created_by`),
  KEY `fk_gathering_waivers_modified_by` (`modified_by`),
  KEY `fk_gathering_waivers_declined_by` (`declined_by`),
  KEY `idx_gathering_waivers_declined_at` (`declined_at`),
  KEY `idx_gathering_waivers_is_exemption` (`is_exemption`),
  CONSTRAINT `fk_gathering_waivers_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_gathering_waivers_declined_by` FOREIGN KEY (`declined_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_gathering_waivers_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `fk_gathering_waivers_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`),
  CONSTRAINT `fk_gathering_waivers_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_gathering_waivers_type` FOREIGN KEY (`waiver_type_id`) REFERENCES `waivers_waiver_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waivers_gathering_waivers`
--

LOCK TABLES `waivers_gathering_waivers` WRITE;
/*!40000 ALTER TABLE `waivers_gathering_waivers` DISABLE KEYS */;
INSERT INTO `waivers_gathering_waivers` VALUES
(1,50,1,1,0,NULL,'2032-10-31','active',NULL,NULL,NULL,NULL,'2025-10-31 19:25:21','2025-10-31 19:25:21',1,1,NULL),
(2,66,1,2,0,NULL,'2032-11-05','active',NULL,NULL,NULL,NULL,'2025-11-06 00:25:28','2025-11-06 00:25:28',1,1,NULL),
(3,66,1,3,0,NULL,'2032-11-05','active',NULL,NULL,NULL,NULL,'2025-11-06 00:26:42','2025-11-06 00:26:42',1,1,NULL),
(4,73,1,4,0,NULL,'2032-11-07','active',NULL,NULL,NULL,NULL,'2025-11-06 01:38:03','2025-11-06 01:38:03',1,1,NULL),
(5,79,1,5,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-06 02:02:05','2025-11-06 02:02:05',1,1,NULL),
(6,79,2,6,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-06 02:03:32','2025-11-06 02:03:32',1,1,NULL),
(7,80,1,7,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-06 02:09:04','2025-11-06 02:09:04',1,1,NULL),
(8,80,6,8,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-06 02:19:05','2025-11-06 02:19:05',1,1,NULL),
(9,80,1,9,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-14 01:52:42','2025-11-14 01:52:42',1,1,NULL),
(10,80,2,10,0,NULL,'2032-11-03','active',NULL,NULL,NULL,NULL,'2025-11-14 01:54:20','2025-11-14 01:54:20',1,1,NULL),
(11,80,3,11,0,NULL,'2045-11-03','active',NULL,NULL,NULL,NULL,'2025-11-14 01:55:02','2025-11-14 01:55:02',1,1,NULL),
(12,81,1,NULL,1,'All Participants had Blue Cards','2032-11-09','active',NULL,NULL,NULL,NULL,'2025-11-17 23:04:54','2025-11-17 23:04:54',1,2872,NULL),
(13,82,1,12,0,NULL,'2032-11-16','active',NULL,NULL,NULL,NULL,'2025-11-18 00:08:23','2025-11-18 00:08:23',1,2872,NULL),
(14,81,1,13,0,NULL,'2032-11-09','active',NULL,NULL,NULL,NULL,'2025-11-18 00:15:50','2025-11-18 00:15:49',1,2872,NULL),
(15,81,1,14,0,NULL,'2032-11-09','active',NULL,NULL,NULL,NULL,'2025-11-18 01:49:20','2025-11-18 01:49:20',1,1,NULL),
(16,81,1,15,0,NULL,'2032-11-09','active',NULL,NULL,NULL,NULL,'2025-11-18 01:50:06','2025-11-18 01:50:06',1,1,NULL),
(17,81,3,NULL,1,'No Minor present.','2045-11-09','active',NULL,NULL,NULL,NULL,'2025-11-18 02:35:48','2025-11-18 02:35:48',1,2872,NULL),
(18,82,3,NULL,1,'No Minor present.','2045-11-16','active',NULL,NULL,NULL,NULL,'2025-11-18 02:37:40','2025-11-18 02:37:40',1,2872,NULL),
(19,82,1,16,0,NULL,'2032-11-16','active',NULL,NULL,NULL,NULL,'2025-11-18 02:46:43','2025-11-18 02:46:43',1,2872,NULL),
(20,50,1,NULL,1,'Activity was Cancelled','2032-10-31','active',NULL,NULL,NULL,NULL,'2025-12-04 00:58:43','2025-12-04 00:58:43',1,2885,NULL),
(21,75,3,17,0,NULL,'2045-11-07','active',NULL,NULL,NULL,NULL,'2025-12-04 01:04:50','2025-12-04 01:04:50',1,2880,NULL),
(22,55,1,18,0,NULL,'2032-11-15','active',NULL,NULL,NULL,NULL,'2025-12-06 19:35:20','2025-12-06 19:35:20',1,2884,NULL),
(23,82,1,19,0,NULL,'2032-11-16','active',NULL,NULL,NULL,NULL,'2025-12-06 19:36:49','2025-12-06 19:36:49',1,2884,NULL),
(24,87,1,20,0,NULL,'2032-12-05','active',NULL,NULL,NULL,NULL,'2025-12-06 19:37:53','2025-12-06 19:37:53',1,2884,NULL),
(25,87,3,NULL,1,'No Minor present.','2045-12-05','active',NULL,NULL,NULL,NULL,'2025-12-06 19:38:45','2025-12-06 19:38:45',1,2884,NULL),
(26,82,1,21,0,NULL,'2032-11-16','active',NULL,NULL,NULL,NULL,'2025-12-06 20:51:45','2025-12-06 20:51:45',1,2884,NULL),
(27,82,1,NULL,1,'Activity was Cancelled','2032-11-16','active',NULL,NULL,NULL,NULL,'2025-12-06 20:53:53','2025-12-06 20:53:53',1,2884,NULL),
(28,55,1,22,0,NULL,'2032-11-15','active',NULL,NULL,NULL,NULL,'2025-12-06 20:56:46','2025-12-06 20:56:46',1,2884,NULL),
(29,85,1,NULL,1,'Activity was Cancelled','2032-11-19','active',NULL,NULL,NULL,NULL,'2025-12-06 21:14:47','2025-12-06 21:14:47',1,2884,NULL),
(30,87,1,23,0,NULL,'2032-12-05','active',NULL,NULL,NULL,NULL,'2025-12-06 21:17:47','2025-12-06 21:17:47',1,2884,NULL),
(31,66,1,24,0,NULL,'2032-11-05','active',NULL,NULL,NULL,NULL,'2025-12-06 21:48:38','2025-12-06 21:48:38',1,1,NULL),
(32,73,1,NULL,1,'All Participants had Blue Cards','2032-11-07','active',NULL,NULL,NULL,NULL,'2025-12-06 21:49:24','2025-12-06 21:49:24',1,1,NULL),
(33,87,3,25,0,NULL,'2045-12-05','active',NULL,NULL,NULL,NULL,'2025-12-15 16:27:34','2025-12-15 16:27:34',1,1,NULL),
(34,50,1,26,0,NULL,'2032-10-31','active',NULL,NULL,NULL,NULL,'2025-12-16 01:35:51','2025-12-16 01:35:51',1,1,NULL),
(35,50,5,27,0,NULL,'2032-10-31','active',NULL,NULL,NULL,NULL,'2025-12-16 01:36:18','2025-12-16 01:36:18',1,1,NULL),
(36,50,3,NULL,1,'No Minor present.','2045-10-31','active',NULL,NULL,NULL,NULL,'2025-12-16 01:36:45','2025-12-16 01:36:45',1,1,NULL),
(37,63,1,28,0,NULL,'2032-11-23','active',NULL,NULL,NULL,NULL,'2025-12-18 00:34:21','2025-12-18 00:34:21',1,1,NULL),
(38,65,1,NULL,1,'All Participants had Blue Cards','2032-11-02','active',NULL,NULL,NULL,NULL,'2025-12-29 09:00:53','2025-12-29 09:00:53',1,1,NULL),
(39,91,1,29,0,NULL,'2032-12-31','active',NULL,NULL,NULL,NULL,'2025-12-29 19:15:28','2025-12-29 19:15:28',1,2872,NULL),
(40,91,3,30,0,NULL,'2045-12-31','active',NULL,NULL,NULL,NULL,'2025-12-29 19:16:06','2025-12-29 19:16:06',1,2872,NULL),
(41,91,4,31,0,NULL,'2045-12-31','active',NULL,NULL,NULL,NULL,'2025-12-29 19:18:38','2025-12-29 19:18:38',1,2872,NULL),
(42,91,5,32,0,NULL,'2032-12-31','active',NULL,NULL,NULL,NULL,'2025-12-29 19:19:03','2025-12-29 19:19:03',1,2872,NULL),
(43,92,3,NULL,1,'No Minor present.','2046-01-01','active',NULL,NULL,NULL,NULL,'2025-12-29 19:20:18','2025-12-29 19:20:18',1,2872,NULL),
(44,92,1,33,0,NULL,'2033-01-01','active',NULL,NULL,NULL,NULL,'2025-12-29 19:20:35','2025-12-29 19:20:35',1,2872,NULL),
(45,92,5,34,0,NULL,'2033-01-01','active',NULL,NULL,NULL,NULL,'2025-12-29 19:21:05','2025-12-29 19:21:05',1,2872,NULL),
(46,93,3,NULL,1,'No Minor present.','2046-01-02','active',NULL,NULL,NULL,NULL,'2025-12-29 19:22:24','2025-12-29 19:22:24',1,2872,NULL),
(47,93,1,35,0,NULL,'2033-01-02','active',NULL,NULL,NULL,NULL,'2025-12-29 19:22:38','2025-12-29 19:22:38',1,2872,NULL),
(48,93,4,36,0,NULL,'2046-01-02','active',NULL,NULL,NULL,NULL,'2025-12-29 19:22:53','2025-12-29 19:22:53',1,2872,NULL),
(49,93,5,37,0,NULL,'2033-01-02','active',NULL,NULL,NULL,NULL,'2025-12-29 19:23:05','2025-12-29 19:23:05',1,2872,NULL),
(50,84,1,NULL,1,'All Participants had Blue Cards','2032-12-18','active',NULL,NULL,NULL,NULL,'2025-12-29 19:47:44','2025-12-29 19:47:44',1,2872,NULL),
(51,75,1,NULL,1,'All Participants had Blue Cards','2032-11-07','active',NULL,NULL,NULL,NULL,'2026-01-01 20:02:36','2026-01-01 20:02:36',1,2880,NULL),
(52,65,1,38,0,NULL,'2032-11-02','active',NULL,NULL,NULL,NULL,'2026-01-10 02:29:20','2026-01-10 02:29:19',1,1,NULL),
(53,99,1,39,0,NULL,'2033-01-07','active',NULL,NULL,NULL,NULL,'2026-01-10 02:41:14','2026-01-10 02:41:14',1,1,NULL),
(54,99,3,NULL,1,'No Minor present.','2046-01-07','active',NULL,NULL,NULL,NULL,'2026-01-10 02:41:52','2026-01-10 02:41:52',1,1,NULL),
(55,90,1,40,0,NULL,'2033-01-03','active',NULL,NULL,NULL,NULL,'2026-01-10 02:43:21','2026-01-10 02:43:21',1,1,NULL),
(56,90,5,41,0,NULL,'2033-01-03','active',NULL,NULL,NULL,NULL,'2026-01-10 02:43:48','2026-01-10 02:43:48',1,1,NULL),
(57,90,2,42,0,NULL,'2033-01-03','active',NULL,NULL,NULL,NULL,'2026-01-10 02:45:25','2026-01-10 02:45:25',1,1,NULL),
(58,66,3,NULL,1,'No Minor present.','2045-11-05','active',NULL,NULL,NULL,NULL,'2026-01-10 02:52:18','2026-01-10 02:52:18',1,1,NULL),
(59,74,3,NULL,1,'No Minor present.','2045-11-06','active',NULL,NULL,NULL,NULL,'2026-01-10 02:53:29','2026-01-10 02:53:29',1,1,NULL),
(60,74,1,43,0,NULL,'2032-11-06','active',NULL,NULL,NULL,NULL,'2026-01-10 02:53:50','2026-01-10 02:53:50',1,1,NULL),
(61,104,1,NULL,1,'All Participants had Blue Cards','2032-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:16:01','2026-01-10 03:16:01',1,1,NULL),
(62,104,1,44,0,NULL,'2032-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:31:00','2026-01-10 03:31:00',1,2889,NULL),
(63,104,2,45,0,NULL,'2032-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:31:24','2026-01-10 03:31:24',1,2889,NULL),
(64,104,3,NULL,1,'No Minor present.','2045-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:31:34','2026-01-10 03:31:34',1,2889,NULL),
(65,104,5,46,0,NULL,'2032-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:32:03','2026-01-10 03:32:03',1,2889,NULL),
(66,104,4,47,0,NULL,'2045-11-25','active',NULL,NULL,NULL,NULL,'2026-01-10 03:32:21','2026-01-10 03:32:21',1,2889,NULL),
(67,50,4,48,0,NULL,'2045-12-24','active',NULL,NULL,NULL,NULL,'2026-01-13 03:04:32','2026-01-13 03:04:32',1,2889,NULL),
(68,92,4,49,0,NULL,'2046-01-01','active',NULL,NULL,NULL,NULL,'2026-01-13 03:34:12','2026-01-13 03:34:12',1,1,NULL),
(69,96,5,50,0,NULL,'2033-01-02','declined',NULL,'2026-01-16 00:05:34',2891,'asdfasdfasdf','2026-01-16 00:05:34','2026-01-13 03:36:18',1,2891,NULL),
(70,90,3,NULL,1,'No Minor present.','2046-01-03','active',NULL,NULL,NULL,NULL,'2026-01-13 04:39:48','2026-01-13 04:39:48',1,2872,NULL),
(71,90,6,51,0,NULL,'2046-01-03','declined',NULL,'2026-01-15 02:16:00',2891,'asdfasdf','2026-01-15 02:16:08','2026-01-13 04:40:07',1,2891,NULL),
(72,112,3,NULL,1,'No Minor present.','2046-01-16','active',NULL,NULL,NULL,NULL,'2026-01-15 04:22:56','2026-01-15 04:22:56',1,2872,NULL),
(73,113,1,NULL,1,'All Participants had Blue Cards','2033-01-16','active',NULL,NULL,NULL,NULL,'2026-01-15 04:30:38','2026-01-15 04:30:38',1,2872,NULL),
(74,113,3,NULL,1,'No Minor present.','2046-01-16','active',NULL,NULL,NULL,NULL,'2026-01-15 04:34:20','2026-01-15 04:34:20',1,2872,NULL),
(75,90,1,NULL,1,'Activity was Cancelled','2033-01-03','active',NULL,NULL,NULL,NULL,'2026-01-16 00:05:51','2026-01-16 00:05:51',1,2891,NULL),
(76,114,3,NULL,1,'No Minor present.','2046-01-19','active',NULL,NULL,NULL,NULL,'2026-01-17 17:31:05','2026-01-17 17:31:05',1,2872,NULL),
(77,114,1,NULL,1,'All Participants had Blue Cards','2033-01-19','active',NULL,NULL,NULL,NULL,'2026-01-17 17:33:31','2026-01-17 17:33:31',1,2872,NULL);
/*!40000 ALTER TABLE `waivers_gathering_waivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waivers_phinxlog`
--

DROP TABLE IF EXISTS `waivers_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waivers_phinxlog`
--

LOCK TABLES `waivers_phinxlog` WRITE;
/*!40000 ALTER TABLE `waivers_phinxlog` DISABLE KEYS */;
INSERT INTO `waivers_phinxlog` VALUES
(20251021180737,'CreateWaiverTypes','2025-10-30 21:03:03','2025-10-30 21:03:03',0),
(20251021180804,'CreateGatheringActivityWaivers','2025-10-30 21:03:03','2025-10-30 21:03:04',0),
(20251021180827,'CreateGatheringWaivers','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251021180858,'CreateGatheringWaiverActivities','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251022150936,'AddDocumentIdToWaiverTypes','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251023162456,'AddDeletedToGatheringActivityWaiversUniqueIndex','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251024012044,'RemoveMemberIdFromGatheringWaivers','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251026000000,'AddDeclineFieldsToGatheringWaivers','2025-10-30 21:03:04','2025-10-30 21:03:04',0),
(20251106163803,'AddExemptionReasonsToWaiverTypes','2025-11-06 21:01:12','2025-11-06 21:01:12',0),
(20251106172020,'AddExemptionFieldsToGatheringWaivers','2025-11-06 21:01:12','2025-11-06 21:01:12',0),
(20251221083000,'DropGatheringWaiverActivities','2025-12-21 14:57:55','2025-12-21 14:57:55',0),
(20251223090000,'CreateGatheringWaiverClosures','2026-01-14 16:18:13','2026-01-14 16:18:13',0),
(20260131001511,'AddReadyToCloseToGatheringWaiverClosures','2026-02-01 00:09:57','2026-02-01 00:09:57',0);
/*!40000 ALTER TABLE `waivers_phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waivers_waiver_types`
--

DROP TABLE IF EXISTS `waivers_waiver_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_waiver_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Name of the waiver type (e.g., General Adult Waiver, Minor Waiver, Equestrian Waiver)',
  `description` text DEFAULT NULL COMMENT 'Description of this waiver type',
  `document_id` int(11) DEFAULT NULL COMMENT 'FK to documents.id for uploaded template files (null if using external URL)',
  `template_path` varchar(500) DEFAULT NULL COMMENT 'External URL to template (e.g., SCA.org link). Use document_id for uploaded files.',
  `retention_policy` text NOT NULL COMMENT 'JSON: {"anchor": "gathering_end_date", "duration": {"years": 7, "months": 6, "days": 0}}',
  `convert_to_pdf` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether to convert uploaded waivers to PDF format',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this waiver type is currently active',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `exemption_reasons` text DEFAULT NULL COMMENT 'JSON array of valid reasons for why a waiver might not be required (e.g., ["No minors present", "Activity cancelled", "Virtual event"])',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_waiver_types_name` (`name`),
  KEY `idx_waiver_types_active` (`is_active`),
  KEY `idx_waiver_types_document_id` (`document_id`),
  CONSTRAINT `waivers_waiver_types_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waivers_waiver_types`
--

LOCK TABLES `waivers_waiver_types` WRITE;
/*!40000 ALTER TABLE `waivers_waiver_types` DISABLE KEYS */;
INSERT INTO `waivers_waiver_types` VALUES
(1,'Participation Roster Waiver','Consent to Participate and Release Liability - required for all non-members.',NULL,'https://www.sca.org/wp-content/uploads/2019/12/rosterwaiver.pdf','{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":7}}',1,1,'2025-11-06 21:10:06','2025-10-30 21:12:58',1,1,NULL,'[\"Activity was Cancelled\",\"All Participants had Blue Cards\"]'),
(2,'Equestrian Activity Waiver','When horses are at the event.',NULL,'https://www.sca.org/wp-content/uploads/2024/03/AdultEquestrianWaivers2024.pdf','{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":7}}',1,1,'2025-11-02 01:43:23','2025-11-02 01:43:23',1,1,NULL,NULL),
(3,'Minor Waivers','Minor Waivers are required to be signed by the parent or legal guardian for any minor (a person under the age of majority, usually 18) who is attending an SCA event or participating in a covered activity.',NULL,'https://www.sca.org/wp-content/uploads/2024/04/chldwaiv.pdf','{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":20}}',1,1,'2025-11-18 02:34:43','2025-11-02 02:12:15',1,1,NULL,'[\"No Minor present.\"]'),
(4,'Family Minor Waiver','For families with more than one child, Family Minor Waivers are required to be signed by the parent or legal guardian for any minors (persons under the age of majority, usually 18) who is attending an SCA event or participating in a covered activity.',NULL,'https://sca.org/wp-content/uploads/2024/09/waiver_minor_family.pdf','{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":20}}',1,1,'2025-11-02 02:13:41','2025-11-02 02:12:52',1,1,NULL,NULL),
(5,'Gate Sign-In Sheets','The papers from Gate where all attendees signed into the event and documentation of their membership status was reviewed in person.',NULL,'https://ansteorra.org/exchequer/event-reports/','{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":7}}',1,1,'2025-11-06 01:32:09','2025-11-06 01:32:09',1,1,NULL,NULL),
(6,'All Blue Cards','Choose this option when all martial participants have shown their blue membership card.',NULL,NULL,'{\"anchor\":\"gathering_end_date\",\"duration\":{\"years\":7}}',1,0,'2025-11-11 03:24:31','2025-11-06 02:15:53',1,1,NULL,'[\"Activity was cancelled.\"]');
/*!40000 ALTER TABLE `waivers_waiver_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warrant_periods`
--

DROP TABLE IF EXISTS `warrant_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warrant_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warrant_periods`
--

LOCK TABLES `warrant_periods` WRITE;
/*!40000 ALTER TABLE `warrant_periods` DISABLE KEYS */;
INSERT INTO `warrant_periods` VALUES
(2,'2022-01-01','2025-01-18','2025-01-12 17:55:25',1),
(3,'2025-01-17','2025-07-21','2025-01-12 18:03:18',1),
(5,'2025-07-19','2025-12-31','2025-07-15 23:15:02',1),
(6,'2026-01-01','2026-07-01','2026-02-04 18:02:23',1);
/*!40000 ALTER TABLE `warrant_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warrant_roster_approvals`
--

DROP TABLE IF EXISTS `warrant_roster_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warrant_roster_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `warrant_roster_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `approved_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approver_id` (`approver_id`),
  KEY `warrant_roster_id` (`warrant_roster_id`),
  CONSTRAINT `warrant_roster_approvals_ibfk_1` FOREIGN KEY (`warrant_roster_id`) REFERENCES `warrant_rosters` (`id`),
  CONSTRAINT `warrant_roster_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=626 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warrant_roster_approvals`
--

LOCK TABLES `warrant_roster_approvals` WRITE;
/*!40000 ALTER TABLE `warrant_roster_approvals` DISABLE KEYS */;
INSERT INTO `warrant_roster_approvals` VALUES
(1,1,1,'2025-01-12 01:02:18'),
(605,418,1,'2025-10-30 21:13:42'),
(606,411,1,'2025-11-11 03:06:30'),
(607,414,1,'2025-11-11 03:06:40'),
(609,412,1,'2025-12-04 00:33:17'),
(610,413,1,'2025-12-04 00:33:22'),
(611,415,1,'2025-12-04 00:33:27'),
(612,416,1,'2025-12-04 00:33:30'),
(613,417,1,'2025-12-04 00:33:35'),
(614,421,1,'2025-12-04 00:35:16'),
(616,422,1,'2025-12-04 00:39:31'),
(617,430,1,'2025-12-30 18:24:10'),
(618,429,1,'2025-12-30 18:24:14'),
(619,425,1,'2025-12-30 18:24:21'),
(620,424,1,'2025-12-30 18:24:28'),
(621,423,1,'2025-12-30 18:24:32'),
(622,426,1,'2025-12-30 18:24:38'),
(623,427,1,'2025-12-30 18:24:42');
/*!40000 ALTER TABLE `warrant_roster_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warrant_rosters`
--

DROP TABLE IF EXISTS `warrant_rosters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warrant_rosters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `approvals_required` int(11) NOT NULL,
  `approval_count` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=437 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warrant_rosters`
--

LOCK TABLES `warrant_rosters` WRITE;
/*!40000 ALTER TABLE `warrant_rosters` DISABLE KEYS */;
INSERT INTO `warrant_rosters` VALUES
(1,'System Admin Warrant Set',1,1,'Approved',NULL,'2025-01-12 01:02:18',1,NULL),
(395,'Kingdom MoAS : Eirik Demoer',1,1,'Approved','2025-06-22 19:41:16','2025-06-22 19:38:59',1,1073),
(396,'Regional MoAS : Devon Demoer',1,1,'Approved','2025-06-22 19:51:44','2025-06-22 19:51:00',1,1073),
(397,'Regional MoAS : Devon Demoer',1,1,'Approved','2025-06-22 20:13:22','2025-06-22 20:12:55',1,1073),
(398,'Local MoAS : Bryce Demoer',1,1,'Approved','2025-06-22 20:35:39','2025-06-22 20:33:41',1,1073),
(399,'Local MoAS : Agatha Demoer',1,1,'Approved','2025-06-22 20:35:44','2025-06-22 20:34:23',1,1073),
(400,'Regional MoAS : Devon Demoer',1,1,'Approved','2025-06-22 20:35:47','2025-06-22 20:35:28',1,1073),
(401,'Arts & Sciences roster for 2025-01-17 ~ 2025-07-21',1,1,'Approved','2025-08-27 23:22:50','2025-06-22 20:51:50',1,669),
(402,'Kingdom MoAS Deputy : Haylee Demoer',1,1,'Approved','2025-08-07 21:09:42','2025-06-25 02:32:54',1,1073),
(404,'Regional Rapier Marshal : Leonard Demoer',1,1,'Approved','2025-08-07 21:09:34','2025-08-07 21:09:06',1,1073),
(405,'Kingdom Rapier Marshal : Leonard Demoer',1,1,'Approved','2025-08-07 21:11:03','2025-08-07 21:10:56',1,1073),
(411,'Local Seneschal : Bryce Demoer',1,1,'Approved','2025-11-11 03:06:30','2025-08-30 21:08:09',1,2881),
(412,'Regional Seneschal : Caroline Demoer',1,1,'Approved','2025-12-04 00:33:17','2025-08-30 21:09:16',1,1),
(413,'Regional Armored Marshal : Devon Demoer',1,1,'Approved','2025-12-04 00:33:22','2025-08-30 21:10:12',1,1),
(414,'Kingdom Seneschal : Eirik Demoer',1,1,'Approved','2025-11-11 03:06:40','2025-08-30 21:11:04',1,2881),
(415,'Kingdom Rapier Marshal : Garun Demoer',1,1,'Approved','2025-12-04 00:33:27','2025-08-30 21:12:12',1,1),
(416,'Local Treasurer : Mel Local Exch and Kingdom Social Demoer',1,1,'Approved','2025-12-04 00:33:30','2025-08-30 21:31:13',1,1),
(417,'Kingdom Social Media Officer : Mel Local Exch and Kingdom Social Demoer',1,1,'Approved','2025-12-04 00:33:35','2025-08-30 21:31:30',1,1),
(418,'At Large: Armored Authorizing Marshal : Admin von Admin',1,1,'Approved','2025-10-30 21:13:42','2025-09-12 02:51:12',1,1),
(421,'Regional MoAS : Devon Regional Armored Demoer',1,1,'Approved','2025-12-04 00:35:16','2025-12-04 00:35:09',1,1),
(422,'Local Landed : Kal Local Landed w Canton Demoer',1,1,'Approved','2025-12-04 00:39:31','2025-12-04 00:39:26',1,1),
(423,'Kingdom Chatelaine : Bryce Local Seneschal Demoer',1,1,'Approved','2025-12-30 18:24:32','2025-12-16 01:54:50',1,2881),
(424,'At Large: C&T 2 Handed Weapons Authorizing Marshal : Nester C&T Marshal Demoer',1,1,'Approved','2025-12-30 18:24:28','2025-12-27 20:33:21',1,2881),
(425,'At Large: C&T Authorizing Marshal : Nester C&T Marshal Demoer',1,1,'Approved','2025-12-30 18:24:21','2025-12-27 20:33:33',1,2881),
(426,'Local Chatelaine : Olivia Local Chatelaine Demoer',1,1,'Approved','2025-12-30 18:24:38','2025-12-27 20:51:51',1,2881),
(427,'Local Armored Marshal : Olivia Local Chatelaine Demoer',1,1,'Approved','2025-12-30 18:24:42','2025-12-27 20:54:24',1,2881),
(428,'Kingdom MoAS : Haylee Kingdom MoAS Deputy Demoer',1,NULL,'Pending','2025-12-27 21:05:53','2025-12-27 21:05:53',1,1),
(429,'Local Landed : Kal Local Landed w Canton Demoer',1,1,'Approved','2025-12-30 18:24:14','2025-12-30 18:23:36',1,2881),
(430,'Local Landed : Kal Local Landed w Canton Demoer',1,1,'Approved','2025-12-30 18:24:10','2025-12-30 18:23:48',1,2881),
(434,'Seneschallate roster for 2026-01-01 ~ 2026-07-01',1,NULL,'Pending','2026-01-14 22:20:37','2026-01-14 22:20:37',1,2875),
(436,'Principality Consort : Jael Principality Coronet Demoer',1,NULL,'Pending','2026-02-04 18:02:38','2026-02-04 18:02:38',1,1);
/*!40000 ALTER TABLE `warrant_rosters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warrants`
--

DROP TABLE IF EXISTS `warrants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warrants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `member_id` int(11) NOT NULL,
  `warrant_roster_id` int(11) NOT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` int(11) NOT NULL,
  `member_role_id` int(11) DEFAULT NULL,
  `expires_on` datetime DEFAULT NULL,
  `start_on` datetime DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `revoked_reason` varchar(255) DEFAULT '',
  `revoker_id` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `entity_type` (`entity_type`),
  KEY `start_on` (`start_on`),
  KEY `expires_on` (`expires_on`),
  KEY `member_id` (`member_id`),
  KEY `member_role_id` (`member_role_id`),
  KEY `warrant_roster_id` (`warrant_roster_id`),
  CONSTRAINT `warrants_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `warrants_ibfk_2` FOREIGN KEY (`member_role_id`) REFERENCES `member_roles` (`id`),
  CONSTRAINT `warrants_ibfk_3` FOREIGN KEY (`warrant_roster_id`) REFERENCES `warrant_rosters` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2536 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warrants`
--

LOCK TABLES `warrants` WRITE;
/*!40000 ALTER TABLE `warrants` DISABLE KEYS */;
INSERT INTO `warrants` VALUES
(1,'System Admin Warrant',1,1,'Direct Grant',-1,1,'2100-10-10 00:00:00','2020-01-01 00:00:00','2020-01-01 00:00:00','Current',NULL,NULL,NULL,'2025-01-12 01:02:18',1,NULL),
(2435,'Hiring Warrant: Ansteorra - Kingdom MoAS',2875,395,'Officers.Officers',928,362,'2025-08-30 21:10:50','2025-06-22 19:41:16','2025-06-22 19:41:16','Deactivated','cleaning up demo users',1073,'2025-08-30 21:10:50','2025-06-22 19:38:59',1,1073),
(2436,'Hiring Warrant: Central Region - Regional MoAS',2874,396,'Officers.Officers',929,NULL,'2025-06-22 20:12:37','2025-06-22 19:51:44','2025-06-22 19:51:44','Deactivated','test release',2875,'2025-06-22 20:12:37','2025-06-22 19:51:00',1,2875),
(2437,'Hiring Warrant: Central Region - Regional MoAS',2874,397,'Officers.Officers',930,363,'2025-08-30 21:10:03','2025-06-22 20:13:22','2025-06-22 20:13:22','Deactivated','cleaning up demo users',1073,'2025-08-30 21:10:03','2025-06-22 20:12:55',1,1073),
(2438,'Hiring Warrant: Barony of Bonwicke - Local MoAS',2872,398,'Officers.Officers',931,NULL,'2025-08-30 21:06:58','2025-06-22 20:35:39','2025-06-22 20:35:39','Deactivated','cleaning up demo users',1073,'2025-08-30 21:06:58','2025-06-22 20:33:41',1,1073),
(2439,'Hiring Warrant: Shire of Graywood - Local MoAS',2871,399,'Officers.Officers',932,NULL,'2025-07-21 00:00:00','2025-06-22 20:35:44','2025-06-22 20:35:44','Expired','',NULL,'2025-08-07 21:09:06','2025-06-22 20:34:23',1,2875),
(2440,'Hiring Warrant: Southern Region - Regional MoAS',2874,400,'Officers.Officers',933,364,'2025-07-21 00:00:00','2025-06-22 20:35:47','2025-06-22 20:35:47','Expired','',NULL,'2025-08-07 21:09:06','2025-06-22 20:35:28',1,2875),
(2470,'Renewal: Ansteorra Kingdom MoAS',2875,401,'Officers.Officers',928,362,'2025-07-21 00:00:00','2025-08-27 23:22:50','2025-08-27 23:22:50','Expired','',NULL,'2025-08-27 23:23:04','2025-06-22 20:51:50',1,669),
(2471,'Renewal: Central Region Regional MoAS',2874,401,'Officers.Officers',930,363,'2025-07-21 00:00:00','2025-08-27 23:22:50','2025-08-27 23:22:50','Expired','',NULL,'2025-08-27 23:23:04','2025-06-22 20:51:50',1,669),
(2472,'Renewal: Barony of Bonwicke Local MoAS',2872,401,'Officers.Officers',931,NULL,'2025-07-21 00:00:00','2025-08-27 23:22:50','2025-08-27 23:22:50','Expired','',NULL,'2025-08-27 23:23:04','2025-06-22 20:51:50',1,669),
(2473,'Renewal: Shire of Graywood Local MoAS',2871,401,'Officers.Officers',932,NULL,'2025-07-21 00:00:00','2025-08-27 23:22:50','2025-08-27 23:22:50','Expired','',NULL,'2025-08-27 23:23:04','2025-06-22 20:51:50',1,669),
(2474,'Renewal: Southern Region Regional MoAS',2874,401,'Officers.Officers',933,364,'2025-07-21 00:00:00','2025-08-27 23:22:50','2025-08-27 23:22:50','Expired','',NULL,'2025-08-27 23:23:04','2025-06-22 20:51:50',1,669),
(2475,'Hiring Warrant: Ansteorra - Kingdom MoAS Deputy (Demoer Deputy)',2877,402,'Officers.Officers',934,NULL,'2025-12-27 21:06:07','2025-08-07 21:09:42','2025-08-07 21:09:42','Deactivated','fixing uat office assignments',1,'2025-12-27 21:06:07','2025-06-25 02:32:54',1,1),
(2500,'Hiring Warrant: Vindheim - Regional Rapier Marshal',2882,404,'Officers.Officers',942,NULL,'2025-08-30 21:14:38','2025-08-07 21:09:34','2025-08-07 21:09:34','Deactivated','cleaning up demo users',1073,'2025-08-30 21:14:38','2025-08-07 21:09:06',1,1073),
(2501,'Hiring Warrant: Ansteorra - Kingdom Rapier Marshal',2882,405,'Officers.Officers',943,372,'2025-08-30 21:12:12','2025-08-07 21:11:03','2025-08-07 21:11:03','Deactivated','Replaced by new officer',1073,'2025-08-30 21:12:12','2025-08-07 21:10:56',1,1073),
(2505,'Hiring Warrant: Barony of Stargate - Local Seneschal',2872,411,'Officers.Officers',949,374,'2026-07-01 00:00:00','2025-11-11 03:06:30','2025-11-11 03:06:30','Current','',NULL,NULL,'2025-08-30 21:08:09',1,1),
(2506,'Hiring Warrant: Central Region - Regional Seneschal',2873,412,'Officers.Officers',950,375,'2026-07-01 00:00:00','2025-12-04 00:33:17','2025-12-04 00:33:17','Current','',NULL,NULL,'2025-08-30 21:09:16',1,NULL),
(2507,'Hiring Warrant: Central Region - Regional Armored Marshal',2874,413,'Officers.Officers',951,NULL,'2026-07-01 00:00:00','2025-12-04 00:33:22','2025-12-04 00:33:22','Current','',NULL,NULL,'2025-08-30 21:10:12',1,NULL),
(2508,'Hiring Warrant: Ansteorra - Kingdom Seneschal',2875,414,'Officers.Officers',952,376,'2026-07-01 00:00:00','2025-11-11 03:06:40','2025-11-11 03:06:40','Current','',NULL,NULL,'2025-08-30 21:11:04',1,1),
(2509,'Hiring Warrant: Ansteorra - Kingdom Rapier Marshal',2876,415,'Officers.Officers',953,377,'2026-07-01 00:00:00','2025-12-04 00:33:27','2025-12-04 00:33:27','Current','',NULL,NULL,'2025-08-30 21:12:12',1,NULL),
(2510,'Hiring Warrant: Shire of Seawinds - Local Treasurer',2883,416,'Officers.Officers',956,NULL,'2026-07-01 00:00:00','2025-12-04 00:33:30','2025-12-04 00:33:30','Current','',NULL,NULL,'2025-08-30 21:31:13',1,NULL),
(2511,'Hiring Warrant: Ansteorra - Kingdom Social Media Officer',2883,417,'Officers.Officers',957,NULL,'2026-07-01 00:00:00','2025-12-04 00:33:35','2025-12-04 00:33:35','Current','',NULL,NULL,'2025-08-30 21:31:30',1,NULL),
(2512,'Hiring Warrant: Ansteorra - At Large: Armored Authorizing Marshal',1,418,'Officers.Officers',958,380,'2026-07-01 00:00:00','2025-10-30 21:13:42','2025-10-30 21:13:42','Current','',NULL,NULL,'2025-09-12 02:51:12',1,NULL),
(2515,'Manual Request Warrant: Southern Region - Regional MoAS',2874,421,'Officers.Officers',933,364,'2026-07-01 00:00:00','2025-12-04 00:35:16','2025-12-04 00:35:16','Current','',NULL,NULL,'2025-12-04 00:35:09',1,NULL),
(2516,'Manual Request Warrant: Barony of Stargate - Local Landed',2880,422,'Officers.Officers',961,384,'2026-07-01 00:00:00','2025-12-04 00:39:31','2025-12-04 00:39:31','Current','',NULL,NULL,'2025-12-04 00:39:26',1,NULL),
(2517,'Hiring Warrant: Ansteorra - Kingdom Chatelaine',2872,423,'Officers.Officers',962,385,'2025-12-27 20:39:52','2025-12-16 00:00:00',NULL,'Deactivated','need Bryce Local Sen to not have a Kingdom office for testing',2872,'2025-12-27 20:39:52','2025-12-16 01:54:50',1,2872),
(2518,'Hiring Warrant: Ansteorra - At Large: C&T 2 Handed Weapons Authorizing Marshal',2886,424,'Officers.Officers',963,386,'2026-07-01 00:00:00','2025-12-30 18:24:28','2025-12-30 18:24:28','Current','',NULL,NULL,'2025-12-27 20:33:21',1,1),
(2519,'Hiring Warrant: Ansteorra - At Large: C&T Authorizing Marshal',2886,425,'Officers.Officers',964,387,'2026-07-01 00:00:00','2025-12-30 18:24:21','2025-12-30 18:24:21','Current','',NULL,NULL,'2025-12-27 20:33:33',1,1),
(2520,'Hiring Warrant: Shire of Rosenfeld - Local Chatelaine',2887,426,'Officers.Officers',965,NULL,'2026-07-01 00:00:00','2025-12-30 18:24:38','2025-12-30 18:24:38','Current','',NULL,NULL,'2025-12-27 20:51:51',1,1),
(2521,'Hiring Warrant: Shire of Rosenfeld - Local Armored Marshal',2887,427,'Officers.Officers',966,388,'2025-12-27 20:54:59','2025-12-27 00:00:00',NULL,'Deactivated','need olivia to only have local chatelaine, this was a test appointment',1,'2025-12-27 20:54:59','2025-12-27 20:54:24',1,1),
(2522,'Hiring Warrant: Ansteorra - Kingdom MoAS',2877,428,'Officers.Officers',967,389,'2026-07-01 00:00:00','2025-12-27 00:00:00',NULL,'Pending','',NULL,'2025-12-27 21:05:53','2025-12-27 21:05:53',1,1),
(2523,'Manual Request Warrant: Barony of the Steppes - Local Landed',2880,429,'Officers.Officers',936,366,'2026-07-01 00:00:00','2025-12-30 18:24:14','2025-12-30 18:24:14','Current','',NULL,NULL,'2025-12-30 18:23:36',1,1),
(2524,'Manual Request Warrant: Canton of Glaslyn - Local Landed',2880,430,'Officers.Officers',939,369,'2026-07-01 00:00:00','2025-12-30 18:24:10','2025-12-30 18:24:10','Current','',NULL,NULL,'2025-12-30 18:23:48',1,1),
(2528,'Renewal: Barony of Stargate Local Seneschal',2872,434,'Officers.Officers',949,381,'2026-07-01 00:00:00','2025-08-30 00:00:00',NULL,'Pending','',NULL,'2026-01-14 22:20:37','2026-01-14 22:20:37',1,2875),
(2529,'Renewal: Central Region Regional Seneschal',2873,434,'Officers.Officers',950,375,'2026-07-01 00:00:00','2025-08-30 00:00:00',NULL,'Pending','',NULL,'2026-01-14 22:20:37','2026-01-14 22:20:37',1,2875),
(2530,'Renewal: Ansteorra Kingdom Seneschal',2875,434,'Officers.Officers',952,376,'2026-07-01 00:00:00','2025-08-30 00:00:00',NULL,'Pending','',NULL,'2026-01-14 22:20:37','2026-01-14 22:20:37',1,2875),
(2531,'Renewal: Ansteorra Kingdom Social Media Officer',2883,434,'Officers.Officers',957,NULL,'2026-07-01 00:00:00','2025-08-30 00:00:00',NULL,'Pending','',NULL,'2026-01-14 22:20:37','2026-01-14 22:20:37',1,2875),
(2535,'Hiring Warrant: Vindheim - Principality Consort',2879,436,'Officers.Officers',975,395,'2026-07-01 00:00:00','2026-02-04 00:00:00',NULL,'Pending','',NULL,'2026-02-04 18:02:38','2026-02-04 18:02:38',1,1);
/*!40000 ALTER TABLE `warrants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'amp-seed'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-06 13:52:58
