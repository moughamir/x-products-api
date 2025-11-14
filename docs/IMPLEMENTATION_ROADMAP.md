# Implementation Roadmap - Production Launch

**Project**: X-Products API Admin Panel Completion  
**Goal**: Replace all placeholder pages with production-ready implementations  
**Timeline**: 5-8 days (40-60 hours)

---

## Overview

This roadmap outlines the step-by-step implementation plan to complete all missing features and replace placeholder content with production-ready code.

---

## Phase 1: Foundation (Day 1-2)

### Task 1.1: Create Missing Models (4 hours)

**Files to Create**:
- `src/Models/Collection.php`
- `src/Models/Category.php`
- `src/Models/Tag.php`
- `src/Models/ApiKey.php`
- `src/Models/Setting.php`

**Each Model Should Include**:
- Properties matching database schema
- `toArray()` method for API responses
- Validation rules
- Relationships to other models

**Example Structure** (Collection.php):
```php
class Collection {
    public int $id;
    public string $title;
    public string $handle;
    public ?string $description;
    public ?string $image_url;
    public bool $is_smart;
    public ?string $rules;
    public string $sort_order;
    public bool $is_featured;
    
    // CRUD methods
    public static function find(PDO $db, int $id): ?self;
    public static function findByHandle(PDO $db, string $handle): ?self;
    public static function all(PDO $db, int $page = 1, int $limit = 50): array;
    public function save(PDO $db): bool;
    public function delete(PDO $db): bool;
    public function getProducts(PDO $db): array;
    public function addProduct(PDO $db, int $productId, int $position = 0): bool;
    public function removeProduct(PDO $db, int $productId): bool;
}
```

---

### Task 1.2: Create Service Classes (4 hours)

**Files to Create**:
- `src/Services/CollectionService.php`
- `src/Services/CategoryService.php`
- `src/Services/TagService.php`
- `src/Services/ApiKeyService.php`
- `src/Services/SettingService.php`
- `src/Services/ProductManagementService.php`

**Key Features**:

**CollectionService.php**:
- Smart collection rule evaluation
- Product auto-assignment based on rules
- Manual product ordering
- Collection statistics

**CategoryService.php**:
- Hierarchical tree building
- Parent-child relationship management
- Category path generation (breadcrumbs)
- Product count per category

**TagService.php**:
- Tag normalization (slug generation)
- Tag merging (combine multiple tags)
- Usage statistics
- Auto-suggest for tagging

**ApiKeyService.php**:
- Secure key generation (random 64-char string)
- Key hashing for storage
- Key validation
- Usage tracking and rate limiting

**ProductManagementService.php**:
- Extends ProductService with admin features
- Bulk operations (delete, update, assign)
- Image upload handling
- Variant/option management
- Inventory updates

---

## Phase 2: Product Management (Day 2-3)

### Task 2.1: Product Controller (3 hours)

**File**: `src/Controllers/Admin/ProductController.php`

**Methods**:
- `index()` - List products with search, filters, pagination
- `create()` - Show create form
- `store()` - Save new product
- `edit($id)` - Show edit form
- `update($id)` - Update product
- `delete($id)` - Delete product
- `bulkDelete()` - Delete multiple products
- `bulkAssign()` - Assign products to collection/category

---

### Task 2.2: Product Templates (5 hours)

**Files to Create**:
- `templates/admin/products/index.html.twig`
- `templates/admin/products/create.html.twig`
- `templates/admin/products/edit.html.twig`
- `templates/admin/products/_form.html.twig` (shared form partial)

**Features**:

**index.html.twig**:
- Data table with sortable columns
- Search bar (title, handle, SKU)
- Filters (category, collection, tag, in stock, price range)
- Bulk selection checkboxes
- Pagination
- Quick actions (edit, delete, view)
- "Add Product" button

**create.html.twig / edit.html.twig**:
- Product information (title, handle, description)
- Pricing (price, compare_at_price)
- Images (upload multiple, drag-to-reorder, set featured)
- Inventory (SKU, quantity, track inventory)
- Organization (vendor, product_type, tags, collections, categories)
- Variants (add/remove, options like size/color)
- SEO (meta title, description)
- Status (active/draft)

---

## Phase 3: Collections, Categories, Tags (Day 3-4)

### Task 3.1: Collections Management (4 hours)

**Controller**: `src/Controllers/Admin/CollectionController.php`  
**Templates**: `templates/admin/collections/`

**Features**:
- List all collections (manual + smart)
- Create/edit collection form
- Manual collection: drag-and-drop product assignment
- Smart collection: rule builder UI
  - Condition types: tag contains, price range, product type, vendor, in stock
  - AND/OR logic between conditions
  - Live preview of matching products
- Featured toggle
- Sort order options (manual, price asc/desc, newest, bestselling)

---

### Task 3.2: Categories Management (4 hours)

**Controller**: `src/Controllers/Admin/CategoryController.php`  
**Templates**: `templates/admin/categories/`

**Features**:
- Hierarchical tree view (nested list with expand/collapse)
- Create/edit category form
- Parent category dropdown
- Drag-and-drop reordering
- Product assignment (search and add products)
- Category image upload
- Breadcrumb display
- Product count per category

---

### Task 3.3: Tags Management (3 hours)

**Controller**: `src/Controllers/Admin/TagController.php`  
**Templates**: `templates/admin/tags/`

**Features**:
- Tag list with usage count
- Create/edit/delete tags
- Merge tags (select multiple, merge into one)
- Bulk delete
- Search tags
- View products by tag
- Auto-suggest when typing

---

## Phase 4: System Features (Day 4-5)

### Task 4.1: Activity Log (3 hours)

