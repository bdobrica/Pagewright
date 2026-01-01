# Pagewright - Development TODO

**Last Updated:** December 31, 2025

**Status:** Phase 4 Complete! 🎉 LLM integration fully operational with natural language editing.

---

## 🔴 Critical Issues (Fix Immediately)

### Security & Stability

- [x] **Fix undefined constant error** - Define `ADMIN_BASE_URL` in `config.php` or remove all references in `Http.php` ✅ 2025-12-31
  - File: `pagewright/pw-admin/libs/util/Http.php:9`
  - Impact: PHP warnings/errors in production

- [x] **Add CSRF protection to login form** - Add token generation and validation ✅ 2025-12-31
  - File: `pagewright/pw-admin/index.php:12-23`
  - Impact: Form submission vulnerability

- [x] **Implement session regeneration** - Call `session_regenerate_id(true)` after successful login ✅ 2025-12-31
  - File: `pagewright/pw-admin/libs/security/Session.php:18`
  - Impact: Session fixation vulnerability

- [x] **Add file operation error handling** - Validate writability and handle failures ✅ 2025-12-31
  - Files: `pagewright/pw-admin/libs/util/Storage.php`
  - Impact: Silent failures on permission errors

- [x] **Strengthen .htaccess protection** - Ensure Apache deployments protect storage directory ✅ 2025-12-31
  - File: `pagewright/pw-storage/.htaccess`
  - Impact: Potential data exposure

---

## 🟡 High Priority (Before Public Release)

### Configuration & Setup

- [x] **Create `.env.example` template** - Document required environment variables ✅ 2025-12-31
  - Location: Root directory
  - Content: OAuth credentials template

- [x] **Add health check endpoint** - Verify PHP version, extensions, storage writability ✅ 2025-12-31
  - Location: `pagewright/pw-admin/health.php`
  - Purpose: Installation validation

- [x] **Document Docker environment setup** - Clarify .env file structure in README ✅ 2025-12-31
  - File: `README.md`

- [x] **Make Docker port configurable** - Move hardcoded 8880 to .env variable ✅ 2025-12-31
  - File: `docker-compose.yaml:6`

### Security Hardening

- [x] **Implement session timeout** - Add `last_activity` tracking and expiration ✅ 2025-12-31
  - File: `pagewright/pw-admin/libs/security/Session.php`
  - Suggested: 30-minute timeout

- [x] **Add rate limiting** - Protect OAuth callbacks and login attempts ✅ 2025-12-31
  - Scope: OAuth flow, login form
  - Consider: Simple file-based rate limiter

- [x] **Sanitize error messages** - Use generic messages in production, log details ✅ 2025-12-31
  - Files: `pagewright/pw-admin/index.php:47`, OAuth providers
  - Impact: Prevent information disclosure

- [x] **Validate provider parameter** - Whitelist check before passing to OAuthManager ✅ 2026-01-01
  - File: `pagewright/pw-admin/index.php:10`
  - Added validation against allowed providers list before OAuthManager call

- [x] **Handle missing email from GitHub** - Explicit handling when email is private/null ✅ 2026-01-01
  - File: `pagewright/pw-admin/libs/oauth/GitHubProvider.php:50`
  - Throws clear error with instructions to make email public

### Code Quality

- [x] **Add structured logging** - Log critical events (login, admin creation, errors) ✅ 2025-12-31
  - Scope: All critical operations
  - Format: JSON logs to storage directory

- [x] **Refactor HTTP client** - Extract duplicated cURL code to shared utility ✅ 2025-12-31
  - Files: `GoogleProvider.php`, `GitHubProvider.php`
  - New: `pagewright/pw-admin/libs/util/HttpClient.php`

- [x] **Add type declarations** - Complete return types and PHPDoc annotations ✅ 2026-01-01
  - Files: All utility classes, OAuth providers, Session class
  - Format: `@return array{provider: string, subject: string, ...}`
  - Added comprehensive PHPDoc with detailed type hints

- [x] **Verify secret file creation** - Ensure secret key is written successfully ✅ 2026-01-01
  - File: `pagewright/pw-admin/libs/util/Storage.php:16`
  - Added verification by reading back written secret
  - Validates secret length before and after writing

---

## 🟢 Medium Priority (Post-Launch)

### Features

