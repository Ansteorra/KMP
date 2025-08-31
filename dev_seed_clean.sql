/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: amp-seed
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB-0+deb12u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!40101 SET NAMES utf8mb4 */
;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */
;
/*!40103 SET TIME_ZONE='+00:00' */
;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */
;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */
;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */
;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */
;

--
-- Table structure for table `activities_activities`
--

DROP TABLE IF EXISTS `activities_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 67 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `activities_activities`
--

LOCK TABLES `activities_activities` WRITE;
/*!40000 ALTER TABLE `activities_activities` DISABLE KEYS */
;
INSERT INTO
    `activities_activities`
VALUES (
        1,
        'Armored',
        48,
        1,
        NULL,
        16,
        200,
        1,
        1,
        1001,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        2,
        'Fiberglass Spear',
        48,
        1,
        NULL,
        16,
        200,
        1,
        1,
        1002,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        3,
        'Armored Field Marshal',
        48,
        1,
        NULL,
        18,
        200,
        1,
        1,
        1003,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        4,
        'Rapier',
        48,
        2,
        NULL,
        16,
        200,
        1,
        1,
        1004,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        5,
        'Rapier Field Marshal',
        48,
        2,
        NULL,
        18,
        200,
        1,
        1,
        1005,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        6,
        'Cut And Thrust',
        48,
        3,
        NULL,
        16,
        200,
        1,
        1,
        1006,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        7,
        'Rapier Spear',
        48,
        2,
        NULL,
        16,
        200,
        1,
        1,
        1007,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        8,
        'Siege',
        48,
        4,
        NULL,
        16,
        200,
        1,
        1,
        1008,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        9,
        'Siege Marshal',
        48,
        4,
        NULL,
        16,
        200,
        1,
        1,
        1009,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        10,
        'Armored Combat Archery',
        48,
        4,
        NULL,
        16,
        200,
        1,
        1,
        1010,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        11,
        'Rapier Combat Archery',
        48,
        4,
        NULL,
        16,
        200,
        1,
        1,
        1011,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        12,
        'Combat Archery Marshal',
        48,
        4,
        NULL,
        18,
        200,
        1,
        1,
        1012,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        13,
        'Target Archery Marshal',
        48,
        4,
        NULL,
        18,
        200,
        1,
        1,
        1013,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        14,
        'Thrown Weapons Marshal',
        48,
        4,
        NULL,
        18,
        200,
        1,
        1,
        1014,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        15,
        'Youth Boffer 1',
        48,
        7,
        NULL,
        6,
        13,
        1,
        1,
        1015,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        16,
        'Youth Boffer 2',
        48,
        7,
        NULL,
        6,
        13,
        1,
        1,
        1016,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        17,
        'Youth Boffer 3',
        48,
        7,
        NULL,
        6,
        13,
        1,
        1,
        1017,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        18,
        'Youth Boffer Marshal',
        48,
        7,
        NULL,
        18,
        200,
        1,
        1,
        1018,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        19,
        'Youth Boffer Junior Marshal',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1019,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        20,
        'Youth Armored Combat',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1020,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        21,
        'Youth Armored Combat Two Weapons',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1021,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        22,
        'Youth Armored Combat Spear',
        48,
        7,
        NULL,
        13,
        27,
        1,
        1,
        1022,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        23,
        'Youth Armored Combat Weapon Shield',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1023,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        24,
        'Youth Armored Combat Grea Weapons',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1024,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        25,
        'Youth Armored Field Marshal',
        48,
        7,
        NULL,
        18,
        200,
        1,
        1,
        1025,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        26,
        'Youth Armored Combat Junior Marshal',
        48,
        7,
        NULL,
        13,
        17,
        1,
        1,
        1026,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        27,
        'Youth Rapier Combat Foil',
        48,
        6,
        NULL,
        10,
        17,
        1,
        1,
        1027,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        28,
        'Youth Rapier Combat Epee',
        48,
        6,
        NULL,
        10,
        17,
        1,
        1,
        1028,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        29,
        'Youth Rapier Combat Heavy Rapier',
        48,
        6,
        NULL,
        12,
        17,
        1,
        1,
        1029,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        30,
        'Youth Rapier Combat Plastic Sword',
        48,
        6,
        NULL,
        6,
        13,
        1,
        1,
        1030,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        31,
        'Youth Rapier Combat Melee',
        48,
        6,
        NULL,
        13,
        17,
        1,
        1,
        1031,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        32,
        'Youth Rapier Combat Offensive Secondary',
        48,
        6,
        NULL,
        13,
        17,
        1,
        1,
        1032,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        33,
        'Youth Rapier Combat Defensive Secondary',
        48,
        6,
        NULL,
        13,
        17,
        1,
        1,
        1033,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        34,
        'Youth Rapier Field Marshal',
        48,
        6,
        NULL,
        18,
        200,
        1,
        1,
        1034,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        35,
        'Youth Rapier Combat Plastic Sword Marshal',
        48,
        6,
        NULL,
        18,
        200,
        1,
        1,
        1035,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        36,
        'Experimental: Rapier Spear',
        48,
        2,
        NULL,
        99,
        101,
        1,
        1,
        1036,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        37,
        'C&T 2 Handed Weapon',
        48,
        3,
        NULL,
        16,
        200,
        1,
        1,
        1037,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        38,
        'Equestrian Field Marshal',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1038,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        39,
        'General Riding',
        48,
        5,
        NULL,
        5,
        200,
        1,
        1,
        1039,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        40,
        'Mounted Games',
        48,
        5,
        NULL,
        5,
        200,
        1,
        1,
        1040,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        41,
        'Mounted Combat',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1041,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        42,
        'Foam Jousting',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1042,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        43,
        'Driving',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1043,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        44,
        'Wooden Lance',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1044,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        45,
        'Mounted Archery',
        48,
        5,
        NULL,
        18,
        200,
        1,
        1,
        1045,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        50,
        'C&T - Historic Combat Experiment',
        48,
        3,
        NULL,
        16,
        200,
        1,
        1,
        1050,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        51,
        'Reduced Rapier Armor Experiment ',
        48,
        2,
        NULL,
        18,
        200,
        1,
        1,
        1051,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL
    ),
    (
        52,
        'Deleted: Armored Authorizing Marshal',
        48,
        1,
        1001,
        18,
        200,
        1,
        1,
        1052,
        '2025-01-17 16:17:12',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:17:12'
    ),
    (
        53,
        'Deleted: Rapier Authorizing Marshal',
        48,
        2,
        1002,
        18,
        200,
        1,
        1,
        1053,
        '2025-01-17 16:26:36',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:26:36'
    ),
    (
        54,
        'Deleted: Target Archery Authorizing Marshal',
        48,
        4,
        1004,
        18,
        200,
        1,
        1,
        1054,
        '2025-01-17 16:27:15',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:27:15'
    ),
    (
        55,
        'Deleted: C&T Authorizing Marshal',
        48,
        3,
        1003,
        18,
        200,
        1,
        1,
        1055,
        '2025-01-17 16:56:09',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:56:09'
    ),
    (
        56,
        'Deleted: Equestrian Authorizing Marshal ',
        48,
        5,
        1005,
        18,
        200,
        1,
        1,
        1056,
        '2025-01-17 16:21:31',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:21:31'
    ),
    (
        57,
        'Deleted: Youth Armored Authorizing Marshal',
        48,
        7,
        1007,
        18,
        200,
        1,
        1,
        1057,
        '2025-01-17 16:28:41',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:28:41'
    ),
    (
        58,
        'Deleted: Youth Rapier Authorizing Marshal',
        48,
        6,
        1006,
        18,
        200,
        1,
        1,
        1058,
        '2025-01-17 16:29:04',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:29:04'
    ),
    (
        59,
        'Deleted: Siege Authorizing Marshal',
        48,
        4,
        1008,
        18,
        200,
        1,
        1,
        1059,
        '2025-01-17 16:27:25',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:27:25'
    ),
    (
        60,
        'Deleted: Rapier Spear Authorizing Marshal',
        48,
        2,
        1009,
        18,
        200,
        1,
        1,
        1060,
        '2025-01-17 16:27:03',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:27:03'
    ),
    (
        61,
        'Deleted: Thrown Weapons Authorizing Marshal',
        48,
        4,
        1010,
        18,
        200,
        1,
        1,
        1061,
        '2025-01-17 16:27:41',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:27:41'
    ),
    (
        62,
        'Deleted: Combat Archery Authorizing Marshal',
        48,
        4,
        1011,
        18,
        200,
        1,
        1,
        1062,
        '2025-01-17 16:21:24',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:21:24'
    ),
    (
        63,
        'Deleted: Two Handed C&T Authorizing Marshal',
        48,
        3,
        1012,
        18,
        200,
        1,
        1,
        1063,
        '2025-01-17 21:58:04',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 21:58:04'
    ),
    (
        64,
        'Deleted: Wooden Lance Authorizing Marshal',
        48,
        5,
        1013,
        18,
        200,
        1,
        1,
        1064,
        '2025-01-17 16:54:34',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:54:34'
    ),
    (
        65,
        'Deleted: Reduced Armor Experiement Authorizing Marshal',
        48,
        2,
        1014,
        18,
        200,
        1,
        1,
        1065,
        '2025-01-17 16:26:52',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 16:26:52'
    ),
    (
        66,
        'Deleted: C&T - Historic Combat Experiment Authorizing Marshal',
        48,
        3,
        1015,
        18,
        200,
        1,
        1,
        1066,
        '2025-01-17 21:58:09',
        '2024-09-29 15:47:04',
        1,
        1096,
        '2025-01-17 21:58:09'
    );
/*!40000 ALTER TABLE `activities_activities` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `activities_activity_groups`
--

DROP TABLE IF EXISTS `activities_activity_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 8 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `activities_activity_groups`
--

LOCK TABLES `activities_activity_groups` WRITE;
/*!40000 ALTER TABLE `activities_activity_groups` DISABLE KEYS */
;
INSERT INTO
    `activities_activity_groups`
VALUES (
        1,
        'Armored Combat',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        2,
        'Rapier',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        3,
        'Cut & Thrust',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        4,
        'Missile',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        5,
        'Equestrian',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        6,
        'Youth Rapier',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        7,
        'Youth Armored',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    );
/*!40000 ALTER TABLE `activities_activity_groups` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `activities_authorization_approvals`
--

DROP TABLE IF EXISTS `activities_authorization_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 9312 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `activities_authorization_approvals`
--

LOCK TABLES `activities_authorization_approvals` WRITE;
/*!40000 ALTER TABLE `activities_authorization_approvals` DISABLE KEYS */
;
INSERT INTO
    `activities_authorization_approvals`
VALUES (
        9306,
        9311,
        1,
        '562b4c599fd7f065f09baa45be9ba0ac',
        '2025-06-22 18:58:39',
        '2025-06-22 19:00:16',
        0,
        'test deny'
    ),
    (
        9307,
        9312,
        1,
        '0df999ac1381d559b9627621ce856fcb',
        '2025-06-22 19:10:19',
        '2025-06-22 19:10:48',
        0,
        'Test Deny'
    ),
    (
        9308,
        9313,
        1,
        'c09f8cdd17e8dfc4b165e566c06a7a74',
        '2025-06-22 19:11:32',
        '2025-06-22 19:13:58',
        1,
        NULL
    );
/*!40000 ALTER TABLE `activities_authorization_approvals` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `activities_authorizations`
--

DROP TABLE IF EXISTS `activities_authorizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 9317 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `activities_authorizations`
--

LOCK TABLES `activities_authorizations` WRITE;
/*!40000 ALTER TABLE `activities_authorizations` DISABLE KEYS */
;
INSERT INTO
    `activities_authorizations`
VALUES (
        9311,
        2872,
        6,
        NULL,
        '2025-06-22 19:00:15',
        '2025-06-22 19:00:15',
        '2025-06-22 18:58:39',
        0,
        'Denied',
        'test deny',
        1096,
        0
    ),
    (
        9312,
        2872,
        6,
        NULL,
        '2025-06-22 19:10:47',
        '2025-06-22 19:10:47',
        '2025-06-22 19:10:19',
        0,
        'Denied',
        'Test Deny',
        1096,
        0
    ),
    (
        9313,
        2872,
        6,
        NULL,
        '2029-06-22 19:13:58',
        '2025-06-22 19:13:58',
        '2025-06-22 19:11:32',
        1,
        'Approved',
        '',
        NULL,
        0
    );
/*!40000 ALTER TABLE `activities_authorizations` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `activities_phinxlog`
--

DROP TABLE IF EXISTS `activities_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `activities_phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `activities_phinxlog`
--

LOCK TABLES `activities_phinxlog` WRITE;
/*!40000 ALTER TABLE `activities_phinxlog` DISABLE KEYS */
;
INSERT INTO
    `activities_phinxlog`
VALUES (
        20240614001010,
        'InitActivities',
        '2024-09-29 15:47:03',
        '2024-09-29 15:47:03',
        0
    ),
    (
        20250228144601,
        'MakeTermMonthsNotYears',
        '2025-03-01 14:24:26',
        '2025-03-01 14:24:26',
        0
    );
/*!40000 ALTER TABLE `activities_phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 81 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */
;
INSERT INTO
    `app_settings`
