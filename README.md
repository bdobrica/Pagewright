# Pagewright

**Pagewright** is a simple, WordPress-style CMS that lets you build and manage a website by *writing instructions*, not configuring databases or installing plugins.

You tell Pagewright what you want to change.  
Pagewright compiles your content into HTML.  
Your site runs anywhere that supports PHP - even the cheapest shared hosting.

---

## What is Pagewright?

Pagewright is a **file-based CMS** powered by large language models (LLMs) and designed for **non-technical users**.

Unlike traditional CMS platforms:

- There is **no database**
- There is **no local development environment**
- There is **no build toolchain**
- There is **no command line**

Everything happens **on the hosting server**, just like WordPress.

---

## Core ideas

- **Markdown is the source of truth**  
  All site content lives in plain Markdown files.

- **HTML is compiled on the server**  
  When you publish, Pagewright converts Markdown into static HTML files.

- **Themes are contracts**  
  Themes define layout regions (header, content, sidebar, footer) and allowed components.

- **LLMs are editors, not magic**  
  The AI proposes structured changes that are validated before being applied.

- **Files over databases**  
  Users, settings, history, and content are stored as files for maximum portability.

---

## Who is this for?

Pagewright is designed for:

- Non-technical users who want to run a website
- People used to WordPress-style hosting (cPanel, FTP, ZIP uploads)
- Small business sites, portfolios, landing pages, documentation sites
- Anyone who wants **clarity and control** over their content

If you’re a developer who enjoys Docker, Node.js, and CI pipelines - this is probably *not* your tool.  
If you want to upload a ZIP and start editing your site in a browser - it is.

---

## Features (current and planned)

### Current
- PHP-based admin panel
- OAuth login (Google, GitHub)
- First-login bootstrap (first user becomes admin)
- File-based admin storage (no DB)
- PicoCSS-based UI
- Shared-hosting compatible

### Planned
- Prompt → Preview → Publish workflow
- Markdown-to-HTML compiler
- Theme contracts (layout + allowed components)
- Sidebar widgets (WordPress-style)
- Media library (images, PDFs, documents)
- Patch history and rollback
- Multiple themes
- Plugin-style content components

---

## How Pagewright works

1. You upload Pagewright to your hosting account
2. You visit `/pw-admin` in your browser
3. You sign in using OAuth (Google or GitHub)
4. You describe changes in natural language
5. Pagewright:
   - updates Markdown files
   - compiles HTML
   - shows a preview
6. You publish

No database migrations.  
No FTP uploads after installation.  
No local tooling.

---

## Installation

### Option 1: Docker (for development)

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/pagewright.git
   cd pagewright
   ```

2. **Create your environment file**
   ```bash
   cp .env.example .env
   ```

3. **Configure your credentials**
   
   Edit `.env` and add your OAuth credentials:
   
   - **Google OAuth**: Get credentials from [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
     - Create OAuth 2.0 Client ID
     - Set redirect URI: `http://localhost:8880/pw-admin/oauth/callback.php`
   
   - **GitHub OAuth**: Get credentials from [GitHub Settings](https://github.com/settings/developers)
     - Create new OAuth App
     - Set callback URL: `http://localhost:8880/pw-admin/oauth/callback.php`
   
   - **OpenAI API**: Get your key from [OpenAI Platform](https://platform.openai.com/api-keys)

4. **Start the containers**
   ```bash
   docker-compose up -d
   ```

5. **Verify installation**
   
   Visit [http://localhost:8880/pw-admin/health.php](http://localhost:8880/pw-admin/health.php) to check system status.

6. **Access the admin panel**
   
   Open [http://localhost:8880/pw-admin](http://localhost:8880/pw-admin) and sign in with OAuth.

> 💡 **First login creates the admin user**. The first person to sign in becomes the site administrator.

### Option 2: Shared hosting (for production)

1. Download the Pagewright ZIP
2. Upload it to your hosting account (via cPanel or FTP)
3. Copy `.env.example` to `.env` and configure your credentials
4. Make sure PHP 8.x is enabled
5. Visit `/pw-admin/health.php` to verify your setup
6. Visit `/pw-admin` and sign in with OAuth
7. Start building

> ⚠️ The `pw-storage/` directory must be writable by PHP.
> ⚠️ Use HTTPS URLs for OAuth callbacks in production.

---

## Requirements

- PHP 8.0+
- cURL extension enabled
- File write permissions
- Apache or compatible web server

Tested with typical shared hosting environments.

---

## Folder structure (simplified)

pagewright/
admin/ # Admin panel (OAuth, editor, dashboard)
app/ # Core logic (compiler, themes, validation)
content/ # Markdown content (source of truth)
public/ # Compiled HTML output
uploads/ # Media library
storage/ # Users, settings, history (file-based)


---

## Security model

- OAuth authentication (no passwords stored)
- CSRF protection
- Strict file path allowlists
- No raw HTML allowed in content by default
- API keys stored encrypted at rest
- Uploads directory locked against script execution
- Full audit log of admin actions

---

## Philosophy

Pagewright is intentionally **boring** in its infrastructure and **powerful** in its editing experience.

It prioritizes:
- simplicity over flexibility
- determinism over magic
- files over databases
- clarity over cleverness

LLMs are used as **assistants**, not authorities.

---

## Development and Testing

The `/tests` directory contains test scripts for verifying core functionality:

- `test-llm.php` - LLM integration tests (OpenAI API, edit workflow, page creation)
- `test-compiler.php` - Markdown compilation and publishing tests
- `test-operations.php` - Operations engine and rollback tests

Run tests from the `/tests` directory:

```bash
cd /tests
php test-llm.php      # Requires OPENAI_API_KEY in .env
php test-compiler.php
php test-operations.php
```

The `/pagewright` directory is designed to be a clean release package (tests excluded).

---

## Status

Pagewright is in **early development**.

Expect:
- breaking changes
- incomplete features
- evolving architecture

Feedback and ideas are welcome.

---

## License

[Apache License, Version 2](./LICENSE)

---

## Name

**Pagewright** - because pages should be *written*, not engineered.