- [ ] **Admin user management UI** - Add/remove/list admins
  - Location: New dashboard section
  - Features: Invite, revoke, list active admins

- [ ] **Installation wizard** - Guide users through initial setup
  - Location: `pagewright/pw-admin/install.php`
  - Features: Check requirements, validate OAuth config

- [ ] **Dashboard improvements** - Replace empty dashboard with useful content
  - File: `pagewright/pw-admin/index.php:83`
  - Add: Quick start, feature status, documentation links

- [ ] **Loading states** - Visual feedback during OAuth redirects
  - Files: Frontend UI components

### Documentation

- [ ] **Update README folder structure** - Remove non-existent folders or mark as planned
  - File: `README.md:119-126`
  - Folders: `app/`, `content/`, `public/`, `uploads/`

- [ ] **Add PHPDoc blocks** - Comprehensive inline documentation
  - Scope: All classes and methods
  - Include: Parameters, return types, exceptions

- [ ] **Create API documentation** - Document expected array structures and interfaces
  - Format: Markdown or generated from PHPDoc

- [ ] **Write deployment guide** - Step-by-step for shared hosting vs Docker
  - Location: `DEPLOYMENT.md`

### Testing

- [ ] **Add unit tests** - Test OAuth providers, utilities, storage
  - Framework: PHPUnit
  - Coverage: Core business logic

- [ ] **Add integration tests** - Test session management, OAuth flow
  - Scope: Full authentication workflow

- [ ] **Add error scenario tests** - Test failure modes
  - Scenarios: Provider down, unwritable storage, invalid JSON

---

## 🔵 Low Priority (Future Enhancements)

### Architecture

- [x] **Implement Router** - Front-end router for clean URLs ✅ 2025-12-31
  - Created `pagewright/index.php` to serve compiled pages
  - Updated `docker/nginx/default.conf` for URL rewriting
  - Supports clean URLs: `/about` → `about.html`
  
- [ ] **Implement Registry pattern** - Replace global constants with configuration object
- [ ] **Create HTTP Response abstraction** - Replace raw `header()` calls
- [ ] **Add Dependency Injection** - Improve testability
- [ ] **Implement Event System** - Enable plugin extensibility

### Operational

- [ ] **Add monitoring hooks** - Health metrics, error tracking
- [ ] **Implement backup system** - Automated storage directory backups
- [ ] **Add audit log viewer** - UI for reviewing admin actions
- [ ] **Performance optimization** - Caching, opcode optimization

---

## � Core CMS Features (v0.2-v0.4 Implementation Plan)

### Phase 1: Define the on-disk "CMS model" ✅

**Goal:** Establish file-based content structure without LLM integration.

- [x] **Create core directory structure** ✅ 2025-12-31
  - Location: `pagewright/`
  - Directories created:
    - `pw-content/` (pages, blocks, nav.json)
    - `pw-themes/` (default theme with templates, assets, prompt.md)
    - `pw-public/` (site and preview subdirectories)
    - `pw-plugins/` (components)
    - `pw-log/` (patches, snapshots)

- [x] **Define JSON schemas and validators** ✅ 2025-12-31
  - `nav.json` structure: tree of `{label, href, pageId}` items with nested children support
  - Page front matter fields: `id`, `title`, `slug`, `updated_at`, `draft`
  - `theme.json`: regions, allowed components, tokens, template paths
  - Implemented PHP validators: `PageValidator`, `NavValidator`, `ThemeValidator`

- [x] **Create default theme structure** ✅ 2025-12-31
  - Location: `pw-themes/default/`
  - Files: `theme.json`, `prompt.md`, `templates/layout.php`, `templates/partials/`
  - Assets: `theme.css` (PicoCSS + custom styles), `theme.js`
  - Supports 12 components: hero, callout, button, card, grid, image, video, quote, code, divider, accordion, tabs

- [x] **Implement content management from disk** ✅ 2025-12-31
  - Created `ContentManager` class to scan pages, parse front matter, manage navigation
  - Created `ThemeManager` class to load themes, validate configs, manage tokens
  - Sample content: home.md and about.md with JSON front matter

### Phase 2: Build the compiler (without LLM) ✅

**Goal:** Deterministic WordPress-like rendering system.

