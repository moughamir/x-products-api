# Task 2: Admin Dashboard (Backoffice) - Comprehensive Plan

## Executive Summary

This document outlines a complete plan for building an admin dashboard to manage the Cosmos Product API catalog. The dashboard will be a separate application with its own authentication system, integrated with the existing product database.

---

## 1. Architecture Plan

### 1.1 Overall Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Admin Dashboard                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Frontend   │  │   Backend    │  │  Admin Auth  │     │
│  │  (Vue.js 3)  │◄─┤  (PHP/Slim)  │◄─┤   (SQLite)   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│         │                  │                                │
│         └──────────────────┼────────────────────────────────┤
│                            ▼                                │
│                  ┌──────────────────┐                       │
│                  │  Products DB     │                       │
│                  │  (SQLite)        │                       │
│                  │  - products      │                       │
│                  │  - images        │                       │
│                  │  - collections   │                       │
│                  └──────────────────┘                       │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
                  ┌──────────────────┐
                  │  Public API      │
                  │  (Read-Only)     │
                  └──────────────────┘
```

### 1.2 Technology Stack

#### Backend

- **Framework**: Slim 4 (already in use, consistent with main API)
- **Language**: PHP 8.4
- **Database**:
  - **Admin DB**: SQLite (`data/sqlite/admin.sqlite`) - Separate database for admin users
  - **Products DB**: SQLite (`data/sqlite/products.sqlite`) - Existing database
- **Authentication**: PHP Sessions + JWT for API calls
- **Image Processing**: Intervention/Image (PHP library for image manipulation)
- **Validation**: Respect/Validation

#### Frontend

- **Framework**: Vue.js 3 with Composition API
- **UI Library**: Vuetify 3 (Material Design components)
- **State Management**: Pinia
- **HTTP Client**: Axios
- **Rich Text Editor**: TipTap (for product descriptions)
- **Image Upload**: vue-dropzone or similar
- **Build Tool**: Vite

#### Development Tools

- **API Documentation**: OpenAPI/Swagger (already in place)
- **Code Quality**: PHP_CodeSniffer, PHPStan
- **Testing**: PHPUnit for backend, Vitest for frontend

### 1.3 Database Strategy

**Two-Database Approach:**

1. **Admin Database** (`admin.sqlite`):

   - Admin users and authentication
   - Roles and permissions
   - Activity logs
   - Session management
   - API key management

2. **Products Database** (`products.sqlite`):
   - All product data (existing)
   - Collections (new table)
   - Categories (new table)
   - Product-Collection relationships (new table)

**Why separate databases?**

- Security isolation (admin credentials separate from product data)
- Easier backup strategies (can backup admin DB separately)
- Clear separation of concerns
- Can scale independently if needed

### 1.4 Integration with Existing API

The admin dashboard will:

- **Write directly** to the products database
- **Trigger FTS5 index updates** after product modifications
- **Invalidate caches** if caching is implemented
- **Use the same database connection** logic from existing codebase
- **Respect the same data structures** (variants_json, options_json, etc.)

---

## 2. Feature Specifications

### 2.1 Products Management

#### List View

- **Pagination**: 50 products per page
- **Columns**: Thumbnail, ID, Title, Price, Stock Status, Vendor, Created Date
- **Filters**:
  - By vendor
  - By product type
  - By stock status
  - By price range
  - By tags
  - By collection
- **Bulk Actions**:
  - Delete selected
  - Add to collection
  - Remove from collection
  - Update tags (add/remove)
  - Export to JSON/CSV
- **Search**: Full-text search using existing FTS5 index
- **Sort**: By any column (ID, title, price, created_at, etc.)

#### Create/Edit Product

- **Basic Info**:
  - Title (required)
  - Handle (auto-generated from title, editable)
  - Description (rich text editor with HTML support)
  - Vendor
  - Product Type
  - Tags (multi-select with autocomplete)
- **Pricing**:
  - Base price (calculated from variants)
  - Compare at price
- **Images**:
  - Drag-and-drop upload
  - Reorder by dragging
  - Set alt text
  - Associate with variants
  - Crop/resize tool
  - Delete images
- **Variants**:
  - Add/edit/delete variants
  - Set options (Size, Color, etc.)
  - Individual pricing per variant
  - SKU management
  - Inventory tracking
  - Featured image per variant
- **Options**:
  - Add/edit/delete options
  - Set option values
  - Reorder options
- **SEO**:
  - Meta title
  - Meta description
  - URL handle
- **Organization**:
  - Collections (multi-select)
  - Categories (hierarchical select)
  - Tags (multi-select with create new)

#### Bulk Import/Export

- **Import**:
  - JSON format (Shopify-compatible)
  - CSV format
  - Validation before import
  - Preview changes
  - Rollback on error
- **Export**:
  - JSON format
  - CSV format
  - Filter before export
  - Include/exclude images

### 2.2 Collections Management

#### List View

- **Columns**: Name, Handle, Product Count, Created Date
- **Actions**: Edit, Delete, View Products
- **Search**: By name or handle

#### Create/Edit Collection

- **Basic Info**:
  - Title (required)
  - Handle (auto-generated, editable)
  - Description (rich text)
  - Image (collection banner)
- **Products**:
  - Add products (search and select)
  - Remove products
  - Reorder products (manual or by rules)
- **Rules** (Smart Collections):
  - Auto-add products matching criteria:
    - Tag contains...
    - Product type is...
    - Vendor is...
    - Price range...
  - Manual override (pin/exclude specific products)
- **Display**:
  - Featured (show on homepage)
  - Sort order (manual, price, newest, bestseller)

### 2.3 Categories Management

#### Hierarchical Structure

- **Tree View**: Drag-and-drop to reorganize
- **Breadcrumbs**: Show category path
- **Nesting**: Unlimited depth (e.g., Lighting > Floor Lamps > Reading Lamps)

#### Create/Edit Category

- **Basic Info**:
  - Name (required)
  - Slug (auto-generated)
  - Description
  - Parent category (select from tree)
- **Products**:
  - Assign products to category
  - View products in category
- **Display**:
  - Icon/image
  - Sort order

### 2.4 Tags Management

#### List View

- **Columns**: Tag Name, Product Count, Created Date
- **Actions**: Rename, Merge, Delete
- **Search**: By tag name

#### Operations

- **Create**: Add new tag
- **Rename**: Update tag name across all products
- **Merge**: Combine multiple tags into one
- **Bulk Assign**: Add tag to multiple products
- **Bulk Remove**: Remove tag from multiple products
- **Delete**: Remove tag (with confirmation if used)

### 2.5 Images Management

#### Upload Interface

- **Drag-and-Drop**: Multi-file upload
- **Progress Indicators**: Show upload progress
- **Validation**: File type, size limits
- **Auto-Processing**:
  - Generate thumbnails
  - Optimize file size
  - Extract dimensions

#### Image Editor

- **Crop**: Aspect ratio presets (1:1, 4:3, 16:9, custom)
- **Resize**: Set width/height
- **Rotate**: 90° increments
- **Filters**: Brightness, contrast, saturation
- **Alt Text**: Accessibility description
- **Variant Association**: Link to specific variants

#### Image Library

- **Grid View**: Thumbnails with metadata
- **Filters**: By product, by size, by date
- **Search**: By filename or alt text
- **Bulk Actions**: Delete, update alt text

### 2.6 Variants Management

#### Variant Editor (within Product Edit)

- **Add Variant**: Create new variant with options
- **Edit Variant**:
  - Title (auto-generated from options)
  - Price (required)
  - Compare at price
  - SKU
  - Barcode
  - Weight (grams)
  - Inventory quantity
  - Featured image
  - Shipping required (checkbox)
  - Taxable (checkbox)
- **Bulk Edit**: Update multiple variants at once
- **Generate Variants**: Auto-create from option combinations

### 2.7 Options Management

#### Option Editor (within Product Edit)

- **Add Option**: Name (e.g., "Size", "Color")
- **Add Values**: List of values (e.g., "Small", "Medium", "Large")
- **Reorder**: Drag to change position
- **Delete**: Remove option (cascades to variants)

---

## 3. Database Schema for Admin Functionality

### 3.1 Admin Database (`admin.sqlite`)

```sql
-- Admin Users Table
CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role_id INTEGER NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    last_login_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES admin_roles(id)
);

