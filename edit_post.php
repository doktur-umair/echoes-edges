<?php
// File: /edit_post.php

require_once 'templates/includes/header.php';
require_once 'config/database.php';

// Must be logged in to access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get the post ID from URL and fetch data
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($post_id === 0) die("Invalid post ID.");

// Fetch the post
$stmt = $conn->prepare("SELECT title, content, user_id FROM blogpost WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Post not found.");
$post = $result->fetch_assoc();

//  Authorization Check: Make sure logged-in user is the author
if ($_SESSION['user_id'] != $post['user_id']) {
    die("You are not authorized to edit this post.");
}

//  Fetch the current hashtags for this post
$stmt = $conn->prepare("SELECT h.name FROM hashtags h JOIN post_hashtags ph ON h.id = ph.hashtag_id WHERE ph.post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$current_hashtags = [];
while($row = $result->fetch_assoc()) {
    $current_hashtags[] = $row['name'];
}
$hashtags_string = implode(', ', $current_hashtags);
?>

<div class="form-container">
    <h2>Edit Blog Post</h2>

    <form action="posts/handle_edit_post.php" method="POST">
        <!-- send post_ID -->
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
        </div>
        <div class="form-group">
            <label for="content">Content</label>
            <textarea id="content" name="content" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>
        
        <!-- Pre-filling  tag input  -->
        <div class="form-group">
            <label for="hashtags-input">Hashtags</label>
            <div id="tag-container" class="tag-container">
                <input type="text" id="hashtags-input" placeholder="Type and press Enter...">
            </div>
            <div id="suggestions-box" class="suggestions-box"></div>
            <input type="hidden" name="hashtags" id="hidden-hashtags-input" value="<?php echo htmlspecialchars($hashtags_string); ?>">
            <small>Separate hashtags with commas.</small>
        </div>

        <button type="submit" class="btn">Update Post</button>
    </form>
</div>



<?php
require_once 'templates/includes/footer.php';
?>