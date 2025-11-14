# Quick Start Guide - X-Products API Admin Panel

## 🚀 Getting Started

### 1. Start the Application
```bash
cd /home/odin/Downloads/x-products-api
php -S localhost:8000
```

### 2. Login
- **URL**: http://localhost:8000/cosmos/admin/login
- **Username**: `admin`
- **Password**: `admin123`

---

## 📖 Feature Walkthroughs

### Product Management

#### View All Products
1. Click **Products** in sidebar
2. Use filters to narrow down:
   - Search by title, handle, or vendor
   - Filter by collection, category, tag
   - Filter by stock status
   - Filter by price range
   - Filter by product type or vendor

#### Create a Product
1. Click **Products** → **Add Product**
2. Fill in required fields:
   - Title (required)
   - Price (required)
3. Optional fields:
   - Handle (auto-generated if empty)
   - Description (HTML supported)
   - Compare at price (for sales)
   - Vendor
   - Product type
   - Tags (comma-separated)
4. Assign to collections (check boxes)
5. Assign to categories (check boxes)
6. Set stock status
7. Click **Create Product**

#### Edit a Product
1. Click **Products**
2. Click **Edit** icon next to product
3. Modify fields
4. Click **Update Product**

#### Delete Products
- **Single**: Click trash icon → Confirm
- **Bulk**: Check multiple products → Click **Delete Selected** → Confirm

---

### Collections Management

#### Create a Manual Collection
1. Click **Collections** → **Add Collection**
2. Enter title
3. Leave "Smart Collection" unchecked
4. Click **Create Collection**
5. Assign products manually via Product edit page

#### Create a Smart Collection
1. Click **Collections** → **Add Collection**
2. Enter title
3. Check **Smart Collection**
4. Set rule type:
   - **all**: All products
   - **tag_contains**: Products with specific tag
   - **price_range**: Products in price range
   - **product_type**: Products of specific type
   - **vendor**: Products from specific vendor
   - **in_stock**: Only in-stock products
   - **out_of_stock**: Only out-of-stock products
5. Click **Create Collection**
6. Products auto-populate based on rules

#### Sync Smart Collection
1. Click **Collections**
2. Click sync icon (circular arrows) next to smart collection
3. Products will be re-evaluated and updated

---

### Categories Management

#### Create a Category
1. Click **Categories** → **Add Category**
2. Enter name
3. Optional: Select parent category for hierarchy
4. Enter description
5. Click **Create Category**

#### Create Subcategory
1. Click **Categories** → **Add Category**
2. Enter name
3. Select **Parent Category** from dropdown
4. Click **Create Category**

#### View Category Tree
- Categories display with indentation showing hierarchy
- Parent categories show first
- Subcategories show indented with "└─" prefix

---

### Tags Management

#### Create a Tag
1. Click **Tags** → **Add Tag**
2. Enter tag name
3. Click **Create Tag**

#### Cleanup Unused Tags
1. Click **Tags**
2. Click **Cleanup Unused** button
3. Confirm deletion
4. All tags not assigned to products will be deleted

#### View Tag Statistics
- Top of page shows:
  - Total tags
  - Used tags (assigned to products)
  - Unused tags

---

### Activity Log

#### View Activity
1. Click **Activity** in sidebar
2. See all admin actions with:
   - Timestamp
   - User who performed action
   - Action type (create, update, delete)
   - Entity type (product, collection, etc.)
   - Details

#### Filter Activity
Use filters to narrow down:
- **User**: See actions by specific admin
- **Action**: Filter by create/update/delete
- **Entity Type**: Filter by product/collection/category/etc.

---

### API Keys Management

#### Generate API Key
1. Click **API Keys** → **Generate API Key**
2. Enter name (e.g., "Production API Key")
3. Set rate limit (requests per minute)
4. Optional: Set expiration date
5. Click **Generate API Key**
6. **IMPORTANT**: Copy the key immediately - it won't be shown again!

