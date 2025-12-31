# Pagewright - Development TODO

**Last Updated:** December 31, 2025

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

- [ ] **Validate provider parameter** - Whitelist check before passing to OAuthManager
  - File: `pagewright/pw-admin/index.php:10`

- [ ] **Handle missing email from GitHub** - Explicit handling when email is private/null
  - File: `pagewright/pw-admin/libs/oauth/GitHubProvider.php:50`

### Code Quality

- [x] **Add structured logging** - Log critical events (login, admin creation, errors) ✅ 2025-12-31
  - Scope: All critical operations
  - Format: JSON logs to storage directory

- [x] **Refactor HTTP client** - Extract duplicated cURL code to shared utility ✅ 2025-12-31
  - Files: `GoogleProvider.php`, `GitHubProvider.php`
  - New: `pagewright/pw-admin/libs/util/HttpClient.php`

- [ ] **Add type declarations** - Complete return types and PHPDoc annotations
  - Files: All classes, especially `Storage.php`
  - Format: `@return array{provider: string, subject: string, ...}`

- [ ] **Verify secret file creation** - Ensure secret key is written successfully
  - File: `pagewright/pw-admin/libs/util/Storage.php:16`

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

- [ ] **Implement Registry pattern** - Replace global constants with configuration object
- [ ] **Add Router** - Replace query parameter-based routing
- [ ] **Create HTTP Response abstraction** - Replace raw `header()` calls
- [ ] **Add Dependency Injection** - Improve testability
- [ ] **Implement Event System** - Enable plugin extensibility

### Operational

- [ ] **Add monitoring hooks** - Health metrics, error tracking
- [ ] **Implement backup system** - Automated storage directory backups
- [ ] **Add audit log viewer** - UI for reviewing admin actions
- [ ] **Performance optimization** - Caching, opcode optimization

---

## 📋 Progress Tracking

### Completion Status
- **Critical Issues:** 5/5 (100%) ✅
- **High Priority:** 9/14 (64%)
- **Medium Priority:** 0/11 (0%)
- **Low Priority:** 0/8 (0%)

### Overall: 14/38 items complete (37%)

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
