# Registro de Ponto (PHP)

[![PHP](https://img.shields.io/badge/PHP-%5E8.1-blue)](#)
[![Composer](https://img.shields.io/badge/Composer-ready-green)](#)
[![Status](https://img.shields.io/badge/status-em%20desenvolvimento-yellow)](#)

Sistema simples de **registro de ponto**. Permite **login**, **bater ponto**, **recuperar senha** e **verificar e exportar a folha** por período.

> 🎥 **Demonstração rápida (GIF):**  
> *(adicione aqui um GIF ou screenshots do fluxo login → bater ponto → exportar)*

---

## ✨ Funcionalidades

- Autenticação de usuários (login/logout)
- Recuperação de senha (fluxo *esqueci senha* → *validar código* → *nova senha*)
- Registro de ponto 
- Exportação de folha (CSV/XLSX)


---

## 🧱 Stack

- **PHP** 8.1+
- **MySQL/MariaDB**
- **Composer** (gerenciador de dependências)

---

## 🚀 Como rodar localmente

### 1) Pré-requisitos

- PHP 8.1 ou superior (extensões `pdo` e `pdo_mysql` habilitadas)  
- MySQL/MariaDB  
- Composer

### 2) Clonar e instalar dependências

```bash
git clone https://github.com/gaabrielmunhoz/registro-de-ponto.git
cd registro-de-ponto
composer install
```

### 3) Configurar variáveis de ambiente

Crie o seu `.env` a partir do exemplo:

```bash
cp .env.example .env
```

Edite o `.env` com as suas credenciais:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=registro_ponto
DB_USER=seu_usuario
DB_PASS=sua_senha

APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=America/Sao_Paulo
```

> 💡 **Banco:** crie um schema vazio no MySQL com o nome definido em `DB_NAME`.  
> *(Se você mantém um `.sql` de seed, indique aqui o comando de import; caso contrário, siga as telas do app para criar o primeiro usuário.)*

### 4) Subir servidor embutido do PHP

Ajuste o `-t` se você usar uma pasta pública; se não houver, rode a partir da raiz do projeto.

```bash
php -S localhost:8000
```

Abra em: http://localhost:8000

---

## 📝 Licença

Este projeto está sob a licença **MIT**. Veja o arquivo `LICENSE` para mais detalhes.

---

## 🤝 Contribuições

Sugestões, issues e PRs são bem-vindos!  