VALUES (
        1,
        'Activity.SecretaryEmail',
        'amp-secretary@webminister.ansteorra.org',
        '2025-01-13 23:19:32',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        2,
        'KMP.KingdomName',
        'Ansteorra',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        3,
        'Activity.SecretaryName',
        'Lady Megan Flower del Wall',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        4,
        'Members.AccountVerificationContactEmail',
        'amp-secretary@webminister.ansteorra.org',
        '2025-01-13 23:20:27',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        5,
        'Members.AccountDisabledContactEmail',
        'amp-secretary@webminister.ansteorra.org',
        '2025-01-13 23:20:04',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        6,
        'Email.SystemEmailFromAddress',
        'donotreply@amp.ansteorra.org',
        '2025-07-09 11:55:12',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        7,
        'KMP.LongSiteTitle',
        'Ansteorra Management Portal',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        8,
        'Members.NewMinorSecretaryEmail',
        'amp-secretary@webminister.ansteorra.org',
        '2025-01-13 23:20:49',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        9,
        'KMP.ShortSiteTitle',
        'UAT',
        '2025-03-01 15:02:10',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        10,
        'Member.ExternalLink.Order of Precedence',
        'https://op.ansteorra.org/people/id/{{additional_info->OrderOfPrecedence_Id}}',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        0
    ),
    (
        11,
        'Member.AdditionalInfo.OrderOfPrecedence_Id',
        'number|user',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        0
    ),
    (
        12,
        'Member.MobileCard.BgColor',
        'gold',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        13,
        'Member.MobileCard.ThemeColor',
        'gold',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        14,
        'Member.ViewCard.Graphic',
        'auth_card_back.gif',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        15,
        'Member.ViewCard.HeaderColor',
        'gold',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        16,
        'Member.ViewCard.Template',
        'view_card',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        17,
        'Member.ViewMobileCard.Template',
        'view_mobile_card',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        18,
        'Members.NewMemberSecretaryEmail',
        'amp-secretary@webminister.ansteorra.org',
        '2025-01-13 23:20:40',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        19,
        'Plugin.Activities.Active',
        'yes',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        20,
        'Plugin.Awards.Active',
        'yes',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        21,
        'Plugin.GitHubIssueSubmitter.Active',
        'yes',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        22,
        'Plugin.Officers.Active',
        'yes',
        '2025-01-12 01:03:43',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        23,
        'Awards.CallIntoCourtOptions',
        'I do not know,Never,With Notice,Without Notice,With notice given to another person,With notice given to me,With notice given to me and another person',
        '2024-09-30 01:51:18',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        1
    ),
    (
        24,
        'Awards.CourtAvailabilityOptions',
        'I do not know,None,Morning,Evening,Any',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        25,
        'Awards.RecButtonClass',
        'btn-warning',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        26,
        'Email.SiteAdminSignature',
        'Webminister',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        27,
        'KMP.AppSettings.HelpUrl',
        'https://github.com/Ansteorra/KMP/wiki/App-Settings',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        28,
        'KMP.BannerLogo',
        'badge.png',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        29,
        'KMP.EnablePublicRegistration',
        'yes',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        30,
        'KMP.GitHub.Owner',
        'Ansteorra',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        31,
        'KMP.GitHub.Project',
        'KMP',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        1
    ),
    (
        32,
        'Member.AdditionalInfo.CallIntoCourt',
        'select:Never,With Notice,Without Notice,With notice given to another person,With notice given to me,With notice given to me and another person|user|public',
        '2024-09-30 01:51:08',
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        0
    ),
    (
        33,
        'Member.AdditionalInfo.PersonToGiveNoticeTo',
        'text|user|public',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        0
    ),
    (
        34,
        'Member.AdditionalInfo.CourtAvailability',
        'select:None,Morning,Evening,Any|user|public',
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL,
        0
    ),
    (
        35,
        'KMP.BranchInitRun',
        'recovered',
        '2024-09-29 16:55:23',
        '2024-09-29 15:47:44',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        36,
        'KMP.Login.Graphic',
        'populace_badge.png',
        '2024-09-29 15:47:44',
        '2024-09-29 15:47:44',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        37,
        'Activities.NextStatusCheck',
        '2025-08-31',
        '2025-08-30 20:59:24',
        '2024-09-29 15:47:44',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        38,
        'Officer.NextStatusCheck',
        '2025-08-31',
        '2025-08-30 20:59:06',
        '2024-09-29 15:47:44',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        50,
        'Awards.RecommendationStatesRequireCanViewHidden',
        '---\n- No Action\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        51,
        'Awards.RecommendationStatuses',
        '---\nIn Progress:\n- Submitted\n- In Consideration\n- Awaiting Feedback\n- Deferred till Later\n- King Approved\n- Queen Approved\nScheduling:\n- Need to Schedule\nTo Give:\n- Scheduled\n- Announced Not Given\nClosed:\n- Given\n- No Action\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        52,
        'Awards.RecommendationStateRules',
        '---\nNeed to Schedule:\n  Visible:\n  - planToGiveBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\nScheduled:\n  Kanban Popup: selectEvent\n  Required:\n  - planToGiveEventTarget\n  Visible:\n  - planToGiveBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\nGiven:\n  Kanban Popup: selectGivenDate\n  Required:\n  - planToGiveEventTarget\n  - givenDateTarget\n  Visible:\n  - planToGiveBlockTarget\n  - givenBlockTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - scaMemberTarget\n  Set:\n    close_reason: Given\nNo Action:\n  Required:\n  - closeReasonTarget\n  Visible:\n  - closeReasonBlockTarget\n  - closeReasonTarget\n  Disabled:\n  - domainTarget\n  - awardTarget\n  - specialtyTarget\n  - scaMemberTarget\n  - branchTarget\n  - courtAvailabilityTarget\n  - callIntoCourtTarget\n  - scaMemberTarget\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        53,
        'Awards.ViewConfig.Default',
        '---\ntable:\n  filter: []\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: true\n    Award: true\n    Reason: true\n    Events: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: true\n    Event: true\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: true\n    Pronouns: true\n    Pronunciation: true\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: true\n    Contact Email: true\n    Contact Phone: true\n    Domain: true\n    Award: true\n    Reason: true\n    Events: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: true\n    Event: true\n    State Date: true\n    Given Date: true\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        54,
        'Awards.ViewConfig.In Progress',
        '---\ntable:\n  filter:\n    Recommendations->Status: In Progress\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: true\n    Award: true\n    Reason: true\n    Events: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Event: false\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: true\n    Pronouns: true\n    Pronunciation: true\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: true\n    Contact Email: true\n    Contact Phone: true\n    Domain: true\n    Award: true\n    Reason: true\n    Events: true\n    Notes: true\n    Status: true\n    State: true\n    Close Reason: false\n    Event: false\n    State Date: true\n    Given Date: false\nboard:\n  use: true\n  states:\n  - Submitted\n  - In Consideration\n  - Awaiting Feedback\n  - Deferred till Later\n  - King Approved\n  - Queen Approved\n  - Need to Schedule\n  - No Action\n  hiddenByDefault:\n    lookback: 30\n    states:\n    - No Action\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        55,
        'Awards.ViewConfig.Scheduling',
        '---\ntable:\n  filter:\n    Recommendations->Status: Scheduling\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: false\n    Events: true\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Event: true\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: true\n    Branch: true\n    Call Into Court: true\n    Court Avail: true\n    Person to Notify: true\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Events: true\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: false\n    Event: true\n    State Date: false\n    Given Date: false\nboard:\n  use: true\n  states:\n  - Need to Schedule\n  - Scheduled\n  hiddenByDefault:\n    lookback: 30\n    states: []\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        56,
        'Awards.ViewConfig.To Give',
        'table:\r\n  filter:\r\n    Recommendations->Status: To Give\r\n  optionalPermission: []\r\n  use: true\r\n  enableExport: true\r\n  columns:\r\n    Submitted: true\r\n    For: true\r\n    For Herald: false\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: false\r\n    Branch: true\r\n    Call Into Court: true\r\n    Court Avail: true\r\n    Person to Notify: true\r\n    Submitted By: false\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: false\r\n    Notes: true\r\n    Status: false\r\n    State: true\r\n    Close Reason: false\r\n    Event: true\r\n    State Date: false\r\n    Given Date: false\r\n  export:\r\n    Submitted: true\r\n    For: true\r\n    For Herald: true\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: true\r\n    Branch: true\r\n    Call Into Court: true\r\n    Court Avail: true\r\n    Person to Notify: true\r\n    Submitted By: false\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: false\r\n    Notes: true\r\n    Status: false\r\n    State: true\r\n    Close Reason: false\r\n    Event: true\r\n    State Date: false\r\n    Given Date: false\r\nboard:\r\n  use: true\r\n  states:\r\n    - Scheduled\r\n    - Announced Not Given\r\n    - Given\r\n  hiddenByDefault:\r\n    lookback: 30\r\n    states:\r\n      - Given\r\n',
        '2024-12-06 01:13:51',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        57,
        'Awards.ViewConfig.Closed',
        '---\ntable:\n  filter:\n    Recommendations->Status: Closed\n  optionalPermission: []\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Events: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: true\n    Event: true\n    State Date: true\n    Given Date: true\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: true\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Events: false\n    Notes: true\n    Status: false\n    State: true\n    Close Reason: true\n    Event: true\n    State Date: true\n    Given Date: true\nboard:\n  use: false\n  states: []\n  hiddenByDefault:\n    lookback: 30\n    states: []\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        58,
        'Awards.ViewConfig.Event',
        'table:\r\n  filter:\r\n    Recommendations->event_id: \'-event_id-\'\r\n  optionalPermission: ViewEventRecommendations\r\n  use: true\r\n  enableExport: true\r\n  columns:\r\n    Submitted: false\r\n    For: true\r\n    For Herald: false\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: false\r\n    Branch: true\r\n    Call Into Court: true\r\n    Court Avail: true\r\n    Person to Notify: true\r\n    Submitted By: false\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: false\r\n    Notes: false\r\n    Status: false\r\n    State: true\r\n    Close Reason: false\r\n    Event: false\r\n    State Date: false\r\n    Given Date: false\r\n  export:\r\n    Submitted: false\r\n    For: true\r\n    For Herald: true\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: false\r\n    Branch: true\r\n    Call Into Court: true\r\n    Court Avail: true\r\n    Person to Notify: true\r\n    Submitted By: true\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: false\r\n    Notes: false\r\n    Status: false\r\n    State: true\r\n    Close Reason: false\r\n    Event: true\r\n    State Date: false\r\n    Given Date: false\r\nboard:\r\n  use: false\r\n  states: []\r\n  hiddenByDefault: []\r\n',
        '2024-12-06 01:15:30',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        59,
        'Awards.ViewConfig.SubmittedByMember',
        '---\ntable:\n  filter:\n    Recommendations->requester_id: -member_id-\n  optionalPermission: ViewSubmittedByMember\n  use: true\n  enableExport: true\n  columns:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Events: true\n    Notes: false\n    Status: false\n    State: false\n    Close Reason: false\n    Event: false\n    State Date: false\n    Given Date: false\n  export:\n    Submitted: true\n    For: true\n    For Herald: false\n    Title: false\n    Pronouns: false\n    Pronunciation: false\n    OP: false\n    Branch: false\n    Call Into Court: false\n    Court Avail: false\n    Person to Notify: false\n    Submitted By: false\n    Contact Email: false\n    Contact Phone: false\n    Domain: false\n    Award: true\n    Reason: true\n    Events: true\n    Notes: false\n    Status: false\n    State: false\n    Close Reason: false\n    Event: false\n    State Date: false\n    Given Date: false\nboard:\n  use: false\n  states: []\n  hiddenByDefault: []\n...\n',
        '2024-10-31 23:16:05',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        60,
        'Awards.ViewConfig.SubmittedForMember',
        'table:\r\n  filter:\r\n    Recommendations->member_id: \'-member_id-\'\r\n  optionalPermission: ViewSubmittedForMember\r\n  use: true\r\n  enableExport: false\r\n  columns:\r\n    Submitted: true\r\n    For: true\r\n    For Herald: false\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: false\r\n    Branch: false\r\n    Call Into Court: false\r\n    Court Avail: false\r\n    Person to Notify: false\r\n    Submitted By: true\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: true\r\n    Notes: false\r\n    Status: false\r\n    State: true\r\n    Close Reason: true\r\n    Event: true\r\n    State Date: false\r\n    Given Date: true\r\n  export:\r\n    Submitted: true\r\n    For: true\r\n    For Herald: false\r\n    Title: false\r\n    Pronouns: false\r\n    Pronunciation: false\r\n    OP: false\r\n    Branch: false\r\n    Call Into Court: false\r\n    Court Avail: false\r\n    Person to Notify: false\r\n    Submitted By: true\r\n    Contact Email: false\r\n    Contact Phone: false\r\n    Domain: false\r\n    Award: true\r\n    Reason: true\r\n    Events: true\r\n    Notes: false\r\n    Status: false\r\n    State: true\r\n    Close Reason: true\r\n    Event: true\r\n    State Date: false\r\n    Given Date: true\r\nboard:\r\n  use: false\r\n  states: []\r\n  hiddenByDefault: []\r\n',
        '2024-11-01 01:49:19',
        '2024-10-31 23:16:05',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        62,
        'KMP.HeaderLink. Support',
        'https://discord.gg/bUrnUprz4T|bi bi-discord btn-outline-warning',
        '2025-01-12 21:11:25',
        '2024-11-26 22:28:38',
        NULL,
        NULL,
        NULL,
        0
    ),
    (
        63,
        'KMP.HeaderLink.GitHub.no-label',
        'https://github.com/Ansteorra/KMP|bi bi-github',
        '2024-11-26 23:04:13',
        '2024-11-26 22:29:28',
        NULL,
        NULL,
        NULL,
        0
    ),
    (
        65,
        'KMP.FooterLink. What Is AMP',
        'https://ansteorra.org/amp/|btn btn-sm btn-warning bi bi-patch-question-fill',
        '2025-01-12 01:17:21',
        '2025-01-10 13:12:36',
        NULL,
        NULL,
        NULL,
        0
    ),
    (
        66,
        'KMP.configVersion',
        '25.01.11.a',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        67,
        'Warrant.LastCheck',
        '2025-08-30 21:31:30',
        '2025-08-30 21:31:30',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        0
    ),
    (
        68,
        'KMP.RequireActiveWarrantForSecurity',
        'no',
        '2025-01-12 01:03:28',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        69,
        'Warrant.RosterApprovalsRequired',
        '1',
        '2025-03-04 12:04:08',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        70,
        'Branches.Types',
        '---\n- Kingdom\n- Principality\n- Region\n- Local Group\n- N/A\n...\n',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        'yaml',
        1
    ),
    (
        71,
        'GitHubIssueSubmitter.configVersion',
        '25.01.11.a',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        72,
        'Plugin.GitHubIssueSubmitter.PopupMessage',
        'This Feedback form is anonymous and will be submitted to the KMP GitHub repository. Please do not include any private information or use this for support requests.  If you have any support needs please reach out over discord at https://discord.gg/AMKEAAX7',
        '2025-01-12 01:20:17',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        73,
        'Activities.configVersion',
        '25.01.11.c',
        '2025-03-23 12:52:52',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        74,
        'Officer.configVersion',
        '25.01.11.a',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        75,
        'Awards.configVersion',
        '25.01.11.a',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        76,
        'Queue.configVersion',
        '0.0.0',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        77,
        'Plugin.Queue.Active',
        'yes',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        79,
        'Activities.api_access.gw_gate',
        'giE36JsEazhckMdFCbYfE9pgdTNLE9hdp8T2ZMgwTgFUZXAUyi',
        '2025-02-23 20:29:34',
        '2025-02-23 20:02:44',
        NULL,
        NULL,
        NULL,
        0
    ),
    (
        80,
        'Email.UseQueue',
        'yes',
        '2025-07-09 11:57:22',
        '2025-07-09 11:49:38',
        NULL,
        NULL,
        NULL,
        1
    );
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_awards`
--

DROP TABLE IF EXISTS `awards_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 81 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_awards`
--

LOCK TABLES `awards_awards` WRITE;
/*!40000 ALTER TABLE `awards_awards` DISABLE KEYS */
;
INSERT INTO
    `awards_awards`
VALUES (
        1,
        'Award of the Sable Falcon',
        'Falcon',
        NULL,
        'Given to those who have striven greatly to further their skill level and capabilities in heavy weapons combat. Often given for a single notable deed.',
        'A cord braided sable and Or tied to a metal ring worn on the belt.',
        'None',
        '',
        1,
        1,
        2,
        '2024-06-25 22:21:14',
        '2024-06-25 22:21:14',
        1,
        1,
        NULL
    ),
    (
        2,
        'Award of the Sable Talon of Ansteorra (Chivalric)',
        'Talon',
        '[]',
        'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly  for different martial activities. ',
        'The badge worn as a medallion or pin',
        '(Fieldless) An eagle’s leg erased à la quise sable.',
        '',
        1,
        2,
        2,
        '2024-09-03 01:19:01',
        '2024-06-25 22:22:00',
        1,
        4,
        NULL
    ),
    (
        3,
        'Order of the Centurions of the Sable Star of Ansteorra',
        'Centurion',
        NULL,
        'Polling order. Confers a Grant of Arms. Given to those who have demonstrated exceptional leadership, skill and honor in chivalric combat.',
        'A ribbon Or edged gules charged with an Ansteorran star (a mullet of five greater\nand five lesser points) sable worn as a garter, and/or the badge of the order prominently\ndisplayed on a red cloak.',
        'On an eagle displayed wings inverted Or a mullet of five greater and five lesser points\nsable.',
        '',
        1,
        3,
        2,
        '2024-06-25 22:22:42',
        '2024-06-25 22:22:42',
        1,
        1,
        NULL
    ),
    (
        4,
        'Order of Chivalry',
        'Order of Chivalry',
        '[]',
        'Polling order. The highest award for chivalric combat.',
        'Knighthood : White belt and unadorned gold chain\r\nMaster at Arms: White baldric',
        'Knighthood : (Fieldless) A white belt\r\nMaster at Arms: (Fieldless) A white baldric',
        '',
        1,
        4,
        2,
        '2024-10-31 19:33:05',
        '2024-06-25 22:24:06',
        1,
        1096,
        NULL
    ),
    (
        5,
        'Award of the Lilium Aureum of Ansteorra',
        'Lilium Aurium',
        '[\"Artwork\",\"Bardic Arts\",\"Calligraphy\",\"Chainmail\",\"Costuming\",\"Culinary Arts\",\"Dance\",\"Drumming\",\"Embroidery\",\"Fiber Arts\",\"Heraldic Arts\",\"Herbalism\",\"Illumination\",\"Instrumental Music\",\"Jewelry Making\",\"Kumihimo\",\"Leatherworking\",\"Metalworking\",\"Music\",\"Needlework\",\"Painting\",\"Period Cooking\",\"Period Gaming\",\"Poetry\",\"Pottery\",\"Research\",\"Scribal Arts\",\"Spinning\",\"Textile Arts\",\"Voice Heraldry\",\"Weaving\",\"Woodcarving\",\"Woodworking\"]',
        'Given in recognition of achievements in Arts and Sciences by youth members of the Kingdom.',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable a fleur-de-lis Or',
        '',
        10,
        1,
        2,
        '2024-11-26 19:12:51',
        '2024-08-14 19:47:19',
        1,
        1096,
        NULL
    ),
    (
        6,
        'Award of the Sable Thistle of Ansteorra',
        'Sable Thistle',
        '[\"Applique\",\"Armor Making\",\"Artwork\",\"Banner Painting\",\"Bardic Arts\",\"Beadwork\",\"Blacksmithing\",\"Blackwork\",\"Bobbin Lace\",\"Brewing\",\"Calligraphy\",\"Carving\",\"Chainmail\",\"Costuming\",\"Crossbow Making\",\"Culinary Arts\",\"Dance\",\"Drumming\",\"Embroidery\",\"Equestrian Arts\",\"European Dance\",\"Fiber Arts\",\"Fletching\",\"Foolery\",\"Glasswork\",\"Haberdashery\",\"Heraldic Arts\",\"Herbalism\",\"Iconography\",\"Illumination\",\"Inkle Weaving\",\"Instrumental Music\",\"Jewelry Making\",\"Knife Making\",\"Kumihimo\",\"Lampwork Beads\",\"Leatherworking\",\"Metal Casting\",\"Metalworking\",\"Middle Eastern Dancing\",\"Music\",\"Needlework\",\"Painting\",\"Period Cooking\",\"Period Gaming\",\"Pewter Casting\",\"Poetry\",\"Pottery\",\"Research\",\"Scribal Arts\",\"Siege Engines\",\"Spinning\",\"Stained Glass\",\"Textile Arts\",\"Voice Heraldry\",\"Weaving\",\"Woodcarving\",\"Woodworking\"]',
        'Given to those who exhibit outstanding work in any field of the arts and sciences. The Award may be given to an individual more than once, but only once for a particular field.',
        'The badge worn as a medallion or pin',
        '(Fieldless) A blue thistle sable, slipped and leaved Or. ',
        '',
        3,
        2,
        2,
        '2024-09-05 12:06:42',
        '2024-08-14 19:51:16',
        1,
        1,
        NULL
    ),
    (
        7,
        'Order of the Iris of Merit of Ansteorra',
        'CIM',
        NULL,
        'A member of the kingdom who shows outstanding work in the arts and sciences, well above that which is expected of the citizens of Ansteorra, Knowledge of the courtly graces; and\nwho have shown consistent respect for the laws and customs of Ansteorra.',
        'A ribbon tinctured in the spectrum of a natural rainbow (red, orange, yellow, green, blue, violet) worn on the left shoulder',
        'Or, a mullet of five greater and five lesser points voided sable, surmounted by a natural rainbow proper.',
        '',
        3,
        3,
        2,
        '2024-08-14 20:10:53',
        '2024-08-14 19:54:19',
        1,
        1,
        NULL
    ),
    (
        8,
        'Order of the Laurel',
        'OL',
        NULL,
        'A candidate must have attained the standard of excellence in skill and/or knowledge equal to that of his or her prospective peers in an area of the Arts or Sciences. The candidate must have applied this skill and/or knowledge for the instruction of members and service to the kingdom to an extent above and beyond that normally expected of members of the Society. this is the highest award given in the SCA for excellence in the Arts and Sciences.',
        'Laurel wreath worn on the head and/or badge of the order worn as a medallion or pin. In Ansteorra, many members of the Order also wear a cloak that incorporates the badge.',
        '(Fieldless) a Laurel Wreath',
        '',
        3,
        4,
        2,
        '2024-08-14 19:57:57',
        '2024-08-14 19:57:57',
        1,
        1,
        NULL
    ),
    (
        9,
        'Award of the Sable Flur of Ansteorra',
        'Flur',
        '[]',
        'A Member who greatly impressed the crown with a singular act of extraordinary artistry, or general overall excellence in the arts and sciences.',
        'A cord braided Vert and Argent tied to a metal ring worn on the belt.',
        'none',
        '',
        3,
        1,
        2,
        '2024-10-31 19:33:44',
        '2024-08-14 20:00:25',
        1,
        1096,
        NULL
    ),
    (
        10,
        'Award of the Aquila Aurea of Ansteorra (Chivalric)',
        'Aquila Aurea',
        '[]',
        'For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or',
        '',
        10,
        1,
        2,
        '2024-11-26 19:13:16',
        '2024-08-14 20:03:19',
        1,
        1096,
        NULL
    ),
    (
        11,
        'Award of the Aquila Aurea of Ansteorra (Equestrian)',
        'Aquila Aurea',
        '[]',
        'For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or',
        '',
        10,
        1,
        2,
        '2024-11-26 19:13:27',
        '2024-08-14 20:05:29',
        1,
        1096,
        NULL
    ),
    (
        12,
        'Award of the Aquila Aurea of Ansteorra (Rapier and Steel)',
        'Aquila Aurea',
        '[]',
        'For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or',
        '',
        10,
        1,
        2,
        '2024-11-26 19:13:38',
        '2024-08-14 20:06:10',
        1,
        1096,
        NULL
    ),
    (
        13,
        'Award of the Aquila Aurea of Ansteorra (Missile Weapons)',
        'Aquila Aurea',
        '[]',
        'For children of the Kingdom (under the age of 18) who have made contributions of worth to the Kingdom in a martial endeavor.\r\n',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable an eagle’s head Or',
        '',
        10,
        1,
        2,
        '2024-11-26 19:13:49',
        '2024-08-14 20:06:29',
        1,
        1096,
        NULL
    ),
    (
        14,
        'Award of the Sable Talon of Ansteorra (Rapier and Steel)',
        'Talon',
        '[\"Rapier\",\"Cut & Thrust\"]',
        'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.',
        'The badge worn as a medallion or pin\n\n\n',
        '(Fieldless) An eagle’s leg erased à la quise sable.',
        '',
        4,
        2,
        2,
        '2024-09-27 15:36:51',
        '2024-08-14 20:08:46',
        1,
        1,
        NULL
    ),
    (
        15,
        'Award of the Sable Talon of Ansteorra (Equestrian)',
        'Talon',
        NULL,
        'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.',
        'The badge worn as a medallion or pin\n\n\n',
        '(Fieldless) An eagle’s leg erased à la quise sable.',
        '',
        6,
        2,
        2,
        '2024-08-14 20:10:06',
        '2024-08-14 20:09:01',
        1,
        1,
        NULL
    ),
    (
        16,
        'Award of the Sable Talon of Ansteorra (Missile)',
        'Talon',
        '[\"Combat Archery\",\"Archery\",\"Thrown Weapons\",\"Siege Weapons\"]',
        'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly for different martial activities.',
        'The badge worn as a medallion or pin\n\n\n',
        '(Fieldless) An eagle’s leg erased à la quise sable.',
        '',
        5,
        2,
        2,
        '2024-09-27 15:42:47',
        '2024-08-14 20:09:12',
        1,
        1,
        NULL
    ),
    (
        17,
        'Order of the Golden Lance of Ansteorra',
        'CGL',
        NULL,
        'persons who have demonstrated exceptional leadership, skill and honor on the equestrian field; Service to Ansteorra and its people; Knowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.',
        'A ribbon sable edged Or charged with a lance Or worn as a garter, and/or a pennon bearing the badge of the order (once registered) displayed on a tournament lance',
        'none',
        '',
        6,
        3,
        2,
        '2024-08-14 20:14:43',
        '2024-08-14 20:14:43',
        1,
        1,
        NULL
    ),
    (
        18,
        'Award of the Golden Bridle of Ansteorra',
        'Golden Bridle',
        NULL,
        'persons who have striven greatly to further their skill level and capabilities on the equestrian field; have given of themselves to the furthering of equestrian arts; and shall be exemplars of courtly graces, manners, and chivalry.',
        'A cord braided vert argent and Or tied to a metal ring worn on the belt.',
        'None',
        '',
        6,
        1,
        2,
        '2024-08-14 20:16:09',
        '2024-08-14 20:16:09',
        1,
        1,
        NULL
    ),
    (
        19,
        'Order of the Blade of Merit of Ansteorra',
        'CBM',
        NULL,
        'And award given to a member for exceptional skill and abilities on the duello field of combat.',
        'A ribbon of five equal width stripes of sable, Or, gules, Or, and sable.',
        'Sable, on a pale gules fimbriated, a rapier inverted Or.',
        '',
        4,
        3,
        2,
        '2024-08-15 12:29:39',
        '2024-08-15 12:29:39',
        1,
        1,
        NULL
    ),
    (
        20,
        'Order of Defense',
        'OD',
        NULL,
        'This is the highest award given in the SCA for excellence in the fighting fields of either rapier and/or cut-and-thrust combat. The candidate must be considered the equal of their prospective peers with the basic weapons of rapier and/or cut-and-thrust combat. The candidate must have applied this skill and/or knowledge for the instruction of members and service to the kingdom to an extent above and beyond that normally expected of members of the Society.',
        'The official insignia is a white livery collar. Most members of the Order wear a medallion bearing the badge of the order suspended from their collar. In Ansteorra, members of the Order also wear a white short cloak that incorporates the badge.',
        '(Tinctureless) Three rapiers in pall inverted tips crossed',
        '',
        4,
        4,
        2,
        '2024-08-15 12:31:29',
        '2024-08-15 12:31:29',
        1,
        1,
        NULL
    ),
    (
        22,
        'Award of the Queen\'s Rapier of Ansteorra',
        'Queen\'s Rapier',
        NULL,
        'Given to a member who has striven greatly to further their skill level and capabilities in rapier combat; involved in the skills of the arts and basic heraldry; and should display courtly graces, manners and chivalry. This award is given only by the Queen. ',
        'A cord braided Gules and Argent tied to a metal ring worn on the belt.',
        'None',
        '',
        4,
        1,
        2,
        '2024-08-15 12:35:25',
        '2024-08-15 12:35:25',
        1,
        1,
        NULL
    ),
    (
        23,
        'Order of the White Scarf of Ansteorra',
        'WSA',
        NULL,
        'Given to persons who have demonstrated exceptional skill and chivalry in combat with weapons of the duello; service to Ansteorra and its people; knowledge of the courtly graces; and obedience to the laws and ideals of Ansteorra and of the Society for Creative Anachronism. ',
        'A white scarf worn about the left shoulder or above the left elbow.',
        'Sable, on a pale argent between two rapiers, guards to center, proper, in chief a mullet of five greater and five lesser points sable.',
        '',
        4,
        1,
        2,
        '2024-08-15 12:37:43',
        '2024-08-15 12:37:43',
        1,
        1,
        NULL
    ),
    (
        24,
        'Award of the Golden Bridge of Ansteorra',
        'Golden Bridge',
        NULL,
        'Given to those members who have gone above and beyond in the areas of Diversity, Equity, Inclusion and Belonging. This award recognizes a member\'s efforts in making our game more inclusive and easier to access to those who have historically been marginalized or excluded. ',
        '',
        '',
        '',
        9,
        1,
        2,
        '2024-08-15 12:42:48',
        '2024-08-15 12:42:48',
        1,
        1,
        NULL
    ),
    (
        25,
        'Nobility of the Court',
        'Court Barony',
        NULL,
        'An award given at the pleasure of the Crown for outstanding work in service, contribution and help in the kingdom, well above that which is expected of the citizens of Ansteorra;\nKnowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.',
        'A flat-topped or engrailed coronet decorated with the arms, ensign, or other badges of the barony which they rule',
        'none',
        '',
        9,
        3,
        2,
        '2024-08-15 12:47:22',
        '2024-08-15 12:47:22',
        1,
        1,
        NULL
    ),
    (
        26,
        'Order of the Sable Garland of Ansteorra',
        'Sable Garland',
        NULL,
        'Patroned by a Rose of Ansteorra, the members of this Order shall have the strength of nature, the degree of martial skill, and the commitment to Ansteorra that they shall have obtained a chosen position in the Order of the Chivalry. They shall have served and promoted the honor and spirit of Ansteorra within its borders and beyond. They shall have been a vital part of the fabric of Ansteorra through service, counsel, knowledge, and shared knowledge.  They shall have used all talents and means to preserve and protect the Kingdom, the Crown, and the standards and ideals of their oaths.',
        'A sable cloak with a border, Or, upon which lies a garland of mullets of five greater and five lesser points slipped and leaved sable.	',
        'none',
        '',
        1,
        2,
        2,
        '2024-08-15 12:52:50',
        '2024-08-15 12:52:50',
        1,
        1,
        NULL
    ),
    (
        27,
        'Award of Arms',
        'AoA',
        NULL,
        'A simple grant of arms, given in recognition of membership and participation in our game. This is usually, the first award given to a member. ',
        'None officially. Many crowns present new armigers with a metal circlet/fillet of ½” height or less. Such circlets are not currently reserved or restricted insignia.	',
        'none',
        '',
        9,
        2,
        2,
        '2024-08-15 12:59:28',
        '2024-08-15 12:59:28',
        1,
        1,
        NULL
    ),
    (
        28,
        'Award of Amicitia of Ansteorra',
        'Amicitia',
        NULL,
        'Award given to residents of foreign lands for service to the Kingdom of Ansteorra. ',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater andfive lesser points sable a foi Or.',
        '',
        9,
        1,
        2,
        '2024-08-15 13:01:54',
        '2024-08-15 13:01:54',
        1,
        1,
        NULL
    ),
    (
        29,
        'Order of the Lion of Ansteorra',
        'Lion',
        NULL,
        'Known as the Defenders of the Dream, These individuals are awarded the lion for exemplifying the ideals of the Society and serving as an inspiration to others.',
        'Badge of the award worn as a medallion or pin',
        'Or, a mullet of five greater and five lesser points sable, overall a lion rampant argent.',
        '',
        9,
        1,
        2,
        '2024-08-15 13:03:09',
        '2024-08-15 13:03:09',
        1,
        1,
        NULL
    ),
    (
        30,
        'Award of the Rising Star of Ansteorra',
        'Rising Star',
        '[]',
        'Given to youth members for exceptional endeavors, whether in service, martial, or arts and sciences. ',
        'Badge of the award worn as a medallion or pin',
        'Or a mullet of five greater and five lesser points sable overall a point issuant from base gules.',
        '',
        10,
        1,
        2,
        '2024-11-26 19:14:13',
        '2024-08-15 13:05:31',
        1,
        1096,
        NULL
    ),
    (
        31,
        'Award of the Lyra Aurea of Ansteorra',
        'Lyra Aurea',
        '[]',
        'Given to youth members for endeavors in service to their community. ',
        'Badge of the award worn as a medallion or pin',
        '(Fieldless) On a mullet of five greater and five lesser points sable a lyre Or.',
        '',
        10,
        1,
        2,
        '2024-11-26 19:14:26',
        '2024-08-15 13:07:27',
        1,
        1096,
        NULL
    ),
    (
        32,
        'Award of the Sable Crane of Ansteorra',
        'Crane',
        '[\"Community Building - Hospitality\",\"Community Building - Member Engagement\",\"Community Building - Recruitment\",\"Community Building - Retention\",\"Event Administration - Event Steward\",\"Event Administration - Feast Steward\",\"Event Operations - Feast Service\",\"Event Operations - Field Marshalling\",\"Event Operations - Land management\",\"Event Operations - Lyst Coordination\",\"Event Operations - Sanitation\",\"Event Operations - Security\",\"Event Operations - Voice Heraldry\",\"Event Operations - Youth Activities\",\"Member Development - Coaching\\/Mentorship\",\"Member Development - Teaching\",\"Officer - Chatelaine\",\"Officer - Chronicler\",\"Officer - College of Heralds\",\"Officer - College of Scribes\",\"Officer - Exchequer\",\"Officer - Historian\",\"Officer - Marshalate\",\"Officer - Minister of Arts and Sciences\",\"Officer - Seneschalate\",\"Officer - Web Minister\",\"Online Activities - Community Engagement\",\"Online Activities - Graphic Design\",\"Online Activities - Social Media\",\"Service to a SCA Segment - A&S Activities\",\"Service to a SCA Segment - Charter Illumination\",\"Service to a SCA Segment - Fighter Support\",\"Service to a SCA Segment - Heraldic Consulting\",\"Service to a SCA Segment - Insignia Creation\"]',
        'Given by the Crown unto persons who have displayed outstanding service to Ansteorra.',
        'The badge worn as a medallion or pin',
        'Or, a crane in its vigilance sable, armed, orbed, membered, crested and throated Or, fimbriated sable, bearing in its dexter claw a mullet of five greater and five lesser points sable.',
        '',
        2,
        2,
        2,
        '2024-09-05 12:09:26',
        '2024-08-15 13:18:12',
        1,
        1,
        NULL
    ),
    (
        33,
        'Award of the Compass Rose of Ansteorra',
        'Compass Rose',
        NULL,
        'Given by the crown unto a person for outstanding service to children of the Kingdom.',
        'The badge worn as a medallion or pin',
        'Per chevron Or and gules, a compass rose sable.',
        '',
        2,
        2,
        2,
        '2024-08-15 13:19:55',
        '2024-08-15 13:19:55',
        1,
        1,
        NULL
    ),
    (
        34,
        'Award of the Sable Comet of Ansteorra',
        'Sable Comet',
        NULL,
        'Service to an official branch of the SCA other than a Barony.  These are usually shires, cantons, strongholds, chases etc.',
        'The badge worn as a medallion or pin',
        '(Fieldless) A comet headed of a mullet of five greater and five lesser points fesswise reversed sable.',
        '',
        2,
        2,
        2,
        '2024-08-15 13:21:44',
        '2024-08-15 13:21:44',
        1,
        1,
        NULL
    ),
    (
        35,
        'Order of the Star of Merit of Ansteorra',
        'CSM',
        NULL,
        'the Crown shall select persons who have served their kingdom well and faithfully, well above that which is normally expected of the citizens of Ansteorra; Knowledge of the courtly graces; and who have shown consistent respect for the laws and customs of Ansteorra.',
        'A ribbon Or edged sable charged with an Ansteorran star (a mullet of five greater and five lesser points sable), worn above the left elbow or below the right knee.',
        'Argent, on a fess Or fimbriated a mullet of five greater and five lesser points sable.',
        '',
        2,
        3,
        2,
        '2024-08-15 13:23:27',
        '2024-08-15 13:23:27',
        1,
        1,
        NULL
    ),
    (
        36,
        'Order of the Pelican',
        'OP',
        NULL,
        'The highest award given for Service to the Kingdom. ',
        'Cap of maintenance (chapeau) and/or badge of the order worn as a medallion or pin. The cap may be gules trimmed ermine or gules trimmed argent goutty de sang (red blood drops). In Ansteorra, many members of the Order also wear a cloak that incorporates the badge.',
        '(Fieldless) a pelican in her piety Also: (Tinctureless) A pelican vulning itself. This means that the pelican can be shown without the nest and chicks.',
        '',
        2,
        4,
        2,
        '2024-08-15 13:26:30',
        '2024-08-15 13:26:30',
        1,
        1,
        NULL
    ),
    (
        37,
        'Award of the Golden Star of Ansteorra',
        'Golden Star',
        NULL,
        'Given to a member who has been found to have served faithfully in attendance to the Crown for the duration for their reign.',
        'A unique token chosen by the granting Crown, bearing their initials or other personal mark.',
        'none',
        '',
        2,
        1,
        2,
        '2024-08-15 13:27:36',
        '2024-08-15 13:27:36',
        1,
        1,
        NULL
    ),
    (
        38,
        'Award of the Sable Sparrow of Ansteorra',
        'Sable Sparrow',
        NULL,
        'Given by the crown unto a person who has greatly impressed them with a singular act of extraordinary service',
        'A cord braided sable Gules and Or tied to a metal ring worn on the belt.',
        'none',
        '',
        2,
        1,
        2,
        '2024-08-15 13:29:03',
        '2024-08-15 13:29:03',
        1,
        1,
        NULL
    ),
    (
        39,
        'Award of the King\'s Gauntlet of Ansteorra',
        'King\'s Gauntlet',
        '[]',
        'Given by the Sovereign unto persons found to have served them well and faithfully, above and beyond what is normally expected of a citizen of Ansteorra. ',
        'A leather or cloth gauntlet bearing an Ansteorran star (a mullet of five greater and five lesser points sable) and the granting king’s sigil/cypher.',
        'none',
        '',
        2,
        2,
        2,
        '2024-09-03 01:19:49',
        '2024-08-15 13:31:01',
        1,
        4,
        NULL
    ),
    (
        40,
        'Award of the Queen\'s Glove of Ansteorra',
        'Queen\'s Glove',
        NULL,
        'An Award given by the Consort unto persons found to have served them well and faithfully, above and beyond what is normally expected of a citizen of Ansteorra. ',
        'A cloth or leather glove bearing the Queen’s Rose (a rose sable charged with another Or, thereon a mullet of five greater and five lesser points sable) and the granting Queen’s sigil/cypher.',
        'None',
        '',
        2,
        2,
        2,
        '2024-08-15 13:32:39',
        '2024-08-15 13:32:39',
        1,
        1,
        NULL
    ),
    (
        41,
        'Order of the Arc d\'Or of Ansteorra',
        'CAO',
        NULL,
        'persons who have demonstrated exceptional skill with missile weaponry, including archery, thrown weapons, and siege engines, on the target or martial fields; by their service to Ansteorra and its people; in the promotion of the art of missile weaponry ; by their knowledge of the courtly graces; and by obedience to the laws and ideals of Ansteorra and for the Society for Creative Anachronism .',
        'A ribbon Or edged sable charged with an Ansteorran star (a mullet of five greater and five lesser points sable), worn above the left elbow or below the right knee.',
        'Sable, on a fess argent a mullet of five greater and five lesser points sable, overall two bows addorsed Or.',
        '',
        5,
        3,
        2,
        '2024-08-15 13:37:21',
        '2024-08-15 13:35:01',
        1,
        1,
        NULL
    ),
    (
        42,
        'Award of the King\'s Archer of Ansteorra',
        'King\'s Archer',
        NULL,
        'persons who have striven greatly to further their skill level and capabilities in combat or target archery, the arts, basic heraldry, and\nshall be exemplars of courtly graces, manners, and chivalry.',
        'A cord braided Sable and Vert tied to a metal ring worn on the belt.',
        'None',
        '',
        5,
        1,
        2,
        '2024-08-15 13:36:44',
        '2024-08-15 13:36:44',
        1,
        1,
        NULL
    ),
    (
        43,
        'Sodality of the Sentinels of the Stargate',
        'SSG',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        'Sable, two spears in saltire between two towers in fess argent.',
        '',
        7,
        2,
        39,
        '2024-09-30 01:39:51',
        '2024-09-27 19:09:22',
        1,
        1096,
        NULL
    ),
    (
        44,
        'Order of the Oak of the Steppes',
        'OOS',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion	',
        'Or, on a pale sable endorsed vert an oak leaf inverted Or.',
        '',
        7,
        2,
        27,
        '2024-10-31 19:32:22',
        '2024-09-27 19:10:06',
        1,
        1096,
        NULL
    ),
    (
        45,
        'Order of the Firebrand of Bjornsborg',
        'OFB',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        '(Fieldless) A wooden torch bendwise sinister flammant proper.',
        '',
        7,
        2,
        41,
        '2024-09-30 01:40:09',
        '2024-09-27 19:10:56',
        1,
        1096,
        NULL
    ),
    (
        46,
        'Order of the Silent Trumpet of Bordermarch',
        'OSTB',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'A baldric gules singly striped and tasseled azure, worn over the left shoulder.',
        'None*',
        '',
        7,
        2,
        34,
        '2024-09-30 01:40:17',
        '2024-09-27 19:12:08',
        1,
        1096,
        NULL
    ),
    (
        47,
        'Order of the Dreigiau Bryn',
        'ODB',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of Order sewn on a ribbon sable triply striped argent.',
        'Or, a wyvern erect gules maintaining a halberd palewise sable, overall a triple-peaked mountain, issuant from base, vert.',
        '',
        7,
        2,
        33,
        '2024-09-30 01:40:24',
        '2024-09-27 19:12:49',
        1,
        1096,
        NULL
    ),
    (
        48,
        'Order of the Heart of the Sable Storm',
        'OHSS',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        '(Fieldless) A pile wavy Or.',
        '',
        7,
        2,
        18,
        '2024-09-30 01:40:32',
        '2024-09-27 19:13:35',
        1,
        1096,
        NULL
    ),
    (
        49,
        'Order des Cotes Anciennes',
        'OCA',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        'Argent, a mountain of three peaks issuant from base gules.',
        '',
        7,
        2,
        17,
        '2024-09-30 01:40:40',
        '2024-09-27 19:16:21',
        1,
        1096,
        NULL
    ),
    (
        50,
        'Order of the Raven’s Wings	',
        '',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        '(Fieldless) A vol sable.',
        '',
        7,
        2,
        40,
        '2024-09-30 01:40:49',
        '2024-09-27 19:17:00',
        1,
        1096,
        NULL
    ),
    (
        51,
        'Order of the Azure Keystone of Elfsea',
        'AKE',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion	',
        '(Fieldless) A keystone embattled on its upper edge azure charged with two bars wavy Or.',
        '',
        7,
        2,
        29,
        '2024-09-30 01:40:57',
        '2024-09-27 19:17:27',
        1,
        1096,
        NULL
    ),
    (
        52,
        'Order of the Lanternarius of Wiesenfeuer',
        'OLW',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion	',
        '(Fieldless) On an annulet of flame sable an annulet Or.',
        '',
        7,
        2,
        20,
        '2024-09-30 01:41:07',
        '2024-09-27 19:18:41',
        1,
        1096,
        NULL
    ),
    (
        53,
        'Order of the Serpent’s Toils of Loch Soilleir',
        'STLS',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        '(Fieldless) A sea-serpent in annulo head to chief and vorant of its own tail vert.',
        '',
        7,
        2,
        36,
        '2024-09-30 01:41:16',
        '2024-09-27 19:19:20',
        1,
        1096,
        NULL
    ),
    (
        54,
        'Order of the Western Cross of Bonwicke',
        'OWCB',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'None',
        'None',
        '',
        7,
        2,
        30,
        '2024-09-30 01:41:28',
        '2024-09-27 19:20:25',
        1,
        1096,
        NULL
    ),
    (
        55,
        'Order of the Lions Paw of Kenmare',
        'OLPK',
        '\"\"',
        'Baronial Service Award that grants an Award of Arms and as such requires Crown approval.',
        'Badge of the order worn as a medallion',
        '(Fieldless) In chevron a tower sable sustained by two lion’s gambes erased Or.',
        '',
        7,
        2,
        25,
        '2024-09-30 01:41:34',
        '2024-09-27 19:21:07',
        1,
        1096,
        NULL
    ),
    (
        56,
        'Order of the Mark',
        'OM',
        '\"\"',
        '5th Peerage for Missile related activities in the SCA including Archery, Thrown Weapons and other activities.',
        '',
        '',
        '',
        5,
        4,
        2,
        '2025-06-21 23:46:17',
        '2024-12-18 13:47:54',
        1,
        2870,
        NULL
    ),
    (
        57,
        'Trees are Cool',
        'TAC',
        '\"\"',
        'Award for people who love Bears',
        '',
        '',
        '',
        7,
        1,
        27,
        '2025-07-02 23:08:18',
        '2025-04-21 22:44:51',
        1,
        1096,
        NULL
    ),
    (
        58,
        'Order of the Bastion of Vindheim',
        'Bastion',
        '\"\"',
        'Persons who exemplify the spirit of the Dream in Vindheim and inspire the populace to greater deeds and service. The candidate should be a person that Vindheim relies upon for the betterment of the Principality, using their skills and talents, and by whose absence the Principality would be greatly deprived of a valuable member.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 00:21:00',
        '2025-06-21 23:51:50',
        1,
        2870,
        NULL
    ),
    (
        59,
        'Award of the Brazier of Vindheim',
        'Brazier',
        '[\"Martial Endeavors as Art\",\"Martial Endeavors as Service\"]',
        'Persons who have used their martial skills to enhance artistry or assist the service community. The candidate should use their skills and talents to contribute to the betterment of Vindheim or its subordinate communities. The Award may be given to an individual more than once, but only for the reasons listed.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-23 23:56:33',
        '2025-06-22 00:06:22',
        1,
        2870,
        NULL
    ),
    (
        60,
        'Award of the Key of Keys of Vindheim',
        'Key of Keys',
        '\"\"',
        'Persons who have given especial service in attendance to the persons of the Vindheim Coronet(s). The candidate should exemplify the pinnacle of service and respect for the Vindheim Coronet(s), the laws of the Principality of Vindheim, and the Kingdom of Ansteorra.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 00:47:25',
        '2025-06-22 00:47:25',
        1,
        2870,
        NULL
    ),
    (
        61,
        'Award of the Key of Vindheim',
        'Key',
        '\"\"',
        'Persons who have faithfully served in attendance to the Vindheim Coronet(s). The candidate should exemplify faithful service and respect for the Vindheim Coronet(s), the laws of the Principality of Vindheim, and the Kingdom of Ansteorra.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 00:52:30',
        '2025-06-22 00:52:30',
        1,
        2870,
        NULL
    ),
    (
        62,
        'Order of the Fountain of Vindheim',
        'Fountain',
        '[\"Art as Service\",\"Art in support of Marshallate\"]',
        'Persons who have offered public service to others and the Vindheim community through the arts and science or a martial aspect, including but not limited to bardcraft, music, vocal heraldry, and dance. The candidate should use their skills and talents to contribute to the ambiance, atmosphere, and splendor of Vindheim in our public spaces.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-23 23:58:36',
        '2025-06-22 00:54:39',
        1,
        2870,
        NULL
    ),
    (
        63,
        'Order of the Golden Comb of Vindheim',
        'Golden Comb',
        '\"\"',
        'Younger members of the Society that have not reached their majority shall be recognized for service above that which is normally expected of them.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:00:29',
        '2025-06-22 01:00:29',
        1,
        2870,
        NULL
    ),
    (
        64,
        'Order of the Goutte de Vin of Vindheim',
        'Goutte',
        '\"\"',
        'Persons who have served the Principality through provision of original award scrolls.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:03:02',
        '2025-06-22 01:02:41',
        1,
        2870,
        NULL
    ),
    (
        65,
        'Award of the Pillar of Vindheim',
        'Pillar',
        '[\"Service to the Marshallate\",\"Service to the Arts\"]',
        'Persons who have offered notable service to artistic or martial communities of Vindheim through the gift of their time or organizational skills. Service done to enable the art community or the martial community or organizing a task so flawlessly it becomes an art. The candidate should use their skills and talents to contribute to the betterment of Vindheim or its subordinate communities.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-23 23:59:43',
        '2025-06-22 01:05:14',
        1,
        2870,
        NULL
    ),
    (
        66,
        'Order of the Sanguine Bowl of Vindheim',
        'Sanguine Bowl',
        '\"\"',
        'Persons who embody the spirit of Vindheim even though they are not residents thereof. The inductee should reside outside the borders of Vindheim and exhibit love, dedication, and service which truly embodies the Vindheim ideal.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:23:10',
        '2025-06-22 01:23:10',
        1,
        2870,
        NULL
    ),
    (
        67,
        'Award of the River of Vindheim',
        'River',
        '\"\"',
        'Persons who have demonstrated excellence in period style, display, or fidelity.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:24:17',
        '2025-06-22 01:24:17',
        1,
        2870,
        NULL
    ),
    (
        68,
        'Award of the Thunderbolt of Vindheim',
        'Thunderbolt',
        '\"\"',
        'Persons who have distinguished themselves to the Coronet in an extraordinary fashion. The candidate should show an example of phenomenal prowess, extraordinary artistry, or remarkable service.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:27:48',
        '2025-06-22 01:27:48',
        1,
        2870,
        NULL
    ),
    (
        69,
        'Award of the Wellspring of Vindheim',
        'Wellspring',
        '\"\"',
        'Persons who have served the Principality through static arts.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-22 01:30:32',
        '2025-06-22 01:30:32',
        1,
        2870,
        NULL
    ),
    (
        70,
        'Award of the Sanguine Company of Vindheim',
        'Sanguine Company',
        '[\"Armoured Combat\",\"Rapier Combat\",\"Steel Combat\",\"Combat Archery\",\"Target Archery\",\"Siege Combat\",\"Thrown Weapons\",\"Youth Rapier Combat\",\"Youth Armoured Combat\",\"Equestrian Deeds of Arms\"]',
        'Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in marshal activities. The Award may be given to an individual more than once, but only once for a particular marshallate field.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-24 00:03:16',
        '2025-06-22 01:47:18',
        1,
        2870,
        NULL
    ),
    (
        71,
        'Award of the Sinople Company of Vindheim',
        'Sinople Company',
        '[\"Fiber Arts\",\"Culinary Arts\",\"Performance Arts\",\"Household Arts\",\"Metal Arts\",\"Research\",\"Tangible Heraldry\",\"Husbandry\"]',
        'Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in art or science activities. The Award may be given to an individual more than once, but only once for a particular art or science field.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-24 00:04:59',
        '2025-06-22 01:48:50',
        1,
        2870,
        NULL
    ),
    (
        72,
        'Award of the Tenne Company of Vindheim',
        'Tenne Company',
        '[\"Group Management\",\"Event Administration\",\"Infrastructure\",\"General Assistance\",\"Event Operations\"]',
        'Recognition of those who take on the burden of representing Vindheim well in foreign lands. These persons shall increase the recognition or improve upon the reputation of Vindheim while engaging in service activities. The Award may be given to an individual more than once, but only once for a particular type of service.',
        '',
        '',
        '',
        11,
        1,
        11,
        '2025-06-24 00:06:16',
        '2025-06-22 01:50:23',
        1,
        2870,
        NULL
    ),
    (
        73,
        'Award of the Dragon\'s Egg',
        'BGDE',
        '\"\"',
        '\"We honor the contributions of youth to our Barony of Bryn Gwlad. Hear now, young gentles, you are hereby named a recipient of the Dragon’s Egg, in recognition of courtesy, service, and enthusiasm, that you may continue to inspire others.\"',
        '',
        '',
        '',
        7,
        1,
        33,
        '2025-07-08 20:44:24',
        '2025-07-08 20:43:58',
        1,
        1073,
        NULL
    ),
    (
        74,
        'Award of the Cross Fleury',
        'BGCF',
        '\"\"',
        '\"The warriors of Bryn Gwlad are admired for their skills and courage. These gentles have defended our lands and our people with great honor, and are hereby awarded the Cross Fleury of Bryn Gwlad.\"',
        '',
        '',
        '',
        7,
        1,
        33,
        '2025-07-08 20:45:04',
        '2025-07-08 20:45:04',
        1,
        1073,
        NULL
    ),
    (
        75,
        'Award of the Golden Martlet',
        'BGGM',
        '\"\"',
        '\"The Golden Martlet is a non-armigerous baronial award to honor service to the Barony of Bryn Gwlad.\"',
        '',
        '',
        '',
        7,
        1,
        33,
        '2025-07-08 20:45:34',
        '2025-07-08 20:45:34',
        1,
        1073,
        NULL
    ),
    (
        76,
        'Award of the Muse',
        'BGM',
        '\"\"',
        '\"The beauty of Bryn Gwlad is a gift for all who gather here. Recipients of this award are highly admired artisans whose skills have enriched our barony, and are hereby awarded the Muse of Bryn Gwlad.\"',
        '',
        '',
        '',
        7,
        1,
        33,
        '2025-07-08 20:46:25',
        '2025-07-08 20:46:25',
        1,
        1073,
        NULL
    ),
    (
        77,
        'Award of the Silver Chalice',
        'BGSC',
        '\"\"',
        '\"The quality of one’s character is highly prized in our Society. The persona of these gentles has greatly enriched the Barony of Bryn Gwlad, and with these words are hereby awarded the Silver Chalice of Bryn Gwlad.\"',
        '',
        '',
        '',
        7,
        1,
        33,
        '2025-07-08 20:46:49',
        '2025-07-08 20:46:49',
        1,
        1073,
        NULL
    ),
    (
        78,
        'Keeper of the Crucible',
        'HGKC',
        '\"\"',
        '\"The Keeper of the Crucible is an award given by the Lord and Lady of Hellsgate to recognize works of Arts and Sciences within our Stronghold of Hellsgate.\"',
        '',
        '',
        '',
        7,
        1,
        38,
        '2025-07-08 20:47:26',
        '2025-07-08 20:47:26',
        1,
        1073,
        NULL
    ),
    (
        79,
        'Keeper of the Flame',
        'HGF',
        '\"\"',
        '\"The Keeper of the Flame is an award given by the Lord and Lady of Hellsgate to recognize acts of service to our Stronghold of Hellsgate.\"',
        '',
        '',
        '',
        7,
        1,
        38,
        '2025-07-08 20:48:27',
        '2025-07-08 20:47:48',
        1,
        1073,
        NULL
    ),
    (
        80,
        'Keeper of the Gate',
        'HGG',
        '\"\"',
        '\"The Keeper of the Gate is an award given by the Lord and Lady of Hellsgate to recognize skill in combat shown within our Stronghold of Hellsgate.\"',
        '',
        '',
        '',
        7,
        1,
        38,
        '2025-07-08 20:48:11',
        '2025-07-08 20:48:11',
        1,
        1073,
        NULL
    );
/*!40000 ALTER TABLE `awards_awards` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_domains`
--