- [x] **Integrate Markdown parser** ✅ 2025-12-31
  - Downloaded Parsedown (single-file, shared hosting friendly)
  - Created `MarkdownParser` wrapper with safe mode enabled
  - Supports GitHub-flavored markdown with breaks

- [x] **Implement component blocks system** ✅ 2025-12-31
  - Created `ComponentParser` to extract `:::component` blocks and parse parameters
  - Created `ComponentRegistry` with 10 built-in components:
    - hero, callout, button, card, grid, image, video, quote, code, divider
  - Component validation against theme's allowed list
  - Safe HTML rendering with error handling

- [x] **Build theme rendering system** ✅ 2025-12-31
  - Created `Compiler` class combining markdown, components, and templates
  - Placeholder system to prevent markdown from mangling component HTML
  - Full template variable support: `$headerHtml, $contentHtml, $sidebarHtml, $footerHtml`
  - Navigation rendering with active page detection
  - Theme assets (CSS/JS) automatically included

- [x] **Implement preview and publish workflows** ✅ 2025-12-31
  - Created `Publisher` class
  - Preview: compile to `pw-public/preview/`
  - Publish: compile to `pw-public/site/` (published pages only)
  - Auto-generate `index.html` from home page
  - Test script confirms: 2 pages compiled successfully

- [x] **Add preview mode navigation** ✅ 2026-01-01
  - Preview links append `?preview=true` for proper navigation
  - Published site has clean links without preview parameters
  - Supports both `?` and `&` for URLs with existing query parameters
  - Works in both template-based and fallback navigation rendering
  - Fixed preview URLs to use router-based clean URLs (e.g., `/contact?preview=true` instead of `/preview/contact.html`)
  - Fixed double nesting of `pw-log` directory (was creating `pw-log/pw-log/`)

### Phase 3: Change operations and changelog system ✅

**Goal:** Implement safe, reversible change engine before adding LLM.

- [x] **Design standardized operation structure** ✅ 2025-12-31
  - Created `Operation` class with 4 types: `write_file`, `delete_file`, `update_json`, `update_tokens`
  - Path validation and traversal prevention
  - Path allowlist enforcement (`pw-content/**`, `pw-themes/**/theme.json`)
  - Data type validation per operation type

- [x] **Implement patch storage system** ✅ 2025-12-31
  - Created `ChangeSet` class to group operations with metadata
  - Created `ChangeLogger` to store changes in `pw-log/patches/{changeId}/`
  - Stores `manifest.json` with full change details
  - Stores `files/{path}.before` and `files/{path}.after` snapshots
  - Full file versioning for reliable rollback

- [x] **Create operations engine** ✅ 2025-12-31
  - Created `OperationsEngine` to validate and apply operations
  - Atomic operations with automatic rollback on failure
  - Dry-run mode for validation without applying
  - Integration with ContentManager and ThemeManager
  - Proper error handling and reporting

- [x] **Implement rollback system** ✅ 2025-12-31
  - Rollback individual changes by change ID
  - Restores from "before" snapshots
  - Automatic cleanup of new files on rollback
  - Test confirms: create → rollback → restore working perfectly
  - Pruning support to limit stored changes (keep last N)

### Phase 4: LLM integration (Prompt → Preview → Publish) ✅

**Goal:** Safe, validated LLM-powered content editing.

- [x] **Add LLM provider settings** ✅ 2025-12-31
  - Added OpenAI configuration to `config.php`:
    - `OPENAI_API_KEY` (from environment or config)
    - `OPENAI_MODEL` (default: gpt-4o)
    - `OPENAI_API_BASE_URL` (allow custom endpoints)
  - Created `.env` loader in config.php for easy local development
  - Secrets stored in PHP config (more secure than JSON)

- [x] **Build LLM client abstraction** ✅ 2025-12-31
  - Created `LLMClient` abstract class for provider interface
  - Implemented `OpenAIClient` with chat completions API
  - Created `LLMFactory` to instantiate from config
  - Features: JSON mode, error handling, connection testing
  - HttpClient updated with proper JSON handling

- [x] **Implement theme prompt contracts** ✅ 2025-12-31
  - Created `PromptBuilder` to construct system/user prompts
  - System prompt loaded from theme's `prompt.md` (300+ lines)
  - Dynamic injection of theme context:
    - Allowed components from theme
    - Theme tokens reference
    - File path constraints
    - Operation types and examples
  - User prompt includes current page content and navigation

