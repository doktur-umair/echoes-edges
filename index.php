<?php
// File: index.php

// Optional debug toggle: visit index.php?debug=1 while diagnosing
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ---------- Inline AJAX only for search title suggestions ----------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_suggest') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/config/database.php';
    try {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $items = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $stmt = $conn->prepare("SELECT DISTINCT title FROM blogpost WHERE title LIKE ? ORDER BY created_at DESC LIMIT 10");
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['title'])) $items[] = $row['title'];
            }
            $stmt->close();
        }
        echo json_encode(['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'items' => []]);
    }
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    exit;
}

require_once __DIR__ . '/templates/includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/templates/includes/safe_html.php';

// Prepared statement to fetch hashtags for each post (for hashtag chips)
$tag_sql  = "SELECT h.name
             FROM hashtags h
             JOIN post_hashtags ph ON h.id = ph.hashtag_id
             WHERE ph.post_id = ?
             ORDER BY h.name ASC";
$tag_stmt = $conn->prepare($tag_sql);

// Detect if user is performing a search or tag filter
$is_searching = (isset($_GET['search']) && $_GET['search'] !== '') || (isset($_GET['tags']) && $_GET['tags'] !== '');
?>
<div class="container">
    <h2>Latest Posts</h2>

    <!-- Search/filter form -->
    <form action="index.php" method="get" style="margin: 0 0 1rem 0;">
        <input id="search" type="text" name="search" placeholder="Search posts..."
               list="searchSuggestions"
               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
        <datalist id="searchSuggestions"></datalist>

        <input id="tags" type="text" name="tags" placeholder="Filter by tag (comma separated)"
               list="tagSuggestions"
               value="<?php
                    if (isset($_GET['tags'])) {
                        echo is_array($_GET['tags'])
                            ? htmlspecialchars(implode(', ', array_map('strval', $_GET['tags'])))
                            : htmlspecialchars($_GET['tags']);
                    }
               ?>">
        <datalist id="tagSuggestions"></datalist>

        <button type="submit">Filter</button>
        <?php if ($is_searching): ?>
            <a href="index.php" class="btn" style="margin-left: .5rem;">Clear</a>
        <?php endif; ?>
    </form>