DROP TABLE IF EXISTS `awards_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 12 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_domains`
--

LOCK TABLES `awards_domains` WRITE;
/*!40000 ALTER TABLE `awards_domains` DISABLE KEYS */
;
INSERT INTO
    `awards_domains`
VALUES (
        1,
        'Chivalric',
        '2024-06-25 15:10:11',
        '2024-06-25 13:59:24',
        1,
        1,
        NULL
    ),
    (
        2,
        'Service',
        '2024-06-25 13:59:33',
        '2024-06-25 13:59:33',
        1,
        1,
        NULL
    ),
    (
        3,
        'Arts & Sciences',
        '2024-06-25 13:59:49',
        '2024-06-25 13:59:49',
        1,
        1,
        NULL
    ),
    (
        4,
        'Rapier & Steel Weapons',
        '2024-06-25 13:59:59',
        '2024-06-25 13:59:59',
        1,
        1,
        NULL
    ),
    (
        5,
        'Missile Weapons',
        '2024-06-25 14:00:13',
        '2024-06-25 14:00:13',
        1,
        1,
        NULL
    ),
    (
        6,
        'Equestrian',
        '2024-06-25 14:00:20',
        '2024-06-25 14:00:20',
        1,
        1,
        NULL
    ),
    (
        7,
        'Baronial',
        '2024-06-25 14:00:36',
        '2024-06-25 14:00:36',
        1,
        1,
        NULL
    ),
    (
        8,
        'Kingdom',
        '2024-06-25 14:00:44',
        '2024-06-25 14:00:44',
        1,
        1,
        NULL
    ),
    (
        9,
        'General',
        '2024-08-14 19:38:50',
        '2024-08-14 19:32:54',
        1,
        1,
        NULL
    ),
    (
        10,
        'Youth',
        '2024-11-26 19:12:30',
        '2024-11-26 19:12:30',
        1,
        1096,
        NULL
    ),
    (
        11,
        'Principality',
        '2025-06-22 00:20:30',
        '2025-06-22 00:20:30',
        1,
        2870,
        NULL
    );
/*!40000 ALTER TABLE `awards_domains` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_events`
--

DROP TABLE IF EXISTS `awards_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 52 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_events`
--

LOCK TABLES `awards_events` WRITE;
/*!40000 ALTER TABLE `awards_events` DISABLE KEYS */
;
INSERT INTO
    `awards_events`
VALUES (
        1,
        'Namron Protectorate and Kingdom Coronation',
        'https://ansteorra.org/namron/protectorate-xlviii/',
        18,
        '2024-10-04 00:00:00',
        '2024-10-06 00:00:00',
        '2024-11-11 00:21:57',
        '2024-09-29 17:29:31',
        1,
        65,
        NULL,
        1
    ),
    (
        2,
        'Bjornsborg\'s Fall Event - Dance Macabre',
        'https://ansteorra.org/bjornsborg/danse-macabre/',
        41,
        '2024-10-18 00:00:00',
        '2024-10-20 00:00:00',
        '2024-11-11 00:22:35',
        '2024-09-29 17:31:28',
        1,
        65,
        NULL,
        1
    ),
    (
        3,
        'Diamond Wars',
        'https://gleannabhann.net/event/diamond-wars/',
        10,
        '2024-10-18 00:00:00',
        '2024-10-20 00:00:00',
        '2024-11-14 20:44:37',
        '2024-09-29 17:32:09',
        1,
        669,
        NULL,
        1
    ),
    (
        4,
        'Eldern Hills - Samhain',
        'https://ansteorra.org/eldern-hills/events/',
        17,
        '2024-10-25 00:00:00',
        '2024-10-27 00:00:00',
        '2024-11-19 02:53:50',
        '2024-09-29 17:33:16',
        1,
        65,
        NULL,
        1
    ),
    (
        5,
        'Seawind\'s Defender',
        'https://ansteorra.org/seawinds/',
        37,
        '2024-10-25 00:00:00',
        '2024-10-27 00:00:00',
        '2024-11-19 02:54:46',
        '2024-09-29 17:33:45',
        1,
        65,
        NULL,
        1
    ),
    (
        6,
        'Vindheim Missile Academy II',
        ' https://sites.google.com/u/5/d/1QToqHTcjWDrReUs_OJp_wvDdQln3hduf/preview',
        2,
        '2024-11-01 00:00:00',
        '2024-11-03 00:00:00',
        '2024-11-14 20:44:11',
        '2024-09-29 17:34:23',
        1,
        669,
        NULL,
        1
    ),
    (
        7,
        'A Toast to Absent Friends: A Dia de Los Muertos Event',
        'https://ansteorra.org/shadowlands',
        35,
        '2024-11-01 00:00:00',
        '2024-11-03 00:00:00',
        '2024-11-19 03:01:49',
        '2024-09-29 17:35:04',
        1,
        65,
        NULL,
        1
    ),
    (
        8,
        'Queen\'s Champion',
        'https://ansteorra.org/events',
        20,
        '2024-11-09 00:00:00',
        '2024-11-09 00:00:00',
        '2025-01-12 23:45:53',
        '2024-09-29 17:35:58',
        1,
        65,
        NULL,
        1
    ),
    (
        9,
        'Bryn Gwlad Fall Baronial',
        'https://ansteorra.org/bryn-gwlad/bryn-gwlad-fall-baronial-2024/',
        33,
        '2024-11-15 00:00:00',
        '2024-11-17 00:00:00',
        '2025-01-13 00:38:53',
        '2024-09-29 17:36:22',
        1,
        65,
        NULL,
        1
    ),
    (
        10,
        'Bordermarch War of the Rams',
        'https://ansteorra.org/bordermarch',
        34,
        '2024-11-21 00:00:00',
        '2024-11-24 00:00:00',
        '2025-01-17 23:25:37',
        '2024-09-29 17:36:54',
        1,
        65,
        NULL,
        1
    ),
    (
        11,
        'Winter Crown Tournament',
        'https://ansteorra.org/events',
        29,
        '2024-12-07 00:00:00',
        '2024-12-07 00:00:00',
        '2024-12-17 16:29:20',
        '2024-09-29 17:37:27',
        1,
        669,
        NULL,
        1
    ),
    (
        12,
        'Vindheim Winter Coronet',
        'https://ansteorra.org/events',
        23,
        '2024-12-14 00:00:00',
        '2024-12-14 00:00:00',
        '2024-12-17 16:32:05',
        '2024-09-29 17:37:49',
        1,
        669,
        NULL,
        1
    ),
    (
        13,
        'Stargate Yule',
        'https://ansteorra.org/events',
        39,
        '2024-12-14 00:00:00',
        '2024-12-14 00:00:00',
        '2025-01-13 00:41:45',
        '2024-09-29 17:38:10',
        1,
        65,
        NULL,
        1
    ),
    (
        14,
        'Wiesenfeuer Yule',
        'https://ansteorra.org/wiesenfeuer',
        20,
        '2024-12-21 00:00:00',
        '2024-12-21 00:00:00',
        '2025-01-13 00:48:27',
        '2024-09-29 17:38:37',
        1,
        65,
        NULL,
        1
    ),
    (
        15,
        'Steppes 12th Night',
        'https://ansteorra.org/steppes',
        27,
        '2025-01-04 00:00:00',
        '2025-01-04 00:00:00',
        '2025-01-13 00:48:50',
        '2024-09-29 17:38:59',
        1,
        65,
        NULL,
        1
    ),
    (
        16,
        'Elfsea\'s Yule',
        'https://ansteorra.org/elfsea',
        29,
        '2025-01-11 00:00:00',
        '2025-01-11 00:00:00',
        '2025-01-13 00:49:07',
        '2024-09-29 17:39:28',
        1,
        65,
        NULL,
        1
    ),
    (
        17,
        'Deleted: Winter Round Table',
        'https://ansteorra.org/round-table',
        2,
        '2025-01-18 00:00:00',
        '2025-01-18 00:00:00',
        '2024-09-29 17:51:16',
        '2024-09-29 17:39:51',
        1,
        1096,
        '2024-09-29 17:51:16',
        0
    ),
    (
        18,
        'Marata Midwinter Melees',
        'https://ansteorra.org/events',
        19,
        '2025-01-25 00:00:00',
        '2025-01-25 00:00:00',
        '2024-11-26 21:54:10',
        '2024-09-29 17:40:21',
        1,
        669,
        NULL,
        0
    ),
    (
        19,
        'Winterkingdom',
        ' https://ansteorra.org/northkeep/activities/events/winterkingdom/winterkingdom-collegium-when-in-rome/',
        25,
        '2025-02-01 00:00:00',
        '2025-02-01 00:00:00',
        '2024-09-29 17:45:02',
        '2024-09-29 17:40:52',
        1,
        1096,
        NULL,
        0
    ),
    (
        20,
        'Bryn Gwlad Candlemas',
        'https://ansteorra.org/bryn-gwlad',
        33,
        '2025-02-01 00:00:00',
        '2025-02-01 00:00:00',
        '2024-09-29 17:41:41',
        '2024-09-29 17:41:21',
        1,
        1096,
        NULL,
        0
    ),
    (
        21,
        'Laurel\'s Prize Tournament',
        'https://ansteorra.org/events',
        2,
        '2025-02-08 00:00:00',
        '2025-02-08 00:00:00',
        '2024-09-29 17:42:17',
        '2024-09-29 17:42:17',
        1,
        1073,
        NULL,
        0
    ),
    (
        22,
        'Battle of the Pines',
        'https://ansteorra.org/graywood',
        31,
        '2025-02-15 00:00:00',
        '2025-02-15 00:00:00',
        '2024-09-29 17:43:00',
        '2024-09-29 17:43:00',
        1,
        1073,
        NULL,
        0
    ),
    (
        23,
        'Gulf Wars XXXIII',
        'https://gulfwars.org',
        10,
        '2025-03-08 00:00:00',
        '2025-03-16 00:00:00',
        '2024-09-29 17:43:32',
        '2024-09-29 17:43:32',
        1,
        1073,
        NULL,
        0
    ),
    (
        24,
        'Commander\'s Crucible Anniversary',
        'https://ansteorra.org/hellsgate',
        38,
        '2025-03-28 00:00:00',
        '2025-03-30 00:00:00',
        '2024-09-29 17:44:16',
        '2024-09-29 17:44:16',
        1,
        1073,
        NULL,
        0
    ),
    (
        25,
        'Elfsea\'s Defender',
        'https://ansteorra.org/elfsea',
        29,
        '2025-04-04 00:00:00',
        '2025-04-06 00:00:00',
        '2024-09-29 17:44:49',
        '2024-09-29 17:44:49',
        1,
        1073,
        NULL,
        0
    ),
    (
        26,
        'Coronation',
        'https://ansteorra.org/events',
        2,
        '2025-04-12 00:00:00',
        '2025-04-12 00:00:00',
        '2024-09-29 17:45:19',
        '2024-09-29 17:45:19',
        1,
        1073,
        NULL,
        0
    ),
    (
        27,
        'Stargate\'s Baronial',
        'https://ansteorra.org/stargate',
        39,
        '2025-04-18 00:00:00',
        '2025-04-20 00:00:00',
        '2024-09-29 17:45:48',
        '2024-09-29 17:45:48',
        1,
        1073,
        NULL,
        0
    ),
    (
        28,
        'Wiesenfeuer\'s Baronial',
        'https://ansteorra.org/wiesenfeuer',
        20,
        '2025-04-18 00:00:00',
        '2025-04-20 00:00:00',
        '2025-08-29 00:23:37',
        '2024-09-29 17:46:40',
        1,
        2870,
        NULL,
        0
    ),
    (
        29,
        'Glaslyn\'s Defender on the Flame',
        'https://ansteorra.org/glaslyn',
        28,
        '2025-04-25 00:00:00',
        '2025-04-27 00:00:00',
        '2024-09-29 17:47:24',
        '2024-09-29 17:47:24',
        1,
        1073,
        NULL,
        0
    ),
    (
        30,
        'Loch Soilleir\'s Baronial',
        'https://ansteorra.org/loch-soilleir',
        36,
        '2025-05-02 00:00:00',
        '2025-05-04 00:00:00',
        '2024-09-29 17:47:54',
        '2024-09-29 17:47:54',
        1,
        1073,
        NULL,
        0
    ),
    (
        31,
        'Queen\'s Champion',
        'https://ansteorra.org/events',
        2,
        '2025-05-10 00:00:00',
        '2025-05-10 00:00:00',
        '2024-09-29 17:48:15',
        '2024-09-29 17:48:15',
        1,
        1073,
        NULL,
        0
    ),
    (
        32,
        'Northkeep\'s Castellan',
        'https://ansteorra.org/northkeep',
        25,
        '2025-05-16 00:00:00',
        '2025-05-18 00:00:00',
        '2024-09-29 17:48:39',
        '2024-09-29 17:48:39',
        1,
        1073,
        NULL,
        0
    ),
    (
        33,
        'Steppes Warlord',
        'https://ansteorra.org/steppes',
        27,
        '2025-05-23 00:00:00',
        '2025-05-25 00:00:00',
        '2024-11-19 16:41:29',
        '2024-09-29 17:49:02',
        1,
        1073,
        NULL,
        0
    ),
    (
        34,
        'Summer Crown Tournament',
        'https://ansteorra.org/events',
        2,
        '2025-06-07 00:00:00',
        '2025-06-07 00:00:00',
        '2024-09-29 17:49:22',
        '2024-09-29 17:49:22',
        1,
        1073,
        NULL,
        0
    ),
    (
        35,
        'Vindheim Summer Coronet',
        'https://ansteorra.or/events',
        2,
        '2025-06-21 00:00:00',
        '2025-06-21 00:00:00',
        '2024-09-29 17:49:48',
        '2024-09-29 17:49:48',
        1,
        1073,
        NULL,
        0
    ),
    (
        36,
        'Kingdom Collegium',
        'https://ansteorra.org/events',
        2,
        '2025-07-12 00:00:00',
        '2025-07-12 00:00:00',
        '2024-09-29 17:50:07',
        '2024-09-29 17:50:07',
        1,
        1073,
        NULL,
        0
    ),
    (
        37,
        'Deleted: Summer Round Table',
        'https://ansteorra.org/round-table',
        2,
        '2025-07-19 00:00:00',
        '2025-07-19 00:00:00',
        '2024-09-29 17:51:38',
        '2024-09-29 17:50:39',
        1,
        1096,
        '2024-09-29 17:51:38',
        0
    ),
    (
        38,
        'Pennsic',
        'https://pennsic.org',
        10,
        '2025-07-25 00:00:00',
        '2025-08-10 00:00:00',
        '2024-09-29 17:51:11',
        '2024-09-29 17:51:11',
        1,
        1073,
        NULL,
        0
    ),
    (
        39,
        'Steppes Artisan',
        'https://ansteorra.org/steppes',
        27,
        '2025-08-16 00:00:00',
        '2025-08-16 00:00:00',
        '2024-09-29 17:51:35',
        '2024-09-29 17:51:35',
        1,
        1073,
        NULL,
        0
    ),
    (
        40,
        'Serpent\'s Symposium VII',
        'https://ansteorra.org/loch-soilleir',
        36,
        '2025-08-23 00:00:00',
        '2025-08-23 00:00:00',
        '2024-09-29 17:52:01',
        '2024-09-29 17:52:01',
        1,
        1073,
        NULL,
        0
    ),
    (
        41,
        'Bonwicke\'s War of Legends',
        'https://ansteorra.org/bonwicke',
        30,
        '2025-08-29 00:00:00',
        '2025-08-31 00:00:00',
        '2024-09-29 17:52:32',
        '2024-09-29 17:52:32',
        1,
        1073,
        NULL,
        0
    ),
    (
        42,
        'Elfsea Baronial College',
        'https://ansteorra.org/elfsea',
        29,
        '2025-09-07 00:00:00',
        '2025-09-07 00:00:00',
        '2024-09-29 17:52:55',
        '2024-09-29 17:52:55',
        1,
        1073,
        NULL,
        0
    ),
    (
        43,
        'Kingdom Arts and Sciences',
        'https://ansteorra.org/events',
        2,
        '2025-09-13 00:00:00',
        '2025-09-13 00:00:00',
        '2024-09-29 17:53:13',
        '2024-09-29 17:53:13',
        1,
        1073,
        NULL,
        0
    ),
    (
        44,
        'Mooneschadowe\'s Triumphe of the Eclipse',
        'https://ansteorra.org/mooneschadowe',
        22,
        '2025-09-19 00:00:00',
        '2025-09-21 00:00:00',
        '2024-09-29 17:53:40',
        '2024-09-29 17:53:40',
        1,
        1073,
        NULL,
        0
    ),
    (
        45,
        'Raven\'s Fort Defender of the Fort',
        'https://ansteorra.org/ravensfort',
        40,
        '2025-09-19 00:00:00',
        '2025-09-21 00:00:00',
        '2024-09-29 17:54:14',
        '2024-09-29 17:54:14',
        1,
        1073,
        NULL,
        0
    ),
    (
        46,
        'Rosenfeld Champions and Three Things',
        'https://ansteorra.org/rosenfeld',
        32,
        '2025-09-26 00:00:00',
        '2025-09-28 00:00:00',
        '2024-09-29 17:54:54',
        '2024-09-29 17:54:44',
        1,
        1073,
        NULL,
        0
    ),
    (
        47,
        'Ffynnon Gath\'s War of Ages',
        'https://ansteorra.org/ffynnon-gath',
        42,
        '2025-09-26 00:00:00',
        '2025-09-28 00:00:00',
        '2024-09-29 17:55:33',
        '2024-09-29 17:55:33',
        1,
        1073,
        NULL,
        0
    ),
    (
        48,
        'Bjornsborg Spring Event',
        'Bjornsborg\'s Spring Event',
        41,
        '2025-04-25 00:00:00',
        '2025-04-27 00:00:00',
        '2024-11-17 13:58:18',
        '2024-11-17 13:58:18',
        1,
        1096,
        NULL,
        0
    ),
    (
        49,
        'A Day in the...',
        'Ravens Fort Spring event',
        40,
        '2025-02-21 00:00:00',
        '2025-03-23 00:00:00',
        '2024-12-15 14:11:07',
        '2024-12-15 14:11:07',
        1,
        1096,
        NULL,
        0
    ),
    (
        50,
        'Enchanted Conflict (Raven\'s Fort)',
        'Raven\'s Fort\'s Spring Event',
        40,
        '2025-02-21 00:00:00',
        '2025-02-23 00:00:00',
        '2024-12-22 00:27:07',
        '2024-12-22 00:27:07',
        1,
        1073,
        NULL,
        0
    ),
    (
        51,
        'Bordermarch Baronials',
        'Spring Baronial Event',
        34,
        '2025-03-29 00:00:00',
        '2025-03-30 00:00:00',
        '2025-01-22 03:48:04',
        '2025-01-22 03:48:04',
        1,
        65,
        NULL,
        0
    );
/*!40000 ALTER TABLE `awards_events` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_levels`
--

DROP TABLE IF EXISTS `awards_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 6 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_levels`
--

LOCK TABLES `awards_levels` WRITE;
/*!40000 ALTER TABLE `awards_levels` DISABLE KEYS */
;
INSERT INTO
    `awards_levels`
VALUES (
        1,
        'Non-Armigerous',
        0,
        '2024-06-25 13:53:55',
        '2024-06-25 13:53:55',
        1,
        1,
        NULL
    ),
    (
        2,
        'Armigerous',
        1,
        '2024-06-25 13:54:15',
        '2024-06-25 13:54:15',
        1,
        1,
        NULL
    ),
    (
        3,
        'Grant',
        2,
        '2024-06-25 13:55:21',
        '2024-06-25 13:55:21',
        1,
        1,
        NULL
    ),
    (
        4,
        'Peerage',
        4,
        '2024-06-25 13:56:44',
        '2024-06-25 13:55:32',
        1,
        1,
        NULL
    ),
    (
        5,
        'Nobility',
        3,
        '2024-06-25 13:56:55',
        '2024-06-25 13:56:55',
        1,
        1,
        NULL
    );
/*!40000 ALTER TABLE `awards_levels` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_phinxlog`
--

DROP TABLE IF EXISTS `awards_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `awards_phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_phinxlog`
--

LOCK TABLES `awards_phinxlog` WRITE;
/*!40000 ALTER TABLE `awards_phinxlog` DISABLE KEYS */
;
INSERT INTO
    `awards_phinxlog`
VALUES (
        20240614001010,
        'InitAwards',
        '2024-09-29 15:47:03',
        '2024-09-29 15:47:04',
        0
    ),
    (
        20240912174050,
        'AddPersonToNotify',
        '2024-09-29 15:47:04',
        '2024-09-29 15:47:04',
        0
    ),
    (
        20241017085448,
        'AddEventClosedFlag',
        '2024-10-31 23:13:10',
        '2024-10-31 23:13:10',
        0
    ),
    (
        20241018230237,
        'AddNoActionReason',
        '2024-10-31 23:13:10',
        '2024-10-31 23:13:10',
        0
    ),
    (
        20241018231315,
        'RecommendationStates',
        '2024-10-31 23:13:10',
        '2024-10-31 23:13:10',
        0
    );
/*!40000 ALTER TABLE `awards_phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations`
--

DROP TABLE IF EXISTS `awards_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
    CONSTRAINT `awards_recommendations_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `awards_recommendations_ibfk_2` FOREIGN KEY (`requester_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `awards_recommendations_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `awards_recommendations_ibfk_4` FOREIGN KEY (`award_id`) REFERENCES `awards_awards` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 586 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_recommendations`
--

LOCK TABLES `awards_recommendations` WRITE;
/*!40000 ALTER TABLE `awards_recommendations` DISABLE KEYS */
;
INSERT INTO
    `awards_recommendations`
