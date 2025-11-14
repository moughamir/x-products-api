# Admin Dashboard Implementation Plan

## Overview

Comprehensive plan for implementing full CRUD functionality for all admin dashboard sections, replacing the current placeholder pages with working features.

**Date**: October 6, 2025  
**Status**: Planning Phase  
**Priority**: Priority 2 (after critical production fix)

---

## Current Status

### ✅ Completed (Phase 1)

- [x] Authentication system (login/logout)
- [x] Session management
- [x] CSRF protection
- [x] Activity logging infrastructure
- [x] Dashboard homepage with statistics
- [x] Placeholder pages for all sections
- [x] Navigation and layout
- [x] User profile integration

### 🔄 In Progress (Phase 2)

- [ ] User Management CRUD
- [ ] Activity Log viewer
- [ ] Product Management
- [ ] Collections Management
- [ ] Categories Management
- [ ] Tags Management
- [ ] API Keys Management
- [ ] Profile & Settings pages

---

## Implementation Priority

### Priority 1: Core Admin Features (Week 1)

1. **User Management** - Critical for admin operations
2. **Activity Log Viewer** - Essential for audit trail
3. **Profile Management** - User self-service

### Priority 2: Product Management (Week 2)

4. **Product List & Search** - Core business functionality
5. **Product Edit** - Modify existing products
6. **Collections Management** - Group products

### Priority 3: Content Management (Week 3)

7. **Categories Management** - Organize products
8. **Tags Management** - Product tagging
9. **API Keys Management** - External integrations

### Priority 4: System Settings (Week 4)

10. **Settings Page** - System configuration
11. **Advanced Features** - Bulk operations, exports

---

## Detailed Task Breakdown

## 1. User Management

**Route**: `/cosmos/admin/users`  
**Estimated Time**: 8 hours  
**Priority**: HIGH

### Features

- [ ] User list with pagination (50 per page)
- [ ] Search by username, email, full name
- [ ] Filter by role, status (active/inactive)
- [ ] Sort by name, email, created date, last login
- [ ] Create new user form
- [ ] Edit user form
- [ ] Delete user (with confirmation)
- [ ] Activate/deactivate user toggle
- [ ] Role assignment dropdown
- [ ] Password reset functionality
- [ ] View user activity history

### Files to Create

```
src/Controllers/Admin/UserController.php
templates/admin/users/index.html.twig
templates/admin/users/create.html.twig
templates/admin/users/edit.html.twig
templates/admin/users/view.html.twig
```

### Database Queries

```sql
-- List users with pagination
SELECT u.*, r.name as role_name, r.permissions
FROM admin_users u
LEFT JOIN admin_roles r ON u.role_id = r.id
ORDER BY u.created_at DESC
LIMIT :limit OFFSET :offset

-- Search users
WHERE u.username LIKE :search
   OR u.email LIKE :search
   OR u.full_name LIKE :search

-- Get user activity
SELECT * FROM admin_activity_log
WHERE user_id = :user_id
ORDER BY created_at DESC
LIMIT 50
```

### Validation Rules

- Username: 3-50 chars, alphanumeric + underscore
- Email: Valid email format
- Password: Min 8 chars, at least 1 uppercase, 1 lowercase, 1 number
- Full name: 2-100 chars
- Role: Must exist in admin_roles table

---

## 2. Activity Log Viewer

**Route**: `/cosmos/admin/activity`  
**Estimated Time**: 4 hours  
**Priority**: HIGH

### Features

- [ ] Activity log list with pagination
- [ ] Filter by user
- [ ] Filter by action (create, update, delete, login, logout)
- [ ] Filter by resource (user, product, collection, etc.)
- [ ] Filter by date range
- [ ] Search in details field
- [ ] Export to CSV
- [ ] Auto-refresh option (every 30 seconds)

### Files to Create

```
src/Controllers/Admin/ActivityController.php
templates/admin/activity/index.html.twig
```

### Database Queries

