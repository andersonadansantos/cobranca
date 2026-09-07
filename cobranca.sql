-- =====================================================================
-- SISTEMA DE COBRANCA - MULTI-TENANT
-- Dump sanitizado do schema (estrutura migrada por admin) + dados basicos.
-- Credenciais/segredos de API foram zerados; valores reais devem ser
-- preenchidos por cada admin em seu proprio painel (admin/config_api.php).
-- Gerado em: 07/09/2026
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS cobranca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cobranca;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: cobranca_san
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `admin_certificados`
--

DROP TABLE IF EXISTS `admin_certificados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_certificados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `subject_dn` text NOT NULL,
  `issuer_dn` text NOT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `thumbprint` varchar(255) NOT NULL,
  `certificado_pem` text NOT NULL,
  `validade_inicio` datetime DEFAULT NULL,
  `validade_fim` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_uso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_certificados`
--

LOCK TABLES `admin_certificados` WRITE;
/*!40000 ALTER TABLE `admin_certificados` DISABLE KEYS */;
INSERT INTO `admin_certificados` VALUES (3,1,'WD COMUNICACOES AGENCIA DIGITAL','WD COMUNICACOES AGENCIA DIGITAL LTDA:29243448000118','AC Certisign RFB G5','','','','2025-12-27 13:31:21','2026-12-27 13:31:21',1,'2026-09-07 21:27:12','2026-07-29 19:15:34');
/*!40000 ALTER TABLE `admin_certificados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_evolution`
--

DROP TABLE IF EXISTS `admin_evolution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_evolution` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `url_api` varchar(255) NOT NULL DEFAULT '',
  `api_key` varchar(255) NOT NULL DEFAULT '',
  `instance` varchar(100) NOT NULL DEFAULT '',
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_evolution` (`admin_id`),
  CONSTRAINT `admin_evolution_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_evolution`
--

LOCK TABLES `admin_evolution` WRITE;
/*!40000 ALTER TABLE `admin_evolution` DISABLE KEYS */;
INSERT INTO `admin_evolution` VALUES (2,2,'https://api.agenciawd.com.br','','Sys_Cobranca',1,'2026-08-29 13:53:21','2026-09-07 21:27:12');
/*!40000 ALTER TABLE `admin_evolution` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_planos`
--

DROP TABLE IF EXISTS `admin_planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_plano` (`admin_id`),
  KEY `plano_id` (`plano_id`),
  CONSTRAINT `admin_planos_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_planos_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_planos`
--

LOCK TABLES `admin_planos` WRITE;
/*!40000 ALTER TABLE `admin_planos` DISABLE KEYS */;
INSERT INTO `admin_planos` VALUES (3,2,1,'2026-09-04','2026-10-04','2026-09-04 21:10:08');
/*!40000 ALTER TABLE `admin_planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administradores`
--

DROP TABLE IF EXISTS `administradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `subdominio` varchar(50) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `token_recuperacao` varchar(64) DEFAULT NULL,
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `razao_social` varchar(200) DEFAULT NULL,
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `telefone_comercial` varchar(20) DEFAULT NULL,
  `email_comercial` varchar(100) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uq_admin_subdominio` (`subdominio`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administradores`
--

LOCK TABLES `administradores` WRITE;
/*!40000 ALTER TABLE `administradores` DISABLE KEYS */;
INSERT INTO `administradores` VALUES (2,'admin','admin','$2y$10$FfCIAnotiHzdCVvMgAeZkOhNXQNKf6MoZTnwNzgCt8AMYEZ0cah6i','Lidiane Costa','lyflix80@gmail.com',NULL,NULL,NULL,NULL,'WD Solu????es Digitais','cenas da natureza','29243448000118','','','','(91) 98267-5576','lyflix80@gmail.com','66813930','Tv Moura CARVALHO','134','','Campina','Bel??m','PA',1,'2026-09-07 21:27:12','2026-09-07 14:35:29');
/*!40000 ALTER TABLE `administradores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `tipo_pessoa` enum('PF','PJ') NOT NULL DEFAULT 'PF',
  `nome_razao` varchar(200) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email2` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `token_recuperacao` varchar(64) DEFAULT NULL,
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_cpf_cnpj` (`admin_id`,`cpf_cnpj`),
  KEY `idx_clientes_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracoes`
--

DROP TABLE IF EXISTS `configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_chave` (`admin_id`,`chave`),
  KEY `idx_config_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracoes`
--

LOCK TABLES `configuracoes` WRITE;
/*!40000 ALTER TABLE `configuracoes` DISABLE KEYS */;
INSERT INTO `configuracoes` VALUES (1,NULL,'mp_access_token','','2026-09-07 21:27:12'),(2,NULL,'mp_public_key','','2026-09-07 21:27:12'),(3,NULL,'mp_webhook_url','','2026-09-07 21:27:12'),(4,NULL,'cor_primaria','#bc1515','2026-07-19 18:19:10'),(5,NULL,'cor_secundaria','#6c757d','2026-07-19 18:19:10'),(6,NULL,'cor_fundo','#f8f9fa','2026-07-19 18:19:10'),(7,NULL,'logo_empresa','/cobranca/assets/img/logo_1784559250.png','2026-07-19 18:19:10'),(8,NULL,'nome_sistema','WD_payments','2026-07-19 18:19:10'),(9,NULL,'logo_login','/cobranca/assets/img/logo_login_1784559397.png','2026-07-20 14:56:37'),(10,NULL,'smtp_host','','2026-09-07 21:27:12'),(11,NULL,'smtp_port','465','2026-07-20 19:19:46'),(12,NULL,'smtp_usuario','','2026-09-07 21:27:12'),(13,NULL,'smtp_senha','','2026-09-07 21:27:12'),(14,NULL,'smtp_from_email','contato@agenciawd.com.br','2026-07-20 19:19:46'),(15,NULL,'smtp_from_nome','WD Solu+?+?es Digitais','2026-07-20 19:19:46'),(16,NULL,'smtp_ssl','ssl','2026-07-20 19:19:46'),(24,NULL,'envio_dias_antes','3','2026-07-20 19:38:38'),(25,NULL,'envio_dias_depois','1','2026-07-20 19:38:38'),(26,NULL,'envio_hora','08:00','2026-07-20 19:38:38'),(27,NULL,'cron_envio_ativo','1','2026-07-20 19:38:38'),(28,NULL,'inter_client_id','','2026-09-07 21:27:12'),(29,NULL,'inter_client_secret','','2026-09-07 21:27:12'),(30,NULL,'inter_conta','0001-3855031-8','2026-07-20 21:19:05'),(31,NULL,'inter_webhook_url','','2026-09-07 21:27:12'),(32,NULL,'api_pagamento_ativa','mercadopago','2026-08-28 21:05:15'),(40,NULL,'inter_cert_crt','','2026-09-07 21:27:12'),(41,NULL,'inter_cert_key','','2026-09-07 21:27:12'),(55,NULL,'inter_cert_webhook','','2026-09-07 21:27:12'),(85,NULL,'bb_client_id','','2026-09-07 21:27:12'),(86,NULL,'bb_client_secret','','2026-09-07 21:27:12'),(87,NULL,'bb_conta','168360','2026-07-21 21:23:06'),(88,NULL,'bb_agencia','2605','2026-07-21 21:23:06'),(89,NULL,'bb_convenio','','2026-07-21 21:23:06'),(90,NULL,'bb_carteira','','2026-07-21 21:23:06'),(91,NULL,'bb_variacao','','2026-07-21 21:23:06'),(92,NULL,'bb_webhook_url','','2026-07-21 21:23:06'),(93,NULL,'bb_chave_pix','','2026-07-21 21:23:06'),(94,NULL,'bb_ambiente','producao','2026-07-21 21:23:06'),(95,NULL,'logo_mobile','/cobranca/assets/img/logo_mobile_1784733364.png','2026-07-22 15:16:04'),(96,NULL,'banner_desktop','/cobranca/assets/img/banners/banner_desktop.png','2026-07-22 15:39:11'),(97,NULL,'banner_mobile','/cobranca/assets/img/banners/banner_mobile.png','2026-07-22 15:39:17'),(98,NULL,'banner_desktop_1','/cobranca/assets/img/banners/desktop_1.png','2026-07-22 15:55:55'),(99,NULL,'banner_desktop_2','/cobranca/assets/img/banners/desktop_2.png','2026-07-22 15:56:02'),(100,NULL,'banner_desktop_3','/cobranca/assets/img/banners/desktop_3.png','2026-07-22 15:56:09'),(101,NULL,'banner_mobile_1','/cobranca/assets/img/banners/mobile_1.png','2026-07-22 16:04:04'),(102,NULL,'banner_mobile_2','/cobranca/assets/img/banners/mobile_2.png','2026-07-22 16:04:11'),(103,NULL,'banner_mobile_3','/cobranca/assets/img/banners/mobile_3.png','2026-07-22 16:04:18'),(136,NULL,'financeiro_whatsapp','91982675573','2026-07-23 14:34:20'),(137,NULL,'financeiro_email','contato@agenciawd.com.br','2026-07-23 14:34:20'),(138,NULL,'financeiro_fone','91982675573','2026-07-23 14:34:20'),(164,NULL,'regua_1_enviar_geracao','1','2026-07-24 01:07:25'),(165,NULL,'regua_2_dias_antes','5','2026-07-24 01:07:25'),(166,NULL,'regua_3_dias_antes','3','2026-07-24 01:07:25'),(167,NULL,'regua_4_no_vencimento','1','2026-07-24 01:07:25'),(168,NULL,'regua_5_dias_depois','2','2026-07-24 01:07:25'),(212,NULL,'pagbank_token','','2026-09-07 21:27:12'),(213,NULL,'pagbank_ambiente','producao','2026-07-27 14:48:37'),(214,NULL,'pagbank_webhook_url','','2026-07-27 14:48:37'),(215,NULL,'nubank_chave_pix','29243448000118','2026-07-27 15:29:06'),(216,NULL,'nubank_whatsapp','5591982675573','2026-07-27 15:29:06'),(232,NULL,'pix_manual_chave','29243448000118','2026-07-27 19:07:41'),(233,NULL,'pix_manual_banco','BTG Pactual','2026-07-27 19:07:41'),(234,NULL,'pix_manual_favorecido','WD Comunica+?+?es Digitais LTDA','2026-07-27 19:07:41'),(235,NULL,'pix_manual_cnpj','29243448000118','2026-07-27 19:07:41'),(236,NULL,'pix_manual_whatsapp','5591982675573','2026-07-27 19:07:41'),(246,NULL,'whatsapp_api_url','','2026-09-07 21:27:12'),(247,NULL,'whatsapp_api_key','','2026-09-07 21:27:12'),(248,NULL,'whatsapp_instance','','2026-09-07 21:27:12'),(249,NULL,'whatsapp_ativo','1','2026-07-29 12:16:01'),(250,NULL,'template_whats_antes','{nomeEmpresa}\r\nOl+? {cliente} esse +? um lembrete de sua fatura {numero} de valor {valor} com vencimento: {data_vencimento}\r\n\r\nSegue seu c+?digo PIX para pagamento:\r\n{pix}\r\n\r\n==========CENTRAL DE CLIENTE===============\r\n\r\nPara 2-? via de sua fatura acesse sua +?rea de cliente:\r\n{link_fatura}\r\n\r\n===========ACESSO:=========================\r\n{cpf_cnpj}','2026-07-30 14:05:53'),(252,NULL,'template_whats_depois','{nomeEmpresa}\r\nOl+? {cliente} SUA FATURA EST+? EM ATRASO. {numero} de valor {valor} com vencimento: {data_vencimento}\r\n\r\nSegue seu c+?digo PIX para pagamento:\r\n{pix}\r\n\r\n==========CENTRAL DE CLIENTE===============\r\n\r\nPara 2-? via de sua fatura acesse sua +?rea de cliente:\r\n{link_fatura}\r\n\r\n===========ACESSO:=========================\r\n{cpf_cnpj}','2026-07-30 14:06:47'),(253,NULL,'template_whats_pagamento','{nomeEmpresa}\r\nOl+? {cliente} RECEBEMOS SEU PAGAMENTO. Fatura {numero} de valor {valor} com vencimento: {data_vencimento}.\r\n\r\nOBRIGADO!\r\n\r\n\r\n','2026-07-30 14:07:50'),(277,NULL,'cron_token','','2026-09-07 21:27:12'),(283,NULL,'super_inter_client_id','','2026-09-07 21:27:12'),(284,NULL,'super_inter_client_secret','','2026-09-07 21:27:12'),(285,NULL,'super_inter_conta','0001-3855031-8','2026-08-28 21:56:25'),(286,NULL,'super_inter_webhook_url','','2026-08-28 21:56:11'),(287,NULL,'super_inter_cert_crt','','2026-09-07 21:27:12'),(288,NULL,'super_inter_cert_key','','2026-09-07 21:27:12'),(294,NULL,'isolamento_admin','1','2026-08-29 14:31:29'),(295,NULL,'multitenant','1','2026-08-29 14:50:56'),(296,NULL,'usuarios_admin_isolado','1','2026-08-29 17:19:31'),(297,NULL,'planos_limites','1','2026-09-04 21:06:42'),(298,NULL,'nfse_token','','2026-09-07 15:03:59'),(299,NULL,'nfse_ambiente','2','2026-09-07 15:03:59'),(300,NULL,'nfse_codigo_municipio','3550308','2026-09-07 15:03:59'),(301,NULL,'nfse_serie','900','2026-09-07 15:03:59'),(302,NULL,'nfse_codigo_servico','01.01','2026-09-07 15:03:59'),(303,NULL,'nfse_aliquota','5.00','2026-09-07 15:03:59'),(304,NULL,'nfse_regime_tributario','simples_nacional','2026-09-07 15:03:59'),(305,NULL,'nfse_optante_simples','1','2026-09-07 15:03:59'),(306,NULL,'nfse_configurado','1','2026-09-07 15:03:59');
/*!40000 ALTER TABLE `configuracoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contratos`
--

DROP TABLE IF EXISTS `contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `valor_mensal` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','suspenso','encerrado') DEFAULT NULL,
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `cliente_id` (`cliente_id`),
  KEY `idx_contratos_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contratos`
--

LOCK TABLES `contratos` WRITE;
/*!40000 ALTER TABLE `contratos` DISABLE KEYS */;
/*!40000 ALTER TABLE `contratos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faturas`
--

DROP TABLE IF EXISTS `faturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `desconto` decimal(10,2) DEFAULT NULL,
  `multa` decimal(10,2) DEFAULT NULL,
  `juros` decimal(5,2) DEFAULT NULL,
  `valor_final` decimal(10,2) NOT NULL,
  `data_emissao` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','atrasado','cancelado','vencido') DEFAULT NULL,
  `pix_qrcode` text DEFAULT NULL,
  `pix_copia_cola` varchar(500) DEFAULT NULL,
  `link_pagamento` varchar(500) DEFAULT NULL,
  `boleto_url` varchar(500) DEFAULT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `inter_codigo_solicitacao` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `ultimo_envio` date DEFAULT NULL,
  `ultimo_envio_tipo` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `acesso_token` varchar(64) DEFAULT NULL,
  `api_pagamento` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_numero` (`admin_id`,`numero`),
  KEY `fatura_recorrente_id` (`fatura_recorrente_id`),
  KEY `idx_faturas_cliente` (`cliente_id`),
  KEY `idx_faturas_status` (`status`),
  KEY `idx_faturas_vencimento` (`data_vencimento`),
  KEY `idx_faturas_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas`
--

LOCK TABLES `faturas` WRITE;
/*!40000 ALTER TABLE `faturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `faturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faturas_recorrentes`
--

DROP TABLE IF EXISTS `faturas_recorrentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas_recorrentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `frequencia` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NOT NULL DEFAULT 'mensal',
  `dia_vencimento` int(11) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'ativa',
  `numero` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas_recorrentes`
--

LOCK TABLES `faturas_recorrentes` WRITE;
/*!40000 ALTER TABLE `faturas_recorrentes` DISABLE KEYS */;
/*!40000 ALTER TABLE `faturas_recorrentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livro_caixa_custos`
--

DROP TABLE IF EXISTS `livro_caixa_custos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_custos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pago_mes` int(11) DEFAULT NULL,
  `pago_ano` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lc_custos_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livro_caixa_custos`
--

LOCK TABLES `livro_caixa_custos` WRITE;
/*!40000 ALTER TABLE `livro_caixa_custos` DISABLE KEYS */;
INSERT INTO `livro_caixa_custos` VALUES (2,NULL,'aluguel da casa',1000.00,'2026-08-15','2026-07-20 20:48:15',7,2026),(7,NULL,'Vivo',41.00,'2026-07-23','2026-07-23 13:51:56',NULL,NULL);
/*!40000 ALTER TABLE `livro_caixa_custos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livro_caixa_entradas`
--

DROP TABLE IF EXISTS `livro_caixa_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lc_entradas_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livro_caixa_entradas`
--

LOCK TABLES `livro_caixa_entradas` WRITE;
/*!40000 ALTER TABLE `livro_caixa_entradas` DISABLE KEYS */;
/*!40000 ALTER TABLE `livro_caixa_entradas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livro_caixa_saidas`
--

DROP TABLE IF EXISTS `livro_caixa_saidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_saidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lc_saidas_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `livro_caixa_saidas`
--

LOCK TABLES `livro_caixa_saidas` WRITE;
/*!40000 ALTER TABLE `livro_caixa_saidas` DISABLE KEYS */;
/*!40000 ALTER TABLE `livro_caixa_saidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contexto` varchar(20) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `tentativas` int(11) DEFAULT NULL,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `idx_contexto_id` (`identificador`,`contexto`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (2,'user','all','::1',2,NULL,'2026-08-25 20:44:17','2026-07-30 16:05:02'),(3,'admin','all','::1',3,NULL,'2026-09-05 19:31:32','0000-00-00 00:00:00');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nfse_notas`
--

DROP TABLE IF EXISTS `nfse_notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nfse_notas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `ns_nrec` int(11) DEFAULT NULL,
  `chave_acesso` varchar(100) DEFAULT NULL,
  `numero_nfse` varchar(30) DEFAULT NULL,
  `serie` varchar(10) DEFAULT '900',
  `status` varchar(30) DEFAULT 'pendente',
  `motivo` varchar(500) DEFAULT NULL,
  `valor_servico` decimal(10,2) DEFAULT 0.00,
  `aliquota` decimal(5,2) DEFAULT 5.00,
  `valor_issqn` decimal(10,2) DEFAULT 0.00,
  `descricao_servico` varchar(500) DEFAULT NULL,
  `codigo_servico` varchar(20) DEFAULT NULL,
  `xml_dps` longtext DEFAULT NULL,
  `xml_nfse` longtext DEFAULT NULL,
  `danfse_pdf` longblob DEFAULT NULL,
  `data_emissao` datetime DEFAULT NULL,
  `data_processamento` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nfse_admin` (`admin_id`),
  KEY `idx_nfse_fatura` (`fatura_id`),
  KEY `idx_nfse_cliente` (`cliente_id`),
  KEY `idx_nfse_status` (`status`),
  KEY `idx_nfse_nsNRec` (`ns_nrec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nfse_notas`
--

LOCK TABLES `nfse_notas` WRITE;
/*!40000 ALTER TABLE `nfse_notas` DISABLE KEYS */;
/*!40000 ALTER TABLE `nfse_notas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagamentos_log`
--

DROP TABLE IF EXISTS `pagamentos_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagamentos_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_id` int(11) NOT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT NULL,
  `mp_status_detail` varchar(100) DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `tipo_pagamento` varchar(50) DEFAULT NULL,
  `dados_raw` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fatura_id` (`fatura_id`),
  KEY `idx_pagamentos_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagamentos_log`
--

LOCK TABLES `pagamentos_log` WRITE;
/*!40000 ALTER TABLE `pagamentos_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagamentos_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos`
--

DROP TABLE IF EXISTS `planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `preco` decimal(10,2) DEFAULT 0.00,
  `descricao` varchar(500) DEFAULT NULL,
  `beneficios` text DEFAULT NULL,
  `max_clientes` int(11) DEFAULT NULL,
  `max_usuarios` int(11) DEFAULT NULL,
  `max_faturas_mensais` int(11) DEFAULT NULL,
  `whatsapp_cobranca` tinyint(1) DEFAULT 1,
  `email_cobranca` tinyint(1) DEFAULT 1,
  `cor` varchar(20) DEFAULT 'secondary',
  `icon` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `ordem` int(11) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=14353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos`
--

LOCK TABLES `planos` WRITE;
/*!40000 ALTER TABLE `planos` DISABLE KEYS */;
INSERT INTO `planos` VALUES (1,'Bronze','bronze',49.00,'Aut??nomos/pequenos','at?? 100 clientes\r\n1 usu??rio\r\n300 faturas/m??s\r\nCobran??as por WhatsApp: Liberado\r\nCobran??as por e-mail: Liberado\r\nGateways: Mercado Pago ?? Inter ?? Asaas ?? PIX Manual',100,1,300,1,1,'bronze','fa-medal',1,1,'2026-08-28 12:21:22'),(2,'Prata','prata',99.90,'PMEs/microempresas','at?? 300 clientes\r\n3 usu??rios\r\n500 faturas/m??s\r\nCobran??as por WhatsApp: Liberado\r\nCobran??as por e-mail: Liberado\r\nGateways: Mercado Pago ?? Inter ?? Asaas ?? PIX Manual',300,3,500,1,1,'secondary','fa-circle-half-stroke',1,2,'2026-08-28 12:21:22'),(3,'Ouro','ouro',199.90,'Empresas / recuperadoras','Clientes ilimitados\n10 usu??rios\nFaturas ilimitadas\nCobran??as por WhatsApp: Liberado\nCobran??as por e-mail: Liberado\nGateways: Mercado Pago ?? Inter ?? Asaas ?? PIX Manual',NULL,10,NULL,1,1,'warning','fa-crown',1,3,'2026-08-28 12:21:22');
/*!40000 ALTER TABLE `planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_pagamentos`
--

DROP TABLE IF EXISTS `planos_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planos_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `duracao_meses` int(11) DEFAULT 1,
  `descricao` varchar(200) DEFAULT NULL,
  `codigo_solicitacao` varchar(100) DEFAULT NULL,
  `qr_code` longtext DEFAULT NULL,
  `pix_copia_cola` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pendente',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pago_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planos_pag_admin` (`admin_id`),
  KEY `idx_planos_pag_codigo` (`codigo_solicitacao`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_pagamentos`
--

LOCK TABLES `planos_pagamentos` WRITE;
/*!40000 ALTER TABLE `planos_pagamentos` DISABLE KEYS */;
INSERT INTO `planos_pagamentos` VALUES (1,1,2,99.90,1,NULL,'f07c95e6-95b7-4f11-9885-39bcbf31eac0','','','pendente','2026-08-28 22:12:18',NULL),(2,1,2,99.90,1,NULL,'3b819822-90f5-43fb-ae04-0777456a0898','','','pendente','2026-08-28 23:07:06',NULL),(3,2,1502,250.00,1,NULL,'54b42a13-3097-4eac-98e1-525fb7eb10e5','','','pendente','2026-08-29 13:58:26',NULL),(4,2,2,99.90,1,NULL,'483be3fc-8c8a-498f-8f26-1513dfce8900','','','pendente','2026-09-03 22:38:47',NULL),(5,2,1,49.90,1,NULL,'6311e75c-949a-4e84-b881-add3e0fac1b2','','','pendente','2026-09-03 22:43:32',NULL),(6,2,1,49.90,1,NULL,'d4977767-1dcf-4a76-bfd5-0418fd90e53b','','','pendente','2026-09-03 22:44:13',NULL),(7,2,2,99.90,1,NULL,'478921d6-ae74-46fa-8930-2ecfed89d615','','','pendente','2026-09-03 22:47:07',NULL),(8,2,1,49.90,1,NULL,'24c5c65e-86fd-479b-8e8a-14f5bba2d9e7','','','pendente','2026-09-03 22:48:30',NULL),(9,2,1,49.90,1,NULL,'e406ff28-9bc0-4165-8b6c-375b757fbb8c','','','pendente','2026-09-03 22:50:06',NULL),(10,2,2,99.90,1,NULL,'9919b32c-1ee5-4b28-9fcb-6ceac9076ca9','','','pendente','2026-09-03 22:51:12',NULL),(11,2,1,49.90,1,NULL,'3b2d9fca-e536-4855-9eef-a524d4a43f81','','','pendente','2026-09-03 22:53:25',NULL),(12,2,1,49.90,1,NULL,'104cfd1d-fa4a-41a0-b33c-011833d1e629','','','pendente','2026-09-03 22:54:02',NULL),(13,2,1,49.90,1,NULL,'184b1ee5-8f6e-42d8-9aec-a31facbd827f','','','pendente','2026-09-03 22:55:51',NULL);
/*!40000 ALTER TABLE `planos_pagamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superadmin`
--

DROP TABLE IF EXISTS `superadmin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin`
--

LOCK TABLES `superadmin` WRITE;
/*!40000 ALTER TABLE `superadmin` DISABLE KEYS */;
INSERT INTO `superadmin` VALUES (1,'superadmin','$2y$10$FfCIAnotiHzdCVvMgAeZkOhNXQNKf6MoZTnwNzgCt8AMYEZ0cah6i','Super Admin','superadmin@sistema.com',NULL,'2026-08-29 17:20:27','2026-08-28 12:35:48'),(2,'super','$2y$10$FfCIAnotiHzdCVvMgAeZkOhNXQNKf6MoZTnwNzgCt8AMYEZ0cah6i','Super Administrador',NULL,NULL,'2026-09-07 14:35:39','2026-09-03 19:56:51');
/*!40000 ALTER TABLE `superadmin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_admin`
--

DROP TABLE IF EXISTS `usuarios_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','financeiro','atendimento') DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ua_admin_email` (`admin_id`,`email`),
  UNIQUE KEY `uq_ua_admin_usuario` (`admin_id`,`usuario`),
  KEY `idx_ua_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios_admin`
--

LOCK TABLES `usuarios_admin` WRITE;
/*!40000 ALTER TABLE `usuarios_admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuarios_admin` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-07 18:27:18


COMMIT;
