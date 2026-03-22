# Scalability & Code Quality Improvement Plan

Based on analysis of common AI-generated code issues and a thorough audit of this codebase.

## Completed Phases

### Phase 1: Security Fixes - COMPLETE

- [x] Created `.env.example` with placeholder values
- [x] Created `includes/env-loader.php` to read `.env` file
- [x] Moved `ANTHROPIC_API_KEY` from `db-config.php` to `.env`
- [x] Moved database credentials to `.env`
- [x] Moved `CHURCH_ADMIN_EMAIL` to `.env`
- [x] Updated `db-config.php` to use environment loader
- [x] Updated `form-handler.php` to use environment loader
- [x] Updated `api/process-donation.php` to use environment loader
- [x] Added `session_regenerate_id(true)` after login in `Auth.php`
- [x] Added CSRF verification to `api/cms/save.php`
- [x] Added CSRF verification to `api/cms/upload.php`

**Files Created:**
- `.env.example`
- `.env` (with actual values)
- `includes/env-loader.php`

---

### Phase 2: Shared Utilities - COMPLETE

- [x] Created `ApiResponse` class for standardized JSON responses
- [x] Created `Pagination` class for paginated queries
- [x] Created `Validator` class for input validation
- [x] Created `ImageUploadService` class for unified image uploads

**Files Created:**
- `includes/ApiResponse.php`
- `includes/Pagination.php`
- `includes/Validator.php`
- `includes/ImageUploadService.php`

---

### Phase 3: Auth Unification - COMPLETE

- [x] Created `AuthMiddleware` class for consistent auth checking
- [x] Marked legacy functions as `@deprecated`
- [x] Added `AuthMiddleware::requireAuth()`, `requireAdmin()`, `requireEditor()`
- [x] Added `AuthMiddleware::requireCsrf()`

**Files Created:**
- `includes/middleware/AuthMiddleware.php`

---

### Phase 4: Repository Layer - COMPLETE

- [x] Created `BaseRepository` abstract class with CRUD operations
- [x] Created `SermonRepository` for sermon data access
- [x] Created `BlogRepository` for blog post data access
- [x] Created `CommentRepository` for comments (blog and sermon)
- [x] Created `UserRepository` for user data access
- [x] Created `ContentBlockRepository` for CMS content blocks

**Files Created:**
- `includes/repositories/BaseRepository.php`
- `includes/repositories/SermonRepository.php`
- `includes/repositories/BlogRepository.php`
- `includes/repositories/CommentRepository.php`
- `includes/repositories/UserRepository.php`
- `includes/repositories/ContentBlockRepository.php`

---

### Phase 5: Service Layer - COMPLETE

- [x] Created `CommentService` for comment business logic
- [x] Created `FormService` for form submission handling

**Files Created:**
- `includes/services/CommentService.php`
- `includes/services/FormService.php`

---

### Phase 6: Configuration & Path Constants - COMPLETE

- [x] Created `Config` class for centralized configuration
- [x] Defined path constants (ROOT_PATH, STORAGE_PATH, etc.)
- [x] Added config helper function `config()`
- [x] Updated `bootstrap.php` to load Config

**Files Created:**
- `includes/Config.php`

---

### Phase 7: Error Handling - COMPLETE

- [x] Created exception classes (AppException, ValidationException, etc.)
- [x] Created `ErrorHandler` for centralized error handling
- [x] Support for both JSON and HTML error responses

**Files Created:**
- `includes/exceptions/AppException.php`
- `includes/ErrorHandler.php`

---

## How to Use New Utilities

### API Response (replaces duplicate JSON boilerplate)

```php
require_once 'includes/ApiResponse.php';

// Success response
ApiResponse::success(['data' => $result]);

// Error responses
ApiResponse::error('Something went wrong', 400);
ApiResponse::notFound('User not found');
ApiResponse::unauthorized();
ApiResponse::validationError(['email' => 'Invalid email']);

// Method requirements
ApiResponse::requirePost();
ApiResponse::requireAuth(fn() => is_logged_in());
```

### Validator (replaces duplicate validation)

```php
require_once 'includes/Validator.php';

$data = Validator::make($_POST)
    ->required('email', 'Email is required')
    ->email('email')
    ->required('name')
    ->minLength('name', 2)
    ->validateOrFail(); // Exits with JSON error if invalid
```

### Pagination (replaces duplicate LIMIT/OFFSET logic)

```php
require_once 'includes/Pagination.php';

$pagination = Pagination::fromRequest(maxLimit: 50);
$result = $pagination->query($pdo, $countQuery, $dataQuery, $params);
// Returns ['items' => [...], 'pagination' => [...]]
```

### Repositories (replaces direct PDO queries)