```sql
-- List activity with filters
SELECT a.*, u.username, u.full_name
FROM admin_activity_log a
LEFT JOIN admin_users u ON a.user_id = u.id
WHERE 1=1
  AND (:user_id IS NULL OR a.user_id = :user_id)
  AND (:action IS NULL OR a.action = :action)
  AND (:resource IS NULL OR a.resource = :resource)
  AND (:date_from IS NULL OR a.created_at >= :date_from)
  AND (:date_to IS NULL OR a.created_at <= :date_to)
ORDER BY a.created_at DESC
LIMIT :limit OFFSET :offset
```

---

## 3. Profile Management

**Route**: `/cosmos/admin/profile`  
**Estimated Time**: 3 hours  
**Priority**: HIGH

### Features

- [ ] View current user profile
- [ ] Edit full name
- [ ] Edit email
- [ ] Change password (with current password verification)
- [ ] View own activity history
- [ ] View active sessions
- [ ] Logout other sessions

### Files to Create

```
src/Controllers/Admin/ProfileController.php
templates/admin/profile/index.html.twig
templates/admin/profile/edit.html.twig
templates/admin/profile/change-password.html.twig
```

---

## 4. Product Management

**Route**: `/cosmos/admin/products`  
**Estimated Time**: 12 hours  
**Priority**: MEDIUM

### Features

- [ ] Product list with pagination (50 per page)
- [ ] Search by title, handle, vendor
- [ ] Filter by product type, vendor, in stock
- [ ] Filter by collection, category, tag
- [ ] Sort by title, price, created date, updated date
- [ ] Bulk actions (delete, change status, assign tags)
- [ ] View product details
- [ ] Edit product (title, description, price, etc.)
- [ ] Manage product images
- [ ] Manage product variants
- [ ] Assign to collections
- [ ] Assign tags
- [ ] Delete product (with confirmation)

### Files to Create

```
src/Controllers/Admin/ProductController.php
templates/admin/products/index.html.twig
templates/admin/products/view.html.twig
templates/admin/products/edit.html.twig
```

### Database Queries

```sql
-- List products with filters
SELECT p.*,
       GROUP_CONCAT(DISTINCT c.name) as collections,
       GROUP_CONCAT(DISTINCT t.name) as tag_names
FROM products p
LEFT JOIN collection_products cp ON p.id = cp.product_id
LEFT JOIN collections c ON cp.collection_id = c.id
LEFT JOIN product_tags pt ON p.id = pt.product_id
LEFT JOIN tags t ON pt.tag_id = t.id
WHERE 1=1
  AND (:search IS NULL OR p.title LIKE :search OR p.handle LIKE :search)
  AND (:product_type IS NULL OR p.product_type = :product_type)
  AND (:vendor IS NULL OR p.vendor = :vendor)
  AND (:in_stock IS NULL OR p.in_stock = :in_stock)
GROUP BY p.id
ORDER BY p.created_at DESC
LIMIT :limit OFFSET :offset
```

---

## 5. Collections Management

**Route**: `/cosmos/admin/collections`  
**Estimated Time**: 6 hours  
**Priority**: MEDIUM

### Features

- [ ] Collection list
- [ ] Create new collection
- [ ] Edit collection (name, description, rules)
- [ ] Delete collection
- [ ] View products in collection
- [ ] Add/remove products manually
- [ ] Smart collection rules (auto-add based on criteria)
- [ ] Reorder products in collection

### Files to Create

```
src/Controllers/Admin/CollectionController.php
templates/admin/collections/index.html.twig
templates/admin/collections/create.html.twig
templates/admin/collections/edit.html.twig
templates/admin/collections/products.html.twig
```

---

## 6. Categories Management

**Route**: `/cosmos/admin/categories`  
**Estimated Time**: 8 hours  
**Priority**: MEDIUM

### Features

- [ ] Hierarchical category tree view
- [ ] Create new category
- [ ] Edit category (name, parent, description)
- [ ] Delete category (reassign products)
- [ ] Drag-and-drop reordering
- [ ] Assign products to category
- [ ] View products in category
- [ ] Category breadcrumbs

### Files to Create

```
src/Controllers/Admin/CategoryController.php
templates/admin/categories/index.html.twig
templates/admin/categories/create.html.twig
templates/admin/categories/edit.html.twig
```

