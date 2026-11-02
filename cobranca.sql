-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: cobranca
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
/*!40000 ALTER TABLE `admin_certificados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_evolution`
--

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
/*!40000 ALTER TABLE `admin_evolution` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_planos`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_planos`
--

LOCK TABLES `admin_planos` WRITE;
/*!40000 ALTER TABLE `admin_planos` DISABLE KEYS */;
INSERT INTO `admin_planos` VALUES (13,1,4,'2026-10-05','2110-01-05','2026-10-05 15:21:01');
/*!40000 ALTER TABLE `admin_planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administradores`
--

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
  `origem` varchar(20) DEFAULT 'painel',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uq_admin_subdominio` (`subdominio`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administradores`
--

LOCK TABLES `administradores` WRITE;
/*!40000 ALTER TABLE `administradores` DISABLE KEYS */;
INSERT INTO `administradores` VALUES (1,'demo',NULL,'$2y$10$2lDx8CLI4IghV2lkQ63zKullc1qE0Y/DdeNA4WqmYRZVddiY9/saa','Conta Demo','demo@cobranca.local',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-10-05 15:21:01',NULL,'demo'),(9,'admin',NULL,'$2y$10$XzFHQsxLfR9G1CHum4D.suXyQGqjmcV.FUg3S.FRXH3P6SVzlkgVS','Administrador','admin@sistema.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-10-05 19:39:18','2026-10-05 19:39:18','painel');
/*!40000 ALTER TABLE `administradores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

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
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_cpf_cnpj` (`admin_id`,`cpf_cnpj`),
  KEY `idx_clientes_admin` (`admin_id`),
  KEY `idx_clientes_cpf_cnpj` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=494 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracoes`
--

LOCK TABLES `configuracoes` WRITE;
/*!40000 ALTER TABLE `configuracoes` DISABLE KEYS */;
INSERT INTO `configuracoes` VALUES (390,NULL,'isolamento_admin','1','2026-10-05 18:26:05'),(391,NULL,'multitenant','1','2026-10-05 18:26:05'),(392,NULL,'usuarios_admin_isolado','1','2026-10-05 18:26:05'),(393,NULL,'planos_limites','1','2026-10-05 18:26:05'),(394,NULL,'planos_utf8_fix','1','2026-10-05 18:26:05'),(395,NULL,'mp_access_token','','2026-10-05 18:41:19'),(396,NULL,'mp_public_key','','2026-10-05 18:41:19'),(397,NULL,'mp_webhook_url','','2026-10-05 18:41:19'),(403,NULL,'smtp_host','','2026-10-05 18:41:19'),(404,NULL,'smtp_port','587','2026-10-05 18:41:19'),(405,NULL,'smtp_usuario','','2026-10-05 18:41:19'),(406,NULL,'smtp_senha','','2026-10-05 18:41:19'),(407,NULL,'smtp_from_email','','2026-10-05 18:41:19'),(408,NULL,'smtp_from_nome','Sistema de Cobrança','2026-10-05 18:41:19'),(409,NULL,'smtp_ssl','tls','2026-10-05 18:41:19'),(410,NULL,'envio_dias_antes','3','2026-10-05 18:41:19'),(411,NULL,'envio_dias_depois','1','2026-10-05 18:41:19'),(412,NULL,'envio_hora','08:00','2026-10-05 18:41:19'),(413,NULL,'cron_envio_ativo','0','2026-10-05 18:41:19'),(414,NULL,'template_email_assunto_antes','Lembrete: Fatura {numero} vence em {data_vencimento}','2026-10-05 18:41:19'),(415,NULL,'template_email_assunto_depois','Cobrança: Fatura {numero} vencida','2026-10-05 18:41:19'),(416,NULL,'template_email_corpo_antes','','2026-10-05 18:41:19'),(417,NULL,'template_email_corpo_depois','','2026-10-05 18:41:19'),(418,NULL,'api_pagamento_ativa','mercadopago','2026-10-05 18:41:19'),(419,NULL,'inter_client_id','','2026-10-05 18:41:19'),(420,NULL,'inter_client_secret','','2026-10-05 18:41:19'),(421,NULL,'inter_conta','','2026-10-05 18:41:19'),(422,NULL,'inter_webhook_url','','2026-10-05 18:41:19'),(423,NULL,'inter_cert_crt','','2026-10-05 18:41:19'),(424,NULL,'inter_cert_key','','2026-10-05 18:41:19'),(425,NULL,'inter_cert_webhook','','2026-10-05 18:41:19'),(426,NULL,'bb_client_id','','2026-10-05 18:41:19'),(427,NULL,'bb_client_secret','','2026-10-05 18:41:19'),(428,NULL,'bb_conta','','2026-10-05 18:41:19'),(429,NULL,'bb_agencia','','2026-10-05 18:41:19'),(430,NULL,'bb_convenio','','2026-10-05 18:41:19'),(431,NULL,'bb_carteira','','2026-10-05 18:41:19'),(432,NULL,'bb_variacao','','2026-10-05 18:41:19'),(433,NULL,'bb_webhook_url','','2026-10-05 18:41:19'),(434,NULL,'bb_chave_pix','','2026-10-05 18:41:19'),(435,NULL,'bb_ambiente','producao','2026-10-05 18:41:19'),(436,NULL,'cora_client_id','','2026-10-05 18:41:19'),(437,NULL,'cora_cert_path','','2026-10-05 18:41:19'),(438,NULL,'cora_key_path','','2026-10-05 18:41:19'),(439,NULL,'cora_webhook_url','','2026-10-05 18:41:19'),(440,NULL,'cora_ambiente','producao','2026-10-05 18:41:19'),(441,NULL,'c6_client_id','','2026-10-05 18:41:19'),(442,NULL,'c6_client_secret','','2026-10-05 18:41:19'),(443,NULL,'c6_cert_path','','2026-10-05 18:41:19'),(444,NULL,'c6_cert_senha','','2026-10-05 18:41:19'),(445,NULL,'c6_agencia','','2026-10-05 18:41:19'),(446,NULL,'c6_conta','','2026-10-05 18:41:19'),(447,NULL,'c6_beneficiario','','2026-10-05 18:41:19'),(448,NULL,'c6_empresa','','2026-10-05 18:41:19'),(449,NULL,'c6_convenio','','2026-10-05 18:41:19'),(450,NULL,'c6_carteira','','2026-10-05 18:41:19'),(451,NULL,'c6_webhook_url','','2026-10-05 18:41:19'),(452,NULL,'c6_ambiente','homologacao','2026-10-05 18:41:19'),(453,NULL,'pagbank_token','','2026-10-05 18:41:19'),(454,NULL,'pagbank_ambiente','sandbox','2026-10-05 18:41:19'),(455,NULL,'pagbank_webhook_url','','2026-10-05 18:41:19'),(456,NULL,'nubank_chave_pix','','2026-10-05 18:41:19'),(457,NULL,'nubank_whatsapp','','2026-10-05 18:41:19'),(458,NULL,'whatsapp_ativo','0','2026-10-05 18:41:19'),(459,NULL,'whatsapp_api_url','','2026-10-05 18:41:19'),(460,NULL,'whatsapp_api_key','','2026-10-05 18:41:19'),(461,NULL,'whatsapp_instance','','2026-10-05 18:41:19'),(462,NULL,'cron_token','','2026-10-05 18:41:19'),(472,9,'logo_empresa','/cobranca/assets/img/logo_1788975582.png','2026-10-05 18:41:19'),(473,9,'logo_empresa_admin','/cobranca/assets/img/logo_empresa_admin_1789150809.png','2026-10-05 18:41:19'),(474,9,'logo_mobile','/cobranca/assets/img/logo_mobile_1788975594.png','2026-10-05 18:41:19'),(475,9,'logo_login','/cobranca/assets/img/logo_login_1788973310.png','2026-10-05 18:41:19'),(476,9,'cor_primaria','#e24646','2026-10-05 18:41:19'),(477,9,'cor_secundaria','#000000','2026-10-05 18:41:19'),(478,9,'cor_fundo','#ededed','2026-10-05 18:41:19'),(479,9,'nome_sistema','WD_Payment','2026-10-05 18:41:19'),(480,NULL,'logo_login','/cobranca/assets/img/logo_login_1788973310.png','2026-10-05 18:42:11'),(481,NULL,'logo_empresa','/cobranca/assets/img/logo_1788975582.png','2026-10-05 18:42:11'),(482,NULL,'logo_mobile','/cobranca/assets/img/logo_mobile_1788975594.png','2026-10-05 18:42:11'),(483,NULL,'cor_primaria','#bc1515','2026-10-05 18:42:11'),(484,NULL,'cor_secundaria','#6c757d','2026-10-05 18:42:11'),(485,NULL,'cor_fundo','#f8f9fa','2026-10-05 18:42:11'),(486,NULL,'nome_sistema','Central de faturas','2026-10-05 18:42:11'),(487,NULL,'site_url','https://centraldefaturas.com.br','2026-10-05 18:42:11'),(488,NULL,'super_inter_client_id','7dd3fd9f-d422-4eb7-b305-71ed4fe07ec7','2026-10-05 19:46:36'),(489,NULL,'super_inter_client_secret','9534137d-34d4-4808-a75a-0b310b3e5638','2026-10-05 19:46:36'),(490,NULL,'super_inter_conta','','2026-10-05 19:46:36'),(491,NULL,'super_inter_webhook_url','','2026-10-05 19:46:36'),(492,NULL,'super_inter_cert_crt','C:\\xampp\\cobranca\\superadmin/../config/inter_certs_super/certificado.crt','2026-10-05 19:46:36'),(493,NULL,'super_inter_cert_key','C:\\xampp\\cobranca\\superadmin/../config/inter_certs_super/certificado.key','2026-10-05 19:46:36');
/*!40000 ALTER TABLE `configuracoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contratos`
--

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
-- Table structure for table `cron_execucoes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_execucoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `execucao_id` varchar(40) NOT NULL,
  `inicio` datetime NOT NULL,
  `fim` datetime DEFAULT NULL,
  `origem` varchar(40) DEFAULT NULL,
  `recorrencias_processadas` int(11) NOT NULL DEFAULT 0,
  `faturas_emitidas` int(11) NOT NULL DEFAULT 0,
  `ignoradas` int(11) NOT NULL DEFAULT 0,
  `erros` int(11) NOT NULL DEFAULT 0,
  `resultado` varchar(20) DEFAULT 'em_andamento',
  `mensagem` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cron_execucao` (`execucao_id`),
  KEY `idx_cron_exec_inicio` (`inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_execucoes`
--

LOCK TABLES `cron_execucoes` WRITE;
/*!40000 ALTER TABLE `cron_execucoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `cron_execucoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cron_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `execucao_id` varchar(40) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `frequencia` varchar(20) DEFAULT NULL,
  `competencia` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `proxima_competencia` date DEFAULT NULL,
  `resultado` varchar(30) NOT NULL,
  `mensagem` text DEFAULT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cronlog_exec` (`execucao_id`),
  KEY `idx_cronlog_rec` (`fatura_recorrente_id`,`criado_em`),
  KEY `idx_cronlog_resultado` (`resultado`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_log`
--

LOCK TABLES `cron_log` WRITE;
/*!40000 ALTER TABLE `cron_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `cron_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faturas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `parcela_atual` int(11) DEFAULT NULL,
  `total_parcelas` int(11) DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `desconto` decimal(10,2) DEFAULT NULL,
  `multa` decimal(10,2) DEFAULT NULL,
  `juros` decimal(5,2) DEFAULT NULL,
  `valor_final` decimal(10,2) NOT NULL,
  `data_emissao` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `competencia` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','atrasado','cancelado','vencido') DEFAULT NULL,
  `pix_qrcode` text DEFAULT NULL,
  `pix_copia_cola` varchar(500) DEFAULT NULL,
  `link_pagamento` varchar(500) DEFAULT NULL,
  `boleto_url` varchar(500) DEFAULT NULL,
  `boleto_codigo_barras` varchar(100) DEFAULT NULL,
  `boleto_linha_digitavel` varchar(100) DEFAULT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `inter_codigo_solicitacao` varchar(100) DEFAULT NULL,
  `inter_situacao` varchar(30) DEFAULT NULL,
  `inter_data_situacao` datetime DEFAULT NULL,
  `pix_inter_codigo` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `ultimo_envio` date DEFAULT NULL,
  `ultimo_envio_tipo` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `acesso_token` varchar(64) DEFAULT NULL,
  `api_pagamento` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_numero` (`admin_id`,`numero`),
  UNIQUE KEY `uq_recorrencia_competencia` (`fatura_recorrente_id`,`competencia`),
  KEY `fatura_recorrente_id` (`fatura_recorrente_id`),
  KEY `idx_faturas_cliente` (`cliente_id`),
  KEY `idx_faturas_status` (`status`),
  KEY `idx_faturas_vencimento` (`data_vencimento`),
  KEY `idx_faturas_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas`
--

LOCK TABLES `faturas` WRITE;
/*!40000 ALTER TABLE `faturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `faturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faturas_excluidas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas_excluidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `competencia` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `motivo` varchar(40) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lapide_ciclo` (`fatura_recorrente_id`,`data_vencimento`),
  KEY `idx_lapide_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faturas_excluidas`
--

LOCK TABLES `faturas_excluidas` WRITE;
/*!40000 ALTER TABLE `faturas_excluidas` DISABLE KEYS */;
/*!40000 ALTER TABLE `faturas_excluidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faturas_recorrentes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas_recorrentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `frequencia` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual','lote') NOT NULL DEFAULT 'mensal',
  `dia_vencimento` int(11) DEFAULT NULL,
  `dia_ancora` tinyint(4) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `data_proxima_fatura` date DEFAULT NULL,
  `data_ultima_fatura` date DEFAULT NULL,
  `quantidade_transacoes` int(10) unsigned DEFAULT NULL,
  `antecedencia_emissao` int(11) NOT NULL DEFAULT 10,
  `dias_vencimento` int(11) NOT NULL DEFAULT 0,
  `politica_atraso` varchar(20) NOT NULL DEFAULT 'todas',
  `tentativas_erro` int(11) NOT NULL DEFAULT 0,
  `ultimo_erro` text DEFAULT NULL,
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
/*!40000 ALTER TABLE `livro_caixa_custos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `livro_caixa_entradas`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nfse_notas`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=54328 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos`
--

LOCK TABLES `planos` WRITE;
/*!40000 ALTER TABLE `planos` DISABLE KEYS */;
INSERT INTO `planos` VALUES (1,'Bronze','bronze',49.00,'Autônomos/pequenos','até 100 clientes\n1 usuário\n500 faturas/mês\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual',100,1,500,1,1,'bronze','fa-medal',1,1,'2026-10-05 15:21:01'),(2,'Prata','prata',99.90,'PMEs/microempresas','até 500 clientes\n3 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual',500,3,NULL,1,1,'secondary','fa-circle-half-stroke',1,2,'2026-10-05 15:21:01'),(3,'Ouro','ouro',199.90,'Empresas / recuperadoras','Clientes ilimitados\n10 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual',NULL,10,NULL,1,1,'warning','fa-crown',1,3,'2026-10-05 15:21:01'),(4,'Demo','demo',0.00,'Conta demo para explorar o sistema','Acesso completo ao painel\nCobranças por WhatsApp: Bloqueado\nCobranças por e-mail: Bloqueado\nGateways: visualização apenas',100,1,1,0,0,'info','fa-flask',1,99,'2026-10-05 15:21:01');
/*!40000 ALTER TABLE `planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_pagamentos`
--

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
  `metodo` varchar(20) DEFAULT NULL,
  `boleto_url` text DEFAULT NULL,
  `boleto_codigo_barras` text DEFAULT NULL,
  `boleto_linha_digitavel` text DEFAULT NULL,
  `mp_status` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planos_pag_admin` (`admin_id`),
  KEY `idx_planos_pag_codigo` (`codigo_solicitacao`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_pagamentos`
--

LOCK TABLES `planos_pagamentos` WRITE;
/*!40000 ALTER TABLE `planos_pagamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `planos_pagamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recibos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recibos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao_servico` text DEFAULT NULL,
  `cidade_emissao` varchar(100) DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `valor_recebido` decimal(10,2) NOT NULL,
  `valor_extenso` text DEFAULT NULL,
  `html_gerado` longtext DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_recibo_numero` (`admin_id`,`numero`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recibos`
--

LOCK TABLES `recibos` WRITE;
/*!40000 ALTER TABLE `recibos` DISABLE KEYS */;
/*!40000 ALTER TABLE `recibos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recorrencia_ajustes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recorrencia_ajustes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fatura_recorrente_id` int(11) NOT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `data_vigencia` date NOT NULL,
  `percentual` decimal(6,3) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `desconto` decimal(10,2) DEFAULT NULL,
  `multa` decimal(10,2) DEFAULT NULL,
  `juros` decimal(6,3) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ajustes_rec_vig` (`fatura_recorrente_id`,`data_vigencia`,`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recorrencia_ajustes`
--

LOCK TABLES `recorrencia_ajustes` WRITE;
/*!40000 ALTER TABLE `recorrencia_ajustes` DISABLE KEYS */;
/*!40000 ALTER TABLE `recorrencia_ajustes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superadmin`
--

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
INSERT INTO `superadmin` VALUES (1,'super','$2y$10$..GHXsJc..h32Duo7hm4M.1.Vq/bFdMgEXV0ZA4.ufawoPgUVRRUq','Super Administrador','superadmin@sistema.com',NULL,'2026-10-05 19:43:52','2026-10-05 15:21:01');
/*!40000 ALTER TABLE `superadmin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_admin`
--

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

-- Dump completed on 2026-10-06  9:50:52
