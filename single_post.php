<?php
// File: /single_post.php

require_once 'templates/includes/header.php';
require_once 'config/database.php';
require_once 'templates/includes/safe_html.php';

// --- 1. Get the post ID from the URL and Validate it ---
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($post_id === 0) {
    echo "<div class='container'><p class='error-message'>Invalid post ID.</p></div>";
    require_once 'templates/includes/footer.php';
    exit();
}

$_SESSION['last_viewed_post_id'] = $post_id; //for recommendation

// --- 2. Fetch the Main Post Details ---
$sql = "SELECT bp.id, bp.user_id, bp.title, bp.content, bp.created_at, bp.updated_at,
       u.username
FROM `blogpost` AS bp
JOIN `user` AS u ON bp.user_id = u.id
WHERE bp.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='container'><p class='error-message'>Post not found.</p></div>";
    require_once 'templates/includes/footer.php';
    exit();
}
$post = $result->fetch_assoc();

// --- 3. Fetch the Hashtags for this Post ---
$hashtags_sql = "SELECT h.name FROM hashtags h JOIN post_hashtags ph ON h.id = ph.hashtag_id WHERE ph.post_id = ?";
$hashtag_stmt = $conn->prepare($hashtags_sql);
$hashtag_stmt->bind_param("i", $post_id);
$hashtag_stmt->execute();
$hashtags_result = $hashtag_stmt->get_result();
$post_hashtags = [];
while ($row = $hashtags_result->fetch_assoc()) {
    $post_hashtags[] = $row['name'];
}
?>

<div class="container">
    <div class="single-post">
        <h1><?php echo htmlspecialchars($post['title']); ?></h1>
        <p class="post-meta">
            By <?php echo htmlspecialchars($post['username']); ?> on <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
        </p>
        <?php
        // Authorization Check: Show Edit/Delete buttons ONLY if the logged-in user is the author.

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $post['user_id']) {
        ?>
            <div class="post-actions">
                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn">Edit Post</a>
                <form action="posts/handle_delete_post.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');" style="display:inline;">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" class="btn btn-danger">Delete Post</button>
                </form>
            </div>
        <?php
        }
        ?>

        <!-- Display the hashtags for the current post -->
        <div class="hashtags">
            <?php foreach ($post_hashtags as $tag): ?>
                <a href="tag_posts.php?tag=<?php echo urlencode($tag); ?>" class="hashtag">
                    #<?php echo htmlspecialchars($tag); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="post-body">

            <?php echo nl2br(sanitize_html($post['content'])); ?>
        </div>
    </div>

    <hr>

    <!-- ---  Recommendation Engine Logic --- -->
    <h3>Recommended For You</h3>
    <div class="recommendations">
        <?php
        $recommended_posts = [];
        if (!empty($post_hashtags)) {
            // Create placeholders (?,?,?) for the IN clause
            $placeholders = implode(',', array_fill(0, count($post_hashtags), '?'));


            $recommendation_sql = "
            SELECT 
                bp.id, bp.title, u.username, COUNT(ph.hashtag_id) as matching_tags
            FROM post_hashtags ph
            JOIN blogpost bp ON ph.post_id = bp.id
            JOIN user u ON bp.user_id = u.id
            JOIN hashtags h ON ph.hashtag_id = h.id
            WHERE h.name IN ($placeholders) AND bp.id != ?
            GROUP BY bp.id, bp.title, u.username
            ORDER BY matching_tags DESC
            LIMIT 5
        ";

            $rec_stmt = $conn->prepare($recommendation_sql);

            // Bind the parameters
            $types = str_repeat('s', count($post_hashtags)) . 'i';
            $params = array_merge($post_hashtags, [$post_id]);
            $rec_stmt->bind_param($types, ...$params);

            $rec_stmt->execute();
            $recommendations_result = $rec_stmt->get_result();
            while ($rec_post = $recommendations_result->fetch_assoc()) {
                $recommended_posts[] = $rec_post;
            }
        }

        if (!empty($recommended_posts)) {
            echo '<ul>';
            foreach ($recommended_posts as $rec) {
                echo '<li><a href="single_post.php?id=' . $rec['id'] . '">' . htmlspecialchars($rec['title']) . '</a> <small>by ' . htmlspecialchars($rec['username']) . ' (' . $rec['matching_tags'] . ' matching tags)</small></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No related posts found.</p>';
        }
        ?>
    </div>

</div>

<?php
$stmt->close();
$hashtag_stmt->close();
if (isset($rec_stmt)) $rec_stmt->close();
$conn->close();
require_once 'templates/includes/footer.php';
?>