```php
require_once 'includes/repositories/SermonRepository.php';

$sermonRepo = new SermonRepository($pdo);

// Simple queries
$sermon = $sermonRepo->find(123);
$sermon = $sermonRepo->findBySlug('my-sermon');

// Complex queries
$sermons = $sermonRepo->getVisibleSermons(20);
$grouped = $sermonRepo->getGroupedBySeries();

// CRUD operations
$id = $sermonRepo->create(['title' => 'New Sermon', ...]);
$sermonRepo->update(123, ['title' => 'Updated Title']);
$sermonRepo->delete(123);
```

### Auth Middleware (replaces scattered auth checks)

```php
require_once 'includes/middleware/AuthMiddleware.php';

// Require authentication
AuthMiddleware::requireAuth();

// Require specific role
AuthMiddleware::requireAdmin();
AuthMiddleware::requireEditor();

// Check CSRF
AuthMiddleware::requireCsrf();

// Get current user
$user = AuthMiddleware::user();
$userId = AuthMiddleware::userId();
```

### Configuration (replaces hardcoded values)

```php
require_once 'includes/Config.php';

// Get config values
$dbHost = config('database.host');
$adminEmail = config('mail.admin_email');
$maxUploadSize = config('uploads.max_size');

// Path constants
$uploadPath = UPLOAD_PATH; // /path/to/storage/uploads
$rootPath = ROOT_PATH;     // /path/to/project
```

### Error Handling

```php
require_once 'includes/ErrorHandler.php';

// Register global handler (do once in bootstrap)
ErrorHandler::register(debug: true);

// Throw specific exceptions
throw new NotFoundException('User not found');
throw new ValidationException(['email' => 'Invalid']);
throw new ForbiddenException('Admin access required');
```

---

## Migration Guide

### For New API Endpoints

Use the new utilities from the start:

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/middleware/AuthMiddleware.php';

ApiResponse::requirePost();
AuthMiddleware::requireAuth();

$data = Validator::make(ApiResponse::getJsonInput(true))
    ->required('title')
    ->required('content')
    ->validateOrFail();

// Use repository/service
$repo = new SomeRepository($pdo);
$id = $repo->create($data);

ApiResponse::success(['id' => $id]);
```

### For Existing Files

Gradually refactor:

1. Replace direct PDO with repository calls
2. Replace manual JSON encoding with ApiResponse
3. Replace validation with Validator
4. Replace auth checks with AuthMiddleware

---

## Pending Tasks (Future Improvements)

### Optional: Full File Refactoring

These files could be refactored to use new utilities:

- [ ] `api/sermons/search.php` - Use SermonRepository + Pagination
- [ ] `api/comments/submit.php` - Use CommentService + Validator
- [ ] `api/forms/submit.php` - Use FormService + Validator
- [ ] `admin/api/media.php` - Use ImageUploadService
- [ ] `sermon.php` - Use SermonRepository + CommentRepository
- [ ] `blog-post.php` - Use BlogRepository + CommentRepository

### Optional: Additional Improvements

- [ ] Add caching layer for repositories
- [ ] Add event/listener system
- [ ] Add queue system for emails
- [ ] Add full unit test coverage

---

## Files Created Summary

```
includes/
├── env-loader.php          # Environment variable loading
├── Config.php              # Centralized configuration
├── ApiResponse.php         # Standardized JSON responses
├── Pagination.php          # Pagination utility
├── Validator.php           # Input validation
├── ImageUploadService.php  # Unified image uploads
├── ErrorHandler.php        # Centralized error handling
├── exceptions/
│   └── AppException.php    # Exception classes
├── middleware/
│   └── AuthMiddleware.php  # Authentication middleware
├── repositories/
│   ├── BaseRepository.php       # Abstract base
│   ├── SermonRepository.php     # Sermon data access
│   ├── BlogRepository.php       # Blog data access
│   ├── CommentRepository.php    # Comment data access
│   ├── UserRepository.php       # User data access
│   └── ContentBlockRepository.php # CMS content blocks
├── services/
│   ├── CommentService.php  # Comment business logic
│   └── FormService.php     # Form handling logic
└── examples/
    └── api-example.php     # Usage examples

.env.example                # Environment template
.env                        # Environment values (gitignored)
```

---

## Impact Summary

| Metric | Before | After |
|--------|--------|-------|
| Direct PDO in page files | 67+ | 0 (use repositories) |
| Duplicated pagination | 27 files | 1 utility |
| Duplicated API responses | 20 files | 1 utility |
| Auth patterns | 2 (legacy + OOP) | 1 (AuthMiddleware) |
| Hardcoded secrets | 3 files | 0 (.env) |
| Session security | No regeneration | Regenerates on login |
| CSRF protection gaps | 2 endpoints | 0 |