#### View API Keys
- See all keys with:
  - Name
  - Key prefix (first 8 characters)
  - Total requests
  - Last used timestamp
  - Expiration status

#### Revoke API Key
1. Click **API Keys**
2. Click **Revoke** next to key
3. Confirm deletion

---

### User Profile

#### Update Profile
1. Click **Profile** in sidebar
2. Update:
   - Username
   - Email
   - Full name
3. Click **Update Profile**

#### Change Password
1. Click **Profile** in sidebar
2. In "Change Password" section:
   - Enter current password
   - Enter new password (min 8 characters)
   - Confirm new password
3. Click **Change Password**

---

### Settings

#### Update Application Settings
1. Click **Settings** in sidebar
2. Modify settings in three categories:

**General Settings**:
- Application name
- Application description
- Timezone
- Default currency

**Display Settings**:
- Items per page
- Max image size
- Allowed image types

**Email Settings (SMTP)**:
- SMTP host
- SMTP port
- SMTP username/password
- SMTP encryption (TLS/SSL)
- From email/name

3. Click **Save Settings**

---

## 💡 Tips & Tricks

### Product Management
- Use **bulk operations** to delete multiple products at once
- Use **filters** to find products quickly
- Assign products to multiple collections and categories
- Use **tags** for flexible product organization

### Collections
- Use **smart collections** for dynamic product grouping
- Mark important collections as **Featured**
- Sync smart collections after changing products

### Categories
- Build a **hierarchy** for better organization
- Keep category names short and descriptive
- Use **breadcrumbs** to navigate hierarchy

### Tags
- Use **comma-separated** tags when editing products
- Run **cleanup** periodically to remove unused tags
- Check **statistics** to see tag usage

### Activity Log
- Use **filters** to find specific actions
- Review activity for **audit trail**
- Check **last actions** before making changes

### API Keys
- Set **rate limits** to prevent abuse
- Use **expiration dates** for temporary access
- **Revoke** keys immediately if compromised
- Track **usage** to monitor API activity

---

## 🔍 Common Tasks

### Task: Organize Products into Collections
1. Create collection (manual or smart)
2. If manual: Edit products → Check collection
3. If smart: Set rules → Sync collection

### Task: Build Category Hierarchy
1. Create parent categories first
2. Create subcategories with parent selected
3. Assign products to categories via product edit

### Task: Tag Products
1. Edit product
2. Enter tags in "Tags" field (comma-separated)
3. Save product
4. Tags auto-created if they don't exist

### Task: Monitor Admin Actions
1. Go to Activity log
2. Filter by user/action/entity
3. Review recent changes

### Task: Generate API Access
1. Go to API Keys
2. Generate new key
3. Copy key immediately
4. Provide to API consumer

---

## ⚠️ Important Notes

### Security
- **Change default password** immediately
- **Revoke unused API keys** regularly
- **Review activity log** for suspicious actions
- **Use strong passwords** (min 8 characters)

### Data Management
- **Backup database** before bulk operations
- **Test smart collection rules** before deploying
- **Review changes** in activity log
- **Use filters** to avoid accidental bulk deletes

### Performance
- **Pagination** limits results to 50-100 items
- **Use filters** to narrow down large datasets
- **Cleanup unused tags** to reduce database size
- **Monitor API key usage** for rate limiting

---

## 🆘 Troubleshooting

### Can't Login
- Check username/password (default: admin/admin123)
- Clear browser cookies
- Check server is running

### Products Not Showing
- Check filters are not too restrictive
- Verify products exist in database
- Check pagination (may be on different page)

### Smart Collection Empty
- Verify rules are correct
- Click sync button to refresh
- Check products match rule criteria

### API Key Not Working
- Verify key was copied correctly
- Check key hasn't expired
- Verify key hasn't been revoked
- Check rate limit not exceeded

---

## 📞 Need Help?

1. **Documentation**: Check `/docs` folder
2. **Activity Log**: Review recent actions
3. **Code Comments**: All code is well-documented
4. **Error Messages**: Read error messages carefully

---

**Happy Managing!** 🎉