-- Roles Table
CREATE TABLE admin_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    permissions TEXT, -- JSON array of permission strings
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sessions Table
CREATE TABLE admin_sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- Activity Log Table
CREATE TABLE admin_activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50), -- 'product', 'collection', 'category', etc.
    entity_id INTEGER,
    details TEXT, -- JSON with before/after values
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- API Keys Table (for managing public API keys)
CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_hash VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INTEGER,
    is_active BOOLEAN DEFAULT 1,
    last_used_at DATETIME,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
);

-- Password Reset Tokens
CREATE TABLE password_reset_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);
```

### 3.2 Products Database Extensions (`products.sqlite`)

```sql
-- Collections Table
CREATE TABLE collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    handle VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    image_url TEXT,
    is_smart BOOLEAN DEFAULT 0, -- Smart collection (rule-based)
    rules TEXT, -- JSON rules for smart collections
    sort_order VARCHAR(50) DEFAULT 'manual', -- manual, price_asc, price_desc, newest, bestseller
    is_featured BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Product-Collection Relationship (for manual collections)
CREATE TABLE product_collections (
    product_id INTEGER NOT NULL,
    collection_id INTEGER NOT NULL,
    position INTEGER DEFAULT 0, -- For manual sorting
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, collection_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
);

-- Categories Table (Hierarchical)
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    parent_id INTEGER,
    image_url TEXT,
    position INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Product-Category Relationship
