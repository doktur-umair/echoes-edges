<?php
// File: /posts/handle_edit_post.php

require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) die("Access denied.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ---  Get and Sanitize Data ---
    $post_id = (int)$_POST['post_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $new_hashtags_string = str_replace('#', '', trim($_POST['hashtags']));
    $current_user_id = $_SESSION['user_id'];

    if (empty($title) || empty($content)) die("Title and content cannot be empty.");

    // ---  Authorization Check ---
    $stmt = $conn->prepare("SELECT user_id FROM blogpost WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) die("Post not found.");
    $post = $result->fetch_assoc();

    if ($current_user_id != $post['user_id']) die("You are not authorized to edit this post.");
    $stmt->close();

    // --- Start Transaction for safe database updates ---
    $conn->begin_transaction();

    try {
        // ---  Update the main post title and content ---
        $stmt = $conn->prepare("UPDATE blogpost SET title = ?, content = ? WHERE id = ?");
        $stmt->bind_param("ssi", $title, $content, $post_id);
        $stmt->execute();
        $stmt->close();

        // ---  Handle Hashtag Updates ---

        // Get the list of old hashtags from the DB
        $stmt = $conn->prepare("SELECT h.id, h.name FROM hashtags h JOIN post_hashtags ph ON h.id = ph.hashtag_id WHERE ph.post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_hashtags = [];
        while($row = $result->fetch_assoc()) {
            $old_hashtags[$row['name']] = $row['id'];
        }
        $stmt->close();
        
        // Get the list of new hashtags from the form
        $new_hashtags_array = array_map('trim', explode(',', strtolower($new_hashtags_string)));
        $new_hashtags_array = array_filter($new_hashtags_array); // Remove empty values

        // Find which tags to add and which to remove
        $tags_to_add = array_diff($new_hashtags_array, array_keys($old_hashtags));
        $tags_to_remove = array_diff(array_keys($old_hashtags), $new_hashtags_array);
        
        // Remove old hashtag links
        if (!empty($tags_to_remove)) {
            $ids_to_remove = array_map(fn($tag) => $old_hashtags[$tag], $tags_to_remove);
            $placeholders = implode(',', array_fill(0, count($ids_to_remove), '?'));
            $types = str_repeat('i', count($ids_to_remove));

            $stmt = $conn->prepare("DELETE FROM post_hashtags WHERE post_id = ? AND hashtag_id IN ($placeholders)");
            // We need to bind post_id first, then the hashtag IDs
            $params = array_merge([$post_id], $ids_to_remove);
            $stmt->bind_param("i" . $types, ...$params);
            $stmt->execute();
            $stmt->close();
        }

        // Add new hashtags and link them
        if (!empty($tags_to_add)) {
            // Re-use the logic from handle_create_post.php
            foreach ($tags_to_add as $tag_name) {
                // Check if hashtag exists
                $stmt = $conn->prepare("SELECT id FROM hashtags WHERE name = ?");
                $stmt->bind_param("s", $tag_name);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $hashtag_id = $result->fetch_assoc()['id'];
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO hashtags (name) VALUES (?)");
                    $stmt_insert->bind_param("s", $tag_name);
                    $stmt_insert->execute();
                    $hashtag_id = $conn->insert_id;
                    $stmt_insert->close();
                }
                $stmt->close();
                
                // Link post to this hashtag
                $stmt = $conn->prepare("INSERT INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $post_id, $hashtag_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // If we reach here without errors, commit all changes
        $conn->commit();
        header("Location: ../single_post.php?id=$post_id");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("An error occurred while updating the post: " . $e->getMessage());
    }
}
?>