-- dental-agenda/sql/schema.sql
-- Banco: dental_agenda

CREATE DATABASE IF NOT EXISTS dental_agenda
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dental_agenda;

-- Tabela de usuários (Admin, Dentista, Recepção)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  usuario VARCHAR(80) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'dentista', 'recepcao') NOT NULL DEFAULT 'recepcao',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuario (usuario)
) ENGINE=InnoDB;

-- Logins / auditoria simples
CREATE TABLE IF NOT EXISTS auth_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  usuario_digitado VARCHAR(80) NULL,
  evento ENUM('login_sucesso','login_falha','logout') NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  CONSTRAINT fk_auth_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- (Próximas etapas) Pacientes, agendamentos, orçamentos, tratamentos
-- Vamos criar na etapa 2/3 para manter o MVP enxuto e funcional.