- [x] **Build validation pipeline** ✅ 2025-12-31
  - Created `OutputValidator` to parse and validate LLM JSON
  - Supports both "operations" and "ops" keys (theme uses "ops")
  - Path allowlist enforcement (`pw-content/**`, `pw-themes/default/theme.json`)
  - Page validation: front matter structure, required fields
  - Navigation validation: proper nesting, required fields
  - Component validation: only allowed components per theme
  - Path traversal prevention (no `..`)
  - Multi-line JSON front matter support

- [x] **Create complete edit workflow** ✅ 2025-12-31
  - Created `EditWorkflow` orchestrator class
  - Complete flow: Prompt → LLM → Validate → Apply → Preview → Publish
  - Features:
    - `editPage()`: Edit existing pages with natural language
    - `createPage()`: Create new pages with navigation updates
    - `previewPage()`: Generate preview HTML
    - `publishPage()`: Publish to site directory
    - `rollback()`: Undo changes by changeset ID
  - Atomic operations with automatic rollback on failure
  - Auto-preview generation after successful edits
  - Token usage tracking (prompt/completion/total)

- [x] **Add test interface** ✅ 2025-12-31
  - Created `test-llm.php` with 7 comprehensive tests:
    1. Load LLM configuration from config.php
    2. Test API connection
    3. Initialize workflow with all dependencies
    4. Edit page: Add "Why Choose Pagewright" section with 3 callout benefits
    5. Verify page changes applied
    6. Generate preview
    7. Create new contact page with nav updates
  - All tests passing ✅
  - Real OpenAI API integration working
  - Example changeset: chg_20251231_122411_1aaf50fc

### Phase 5: Media Library ✅

**Goal:** Support images and file uploads for real sites.

- [x] **Create upload system** ✅ 2026-01-01
  - Directory: `pw-public/uploads/` with thumbnails subfolder
  - Admin upload endpoint: `pw-admin/api/upload.php`
  - Store original files with safe naming (ID + timestamp)
  - Generate thumbnails for images (PHP GD)
  - Store metadata in `pw-storage/media.json`
  - Security: .htaccess prevents PHP execution in uploads

- [x] **Integrate media with LLM** ✅ 2026-01-01
  - Include media manifest in LLM context in `PromptBuilder`
  - LLM references media by URL from manifest
  - Attached files highlighted in prompt as "Just Uploaded"
  - Full media library shown with URLs and metadata
  - Validate media references in operations

- [x] **Build media library UI** ✅ 2026-01-01
  - File upload in prompt interface with "📎 Attach Files" button
  - Grid view of uploaded media in collapsible section
  - Thumbnail previews for images
  - Copy URL functionality
  - Delete media functionality
  - API endpoints: `api/upload.php`, `api/media.php`

### Phase 6: WordPress-like UX polish ✅

**Goal:** Production-ready user experience.

- [x] **Create admin dashboard with editor UI** ✅ 2025-12-31
  - Built complete editing interface in `pw-admin/index.php`
  - Two-column layout: Edit controls + Preview
  - Page selector dropdown
  - Natural language instruction input
  - Action buttons: Edit, Create, Preview, Publish
  - Auto-preview after edits
  - Status messages and error handling
  - Changeset info display with token usage

- [x] **Create API endpoint for LLM operations** ✅ 2025-12-31
  - Created `pw-admin/api/edit.php`
  - Actions: list_pages, edit_page, create_page, preview_page, publish_page, publish_all, rollback
  - JSON API with authentication
  - Full error handling
  - Integrated with EditWorkflow

- [x] **Add frontend JavaScript** ✅ 2025-12-31
  - Created `pw-admin/assets/js/editor.js`
  - Async API calls with proper error handling
  - Loading states with animations
  - Real-time form validation
  - Preview iframe integration
  - Status message system

- [x] **Add CSS styling** ✅ 2025-12-31
  - Created `pw-admin/assets/css/editor.css`
  - Professional grid layout
  - Responsive design
  - Status message variants
  - Loading animations
  - Preview frame styling

- [ ] **Create install wizard**
  - Check PHP version and extensions
  - Verify directory writability
  - Create required folders
  - Set base URL
  - Configure OAuth
  - Test compilation