VALUES (
        579,
        11,
        2878,
        2872,
        22,
        72,
        'Event Administration',
        'Iris Demoer',
        'Bryce Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'some cool reason',
        'Not Set',
        'Not Set',
        'Closed',
        '2025-08-29 00:20:33',
        28,
        '2025-08-23 00:00:00',
        '2025-08-29 00:20:33',
        '2025-07-02 21:18:49',
        1,
        2870,
        NULL,
        '',
        NULL,
        '',
        'Given'
    ),
    (
        580,
        12,
        2878,
        2873,
        33,
        44,
        NULL,
        'Iris Demoer',
        'Caroline Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'asdf',
        'Not Set',
        'Not Set',
        'In Progress',
        '2025-07-02 21:19:06',
        NULL,
        NULL,
        '2025-07-02 21:19:06',
        '2025-07-02 21:19:06',
        1,
        2878,
        NULL,
        '',
        NULL,
        NULL,
        'Submitted'
    ),
    (
        581,
        13,
        2878,
        2872,
        22,
        48,
        NULL,
        'Iris Demoer',
        'Bryce Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'asdf',
        'Not Set',
        'Not Set',
        'In Progress',
        '2025-07-02 21:19:19',
        NULL,
        NULL,
        '2025-07-02 21:19:19',
        '2025-07-02 21:19:19',
        1,
        2878,
        NULL,
        '',
        NULL,
        NULL,
        'Submitted'
    ),
    (
        582,
        14,
        2878,
        2879,
        36,
        6,
        'Banner Painting',
        'Iris Demoer',
        'Jael Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'reason reason reason\r\n',
        'Not Set',
        'Not Set',
        'In Progress',
        '2025-07-02 21:20:47',
        NULL,
        NULL,
        '2025-07-02 21:20:47',
        '2025-07-02 21:20:47',
        1,
        2878,
        NULL,
        '',
        NULL,
        NULL,
        'Submitted'
    ),
    (
        583,
        15,
        2878,
        2872,
        22,
        44,
        NULL,
        'Iris Demoer',
        'Bryce Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'testing again!  bryce is great',
        'Not Set',
        'Not Set',
        'In Progress',
        '2025-07-02 23:05:15',
        NULL,
        NULL,
        '2025-07-02 23:05:15',
        '2025-07-02 23:05:15',
        1,
        2878,
        NULL,
        '',
        NULL,
        NULL,
        'Submitted'
    ),
    (
        584,
        16,
        2878,
        2872,
        22,
        57,
        NULL,
        'Iris Demoer',
        'Bryce Demoer',
        '9999999999',
        'iris@ampdemo.com',
        'asdf',
        'Not Set',
        'Not Set',
        'Closed',
        '2025-07-16 23:45:57',
        39,
        NULL,
        '2025-07-16 23:45:57',
        '2025-07-02 23:08:54',
        1,
        2880,
        NULL,
        '',
        NULL,
        'Given',
        'Given'
    );
/*!40000 ALTER TABLE `awards_recommendations` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations_events`
--

DROP TABLE IF EXISTS `awards_recommendations_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `awards_recommendations_events` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `recommendation_id` int(11) NOT NULL,
    `event_id` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `recommendation_id` (`recommendation_id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `awards_recommendations_events_ibfk_1` FOREIGN KEY (`recommendation_id`) REFERENCES `awards_recommendations` (`id`),
    CONSTRAINT `awards_recommendations_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `awards_events` (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 2091 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_recommendations_events`
--

LOCK TABLES `awards_recommendations_events` WRITE;
/*!40000 ALTER TABLE `awards_recommendations_events` DISABLE KEYS */
;
/*!40000 ALTER TABLE `awards_recommendations_events` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `awards_recommendations_states_logs`
--

DROP TABLE IF EXISTS `awards_recommendations_states_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 1158 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `awards_recommendations_states_logs`
--

LOCK TABLES `awards_recommendations_states_logs` WRITE;
/*!40000 ALTER TABLE `awards_recommendations_states_logs` DISABLE KEYS */
;
INSERT INTO
    `awards_recommendations_states_logs`
VALUES (
        1121,
        579,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 21:18:49',
        1
    ),
    (
        1122,
        580,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 21:19:06',
        1
    ),
    (
        1123,
        581,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 21:19:19',
        1
    ),
    (
        1124,
        582,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 21:20:47',
        1
    ),
    (
        1125,
        583,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 23:05:15',
        1
    ),
    (
        1126,
        584,
        'New',
        'Submitted',
        'In Progress',
        'In Progress',
        '2025-07-02 23:08:54',
        1
    ),
    (
        1128,
        584,
        'Submitted',
        'In Consideration',
        'In Progress',
        'In Progress',
        '2025-07-08 21:59:32',
        1
    ),
    (
        1129,
        584,
        'In Consideration',
        'Awaiting Feedback',
        'In Progress',
        'In Progress',
        '2025-07-08 21:59:39',
        1
    ),
    (
        1130,
        584,
        'Submitted',
        'Need to Schedule',
        'In Progress',
        'Scheduling',
        '2025-07-16 22:54:39',
        1
    ),
    (
        1131,
        584,
        'Need to Schedule',
        'Awaiting Feedback',
        'Scheduling',
        'In Progress',
        '2025-07-16 23:12:10',
        1
    ),
    (
        1140,
        584,
        'Awaiting Feedback',
        'King Approved',
        'In Progress',
        'In Progress',
        '2025-07-16 23:44:05',
        1
    ),
    (
        1142,
        584,
        'King Approved',
        'Queen Approved',
        'In Progress',
        'In Progress',
        '2025-07-16 23:44:16',
        1
    ),
    (
        1145,
        584,
        'Queen Approved',
        'Need to Schedule',
        'In Progress',
        'Scheduling',
        '2025-07-16 23:44:47',
        1
    ),
    (
        1146,
        584,
        'Need to Schedule',
        'Scheduled',
        'Scheduling',
        'To Give',
        '2025-07-16 23:45:27',
        1
    ),
    (
        1147,
        584,
        'Scheduled',
        'Announced Not Given',
        'To Give',
        'To Give',
        '2025-07-16 23:45:51',
        1
    ),
    (
        1148,
        584,
        'Announced Not Given',
        'Given',
        'To Give',
        'Closed',
        '2025-07-16 23:45:57',
        1
    ),
    (
        1154,
        579,
        'Submitted',
        'Scheduled',
        'In Progress',
        'To Give',
        '2025-08-29 00:20:33',
        1
    );
/*!40000 ALTER TABLE `awards_recommendations_states_logs` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `branches` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
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
    KEY `parent_id` (`parent_id`),
    KEY `lft` (`lft`),
    KEY `rght` (`rght`),
    KEY `deleted` (`deleted`),
    CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `branches` (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 43 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */
;
INSERT INTO
    `branches`
VALUES (
        2,
        'Ansteorra',
        'Texas & Oklahoma',
        NULL,
        '[{\"url\":\"https:\\/\\/anstorra.org\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/AnsteorraSCA\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/78670722996\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/www.youtube.com\\/channel\\/UC09oAqJbPGviT5ff9AAtBjQ\",\"type\":\"youtube\"},{\"url\":\"https:\\/\\/www.instagram.com\\/scakingdomofansteorra\\/\",\"type\":\"instagram\"},{\"url\":\"https:\\/\\/discord.gg\\/kPM52QgqK6\",\"type\":\"discord\"}]',
        0,
        1,
        66,
        '2025-04-21 22:30:47',
        '2024-08-14 13:35:17',
        1,
        1,
        NULL,
        'Kingdom',
        'ansteorra.org'
    ),
    (
        10,
        'Out of Kingdom',
        'Out of Kingdom',
        NULL,
        '[]',
        1,
        67,
        68,
        '2025-01-12 17:54:14',
        '2024-08-14 13:35:18',
        1,
        1096,
        NULL,
        'N/A',
        ''
    ),
    (
        11,
        'Vindheim',
        'Oklahoma & the Panhandle of Texas',
        2,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/vindheim\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/656962524780331\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/ansteorra.org\\/vindheim\\/calendar\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/discord.gg\\/PJbnsEMRmv\",\"type\":\"discord\"}]',
        0,
        2,
        27,
        '2025-03-04 15:43:47',
        '2024-08-13 22:56:44',
        1,
        2866,
        NULL,
        'Principality',
        'vindheim.ansteorra.org'
    ),
    (
        12,
        'Central Region',
        'Cities around the I-20 corridor',
        2,
        '[]',
        0,
        28,
        41,
        '2025-06-04 23:50:48',
        '2024-08-13 22:58:37',
        1,
        2866,
        NULL,
        'Region',
        'central.ansteorra.org'
    ),
    (
        13,
        'Southern Region',
        'Southern half of Texas',
        2,
        '[]',
        0,
        42,
        63,
        '2025-06-04 23:48:17',
        '2024-08-13 22:58:59',
        1,
        2866,
        NULL,
        'Region',
        'southern.ansteorra.org'
    ),
    (
        14,
        'Shire of Adlersruhe',
        'Amarillo, TX',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/adlersruhe\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40adlersruhe.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/412770745519031\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/gJ2hzbh4aa\",\"type\":\"discord\"}]',
        1,
        3,
        4,
        '2025-06-04 23:33:41',
        '2024-08-13 23:01:05',
        1,
        2866,
        NULL,
        'Local Group',
        'Adlersruhe.ansteorra.org'
    ),
    (
        15,
        'Canton of Chemin Noir',
        'Bartlesville, OK',
        25,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/chemin-noir\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=northkeep.ansteorra.org_hr396dqho0t6h9ltn5ji524ldc%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/scacheminnoir\",\"type\":\"facebook\"}]',
        1,
        20,
        21,
        '2025-06-04 23:39:13',
        '2024-08-13 23:02:13',
        1,
        2866,
        NULL,
        'Local Group',
        'chemin-noir.ansteorra.org'
    ),
    (
        16,
        'Canton of Myrgenfeld',
        'Guthrie, OK',
        20,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/myrgenfeld\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40myrgenfeld.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"}]',
        1,
        12,
        13,
        '2025-06-04 23:38:13',
        '2024-08-13 23:02:58',
        1,
        2866,
        NULL,
        'Local Group',
        'Myrgenfeld.ansteorra.org'
    ),
    (
        17,
        'Barony of Eldern Hills',
        'Lawton, OK',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/eldern-hills\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/u\\/0\\/embed?src=webminister@eldern-hills.ansteorra.org&ctz=America\\/Chicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/278741078929172\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/ga6fEEkFxE\",\"type\":\"discord\"}]',
        1,
        5,
        6,
        '2025-06-04 23:41:12',
        '2024-08-13 23:04:00',
        1,
        2866,
        NULL,
        'Local Group',
        'eldern-hills.ansteorra.org'
    ),
    (
        18,
        'Barony of Namron',
        'Norman, OK',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/namron\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=l0harfs5tqu14a6ta8rt2ggvec%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/93652129248\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/eEyTuvjXre\",\"type\":\"discord\"}]',
        1,
        7,
        10,
        '2025-06-04 23:40:49',
        '2024-08-13 23:05:07',
        1,
        2866,
        NULL,
        'Local Group',
        'namron.ansteorra.org'
    ),
    (
        19,
        'Riding of Marata',
        'Enid, OK',
        22,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/marata\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/SCAEnid\",\"type\":\"facebook\"}]',
        1,
        16,
        17,
        '2025-06-04 23:30:21',
        '2024-08-14 18:59:29',
        1,
        2866,
        NULL,
        'Local Group',
        'marata.ansteorra.org'
    ),
    (
        20,
        'Barony of Wiesenfeuer',
        'Oklahoma City, OK',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/wiesenfeuer\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=l0harfs5tqu14a6ta8rt2ggvec%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/272431286188558\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/eEyTuvjXre\",\"type\":\"discord\"}]',
        1,
        11,
        14,
        '2025-03-04 15:53:08',
        '2024-08-14 19:01:30',
        1,
        2866,
        NULL,
        'Local Group',
        'wiesenfeuer.ansteorra.org'
    ),
    (
        21,
        'Canton of Skorragarðr',
        'Shawnee, OK',
        18,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/skorragardr\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40skorragardr.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/307430935797\",\"type\":\"facebook\"}]',
        1,
        8,
        9,
        '2025-06-04 23:40:29',
        '2024-08-14 19:03:21',
        1,
        2866,
        NULL,
        'Local Group',
        'skorragardr.ansteorra.org'
    ),
    (
        22,
        'Province of Mooneschadowe',
        'Stillwater, OK',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/mooneschadowe\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=rkbhhf7m1vbhchtqjbq9iggpmk%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/2200765792\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/7jgdeE4hnr\",\"type\":\"discord\"}]',
        1,
        15,
        18,
        '2025-06-04 23:30:02',
        '2024-08-14 19:04:21',
        1,
        2866,
        NULL,
        'Local Group',
        'mooneschadowe.ansteorra.org'
    ),
    (
        23,
        'Canton of Wyldewode',
        'Tahlequah, OK',
        25,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/wyldewode\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=northkeep.ansteorra.org_m0q38jbcjdrmgsgp7u0lufri34%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Wyldewode\",\"type\":\"facebook\"}]',
        1,
        22,
        23,
        '2025-06-04 23:38:44',
        '2024-08-14 19:06:35',
        1,
        2866,
        NULL,
        'Local Group',
        'Wyldewode.ansteorra.org'
    ),
    (
        24,
        'Kingdom Land',
        'All of Ansteorra not supported by a group.',
        2,
        '[]',
        1,
        64,
        65,
        '2025-01-12 17:50:51',
        '2024-08-14 19:06:36',
        1,
        1096,
        NULL,
        'N/A',
        ''
    ),
    (
        25,
        'Barony of Northkeep',
        'Tulsa, OK',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/northkeep\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40northkeep.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/26587534453\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/sxGErTR2eq\",\"type\":\"discord\"}]',
        1,
        19,
        24,
        '2025-06-04 23:39:42',
        '2024-08-14 19:07:27',
        1,
        2866,
        NULL,
        'Local Group',
        'northkeep.ansteorra.org'
    ),
    (
        26,
        'Shire of Brad Leah',
        'Wichita Falls, TX',
        11,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/brad-leah\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40brad-leah.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/390730670964114\",\"type\":\"facebook\"}]',
        1,
        25,
        26,
        '2025-06-04 23:33:21',
        '2024-08-14 19:08:11',
        1,
        2866,
        NULL,
        'Local Group',
        'brad-leah.ansteorra.org'
    ),
    (
        27,
        'Barony of the Steppes',
        'Dallas, TX',
        12,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/steppes\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=steppes.ansteorra.org_p99m0mt1654cmg959rea97j754%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"http:\\/\\/facebook.com\\/groups\\/baronyofthesteppes\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/6ncnjMs7M3\",\"type\":\"discord\"}]',
        1,
        29,
        32,
        '2025-06-04 23:49:31',
        '2024-08-14 19:10:59',
        1,
        2866,
        NULL,
        'Local Group',
        'steppes.ansteorra.org'
    ),
    (
        28,
        'Canton of Glaslyn',
        'Denton, TX',
        27,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/glaslyn\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=glaslyn.ansteorra.org_fsnt3kfgr4urpe3mkdbd35t6q8@group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/203611906388\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/dGM6qdkgsr\",\"type\":\"discord\"}]',
        1,
        30,
        31,
        '2025-06-04 23:49:15',
        '2024-08-14 19:12:42',
        1,
        2866,
        NULL,
        'Local Group',
        'glaslyn.ansteorra.org'
    ),
    (
        29,
        'Barony of Elfsea',
        'Ft. Worth, TX',
        12,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/elfsea\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=elfsea.ansteorra.org_ghoosc4f2mg7ovhfdi4mpe5qng%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/119112885484\\/\",\"type\":\"facebook\"}]',
        1,
        33,
        34,
        '2025-06-04 23:49:46',
        '2024-08-14 19:13:36',
        1,
        2866,
        NULL,
        'Local Group',
        'elfsea.ansteorra.org'
    ),
    (
        30,
        'Barony of Bonwicke',
        'Lubbock, TX',
        12,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/bonwicke\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=imm9goc03ounmuv9p0qd685dco%40group.calendar.google.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Bonwicke\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/qqhCYN25Sk\",\"type\":\"discord\"}]',
        1,
        35,
        36,
        '2025-06-04 23:50:04',
        '2024-08-14 19:14:39',
        1,
        2866,
        NULL,
        'Local Group',
        'bonwicke.ansteorra.org'
    ),
    (
        31,
        'Shire of Graywood',
        'Lufkin, TX',
        12,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/graywood\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/1791805071079120\\/\",\"type\":\"facebook\"}]',
        1,
        37,
        38,
        '2025-06-04 23:48:56',
        '2024-08-14 19:15:18',
        1,
        2866,
        NULL,
        'Local Group',
        'graywood.ansteorra.org'
    ),
    (
        32,
        'Shire of Rosenfeld',
        'Tyler, TX',
        12,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/rosenfeld\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/rosenfeld\\/current-meetings-practices\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/shireofrosenfeld\",\"type\":\"facebook\"}]',
        1,
        39,
        40,
        '2025-06-04 23:48:38',
        '2024-08-14 19:15:59',
        1,
        2866,
        NULL,
        'Local Group',
        'rosenfeld.ansteorra.org'
    ),
    (
        33,
        'Barony of Bryn Gwlad',
        'Austin, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/bryn-gwlad\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40bryn-gwlad.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/BrynGwlad\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/kCT4fRu\",\"type\":\"discord\"}]',
        1,
        43,
        44,
        '2025-06-04 23:47:29',
        '2024-08-14 19:17:03',
        1,
        2866,
        NULL,
        'Local Group',
        'bryn-gwlad.ansteorra.org'
    ),
    (
        34,
        'Barony of Bordermarch',
        'Beaumont, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/bordermarch\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/bordermarch\\/cal\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/905851402869576\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/GGTBHNr76r\",\"type\":\"discord\"}]',
        1,
        45,
        46,
        '2025-01-12 17:51:17',
        '2024-08-14 19:17:49',
        1,
        1096,
        NULL,
        'Local Group',
        ''
    ),
    (
        35,
        'Shire of the Shadowlands',
        'Bryan/College Station, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/shadowlands\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=shadowlands.seneschal%40gmail.com&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/140819382632871\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/zXNU7An\",\"type\":\"discord\"}]',
        1,
        47,
        48,
        '2025-06-04 23:42:31',
        '2024-08-14 19:18:43',
        1,
        2866,
        NULL,
        'Local Group',
        'shadowlands.ansteorra.org'
    ),
    (
        36,
        'Barony of Loch Soilleir',
        'Clear Lake, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/loch-soilleir\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40loch-soilleir.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/134428422244\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/yD9GHvf5B8\",\"type\":\"discord\"}]',
        1,
        49,
        50,
        '2025-06-04 23:46:54',
        '2024-08-14 19:19:33',
        1,
        2866,
        NULL,
        'Local Group',
        'loch-soilleir.ansteorra.org'
    ),
    (
        37,
        'Shire of Seawinds',
        'Corpus Christi, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/seawinds\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=seneschal%40seawinds.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/117945061876595\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/VP37mr8r7Z\",\"type\":\"discord\"}]',
        1,
        51,
        52,
        '2025-06-04 23:43:38',
        '2024-08-14 19:20:34',
        1,
        2866,
        NULL,
        'Local Group',
        'seawinds.ansteorra.org'
    ),
    (
        38,
        'Stronghold of Hellsgate',
        'Ft. Cavazos, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/hellsgate\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/hellsgate\\/practices-meetings\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/516103795088723\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/sMGa7TZZwn\",\"type\":\"discord\"}]',
        1,
        53,
        54,
        '2025-06-04 23:42:12',
        '2024-08-14 19:21:37',
        1,
        2866,
        NULL,
        'Local Group',
        'hellsgate.ansteorra.org'
    ),
    (
        39,
        'Barony of Stargate',
        'Houston, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/stargate\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister@stargate.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.instagram.com\\/baronyofstargate?igsh=MXdoc3g0b2V0eGk4OQ==\",\"type\":\"instagram\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/56697227816\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/dH3y9rfZ4Q\",\"type\":\"discord\"}]',
        1,
        55,
        56,
        '2025-06-04 23:44:18',
        '2024-08-14 19:22:47',
        1,
        2866,
        NULL,
        'Local Group',
        'stargate.ansteorra.org'
    ),
    (
        40,
        'Barony of Raven’s Fort',
        'Huntsville, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/ravensfort\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/calendar.google.com\\/calendar\\/embed?src=webminister%40ravens-fort.ansteorra.org&ctz=America%2FChicago\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/294764366381\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/znfQcwN9\",\"type\":\"discord\"}]',
        1,
        57,
        58,
        '2025-06-04 23:46:29',
        '2024-08-14 19:23:53',
        1,
        2866,
        NULL,
        'Local Group',
        'ravens-fort.ansteorra.org'
    ),
    (
        41,
        'Barony of Bjornsborg',
        'San Antonio, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/bjornsborg\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/bjornsborg\\/local_activities\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/Bjornsborg\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/GGTBHNr76r\",\"type\":\"discord\"}]',
        1,
        59,
        60,
        '2025-03-01 14:58:28',
        '2024-08-14 19:24:54',
        1,
        1096,
        NULL,
        'Local Group',
        'bjornsborg.ansteorra.org'
    ),
    (
        42,
        'Shire of Ffynnon Gath',
        'San Marcos, TX',
        13,
        '[{\"url\":\"https:\\/\\/ansteorra.org\\/ffynnon-gath\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/ansteorra.org\\/ffynnon-gath\\/info\\/\",\"type\":\"link\"},{\"url\":\"https:\\/\\/www.facebook.com\\/groups\\/131275780322581\\/\",\"type\":\"facebook\"},{\"url\":\"https:\\/\\/discord.gg\\/twGW2xYcky\",\"type\":\"discord\"}]',
        1,
        61,
        62,
        '2025-06-04 23:44:00',
        '2024-08-14 19:25:46',
        1,
        2866,
        NULL,
        'Local Group',
        'ffynnon-gath.ansteorra.org'
    );
/*!40000 ALTER TABLE `branches` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `member_roles`
--

DROP TABLE IF EXISTS `member_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 380 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `member_roles`
--

LOCK TABLES `member_roles` WRITE;
/*!40000 ALTER TABLE `member_roles` DISABLE KEYS */
;
INSERT INTO
    `member_roles`
VALUES (
        1,
        1,
        1,
        NULL,
        '2024-05-30 01:22:55',
        'Direct Grant',
        NULL,
        1,
        NULL,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL
    ),
    (
        362,
        2875,
        1116,
        '2025-08-30 21:10:49',
        '2025-06-22 19:38:58',
        'Officers.Officers',
        928,
        1,
        1073,
        '2025-08-30 21:10:50',
        '2025-06-22 19:38:59',
        1,
        1073,
        2
    ),
    (
        363,
        2874,
        1118,
        '2025-08-30 21:10:02',
        '2025-06-22 20:12:55',
        'Officers.Officers',
        930,
        1,
        1073,
        '2025-08-30 21:10:03',
        '2025-06-22 20:12:55',
        1,
        1073,
        12
    ),
    (
        364,
        2874,
        1118,
        '2027-06-22 20:35:28',
        '2025-06-22 20:35:28',
        'Officers.Officers',
        933,
        1,
        NULL,
        '2025-06-22 20:35:28',
        '2025-06-22 20:35:28',
        1,
        2875,
        13
    ),
    (
        365,
        2879,
        200,
        '2026-01-02 21:16:11',
        '2025-07-02 21:16:11',
        'Officers.Officers',
        935,
        1,
        NULL,
        '2025-07-02 21:16:11',
        '2025-07-02 21:16:11',
        1,
        1073,
        11
    ),
    (
        366,
        2880,
        1117,
        NULL,
        '2025-07-02 21:17:24',
        'Officers.Officers',
        936,
        1,
        NULL,
        '2025-07-02 21:17:24',
        '2025-07-02 21:17:24',
        1,
        1073,
        27
    ),
    (
        367,
        2871,
        1117,
        '2025-08-30 21:06:28',
        '2025-07-02 21:17:46',
        'Officers.Officers',
        937,
        1,
        1073,
        '2025-08-30 21:06:29',
        '2025-07-02 21:17:46',
        1,
        1073,
        27
    ),
    (
        368,
        2881,
        100,
        '2026-07-02 21:28:40',
        '2025-07-02 21:28:40',
        'Officers.Officers',
        938,
        1,
        NULL,
        '2025-07-02 21:28:40',
        '2025-07-02 21:28:40',
        1,
        1073,
        2
    ),
    (
        369,
        2880,
        1117,
        NULL,
        '2025-07-09 21:57:17',
        'Officers.Officers',
        939,
        1,
        NULL,
        '2025-07-09 21:57:17',
        '2025-07-09 21:57:17',
        1,
        1073,
        28
    ),
    (
        370,
        2874,
        1117,
        NULL,
        '2025-07-09 21:59:26',
        'Officers.Officers',
        940,
        1,
        NULL,
        '2025-07-09 21:59:26',
        '2025-07-09 21:59:26',
        1,
        1073,
        33
    ),
    (
        371,
        2874,
        1117,
        NULL,
        '2025-07-09 21:59:47',
        'Officers.Officers',
        941,
        1,
        NULL,
        '2025-07-09 21:59:47',
        '2025-07-09 21:59:47',
        1,
        1073,
        38
    ),
    (
        372,
        2882,
        1109,
        '2025-08-30 21:12:11',
        '2025-08-07 21:10:56',
        'Officers.Officers',
        943,
        1,
        1073,
        '2025-08-30 21:12:12',
        '2025-08-07 21:10:56',
        1,
        1073,
        2
    ),
    (
        374,
        2872,
        1118,
        '2027-08-30 21:08:09',
        '2025-08-30 21:08:09',
        'Officers.Officers',
        949,
        1,
        NULL,
        '2025-08-30 21:08:09',
        '2025-08-30 21:08:09',
        1,
        1073,
        39
    ),
    (
        375,
        2873,
        1118,
        '2027-08-30 21:09:16',
        '2025-08-30 21:09:16',
        'Officers.Officers',
        950,
        1,
        NULL,
        '2025-08-30 21:09:16',
        '2025-08-30 21:09:16',
        1,
        1073,
        12
    ),
    (
        376,
        2875,
        1116,
        '2027-08-30 21:11:04',
        '2025-08-30 21:11:04',
        'Officers.Officers',
        952,
        1,
        NULL,
        '2025-08-30 21:11:04',
        '2025-08-30 21:11:04',
        1,
        1073,
        2
    ),
    (
        377,
        2876,
        1109,
        '2027-08-30 21:12:12',
        '2025-08-30 21:12:12',
        'Officers.Officers',
        953,
        1,
        NULL,
        '2025-08-30 21:12:12',
        '2025-08-30 21:12:12',
        1,
        1073,
        2
    ),
    (
        378,
        2882,
        1117,
        NULL,
        '2025-08-30 21:15:22',
        'Officers.Officers',
        954,
        1,
        NULL,
        '2025-08-30 21:15:22',
        '2025-08-30 21:15:22',
        1,
        1073,
        33
    ),
    (
        379,
        2882,
        1117,
        NULL,
        '2025-08-30 21:15:45',
        'Officers.Officers',
        955,
        1,
        NULL,
        '2025-08-30 21:15:45',
        '2025-08-30 21:15:45',
        1,
        1073,
        38
    );
/*!40000 ALTER TABLE `member_roles` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `members` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
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
    `additional_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT _utf8mb3 '{}' CHECK (json_valid(`additional_info`)),
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
    KEY `deleted` (`deleted`),
    KEY `branch_id` (`branch_id`),
    CONSTRAINT `members_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 2884 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */
;
INSERT INTO
    `members`
