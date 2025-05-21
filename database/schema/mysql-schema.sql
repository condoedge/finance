/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `address_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `address_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `adt_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `address_types_added_by_foreign` (`added_by`),
  KEY `address_types_modified_by_foreign` (`modified_by`),
  KEY `address_types_team_id_foreign` (`team_id`),
  CONSTRAINT `address_types_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `address_types_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `address_types_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `addressable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addressable_id` bigint unsigned DEFAULT NULL,
  `address_type_id` bigint unsigned DEFAULT NULL,
  `type_ad` int DEFAULT NULL,
  `address1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apt_or_suite` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(16,12) DEFAULT NULL,
  `lng` decimal(16,12) DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_ad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_ad` int DEFAULT NULL,
  `default_tax_group_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_added_by_foreign` (`added_by`),
  KEY `addresses_modified_by_foreign` (`modified_by`),
  KEY `addresses_team_id_foreign` (`team_id`),
  KEY `addresses_addressable_type_addressable_id_index` (`addressable_type`,`addressable_id`),
  KEY `addresses_address_type_id_foreign` (`address_type_id`),
  KEY `addresses_default_tax_group_id_foreign` (`default_tax_group_id`),
  CONSTRAINT `addresses_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `addresses_address_type_id_foreign` FOREIGN KEY (`address_type_id`) REFERENCES `address_types` (`id`),
  CONSTRAINT `addresses_default_tax_group_id_foreign` FOREIGN KEY (`default_tax_group_id`) REFERENCES `fin_taxes_groups` (`id`),
  CONSTRAINT `addresses_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `addresses_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_sendings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_sendings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` tinyint DEFAULT NULL,
  `communication_template_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_sendings_added_by_foreign` (`added_by`),
  KEY `communication_sendings_modified_by_foreign` (`modified_by`),
  KEY `communication_sendings_communication_template_id_foreign` (`communication_template_id`),
  CONSTRAINT `communication_sendings_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_sendings_communication_template_id_foreign` FOREIGN KEY (`communication_template_id`) REFERENCES `communication_templates` (`id`),
  CONSTRAINT `communication_sendings_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_template_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_template_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_template_groups_added_by_foreign` (`added_by`),
  KEY `communication_template_groups_modified_by_foreign` (`modified_by`),
  CONSTRAINT `communication_template_groups_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_template_groups_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `type` tinyint DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_draft` tinyint DEFAULT NULL,
  `extra` json DEFAULT NULL,
  `template_group_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_templates_added_by_foreign` (`added_by`),
  KEY `communication_templates_modified_by_foreign` (`modified_by`),
  KEY `communication_templates_template_group_id_foreign` (`template_group_id`),
  CONSTRAINT `communication_templates_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_templates_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_templates_template_group_id_foreign` FOREIGN KEY (`template_group_id`) REFERENCES `communication_template_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `redirect_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `email_requests_added_by_foreign` (`added_by`),
  KEY `email_requests_modified_by_foreign` (`modified_by`),
  CONSTRAINT `email_requests_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `email_requests_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `emailable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emailable_id` bigint unsigned DEFAULT NULL,
  `type_em` int DEFAULT NULL,
  `address_em` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `emails_added_by_foreign` (`added_by`),
  KEY `emails_modified_by_foreign` (`modified_by`),
  KEY `emails_team_id_foreign` (`team_id`),
  KEY `emails_emailable_type_emailable_id_index` (`emailable_type`,`emailable_id`),
  CONSTRAINT `emails_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `emails_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `emails_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `fileable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fileable_id` bigint unsigned DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint DEFAULT NULL,
  `visibility` tinyint NOT NULL DEFAULT '0',
  `subtype` tinyint DEFAULT NULL,
  `disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'local',
  PRIMARY KEY (`id`),
  KEY `files_added_by_foreign` (`added_by`),
  KEY `files_modified_by_foreign` (`modified_by`),
  KEY `files_team_id_foreign` (`team_id`),
  KEY `files_user_id_foreign` (`user_id`),
  KEY `files_fileable_type_fileable_id_index` (`fileable_type`,`fileable_id`),
  CONSTRAINT `files_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `files_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `files_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `files_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_accounts_added_by_foreign` (`added_by`),
  KEY `fin_accounts_modified_by_foreign` (`modified_by`),
  CONSTRAINT `fin_accounts_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_accounts_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_customer_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_customer_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(19,5) NOT NULL,
  `amount_left` decimal(19,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_customer_payments_added_by_foreign` (`added_by`),
  KEY `fin_customer_payments_modified_by_foreign` (`modified_by`),
  KEY `fin_customer_payments_customer_id_foreign` (`customer_id`),
  CONSTRAINT `fin_customer_payments_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_customer_payments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `fin_customers` (`id`),
  CONSTRAINT `fin_customer_payments_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_payment_type_id` tinyint DEFAULT NULL,
  `default_billing_address_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `customable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customable_id` bigint unsigned DEFAULT NULL,
  `customer_due_amount` decimal(19,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_customers_added_by_foreign` (`added_by`),
  KEY `fin_customers_modified_by_foreign` (`modified_by`),
  KEY `fin_customers_team_id_foreign` (`team_id`),
  KEY `fin_customers_customable_type_customable_id_index` (`customable_type`,`customable_id`),
  KEY `fin_customers_default_billing_address_id_foreign` (`default_billing_address_id`),
  CONSTRAINT `fin_customers_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_customers_default_billing_address_id_foreign` FOREIGN KEY (`default_billing_address_id`) REFERENCES `addresses` (`id`),
  CONSTRAINT `fin_customers_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_customers_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `transaction_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `debit_amount` decimal(19,5) NOT NULL,
  `credit_amount` decimal(19,5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_entries_added_by_foreign` (`added_by`),
  KEY `fin_entries_modified_by_foreign` (`modified_by`),
  KEY `fin_entries_transaction_id_foreign` (`transaction_id`),
  KEY `fin_entries_account_id_foreign` (`account_id`),
  CONSTRAINT `fin_entries_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `fin_accounts` (`id`),
  CONSTRAINT `fin_entries_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_entries_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_entries_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `fin_transactions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_historical_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_historical_customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_historical_customers_added_by_foreign` (`added_by`),
  KEY `fin_historical_customers_modified_by_foreign` (`modified_by`),
  KEY `fin_historical_customers_team_id_foreign` (`team_id`),
  KEY `fin_historical_customers_customer_id_foreign` (`customer_id`),
  CONSTRAINT `fin_historical_customers_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_historical_customers_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `fin_customers` (`id`),
  CONSTRAINT `fin_historical_customers_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_historical_customers_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `prevent_modification_fin_historical_customers` BEFORE UPDATE ON `fin_historical_customers` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Modifying fin_historical_customers is not allowed';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `prevent_delete_fin_historical_customers` BEFORE DELETE ON `fin_historical_customers` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Deleting fin_historical_customers is not allowed';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `fin_invoice_applies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice_applies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `apply_date` date NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `applicable_id` bigint unsigned NOT NULL,
  `applicable_type` smallint unsigned NOT NULL,
  `payment_applied_amount` decimal(19,5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_invoice_applies_added_by_foreign` (`added_by`),
  KEY `fin_invoice_applies_modified_by_foreign` (`modified_by`),
  KEY `fin_invoice_applies_invoice_id_foreign` (`invoice_id`),
  KEY `fin_invoice_applicable_index` (`applicable_id`,`applicable_type`),
  CONSTRAINT `fin_invoice_applies_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_applies_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `fin_invoices` (`id`),
  CONSTRAINT `fin_invoice_applies_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_ensure_invoice_payment_integrity` BEFORE INSERT ON `fin_invoice_applies` FOR EACH ROW BEGIN
    DECLARE payment_left DECIMAL(19, 5);
    DECLARE invoice_left DECIMAL(19, 5);
    DECLARE applied_amount DECIMAL(19, 5);

    
    

    if NEW.applicable_type = 1 then
        select calculate_payment_amount_left(NEW.applicable_id) into payment_left;
    end if;
    
    if NEW.applicable_type = 2 then
        select abs(calculate_invoice_due(NEW.applicable_id)) into payment_left;
    end if;

    select calculate_invoice_due(NEW.invoice_id) into invoice_left;

    select get_amount_using_sign_from_invoice(NEW.invoice_id, NEW.payment_applied_amount) into applied_amount;

    if payment_left - applied_amount < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the payment left.';
    end if;

    if invoice_left - applied_amount < 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount exceeds the invoice left.';
    end if;

    if applied_amount = 0 then
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amount must be greater than zero.';
    end if;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `fin_invoice_detail_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice_detail_taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_detail_id` bigint unsigned NOT NULL,
  `tax_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NULL,
  `tax_amount` decimal(19,5) NOT NULL,
  `tax_rate` decimal(19,5) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_invoice_detail_taxes_added_by_foreign` (`added_by`),
  KEY `fin_invoice_detail_taxes_modified_by_foreign` (`modified_by`),
  KEY `fin_invoice_detail_taxes_invoice_detail_id_foreign` (`invoice_detail_id`),
  KEY `fin_invoice_detail_taxes_tax_id_foreign` (`tax_id`),
  KEY `fin_invoice_detail_taxes_account_id_foreign` (`account_id`),
  CONSTRAINT `fin_invoice_detail_taxes_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `fin_accounts` (`id`),
  CONSTRAINT `fin_invoice_detail_taxes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_detail_taxes_invoice_detail_id_foreign` FOREIGN KEY (`invoice_detail_id`) REFERENCES `fin_invoice_details` (`id`),
  CONSTRAINT `fin_invoice_detail_taxes_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_detail_taxes_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `fin_taxes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_invoice_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `revenue_account_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(19,5) NOT NULL,
  `extended_price` decimal(19,5) GENERATED ALWAYS AS ((`quantity` * `unit_price`)) STORED,
  `tax_amount` decimal(19,5) DEFAULT NULL,
  `total_amount` decimal(19,5) GENERATED ALWAYS AS ((`extended_price` + `tax_amount`)) STORED,
  PRIMARY KEY (`id`),
  KEY `fin_invoice_details_added_by_foreign` (`added_by`),
  KEY `fin_invoice_details_modified_by_foreign` (`modified_by`),
  KEY `fin_invoice_details_invoice_id_foreign` (`invoice_id`),
  KEY `fin_invoice_details_revenue_account_id_foreign` (`revenue_account_id`),
  KEY `fin_invoice_details_product_id_foreign` (`product_id`),
  CONSTRAINT `fin_invoice_details_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_details_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `fin_invoices` (`id`),
  CONSTRAINT `fin_invoice_details_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_details_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `fin_products` (`id`),
  CONSTRAINT `fin_invoice_details_revenue_account_id_foreign` FOREIGN KEY (`revenue_account_id`) REFERENCES `fin_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_invoice_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice_statuses` (
  `id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_invoice_statuses_added_by_foreign` (`added_by`),
  KEY `fin_invoice_statuses_modified_by_foreign` (`modified_by`),
  CONSTRAINT `fin_invoice_statuses_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_statuses_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_invoice_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoice_types` (
  `id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sign_multiplier` tinyint NOT NULL DEFAULT '1',
  `next_number` bigint unsigned NOT NULL DEFAULT '1',
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_invoice_types_added_by_foreign` (`added_by`),
  KEY `fin_invoice_types_modified_by_foreign` (`modified_by`),
  CONSTRAINT `fin_invoice_types_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoice_types_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_type_id` bigint unsigned NOT NULL,
  `invoice_number` bigint unsigned NOT NULL,
  `invoice_status_id` bigint unsigned DEFAULT NULL,
  `invoice_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_receivable_id` bigint unsigned NOT NULL,
  `payment_type_id` tinyint NOT NULL,
  `is_draft` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_date` timestamp NOT NULL,
  `invoice_due_date` timestamp NOT NULL,
  `invoice_amount_before_taxes` decimal(19,5) DEFAULT NULL,
  `invoice_total_amount` decimal(19,5) GENERATED ALWAYS AS ((`invoice_tax_amount` + `invoice_amount_before_taxes`)) STORED,
  `invoice_due_amount` decimal(19,5) DEFAULT NULL,
  `invoice_tax_amount` decimal(19,5) DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `historical_customer_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fin_invoices_invoice_type_id_invoice_number_unique` (`invoice_type_id`,`invoice_number`),
  UNIQUE KEY `fin_invoices_invoice_reference_unique` (`invoice_reference`),
  KEY `fin_invoices_added_by_foreign` (`added_by`),
  KEY `fin_invoices_modified_by_foreign` (`modified_by`),
  KEY `fin_invoices_invoice_status_id_foreign` (`invoice_status_id`),
  KEY `fin_invoices_account_receivable_id_foreign` (`account_receivable_id`),
  KEY `fin_invoices_approved_by_foreign` (`approved_by`),
  KEY `fin_invoices_historical_customer_id_foreign` (`historical_customer_id`),
  KEY `fin_invoices_customer_id_foreign` (`customer_id`),
  CONSTRAINT `fin_invoices_account_receivable_id_foreign` FOREIGN KEY (`account_receivable_id`) REFERENCES `fin_accounts` (`id`),
  CONSTRAINT `fin_invoices_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoices_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_invoices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `fin_customers` (`id`),
  CONSTRAINT `fin_invoices_historical_customer_id_foreign` FOREIGN KEY (`historical_customer_id`) REFERENCES `fin_historical_customers` (`id`),
  CONSTRAINT `fin_invoices_invoice_status_id_foreign` FOREIGN KEY (`invoice_status_id`) REFERENCES `fin_invoice_statuses` (`id`),
  CONSTRAINT `fin_invoices_invoice_type_id_foreign` FOREIGN KEY (`invoice_type_id`) REFERENCES `fin_invoice_types` (`id`),
  CONSTRAINT `fin_invoices_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_insert_historical_customer` BEFORE INSERT ON `fin_invoices` FOR EACH ROW BEGIN
    INSERT INTO fin_historical_customers (customer_id, name, team_id, created_at, updated_at)
    SELECT id, name, team_id, NOW(), NOW()
    FROM fin_customers
    WHERE id = NEW.customer_id;

    SET NEW.historical_customer_id = LAST_INSERT_ID();
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `tr_invoice_number_before_insert` BEFORE INSERT ON `fin_invoices` FOR EACH ROW BEGIN
    IF NEW.invoice_number IS NULL THEN
        SELECT next_number INTO @next_num
        FROM fin_invoice_types 
        WHERE id = NEW.invoice_type_id
        FOR UPDATE;
        
        SET NEW.invoice_number = @next_num;
        
        UPDATE fin_invoice_types 
        SET next_number = next_number + 1
        WHERE id = NEW.invoice_type_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_insert_address_for_invoice` AFTER INSERT ON `fin_invoices` FOR EACH ROW BEGIN
    INSERT INTO addresses (addressable_id, addressable_type, address1, city, state, postal_code, country, created_at, updated_at)
    select NEW.id, 'invoice', a.address1, a.city, a.state, a.postal_code, a.country, NOW(), NOW() from addresses as a
    join fin_customers c on c.id = NEW.customer_id
    where a.id = c.default_billing_address_id;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `prevent_update_invoice_customer` BEFORE UPDATE ON `fin_invoices` FOR EACH ROW BEGIN
    IF OLD.customer_id != NEW.customer_id OR OLD.historical_customer_id != NEW.historical_customer_id THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Updating customer_id or historical_customer_id is not allowed';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `fin_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_revenue_account_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_products_added_by_foreign` (`added_by`),
  KEY `fin_products_modified_by_foreign` (`modified_by`),
  KEY `fin_products_default_revenue_account_id_foreign` (`default_revenue_account_id`),
  CONSTRAINT `fin_products_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_products_default_revenue_account_id_foreign` FOREIGN KEY (`default_revenue_account_id`) REFERENCES `fin_accounts` (`id`),
  CONSTRAINT `fin_products_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(10,6) NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `valide_from` date NOT NULL,
  `valide_to` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_taxes_added_by_foreign` (`added_by`),
  KEY `fin_taxes_modified_by_foreign` (`modified_by`),
  KEY `fin_taxes_account_id_foreign` (`account_id`),
  CONSTRAINT `fin_taxes_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `fin_accounts` (`id`),
  CONSTRAINT `fin_taxes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_taxes_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_taxes_group_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_taxes_group_taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tax_group_id` bigint unsigned NOT NULL,
  `tax_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_taxes_group_taxes_added_by_foreign` (`added_by`),
  KEY `fin_taxes_group_taxes_modified_by_foreign` (`modified_by`),
  KEY `fin_taxes_group_taxes_tax_group_id_foreign` (`tax_group_id`),
  KEY `fin_taxes_group_taxes_tax_id_foreign` (`tax_id`),
  CONSTRAINT `fin_taxes_group_taxes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_taxes_group_taxes_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_taxes_group_taxes_tax_group_id_foreign` FOREIGN KEY (`tax_group_id`) REFERENCES `fin_taxes_groups` (`id`),
  CONSTRAINT `fin_taxes_group_taxes_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `fin_taxes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_taxes_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_taxes_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_taxes_groups_added_by_foreign` (`added_by`),
  KEY `fin_taxes_groups_modified_by_foreign` (`modified_by`),
  CONSTRAINT `fin_taxes_groups_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_taxes_groups_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fin_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fin_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `transaction_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fin_transactions_added_by_foreign` (`added_by`),
  KEY `fin_transactions_modified_by_foreign` (`modified_by`),
  CONSTRAINT `fin_transactions_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fin_transactions_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_activity` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `concern_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `concern_id` bigint unsigned DEFAULT NULL,
  `title` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `histories_added_by_foreign` (`added_by`),
  KEY `histories_modified_by_foreign` (`modified_by`),
  KEY `histories_team_id_foreign` (`team_id`),
  KEY `histories_user_id_foreign` (`user_id`),
  KEY `histories_concern_type_concern_id_index` (`concern_type`,`concern_id`),
  CONSTRAINT `histories_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `histories_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `histories_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_type` int DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_added_by_foreign` (`added_by`),
  KEY `login_attempts_modified_by_foreign` (`modified_by`),
  CONSTRAINT `login_attempts_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `login_attempts_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_changes_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_changes_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `columns_changed` json NOT NULL,
  `action` tinyint NOT NULL,
  `changeable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changeable_id` bigint unsigned NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `changed_at` timestamp NOT NULL,
  `new_data` json DEFAULT NULL,
  `old_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `model_changes_logs_changeable_type_changeable_id_index` (`changeable_type`,`changeable_id`),
  KEY `model_changes_logs_changed_by_foreign` (`changed_by`),
  CONSTRAINT `model_changes_logs_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `notable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notable_id` bigint unsigned NOT NULL,
  `content_nt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_nt` timestamp NOT NULL DEFAULT '2025-05-12 20:27:49',
  `note_subtype` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notes_added_by_foreign` (`added_by`),
  KEY `notes_modified_by_foreign` (`modified_by`),
  KEY `notes_team_id_foreign` (`team_id`),
  KEY `notes_notable_type_notable_id_index` (`notable_type`,`notable_id`),
  CONSTRAINT `notes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notes_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notes_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `custom_button_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_button_href` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_reminder_button` tinyint(1) DEFAULT NULL,
  `custom_button_handler` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `communication_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_templates_added_by_foreign` (`added_by`),
  KEY `notification_templates_modified_by_foreign` (`modified_by`),
  KEY `notification_templates_communication_id_foreign` (`communication_id`),
  CONSTRAINT `notification_templates_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notification_templates_communication_id_foreign` FOREIGN KEY (`communication_id`) REFERENCES `communication_templates` (`id`),
  CONSTRAINT `notification_templates_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `notifier_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` int DEFAULT NULL,
  `status` int DEFAULT NULL,
  `reminder_at` datetime DEFAULT NULL,
  `about_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `about_id` bigint unsigned NOT NULL,
  `seen_at` datetime DEFAULT NULL,
  `custom_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `custom_button_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `custom_button_href` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `has_reminder_button` tinyint DEFAULT NULL,
  `notification_template_id` bigint unsigned DEFAULT NULL,
  `trigger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_button_handler` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_added_by_foreign` (`added_by`),
  KEY `notifications_modified_by_foreign` (`modified_by`),
  KEY `notifications_team_id_foreign` (`team_id`),
  KEY `notifications_notifier_id_foreign` (`notifier_id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  KEY `notifications_about_type_about_id_index` (`about_type`,`about_id`),
  KEY `notifications_notification_template_id_foreign` (`notification_template_id`),
  CONSTRAINT `notifications_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notifications_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notifications_notification_template_id_foreign` FOREIGN KEY (`notification_template_id`) REFERENCES `notification_templates` (`id`),
  CONSTRAINT `notifications_notifier_id_foreign` FOREIGN KEY (`notifier_id`) REFERENCES `users` (`id`),
  CONSTRAINT `notifications_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `permission_type` tinyint NOT NULL DEFAULT '7',
  PRIMARY KEY (`id`),
  KEY `permission_role_added_by_foreign` (`added_by`),
  KEY `permission_role_modified_by_foreign` (`modified_by`),
  KEY `permission_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_role_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permission_role_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `permission_sections_added_by_foreign` (`added_by`),
  KEY `permission_sections_modified_by_foreign` (`modified_by`),
  CONSTRAINT `permission_sections_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permission_sections_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_team_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_team_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `permission_type` tinyint NOT NULL DEFAULT '7',
  PRIMARY KEY (`id`),
  KEY `permission_team_role_added_by_foreign` (`added_by`),
  KEY `permission_team_role_modified_by_foreign` (`modified_by`),
  KEY `permission_team_role_team_role_id_foreign` (`team_role_id`),
  KEY `permission_team_role_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_team_role_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permission_team_role_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permission_team_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `permission_team_role_team_role_id_foreign` FOREIGN KEY (`team_role_id`) REFERENCES `team_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `permission_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_section_id` bigint unsigned DEFAULT NULL,
  `object_type` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permissions_added_by_foreign` (`added_by`),
  KEY `permissions_modified_by_foreign` (`modified_by`),
  KEY `permissions_permission_section_id_foreign` (`permission_section_id`),
  CONSTRAINT `permissions_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permissions_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `permissions_permission_section_id_foreign` FOREIGN KEY (`permission_section_id`) REFERENCES `permission_sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `phones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `phonable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phonable_id` bigint unsigned DEFAULT NULL,
  `type_ph` tinyint DEFAULT NULL,
  `number_ph` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension_ph` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phones_added_by_foreign` (`added_by`),
  KEY `phones_modified_by_foreign` (`modified_by`),
  KEY `phones_team_id_foreign` (`team_id`),
  KEY `phones_phonable_type_phonable_id_index` (`phonable_type`,`phonable_id`),
  CONSTRAINT `phones_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `phones_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `phones_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `accept_roll_to_child` tinyint DEFAULT NULL,
  `accept_roll_to_neighbourg` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `roles_added_by_foreign` (`added_by`),
  KEY `roles_modified_by_foreign` (`modified_by`),
  CONSTRAINT `roles_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `roles_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `short_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `short_urls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `short_url_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invitation_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `route` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `params` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_signed` tinyint DEFAULT NULL,
  `unique_identifier` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_urls_unique_identifier_unique` (`unique_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `taggable_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taggable_tag` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `taggable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `taggable_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `taggable_tag_taggable_type_taggable_id_index` (`taggable_type`,`taggable_id`),
  KEY `taggable_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `taggable_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tag_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_type` tinyint DEFAULT NULL,
  `context` tinyint NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  KEY `tags_added_by_foreign` (`added_by`),
  KEY `tags_modified_by_foreign` (`modified_by`),
  KEY `tags_tag_id_foreign` (`tag_id`),
  KEY `tags_team_id_foreign` (`team_id`),
  CONSTRAINT `tags_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tags_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`),
  CONSTRAINT `tags_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `team_changes_added_by_foreign` (`added_by`),
  KEY `team_changes_modified_by_foreign` (`modified_by`),
  KEY `team_changes_team_id_foreign` (`team_id`),
  KEY `team_changes_user_id_foreign` (`user_id`),
  CONSTRAINT `team_changes_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_changes_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_changes_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `team_changes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_hierarchy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  PRIMARY KEY (`id`),
  KEY `team_invitations_added_by_foreign` (`added_by`),
  KEY `team_invitations_modified_by_foreign` (`modified_by`),
  KEY `team_invitations_team_id_foreign` (`team_id`),
  CONSTRAINT `team_invitations_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_invitations_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_hierarchy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  `terminated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `parent_team_role_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_roles_added_by_foreign` (`added_by`),
  KEY `team_roles_modified_by_foreign` (`modified_by`),
  KEY `team_roles_role_foreign` (`role`),
  KEY `team_roles_parent_team_role_id_foreign` (`parent_team_role_id`),
  CONSTRAINT `team_roles_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_roles_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `team_roles_parent_team_role_id_foreign` FOREIGN KEY (`parent_team_role_id`) REFERENCES `team_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_roles_role_foreign` FOREIGN KEY (`role`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `modified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `team_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_team_id` bigint unsigned DEFAULT NULL,
  `primary_email_id` bigint unsigned DEFAULT NULL,
  `primary_phone_id` bigint unsigned DEFAULT NULL,
  `primary_billing_address_id` bigint unsigned DEFAULT NULL,
  `primary_shipping_address_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teams_added_by_foreign` (`added_by`),
  KEY `teams_modified_by_foreign` (`modified_by`),
  KEY `teams_user_id_foreign` (`user_id`),
  KEY `teams_parent_team_id_foreign` (`parent_team_id`),
  KEY `teams_primary_email_id_foreign` (`primary_email_id`),
  KEY `teams_primary_phone_id_foreign` (`primary_phone_id`),
  KEY `teams_primary_billing_address_id_foreign` (`primary_billing_address_id`),
  KEY `teams_primary_shipping_address_id_foreign` (`primary_shipping_address_id`),
  CONSTRAINT `teams_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_modified_by_foreign` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `teams_parent_team_id_foreign` FOREIGN KEY (`parent_team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `teams_primary_billing_address_id_foreign` FOREIGN KEY (`primary_billing_address_id`) REFERENCES `addresses` (`id`),
  CONSTRAINT `teams_primary_email_id_foreign` FOREIGN KEY (`primary_email_id`) REFERENCES `emails` (`id`),
  CONSTRAINT `teams_primary_phone_id_foreign` FOREIGN KEY (`primary_phone_id`) REFERENCES `phones` (`id`),
  CONSTRAINT `teams_primary_shipping_address_id_foreign` FOREIGN KEY (`primary_shipping_address_id`) REFERENCES `addresses` (`id`),
  CONSTRAINT `teams_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_team_role_id` bigint unsigned DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_at` timestamp NULL DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_current_team_role_id_foreign` (`current_team_role_id`),
  CONSTRAINT `users_current_team_role_id_foreign` FOREIGN KEY (`current_team_role_id`) REFERENCES `team_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 DROP FUNCTION IF EXISTS `calculate_customer_due` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_customer_due`(p_customer_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE customer_due DECIMAL(19,5);

    DECLARE customer_total_paid DECIMAL(19,5);
    DECLARE customer_total_debt DECIMAL(19,5);

    SELECT SUM(IFNULL(cp.amount, 0)) INTO customer_total_paid FROM fin_customers as c
        left join fin_customer_payments as cp on c.id = cp.customer_id and cp.deleted_at is null
        WHERE c.id = p_customer_id
        group by c.id;

    SELECT SUM(IFNULL(ci.invoice_total_amount, 0)) INTO customer_total_debt FROM fin_customers as c
        left join fin_invoices as ci on c.id = ci.customer_id and ci.deleted_at is null and ci.invoice_status_id != 1 and ci.invoice_status_id != 4
        WHERE c.id = p_customer_id
        group by c.id;
    
    select IFNULL(customer_total_debt, 0) - IFNULL(customer_total_paid, 0) into customer_due;

    IF customer_due IS NULL THEN
        SET customer_due = 0;
    END IF;

    RETURN customer_due;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calculate_invoice_amount_before_taxes` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_invoice_amount_before_taxes`(p_invoice_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE invoice_amount_before_taxes DECIMAL(19,5);
    SELECT SUM(IFNULL(in_d.extended_price, 0)) INTO invoice_amount_before_taxes FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id
        WHERE i.id = p_invoice_id and in_d.deleted_at is null
        group by i.id;

    IF invoice_amount_before_taxes IS NULL THEN
        SET invoice_amount_before_taxes = 0;
    END IF;

    RETURN invoice_amount_before_taxes;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calculate_invoice_due` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_invoice_due`(p_invoice_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE invoice_due DECIMAL(19,5);
    DECLARE invoice_total_paid DECIMAL(19,5);
    DECLARE invoice_total_substract DECIMAL(19,5);
    DECLARE invoice_total DECIMAL(19,5);

    SELECT calculate_invoice_amount_before_taxes(p_invoice_id) + calculate_invoice_tax(p_invoice_id) INTO invoice_total;

    
    SELECT SUM(IFNULL(ip.payment_applied_amount, 0)) INTO invoice_total_paid FROM fin_invoice_applies as ip
    WHERE ip.invoice_id = p_invoice_id and ip.deleted_at IS NULL;

    
    SELECT SUM(- IFNULL(ip.payment_applied_amount, 0)) INTO invoice_total_substract FROM fin_invoice_applies as ip
    WHERE ip.applicable_type = 2 and ip.applicable_id = p_invoice_id and ip.deleted_at IS NULL;

    select IFNULL(invoice_total - IFNULL(invoice_total_paid, 0) - IFNULL(invoice_total_substract, 0), 0) into invoice_due;

    IF invoice_due IS NULL THEN
        SET invoice_due = 0;
    END IF;

    RETURN invoice_due;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calculate_invoice_status` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_invoice_status`(p_invoice_id INT, p_paid_status_id INT, p_draft_status_id INT, p_pending_status_id INT) RETURNS int
BEGIN
    DECLARE current_status INT DEFAULT NULL;
    DECLARE items_quantity INT DEFAULT 0;
    DECLARE is_draft BOOLEAN DEFAULT TRUE;

    select count(*) into items_quantity from fin_invoice_details
        where invoice_id = p_invoice_id;

    SELECT invoice_status_id INTO current_status FROM fin_invoices
            WHERE id = p_invoice_id;

    SELECT fin_invoices.is_draft = 1 INTO is_draft FROM fin_invoices
            WHERE id = p_invoice_id;

    IF is_draft = TRUE THEN
        RETURN p_draft_status_id;
    ELSEIF current_status = p_draft_status_id THEN
        RETURN p_pending_status_id;
    END IF;

    IF calculate_invoice_due(p_invoice_id) = 0 AND items_quantity > 0 THEN
        RETURN p_paid_status_id;
    ELSE 
        IF current_status IS NULL THEN
            RETURN p_draft_status_id;
        END IF;

        IF current_status = p_paid_status_id THEN
            RETURN p_pending_status_id;
        END IF;

        RETURN current_status;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calculate_invoice_tax` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_invoice_tax`(p_invoice_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE invoice_tax DECIMAL(19,5);
    SELECT SUM(IFNULL(get_detail_tax_amount(in_d.id), 0)) INTO invoice_tax FROM fin_invoices as i
        left join fin_invoice_details as in_d on i.id = in_d.invoice_id and in_d.deleted_at is null
        WHERE i.id = p_invoice_id
        group by i.id;

    IF invoice_tax IS NULL THEN
        SET invoice_tax = 0;
    END IF;

    RETURN invoice_tax;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calculate_payment_amount_left` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calculate_payment_amount_left`(payment_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE payment_amount_left DECIMAL(19,5);
    DECLARE payment_amount DECIMAL(19,5);
    DECLARE payment_amount_paid DECIMAL(19,5);

    select p.amount into payment_amount from fin_customer_payments as p
    where p.id = payment_id;

    select sum(ABS(ifnull(pad.payment_applied_amount, 0))) into payment_amount_paid from fin_invoice_applies as pad
    where pad.applicable_id = payment_id and pad.applicable_type = 1 and pad.deleted_at is null;

    select COALESCE(payment_amount - payment_amount_paid, payment_amount, 0)  into payment_amount_left;
    
    RETURN GREATEST(payment_amount_left, 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_amount_using_sign_from_invoice` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_amount_using_sign_from_invoice`(invoice_id INT, amount DECIMAL (19, 5)) RETURNS decimal(19,5)
BEGIN
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT it.sign_multiplier 
    INTO sign_multiplier 
    FROM fin_invoices i
    JOIN fin_invoice_types it ON i.invoice_type_id = it.id
    WHERE i.id = invoice_id;

    return get_amount_using_sign_multiplier(amount, sign_multiplier);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_amount_using_sign_multiplier` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_amount_using_sign_multiplier`(amount DECIMAL(19, 5), sign_multiplier INT) RETURNS decimal(19,5)
BEGIN
    IF amount IS NULL THEN
        RETURN 0.00;
    END IF;

    IF sign_multiplier IS NULL THEN
        RETURN amount;
    END IF;

    IF amount / sign_multiplier < 0 THEN
        RETURN amount * -1;
    END IF;

    RETURN amount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_detail_tax_amount` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_detail_tax_amount`(id_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE tax_amount DECIMAL(19, 5);

    SELECT SUM(idt.`tax_amount`) INTO tax_amount
    FROM fin_invoice_detail_taxes idt
    WHERE idt.invoice_detail_id = id_id and deleted_at is null;

    IF tax_amount IS NULL THEN
        SET tax_amount = 0.0;
    END IF;

    RETURN tax_amount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_detail_unit_price_with_sign` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_detail_unit_price_with_sign`(id_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE unit_price DECIMAL(19, 5);
    DECLARE invoice_id INT;
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT id.invoice_id, id.unit_price 
    INTO invoice_id, unit_price 
    FROM fin_invoice_details id
    WHERE id.id = id_id;

    return get_amount_using_sign_from_invoice(invoice_id, unit_price);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_invoice_reference` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_invoice_reference`(p_invoice_id INT) RETURNS varchar(255) CHARSET utf8mb4
BEGIN
    DECLARE invoice_reference VARCHAR(255);
    SELECT CONCAT(it.prefix, '-', LPAD(i.invoice_number, 8, '0')) INTO invoice_reference FROM fin_invoices as i
        left join fin_invoice_types as it on i.invoice_type_id = it.id
        WHERE i.id = p_invoice_id;

    RETURN invoice_reference;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_payment_applied_amount_with_sign` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_payment_applied_amount_with_sign`(ip_id INT) RETURNS decimal(19,5)
BEGIN
    DECLARE applied_amount DECIMAL(19, 5);
    DECLARE invoice_id INT;
    DECLARE sign_multiplier INT DEFAULT 1;

    SELECT ip.invoice_id, ip.payment_applied_amount 
    INTO invoice_id, applied_amount 
    FROM fin_invoice_applies ip
    WHERE ip.id = ip_id;

    return get_amount_using_sign_from_invoice(invoice_id, applied_amount);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `get_updated_tax_amount_for_taxes` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `get_updated_tax_amount_for_taxes`(p_detail_id INT, tax_rate DECIMAL (19, 5)) RETURNS decimal(19,5)
BEGIN
    DECLARE unit_price    DECIMAL(19,5);
    DECLARE quantity      INT;
    DECLARE taxable_amount DECIMAL(19,5);

    SELECT get_detail_unit_price_with_sign(p_detail_id) INTO unit_price;
    SELECT d.quantity INTO quantity
      FROM fin_invoice_details as d
     WHERE d.id = p_detail_id;

    SET taxable_amount = unit_price * quantity;

    RETURN COALESCE(taxable_amount * tax_rate, 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_testbench_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_testbench_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_testbench_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2014_10_12_000008_create_email_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2014_10_12_000010_create_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2014_10_12_000011_create_team_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2014_10_12_000013_create_team_invitations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2014_10_12_000100_add_base_columns_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2014_10_12_000100_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2014_10_12_000101_create_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2014_10_12_001000_create_permission_team_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2014_10_12_200000_add_profile_photo_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2014_10_12_200001_add_contact_information_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2020_01_01_000001_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2020_01_01_000101_create_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2020_01_25_000000_create_address_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2020_01_25_000001_create_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2020_01_26_000001_create_phones_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2020_01_27_000001_create_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2020_01_30_000000_add_contact_fields_to_tables_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2020_01_30_000001_create_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2020_01_30_000002_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2020_11_11_000000_create_changes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2022_03_10_000001_create_short_urls_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2023_02_29_000002_create_notes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2023_11_11_093904_create_login_attempts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_01_30_000002_add_custom_message_to_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_08_01_000001_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_08_01_000003_add_permission_type_to_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_08_01_000004_add_permission_type_to_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_08_01_000005_add_hierarchy_cols_to_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_08_01_000006_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_08_01_000007_add_permission_section_to_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_08_14_000001_add_banned_at_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_08_14_000003_add_terminated_at_to_team_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_08_20_000007_add_object_type_to_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_08_29_020706_add_role_foreign_key_to_team_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_09_19_182656_add_new_cols_to_short_urls_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_10_10_102759_add_is_signed_to_short_urls_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2024_10_14_000001_create_communications_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2024_10_27_000001_add_disk_to_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2024_11_24_000001_add_parent_team_role_id_to_team_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2024_12_13_204214_create_model_changes_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2024_12_17_000000_add_new_data_to_model_changes_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_12_17_000000_add_old_data_to_model_changes_logs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_03_07_000001_create_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_03_07_000002_create_taxe_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_03_07_000003_update_customer_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_03_07_000004_create_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_03_07_000004_create_invoice_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_03_07_000005_create_historical_customers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_03_07_000005_create_taxes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_03_07_000006_create_taxe_group_taxe_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_03_07_000007_create_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_03_07_000008_create_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_03_07_000009_create_invoice_statuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_03_07_000010_create_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_03_07_000011_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_03_07_000012_create_invoice_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_03_07_000013_create_invoice_detail_taxes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_03_07_000014_create_customer_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_03_07_000015_create_invoice_applies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_03_11_000015_create_customer_due_function',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_03_11_000016_create_invoice_due_function',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_03_15_0000001_add_customer_id_to_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_03_20_000001_set_invoices_functions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_03_20_000001_set_invoices_triggers',1);
