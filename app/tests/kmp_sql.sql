/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: KMP_DEV
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
) ENGINE=InnoDB AUTO_INCREMENT=9312 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=9317 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=586 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `awards_recommendations_events`
--

DROP TABLE IF EXISTS `awards_recommendations_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards_recommendations_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recommendation_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `recommendation_id` (`recommendation_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `awards_recommendations_events_ibfk_1` FOREIGN KEY (`recommendation_id`) REFERENCES `awards_recommendations` (`id`),
  CONSTRAINT `awards_recommendations_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `awards_events` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2091 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=1158 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `uploaded_by` int(11) NOT NULL COMMENT 'Member ID who uploaded the document',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_documents_file_path` (`file_path`),
  KEY `idx_documents_entity` (`entity_type`,`entity_id`),
  KEY `idx_documents_checksum` (`checksum`),
  KEY `idx_documents_uploaded_by` (`uploaded_by`),
  KEY `idx_documents_created` (`created`),
  KEY `fk_documents_created_by` (`created_by`),
  KEY `fk_documents_modified_by` (`modified_by`),
  CONSTRAINT `fk_documents_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_documents_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `clonable` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this type can be used as a template for new gatherings',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gatherings`
--

DROP TABLE IF EXISTS `gatherings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gatherings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL COMMENT 'Branch hosting this gathering',
  `gathering_type_id` int(11) NOT NULL COMMENT 'Type of gathering',
  `name` varchar(255) NOT NULL COMMENT 'Name of the gathering',
  `description` text DEFAULT NULL COMMENT 'Description of the gathering',
  `start_date` date NOT NULL COMMENT 'Start date of the gathering',
  `end_date` date NOT NULL COMMENT 'End date of the gathering',
  `location` varchar(255) DEFAULT NULL COMMENT 'Location of the gathering',
  `created_by` int(11) NOT NULL COMMENT 'Member who created this gathering (steward)',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gatherings_branch` (`branch_id`),
  KEY `idx_gatherings_type` (`gathering_type_id`),
  KEY `idx_gatherings_created_by` (`created_by`),
  KEY `idx_gatherings_start_date` (`start_date`),
  KEY `idx_gatherings_end_date` (`end_date`),
  CONSTRAINT `fk_gatherings_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gatherings_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gatherings_type` FOREIGN KEY (`gathering_type_id`) REFERENCES `gathering_types` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=380 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  KEY `deleted` (`deleted`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2884 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=802 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=958 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=782 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=1078 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=44951 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=771 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=1119 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `waivers_gathering_activity_waivers`
--

DROP TABLE IF EXISTS `waivers_gathering_activity_waivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_gathering_activity_waivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gathering_activity_id` int(11) NOT NULL COMMENT 'Gathering activity this waiver requirement applies to',
  `waiver_type_id` int(11) NOT NULL COMMENT 'Type of waiver required for this activity',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_activity_waivers_unique` (`gathering_activity_id`,`waiver_type_id`),
  KEY `idx_gathering_activity_waivers_activity` (`gathering_activity_id`),
  KEY `idx_gathering_activity_waivers_type` (`waiver_type_id`),
  CONSTRAINT `fk_gathering_activity_waivers_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_activity_waivers_type` FOREIGN KEY (`waiver_type_id`) REFERENCES `waivers_waiver_types` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `waivers_gathering_waiver_activities`
--

DROP TABLE IF EXISTS `waivers_gathering_waiver_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `waivers_gathering_waiver_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier',
  `gathering_waiver_id` int(11) NOT NULL COMMENT 'FK to gathering_waivers.id - the waiver document',
  `gathering_activity_id` int(11) NOT NULL COMMENT 'FK to gathering_activities.id - the activity covered',
  `created` datetime NOT NULL COMMENT 'Record creation timestamp',
  `modified` datetime NOT NULL COMMENT 'Last modification timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_waiver_activities_unique` (`gathering_waiver_id`,`gathering_activity_id`),
  KEY `idx_gathering_waiver_activities_waiver` (`gathering_waiver_id`),
  KEY `idx_gathering_waiver_activities_activity` (`gathering_activity_id`),
  CONSTRAINT `fk_gathering_waiver_activities_activity` FOREIGN KEY (`gathering_activity_id`) REFERENCES `gathering_activities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gathering_waiver_activities_waiver` FOREIGN KEY (`gathering_waiver_id`) REFERENCES `waivers_gathering_waivers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `member_id` int(11) DEFAULT NULL COMMENT 'Member who signed the waiver (nullable for anonymous/unknown participants)',
  `document_id` int(11) NOT NULL COMMENT 'Document entity containing the actual waiver file',
  `retention_date` date NOT NULL COMMENT 'Date when this waiver can be deleted (calculated at upload time)',
  `status` varchar(50) NOT NULL DEFAULT 'active' COMMENT 'Status: active, expired, deleted',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about this waiver',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Member who uploaded this waiver',
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_gathering_waivers_document` (`document_id`),
  KEY `idx_gathering_waivers_gathering` (`gathering_id`),
  KEY `idx_gathering_waivers_type` (`waiver_type_id`),
  KEY `idx_gathering_waivers_member` (`member_id`),
  KEY `idx_gathering_waivers_retention` (`retention_date`),
  KEY `idx_gathering_waivers_status` (`status`),
  KEY `idx_gathering_waivers_created` (`created`),
  KEY `idx_gathering_waivers_created_by` (`created_by`),
  KEY `fk_gathering_waivers_modified_by` (`modified_by`),
  CONSTRAINT `fk_gathering_waivers_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_waivers_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_waivers_gathering` FOREIGN KEY (`gathering_id`) REFERENCES `gatherings` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_waivers_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_waivers_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `members` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_gathering_waivers_type` FOREIGN KEY (`waiver_type_id`) REFERENCES `waivers_waiver_types` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_waiver_types_name` (`name`),
  KEY `idx_waiver_types_active` (`is_active`),
  KEY `idx_waiver_types_document_id` (`document_id`),
  CONSTRAINT `waivers_waiver_types_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=605 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=418 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=2512 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-23 12:14:08