VALUES (
        1,
        '',
        'Admin von Admin',
        'Addy',
        '',
        'Min',
        'Fake Data',
        'a city',
        'TX',
        '00000',
        '555-555-5555',
        'admin@amp.ansteorra.org',
        'AdminAccount',
        '2100-01-01',
        24,
        NULL,
        'verified',
        NULL,
        NULL,
        NULL,
        '9cf9fd5c389304f85d5ade102a9c9119',
        NULL,
        NULL,
        '2025-05-27 19:54:14',
        NULL,
        0,
        4,
        1977,
        '{\"CallIntoCourt\": \"Never\", \"CourtAvailability\": \"None\", \"OrderOfPrecedence_Id\": \"-1000\", \"PersonToGiveNoticeTo\": \"No one this is a system account\"}',
        NULL,
        '2025-04-10 19:43:14',
        '2024-09-29 15:47:03',
        NULL,
        1,
        NULL,
        NULL,
        NULL,
        NULL,
        1
    ),
    (
        2871,
        '',
        'Agatha Local MoAS Demoer',
        'Agatha',
        '',
        'Demoer',
        '123 street st',
        'cyti',
        'OK',
        '11111',
        '111-111-1111',
        'agatha@ampdemo.com',
        '1111111',
        '2029-07-25',
        17,
        NULL,
        'verified',
        '2025-08-30 21:24:24',
        1073,
        NULL,
        '1949ccc7b67dced1',
        NULL,
        NULL,
        '2025-08-30 21:23:34',
        NULL,
        0,
        4,
        1987,
        '{\"CallIntoCourt\": \"With Notice\", \"CourtAvailability\": \"Evening\", \"PersonToGiveNoticeTo\": \"Bryce Demoer\"}',
        NULL,
        '2025-08-30 21:24:24',
        '2025-06-22 18:38:03',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2872,
        '',
        'Bryce Local Seneschal Demoer',
        'Bryce',
        '',
        'Demoer',
        '234 street st',
        'other cyti',
        'ok',
        '22222',
        '222-222-2222',
        'bryce@ampdemo.com',
        '222222222',
        '2029-07-25',
        22,
        NULL,
        'verified',
        '2025-06-22 18:52:58',
        1073,
        NULL,
        'c9e30b0d3bc71f41',
        NULL,
        NULL,
        '2025-07-30 23:33:23',
        NULL,
        0,
        12,
        1982,
        '{}',
        NULL,
        '2025-08-30 21:08:24',
        '2025-06-22 18:51:53',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2873,
        '',
        'Caroline Regional Seneschal Demoer',
        'Caroline',
        '',
        'Demoer',
        '333 street st',
        'some other syti',
        'tx',
        '33333',
        '333-333-3333',
        'caroline@ampdemo.com',
        '333333333',
        '2028-07-22',
        33,
        NULL,
        'verified',
        '2025-06-22 19:31:21',
        1073,
        NULL,
        '5b6e6e11d147d237',
        NULL,
        NULL,
        '2025-07-30 23:33:27',
        NULL,
        0,
        5,
        1965,
        '{}',
        NULL,
        '2025-08-30 21:09:31',
        '2025-06-22 19:29:32',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2874,
        '',
        'Devon Regional Armored Demoer',
        'Devon',
        '',
        'Demoer',
        '444 street st',
        'somewhere',
        'tx',
        '44444',
        '444-444-4444',
        'devon@ampdemo.com',
        '44444444',
        '2025-12-30',
        27,
        NULL,
        'verified',
        '2025-06-22 19:38:02',
        1073,
        NULL,
        '380100f2b4794b42',
        NULL,
        NULL,
        '2025-08-15 20:57:52',
        NULL,
        0,
        9,
        2002,
        '{}',
        NULL,
        '2025-08-30 21:10:27',
        '2025-06-22 19:32:33',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2875,
        '',
        'Eirik Kingdom Seneschal Demoer',
        'Eirik',
        '',
        'Demoer',
        '555 street st',
        'That City',
        'tx',
        '55555',
        '555-555-5555',
        'eirik@ampdemo.com',
        '555555555',
        '2025-09-23',
        36,
        NULL,
        'verified',
        '2025-06-22 19:37:29',
        1073,
        NULL,
        'fe2857083862a393',
        NULL,
        NULL,
        '2025-08-27 23:27:39',
        NULL,
        0,
        12,
        2004,
        '{}',
        NULL,
        '2025-08-30 21:11:19',
        '2025-06-22 19:34:27',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2876,
        '',
        'Garun Kingdom Rapier Deputy Demoer',
        'Garun',
        '',
        'Demoer',
        '777 street st',
        'there',
        'tx',
        '77777',
        '777-777-7777',
        'garun@ampdemo.com',
        '777777777',
        '2029-11-24',
        38,
        NULL,
        'verified',
        '2025-06-25 02:07:01',
        1073,
        NULL,
        '30f2b7dde6d6c0e4',
        NULL,
        NULL,
        '2025-06-25 02:06:27',
        NULL,
        0,
        7,
        1989,
        '{}',
        NULL,
        '2025-08-30 21:12:26',
        '2025-06-25 02:05:31',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2877,
        '',
        'Haylee Kingdom MoAS Deputy Demoer',
        'Haylee',
        '',
        'Demoer',
        '888 street st',
        'here',
        'tx',
        '88888',
        '888-888-8888',
        'haylee@ampdemo.com',
        '88888888',
        '2030-11-24',
        38,
        NULL,
        'verified',
        '2025-06-25 02:32:20',
        1073,
        NULL,
        'fbe83c090b3204bf',
        NULL,
        NULL,
        '2025-06-25 02:32:59',
        NULL,
        0,
        4,
        2006,
        '{}',
        NULL,
        '2025-08-30 21:12:55',
        '2025-06-25 02:30:06',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2878,
        '',
        'Iris Basic User Demoer',
        'Iris',
        '',
        'Demoer',
        '999 street st',
        'here',
        'tx',
        '99999',
        '9999999999',
        'iris@ampdemo.com',
        '',
        NULL,
        30,
        NULL,
        'active',
        NULL,
        NULL,
        NULL,
        '800c45bec8b22900',
        NULL,
        NULL,
        '2025-07-02 23:08:32',
        NULL,
        0,
        6,
        1988,
        '{}',
        NULL,
        '2025-08-30 21:13:19',
        '2025-07-02 21:10:45',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        0
    ),
    (
        2879,
        '',
        'Jael Principality Coronet Demoer',
        'Jael',
        '',
        'Demoer',
        '10 street st',
        'joke',
        'ok',
        '10101',
        '1010101010',
        'jael@ampdemo.com',
        '12121',
        '2029-11-21',
        36,
        NULL,
        'verified',
        '2025-07-02 21:21:56',
        1073,
        NULL,
        'a457c4abcac54e6d',
        NULL,
        NULL,
        '2025-07-02 23:10:03',
        NULL,
        0,
        8,
        1948,
        '{}',
        NULL,
        '2025-08-30 21:13:47',
        '2025-07-02 21:13:19',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2880,
        '',
        'Kal Local Landed w Canton Demoer',
        'Kal',
        '',
        'Demoer',
        '11 street st',
        'asdf',
        'tx',
        '11011',
        '1111111112',
        'kal@ampdemo.com',
        'asdfasdf',
        '2029-11-23',
        19,
        NULL,
        'verified',
        '2025-07-02 21:31:55',
        1073,
        NULL,
        '82d588407d14dbc7',
        NULL,
        NULL,
        '2025-07-16 22:44:00',
        NULL,
        0,
        8,
        2006,
        '{}',
        NULL,
        '2025-08-30 21:14:17',
        '2025-07-02 21:14:50',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2881,
        '',
        'Forest Crown Demoer',
        'Forest',
        '',
        'Demoer',
        '6666 street st',
        'nowhere',
        'tx',
        '66666',
        '6666666666',
        'forest@ampdemo.com',
        '66666666',
        '2029-01-09',
        33,
        NULL,
        'verified',
        '2025-07-02 21:28:16',
        1073,
        NULL,
        'd12e846934665088',
        NULL,
        NULL,
        '2025-08-27 23:28:12',
        NULL,
        0,
        10,
        1987,
        '{}',
        NULL,
        '2025-08-30 21:11:36',
        '2025-07-02 21:27:27',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2882,
        '',
        'Leonard Landed with Stronghold Demoer',
        'Leonard',
        '',
        'Demoer',
        '12 street st',
        'place',
        'bi',
        '12000',
        '1212121212',
        'leonard@ampdemo.com',
        '120000',
        '2029-07-18',
        22,
        NULL,
        'verified',
        '2025-08-07 21:08:44',
        1073,
        NULL,
        '663b5d190bb0e70c',
        NULL,
        NULL,
        '2025-08-07 21:11:07',
        NULL,
        0,
        9,
        1982,
        '{}',
        NULL,
        '2025-08-30 21:16:04',
        '2025-08-07 21:05:57',
        NULL,
        1073,
        NULL,
        '',
        '',
        '',
        1
    ),
    (
        2883,
        '',
        'Mel Local Exch and Kingdom Social Demoer',
        'Mel',
        '',
        'Demoer',
        '13 street way',
        'there',
        'ah',
        '13131',
        '111-333-0000',
        'mel@ampdemo.com',
        '13',
        '2028-11-22',
        24,
        NULL,
        'verified',
        '2025-08-30 21:30:56',
        1073,
        NULL,
        '7267a94cd6a316ae',
        NULL,
        NULL,
        '2025-08-30 21:29:32',
        NULL,
        0,
        3,
        1995,
        '{}',
        NULL,
        '2025-08-30 21:30:56',
        '2025-08-30 21:27:38',
        NULL,
        1073,
        NULL,
        NULL,
        NULL,
        NULL,
        1
    );
/*!40000 ALTER TABLE `members` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 802 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */
;
/*!40000 ALTER TABLE `notes` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `officers_departments`
--

DROP TABLE IF EXISTS `officers_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 13 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `officers_departments`
--

LOCK TABLES `officers_departments` WRITE;
/*!40000 ALTER TABLE `officers_departments` DISABLE KEYS */
;
INSERT INTO
    `officers_departments`
VALUES (
        1,
        'Nobility',
        '2025-01-02 14:08:14',
        '2025-01-02 14:08:14',
        1,
        1,
        NULL,
        ''
    ),
    (
        2,
        'Seneschallate',
        '2025-01-16 17:28:27',
        '2025-01-02 14:08:26',
        1,
        1096,
        NULL,
        ''
    ),
    (
        3,
        'Marshallate',
        '2025-01-06 01:15:36',
        '2025-01-02 14:08:35',
        1,
        1,
        NULL,
        ''
    ),
    (
        4,
        'Webministry',
        '2025-01-15 02:26:41',
        '2025-01-02 14:08:48',
        1,
        1073,
        NULL,
        ''
    ),
    (
        5,
        'Arts & Sciences',
        '2025-01-02 14:08:56',
        '2025-01-02 14:08:56',
        1,
        1,
        NULL,
        ''
    ),
    (
        6,
        'Treasury',
        '2025-01-13 20:39:29',
        '2025-01-02 14:09:38',
        1,
        1073,
        NULL,
        ''
    ),
    (
        7,
        'Chatelaine',
        '2025-01-13 20:41:32',
        '2025-01-02 14:10:13',
        1,
        1096,
        NULL,
        ''
    ),
    (
        8,
        'Historian',
        '2025-01-02 14:10:24',
        '2025-01-02 14:10:24',
        1,
        1,
        NULL,
        ''
    ),
    (
        9,
        'Chronicler',
        '2025-01-02 14:11:02',
        '2025-01-02 14:11:02',
        1,
        1,
        NULL,
        ''
    ),
    (
        10,
        'College of Heralds',
        '2025-01-02 14:11:34',
        '2025-01-02 14:11:34',
        1,
        1,
        NULL,
        ''
    ),
    (
        11,
        'College of Scribes',
        '2025-01-02 14:11:54',
        '2025-01-02 14:11:54',
        1,
        1,
        NULL,
        ''
    ),
    (
        12,
        'Youth and Family Office',
        '2025-01-13 20:04:21',
        '2025-01-02 16:01:21',
        1,
        1096,
        NULL,
        ''
    );
/*!40000 ALTER TABLE `officers_departments` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `officers_officers`
--

DROP TABLE IF EXISTS `officers_officers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 958 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `officers_officers`
--

LOCK TABLES `officers_officers` WRITE;
/*!40000 ALTER TABLE `officers_officers` DISABLE KEYS */
;
INSERT INTO
    `officers_officers`
VALUES (
        928,
        2875,
        2,
        10,
        362,
        '2025-08-30 21:10:49',
        '2025-06-22 19:38:58',
        'Released',
        NULL,
        'cleaning up demo users',
        1073,
        1,
        '2025-06-22 19:38:58',
        NULL,
        NULL,
        '2025-08-30 21:10:50',
        '2025-06-22 19:38:59',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        929,
        2874,
        12,
        13,
        NULL,
        '2025-06-22 20:12:36',
        '2025-06-22 00:00:00',
        'Released',
        NULL,
        'test release',
        2875,
        1,
        '2025-06-22 19:51:00',
        2,
        10,
        '2025-06-22 20:12:37',
        '2025-06-22 19:51:00',
        1,
        2875,
        NULL,
        NULL,
        ''
    ),
    (
        930,
        2874,
        12,
        13,
        363,
        '2025-08-30 21:10:02',
        '2025-06-22 20:12:55',
        'Released',
        NULL,
        'cleaning up demo users',
        1073,
        1,
        '2025-06-22 20:12:55',
        2,
        10,
        '2025-08-30 21:10:03',
        '2025-06-22 20:12:55',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        931,
        2872,
        30,
        14,
        NULL,
        '2025-08-30 21:06:57',
        '2025-06-22 20:33:41',
        'Released',
        NULL,
        'cleaning up demo users',
        1073,
        1,
        '2025-06-22 20:33:41',
        12,
        13,
        '2025-08-30 21:06:58',
        '2025-06-22 20:33:41',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        932,
        2871,
        31,
        14,
        NULL,
        '2027-06-22 20:34:23',
        '2025-06-22 20:34:23',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-06-22 20:34:23',
        12,
        13,
        '2025-06-22 20:34:23',
        '2025-06-22 20:34:23',
        1,
        2874,
        NULL,
        NULL,
        ''
    ),
    (
        933,
        2874,
        13,
        13,
        364,
        '2027-06-22 20:35:28',
        '2025-06-22 20:35:28',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-06-22 20:35:28',
        2,
        10,
        '2025-06-22 20:35:28',
        '2025-06-22 20:35:28',
        1,
        2875,
        NULL,
        NULL,
        ''
    ),
    (
        934,
        2877,
        2,
        12,
        NULL,
        '2027-06-25 02:32:54',
        '2025-06-25 02:32:54',
        'Current',
        'Demoer Deputy',
        '',
        NULL,
        1,
        '2025-06-25 02:32:54',
        2,
        10,
        '2025-06-25 02:32:54',
        '2025-06-25 02:32:54',
        1,
        2875,
        2,
        10,
        ''
    ),
    (
        935,
        2879,
        11,
        93,
        365,
        '2026-01-02 21:16:11',
        '2025-07-02 21:16:11',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-02 21:16:11',
        2,
        NULL,
        '2025-07-02 21:16:11',
        '2025-07-02 21:16:11',
        1,
        1073,
        NULL,
        NULL,
        'prince@vindheim.ansteorra.org'
    ),
    (
        936,
        2880,
        27,
        95,
        366,
        NULL,
        '2025-07-02 21:17:24',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-02 21:17:24',
        2,
        1,
        '2025-07-02 21:17:24',
        '2025-07-02 21:17:24',
        1,
        1073,
        NULL,
        NULL,
        'baron@steppes.ansteorra.org'
    ),
    (
        937,
        2871,
        27,
        95,
        367,
        '2025-08-30 21:06:28',
        '2025-07-02 21:17:46',
        'Released',
        NULL,
        'cleaning up demo users',
        1073,
        1,
        '2025-07-02 21:17:46',
        2,
        1,
        '2025-08-30 21:06:29',
        '2025-07-02 21:17:46',
        1,
        1073,
        NULL,
        NULL,
        'baroness@steppes.ansteorra.org'
    ),
    (
        938,
        2881,
        2,
        1,
        368,
        '2026-07-02 21:28:40',
        '2025-07-02 21:28:40',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-02 21:28:40',
        NULL,
        NULL,
        '2025-07-02 21:28:40',
        '2025-07-02 21:28:40',
        1,
        1073,
        NULL,
        NULL,
        'trm@ansteorra.org'
    ),
    (
        939,
        2880,
        28,
        95,
        369,
        NULL,
        '2025-07-09 21:57:17',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-09 21:57:17',
        27,
        1,
        '2025-07-09 21:57:17',
        '2025-07-09 21:57:17',
        1,
        1073,
        NULL,
        NULL,
        'baron@steppes.ansteorra.org'
    ),
    (
        940,
        2874,
        33,
        95,
        370,
        NULL,
        '2025-07-09 21:59:26',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-09 21:59:26',
        2,
        1,
        '2025-07-09 21:59:26',
        '2025-07-09 21:59:26',
        1,
        1073,
        NULL,
        NULL,
        'baron@bryn-gwlad.ansteorra.org'
    ),
    (
        941,
        2874,
        38,
        95,
        371,
        NULL,
        '2025-07-09 21:59:47',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-07-09 21:59:47',
        2,
        1,
        '2025-07-09 21:59:47',
        '2025-07-09 21:59:47',
        1,
        1073,
        NULL,
        NULL,
        'baron@bryn-gwlad.ansteorra.org'
    ),
    (
        942,
        2882,
        11,
        4,
        NULL,
        '2025-08-30 21:14:37',
        '2025-08-07 21:09:06',
        'Released',
        NULL,
        'cleaning up demo users',
        1073,
        1,
        '2025-08-07 21:09:06',
        2,
        3,
        '2025-08-30 21:14:38',
        '2025-08-07 21:09:06',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        943,
        2882,
        2,
        3,
        372,
        '2025-08-30 21:12:11',
        '2025-08-07 21:10:56',
        'Replaced',
        '',
        'Replaced by new officer',
        1073,
        1,
        '2025-08-07 21:10:56',
        2,
        2,
        '2025-08-30 21:12:12',
        '2025-08-07 21:10:56',
        1,
        1073,
        2,
        2,
        ''
    ),
    (
        949,
        2872,
        39,
        31,
        374,
        '2027-08-30 21:08:09',
        '2025-08-30 21:08:09',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:08:09',
        13,
        30,
        '2025-08-30 21:08:09',
        '2025-08-30 21:08:09',
        1,
        1073,
        NULL,
        NULL,
        'seneschal@stargate.ansteorra.org'
    ),
    (
        950,
        2873,
        12,
        30,
        375,
        '2027-08-30 21:09:16',
        '2025-08-30 21:09:16',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:09:16',
        2,
        28,
        '2025-08-30 21:09:16',
        '2025-08-30 21:09:16',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        951,
        2874,
        12,
        16,
        NULL,
        '2027-08-30 21:10:12',
        '2025-08-30 21:10:12',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:10:12',
        2,
        17,
        '2025-08-30 21:10:12',
        '2025-08-30 21:10:12',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        952,
        2875,
        2,
        28,
        376,
        '2027-08-30 21:11:04',
        '2025-08-30 21:11:04',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:11:04',
        NULL,
        NULL,
        '2025-08-30 21:11:04',
        '2025-08-30 21:11:04',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        953,
        2876,
        2,
        3,
        377,
        '2027-08-30 21:12:12',
        '2025-08-30 21:12:12',
        'Current',
        '',
        '',
        NULL,
        1,
        '2025-08-30 21:12:12',
        2,
        2,
        '2025-08-30 21:12:12',
        '2025-08-30 21:12:12',
        1,
        1073,
        2,
        2,
        ''
    ),
    (
        954,
        2882,
        33,
        95,
        378,
        NULL,
        '2025-08-30 21:15:22',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:15:22',
        2,
        1,
        '2025-08-30 21:15:22',
        '2025-08-30 21:15:22',
        1,
        1073,
        NULL,
        NULL,
        'baron@bryn-gwlad.ansteorra.org'
    ),
    (
        955,
        2882,
        38,
        95,
        379,
        NULL,
        '2025-08-30 21:15:45',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:15:45',
        2,
        1,
        '2025-08-30 21:15:45',
        '2025-08-30 21:15:45',
        1,
        1073,
        NULL,
        NULL,
        'baron@bryn-gwlad.ansteorra.org'
    ),
    (
        956,
        2883,
        37,
        76,
        NULL,
        '2027-08-30 21:31:13',
        '2025-08-30 21:31:13',
        'Current',
        NULL,
        '',
        NULL,
        1,
        '2025-08-30 21:31:13',
        13,
        77,
        '2025-08-30 21:31:13',
        '2025-08-30 21:31:13',
        1,
        1073,
        NULL,
        NULL,
        ''
    ),
    (
        957,
        2883,
        2,
        50,
        NULL,
        '2027-08-30 21:31:30',
        '2025-08-30 21:31:30',
        'Current',
        '',
        '',
        NULL,
        1,
        '2025-08-30 21:31:30',
        2,
        28,
        '2025-08-30 21:31:30',
        '2025-08-30 21:31:30',
        1,
        1073,
        2,
        28,
        ''
    );
/*!40000 ALTER TABLE `officers_officers` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `officers_offices`
--

DROP TABLE IF EXISTS `officers_offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 96 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `officers_offices`
--

LOCK TABLES `officers_offices` WRITE;
/*!40000 ALTER TABLE `officers_offices` DISABLE KEYS */
;
INSERT INTO
    `officers_offices`
VALUES (
        1,
        'Crown',
        1,
        0,
        1,
        0,
        0,
        NULL,
        100,
        12,
        '2025-04-21 22:30:58',
        '2025-01-02 14:17:56',
        1,
        1,
        NULL,
        '\"Kingdom\"',
        NULL,
        'trm'
    ),
    (
        2,
        'Kingdom Earl Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        1108,
        24,
        '2025-01-12 17:05:36',
        '2025-01-02 14:37:55',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        3,
        'Kingdom Rapier Marshal',
        3,
        1,
        1,
        0,
        1,
        2,
        1109,
        24,
        '2025-01-12 17:05:14',
        '2025-01-02 14:38:32',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        4,
        'Regional Rapier Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-12 17:06:20',
        '2025-01-02 15:32:17',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        3,
        ''
    ),
    (
        5,
        'Local Rapier Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-12 17:04:27',
        '2025-01-02 15:32:44',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        4,
        ''
    ),
    (
        6,
        'Kingdom Chronicler',
        9,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-12 17:17:35',
        '2025-01-02 15:43:54',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        7,
        'Kingdom Chronicler Deputy',
        9,
        1,
        0,
        0,
        0,
        6,
        NULL,
        24,
        '2025-01-02 15:44:26',
        '2025-01-02 15:44:26',
        1,
        1,
        NULL,
        '\"Kingdom\"',
        6,
        ''
    ),
    (
        8,
        'Regional Chronicler',
        9,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-12 17:04:38',
        '2025-01-02 15:45:03',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        6,
        ''
    ),
    (
        9,
        'Local Chronicler',
        9,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-02 15:52:01',
        '2025-01-02 15:52:01',
        1,
        1,
        NULL,
        '\"Local Group\"',
        8,
        ''
    ),
    (
        10,
        'Kingdom MoAS',
        5,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-12 17:23:37',
        '2025-01-02 16:01:49',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        11,
        'Kingdom Earl Marshal Deputy',
        3,
        1,
        0,
        0,
        0,
        2,
        NULL,
        24,
        '2025-01-02 16:03:03',
        '2025-01-02 16:03:03',
        1,
        1,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        12,
        'Kingdom MoAS Deputy',
        5,
        1,
        0,
        0,
        0,
        10,
        NULL,
        24,
        '2025-01-02 16:03:41',
        '2025-01-02 16:03:41',
        1,
        1,
        NULL,
        '\"Kingdom\"',
        10,
        ''
    ),
    (
        13,
        'Regional MoAS',
        5,
        1,
        1,
        0,
        1,
        NULL,
        1118,
        24,
        '2025-06-22 20:00:21',
        '2025-01-02 16:20:33',
        1,
        1073,
        NULL,
        '\"Principality\",\"Region\"',
        10,
        ''
    ),
    (
        14,
        'Local MoAS',
        5,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-02 16:21:02',
        '2025-01-02 16:21:02',
        1,
        1,
        NULL,
        '\"Local Group\"',
        13,
        ''
    ),
    (
        15,
        'Local Armored Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-14 01:51:01',
        '2025-01-06 01:12:23',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        16,
        ''
    ),
    (
        16,
        'Regional Armored Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 15:30:05',
        '2025-01-06 01:16:28',
        1,
        1096,
        NULL,
        '\"Region\"',
        17,
        ''
    ),
    (
        17,
        'Kingdom Armored Marshal',
        3,
        1,
        1,
        0,
        1,
        2,
        1111,
        24,
        '2025-01-12 18:33:08',
        '2025-01-06 01:20:16',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        18,
        'Kingdom Webminister',
        4,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-12 17:18:04',
        '2025-01-07 16:08:48',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        19,
        'Deleted: Application Admin',
        4,
        1,
        0,
        0,
        0,
        18,
        NULL,
        24,
        '2025-01-13 14:48:19',
        '2025-01-07 16:11:56',
        1,
        1096,
        '2025-01-13 14:48:19',
        '\"Kingdom\"',
        18,
        ''
    ),
    (
        20,
        'Kingdom Webminister Deputy',
        4,
        1,
        0,
        0,
        0,
        18,
        NULL,
        24,
        '2025-01-13 14:48:51',
        '2025-01-07 16:22:45',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        18,
        ''
    ),
    (
        21,
        'Local Webminister',
        4,
        1,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-07 16:38:21',
        '2025-01-07 16:38:21',
        1,
        1,
        NULL,
        '\"Local Group\"',
        18,
        ''
    ),
    (
        22,
        'Kingdom Webminister - AMP Admin',
        4,
        1,
        0,
        0,
        0,
        18,
        10,
        24,
        '2025-01-13 14:49:14',
        '2025-01-07 21:23:35',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        18,
        ''
    ),
    (
        23,
        'At Large: Rapier Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        3,
        1002,
        48,
        '2025-01-13 17:17:04',
        '2025-01-12 17:07:25',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        24,
        'Kingdom Missile Marshal',
        3,
        1,
        0,
        0,
        1,
        2,
        1110,
        24,
        '2025-01-12 17:13:59',
        '2025-01-12 17:09:02',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        25,
        'Kingdom C&T Marshal',
        3,
        1,
        0,
        0,
        1,
        3,
        1003,
        24,
        '2025-01-12 17:13:38',
        '2025-01-12 17:10:26',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        26,
        'At Large: C&T Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        25,
        1003,
        48,
        '2025-01-13 17:16:51',
        '2025-01-12 17:14:22',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        25,
        ''
    ),
    (
        27,
        'Deleted: At Large: Authorizing Target Archery Marshal',
        3,
        1,
        0,
        0,
        0,
        58,
        1004,
        48,
        '2025-01-13 17:17:27',
        '2025-01-12 17:16:05',
        1,
        1096,
        '2025-01-13 17:17:27',
        '\"Kingdom\"',
        58,
        ''
    ),
    (
        28,
        'Kingdom Seneschal',
        2,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-12 17:22:23',
        '2025-01-12 17:20:13',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        29,
        'Kingdom Seneschal Deputy',
        2,
        1,
        0,
        0,
        0,
        28,
        NULL,
        24,
        '2025-01-12 17:20:52',
        '2025-01-12 17:20:52',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        28,
        ''
    ),
    (
        30,
        'Regional Seneschal',
        2,
        1,
        1,
        0,
        1,
        NULL,
        1118,
        24,
        '2025-06-22 00:16:36',
        '2025-01-12 17:21:31',
        1,
        2866,
        NULL,
        '\"Principality\",\"Region\"',
        28,
        ''
    ),
    (
        31,
        'Local Seneschal',
        2,
        1,
        1,
        0,
        1,
        NULL,
        1118,
        24,
        '2025-06-22 01:10:32',
        '2025-01-12 17:21:57',
        1,
        2866,
        NULL,
        '\"Local Group\"',
        30,
        'seneschal'
    ),
    (
        32,
        'Kingdom Rapier Marshal Deputy',
        3,
        1,
        0,
        0,
        0,
        NULL,
        NULL,
        24,
        '2025-01-12 17:36:25',
        '2025-01-12 17:36:25',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        33,
        'Kingdom Missile Marshal Deputy',
        3,
        1,
        0,
        0,
        0,
        24,
        NULL,
        24,
        '2025-01-12 17:36:59',
        '2025-01-12 17:36:59',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        24,
        ''
    ),
    (
        34,
        'At Large: Armored Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        17,
        1001,
        48,
        '2025-01-13 17:16:22',
        '2025-01-12 17:38:20',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        17,
        ''
    ),
    (
        35,
        'Kingdom Equestrian Marshal',
        3,
        1,
        0,
        0,
        0,
        2,
        1112,
        24,
        '2025-01-12 17:39:07',
        '2025-01-12 17:39:07',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        36,
        'At Large: C&T 2 Handed Weapons Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        25,
        1012,
        48,
        '2025-01-13 17:16:34',
        '2025-01-12 17:41:11',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        25,
        ''
    ),
    (
        37,
        'At Large: Equestrian Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        35,
        1005,
        48,
        '2025-01-18 21:22:20',
        '2025-01-12 17:44:06',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        35,
        ''
    ),
    (
        38,
        'At Large: Wooden Lance Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        35,
        1013,
        48,
        '2025-01-12 17:45:44',
        '2025-01-12 17:45:44',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        35,
        ''
    ),
    (
        39,
        'Kingdom Youth Rapier Marshal',
        3,
        1,
        0,
        0,
        1,
        3,
        1114,
        24,
        '2025-01-21 01:43:23',
        '2025-01-12 18:05:03',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        40,
        'Kingdom Youth Armored Marshal',
        3,
        1,
        0,
        0,
        1,
        17,
        1113,
        24,
        '2025-01-21 01:43:10',
        '2025-01-12 18:05:53',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        17,
        ''
    ),
    (
        41,
        'Star Principal Herald',
        10,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-12 18:07:11',
        '2025-01-12 18:07:11',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        42,
        'Kingdom Herald Deputy',
        10,
        1,
        0,
        0,
        0,
        41,
        NULL,
        24,
        '2025-01-15 01:10:30',
        '2025-01-12 18:08:35',
        1,
        1073,
        NULL,
        '\"Kingdom\"',
        41,
        ''
    ),
    (
        43,
        'Regional Herald',
        10,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-12 18:09:37',
        '2025-01-12 18:09:37',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        41,
        ''
    ),
    (
        44,
        'Local Herald',
        10,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 19:36:40',
        '2025-01-12 18:09:56',
        1,
        1073,
        NULL,
        '\"Local Group\"',
        43,
        ''
    ),
    (
        45,
        'Kingdom Chatelaine',
        7,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-13 20:40:12',
        '2025-01-12 21:55:13',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        46,
        'Regional Chatelaine',
        7,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:41:21',
        '2025-01-12 21:55:41',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        45,
        ''
    ),
    (
        47,
        'Local Chatelaine',
        7,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:40:49',
        '2025-01-12 21:56:03',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        46,
        ''
    ),
    (
        48,
        'Kingdom Chatelaine Deputy',
        7,
        1,
        0,
        0,
        0,
        45,
        NULL,
        24,
        '2025-01-13 20:40:28',
        '2025-01-12 21:56:30',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        45,
        ''
    ),
    (
        49,
        'Local Seneschal Deputy',
        2,
        0,
        0,
        0,
        0,
        31,
        NULL,
        24,
        '2025-01-13 14:21:26',
        '2025-01-13 14:21:26',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        31,
        ''
    ),
    (
        50,
        'Kingdom Social Media Officer',
        2,
        1,
        1,
        0,
        1,
        28,
        NULL,
        24,
        '2025-01-13 14:28:54',
        '2025-01-13 14:28:54',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        28,
        ''
    ),
    (
        51,
        'Kingdom Youth and Family Officer',
        12,
        1,
        1,
        0,
        1,
        NULL,
        1116,
        24,
        '2025-01-13 20:02:54',
        '2025-01-13 14:33:31',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        52,
        'Regional Youth and Family Officer',
        12,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:03:38',
        '2025-01-13 14:35:07',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        51,
        ''
    ),
    (
        53,
        'Local Youth and Family Officer',
        12,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:03:21',
        '2025-01-13 14:35:31',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        52,
        ''
    ),
    (
        54,
        'Local Social Media Officer',
        2,
        1,
        1,
        1,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 19:50:45',
        '2025-01-13 14:36:05',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        50,
        ''
    ),
    (
        55,
        'Local Rapier Marshal Deputy',
        3,
        0,
        0,
        0,
        0,
        5,
        NULL,
        24,
        '2025-01-13 15:28:28',
        '2025-01-13 15:28:28',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        5,
        ''
    ),
    (
        56,
        'Local Armored Marshal Deputy',
        3,
        0,
        0,
        0,
        0,
        15,
        NULL,
        24,
        '2025-01-13 15:29:23',
        '2025-01-13 15:29:23',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        15,
        ''
    ),
    (
        57,
        'Local Heraldry Deputy',
        10,
        0,
        0,
        0,
        0,
        43,
        NULL,
        24,
        '2025-01-13 19:34:38',
        '2025-01-13 15:31:05',
        1,
        1073,
        NULL,
        '\"Local Group\"',
        43,
        ''
    ),
    (
        58,
        'Kingdom Target Archery Marshal',
        3,
        1,
        0,
        0,
        1,
        24,
        1004,
        24,
        '2025-01-13 17:08:04',
        '2025-01-13 17:06:57',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        24,
        ''
    ),
    (
        59,
        'Kingdom Thrown Weapons Marshal',
        3,
        1,
        0,
        0,
        1,
        24,
        1010,
        24,
        '2025-01-13 17:07:41',
        '2025-01-13 17:07:41',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        24,
        ''
    ),
    (
        60,
        'Kingdom Siege Weapons Marshal',
        3,
        1,
        0,
        0,
        1,
        24,
        1008,
        24,
        '2025-01-13 17:10:47',
        '2025-01-13 17:10:47',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        24,
        ''
    ),
    (
        61,
        'Kingdom Combat Archery Marshal',
        3,
        1,
        0,
        0,
        1,
        24,
        1011,
        24,
        '2025-01-13 17:11:20',
        '2025-01-13 17:11:20',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        24,
        ''
    ),
    (
        62,
        'At Large: Target Archery Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        58,
        1004,
        48,
        '2025-01-13 17:12:11',
        '2025-01-13 17:12:11',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        58,
        ''
    ),
    (
        63,
        'At Large: Combat Archery Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        61,
        1011,
        48,
        '2025-01-13 17:13:30',
        '2025-01-13 17:13:30',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        61,
        ''
    ),
    (
        64,
        'At Large: Thrown Weapons Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        59,
        1010,
        48,
        '2025-01-13 17:14:08',
        '2025-01-13 17:14:08',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        59,
        ''
    ),
    (
        65,
        'At Large: Siege Weapons Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        60,
        1008,
        48,
        '2025-01-13 17:15:32',
        '2025-01-13 17:15:32',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        60,
        ''
    ),
    (
        66,
        'At Large: Youth Rapier Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        39,
        1006,
        48,
        '2025-01-13 17:26:32',
        '2025-01-13 17:26:32',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        39,
        ''
    ),
    (
        67,
        'At Large: Youth Armored Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        40,
        1007,
        48,
        '2025-01-13 17:27:18',
        '2025-01-13 17:27:18',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        40,
        ''
    ),
    (
        68,
        'Kingdom Armored Marshal Deputy',
        3,
        1,
        0,
        0,
        0,
        17,
        NULL,
        24,
        '2025-01-13 17:31:06',
        '2025-01-13 17:31:06',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        17,
        ''
    ),
    (
        69,
        'Regional Seneschal Deputy',
        2,
        1,
        0,
        0,
        0,
        30,
        NULL,
        24,
        '2025-01-13 19:19:09',
        '2025-01-13 19:19:09',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        30,
        ''
    ),
    (
        70,
        'Regional Webminister',
        4,
        1,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:01:02',
        '2025-01-13 20:01:02',
        1,
        1073,
        NULL,
        '\"Principality\"',
        18,
        ''
    ),
    (
        71,
        'Regional Herald Deputy',
        10,
        1,
        0,
        0,
        0,
        43,
        NULL,
        24,
        '2025-01-13 20:11:32',
        '2025-01-13 20:11:32',
        1,
        1096,
        NULL,
        '\"Principality\",\"Region\"',
        43,
        ''
    ),
    (
        72,
        'Local Chatelaine Deputy',
        7,
        0,
        0,
        0,
        0,
        47,
        NULL,
        24,
        '2025-01-13 20:41:08',
        '2025-01-13 20:34:40',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        47,
        ''
    ),
    (
        73,
        'Kingdom Treasurer',
        6,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:37:59',
        '2025-01-13 20:37:38',
        1,
        1073,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        74,
        'Kingdom Treasurer Deputy',
        6,
        1,
        0,
        0,
        0,
        73,
        NULL,
        24,
        '2025-01-13 20:39:20',
        '2025-01-13 20:38:42',
        1,
        1073,
        NULL,
        '\"Kingdom\"',
        73,
        ''
    ),
    (
        75,
        'Deleted: Regional Treasurer',
        6,
        1,
        1,
        0,
        1,
        73,
        NULL,
        24,
        '2025-01-13 20:46:18',
        '2025-01-13 20:40:26',
        1,
        1073,
        '2025-01-13 20:46:18',
        '\"Principality\",\"Region\"',
        73,
        ''
    ),
    (
        76,
        'Local Treasurer',
        6,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 21:03:50',
        '2025-01-13 20:41:04',
        1,
        1096,
        NULL,
        '\"Local Group\"',
        77,
        ''
    ),
    (
        77,
        'Regional Treasurer',
        6,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-13 20:47:07',
        '2025-01-13 20:47:07',
        1,
        1073,
        NULL,
        '\"Principality\",\"Region\"',
        73,
        ''
    ),
    (
        78,
        'Kingdom Social Media Deputy',
        2,
        1,
        0,
        0,
        0,
        50,
        NULL,
        24,
        '2025-01-13 21:56:33',
        '2025-01-13 21:56:33',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        50,
        ''
    ),
    (
        79,
        'Regional Chronicler Deputy',
        9,
        1,
        0,
        0,
        0,
        8,
        NULL,
        24,
        '2025-01-13 22:03:17',
        '2025-01-13 22:03:17',
        1,
        1073,
        NULL,
        '\"Principality\",\"Region\"',
        8,
        ''
    ),
    (
        80,
        'Kingdom Earl Marshal Deputy - Secretary ',
        3,
        1,
        0,
        0,
        0,
        2,
        20,
        24,
        '2025-01-14 19:27:30',
        '2025-01-14 19:27:30',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        2,
        ''
    ),
    (
        81,
        'Local Thrown Weapons Marshal',
        3,
        1,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-14 20:40:55',
        '2025-01-14 19:32:14',
        1,
        1073,
        NULL,
        '\"Local Group\"',
        59,
        ''
    ),
    (
        82,
        'Regional Rapier Marshal Deputy',
        3,
        1,
        0,
        0,
        0,
        4,
        NULL,
        24,
        '2025-01-14 19:48:16',
        '2025-01-14 19:48:16',
        1,
        1073,
        NULL,
        '\"Principality\",\"Region\"',
        4,
        ''
    ),
    (
        83,
        'Local Target Archery Marshal',
        3,
        1,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-14 20:41:00',
        '2025-01-14 20:01:40',
        1,
        1073,
        NULL,
        '\"Local Group\"',
        58,
        ''
    ),
    (
        84,
        'Principality Earl Marshal',
        3,
        1,
        1,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-14 20:08:25',
        '2025-01-14 20:08:25',
        1,
        1073,
        NULL,
        '\"Principality\"',
        2,
        ''
    ),
    (
        85,
        'Regional Target Archery Marshal',
        3,
        1,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-14 20:35:55',
        '2025-01-14 20:35:12',
        1,
        1941,
        NULL,
        '\"Principality\",\"Region\"',
        24,
        ''
    ),
    (
        86,
        'Coronet',
        1,
        1,
        0,
        0,
        0,
        NULL,
        NULL,
        12,
        '2025-01-15 02:28:09',
        '2025-01-15 02:28:09',
        1,
        1073,
        NULL,
        '\"Kingdom\"',
        NULL,
        ''
    ),
    (
        87,
        'Local Target Archery Marshal Deputy',
        3,
        0,
        0,
        0,
        0,
        83,
        NULL,
        24,
        '2025-01-15 16:31:05',
        '2025-01-15 16:31:05',
        1,
        1941,
        NULL,
        '\"Local Group\"',
        83,
        ''
    ),
    (
        88,
        'At Large: Rapier Spear Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        3,
        1009,
        48,
        '2025-01-17 13:29:10',
        '2025-01-17 13:29:10',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        89,
        'At Large: Rapier Reduced Armor Experiment Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        3,
        1014,
        48,
        '2025-01-17 13:31:25',
        '2025-01-17 13:31:25',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        3,
        ''
    ),
    (
        90,
        'At Large: C&T - Historic Combat Experiment Authorizing Marshal',
        3,
        1,
        0,
        0,
        0,
        25,
        1015,
        48,
        '2025-01-17 17:03:02',
        '2025-01-17 17:00:59',
        1,
        1096,
        NULL,
        '\"Kingdom\"',
        25,
        ''
    ),
    (
        91,
        'Regional Youth Rapier Marshal',
        3,
        0,
        0,
        0,
        1,
        NULL,
        NULL,
        24,
        '2025-01-21 01:48:06',
        '2025-01-21 01:48:06',
        1,
        1941,
        NULL,
        '\"Principality\",\"Region\"',
        39,
        ''
    ),
    (
        92,
        'Deleted: Landed Nobility',
        1,
        1,
        1,
        0,
        0,
        1,
        1117,
        0,
        '2025-04-21 14:51:33',
        '2025-03-01 15:02:57',
        1,
        1,
        '2025-04-21 14:51:33',
        '\"Local Group\"',
        1,
        'baron'
    ),
    (
        93,
        'Principality Sovereign',
        1,
        0,
        1,
        0,
        1,
        NULL,
        200,
        6,
        '2025-03-04 15:42:58',
        '2025-03-04 15:35:09',
        1,
        2866,
        NULL,
        '\"Principality\"',
        NULL,
        'prince'
    ),
    (
        94,
        'Principality Consort',
        6,
        1,
        1,
        0,
        1,
        NULL,
        200,
        6,
        '2025-03-04 15:43:12',
        '2025-03-04 15:38:01',
        1,
        2866,
        NULL,
        '\"Principality\"',
        NULL,
        'princess'
    ),
    (
        95,
        'Local Landed',
        1,
        0,
        1,
        1,
        0,
        NULL,
        1117,
        0,
        '2025-04-21 22:32:12',
        '2025-04-21 14:50:19',
        1,
        1,
        NULL,
        '\"Local Group\"',
        1,
        'baron'
    );
