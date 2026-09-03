-- =====================================================================
-- SISTEMA DE COBRANCA - MULTI-TENANT
-- Dump sanitizado do schema (estrutura migrada por admin) + dados basicos.
-- Credenciais/segredos de API foram zerados; valores reais devem ser
-- preenchidos por cada admin em seu proprio painel (admin/config_api.php).
-- Gerado em: 29/08/2026 20:09
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `cobranca` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cobranca`;

--
-- ESTRUTURA (SCHEMA MIGRADO)
--
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `cor` varchar(20) DEFAULT 'secondary',
  `icon` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `ordem` int(11) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6358 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `codigo_solicitacao` varchar(100) DEFAULT NULL,
  `qr_code` longtext DEFAULT NULL,
  `pix_copia_cola` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pendente',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pago_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planos_pag_admin` (`admin_id`),
  KEY `idx_planos_pag_codigo` (`codigo_solicitacao`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Superadmin padrao
--
INSERT INTO `superadmin` (`id`,`usuario`,`senha`,`nome`,`email`,`avatar`,`ultimo_login`,`criado_em`) VALUES
(1,'super','$2y$10$FfCIAnotiHzdCVvMgAeZkOhNXQNKf6MoZTnwNzgCt8AMYEZ0cah6i','Super Administrador',NULL,NULL,NULL,NOW());

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-29 15:07:32


--
-- DADOS BASICOS (SANITIZADOS)
--

--
-- Admin padrao (senha hash preservada; ajuste via painel superadmin)
--
INSERT INTO `administradores` (`id`,`usuario`,`subdominio`,`senha`,`nome`,`email`,`ativo`,`cnpj`) VALUES
(2,'admin','admin','$2y$10$FfCIAnotiHzdCVvMgAeZkOhNXQNKf6MoZTnwNzgCt8AMYEZ0cah6i','Lidiane Costa','lyflix80@gmail.com',1,'29243448000118');

--
-- Configuracoes globais (segredos zerados; cada admin configura sua API)
--
INSERT INTO `configuracoes` (`admin_id`,`chave`,`valor`) VALUES
(NULL,'api_pagamento_ativa','mercadopago'),
(NULL,'banner_desktop','/cobranca/assets/img/banners/banner_desktop.png'),
(NULL,'banner_desktop_1','/cobranca/assets/img/banners/desktop_1.png'),
(NULL,'banner_desktop_2','/cobranca/assets/img/banners/desktop_2.png'),
(NULL,'banner_desktop_3','/cobranca/assets/img/banners/desktop_3.png'),
(NULL,'banner_mobile','/cobranca/assets/img/banners/banner_mobile.png'),
(NULL,'banner_mobile_1','/cobranca/assets/img/banners/mobile_1.png'),
(NULL,'banner_mobile_2','/cobranca/assets/img/banners/mobile_2.png'),
(NULL,'banner_mobile_3','/cobranca/assets/img/banners/mobile_3.png'),
(NULL,'bb_agencia','2605'),
(NULL,'bb_ambiente','producao'),
(NULL,'bb_carteira',''),
(NULL,'bb_chave_pix',''),
(NULL,'bb_client_id',''),
(NULL,'bb_client_secret',''),
(NULL,'bb_conta','168360'),
(NULL,'bb_convenio',''),
(NULL,'bb_variacao',''),
(NULL,'bb_webhook_url',''),
(NULL,'cor_fundo','#f8f9fa'),
(NULL,'cor_primaria','#bc1515'),
(NULL,'cor_secundaria','#6c757d'),
(NULL,'cron_envio_ativo','1'),
(NULL,'cron_token',''),
(NULL,'envio_dias_antes','3'),
(NULL,'envio_dias_depois','1'),
(NULL,'envio_hora','08:00'),
(NULL,'financeiro_email','contato@agenciawd.com.br'),
(NULL,'financeiro_fone','91982675573'),
(NULL,'financeiro_whatsapp','91982675573'),
(NULL,'inter_cert_crt',''),
(NULL,'inter_cert_key',''),
(NULL,'inter_cert_webhook',''),
(NULL,'inter_client_id',''),
(NULL,'inter_client_secret',''),
(NULL,'inter_conta','0001-3855031-8'),
(NULL,'inter_webhook_url',''),
(NULL,'isolamento_admin','1'),
(NULL,'logo_empresa','/cobranca/assets/img/logo_1784559250.png'),
(NULL,'logo_login','/cobranca/assets/img/logo_login_1784559397.png'),
(NULL,'logo_mobile','/cobranca/assets/img/logo_mobile_1784733364.png'),
(NULL,'mp_access_token',''),
(NULL,'mp_public_key',''),
(NULL,'mp_webhook_url',''),
(NULL,'multitenant','1'),
(NULL,'nome_sistema','WD_payments'),
(NULL,'nubank_chave_pix',''),
(NULL,'nubank_whatsapp',''),
(NULL,'pagbank_ambiente','producao'),
(NULL,'pagbank_token',''),
(NULL,'pagbank_webhook_url',''),
(NULL,'pix_manual_banco','BTG Pactual'),
(NULL,'pix_manual_chave','29243448000118'),
(NULL,'pix_manual_cnpj','29243448000118'),
(NULL,'pix_manual_favorecido','WD Comunica+?+?es Digitais LTDA'),
(NULL,'pix_manual_whatsapp','5591982675573'),
(NULL,'regua_1_enviar_geracao','1'),
(NULL,'regua_2_dias_antes','5'),
(NULL,'regua_3_dias_antes','3'),
(NULL,'regua_4_no_vencimento','1'),
(NULL,'regua_5_dias_depois','2'),
(NULL,'smtp_from_email',''),
(NULL,'smtp_from_nome',''),
(NULL,'smtp_host',''),
(NULL,'smtp_port',''),
(NULL,'smtp_senha',''),
(NULL,'smtp_ssl',''),
(NULL,'smtp_usuario',''),
(NULL,'super_inter_cert_crt',''),
(NULL,'super_inter_cert_key',''),
(NULL,'super_inter_client_id',''),
(NULL,'super_inter_client_secret',''),
(NULL,'super_inter_conta','0001-3855031-8'),
(NULL,'super_inter_webhook_url',''),
(NULL,'template_whats_antes','{nomeEmpresa}
Ol+? {cliente} esse +? um lembrete de sua fatura {numero} de valor {valor} com vencimento: {data_vencimento}

Segue seu c+?digo PIX para pagamento:
{pix}

==========CENTRAL DE CLIENTE===============

Para 2-? via de sua fatura acesse sua +?rea de cliente:
{link_fatura}

===========ACESSO:=========================
{cpf_cnpj}'),
(NULL,'template_whats_depois','{nomeEmpresa}
Ol+? {cliente} SUA FATURA EST+? EM ATRASO. {numero} de valor {valor} com vencimento: {data_vencimento}

Segue seu c+?digo PIX para pagamento:
{pix}

==========CENTRAL DE CLIENTE===============

Para 2-? via de sua fatura acesse sua +?rea de cliente:
{link_fatura}

===========ACESSO:=========================
{cpf_cnpj}'),
(NULL,'template_whats_pagamento','{nomeEmpresa}
Ol+? {cliente} RECEBEMOS SEU PAGAMENTO. Fatura {numero} de valor {valor} com vencimento: {data_vencimento}.

OBRIGADO!


'),
(NULL,'usuarios_admin_isolado','1'),
(NULL,'whatsapp_api_key',''),
(NULL,'whatsapp_api_url',''),
(NULL,'whatsapp_ativo','1'),
(NULL,'whatsapp_instance','');

--
-- Planos
--
INSERT INTO `planos` (`id`,`nome`,`slug`,`preco`,`descricao`,`cor`,`icon`,`ordem`,`ativo`) VALUES
(1,'Bronze','bronze',49.9,'Plano inicial para pequenos negócios','bronze','fa-medal',1,1),
(2,'Prata','prata',99.9,'Plano intermediário com mais recursos','secondary','fa-circle-half-stroke',2,1),
(3,'Ouro','ouro',199.9,'Plano premium com todos os recursos','warning','fa-crown',3,1),
(1502,'Diamante','diamante',250,'Tudo incluso','primary','fa-crown',10,1);

COMMIT;
