# 🦷 Dental Agenda — Mini Sistema para Clínicas Odontológicas

Sistema web completo para **gestão de clínicas odontológicas**, desenvolvido com foco em **organização, produtividade e histórico clínico**.

> Projeto criado para estudo e portfólio, simulando um sistema real usado em clínicas.

---

## 🎯 Objetivo do Projeto

Este projeto foi desenvolvido para demonstrar, na prática, como funciona um **sistema de gestão odontológica**, abordando:

- Agendamentos
- Cadastro de pacientes
- Orçamentos com procedimentos
- Planos de tratamento com etapas e histórico

Tudo isso utilizando **boas práticas**, código limpo e estrutura pronta para evoluir com back-end mais robusto.

---

## 🧠 Funcionalidades Principais

### 📅 Agenda
- Criar, editar e excluir agendamentos
- Bloqueio de horário por dentista
- Filtros por data, dentista e status

### 👤 Pacientes
- Cadastro completo (CRUD)
- Histórico centralizado por paciente
- Validação de dados

### 💰 Orçamentos
- Catálogo de procedimentos
- Orçamentos com múltiplos itens
- Cálculo automático de valores
- Status: Pendente | Aprovado | Recusado
- Impressão / PDF via `window.print()`

### 🦷 Tratamentos
- Criação de plano de tratamento
- Etapas por procedimento
- Status por etapa (Pendente / Em andamento / Concluído)
- Histórico completo por paciente

---

## 🛠️ Tecnologias Utilizadas

- **HTML5**
- **CSS3**
- **JavaScript (Vanilla)**
- **Bootstrap 5**
- **PHP 8 (PDO + Prepared Statements)**
- **MySQL / phpMyAdmin**

---
## 👨‍💻 Como Utilizar / Login

Crie um novo banco de dados
- Nome: dental_agenda
- Colação selecione: utf8mb4_unicode_ci
- Depois vamos importe o arquivo: dental_agenda.sql para o nosso novo banco de dados criado
- Depois vamos iniciar seu (apache) ou (Xampp)
- Depois vamos no navegador e cole: http://localhost/dental-agenda/public/login.php

As Credenciais por padrão do Admin são;
- Login: Admin
- Senha: Admin@123

---
## 🔐 Boas Práticas Aplicadas

- ✔️ Separação de responsabilidades
- ✔️ PDO com prepared statements (segurança SQL)
- ✔️ Proteção CSRF
- ✔️ Validações no front-end e back-end
- ✔️ Layout responsivo (mobile first)
- ✔️ Código comentado e organizado

---

## 🚀 Próximas Evoluções (Roadmap)

- Barra de progresso nos tratamentos
- Relacionar orçamento → tratamento
- Geração de PDF avançado
- Controle de usuários e permissões
- Dashboard com métricas da clínica

---

## 📂 Estrutura do Projeto

```txt
dental-agenda/
├── app/
│   ├── auth/
│   │   └── check.php
│   └── config/
│       └── db.php
│
├── public/
│   ├── dashboard.php
│   ├── agenda.php
│   ├── pacientes.php
│   ├── orcamentos.php
│   └── tratamentos.php
│
├── assets/
│   └── css/
│   │    └── style.css
│   └── img/
│        └── icon.png
│
├── sql/
│   ├── dental_agenda.sql
│   └── schema.sql
└── README.md