<?php
if ($is_searching) {
    echo "<h3>Search Results</h3>";

    $search_term   = isset($_GET['search']) ? trim($_GET['search']) : '';
    $selected_tags = $_GET['tags'] ?? [];

    // Normalize tags to an array (supports ?tags=tag1,tag2 or ?tags[]=tag1&tags[]=tag2)
    if (!is_array($selected_tags)) {
        $selected_tags = array_filter(array_map('trim', explode(',', $selected_tags)));
    }

    // Base query joins blogpost+user
    $sql = "SELECT DISTINCT bp.id, bp.title, bp.content, bp.created_at, u.username
            FROM `blogpost` AS bp
            JOIN `user` AS u ON bp.user_id = u.id";

    $where  = [];
    $params = [];
    $types  = '';

    // If tags are provided, join through tag tables & filter by tag names (ANY match)
    if (!empty($selected_tags)) {
        $sql .= " JOIN `post_hashtags` AS ph ON ph.post_id = bp.id
                  JOIN `hashtags` AS h ON h.id = ph.hashtag_id";
        $placeholders = implode(',', array_fill(0, count($selected_tags), '?'));
        $where[] = "h.name IN ($placeholders)";
        foreach ($selected_tags as $t) {
            $params[] = $t;
            $types   .= 's';
        }
    }

    // Text search in title or content
    if ($search_term !== '') {
        $where[] = "(bp.title LIKE ? OR bp.content LIKE ?)";
        $like = "%{$search_term}%";
        $params[] = $like; $params[] = $like;
        $types   .= 'ss';
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY bp.created_at DESC LIMIT 50";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<div class="posts-list">';
    if ($result->num_rows === 0) {
        echo "<p>No posts matched.</p>";
    } else {
        while ($post = $result->fetch_assoc()) {
            $post_id = (int)$post['id'];

            // Fetch hashtags for this post
            $post_hashtags = [];
            if ($tag_stmt) {
                $tag_stmt->bind_param("i", $post_id);
                $tag_stmt->execute();
                $tags_res = $tag_stmt->get_result();
                while ($row = $tags_res->fetch_assoc()) {
                    $post_hashtags[] = $row['name'];
                }
            }

            echo '<article class="post-item">';
            echo '<h2><a href="single_post.php?id='.$post_id.'">'.htmlspecialchars($post['title']).'</a></h2>';
            echo '<p class="post-meta">By '.htmlspecialchars($post['username']).' on '.date('F j, Y', strtotime($post['created_at'])).'</p>';

            // Hashtag chips (link back to index filter)
            if (!empty($post_hashtags)) {
                echo '<div class="hashtags">';
                foreach ($post_hashtags as $tagName) {
                    $url = 'index.php?tags=' . urlencode($tagName);
                    echo '<a class="hashtag" href="'.$url.'">#'.htmlspecialchars($tagName).'</a> ';
                }
                echo '</div>';
            }

            // Sanitized HTML preview with clipping
            echo '<div class="post-excerpt" style="max-height:8rem; overflow:hidden;">';
            echo sanitize_html($post['content']);
            echo '</div>';

            echo '<a class="read-more" href="single_post.php?id='.$post_id.'">Read &rarr;</a>';
            echo '</article>';
        }
    }
    echo '</div>';

    $stmt->close();
} else {
    // --- Not searching: show latest posts across the site
    $limit = 10; // adjust if you want more/less on the homepage

    $sql = "SELECT bp.id, bp.title, bp.content, bp.created_at, u.username
            FROM blogpost AS bp
            JOIN user AS u ON bp.user_id = u.id
            ORDER BY bp.created_at DESC
            LIMIT " . (int)$limit;

    $res = $conn->query($sql);

    echo '<div class="posts-list">';
    if ($res && $res->num_rows > 0) {
        while ($post = $res->fetch_assoc()) {
            $post_id = (int)$post['id'];

            // Fetch hashtags for this post
            $post_hashtags = [];
            if ($tag_stmt) {
                $tag_stmt->bind_param("i", $post_id);
                $tag_stmt->execute();
                $tags_res = $tag_stmt->get_result();
                while ($row = $tags_res->fetch_assoc()) {
                    $post_hashtags[] = $row['name'];
                }
            }

            echo '<article class="post-item">';
            echo '<h2><a href="single_post.php?id='.$post_id.'">'.htmlspecialchars($post['title']).'</a></h2>';
            echo '<p class="post-meta">By '.htmlspecialchars($post['username']).' on '.date('F j, Y', strtotime($post['created_at'])).'</p>';

            if (!empty($post_hashtags)) {
                echo '<div class="hashtags">';
                foreach ($post_hashtags as $tagName) {
                    $url = 'index.php?tags=' . urlencode($tagName);
                    echo '<a class="hashtag" href="'.$url.'">#'.htmlspecialchars($tagName).'</a> ';
                }
                echo '</div>';
            }

            // Sanitized HTML preview with clipping
            echo '<div class="post-excerpt" style="max-height:8rem; overflow:hidden;">';
            echo sanitize_html($post['content']);
            echo '</div>';

            echo '<a class="read-more" href="single_post.php?id='.$post_id.'">Read &rarr;</a>';
            echo '</article>';
        }
    } else {
        echo "<p>No posts yet.</p>";
    }
    echo '</div>';
}

// Cleanup
if ($tag_stmt) { $tag_stmt->close(); }
$conn->close();
?>

</div>
<button class="btn-create"><a href="create_post.php" class="href">+ NEW</a></button>

<?php require_once __DIR__ . '/templates/includes/footer.php'; ?>

<!-- Inline JS to wire dropdown suggestions -->
<script>
(function () {
  function debounce(fn, delay) {
    var t; return function() {
      var ctx = this, args = arguments;
      clearTimeout(t); t = setTimeout(function(){ fn.apply(ctx, args); }, delay);
    };
  }

  // Search title suggestions via inline AJAX in this file
  var searchInput = document.getElementById('search');
  var searchList  = document.getElementById('searchSuggestions');
  var abortSearch = null;

  function fetchSearch(term) {
    if (abortSearch) abortSearch.abort();
    abortSearch = new AbortController();
    var url = 'index.php?ajax=search_suggest&q=' + encodeURIComponent(term || '');
    fetch(url, { signal: abortSearch.signal, headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.ok) return;
        searchList.innerHTML = '';
        data.items.forEach(function (title) {
          var opt = document.createElement('option');
          opt.value = title;
          searchList.appendChild(opt);
        });
      })
      .catch(function(){});
  }

  if (searchInput && searchList) {
    searchInput.addEventListener('input', debounce(function () {
      var term = searchInput.value.trim();
      if (term.length >= 1) fetchSearch(term);
    }, 150));
  }

  // Tag suggestions use your shared /api/get_hashtags.php
  var tagsInput = document.getElementById('tags');
  var tagList   = document.getElementById('tagSuggestions');
  var abortTags = null;

  function currentTagTerm() {
    var parts = tagsInput.value.split(',');
    return parts[parts.length - 1].trim();
  }

  function fetchTags(term) {
    if (abortTags) abortTags.abort();
    abortTags = new AbortController();
    var url = 'api/get_hashtags.php?q=' + encodeURIComponent(term || '');
    fetch(url, { signal: abortTags.signal, headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.ok) return;
        tagList.innerHTML = '';
        data.items.forEach(function (name) {
          var opt = document.createElement('option');
          opt.value = name;
          tagList.appendChild(opt);
        });
      })
      .catch(function(){});
  }

  if (tagsInput && tagList) {
    fetchTags('');
    tagsInput.addEventListener('input', debounce(function () {
      var term = currentTagTerm();
      if (term.length >= 1) fetchTags(term);
    }, 150));
  }
})();
</script>
