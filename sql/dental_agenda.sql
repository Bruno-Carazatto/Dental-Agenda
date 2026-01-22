-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 22/01/2026 às 19:49
-- Versão do servidor: 8.0.44-0ubuntu0.24.04.2
-- Versão do PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `dental_agenda`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `appointments`
--

CREATE TABLE `appointments` (
  `id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `hora` time NOT NULL,
  `tipo` enum('avaliacao','consulta','retorno') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'avaliacao',
  `status` enum('agendado','confirmado','concluido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agendado',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `auth_logs`
--

CREATE TABLE `auth_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `usuario_digitado` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evento` enum('login_sucesso','login_falha','logout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `auth_logs`
--

INSERT INTO `auth_logs` (`id`, `user_id`, `usuario_digitado`, `evento`, `ip`, `user_agent`, `criado_em`) VALUES
(1, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 14:15:42'),
(2, 1, 'Bruno.Carazatto', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 14:23:49'),
(3, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 15:59:43'),
(4, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:57:37'),
(5, 1, 'Bruno.Carazatto', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:09'),
(6, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:12'),
(7, 1, 'Bruno.Carazatto', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:13'),
(8, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:14'),
(9, 1, 'Bruno.Carazatto', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:15'),
(10, 1, 'Bruno.Carazatto', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 17:58:17'),
(11, 1, 'Bruno.Carazatto', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:42:15'),
(12, 2, 'Admin', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:43:00'),
(13, 2, 'Admin', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:44:42'),
(14, 2, 'Admin', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:47:11'),
(15, 2, 'Admin', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:53:17'),
(16, 2, 'Admin', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:53:52'),
(17, 2, 'Admin', 'logout', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:56:42'),
(18, 2, 'Admin', 'login_sucesso', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-22 18:56:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `budgets`
--

CREATE TABLE `budgets` (
  `id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `status` enum('pendente','aprovado','recusado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `budget_items`
--

CREATE TABLE `budget_items` (
  `id` int UNSIGNED NOT NULL,
  `budget_id` int UNSIGNED NOT NULL,
  `procedure_id` int UNSIGNED NOT NULL,
  `qtd` int UNSIGNED NOT NULL DEFAULT '1',
  `valor_unit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `patients`
--

CREATE TABLE `patients` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `procedures`
--

CREATE TABLE `procedures` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_base` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `procedures`
--

INSERT INTO `procedures` (`id`, `nome`, `valor_base`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Avaliação', 80.00, 1, '2026-01-22 16:28:51', NULL),
(2, 'Limpeza (Profilaxia)', 150.00, 1, '2026-01-22 16:28:51', NULL),
(3, 'Restauração', 220.00, 1, '2026-01-22 16:28:51', NULL),
(4, 'Canal (Endodontia)', 900.00, 1, '2026-01-22 16:28:51', NULL),
(5, 'Extração', 350.00, 1, '2026-01-22 16:28:51', NULL),
(6, 'Clareamento', 1200.00, 1, '2026-01-22 16:28:51', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `treatments`
--

CREATE TABLE `treatments` (
  `id` int UNSIGNED NOT NULL,
  `patient_id` int UNSIGNED NOT NULL,
  `dentist_id` int UNSIGNED NOT NULL,
  `titulo` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('em_andamento','concluido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'em_andamento',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `treatment_steps`
--

CREATE TABLE `treatment_steps` (
  `id` int UNSIGNED NOT NULL,
  `treatment_id` int UNSIGNED NOT NULL,
  `procedure_id` int UNSIGNED NOT NULL,
  `status` enum('pendente','em_andamento','concluido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','dentista','recepcao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recepcao',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `nome`, `usuario`, `senha_hash`, `role`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(2, 'Admin', 'Admin', '$2y$10$PcCXv3tcHaJB0TMYS5dDWeZb2ug0FI22LObaL1wZf6Mfk9ym.z7Ia', 'admin', 1, '2026-01-22 18:42:40', NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dentist_slot` (`dentist_id`,`data`,`hora`),
  ADD KEY `idx_data` (`data`),
  ADD KEY `idx_dentist` (`dentist_id`),
  ADD KEY `idx_patient` (`patient_id`);

--
-- Índices de tabela `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_data` (`data`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_dentist` (`dentist_id`);

--
-- Índices de tabela `budget_items`
--
ALTER TABLE `budget_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_budget` (`budget_id`),
  ADD KEY `idx_proc` (`procedure_id`);

--
-- Índices de tabela `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cpf` (`cpf`),
  ADD KEY `idx_nome` (`nome`);

--
-- Índices de tabela `procedures`
--
ALTER TABLE `procedures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nome` (`nome`);

--
-- Índices de tabela `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_dentist` (`dentist_id`);

--
-- Índices de tabela `treatment_steps`
--
ALTER TABLE `treatment_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_treatment` (`treatment_id`),
  ADD KEY `idx_proc` (`procedure_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuario` (`usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `auth_logs`
--
ALTER TABLE `auth_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `budget_items`
--
ALTER TABLE `budget_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `procedures`
--
ALTER TABLE `procedures`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `treatment_steps`
--
ALTER TABLE `treatment_steps`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT;

--
-- Restrições para tabelas `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD CONSTRAINT `fk_auth_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `fk_budget_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_budget_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT;

--
-- Restrições para tabelas `budget_items`
--
ALTER TABLE `budget_items`
  ADD CONSTRAINT `fk_item_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_proc` FOREIGN KEY (`procedure_id`) REFERENCES `procedures` (`id`) ON DELETE RESTRICT;

--
-- Restrições para tabelas `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `fk_treatment_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_treatment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT;

--
-- Restrições para tabelas `treatment_steps`
--
ALTER TABLE `treatment_steps`
  ADD CONSTRAINT `fk_step_proc` FOREIGN KEY (`procedure_id`) REFERENCES `procedures` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_step_treatment` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