/*!40000 ALTER TABLE `officers_offices` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `officers_phinxlog`
--

DROP TABLE IF EXISTS `officers_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `officers_phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `officers_phinxlog`
--

LOCK TABLES `officers_phinxlog` WRITE;
/*!40000 ALTER TABLE `officers_phinxlog` DISABLE KEYS */
;
INSERT INTO
    `officers_phinxlog`
VALUES (
        20240614000951,
        'InitOffices',
        '2024-09-29 15:47:03',
        '2024-09-29 15:47:03',
        0
    ),
    (
        20241231161659,
        'RefactorOfficeHierarchy',
        '2025-01-12 01:02:22',
        '2025-01-12 01:02:22',
        0
    ),
    (
        20250124204321,
        'AddViewOfficersPermission',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        0
    ),
    (
        20250227230922,
        'AddDomainToDepartment',
        '2025-03-01 14:35:53',
        '2025-03-01 14:35:53',
        0
    ),
    (
        20250228133830,
        'MakeOfficerTermMonthsNotYears',
        '2025-03-01 14:35:53',
        '2025-03-01 14:35:53',
        0
    );
/*!40000 ALTER TABLE `officers_phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `permission_policies`
--

DROP TABLE IF EXISTS `permission_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `permission_policies` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `permission_id` int(11) NOT NULL,
    `policy_class` varchar(255) NOT NULL,
    `policy_method` varchar(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `permission_id` (`permission_id`),
    CONSTRAINT `permission_policies_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 782 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `permission_policies`
--

LOCK TABLES `permission_policies` WRITE;
/*!40000 ALTER TABLE `permission_policies` DISABLE KEYS */
;
INSERT INTO
    `permission_policies`
VALUES (
        1,
        2,
        'App\\Policy\\RolePolicy',
        'canAdd'
    ),
    (
        2,
        2,
        'App\\Policy\\RolePolicy',
        'canAddPermission'
    ),
    (
        3,
        2,
        'App\\Policy\\RolePolicy',
        'canDelete'
    ),
    (
        4,
        2,
        'App\\Policy\\RolePolicy',
        'canDeletePermission'
    ),
    (
        5,
        2,
        'App\\Policy\\RolePolicy',
        'canEdit'
    ),
    (
        6,
        2,
        'App\\Policy\\RolePolicy',
        'canIndex'
    ),
    (
        7,
        2,
        'App\\Policy\\RolePolicy',
        'canView'
    ),
    (
        8,
        2,
        'App\\Policy\\RolePolicy',
        'canViewPrivateNotes'
    ),
    (
        9,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canAdd'
    ),
    (
        10,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canAddPermission'
    ),
    (
        11,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canDelete'
    ),
    (
        12,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canDeletePermission'
    ),
    (
        13,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canEdit'
    ),
    (
        14,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canIndex'
    ),
    (
        15,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canSearchMembers'
    ),
    (
        16,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canView'
    ),
    (
        17,
        2,
        'App\\Policy\\RolesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        18,
        3,
        'App\\Policy\\PermissionPolicy',
        'canAdd'
    ),
    (
        19,
        3,
        'App\\Policy\\PermissionPolicy',
        'canDelete'
    ),
    (
        20,
        3,
        'App\\Policy\\PermissionPolicy',
        'canEdit'
    ),
    (
        21,
        3,
        'App\\Policy\\PermissionPolicy',
        'canIndex'
    ),
    (
        22,
        3,
        'App\\Policy\\PermissionPolicy',
        'canUpdatePolicy'
    ),
    (
        23,
        3,
        'App\\Policy\\PermissionPolicy',
        'canView'
    ),
    (
        24,
        3,
        'App\\Policy\\PermissionPolicy',
        'canViewPrivateNotes'
    ),
    (
        25,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canAdd'
    ),
    (
        26,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canDelete'
    ),
    (
        27,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canEdit'
    ),
    (
        28,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canIndex'
    ),
    (
        29,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canMatrix'
    ),
    (
        30,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canView'
    ),
    (
        31,
        3,
        'App\\Policy\\PermissionsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        32,
        4,
        'App\\Policy\\BranchPolicy',
        'canAdd'
    ),
    (
        33,
        4,
        'App\\Policy\\BranchPolicy',
        'canDelete'
    ),
    (
        34,
        4,
        'App\\Policy\\BranchPolicy',
        'canEdit'
    ),
    (
        35,
        4,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        36,
        4,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        37,
        4,
        'App\\Policy\\BranchPolicy',
        'canViewPrivateNotes'
    ),
    (
        38,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canAdd'
    ),
    (
        39,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canDelete'
    ),
    (
        40,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canEdit'
    ),
    (
        41,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        42,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        43,
        4,
        'App\\Policy\\BranchesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        44,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canAdd'
    ),
    (
        45,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canDelete'
    ),
    (
        46,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canEdit'
    ),
    (
        47,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canIndex'
    ),
    (
        48,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canView'
    ),
    (
        49,
        5,
        'App\\Policy\\AppSettingPolicy',
        'canViewPrivateNotes'
    ),
    (
        56,
        6,
        'App\\Policy\\MemberPolicy',
        'canAdd'
    ),
    (
        57,
        6,
        'App\\Policy\\MemberPolicy',
        'canAddNote'
    ),
    (
        58,
        6,
        'App\\Policy\\MemberPolicy',
        'canChangePassword'
    ),
    (
        59,
        6,
        'App\\Policy\\MemberPolicy',
        'canDelete'
    ),
    (
        60,
        6,
        'App\\Policy\\MemberPolicy',
        'canEdit'
    ),
    (
        61,
        6,
        'App\\Policy\\MemberPolicy',
        'canEditAdditionalInfo'
    ),
    (
        62,
        6,
        'App\\Policy\\MemberPolicy',
        'canImportExpirationDates'
    ),
    (
        63,
        6,
        'App\\Policy\\MemberPolicy',
        'canIndex'
    ),
    (
        64,
        6,
        'App\\Policy\\MemberPolicy',
        'canPartialEdit'
    ),
    (
        65,
        6,
        'App\\Policy\\MemberPolicy',
        'canSendMobileCardEmail'
    ),
    (
        66,
        6,
        'App\\Policy\\MemberPolicy',
        'canVerifyMembership'
    ),
    (
        67,
        6,
        'App\\Policy\\MemberPolicy',
        'canVerifyQueue'
    ),
    (
        68,
        6,
        'App\\Policy\\MemberPolicy',
        'canView'
    ),
    (
        69,
        6,
        'App\\Policy\\MemberPolicy',
        'canViewCard'
    ),
    (
        70,
        6,
        'App\\Policy\\MemberPolicy',
        'canViewCardJson'
    ),
    (
        71,
        6,
        'App\\Policy\\MemberPolicy',
        'canViewPrivateNotes'
    ),
    (
        79,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canAdd'
    ),
    (
        80,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canDelete'
    ),
    (
        81,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canEdit'
    ),
    (
        82,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canIndex'
    ),
    (
        83,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canVerifyQueue'
    ),
    (
        84,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canView'
    ),
    (
        85,
        6,
        'App\\Policy\\MembersTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        90,
        7,
        'App\\Policy\\ReportsControllerPolicy',
        'canPermissionsWarrantsRoster'
    ),
    (
        91,
        7,
        'App\\Policy\\ReportsControllerPolicy',
        'canRolesList'
    ),
    (
        94,
        8,
        'App\\Policy\\MemberPolicy',
        'canIndex'
    ),
    (
        95,
        8,
        'App\\Policy\\MemberPolicy',
        'canView'
    ),
    (
        96,
        8,
        'App\\Policy\\MemberPolicy',
        'canViewCard'
    ),
    (
        97,
        8,
        'App\\Policy\\MemberPolicy',
        'canViewCardJson'
    ),
    (
        98,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canAdd'
    ),
    (
        99,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canDelete'
    ),
    (
        100,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canEdit'
    ),
    (
        101,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canIndex'
    ),
    (
        102,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canView'
    ),
    (
        103,
        11,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        104,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canAdd'
    ),
    (
        105,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canDelete'
    ),
    (
        106,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canEdit'
    ),
    (
        107,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canIndex'
    ),
    (
        108,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canView'
    ),
    (
        109,
        11,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canViewPrivateNotes'
    ),
    (
        110,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canAdd'
    ),
    (
        111,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canDelete'
    ),
    (
        112,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canEdit'
    ),
    (
        113,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canIndex'
    ),
    (
        114,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canView'
    ),
    (
        115,
        11,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        116,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canAdd'
    ),
    (
        117,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canDelete'
    ),
    (
        118,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canEdit'
    ),
    (
        119,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canIndex'
    ),
    (
        120,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canView'
    ),
    (
        121,
        11,
        'Activities\\Policy\\ActivityPolicy',
        'canViewPrivateNotes'
    ),
    (
        122,
        11,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canAuthorizations'
    ),
    (
        123,
        12,
        'Activities\\Policy\\AuthorizationPolicy',
        'canRevoke'
    ),
    (
        124,
        13,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canView'
    ),
    (
        125,
        13,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canMyQueue'
    ),
    (
        126,
        13,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canIndex'
    ),
    (
        127,
        14,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canAuthorizations'
    ),
    (
        134,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canAdd'
    ),
    (
        135,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canDelete'
    ),
    (
        136,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canEdit'
    ),
    (
        137,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canIndex'
    ),
    (
        138,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canView'
    ),
    (
        139,
        21,
        'Officers\\Policy\\OfficePolicy',
        'canViewPrivateNotes'
    ),
    (
        140,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canAdd'
    ),
    (
        141,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canDelete'
    ),
    (
        142,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canEdit'
    ),
    (
        143,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canIndex'
    ),
    (
        144,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canView'
    ),
    (
        145,
        21,
        'Officers\\Policy\\OfficesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        146,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canAssign'
    ),
    (
        147,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        148,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canEdit'
    ),
    (
        149,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficers'
    ),
    (
        150,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficersByWarrantStatus'
    ),
    (
        151,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canRelease'
    ),
    (
        152,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canRequestWarrant'
    ),
    (
        153,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithAllOfficers'
    ),
    (
        154,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithOfficerDeputies'
    ),
    (
        155,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithOfficerDirectReports'
    ),
    (
        156,
        22,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithOfficerReportingTree'
    ),
    (
        157,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canAdd'
    ),
    (
        158,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canDelete'
    ),
    (
        159,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canEdit'
    ),
    (
        160,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canIndex'
    ),
    (
        161,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canView'
    ),
    (
        162,
        23,
        'Officers\\Policy\\DepartmentPolicy',
        'canViewPrivateNotes'
    ),
    (
        163,
        24,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canDepartmentOfficersRoster'
    ),
    (
        170,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canAdd'
    ),
    (
        171,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canDelete'
    ),
    (
        172,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canEdit'
    ),
    (
        173,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canIndex'
    ),
    (
        174,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canView'
    ),
    (
        175,
        31,
        'Awards\\Policy\\AwardPolicy',
        'canViewPrivateNotes'
    ),
    (
        176,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canAdd'
    ),
    (
        177,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canDelete'
    ),
    (
        178,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canEdit'
    ),
    (
        179,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canIndex'
    ),
    (
        180,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canView'
    ),
    (
        181,
        31,
        'Awards\\Policy\\AwardsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        182,
        32,
        'Awards\\Policy\\RecommendationPolicy',
        'canIndex'
    ),
    (
        183,
        32,
        'Awards\\Policy\\RecommendationPolicy',
        'canView'
    ),
    (
        184,
        32,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewEventRecommendations'
    ),
    (
        185,
        32,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedByMember'
    ),
    (
        186,
        32,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedForMember'
    ),
    (
        187,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canAdd'
    ),
    (
        188,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canAddNote'
    ),
    (
        189,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelArmigerous'
    ),
    (
        190,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelGrant'
    ),
    (
        191,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelNobility'
    ),
    (
        192,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelNon-Armigerous'
    ),
    (
        193,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelPeerage'
    ),
    (
        194,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canDelete'
    ),
    (
        195,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canEdit'
    ),
    (
        196,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canExport'
    ),
    (
        197,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canIndex'
    ),
    (
        198,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canUpdateStates'
    ),
    (
        199,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canUseBoard'
    ),
    (
        200,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canView'
    ),
    (
        201,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewEventRecommendations'
    ),
    (
        202,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewHidden'
    ),
    (
        203,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewPrivateNotes'
    ),
    (
        204,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedByMember'
    ),
    (
        205,
        33,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedForMember'
    ),
    (
        206,
        1067,
        'App\\Policy\\WarrantPolicy',
        'canAllWarrants'
    ),
    (
        207,
        1067,
        'App\\Policy\\WarrantPolicy',
        'canIndex'
    ),
    (
        208,
        1067,
        'App\\Policy\\WarrantPolicy',
        'canView'
    ),
    (
        209,
        1067,
        'App\\Policy\\WarrantsTablePolicy',
        'canIndex'
    ),
    (
        210,
        1067,
        'App\\Policy\\WarrantsTablePolicy',
        'canView'
    ),
    (
        211,
        1067,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canView'
    ),
    (
        212,
        1067,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canIndex'
    ),
    (
        213,
        1067,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAllRosters'
    ),
    (
        214,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canAdd'
    ),
    (
        215,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canAllRosters'
    ),
    (
        216,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canApprove'
    ),
    (
        217,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canDecline'
    ),
    (
        218,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canDelete'
    ),
    (
        219,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canEdit'
    ),
    (
        220,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canIndex'
    ),
    (
        221,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canView'
    ),
    (
        222,
        1068,
        'App\\Policy\\WarrantRosterPolicy',
        'canViewPrivateNotes'
    ),
    (
        223,
        1067,
        'App\\Policy\\WarrantRosterPolicy',
        'canAllRosters'
    ),
    (
        224,
        1067,
        'App\\Policy\\WarrantRosterPolicy',
        'canIndex'
    ),
    (
        225,
        1067,
        'App\\Policy\\WarrantRosterPolicy',
        'canView'
    ),
    (
        226,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAdd'
    ),
    (
        227,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAllRosters'
    ),
    (
        228,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canDelete'
    ),
    (
        229,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canEdit'
    ),
    (
        230,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canIndex'
    ),
    (
        231,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canView'
    ),
    (
        232,
        1068,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        233,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canAdd'
    ),
    (
        234,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canAllWarrants'
    ),
    (
        235,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canDeactivate'
    ),
    (
        236,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canDeclineWarrantInRoster'
    ),
    (
        237,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canDelete'
    ),
    (
        238,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canEdit'
    ),
    (
        239,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canIndex'
    ),
    (
        240,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canView'
    ),
    (
        241,
        1068,
        'App\\Policy\\WarrantPolicy',
        'canViewPrivateNotes'
    ),
    (
        242,
        1069,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        243,
        1069,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        244,
        1070,
        'Officers\\Policy\\OfficerPolicy',
        'canAssign'
    ),
    (
        245,
        1070,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithAllOfficers'
    ),
    (
        247,
        1070,
        'Officers\\Policy\\OfficesTablePolicy',
        'canView'
    ),
    (
        248,
        1070,
        'Officers\\Policy\\OfficesTablePolicy',
        'canIndex'
    ),
    (
        249,
        1071,
        'Officers\\Policy\\OfficesTablePolicy',
        'canIndex'
    ),
    (
        250,
        1071,
        'Officers\\Policy\\OfficesTablePolicy',
        'canView'
    ),
    (
        251,
        1071,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithAllOfficers'
    ),
    (
        253,
        1070,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        254,
        1070,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        255,
        1071,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        256,
        1071,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        257,
        1070,
        'Officers\\Policy\\OfficerPolicy',
        'canRequestWarrant'
    ),
    (
        258,
        1072,
        'Officers\\Policy\\OfficerPolicy',
        'canRequestWarrant'
    ),
    (
        259,
        1072,
        'Officers\\Policy\\RostersControllerPolicy',
        'canAdd'
    ),
    (
        260,
        1072,
        'Officers\\Policy\\RostersControllerPolicy',
        'canCreateRoster'
    ),
    (
        261,
        1072,
        'Officers\\Policy\\RostersControllerPolicy',
        'canView'
    ),
    (
        262,
        1072,
        'Officers\\Policy\\RostersControllerPolicy',
        'canIndex'
    ),
    (
        263,
        1075,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canView'
    ),
    (
        264,
        1075,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canIndex'
    ),
    (
        265,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canUseBoard'
    ),
    (
        266,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canExport'
    ),
    (
        267,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewEventRecommendations'
    ),
    (
        268,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canView'
    ),
    (
        269,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canEdit'
    ),
    (
        270,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canIndex'
    ),
    (
        271,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canUpdateStates'
    ),
    (
        272,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canAddNote'
    ),
    (
        273,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewPrivateNotes'
    ),
    (
        274,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canApproveLevelNon-Armigerous'
    ),
    (
        275,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canAdd'
    ),
    (
        276,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canDelete'
    ),
    (
        277,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewHidden'
    ),
    (
        278,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedForMember'
    ),
    (
        279,
        1075,
        'Awards\\Policy\\RecommendationPolicy',
        'canViewSubmittedByMember'
    ),
    (
        281,
        1075,
        'App\\Policy\\MemberPolicy',
        'canIndex'
    ),
    (
        283,
        1075,
        'App\\Policy\\MembersTablePolicy',
        'canIndex'
    ),
    (
        284,
        1075,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        301,
        1076,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        302,
        1076,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        304,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canRelease'
    ),
    (
        305,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canAssign'
    ),
    (
        306,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canRequestWarrant'
    ),
    (
        307,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        308,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficersByWarrantStatus'
    ),
    (
        309,
        1076,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        311,
        1076,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        312,
        1076,
        'Officers\\Policy\\OfficesTablePolicy',
        'canIndex'
    ),
    (
        313,
        1076,
        'Officers\\Policy\\OfficesTablePolicy',
        'canView'
    ),
    (
        315,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canUpdatePolicy'
    ),
    (
        316,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canAdd'
    ),
    (
        317,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canEdit'
    ),
    (
        318,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canDelete'
    ),
    (
        319,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canView'
    ),
    (
        320,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canIndex'
    ),
    (
        321,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canViewPrivateNotes'
    ),
    (
        322,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canAllWarrants'
    ),
    (
        323,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canDeactivate'
    ),
    (
        324,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canDeclineWarrantInRoster'
    ),
    (
        325,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canAdd'
    ),
    (
        326,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canEdit'
    ),
    (
        327,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canDelete'
    ),
    (
        328,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canView'
    ),
    (
        329,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canIndex'
    ),
    (
        330,
        1077,
        'App\\Policy\\WarrantPolicy',
        'canViewPrivateNotes'
    ),
    (
        331,
        1077,
        'App\\Policy\\BranchPolicy',
        'canAdd'
    ),
    (
        332,
        1077,
        'App\\Policy\\BranchPolicy',
        'canEdit'
    ),
    (
        333,
        1077,
        'App\\Policy\\BranchPolicy',
        'canDelete'
    ),
    (
        334,
        1077,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        335,
        1077,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        336,
        1077,
        'App\\Policy\\BranchPolicy',
        'canViewPrivateNotes'
    ),
    (
        337,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canAdd'
    ),
    (
        338,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canEdit'
    ),
    (
        339,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canDelete'
    ),
    (
        340,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        341,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        342,
        1077,
        'App\\Policy\\BranchesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        343,
        1077,
        'App\\Policy\\MemberPolicy',
        'canView'
    ),
    (
        344,
        1077,
        'App\\Policy\\MemberPolicy',
        'canPartialEdit'
    ),
    (
        345,
        1077,
        'App\\Policy\\MemberPolicy',
        'canViewCard'
    ),
    (
        346,
        1077,
        'App\\Policy\\MemberPolicy',
        'canSendMobileCardEmail'
    ),
    (
        347,
        1077,
        'App\\Policy\\MemberPolicy',
        'canAddNote'
    ),
    (
        348,
        1077,
        'App\\Policy\\MemberPolicy',
        'canChangePassword'
    ),
    (
        349,
        1077,
        'App\\Policy\\MemberPolicy',
        'canViewCardJson'
    ),
    (
        350,
        1077,
        'App\\Policy\\MemberPolicy',
        'canDelete'
    ),
    (
        351,
        1077,
        'App\\Policy\\MemberPolicy',
        'canImportExpirationDates'
    ),
    (
        352,
        1077,
        'App\\Policy\\MemberPolicy',
        'canVerifyMembership'
    ),
    (
        353,
        1077,
        'App\\Policy\\MemberPolicy',
        'canVerifyQueue'
    ),
    (
        354,
        1077,
        'App\\Policy\\MemberPolicy',
        'canEditAdditionalInfo'
    ),
    (
        355,
        1077,
        'App\\Policy\\MemberPolicy',
        'canAdd'
    ),
    (
        356,
        1077,
        'App\\Policy\\MemberPolicy',
        'canEdit'
    ),
    (
        357,
        1077,
        'App\\Policy\\MemberPolicy',
        'canIndex'
    ),
    (
        358,
        1077,
        'App\\Policy\\MemberPolicy',
        'canViewPrivateNotes'
    ),
    (
        359,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canDeactivate'
    ),
    (
        360,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canAdd'
    ),
    (
        361,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canEdit'
    ),
    (
        362,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canDelete'
    ),
    (
        363,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canView'
    ),
    (
        364,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canIndex'
    ),
    (
        365,
        1077,
        'App\\Policy\\MemberRolePolicy',
        'canViewPrivateNotes'
    ),
    (
        366,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canDeactivate'
    ),
    (
        367,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canAdd'
    ),
    (
        368,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canEdit'
    ),
    (
        369,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canDelete'
    ),
    (
        370,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canView'
    ),
    (
        371,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canIndex'
    ),
    (
        372,
        1077,
        'App\\Policy\\MemberRolesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        373,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canVerifyQueue'
    ),
    (
        374,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canAdd'
    ),
    (
        375,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canEdit'
    ),
    (
        376,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canDelete'
    ),
    (
        377,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canView'
    ),
    (
        378,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canIndex'
    ),
    (
        379,
        1077,
        'App\\Policy\\MembersTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        380,
        1077,
        'App\\Policy\\NotePolicy',
        'canAdd'
    ),
    (
        381,
        1077,
        'App\\Policy\\NotePolicy',
        'canEdit'
    ),
    (
        382,
        1077,
        'App\\Policy\\NotePolicy',
        'canDelete'
    ),
    (
        383,
        1077,
        'App\\Policy\\NotePolicy',
        'canView'
    ),
    (
        384,
        1077,
        'App\\Policy\\NotesTablePolicy',
        'canAdd'
    ),
    (
        385,
        1077,
        'App\\Policy\\NotesTablePolicy',
        'canEdit'
    ),
    (
        386,
        1077,
        'App\\Policy\\NotesTablePolicy',
        'canDelete'
    ),
    (
        387,
        1077,
        'App\\Policy\\NotesTablePolicy',
        'canView'
    ),
    (
        388,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canMatrix'
    ),
    (
        389,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canAdd'
    ),
    (
        390,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canEdit'
    ),
    (
        391,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canDelete'
    ),
    (
        392,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canView'
    ),
    (
        393,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canIndex'
    ),
    (
        394,
        1077,
        'App\\Policy\\PermissionsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        395,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canRolesList'
    ),
    (
        396,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canPermissionsWarrantsRoster'
    ),
    (
        397,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canAdd'
    ),
    (
        398,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canEdit'
    ),
    (
        399,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canDelete'
    ),
    (
        400,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canView'
    ),
    (
        401,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canIndex'
    ),
    (
        402,
        1077,
        'App\\Policy\\ReportsControllerPolicy',
        'canViewPrivateNotes'
    ),
    (
        403,
        1077,
        'App\\Policy\\RolePolicy',
        'canDeletePermission'
    ),
    (
        404,
        1077,
        'App\\Policy\\RolePolicy',
        'canAddPermission'
    ),
    (
        405,
        1077,
        'App\\Policy\\RolePolicy',
        'canAdd'
    ),
    (
        406,
        1077,
        'App\\Policy\\RolePolicy',
        'canEdit'
    ),
    (
        407,
        1077,
        'App\\Policy\\RolePolicy',
        'canDelete'
    ),
    (
        408,
        1077,
        'App\\Policy\\RolePolicy',
        'canView'
    ),
    (
        409,
        1077,
        'App\\Policy\\RolePolicy',
        'canIndex'
    ),
    (
        410,
        1077,
        'App\\Policy\\RolePolicy',
        'canViewPrivateNotes'
    ),
    (
        411,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canDeletePermission'
    ),
    (
        412,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canAddPermission'
    ),
    (
        413,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canSearchMembers'
    ),
    (
        414,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canAdd'
    ),
    (
        415,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canEdit'
    ),
    (
        416,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canDelete'
    ),
    (
        417,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canView'
    ),
    (
        418,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canIndex'
    ),
    (
        419,
        1077,
        'App\\Policy\\RolesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        420,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canAdd'
    ),
    (
        421,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canEdit'
    ),
    (
        422,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canDelete'
    ),
    (
        423,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canView'
    ),
    (
        424,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canIndex'
    ),
    (
        425,
        1077,
        'App\\Policy\\WarrantPeriodPolicy',
        'canViewPrivateNotes'
    ),
    (
        426,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canAllRosters'
    ),
    (
        427,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canApprove'
    ),
    (
        428,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canDecline'
    ),
    (
        429,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canAdd'
    ),
    (
        430,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canEdit'
    ),
    (
        431,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canDelete'
    ),
    (
        432,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canView'
    ),
    (
        433,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canIndex'
    ),
    (
        434,
        1077,
        'App\\Policy\\WarrantRosterPolicy',
        'canViewPrivateNotes'
    ),
    (
        435,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canView'
    ),
    (
        436,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canIndex'
    ),
    (
        437,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAllRosters'
    ),
    (
        438,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAdd'
    ),
    (
        439,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canEdit'
    ),
    (
        440,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canDelete'
    ),
    (
        441,
        1077,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        442,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canAdd'
    ),
    (
        443,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canEdit'
    ),
    (
        444,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canDelete'
    ),
    (
        445,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canView'
    ),
    (
        446,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canIndex'
    ),
    (
        447,
        1077,
        'App\\Policy\\WarrantPeriodsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        448,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canView'
    ),
    (
        449,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canIndex'
    ),
    (
        450,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canDeclineWarrantInRoster'
    ),
    (
        451,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canDeactivate'
    ),
    (
        452,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canAdd'
    ),
    (
        453,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canEdit'
    ),
    (
        454,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canDelete'
    ),
    (
        455,
        1077,
        'App\\Policy\\WarrantsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        456,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canAdd'
    ),
    (
        457,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canEdit'
    ),
    (
        458,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canDelete'
    ),
    (
        459,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canView'
    ),
    (
        460,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canIndex'
    ),
    (
        461,
        1077,
        'App\\Policy\\AppSettingPolicy',
        'canViewPrivateNotes'
    ),
    (
        462,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canAdd'
    ),
    (
        463,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canEdit'
    ),
    (
        464,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canDelete'
    ),
    (
        465,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canView'
    ),
    (
        466,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canIndex'
    ),
    (
        467,
        1077,
        'App\\Policy\\AppSettingsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        468,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canAdd'
    ),
    (
        469,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canEdit'
    ),
    (
        470,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canDelete'
    ),
    (
        471,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canView'
    ),
    (
        472,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canIndex'
    ),
    (
        473,
        1077,
        'Activities\\Policy\\ActivitiesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        474,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canAdd'
    ),
    (
        475,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canEdit'
    ),
    (
        476,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canDelete'
    ),
    (
        477,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canView'
    ),
    (
        478,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canIndex'
    ),
    (
        479,
        1077,
        'Activities\\Policy\\ActivityGroupPolicy',
        'canViewPrivateNotes'
    ),
    (
        480,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canAdd'
    ),
    (
        481,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canEdit'
    ),
    (
        482,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canDelete'
    ),
    (
        483,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canView'
    ),
    (
        484,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canIndex'
    ),
    (
        485,
        1077,
        'Activities\\Policy\\ActivityGroupsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        486,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canAdd'
    ),
    (
        487,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canEdit'
    ),
    (
        488,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canDelete'
    ),
    (
        489,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canView'
    ),
    (
        490,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canIndex'
    ),
    (
        491,
        1077,
        'Activities\\Policy\\ActivityPolicy',
        'canViewPrivateNotes'
    ),
    (
        492,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canApprove'
    ),
    (
        493,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canDeny'
    ),
    (
        494,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canView'
    ),
    (
        495,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canMyQueue'
    ),
    (
        496,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canAvailableApproversList'
    ),
    (
        497,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canAdd'
    ),
    (
        498,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canEdit'
    ),
    (
        499,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canDelete'
    ),
    (
        500,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canIndex'
    ),
    (
        501,
        1077,
        'Activities\\Policy\\AuthorizationApprovalPolicy',
        'canViewPrivateNotes'
    ),
    (
        502,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canMyQueue'
    ),
    (
        503,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canAdd'
    ),
    (
        504,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canEdit'
    ),
    (
        505,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canDelete'
    ),
    (
        506,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canView'
    ),
    (
        507,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canIndex'
    ),
    (
        508,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        509,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canRevoke'
    ),
    (
        510,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canAdd'
    ),
    (
        511,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canRenew'
    ),
    (
        512,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canMemberAuthorizations'
    ),
    (
        513,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canEdit'
    ),
    (
        514,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canDelete'
    ),
    (
        515,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canView'
    ),
    (
        516,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canIndex'
    ),
    (
        517,
        1077,
        'Activities\\Policy\\AuthorizationPolicy',
        'canViewPrivateNotes'
    ),
    (
        518,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canActivityWarrantsRoster'
    ),
    (
        519,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canAuthorizations'
    ),
    (
        520,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canAdd'
    ),
    (
        521,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canEdit'
    ),
    (
        522,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canDelete'
    ),
    (
        523,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canView'
    ),
    (
        524,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canIndex'
    ),
    (
        525,
        1077,
        'Activities\\Policy\\ReportsControllerPolicy',
        'canViewPrivateNotes'
    ),
    (
        526,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canAdd'
    ),
    (
        527,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canEdit'
    ),
    (
        528,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canDelete'
    ),
    (
        529,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canView'
    ),
    (
        530,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canIndex'
    ),
    (
        531,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        532,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canAdd'
    ),
    (
        533,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canEdit'
    ),
    (
        534,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canDelete'
    ),
    (
        535,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canView'
    ),
    (
        536,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canIndex'
    ),
    (
        537,
        1077,
        'Awards\\Policy\\RecommendationsStatesLogPolicy',
        'canViewPrivateNotes'
    ),
    (
        557,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canAdd'
    ),
    (
        558,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canEdit'
    ),
    (
        559,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canDelete'
    ),
    (
        560,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canView'
    ),
    (
        561,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canIndex'
    ),
    (
        562,
        1077,
        'Awards\\Policy\\AwardPolicy',
        'canViewPrivateNotes'
    ),
    (
        563,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canAdd'
    ),
    (
        564,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canEdit'
    ),
    (
        565,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canDelete'
    ),
    (
        566,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canView'
    ),
    (
        567,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canIndex'
    ),
    (
        568,
        1077,
        'Awards\\Policy\\AwardsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        569,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canAdd'
    ),
    (
        570,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canEdit'
    ),
    (
        571,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canDelete'
    ),
    (
        572,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canView'
    ),
    (
        573,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canIndex'
    ),
    (
        574,
        1077,
        'Awards\\Policy\\DomainPolicy',
        'canViewPrivateNotes'
    ),
    (
        575,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canAdd'
    ),
    (
        576,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canEdit'
    ),
    (
        577,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canDelete'
    ),
    (
        578,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canView'
    ),
    (
        579,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canIndex'
    ),
    (
        580,
        1077,
        'Awards\\Policy\\DomainsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        581,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canAllEvents'
    ),
    (
        582,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canAdd'
    ),
    (
        583,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canEdit'
    ),
    (
        584,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canDelete'
    ),
    (
        585,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canView'
    ),
    (
        586,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canIndex'
    ),
    (
        587,
        1077,
        'Awards\\Policy\\EventPolicy',
        'canViewPrivateNotes'
    ),
    (
        588,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canAdd'
    ),
    (
        589,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canEdit'
    ),
    (
        590,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canDelete'
    ),
    (
        591,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canView'
    ),
    (
        592,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canIndex'
    ),
    (
        593,
        1077,
        'Awards\\Policy\\EventsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        594,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canAdd'
    ),
    (
        595,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canEdit'
    ),
    (
        596,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canDelete'
    ),
    (
        597,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canView'
    ),
    (
        598,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canIndex'
    ),
    (
        599,
        1077,
        'Awards\\Policy\\LevelPolicy',
        'canViewPrivateNotes'
    ),
    (
        600,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canAdd'
    ),
    (
        601,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canEdit'
    ),
    (
        602,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canDelete'
    ),
    (
        603,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canView'
    ),
    (
        604,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canIndex'
    ),
    (
        605,
        1077,
        'Awards\\Policy\\LevelsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        606,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canAdd'
    ),
    (
        607,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canEdit'
    ),
    (
        608,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canDelete'
    ),
    (
        609,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canView'
    ),
    (
        610,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canIndex'
    ),
    (
        611,
        1077,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        612,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canSubmit'
    ),
    (
        613,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canAdd'
    ),
    (
        614,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canEdit'
    ),
    (
        615,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canDelete'
    ),
    (
        616,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canView'
    ),
    (
        617,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canIndex'
    ),
    (
        618,
        1077,
        'GitHubIssueSubmitter\\Policy\\IssuesControllerPolicy',
        'canViewPrivateNotes'
    ),
    (
        619,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canAdd'
    ),
    (
        620,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canEdit'
    ),
    (
        621,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canDelete'
    ),
    (
        622,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canView'
    ),
    (
        623,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canIndex'
    ),
    (
        624,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canViewPrivateNotes'
    ),
    (
        636,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canAdd'
    ),
    (
        637,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canEdit'
    ),
    (
        638,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canDelete'
    ),
    (
        639,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canView'
    ),
    (
        640,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canIndex'
    ),
    (
        641,
        1077,
        'Officers\\Policy\\OfficePolicy',
        'canViewPrivateNotes'
    ),
    (
        642,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canAdd'
    ),
    (
        643,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canEdit'
    ),
    (
        644,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canDelete'
    ),
    (
        645,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canView'
    ),
    (
        646,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canIndex'
    ),
    (
        647,
        1077,
        'Officers\\Policy\\OfficesTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        648,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canDepartmentOfficersRoster'
    ),
    (
        649,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canAdd'
    ),
    (
        650,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canEdit'
    ),
    (
        651,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canDelete'
    ),
    (
        652,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canView'
    ),
    (
        653,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canIndex'
    ),
    (
        654,
        1077,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canViewPrivateNotes'
    ),
    (
        655,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canAdd'
    ),
    (
        656,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canCreateRoster'
    ),
    (
        657,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canEdit'
    ),
    (
        658,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canDelete'
    ),
    (
        659,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canView'
    ),
    (
        660,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canIndex'
    ),
    (
        661,
        1077,
        'Officers\\Policy\\RostersControllerPolicy',
        'canViewPrivateNotes'
    ),
    (
        662,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canAddJob'
    ),
    (
        663,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canResetJob'
    ),
    (
        664,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canRemoveJob'
    ),
    (
        665,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canProcesses'
    ),
    (
        666,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canReset'
    ),
    (
        667,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canFlush'
    ),
    (
        668,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canHardReset'
    ),
    (
        669,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canStats'
    ),
    (
        670,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canViewClasses'
    ),
    (
        671,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canImport'
    ),
    (
        672,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canData'
    ),
    (
        673,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canExecute'
    ),
    (
        674,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canTest'
    ),
    (
        675,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canMigrate'
    ),
    (
        676,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canTerminate'
    ),
    (
        677,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canCleanup'
    ),
    (
        678,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canAdd'
    ),
    (
        679,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canEdit'
    ),
    (
        680,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canDelete'
    ),
    (
        681,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canView'
    ),
    (
        682,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canIndex'
    ),
    (
        683,
        1077,
        'Queue\\Policy\\QueuedJobPolicy',
        'canViewPrivateNotes'
    ),
    (
        684,
        1077,
        'App\\Policy\\PermissionPolicy',
        'canMatrix'
    ),
    (
        685,
        1077,
        'App\\Policy\\NotePolicy',
        'canIndex'
    ),
    (
        686,
        1077,
        'App\\Policy\\NotePolicy',
        'canViewPrivateNotes'
    ),
    (
        687,
        1077,
        'Activities\\Policy\\AuthorizationApprovalsTablePolicy',
        'canAllQueues'
    ),
    (
        692,
        1077,
        'Officers\\Policy\\DepartmentPolicy',
        'canSeeAllDepartments'
    ),
    (
        693,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canAdd'
    ),
    (
        694,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canEdit'
    ),
    (
        695,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canDelete'
    ),
    (
        696,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canView'
    ),
    (
        697,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canIndex'
    ),
    (
        698,
        1077,
        'Officers\\Policy\\DepartmentsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        699,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        700,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithAllOfficers'
    ),
    (
        704,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canRelease'
    ),
    (
        705,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canRequestWarrant'
    ),
    (
        706,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficersByWarrantStatus'
    ),
    (
        707,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canEdit'
    ),
    (
        708,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficers'
    ),
    (
        709,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canAssign'
    ),
    (
        710,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canAdd'
    ),
    (
        711,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canDelete'
    ),
    (
        712,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canView'
    ),
    (
        713,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canIndex'
    ),
    (
        714,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canViewPrivateNotes'
    ),
    (
        715,
        1076,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithOfficerReportingTree'
    ),
    (
        718,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canAdd'
    ),
    (
        719,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canEdit'
    ),
    (
        720,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canDelete'
    ),
    (
        721,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canView'
    ),
    (
        722,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canIndex'
    ),
    (
        723,
        33,
        'Awards\\Policy\\RecommendationsTablePolicy',
        'canViewPrivateNotes'
    ),
    (
        724,
        8,
        'App\\Policy\\MembersTablePolicy',
        'canView'
    ),
    (
        725,
        8,
        'App\\Policy\\MembersTablePolicy',
        'canIndex'
    ),
    (
        735,
        1,
        'App\\Policy\\WarrantPolicy',
        'canIndex'
    ),
    (
        736,
        1,
        'App\\Policy\\WarrantPolicy',
        'canView'
    ),
    (
        745,
        1,
        'App\\Policy\\WarrantsTablePolicy',
        'canIndex'
    ),
    (
        746,
        1,
        'App\\Policy\\WarrantsTablePolicy',
        'canView'
    ),
    (
        747,
        1,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canIndex'
    ),
    (
        748,
        1,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canView'
    ),
    (
        749,
        1,
        'App\\Policy\\WarrantRosterPolicy',
        'canIndex'
    ),
    (
        750,
        1,
        'App\\Policy\\WarrantRosterPolicy',
        'canView'
    ),
    (
        751,
        1069,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        752,
        1069,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        753,
        1071,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        754,
        1071,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        755,
        1071,
        'Officers\\Policy\\OfficerPolicy',
        'canRelease'
    ),
    (
        756,
        1071,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        757,
        1070,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        758,
        1070,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        759,
        1070,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        760,
        1070,
        'Officers\\Policy\\OfficerPolicy',
        'canOfficersByWarrantStatus'
    ),
    (
        761,
        1072,
        'Officers\\Policy\\OfficerPolicy',
        'canWorkWithAllOfficers'
    ),
    (
        762,
        1072,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        763,
        1072,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canIndex'
    ),
    (
        764,
        1072,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canView'
    ),
    (
        765,
        1072,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAdd'
    ),
    (
        766,
        1072,
        'App\\Policy\\WarrantRosterPolicy',
        'canIndex'
    ),
    (
        767,
        1072,
        'App\\Policy\\WarrantRosterPolicy',
        'canView'
    ),
    (
        768,
        1072,
        'App\\Policy\\WarrantRosterPolicy',
        'canAdd'
    ),
    (
        769,
        1072,
        'App\\Policy\\WarrantRosterPolicy',
        'canAllRosters'
    ),
    (
        770,
        1072,
        'App\\Policy\\WarrantRostersTablePolicy',
        'canAllRosters'
    ),
    (
        772,
        7,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canDepartmentOfficersRoster'
    ),
    (
        773,
        7,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canView'
    ),
    (
        774,
        7,
        'Officers\\Policy\\ReportsControllerPolicy',
        'canIndex'
    ),
    (
        775,
        1077,
        'Officers\\Policy\\OfficerPolicy',
        'canMemberOfficers'
    ),
    (
        776,
        1075,
        'App\\Policy\\BranchPolicy',
        'canView'
    ),
    (
        777,
        1075,
        'App\\Policy\\BranchesTablePolicy',
        'canView'
    ),
    (
        778,
        1075,
        'App\\Policy\\BranchesTablePolicy',
        'canIndex'
    ),
    (
        779,
        1075,
        'App\\Policy\\BranchPolicy',
        'canIndex'
    ),
    (
        780,
        1075,
        'Officers\\Policy\\OfficerPolicy',
        'canBranchOfficers'
    ),
    (
        781,
        1075,
        'Officers\\Policy\\OfficerPolicy',
        'canMemberOfficers'
    );
