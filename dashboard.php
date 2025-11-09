<?php
// File: /dashboard.php

require_once 'templates/includes/header.php';
require_once 'config/database.php'; // DB connection ($conn)
require_once 'templates/includes/safe_html.php';


// Only logged-in users can access the dashboard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$limit   = 10; // how many latest posts to show on the dashboard

// --- Fetch latest posts by this user ---
$sql = "SELECT bp.id, bp.title, bp.content, bp.created_at
        FROM blogpost AS bp
        WHERE bp.user_id = ?
        ORDER BY bp.created_at DESC
        LIMIT " . (int)$limit; // cast to int to keep LIMIT safe/portable

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Prepared statement to fetch hashtags per post
$tag_sql  = "SELECT h.name
             FROM hashtags h
             JOIN post_hashtags ph ON h.id = ph.hashtag_id
             WHERE ph.post_id = ?";
$tag_stmt = $conn->prepare($tag_sql);

// Small helper for an excerpt
function make_excerpt($text, $length = 150)
{
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return (mb_strlen($text, 'UTF-8') > $length)
            ? mb_substr($text, 0, $length, 'UTF-8') . '...'
            : $text;
    }
    return (strlen($text) > $length) ? substr($text, 0, $length) . '...' : $text;
}
?>

<div class="container">
    <h2>Dashboard</h2>
    <p>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
    <p>Welcome to your dashboard. From here you can manage your blog posts.</p>

    <!-- Flash messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?php echo $_SESSION['success_message']; ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message"><?php echo $_SESSION['error_message']; ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <a href="create_post.php" class="btn">+ New Post</a>

    <hr>
    <h3>Your Latest Posts</h3>

    <div class="posts-list">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($post = $result->fetch_assoc()): ?>
                <?php
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

                $excerpt = make_excerpt($post['content'], 150);
                ?>
                <div class="post-item">
                    <h2>
                        <a href="single_post.php?id=<?php echo $post_id; ?>">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                    </h2>

                    <p class="post-meta">
                        By <?php echo htmlspecialchars($_SESSION['username']); ?>
                        on <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                    </p>

                    <?php if (!empty($post_hashtags)): ?>
                        <div class="hashtags">
                            <?php foreach ($post_hashtags as $tag): ?>
                                <a href="tag_posts.php?tag=<?php echo urlencode($tag); ?>" class="hashtag">
                                    #<?php echo htmlspecialchars($tag); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="post-excerpt">
                        <?php echo htmlspecialchars(html_excerpt($post['content'], 150)); ?>
                    </p>


                    <div class="actions">
                        <a href="edit_post.php?id=<?php echo $post_id; ?>" class="btn">Edit</a>
                        <form action="posts/handle_delete_post.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                            <button type="submit" class="btn">Delete</button>
                        </form>
                        <a href="single_post.php?id=<?php echo $post_id; ?>" class="read-more">View &rarr;</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>You haven’t created any posts yet. Click “+ New Post” to get started.</p>
        <?php endif; ?>
    </div>
</div>

<?php
if ($tag_stmt) {
    $tag_stmt->close();
}
$stmt->close();
$conn->close();
require_once 'templates/includes/footer.php';
?>