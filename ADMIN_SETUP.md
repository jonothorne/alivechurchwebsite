# Alive Church Admin Panel - Installation Guide

Complete CMS for managing your Alive Church website with MySQL database backend.

## 🚀 Installation Steps

### 1. **Prepare MySQL Database**

First, ensure you have MySQL or MariaDB installed and running. You'll need:
- Database host (usually `localhost`)
- Database name (will be created if doesn't exist)
- Database username
- Database password

### 2. **Run the Installation Wizard**

Visit the installation page in your browser:

```
http://localhost:8999/install.php
```

The wizard will guide you through 4 steps:

#### Step 1: Database Configuration
- Enter your MySQL connection details
- Database will be created automatically if it doesn't exist

#### Step 2: Create Tables
- Click to create all required database tables
- Schema includes tables for pages, settings, ministries, groups, etc.

#### Step 3: Create Admin Account
- Set up your first admin user account
- Username, email, and password (minimum 8 characters)

#### Step 4: Migrate Existing Data
- All your current content from config.php will be copied to the database
- This includes site settings, ministries, groups, sermons, etc.
- **Your existing config.php is preserved** - nothing is deleted

### 3. **Access the Admin Panel**

After installation, access the admin panel at:

```
http://localhost:8999/admin
```

Login with the admin credentials you created in Step 3.

### 4. **Security: Delete Install File**

⚠️ **IMPORTANT:** After installation, delete or rename `install.php`:

```bash
rm install.php
# OR
mv install.php install.php.bak
```

---

## 📁 Admin Panel Structure

```
/admin/
├── index.php           # Dashboard
├── login.php          # Login page
├── logout.php         # Logout handler
├── settings.php       # Site Settings (✅ COMPLETE)
├── pages.php          # Page Manager (⏳ TODO)
├── navigation.php     # Navigation Menu (⏳ TODO)
├── ministries.php     # Ministries Manager (⏳ TODO)
├── groups.php         # Groups Manager (⏳ TODO)
├── serve.php          # Serve Opportunities (⏳ TODO)
├── next-steps.php     # Next Steps Manager (⏳ TODO)
├── sermons.php        # Sermons Manager (⏳ TODO)
├── media.php          # Media Library (⏳ TODO)
├── forms.php          # Form Submissions (⏳ TODO)
├── users.php          # User Management (⏳ TODO)
├── includes/
│   ├── header.php     # Admin layout header
│   └── footer.php     # Admin layout footer
└── assets/
    ├── css/admin.css  # Admin panel styles
    └── js/admin.js    # Admin panel JavaScript
```

---

## ✅ What's Already Built

### **Complete & Ready to Use:**

1. **Database Schema** - All tables created
2. **Authentication System** - Secure login/logout with sessions
3. **Admin Dashboard** - Overview with stats and recent activity
4. **Site Settings Manager** - Edit all site settings (EXAMPLE IMPLEMENTATION)
5. **Admin Layout** - Sidebar navigation, responsive design
6. **TinyMCE Integration** - WYSIWYG editor ready to use
7. **Activity Logging** - Tracks all admin actions

### **Site Settings Manager**

Located at `/admin/settings.php` - This is a **complete working example** showing:
- How to fetch data from database
- How to display forms with CSRF protection
- How to update database records
- Success/error message handling
- Activity logging

**You can use this as a template for building other admin sections!**

---

## 🔨 Building Additional Admin Sections

Use `/admin/settings.php` as your template. Here's the basic pattern:

### Example: Ministries Manager

```php
<?php
$page_title = 'Ministries';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

// Handle ADD/EDIT/DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        // Process form (INSERT/UPDATE/DELETE)
        // Log activity
        $success = 'Ministry saved!';
    }
}

// Fetch all ministries
$ministries = $pdo->query("SELECT * FROM ministries ORDER BY display_order")->fetchAll();
?>

<!-- Display list of ministries with edit/delete buttons -->
<!-- Add form for creating new ministry -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### Key Components:

1. **Header/Footer** - Use the admin layout
2. **Authentication** - `require_auth()` called in header
3. **CSRF Protection** - `csrf_field()` in forms, `verify_csrf_token()` on submit
4. **Database** - `getDbConnection()` for PDO
5. **Activity Logging** - `log_activity()` after changes
6. **Current User** - `get_current_user()` for user info

---

## 📊 Database Tables Reference

### Core Tables:
- `users` - Admin user accounts
- `site_settings` - Key-value site settings
- `pages` - Dynamic pages
- `page_sections` - Page content sections
- `navigation` - Menu items

### Content Tables:
- `ministries` - Ministry listings
- `groups_list` - Group information
- `serve_opportunities` - Volunteer opportunities
- `next_steps` - Next steps for visitors
- `sermon_series` - Sermon series
- `sermons` - Individual sermons

### System Tables:
- `media` - Uploaded files
- `form_submissions` - Contact form data
- `activity_log` - Admin action history

---

## 🎨 Using the WYSIWYG Editor

Add `class="wysiwyg"` to any textarea:

```html
<textarea name="description" class="wysiwyg"></textarea>
```

The admin.js file automatically initializes TinyMCE on these textareas.

---

## 🔐 Security Features

✅ **Password Hashing** - PHP `password_hash()` with bcrypt
✅ **CSRF Protection** - Token validation on all forms
✅ **SQL Injection Prevention** - Prepared statements everywhere
✅ **Session Management** - Secure session handling
✅ **Activity Logging** - All admin actions tracked
✅ **Role-Based Access** - Admin vs. Editor roles

---

## 🚧 TODO: Build These Sections

Priority order for building additional admin sections:

### High Priority:
1. **Navigation Manager** - Let admins edit menu items
2. **Ministries Manager** - Add/Edit/Delete ministries
3. **Groups Manager** - Manage group listings
4. **Media Library** - Upload and manage images

### Medium Priority:
5. **Page Builder** - Create/edit custom pages
6. **Sermons Manager** - Manage sermon content
7. **Form Submissions** - View contact form submissions

### Low Priority:
8. **User Management** - Add/edit admin users
9. **Serve Opportunities Manager**
10. **Next Steps Manager**

---

## 📝 Example: Building Ministries Manager

Here's a complete example to get you started:

```php
<?php
// /admin/ministries.php
$page_title = 'Manage Ministries';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM ministries WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'ministry', $id, 'Deleted ministry');
        $success = 'Ministry deleted successfully';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $summary = $_POST['summary'];
        $display_order = (int)$_POST['display_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE ministries SET title = ?, summary = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $summary, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'ministry', $id, 'Updated ministry: ' . $title);
            $success = 'Ministry updated successfully';
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO ministries (title, summary, display_order, visible) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $summary, $display_order, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'ministry', $new_id, 'Created ministry: ' . $title);
            $success = 'Ministry created successfully';
        }
    }
}

