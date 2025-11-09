# echoes&edges blog app

A lightweight PHP/MySQL blog with a rich-text editor, hashtags, search & tag filtering, and safe HTML rendering. Designed to run on shared hosts like InfinityFree (with phpMyAdmin).

---

##  Features

-  Rich-text editor (bold/italic/underline, colors, alignment, image insert)
-  User-based post management (create, edit, delete)
-  Hashtags with many-to-many relationships
-  Search by title/content + filter by tags
-  Dropdown suggestions for:
  - Post titles in the search box
  - Tags in both filter & create-post inputs
-  Sanitized HTML rendering (keeps styling, removes unsafe code)
-  Debug mode (`?debug=1`) for database troubleshooting

---

 Project Structure

```
htdocs/
├─ .env                          # Database credentials
├─ index.php                     # Homepage + search/filter
├─ single_post.php               # Full post view
├─ dashboard.php                 # User dashboard
├─ create_post.php               # Rich text editor + tag autocomplete
├─ posts/
│  ├─ handle_create_post.php     # Post creation handler
│  ├─ handle_delete_post.php     # Post deletion handler
│  └─ upload_image.php           # Image upload for editor
├─ api/
│  └─ get_hashtags.php           # Tag suggestion endpoint (JSON)
├─ config/
│  └─ database.php               # MySQL connection logic
└─ templates/
   └─ includes/
      ├─ header.php              # Header + navigation
      ├─ footer.php              # Footer
      └─ safe_html.php           # Sanitizer + helper functions
```

---

 Requirements

- PHP 7.4+ (works on PHP 8.x)
- MySQL 5.7+ / MariaDB
- `mysqli` extension (standard on InfinityFree)
- Optional: `DOM`/`libxml` (used by sanitizer if available)

---

 Database Schema

sql
CREATE TABLE IF NOT EXISTS user (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS blogpost (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hashtags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS post_hashtags (
  post_id INT NOT NULL,
  hashtag_id INT NOT NULL,
  PRIMARY KEY (post_id, hashtag_id),
  FOREIGN KEY (post_id) REFERENCES blogpost(id) ON DELETE CASCADE,
  FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
);
```

 Configuration

 `.env` File
Place in `/htdocs/.env`:

```
DB_HOST=sql303.infinityfree.com
DB_USER=if0_40355423
DB_PASS=YOUR_EXACT_PASSWORD
DB_NAME=if0_40355423_blog_db
DB_PORT=3306
```

> ⚠️ Ensure this file is named exactly `.env` (not `.env.txt`) and is in the root directory.

 Secure `.env`

Add an `.htaccess` file at the root:

```
<FilesMatch "^(\.env|.*\.ini)$">
  Require all denied
</FilesMatch>
```

---

 InfinityFree Deployment

1. Create a MySQL database from your InfinityFree panel.
2. Copy the credentials and paste them into `.env`.
3. Upload all files into `/htdocs`.
4. Import the database tables via phpMyAdmin.
5. Open your site and test at `/index.php`.
6. If connection fails, use `/index.php?debug=1` to see DB details.



##  How It Works

### 🔗 `config/database.php`
- Connects using `mysqli` over TCP (InfinityFree-safe).
- Reads `.env` automatically.
- Prints connection info on `?debug=1`.

###  `templates/includes/safe_html.php`
- Cleans HTML safely but keeps visual formatting.
- Removes `<script>`, inline JS, `javascript:` URLs, unsafe styles.
- Uses DOM when available; falls back to regex otherwise.

###  `index.php`
- Displays latest posts (sanitized previews).
- Search & tag filter logic with prepared statements.
- Tag suggestions from `/api/get_hashtags.php`.
- Search suggestions inline via `?ajax=search_suggest`.

###  `create_post.php`
- Rich-text editor with toolbar.
- Image upload handled by `/posts/upload_image.php`.
- Tag input supports auto-suggestions via `/api/get_hashtags.php`.

###  `api/get_hashtags.php`
- Returns JSON:
  ```json
  { "ok": true, "items": ["poetry","fiction","coding"] }
  ```
- Used by both create and index pages.

 Troubleshooting

 "HY000/2002: No such file or directory"
- Use InfinityFree DB hostname, *not* `localhost`.
- Ensure `.env` exists and uses the correct values.
- Confirm `config/database.php` is the latest InfinityFree-safe version.

 "Access denied for user"
- Wrong username/password or missing prefix.
- Copy exact credentials from InfinityFree’s “MySQL Databases” panel.

Blank page
- Add `?debug=1` to show errors.
- Ensure `safe_html.php` exists and PHP 7.4+ is enabled.

 No suggestions showing
- Visit `/api/get_hashtags.php?q=a` directly — should return JSON.
- Check console for JS errors (Network tab).

---

 Security Notes

- HTML sanitizer prevents XSS and JS injection.
- All database queries use prepared statements.
- `.env` protected by `.htaccess`.
- File uploads limited to images (handled separately).




**Author:** Umair  
**Environment:** InfinityFree + phpMyAdmin  
**Language Stack:** PHP, MySQL, HTML, CSS, JavaScript