- [ ] **Build theme gallery**
  - Browse available themes
  - Preview theme (live demo)
  - Activate theme
  - Theme settings editor

- [ ] **Add component library browser**
  - Gallery of available components/plugins
  - Component preview
  - Insert component into page

- [ ] **Implement page management UI**
  - Page tree view (hierarchical)
  - Slug editor
  - Draft vs published status
  - Create/delete pages
  - Reorder pages

- [ ] **Build menu editor**
  - Visual menu builder
  - Drag-drop reordering
  - Add/remove menu items
  - Edit labels and links
  - Save to `nav.json`

- [ ] **Add repair tools**
  - Rebuild site from content (force recompile)
  - Validate all content files
  - Fix broken references
  - Clear preview cache

### Implementation Priority (Next 7 Days)

Tight sequence for rapid progress:

1. [x] **Day 1-2:** Create directory structure and sample content ✅ 2025-12-31
   - Created `pw-content/pages/home.md` with JSON front matter
   - Created `pw-content/pages/about.md`
   - Created `pw-content/nav.json` with nested menu structure
   - Created default theme in `pw-themes/default/` with 12 components

2. [x] **Day 2-3:** Implement basic compiler ✅ 2025-12-31
   - Integrated Parsedown for Markdown → HTML
   - Implemented component parser with `:::component` syntax
   - Created placeholder system to prevent markdown escaping HTML
   - Theme layout injection with template variables

3. [x] **Day 3-4:** Preview system ✅ 2025-12-31
   - Compile to `pw-public/preview/`
   - Compile to `pw-public/site/` for published pages
   - Auto-generate index.html from home page

4. [x] **Day 4-5:** Changelog system ✅ 2025-12-31
   - Save before/after files in `pw-log/patches/{changeId}/`
   - Store full manifest.json with metadata
   - Implement restore functionality with file snapshots

5. [x] **Day 5-6:** Operations engine ✅ 2025-12-31
   - Implemented 4 operation types: write_file, delete_file, update_json, update_tokens
   - Path validation and allowlist enforcement
   - Atomic operations with automatic rollback on failure

6. [x] **Day 6-7:** Basic LLM integration ✅ 2025-12-31
   - OpenAI client with JSON mode
   - Theme-aware prompt construction
   - Complete validation pipeline
   - Edit and create workflows
   - Auto-preview generation
   - Full test suite passing

7. [x] **Day 7:** Router and Admin UI ✅ 2025-12-31
   - Created front-end router for clean URLs
   - Updated nginx configuration for URL rewriting
   - Built complete admin editing interface
   - Created API endpoint for LLM operations
   - Added frontend JavaScript with async calls
   - Styled with professional CSS
   - Full test suite passing

---

## 📋 Progress Tracking

### Completion Status
- **Critical Issues:** 5/5 (100%) ✅
- **High Priority:** 14/14 (100%) ✅ All High Priority Items Complete!
- **Medium Priority:** 0/11 (0%)
- **Low Priority:** 1/9 (11%) - Router implemented ✅
- **Phase 1 (CMS Model):** 4/4 (100%) ✅
- **Phase 2 (Compiler):** 5/5 (100%) ✅ - Preview navigation added
- **Phase 3 (Changelog):** 4/4 (100%) ✅
- **Phase 4 (LLM Integration):** 6/6 (100%) ✅
- **Phase 5 (Media Library):** 3/3 (100%) ✅ Complete LLM-first workflow
- **Phase 6 (UX Polish):** 4/10 (40%) - Admin UI complete ✅
- **7-Day Sprint:** 7/7 (100%) ✅

### Overall: 51/81 items complete (63%)

---

## 🎯 Recommended Order of Implementation

1. Fix undefined `ADMIN_BASE_URL` constant
2. Add CSRF protection and session regeneration
3. Create `.env.example` and documentation
4. Add file operation error handling
5. Implement session timeout
6. Add health check endpoint
7. Extract HTTP client utility
8. Add structured logging
9. Implement rate limiting
10. Complete remaining high-priority items

---

## Notes

- Mark items with `[x]` when completed
- Add completion dates next to checked items
- Document breaking changes in commit messages
- Update progress tracking section regularly
- Consider adding issue/PR references for tracking