/*!40000 ALTER TABLE `permission_policies` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 1078 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */
;
INSERT INTO
    `permissions`
VALUES (
        1,
        'Is Super User',
        1,
        0,
        0,
        1,
        1,
        0,
        '2025-02-18 01:23:22',
        '2024-09-29 15:47:03',
        1,
        1096,
        NULL,
        'Global'
    ),
    (
        2,
        'Can Manage Roles',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        3,
        'Can Manage Permissions',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        4,
        'Can Manage Branches',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        5,
        'Can Manage Settings',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        6,
        'Can Manage Members',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        7,
        'Can View Core Reports',
        0,
        0,
        0,
        1,
        0,
        0,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        8,
        'Can View Members',
        1,
        0,
        0,
        1,
        0,
        0,
        '2024-10-02 22:37:15',
        '2024-10-02 22:37:15',
        NULL,
        NULL,
        NULL,
        'Global'
    ),
    (
        11,
        'Can Manage Activities',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        12,
        'Can Revoke Authorizations',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        13,
        'Can Manage Authorization Queues',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        14,
        'Can View Activity Reports',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        21,
        'Can Manage Offices',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        22,
        'Can Manage Officers',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        23,
        'Can Manage Departments',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        24,
        'Can View Officer Reports',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        31,
        'Can Manage Awards',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        32,
        'Can View Recommendations',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        33,
        'Can Manage Recommendations',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1001,
        ' Approve Armored',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1002,
        ' Approve Fiberglass Spear',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1003,
        ' Approve Armored Field Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1004,
        ' Approve Rapier',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1005,
        ' Approve Rapier Field Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1006,
        ' Approve Cut And Thrust',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1007,
        ' Approve Rapier Spear',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1008,
        ' Approve Siege',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1009,
        ' Approve Siege Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1010,
        ' Approve Armored Combat Archery',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1011,
        ' Approve Rapier Combat Archery',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1012,
        ' Approve Combat Archery Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1013,
        ' Approve Target Archery Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1014,
        ' Approve Thrown Weapons Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1015,
        ' Approve Youth Boffer 1',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1016,
        ' Approve Youth Boffer 2',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1017,
        ' Approve Youth Boffer 3',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1018,
        ' Approve Youth Boffer Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1019,
        ' Approve Youth Boffer Junior Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1020,
        ' Approve Youth Armored Combat',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1021,
        ' Approve Youth Armored Combat Two Weapons',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1022,
        ' Approve Youth Armored Combat Spear',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1023,
        ' Approve Youth Armored Combat Weapon Shield',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1024,
        ' Approve Youth Armored Combat Grea Weapons',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1025,
        ' Approve Youth Armored Field Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1026,
        ' Approve Youth Armored Combat Junior Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1027,
        ' Approve Youth Rapier Combat Foil',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1028,
        ' Approve Youth Rapier Combat Epee',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1029,
        ' Approve Youth Rapier Combat Heavy Rapier',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1030,
        ' Approve Youth Rapier Combat Plastic Sword',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1031,
        ' Approve Youth Rapier Combat Melee',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1032,
        ' Approve Youth Rapier Combat Offensive Secondary',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1033,
        ' Approve Youth Rapier Combat Defensive Secondary',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1034,
        ' Approve Youth Rapier Field Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1035,
        ' Approve Youth Rapier Combat Plastic Sword Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1036,
        ' Approve Experimental: Rapier Spear',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1037,
        ' Approve C&T 2 Handed Weapon',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1038,
        ' Approve Equestrian Field Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1039,
        ' Approve General Riding',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1040,
        ' Approve Mounted Games',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1041,
        ' Approve Mounted Combat',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1042,
        ' Approve Foam Jousting',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1043,
        ' Approve Driving',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1044,
        ' Approve Wooden Lance',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1045,
        ' Approve Mounted Archery',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1050,
        ' Approve C&T - Historic Combat Experiment',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1051,
        ' Approve Reduced Rapier Armor Experiment ',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1052,
        ' Approve Armored Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1053,
        ' Approve Rapier Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1054,
        ' Approve Target Archery Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1055,
        ' Approve C&T Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1056,
        ' Approve Equestrian Authorizing Marshal ',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1057,
        ' Approve Youth Armored Authorizing Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1058,
        ' Approve Youth Rapier Authorizing Marshal',
        1,
        1,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1059,
        ' Approve Siege Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1060,
        ' Approve Rapier Spear Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1061,
        ' Approve Thrown Weapons Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1062,
        ' Approve Combat Archery Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1063,
        ' Approve Two Handed C&T Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1064,
        ' Approve Wooden Lance Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1065,
        ' Approve Reduced Armor Experiement Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1066,
        ' Approve C&T - Historic Combat Experiment Authorizing Marshal',
        1,
        0,
        18,
        0,
        0,
        1,
        NULL,
        '2024-09-29 15:47:04',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1067,
        'Can View Warrants',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:18',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1068,
        'Can Manage Warrants',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:18',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1069,
        'Can View Branches',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:18',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1070,
        'Can Assign Officers',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:22',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1071,
        'Can Release Officers',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:22',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1072,
        'Can Create Officer Roster',
        1,
        0,
        0,
        1,
        0,
        1,
        NULL,
        '2025-01-12 01:02:22',
        1,
        NULL,
        NULL,
        'Global'
    ),
    (
        1073,
        'Can Manage Queue Engine',
        0,
        0,
        0,
        1,
        0,
        0,
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        NULL,
        NULL,
        NULL,
        'Global'
    ),
    (
        1074,
        'Can View Officers',
        0,
        0,
        0,
        1,
        0,
        0,
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        NULL,
        NULL,
        NULL,
        'Global'
    ),
    (
        1075,
        'Branch Non-Armiguous Recommendation Manager',
        1,
        0,
        0,
        0,
        0,
        0,
        '2025-07-09 21:53:22',
        '2025-04-21 14:45:20',
        1,
        1073,
        NULL,
        'Branch and Children'
    ),
    (
        1076,
        'Manage Officers And Deputies Under Me',
        1,
        0,
        0,
        0,
        0,
        0,
        '2025-04-21 22:47:04',
        '2025-04-21 22:47:04',
        1,
        1,
        NULL,
        'Branch and Children'
    ),
    (
        1077,
        'Can Do All the Thingz',
        1,
        0,
        0,
        0,
        0,
        0,
        '2025-05-14 23:44:04',
        '2025-05-14 23:44:04',
        1,
        1,
        NULL,
        'Global'
    );
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `phinxlog`
--

LOCK TABLES `phinxlog` WRITE;
/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */
;
INSERT INTO
    `phinxlog`
VALUES (
        20230511170042,
        'Init',
        '2024-09-29 15:47:02',
        '2024-09-29 15:47:03',
        0
    ),
    (
        20241001141705,
        'AddViewMembersPermission',
        '2024-10-02 22:37:15',
        '2024-10-02 22:37:15',
        0
    ),
    (
        20241009145957,
        'AddTitlePronounsPronunciationToMembers',
        '2024-10-31 23:13:10',
        '2024-10-31 23:13:10',
        0
    ),
    (
        20241024125311,
        'ChangeAppSettingValueToText',
        '2024-10-31 23:13:10',
        '2024-10-31 23:13:10',
        0
    ),
    (
        20241204160759,
        'Warrants',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:18',
        0
    ),
    (
        20241207172311,
        'AddWarrantableToMembers',
        '2025-01-12 01:02:18',
        '2025-01-12 01:02:22',
        0
    ),
    (
        20241225192403,
        'RefactorAgnosticJoinFields',
        '2025-01-12 01:02:22',
        '2025-01-12 01:02:22',
        0
    ),
    (
        20241231164137,
        'AddTypeToBranches',
        '2025-01-12 01:02:22',
        '2025-01-12 01:02:22',
        0
    ),
    (
        20250108190610,
        'AddRequiredToAppSetting',
        '2025-01-12 01:02:22',
        '2025-01-12 01:02:22',
        0
    ),
    (
        20250227173909,
        'AddScopeToMemberRoles',
        '2025-03-01 14:24:26',
        '2025-03-01 14:24:26',
        0
    ),
    (
        20250227230531,
        'AddDomainToBranch',
        '2025-03-01 14:24:26',
        '2025-03-01 14:24:26',
        0
    ),
    (
        20250328010857,
        'PermissionPolicies',
        '2025-04-10 19:40:02',
        '2025-04-10 19:40:02',
        0
    ),
    (
        20250415203922,
        'ConvertAppSettingsToSingleRecord',
        '2025-04-21 14:17:19',
        '2025-04-21 14:17:19',
        0
    );
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `queue_phinxlog`
--

DROP TABLE IF EXISTS `queue_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `queue_phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `queue_phinxlog`
--

LOCK TABLES `queue_phinxlog` WRITE;
/*!40000 ALTER TABLE `queue_phinxlog` DISABLE KEYS */
;
INSERT INTO
    `queue_phinxlog`
VALUES (
        20240307154751,
        'MigrationQueueInitV8',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        0
    ),
    (
        20250129194018,
        'AddQueueEngineManagerPermission',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        0
    );
/*!40000 ALTER TABLE `queue_phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `queue_processes`
--

DROP TABLE IF EXISTS `queue_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
    UNIQUE KEY `pid` (`pid`, `server`)
) ENGINE = InnoDB AUTO_INCREMENT = 44641 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `queue_processes`
--

LOCK TABLES `queue_processes` WRITE;
/*!40000 ALTER TABLE `queue_processes` DISABLE KEYS */
;
INSERT INTO
    `queue_processes`
VALUES (
        44640,
        '2978278',
        '2025-08-31 14:56:01',
        '2025-08-31 14:56:31',
        0,
        'iad1-shared-b8-16',
        'd04075728ec829f4fce72fbb246bf520e908ce00'
    );
/*!40000 ALTER TABLE `queue_processes` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `queued_jobs`
--

DROP TABLE IF EXISTS `queued_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 771 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `queued_jobs`
--

LOCK TABLES `queued_jobs` WRITE;
/*!40000 ALTER TABLE `queued_jobs` DISABLE KEYS */
;
/*!40000 ALTER TABLE `queued_jobs` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 1119 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */
;
INSERT INTO
    `roles`