CREATE TABLE product_categories (
    product_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Tags Table (normalized)
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Product-Tag Relationship
CREATE TABLE product_tags (
    product_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, tag_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Indexes for Performance
CREATE INDEX idx_collections_handle ON collections(handle);
CREATE INDEX idx_collections_featured ON collections(is_featured);
CREATE INDEX idx_product_collections_collection ON product_collections(collection_id);
CREATE INDEX idx_categories_parent ON categories(parent_id);
CREATE INDEX idx_categories_slug ON categories(slug);
CREATE INDEX idx_product_categories_category ON product_categories(category_id);
CREATE INDEX idx_tags_slug ON tags(slug);
CREATE INDEX idx_product_tags_tag ON product_tags(tag_id);
```

---

## 4. Security Considerations

### 4.1 Authentication Mechanism

**Session-Based Authentication** (for web interface):

- PHP sessions with secure cookies
- Session timeout: 2 hours of inactivity
- Remember me: Optional 30-day persistent cookie
- CSRF protection on all forms

**JWT Tokens** (for API calls from frontend):

- Short-lived access tokens (15 minutes)
- Refresh tokens (7 days)
- Stored in httpOnly cookies
- Automatic refresh before expiration

### 4.2 Password Security

- **Hashing**: bcrypt with cost factor 12
- **Requirements**:
  - Minimum 12 characters
  - Must include uppercase, lowercase, number, special character
- **Password Reset**:
  - Time-limited tokens (1 hour)
  - Single-use tokens
  - Email verification required

### 4.3 Role-Based Access Control (RBAC)

**Predefined Roles:**

1. **Super Admin**:

   - Full access to everything
   - User management
   - API key management
   - System settings

2. **Admin**:

   - Full product management
   - Collection management
   - Category management
   - Cannot manage users or API keys

3. **Editor**:

   - Edit existing products
   - Upload images
   - Manage tags
   - Cannot delete products or manage collections

4. **Viewer**:
   - Read-only access
   - View products, collections, categories
   - Cannot make any changes

**Permission System:**

```json
{
  "products": ["create", "read", "update", "delete"],
  "collections": ["create", "read", "update", "delete"],
  "categories": ["create", "read", "update", "delete"],
  "tags": ["create", "read", "update", "delete"],
  "images": ["upload", "delete"],
  "users": ["create", "read", "update", "delete"],
  "api_keys": ["create", "read", "revoke"]
}
```

### 4.4 API Key Management

- **Generation**: Cryptographically secure random keys
- **Storage**: Hashed (SHA-256) in database
- **Display**: Show once on creation, then only last 4 characters
- **Rotation**: Ability to regenerate keys
- **Expiration**: Optional expiration dates
- **Rate Limiting**: Track usage per key
- **Revocation**: Instant deactivation

### 4.5 Input Validation & Sanitization

- **Server-Side Validation**: All inputs validated on backend
- **XSS Prevention**: HTML purification for rich text fields
- **SQL Injection**: Prepared statements (already in use)
- **File Upload**:
  - Whitelist allowed MIME types (image/jpeg, image/png, image/webp)
  - Maximum file size: 10MB
  - Virus scanning (optional, using ClamAV)
  - Rename files to prevent path traversal

### 4.6 Activity Logging

**Log all sensitive actions:**

- User login/logout
- Product create/update/delete
- Collection modifications
- User management actions
- API key creation/revocation
- Failed login attempts (for brute-force detection)

**Log retention**: 90 days (configurable)

---

## 5. UI/UX Recommendations

### 5.1 Dashboard Layout

```
┌─────────────────────────────────────────────────────────────┐
│  [Logo]  Cosmos Admin    [Search]    [User Menu] [Logout]  │
├──────────┬──────────────────────────────────────────────────┤
│          │                                                   │
│ Products │  ┌─────────────────────────────────────────┐    │
│ Collections│  │         Dashboard Overview              │    │
│ Categories│  │  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐  │    │
│ Tags     │  │  │10,000│ │  245 │ │  89  │ │  12  │  │    │
│ Images   │  │  │Prods │ │Colls │ │Cats  │ │Users │  │    │
│ Settings │  │  └──────┘ └──────┘ └──────┘ └──────┘  │    │
│ Users    │  │                                         │    │
│ API Keys │  │  Recent Activity:                       │    │
│ Logs     │  │  • Product "X" updated by Admin         │    │
│          │  │  • Collection "Y" created by Editor     │    │
│          │  │  • 50 products imported by Super Admin  │    │
│          │  └─────────────────────────────────────────┘    │
│          │                                                   │
└──────────┴───────────────────────────────────────────────────┘
```

### 5.2 Key Screens/Views

#### 1. Dashboard (Home)

- **Widgets**:
  - Total products, collections, categories
  - Recent activity feed
  - Quick actions (Add Product, Create Collection)
  - Low stock alerts
  - System status

#### 2. Products List

- **Layout**: Data table with filters sidebar
- **Features**:
  - Thumbnail preview
  - Inline quick edit (price, stock)
  - Bulk selection checkboxes
  - Pagination controls
  - Export button

#### 3. Product Edit

- **Layout**: Tabbed interface
  - Tab 1: Basic Info
  - Tab 2: Images
  - Tab 3: Variants
  - Tab 4: Organization (Collections, Categories, Tags)
  - Tab 5: SEO
- **Features**:
  - Auto-save drafts
  - Preview button
  - Duplicate product
  - Delete with confirmation

#### 4. Collections List

- **Layout**: Card grid or table view toggle
- **Features**:
  - Collection thumbnail
  - Product count badge
  - Quick view products
  - Drag-to-reorder (for featured collections)

#### 5. Image Library

- **Layout**: Grid view with lightbox
- **Features**:
  - Filter by product
  - Search by filename/alt text
  - Multi-select for bulk actions
  - Upload zone always visible

### 5.3 Suggested Libraries/Frameworks

#### Frontend Components

1. **Vuetify 3** (https://vuetifyjs.com/)

   - Material Design components
   - Data tables with sorting/filtering
   - Form validation
   - Dialogs and modals
   - Snackbars for notifications

2. **TipTap** (https://tiptap.dev/)

   - Rich text editor for product descriptions
   - WYSIWYG interface
   - HTML output
   - Extensible with plugins

3. **Vue Dropzone** (https://github.com/rowanwins/vue-dropzone)

   - Drag-and-drop file uploads
   - Progress indicators
   - Image previews

4. **Vue Draggable** (https://github.com/SortableJS/vue.draggable.next)

   - Drag-and-drop reordering
   - For images, variants, collections

5. **Chart.js** (https://www.chartjs.org/)

   - Dashboard analytics
   - Sales trends
   - Product performance

6. **Vue Toastification** (https://github.com/Maronato/vue-toastification)
   - Toast notifications
   - Success/error messages

#### Backend Libraries

1. **Intervention/Image** (http://image.intervention.io/)

   - Image manipulation
   - Resize, crop, filters
   - Thumbnail generation

2. **Respect/Validation** (https://respect-validation.readthedocs.io/)

   - Input validation
   - Custom rules
   - Fluent interface

3. **Firebase/PHP-JWT** (https://github.com/firebase/php-jwt)

   - JWT token generation/validation
   - For API authentication

4. **PHPMailer** (https://github.com/PHPMailer/PHPMailer)
   - Email sending
   - Password reset emails
   - User notifications

### 5.4 Design System

**Color Palette:**

- Primary: #1976D2 (Blue)
- Secondary: #424242 (Dark Gray)
- Success: #4CAF50 (Green)
- Warning: #FF9800 (Orange)
- Error: #F44336 (Red)
- Background: #FAFAFA (Light Gray)

**Typography:**

- Font Family: Inter, Roboto, sans-serif
- Headings: 600 weight
- Body: 400 weight
- Code: Fira Code, monospace

**Spacing:**

- Base unit: 8px
- Small: 8px
- Medium: 16px
- Large: 24px
- XLarge: 32px

---

## 6. Implementation Roadmap

### Phase 1: Foundation (Weeks 1-2)

- [ ] Set up admin database schema
- [ ] Implement authentication system
- [ ] Create admin user CRUD
- [ ] Build role/permission system
- [ ] Set up Vue.js frontend scaffold
- [ ] Create login/logout pages

### Phase 2: Products Management (Weeks 3-4)

- [ ] Products list view
- [ ] Product create/edit form
- [ ] Image upload and management
- [ ] Variants editor
- [ ] Options editor
- [ ] Product search and filters

### Phase 3: Collections & Categories (Week 5)

- [ ] Collections CRUD
- [ ] Smart collections with rules
- [ ] Categories hierarchical structure
- [ ] Product-collection assignment
- [ ] Product-category assignment

### Phase 4: Tags & Advanced Features (Week 6)

- [ ] Tags management
- [ ] Bulk operations
- [ ] Import/export functionality
- [ ] Activity logging
- [ ] Dashboard analytics

### Phase 5: Polish & Testing (Week 7)

- [ ] UI/UX refinements
- [ ] Performance optimization
- [ ] Security audit
- [ ] User acceptance testing
- [ ] Documentation

---

## 7. Next Steps

1. **Review and approve** this plan
2. **Set up development environment** for admin dashboard
3. **Create database migrations** for admin and products DB extensions
4. **Initialize Vue.js project** with Vite and Vuetify
5. **Implement authentication** as the first feature
6. **Iterate on features** following the roadmap

---

## Appendix A: Admin API Endpoints

### Authentication Endpoints

- `POST /admin/api/auth/login` - Login with username/password
- `POST /admin/api/auth/logout` - Logout current session
- `POST /admin/api/auth/refresh` - Refresh JWT token
- `POST /admin/api/auth/forgot-password` - Request password reset
- `POST /admin/api/auth/reset-password` - Reset password with token
- `GET /admin/api/auth/me` - Get current user info

### Product Endpoints

- `GET /admin/api/products` - List products (paginated, filtered)
- `GET /admin/api/products/{id}` - Get single product
- `POST /admin/api/products` - Create new product
- `PUT /admin/api/products/{id}` - Update product
- `DELETE /admin/api/products/{id}` - Delete product
- `POST /admin/api/products/bulk-delete` - Delete multiple products
- `POST /admin/api/products/bulk-update` - Update multiple products
- `POST /admin/api/products/import` - Import products from JSON/CSV
- `GET /admin/api/products/export` - Export products to JSON/CSV

### Collection Endpoints

- `GET /admin/api/collections` - List collections
- `GET /admin/api/collections/{id}` - Get single collection
- `POST /admin/api/collections` - Create collection
- `PUT /admin/api/collections/{id}` - Update collection
- `DELETE /admin/api/collections/{id}` - Delete collection
- `POST /admin/api/collections/{id}/products` - Add products to collection
- `DELETE /admin/api/collections/{id}/products/{productId}` - Remove product from collection

### Category Endpoints

- `GET /admin/api/categories` - List categories (tree structure)
- `GET /admin/api/categories/{id}` - Get single category
- `POST /admin/api/categories` - Create category
- `PUT /admin/api/categories/{id}` - Update category
- `DELETE /admin/api/categories/{id}` - Delete category
- `PUT /admin/api/categories/{id}/move` - Move category in tree

### Tag Endpoints

- `GET /admin/api/tags` - List all tags
- `POST /admin/api/tags` - Create tag
- `PUT /admin/api/tags/{id}` - Rename tag
- `DELETE /admin/api/tags/{id}` - Delete tag
- `POST /admin/api/tags/merge` - Merge multiple tags

### Image Endpoints

- `POST /admin/api/images/upload` - Upload image(s)
- `GET /admin/api/images` - List images
- `DELETE /admin/api/images/{id}` - Delete image
- `PUT /admin/api/images/{id}` - Update image metadata
- `POST /admin/api/images/{id}/crop` - Crop/resize image

### User Management Endpoints (Super Admin only)

- `GET /admin/api/users` - List admin users
- `POST /admin/api/users` - Create admin user
- `PUT /admin/api/users/{id}` - Update admin user
- `DELETE /admin/api/users/{id}` - Delete admin user
- `PUT /admin/api/users/{id}/password` - Change user password

### API Key Endpoints

- `GET /admin/api/api-keys` - List API keys
- `POST /admin/api/api-keys` - Create new API key
- `PUT /admin/api/api-keys/{id}` - Update API key
- `DELETE /admin/api/api-keys/{id}` - Revoke API key

### Activity Log Endpoints

- `GET /admin/api/activity-log` - Get activity log (paginated, filtered)

---

## Appendix B: Migration Scripts

### Initial Admin Database Setup

```php
<?php
// migrations/001_create_admin_database.php

$adminDb = new PDO('sqlite:data/sqlite/admin.sqlite');
$adminDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$adminDb->exec("
    CREATE TABLE admin_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        permissions TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

$adminDb->exec("
    CREATE TABLE admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        role_id INTEGER NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        last_login_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES admin_roles(id)
    );
");

// Insert default roles
$adminDb->exec("
    INSERT INTO admin_roles (name, description, permissions) VALUES
    ('super_admin', 'Full system access', '{\"*\": [\"*\"]}'),
    ('admin', 'Product and content management', '{\"products\": [\"*\"], \"collections\": [\"*\"], \"categories\": [\"*\"], \"tags\": [\"*\"], \"images\": [\"*\"]}'),
    ('editor', 'Edit products and content', '{\"products\": [\"read\", \"update\"], \"images\": [\"upload\"]}'),
    ('viewer', 'Read-only access', '{\"products\": [\"read\"], \"collections\": [\"read\"], \"categories\": [\"read\"]}');
");

// Create default super admin user
$passwordHash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
$adminDb->exec("
    INSERT INTO admin_users (username, email, password_hash, full_name, role_id)
    VALUES ('admin', 'admin@example.com', '{$passwordHash}', 'System Administrator', 1);
");

echo "Admin database created successfully!\n";
echo "Default login: admin / admin123\n";
echo "⚠️  IMPORTANT: Change the default password immediately!\n";
```

### Products Database Extensions

```php
<?php
// migrations/002_extend_products_database.php

$productsDb = new PDO('sqlite:data/sqlite/products.sqlite');
$productsDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create collections table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(255) NOT NULL,
        handle VARCHAR(255) UNIQUE NOT NULL,
        description TEXT,
        image_url TEXT,
        is_smart BOOLEAN DEFAULT 0,
        rules TEXT,
        sort_order VARCHAR(50) DEFAULT 'manual',
        is_featured BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Create product_collections junction table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS product_collections (
        product_id INTEGER NOT NULL,
        collection_id INTEGER NOT NULL,
        position INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (product_id, collection_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
    );
");

// Create categories table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        description TEXT,
        parent_id INTEGER,
        image_url TEXT,
        position INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    );
");

// Create product_categories junction table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS product_categories (
        product_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (product_id, category_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    );
");

// Create tags table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Create product_tags junction table
$productsDb->exec("
    CREATE TABLE IF NOT EXISTS product_tags (
        product_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (product_id, tag_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    );
");

// Create indexes
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_collections_handle ON collections(handle);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_collections_featured ON collections(is_featured);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_product_collections_collection ON product_collections(collection_id);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_categories_slug ON categories(slug);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_product_categories_category ON product_categories(category_id);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_tags_slug ON tags(slug);");
$productsDb->exec("CREATE INDEX IF NOT EXISTS idx_product_tags_tag ON product_tags(tag_id);");

echo "Products database extended successfully!\n";
```

---

## Appendix C: Environment Configuration

### Admin Configuration File

```php
<?php
// config/admin.php

return [
    'database' => [
        'admin' => [
            'driver' => 'sqlite',
            'path' => __DIR__ . '/../data/sqlite/admin.sqlite',
        ],
        'products' => [
            'driver' => 'sqlite',
            'path' => __DIR__ . '/../data/sqlite/products.sqlite',
        ],
    ],

    'auth' => [
        'session_lifetime' => 7200, // 2 hours in seconds
        'remember_me_lifetime' => 2592000, // 30 days in seconds
        'jwt_secret' => getenv('JWT_SECRET') ?: 'change-this-in-production',
        'jwt_access_lifetime' => 900, // 15 minutes
        'jwt_refresh_lifetime' => 604800, // 7 days
        'password_reset_lifetime' => 3600, // 1 hour
    ],

    'upload' => [
        'max_file_size' => 10485760, // 10MB in bytes
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'upload_path' => __DIR__ . '/../public/uploads',
        'thumbnail_sizes' => [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [800, 800],
        ],
    ],

    'pagination' => [
        'default_per_page' => 50,
        'max_per_page' => 100,
    ],

    'activity_log' => [
        'enabled' => true,
        'retention_days' => 90,
    ],
];
```

---

## Appendix: File Structure

```
admin/
├── backend/
│   ├── src/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── ProductController.php
│   │   │   ├── CollectionController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── TagController.php
│   │   │   ├── ImageController.php
│   │   │   └── UserController.php
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   ├── RoleMiddleware.php
│   │   │   └── CsrfMiddleware.php
│   │   ├── Models/
│   │   │   ├── AdminUser.php
│   │   │   ├── Role.php
│   │   │   ├── Collection.php
│   │   │   ├── Category.php
│   │   │   └── Tag.php
│   │   ├── Services/
│   │   │   ├── AuthService.php
│   │   │   ├── ProductService.php
│   │   │   ├── ImageService.php
│   │   │   └── ActivityLogger.php
│   │   └── App.php
│   ├── config/
│   │   ├── admin_database.php
│   │   └── permissions.php
│   └── public/
│       └── index.php
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   │   ├── products/
│   │   │   ├── collections/
│   │   │   ├── categories/
│   │   │   └── common/
│   │   ├── views/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Products.vue
│   │   │   ├── ProductEdit.vue
│   │   │   ├── Collections.vue
│   │   │   └── Login.vue
│   │   ├── stores/
│   │   │   ├── auth.js
│   │   │   ├── products.js
│   │   │   └── collections.js
│   │   ├── router/
│   │   │   └── index.js
│   │   ├── App.vue
│   │   └── main.js
│   ├── package.json
│   └── vite.config.js
└── data/
    └── sqlite/
        ├── admin.sqlite
        └── products.sqlite (existing)
```
