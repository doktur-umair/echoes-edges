<?php
// File: /tag_posts.php

require_once 'templates/includes/header.php';
require_once 'config/database.php';

//  Get the tag name from the URL and validate it
$tag_name = isset($_GET['tag']) ? trim($_GET['tag']) : '';

if (empty($tag_name)) {
    echo "<div class='container'><p class='error-message'>No tag specified.</p></div>";
    require_once 'templates/includes/footer.php';
    exit();
}
?>

<div class="container">
    <!-- Display a clear heading for the page -->
    <h2>Posts Tagged with: <span class="hashtag-title">#<?php echo htmlspecialchars($tag_name); ?></span></h2>
    <hr>
    
    <div class="posts-list">
    <?php
        // The SQL query to find all posts linked to this specific tag name
        $sql = "SELECT bp.id, bp.title, bp.content, bp.created_at, u.username
                FROM blogpost bp
                JOIN user u ON bp.user_id = u.id
                JOIN post_hashtags ph ON bp.id = ph.post_id
                JOIN hashtags h ON ph.hashtag_id = h.id
                WHERE h.name = ?
                ORDER BY bp.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tag_name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            // Loop through and display the posts (re-using the same layout as index.php)
            while($post = $result->fetch_assoc()) {
                ?>
                <div class="post-item">
                    <h2>
                        <a href="single_post.php?id=<?php echo $post['id']; ?>">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                    </h2>
                    <p class="post-meta">
                        By <?php echo htmlspecialchars($post['username']); ?> on <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                    </p>
                    <p class="post-excerpt">
                        <?php echo htmlspecialchars(substr($post['content'], 0, 150)) . '...'; ?>
                    </p>
                    <a href="single_post.php?id=<?php echo $post['id']; ?>" class="read-more">Read More &rarr;</a>
                </div>
                <?php
            }
        } else {
            // Show a message if no posts are found for this tag
            echo "<p>No posts found for the tag '#" . htmlspecialchars($tag_name) . "'.</p>";
        }

        $stmt->close();
        $conn->close();
    ?>
    </div>
</div>

<?php
require_once 'templates/includes/footer.php';
?>