VALUES (
        1,
        'Admin',
        1,
        NULL,
        '2024-09-29 15:47:03',
        1,
        NULL,
        NULL
    ),
    (
        10,
        'System Administrator',
        0,
        '2025-05-14 23:45:00',
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        20,
        'Site Secretary',
        0,
        '2025-01-14 05:46:42',
        '2024-09-29 15:47:04',
        1,
        840,
        NULL
    ),
    (
        30,
        'Awards Secretary',
        0,
        '2024-09-29 16:55:07',
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        100,
        'Ansteorran Crown',
        0,
        '2025-01-12 01:06:09',
        '2024-09-29 15:47:04',
        1,
        1096,
        NULL
    ),
    (
        110,
        'Ansteorran Coronet',
        0,
        '2024-10-08 02:14:42',
        '2024-09-29 15:47:04',
        1,
        1096,
        NULL
    ),
    (
        200,
        'Vindheim Coronet',
        0,
        '2025-06-22 00:53:40',
        '2024-09-29 15:47:04',
        1,
        2866,
        NULL
    ),
    (
        1001,
        'Armored Combat Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1002,
        'Rapier Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1003,
        'Cut & Thrust Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1004,
        'Target Archery Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1005,
        'Equestrian Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1006,
        'Youth Rapier Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1007,
        'Youth Armored Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1008,
        'Siege Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1009,
        'Rapier Spear Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1010,
        'Thrown Weapons Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1011,
        'Combat Archery Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1012,
        'Two Handed Cut & Thrust Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1013,
        'Wooden Lance Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1014,
        'Reduced Armor Experiment Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1015,
        'C&T - Historic Combat Experiment Authorizer',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1101,
        'Armored Combat Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1102,
        'Rapier Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1103,
        'Cut & Thrust Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1104,
        'Missile Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1105,
        'Equestrian Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1106,
        'Youth Rapier Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1107,
        'Youth Armored Authorizing Marshal Manager',
        0,
        NULL,
        '2024-09-29 15:47:04',
        1,
        1,
        NULL
    ),
    (
        1108,
        'Kingdom Earl Marshal',
        0,
        '2025-01-12 17:18:57',
        '2024-09-29 17:00:20',
        1,
        1096,
        NULL
    ),
    (
        1109,
        'Kingdom Rapier Marshal',
        0,
        '2024-09-29 17:03:02',
        '2024-09-29 17:03:02',
        1,
        1,
        NULL
    ),
    (
        1110,
        'Kingdom Missile Marshal',
        0,
        '2024-09-29 17:04:30',
        '2024-09-29 17:04:30',
        1,
        1,
        NULL
    ),
    (
        1111,
        'Kingdom Armored Marshal',
        0,
        '2024-09-29 17:05:51',
        '2024-09-29 17:05:51',
        1,
        1,
        NULL
    ),
    (
        1112,
        'Kingdom Equestrian Marshal',
        0,
        '2024-09-29 17:07:19',
        '2024-09-29 17:07:19',
        1,
        1,
        NULL
    ),
    (
        1113,
        'Kingdom Youth Armored Marshal',
        0,
        '2024-09-29 17:08:57',
        '2024-09-29 17:08:57',
        1,
        1,
        NULL
    ),
    (
        1114,
        'Kingdom Youth Rapier Marshal',
        0,
        '2024-09-29 17:09:28',
        '2024-09-29 17:09:28',
        1,
        1,
        NULL
    ),
    (
        1115,
        'Thrown Weapons Authorizing Marshal Manager',
        0,
        '2024-12-06 16:29:24',
        '2024-12-06 16:29:24',
        1,
        1096,
        NULL
    ),
    (
        1116,
        'Greater Officer of State',
        0,
        '2025-06-22 20:20:25',
        '2025-01-12 17:17:18',
        1,
        1096,
        NULL
    ),
    (
        1117,
        'Local Landed Crown Repersenative',
        0,
        '2025-04-21 14:50:45',
        '2025-04-21 14:50:45',
        1,
        1,
        NULL
    ),
    (
        1118,
        'Regional Officer Management',
        0,
        '2025-04-21 22:57:04',
        '2025-04-21 22:46:12',
        1,
        1,
        NULL
    );
/*!40000 ALTER TABLE `roles` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `roles_permissions`
--

DROP TABLE IF EXISTS `roles_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 350 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `roles_permissions`
--

LOCK TABLES `roles_permissions` WRITE;
/*!40000 ALTER TABLE `roles_permissions` DISABLE KEYS */
;
INSERT INTO
    `roles_permissions`
VALUES (
        1,
        1,
        1,
        '2024-09-29 15:47:03',
        1
    ),
    (
        2,
        1001,
        1001,
        '2024-09-29 15:47:04',
        1
    ),
    (
        3,
        1002,
        1001,
        '2024-09-29 15:47:04',
        1
    ),
    (
        4,
        1003,
        1001,
        '2024-09-29 15:47:04',
        1
    ),
    (
        5,
        1004,
        1002,
        '2024-09-29 15:47:04',
        1
    ),
    (
        6,
        1005,
        1002,
        '2024-09-29 15:47:04',
        1
    ),
    (
        7,
        1006,
        1003,
        '2024-09-29 15:47:04',
        1
    ),
    (
        8,
        1007,
        1009,
        '2024-09-29 15:47:04',
        1
    ),
    (
        9,
        1008,
        1008,
        '2024-09-29 15:47:04',
        1
    ),
    (
        10,
        1009,
        1008,
        '2024-09-29 15:47:04',
        1
    ),
    (
        11,
        1010,
        1011,
        '2024-09-29 15:47:04',
        1
    ),
    (
        12,
        1011,
        1011,
        '2024-09-29 15:47:04',
        1
    ),
    (
        13,
        1012,
        1011,
        '2024-09-29 15:47:04',
        1
    ),
    (
        14,
        1013,
        1004,
        '2024-09-29 15:47:04',
        1
    ),
    (
        15,
        1014,
        1010,
        '2024-09-29 15:47:04',
        1
    ),
    (
        16,
        1015,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        17,
        1016,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        18,
        1017,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        19,
        1018,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        20,
        1019,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        21,
        1020,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        22,
        1021,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        23,
        1022,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        24,
        1023,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        25,
        1024,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        26,
        1025,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        27,
        1026,
        1007,
        '2024-09-29 15:47:04',
        1
    ),
    (
        28,
        1027,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        29,
        1028,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        30,
        1029,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        31,
        1030,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        32,
        1031,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        33,
        1032,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        34,
        1033,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        35,
        1034,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        36,
        1035,
        1006,
        '2024-09-29 15:47:04',
        1
    ),
    (
        37,
        1037,
        1003,
        '2024-09-29 15:47:04',
        1
    ),
    (
        38,
        1038,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        39,
        1039,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        40,
        1040,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        41,
        1041,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        42,
        1042,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        43,
        1043,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        44,
        1044,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        45,
        1045,
        1005,
        '2024-09-29 15:47:04',
        1
    ),
    (
        46,
        1050,
        1003,
        '2024-09-29 15:47:04',
        1
    ),
    (
        47,
        1051,
        1014,
        '2024-09-29 15:47:04',
        1
    ),
    (
        48,
        1052,
        1101,
        '2024-09-29 15:47:04',
        1
    ),
    (
        49,
        1053,
        1102,
        '2024-09-29 15:47:04',
        1
    ),
    (
        50,
        1054,
        1104,
        '2024-09-29 15:47:04',
        1
    ),
    (
        51,
        1055,
        1103,
        '2024-09-29 15:47:04',
        1
    ),
    (
        52,
        1056,
        1105,
        '2024-09-29 15:47:04',
        1
    ),
    (
        53,
        1057,
        1107,
        '2024-09-29 15:47:04',
        1
    ),
    (
        54,
        1058,
        1106,
        '2024-09-29 15:47:04',
        1
    ),
    (
        55,
        1059,
        1104,
        '2024-09-29 15:47:04',
        1
    ),
    (
        56,
        1060,
        1102,
        '2024-09-29 15:47:04',
        1
    ),
    (
        57,
        1061,
        1104,
        '2024-09-29 15:47:04',
        1
    ),
    (
        58,
        1062,
        1104,
        '2024-09-29 15:47:04',
        1
    ),
    (
        59,
        1063,
        1103,
        '2024-09-29 15:47:04',
        1
    ),
    (
        60,
        1064,
        1105,
        '2024-09-29 15:47:04',
        1
    ),
    (
        61,
        1065,
        1102,
        '2024-09-29 15:47:04',
        1
    ),
    (
        62,
        1066,
        1103,
        '2024-09-29 15:47:04',
        1
    ),
    (
        63,
        31,
        100,
        '2024-09-29 15:47:04',
        1
    ),
    (
        64,
        33,
        100,
        '2024-09-29 15:47:04',
        1
    ),
    (
        65,
        31,
        30,
        '2024-09-29 15:47:04',
        1
    ),
    (
        66,
        4,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        67,
        6,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        68,
        7,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        69,
        31,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        70,
        11,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        71,
        12,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        72,
        13,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        73,
        14,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        74,
        21,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        75,
        22,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        76,
        23,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        77,
        24,
        20,
        '2024-09-29 15:47:04',
        1
    ),
    (
        78,
        2,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        79,
        3,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        80,
        4,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        81,
        5,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        82,
        6,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        83,
        7,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        84,
        11,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        85,
        12,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        86,
        13,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        87,
        14,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        88,
        21,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        89,
        22,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        90,
        23,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        91,
        24,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        92,
        31,
        10,
        '2024-09-29 15:47:04',
        1
    ),
    (
        157,
        32,
        110,
        '2024-09-29 16:50:33',
        1
    ),
    (
        158,
        32,
        100,
        '2024-09-29 16:50:57',
        1
    ),
    (
        159,
        7,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        160,
        12,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        161,
        13,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        162,
        14,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        163,
        1001,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        164,
        1002,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        165,
        1003,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        166,
        1004,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        167,
        1005,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        168,
        1006,
        1108,
        '2024-09-29 17:00:20',
        1
    ),
    (
        169,
        1007,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        170,
        1008,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        171,
        1009,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        172,
        1010,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        173,
        1011,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        174,
        1012,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        175,
        1013,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        176,
        1014,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        177,
        1015,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        178,
        1016,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        179,
        1017,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        180,
        1018,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        181,
        1019,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        182,
        1020,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        183,
        1021,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        184,
        1022,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        185,
        1023,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        186,
        1024,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        187,
        1025,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        188,
        1026,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        189,
        1027,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        190,
        1028,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        191,
        1029,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        192,
        1030,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        193,
        1031,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        194,
        1032,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        195,
        1033,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        196,
        1034,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        197,
        1035,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        198,
        1036,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        199,
        1037,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        200,
        1038,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        201,
        1039,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        202,
        1040,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        203,
        1041,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        204,
        1042,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        205,
        1043,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        206,
        1044,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        207,
        1045,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        208,
        1050,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        209,
        1051,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        210,
        1052,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        211,
        1053,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        212,
        1054,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        213,
        1055,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        214,
        1056,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        215,
        1057,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        216,
        1058,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        217,
        1059,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        218,
        1060,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        219,
        1061,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        220,
        1062,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        221,
        1063,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        222,
        1064,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        223,
        1065,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        224,
        1066,
        1108,
        '2024-09-29 17:00:21',
        1
    ),
    (
        225,
        7,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        226,
        14,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        227,
        1004,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        228,
        1005,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        229,
        1006,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        230,
        1007,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        231,
        1037,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        232,
        1050,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        233,
        1051,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        234,
        1053,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        235,
        1055,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        236,
        1060,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        237,
        1063,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        238,
        1065,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        239,
        1066,
        1109,
        '2024-09-29 17:03:02',
        1
    ),
    (
        240,
        7,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        241,
        14,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        242,
        1008,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        243,
        1009,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        244,
        1010,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        245,
        1011,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        246,
        1012,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        247,
        1013,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        248,
        1014,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        249,
        1054,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        250,
        1059,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        251,
        1061,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        252,
        1062,
        1110,
        '2024-09-29 17:04:30',
        1
    ),
    (
        253,
        7,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        254,
        14,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        255,
        1001,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        256,
        1002,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        257,
        1003,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        258,
        1052,
        1111,
        '2024-09-29 17:05:51',
        1
    ),
    (
        259,
        7,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        260,
        14,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        261,
        1038,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        262,
        1039,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        263,
        1040,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        264,
        1041,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        265,
        1042,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        266,
        1043,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        267,
        1044,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        268,
        1045,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        269,
        1056,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        270,
        1064,
        1112,
        '2024-09-29 17:07:19',
        1
    ),
    (
        271,
        7,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        272,
        14,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        273,
        1015,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        274,
        1016,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        275,
        1017,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        276,
        1018,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        277,
        1019,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        278,
        1020,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        279,
        1021,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        280,
        1022,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        281,
        1023,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        282,
        1024,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        283,
        1025,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        284,
        1026,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        285,
        1057,
        1113,
        '2024-09-29 17:08:57',
        1
    ),
    (
        286,
        1027,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        287,
        1028,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        288,
        1029,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        289,
        1030,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        290,
        1031,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        291,
        1032,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        292,
        1033,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        293,
        1034,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        294,
        1035,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        295,
        1058,
        1114,
        '2024-09-29 17:09:28',
        1
    ),
    (
        296,
        8,
        110,
        '2024-10-02 22:37:59',
        1
    ),
    (
        297,
        8,
        100,
        '2024-10-02 22:38:17',
        1
    ),
    (
        298,
        7,
        100,
        '2024-10-08 02:12:14',
        1
    ),
    (
        299,
        7,
        110,
        '2024-10-08 02:12:45',
        1
    ),
    (
        300,
        14,
        110,
        '2024-10-08 02:13:02',
        1
    ),
    (
        301,
        24,
        100,
        '2024-10-08 02:13:28',
        1
    ),
    (
        302,
        14,
        100,
        '2024-10-08 02:13:36',
        1
    ),
    (
        303,
        24,
        110,
        '2024-10-08 02:14:42',
        1
    ),
    (
        308,
        2,
        20,
        '2024-12-06 16:25:34',
        1
    ),
    (
        309,
        1061,
        1115,
        '2024-12-06 16:29:24',
        1
    ),
    (
        310,
        1067,
        10,
        '2025-01-12 01:04:41',
        1
    ),
    (
        311,
        1070,
        10,
        '2025-01-12 01:04:49',
        1
    ),
    (
        312,
        1071,
        10,
        '2025-01-12 01:04:57',
        1
    ),
    (
        313,
        1072,
        10,
        '2025-01-12 01:05:05',
        1
    ),
    (
        314,
        1067,
        100,
        '2025-01-12 01:05:41',
        1
    ),
    (
        315,
        1068,
        100,
        '2025-01-12 01:05:52',
        1
    ),
    (
        316,
        1069,
        100,
        '2025-01-12 01:05:56',
        1
    ),
    (
        317,
        1070,
        100,
        '2025-01-12 01:06:00',
        1
    ),
    (
        318,
        1071,
        100,
        '2025-01-12 01:06:04',
        1
    ),
    (
        319,
        1072,
        100,
        '2025-01-12 01:06:09',
        1
    ),
    (
        320,
        8,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        321,
        1067,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        322,
        1069,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        323,
        1070,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        324,
        1071,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        325,
        1072,
        1116,
        '2025-01-12 17:17:18',
        1
    ),
    (
        326,
        8,
        1108,
        '2025-01-12 17:18:34',
        1
    ),
    (
        327,
        1067,
        1108,
        '2025-01-12 17:18:40',
        1
    ),
    (
        328,
        1069,
        1108,
        '2025-01-12 17:18:45',
        1
    ),
    (
        329,
        1070,
        1108,
        '2025-01-12 17:18:48',
        1
    ),
    (
        330,
        1071,
        1108,
        '2025-01-12 17:18:53',
        1
    ),
    (
        331,
        1072,
        1108,
        '2025-01-12 17:18:57',
        1
    ),
    (
        332,
        1068,
        10,
        '2025-01-12 17:47:00',
        1
    ),
    (
        333,
        1070,
        20,
        '2025-01-14 05:46:10',
        1
    ),
    (
        334,
        1071,
        20,
        '2025-01-14 05:46:32',
        1
    ),
    (
        335,
        1072,
        20,
        '2025-01-14 05:46:42',
        1
    ),
    (
        336,
        1073,
        10,
        '2025-02-03 14:37:17',
        1
    ),
    (
        337,
        1075,
        1117,
        '2025-04-21 14:50:45',
        1
    ),
    (
        338,
        1076,
        1118,
        '2025-04-21 22:57:04',
        1
    ),
    (
        339,
        8,
        10,
        '2025-05-14 23:40:55',
        1
    ),
    (
        340,
        32,
        10,
        '2025-05-14 23:41:00',
        1
    ),
    (
        341,
        33,
        10,
        '2025-05-14 23:41:03',
        1
    ),
    (
        342,
        1069,
        10,
        '2025-05-14 23:41:12',
        1
    ),
    (
        343,
        1074,
        10,
        '2025-05-14 23:41:17',
        1
    ),
    (
        344,
        1075,
        10,
        '2025-05-14 23:41:25',
        1
    ),
    (
        345,
        1076,
        10,
        '2025-05-14 23:41:29',
        1
    ),
    (
        346,
        1077,
        10,
        '2025-05-14 23:45:00',
        1
    ),
    (
        348,
        1075,
        200,
        '2025-06-22 00:53:40',
        1
    ),
    (
        349,
        7,
        1116,
        '2025-06-22 20:20:25',
        1
    );
/*!40000 ALTER TABLE `roles_permissions` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `tokens`
--

LOCK TABLES `tokens` WRITE;
/*!40000 ALTER TABLE `tokens` DISABLE KEYS */
;
/*!40000 ALTER TABLE `tokens` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `tools_phinxlog`
--

DROP TABLE IF EXISTS `tools_phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `tools_phinxlog` (
    `version` bigint(20) NOT NULL,
    `migration_name` varchar(100) DEFAULT NULL,
    `start_time` timestamp NULL DEFAULT NULL,
    `end_time` timestamp NULL DEFAULT NULL,
    `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `tools_phinxlog`
--

LOCK TABLES `tools_phinxlog` WRITE;
/*!40000 ALTER TABLE `tools_phinxlog` DISABLE KEYS */
;
INSERT INTO
    `tools_phinxlog`
VALUES (
        20200430170235,
        'MigrationToolsTokens',
        '2025-02-03 14:35:12',
        '2025-02-03 14:35:12',
        0
    );
/*!40000 ALTER TABLE `tools_phinxlog` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `warrant_periods`
--

DROP TABLE IF EXISTS `warrant_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
CREATE TABLE `warrant_periods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `created` datetime NOT NULL,
    `created_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 6 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `warrant_periods`
--

LOCK TABLES `warrant_periods` WRITE;
/*!40000 ALTER TABLE `warrant_periods` DISABLE KEYS */
;
INSERT INTO
    `warrant_periods`
VALUES (
        2,
        '2022-01-01',
        '2025-01-18',
        '2025-01-12 17:55:25',
        1
    ),
    (
        3,
        '2025-01-17',
        '2025-07-21',
        '2025-01-12 18:03:18',
        1
    ),
    (
        5,
        '2025-07-19',
        '2026-01-19',
        '2025-07-15 23:15:02',
        1
    );
/*!40000 ALTER TABLE `warrant_periods` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `warrant_roster_approvals`
--

DROP TABLE IF EXISTS `warrant_roster_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 605 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `warrant_roster_approvals`
--

LOCK TABLES `warrant_roster_approvals` WRITE;
/*!40000 ALTER TABLE `warrant_roster_approvals` DISABLE KEYS */
;
INSERT INTO
    `warrant_roster_approvals`
VALUES (
        1,
        1,
        1,
        '2025-01-12 01:02:18'
    );
/*!40000 ALTER TABLE `warrant_roster_approvals` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `warrant_rosters`
--

DROP TABLE IF EXISTS `warrant_rosters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 418 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `warrant_rosters`
--

LOCK TABLES `warrant_rosters` WRITE;
/*!40000 ALTER TABLE `warrant_rosters` DISABLE KEYS */
;
INSERT INTO
    `warrant_rosters`
VALUES (
        1,
        'System Admin Warrant Set',
        1,
        1,
        'Approved',
        NULL,
        '2025-01-12 01:02:18',
        1,
        NULL
    ),
    (
        395,
        'Kingdom MoAS : Eirik Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 19:41:16',
        '2025-06-22 19:38:59',
        1,
        1073
    ),
    (
        396,
        'Regional MoAS : Devon Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 19:51:44',
        '2025-06-22 19:51:00',
        1,
        1073
    ),
    (
        397,
        'Regional MoAS : Devon Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 20:13:22',
        '2025-06-22 20:12:55',
        1,
        1073
    ),
    (
        398,
        'Local MoAS : Bryce Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 20:35:39',
        '2025-06-22 20:33:41',
        1,
        1073
    ),
    (
        399,
        'Local MoAS : Agatha Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 20:35:44',
        '2025-06-22 20:34:23',
        1,
        1073
    ),
    (
        400,
        'Regional MoAS : Devon Demoer',
        1,
        1,
        'Approved',
        '2025-06-22 20:35:47',
        '2025-06-22 20:35:28',
        1,
        1073
    ),
    (
        401,
        'Arts & Sciences roster for 2025-01-17 ~ 2025-07-21',
        1,
        1,
        'Approved',
        '2025-08-27 23:22:50',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        402,
        'Kingdom MoAS Deputy : Haylee Demoer',
        1,
        1,
        'Approved',
        '2025-08-07 21:09:42',
        '2025-06-25 02:32:54',
        1,
        1073
    ),
    (
        404,
        'Regional Rapier Marshal : Leonard Demoer',
        1,
        1,
        'Approved',
        '2025-08-07 21:09:34',
        '2025-08-07 21:09:06',
        1,
        1073
    ),
    (
        405,
        'Kingdom Rapier Marshal : Leonard Demoer',
        1,
        1,
        'Approved',
        '2025-08-07 21:11:03',
        '2025-08-07 21:10:56',
        1,
        1073
    ),
    (
        411,
        'Local Seneschal : Bryce Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:08:09',
        '2025-08-30 21:08:09',
        1,
        1073
    ),
    (
        412,
        'Regional Seneschal : Caroline Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:09:16',
        '2025-08-30 21:09:16',
        1,
        1073
    ),
    (
        413,
        'Regional Armored Marshal : Devon Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:10:12',
        '2025-08-30 21:10:12',
        1,
        1073
    ),
    (
        414,
        'Kingdom Seneschal : Eirik Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:11:04',
        '2025-08-30 21:11:04',
        1,
        1073
    ),
    (
        415,
        'Kingdom Rapier Marshal : Garun Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:12:12',
        '2025-08-30 21:12:12',
        1,
        1073
    ),
    (
        416,
        'Local Treasurer : Mel Local Exch and Kingdom Social Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:31:13',
        '2025-08-30 21:31:13',
        1,
        1073
    ),
    (
        417,
        'Kingdom Social Media Officer : Mel Local Exch and Kingdom Social Demoer',
        1,
        NULL,
        'Pending',
        '2025-08-30 21:31:30',
        '2025-08-30 21:31:30',
        1,
        1073
    );
/*!40000 ALTER TABLE `warrant_rosters` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Table structure for table `warrants`
--

DROP TABLE IF EXISTS `warrants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */
;
/*!40101 SET character_set_client = utf8mb4 */
;
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
) ENGINE = InnoDB AUTO_INCREMENT = 2512 DEFAULT CHARSET = utf8mb3 COLLATE = utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */
;

--
-- Dumping data for table `warrants`
--

LOCK TABLES `warrants` WRITE;
/*!40000 ALTER TABLE `warrants` DISABLE KEYS */
;
INSERT INTO
    `warrants`
VALUES (
        1,
        'System Admin Warrant',
        1,
        1,
        'Direct Grant',
        -1,
        1,
        '2100-10-10 00:00:00',
        '2020-01-01 00:00:00',
        '2020-01-01 00:00:00',
        'Current',
        NULL,
        NULL,
        NULL,
        '2025-01-12 01:02:18',
        1,
        NULL
    ),
    (
        2435,
        'Hiring Warrant: Ansteorra - Kingdom MoAS',
        2875,
        395,
        'Officers.Officers',
        928,
        362,
        '2025-08-30 21:10:50',
        '2025-06-22 19:41:16',
        '2025-06-22 19:41:16',
        'Deactivated',
        'cleaning up demo users',
        1073,
        '2025-08-30 21:10:50',
        '2025-06-22 19:38:59',
        1,
        1073
    ),
    (
        2436,
        'Hiring Warrant: Central Region - Regional MoAS',
        2874,
        396,
        'Officers.Officers',
        929,
        NULL,
        '2025-06-22 20:12:37',
        '2025-06-22 19:51:44',
        '2025-06-22 19:51:44',
        'Deactivated',
        'test release',
        2875,
        '2025-06-22 20:12:37',
        '2025-06-22 19:51:00',
        1,
        2875
    ),
    (
        2437,
        'Hiring Warrant: Central Region - Regional MoAS',
        2874,
        397,
        'Officers.Officers',
        930,
        363,
        '2025-08-30 21:10:03',
        '2025-06-22 20:13:22',
        '2025-06-22 20:13:22',
        'Deactivated',
        'cleaning up demo users',
        1073,
        '2025-08-30 21:10:03',
        '2025-06-22 20:12:55',
        1,
        1073
    ),
    (
        2438,
        'Hiring Warrant: Barony of Bonwicke - Local MoAS',
        2872,
        398,
        'Officers.Officers',
        931,
        NULL,
        '2025-08-30 21:06:58',
        '2025-06-22 20:35:39',
        '2025-06-22 20:35:39',
        'Deactivated',
        'cleaning up demo users',
        1073,
        '2025-08-30 21:06:58',
        '2025-06-22 20:33:41',
        1,
        1073
    ),
    (
        2439,
        'Hiring Warrant: Shire of Graywood - Local MoAS',
        2871,
        399,
        'Officers.Officers',
        932,
        NULL,
        '2025-07-21 00:00:00',
        '2025-06-22 20:35:44',
        '2025-06-22 20:35:44',
        'Expired',
        '',
        NULL,
        '2025-08-07 21:09:06',
        '2025-06-22 20:34:23',
        1,
        2875
    ),
    (
        2440,
        'Hiring Warrant: Southern Region - Regional MoAS',
        2874,
        400,
        'Officers.Officers',
        933,
        364,
        '2025-07-21 00:00:00',
        '2025-06-22 20:35:47',
        '2025-06-22 20:35:47',
        'Expired',
        '',
        NULL,
        '2025-08-07 21:09:06',
        '2025-06-22 20:35:28',
        1,
        2875
    ),
    (
        2470,
        'Renewal: Ansteorra Kingdom MoAS',
        2875,
        401,
        'Officers.Officers',
        928,
        362,
        '2025-07-21 00:00:00',
        '2025-08-27 23:22:50',
        '2025-08-27 23:22:50',
        'Expired',
        '',
        NULL,
        '2025-08-27 23:23:04',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        2471,
        'Renewal: Central Region Regional MoAS',
        2874,
        401,
        'Officers.Officers',
        930,
        363,
        '2025-07-21 00:00:00',
        '2025-08-27 23:22:50',
        '2025-08-27 23:22:50',
        'Expired',
        '',
        NULL,
        '2025-08-27 23:23:04',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        2472,
        'Renewal: Barony of Bonwicke Local MoAS',
        2872,
        401,
        'Officers.Officers',
        931,
        NULL,
        '2025-07-21 00:00:00',
        '2025-08-27 23:22:50',
        '2025-08-27 23:22:50',
        'Expired',
        '',
        NULL,
        '2025-08-27 23:23:04',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        2473,
        'Renewal: Shire of Graywood Local MoAS',
        2871,
        401,
        'Officers.Officers',
        932,
        NULL,
        '2025-07-21 00:00:00',
        '2025-08-27 23:22:50',
        '2025-08-27 23:22:50',
        'Expired',
        '',
        NULL,
        '2025-08-27 23:23:04',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        2474,
        'Renewal: Southern Region Regional MoAS',
        2874,
        401,
        'Officers.Officers',
        933,
        364,
        '2025-07-21 00:00:00',
        '2025-08-27 23:22:50',
        '2025-08-27 23:22:50',
        'Expired',
        '',
        NULL,
        '2025-08-27 23:23:04',
        '2025-06-22 20:51:50',
        1,
        669
    ),
    (
        2475,
        'Hiring Warrant: Ansteorra - Kingdom MoAS Deputy (Demoer Deputy)',
        2877,
        402,
        'Officers.Officers',
        934,
        NULL,
        '2025-07-21 00:00:00',
        '2025-08-07 21:09:42',
        '2025-08-07 21:09:42',
        'Expired',
        '',
        NULL,
        '2025-08-07 21:10:56',
        '2025-06-25 02:32:54',
        1,
        1073
    ),
    (
        2500,
        'Hiring Warrant: Vindheim - Regional Rapier Marshal',
        2882,
        404,
        'Officers.Officers',
        942,
        NULL,
        '2025-08-30 21:14:38',
        '2025-08-07 21:09:34',
        '2025-08-07 21:09:34',
        'Deactivated',
        'cleaning up demo users',
        1073,
        '2025-08-30 21:14:38',
        '2025-08-07 21:09:06',
        1,
        1073
    ),
    (
        2501,
        'Hiring Warrant: Ansteorra - Kingdom Rapier Marshal',
        2882,
        405,
        'Officers.Officers',
        943,
        372,
        '2025-08-30 21:12:12',
        '2025-08-07 21:11:03',
        '2025-08-07 21:11:03',
        'Deactivated',
        'Replaced by new officer',
        1073,
        '2025-08-30 21:12:12',
        '2025-08-07 21:10:56',
        1,
        1073
    ),
    (
        2505,
        'Hiring Warrant: Barony of Stargate - Local Seneschal',
        2872,
        411,
        'Officers.Officers',
        949,
        374,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:08:09',
        '2025-08-30 21:08:09',
        1,
        1073
    ),
    (
        2506,
        'Hiring Warrant: Central Region - Regional Seneschal',
        2873,
        412,
        'Officers.Officers',
        950,
        375,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:09:16',
        '2025-08-30 21:09:16',
        1,
        1073
    ),
    (
        2507,
        'Hiring Warrant: Central Region - Regional Armored Marshal',
        2874,
        413,
        'Officers.Officers',
        951,
        NULL,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:10:12',
        '2025-08-30 21:10:12',
        1,
        1073
    ),
    (
        2508,
        'Hiring Warrant: Ansteorra - Kingdom Seneschal',
        2875,
        414,
        'Officers.Officers',
        952,
        376,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:11:04',
        '2025-08-30 21:11:04',
        1,
        1073
    ),
    (
        2509,
        'Hiring Warrant: Ansteorra - Kingdom Rapier Marshal',
        2876,
        415,
        'Officers.Officers',
        953,
        377,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:12:12',
        '2025-08-30 21:12:12',
        1,
        1073
    ),
    (
        2510,
        'Hiring Warrant: Shire of Seawinds - Local Treasurer',
        2883,
        416,
        'Officers.Officers',
        956,
        NULL,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:31:13',
        '2025-08-30 21:31:13',
        1,
        1073
    ),
    (
        2511,
        'Hiring Warrant: Ansteorra - Kingdom Social Media Officer',
        2883,
        417,
        'Officers.Officers',
        957,
        NULL,
        '2026-01-19 00:00:00',
        '2025-08-30 00:00:00',
        NULL,
        'Pending',
        '',
        NULL,
        '2025-08-30 21:31:30',
        '2025-08-30 21:31:30',
        1,
        1073
    );
/*!40000 ALTER TABLE `warrants` ENABLE KEYS */
;
UNLOCK TABLES;

--
-- Dumping routines for database 'amp-seed'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */
;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */
;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */
;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */
;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */
;

-- Dump completed on 2025-08-31 20:51:31