**Controller**: `src/Controllers/Admin/ActivityController.php`  
**Template**: `templates/admin/activity/index.html.twig`

**Features**:
- Activity log table with pagination
- Columns: timestamp, user, action, entity type, entity ID, description
- Filters:
  - User dropdown
  - Action type (create, update, delete, login, logout)
  - Entity type (product, collection, category, tag, user)
  - Date range picker
- Search by description
- Export to CSV button
- Real-time updates (optional, using polling or WebSockets)

---

### Task 4.2: API Keys Management (6 hours)

**Controller**: `src/Controllers/Admin/ApiKeyController.php`  
**Template**: `templates/admin/api-keys/`

**Features**:
- API key list table
- Generate new key button
  - Name/description
  - Expiration date (optional)
  - Permissions/scopes (read-only, read-write)
  - Rate limit (requests per minute)
- Show key only once after generation (security)
- Revoke/delete key
- Usage statistics (total requests, last used)
- Key rotation (generate new, deprecate old)

**Database Migration Needed**:
```sql
CREATE TABLE IF NOT EXISTS api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    key_hash VARCHAR(255) UNIQUE NOT NULL,
    key_prefix VARCHAR(10) NOT NULL,
    permissions TEXT,
    rate_limit INTEGER DEFAULT 60,
    expires_at DATETIME,
    last_used_at DATETIME,
    total_requests INTEGER DEFAULT 0,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
);
```

---

### Task 4.3: User Profile (4 hours)

**Controller**: `src/Controllers/Admin/ProfileController.php`  
**Template**: `templates/admin/profile/`

**Features**:
- View profile information
- Edit form (full name, email)
- Change password (current password, new password, confirm)
- Activity history (last 50 actions)
- Active sessions list
  - IP address, user agent, last activity
  - "Logout from this device" button
- Two-factor authentication setup (optional)

---

### Task 4.4: Settings Page (4 hours)

**Controller**: `src/Controllers/Admin/SettingsController.php`  
**Template**: `templates/admin/settings/index.html.twig`

**Features**:
- Tabbed interface:
  - **General**: Site name, description, timezone, date format
  - **Products**: Default currency, items per page, image settings
  - **Email**: SMTP configuration (host, port, username, password)
  - **System**: Cache management, database info, PHP info
- Save button per tab
- Success/error messages

**Database Migration Needed**:
```sql
CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value TEXT,
    type VARCHAR(20) DEFAULT 'string',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## Phase 5: Integration & Testing (Day 5-6)

### Task 5.1: Update Routes (1 hour)

**File**: `src/App.php`

Replace all PlaceholderController routes with actual controllers:
```php
// Product Management
$group->get('/products', [ProductController::class, 'index']);
$group->get('/products/new', [ProductController::class, 'create']);
$group->post('/products', [ProductController::class, 'store']);
$group->get('/products/{id}/edit', [ProductController::class, 'edit']);
$group->post('/products/{id}', [ProductController::class, 'update']);
$group->post('/products/{id}/delete', [ProductController::class, 'delete']);

// Collections
$group->get('/collections', [CollectionController::class, 'index']);
// ... etc
```

---

### Task 5.2: Update DI Container (1 hour)

**File**: `src/App.php`

Add all new controllers and services to dependency injection:
```php
ProductController::class => \DI\autowire()
    ->constructorParameter('productsDb', get(PDO::class))
    ->constructorParameter('adminDb', get('AdminPDO')),

CollectionService::class => \DI\autowire()
    ->constructorParameter('db', get(PDO::class)),
```

---

### Task 5.3: Testing (8 hours)

**Test Each Feature**:
1. Product CRUD operations
2. Collection management (manual + smart)
3. Category hierarchy
4. Tag operations and merging
5. Activity log filtering
6. API key generation and validation
7. Profile editing and password change
8. Settings persistence

**Test Cases**:
- Create, read, update, delete for each entity
- Validation errors (empty fields, duplicates)
- Pagination and search
- Bulk operations
- File uploads (images)
- Permissions (different user roles)
- Edge cases (empty states, large datasets)

---

## Phase 6: Polish & Documentation (Day 6-7)

### Task 6.1: UI/UX Improvements (4 hours)

- Add loading spinners for async operations
- Improve error messages
- Add confirmation dialogs for destructive actions
- Implement toast notifications for success/error
- Responsive design testing (mobile, tablet)
- Accessibility improvements (ARIA labels, keyboard navigation)

---

### Task 6.2: Documentation (4 hours)

**Update Files**:
- `README.md` - Add admin panel documentation
- `ADMIN_USER_GUIDE.md` - Create user guide for admin features
- `API_DOCUMENTATION.md` - Update API docs with new endpoints
- `DEPLOYMENT_GUIDE.md` - Update deployment instructions

---

## Phase 7: Production Deployment (Day 7-8)

### Task 7.1: Pre-Deployment Checklist

- [ ] All placeholder routes removed
- [ ] All features tested and working
- [ ] Database migrations run successfully
- [ ] Error handling implemented
- [ ] Logging configured
- [ ] Security review completed
- [ ] Performance testing done
- [ ] Backup strategy in place

### Task 7.2: Deployment Steps

1. Backup current database
2. Run database migrations
3. Deploy new code
4. Clear caches
5. Test critical paths
6. Monitor error logs
7. Verify all features working

---

## Success Criteria

✅ **All placeholder pages replaced with functional implementations**  
✅ **Full CRUD operations for products, collections, categories, tags**  
✅ **Activity logging and API key management working**  
✅ **User profile and settings functional**  
✅ **All tests passing**  
✅ **Documentation complete**  
✅ **Production deployment successful**

---

**Ready to start implementation? Let's begin with Phase 1!**