// Fetch all ministries
$ministries = $pdo->query("SELECT * FROM ministries ORDER BY display_order")->fetchAll();

// Get ministry for editing
$edit_ministry = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ministries WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_ministry = $stmt->fetch();
}
?>

<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= $edit_ministry ? 'Edit' : 'Add New'; ?> Ministry</h2>
    </div>

    <form method="post">
        <?= csrf_field(); ?>
        <?php if ($edit_ministry): ?>
            <input type="hidden" name="id" value="<?= $edit_ministry['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($edit_ministry['title'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>Summary</label>
            <textarea name="summary" class="wysiwyg"><?= htmlspecialchars($edit_ministry['summary'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Display Order</label>
            <input type="number" name="display_order" value="<?= $edit_ministry['display_order'] ?? 0; ?>">
        </div>

        <div class="form-group">
            <label class="toggle-switch">
                <input type="checkbox" name="visible" value="1" <?= ($edit_ministry['visible'] ?? 1) ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
            <span style="margin-left: 1rem;">Visible on website</span>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">Save Ministry</button>
            <?php if ($edit_ministry): ?>
                <a href="/admin/ministries.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Ministries</h2>
    </div>

    <?php if (empty($ministries)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">⛪</div>
            <p>No ministries yet. Add one above!</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Title</th>
                        <th>Summary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ministries as $ministry): ?>
                        <tr>
                            <td><?= $ministry['display_order']; ?></td>
                            <td><?= htmlspecialchars($ministry['title']); ?></td>
                            <td><?= htmlspecialchars(substr($ministry['summary'], 0, 80)) . '...'; ?></td>
                            <td>
                                <?php if ($ministry['visible']): ?>
                                    <span class="badge badge-success">Visible</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?= $ministry['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="?delete=<?= $ministry['id']; ?>" class="btn btn-sm btn-danger" data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

---

## 🎯 Next Steps

1. ✅ Run `/install.php` to set up the database
2. ✅ Log in to `/admin` with your credentials
3. ✅ Test the Site Settings page
4. 🔨 Build additional admin sections using the example above
5. 🔧 Customize as needed for your church's workflow

---

## 💡 Tips

- **Always use CSRF protection** on forms
- **Log important activities** for audit trail
- **Use prepared statements** to prevent SQL injection
- **Test thoroughly** before deploying to production
- **Backup your database** regularly

---

## 🆘 Need Help?

Common issues and solutions:

### "Database connection failed"
- Check MySQL is running
- Verify database credentials in `/includes/db-config.php`
- Ensure database user has proper permissions

### "Invalid security token"
- Your session may have expired
- Log out and log back in
- Check that cookies are enabled

### "Page not found" for admin routes
- Ensure you're using `php -S localhost:8999 router.php`
- Check that router.php includes the admin routes

---

Good luck building out your admin panel! 🚀