---

## 7. Tags Management

**Route**: `/cosmos/admin/tags`  
**Estimated Time**: 5 hours  
**Priority**: MEDIUM

### Features

- [ ] Tag list with product count
- [ ] Search tags
- [ ] Create new tag
- [ ] Edit tag name
- [ ] Delete tag (remove from all products)
- [ ] Merge tags (combine multiple tags into one)
- [ ] Bulk assign tags to products
- [ ] View products with tag

### Files to Create

```
src/Controllers/Admin/TagController.php
templates/admin/tags/index.html.twig
templates/admin/tags/create.html.twig
templates/admin/tags/edit.html.twig
templates/admin/tags/merge.html.twig
```

---

## 8. API Keys Management

**Route**: `/cosmos/admin/api-keys`  
**Estimated Time**: 4 hours  
**Priority**: LOW

### Features

- [ ] API key list
- [ ] Generate new API key
- [ ] Revoke API key
- [ ] View API key usage statistics
- [ ] Set API key permissions
- [ ] Set API key expiration date

### Files to Create

```
src/Controllers/Admin/ApiKeyController.php
templates/admin/api-keys/index.html.twig
templates/admin/api-keys/create.html.twig
```

---

## 9. Settings Page

**Route**: `/cosmos/admin/settings`  
**Estimated Time**: 6 hours  
**Priority**: LOW

### Features

- [ ] System settings (site name, description)
- [ ] Email configuration (SMTP settings)
- [ ] Security settings (session timeout, password policy)
- [ ] Activity log retention period
- [ ] Pagination defaults
- [ ] Upload limits
- [ ] Maintenance mode toggle

### Files to Create

```
src/Controllers/Admin/SettingsController.php
templates/admin/settings/index.html.twig
```

---

## Technical Implementation Details

### Controller Base Class

Create a base controller for common functionality:

```php
// src/Controllers/Admin/BaseAdminController.php
abstract class BaseAdminController
{
    protected AuthService $authService;
    protected Twig $view;
    protected PDO $productsDb;
    protected PDO $adminDb;

    protected function getUser(): array
    {
        return $this->authService->getUserFromSession();
    }

    protected function checkPermission(string $resource, string $action): bool
    {
        $user = $this->getUser();
        return $this->authService->hasPermission($user, $resource, $action);
    }

    protected function logActivity(string $action, string $resource, ?string $details = null): void
    {
        $user = $this->getUser();
        $this->authService->logActivity($user['id'], $action, $resource, $details);
    }

    protected function renderWithLayout(Response $response, string $template, array $data = []): Response
    {
        $data['user'] = $this->getUser();
        return $this->view->render($response, $template, $data);
    }
}
```

### Form Validation Helper

```php
// src/Services/ValidationService.php
class ValidationService
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            // Implement validation logic
        }
        return $errors;
    }
}
```

### Pagination Helper

```php
// src/Services/PaginationService.php
class PaginationService
{
    public function paginate(int $total, int $page, int $perPage): array
    {
        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
        ];
    }
}
```

---

## Estimated Timeline

| Phase | Tasks | Time | Completion |
|-------|-------|------|------------|
| Phase 1 | Authentication & Dashboard | 16h | ✅ Complete |
| Phase 2.1 | User Management | 8h | 🔄 Next |
| Phase 2.2 | Activity Log & Profile | 7h | Pending |
| Phase 3.1 | Product Management | 12h | Pending |
| Phase 3.2 | Collections | 6h | Pending |
| Phase 4.1 | Categories & Tags | 13h | Pending |
| Phase 4.2 | API Keys & Settings | 10h | Pending |
| **Total** | **All Features** | **72h** | **22% Complete** |

---

## Next Immediate Steps

1. ✅ Fix critical production timeout (DONE)
2. 🔄 Create User Management controller and templates (NEXT)
3. Implement Activity Log viewer
4. Deploy to production for testing
5. Continue with Product Management

---

**Author**: Augment Agent  
**Date**: October 6, 2025  
**Status**: Planning Complete - Ready for Implementation

