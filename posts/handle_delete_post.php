<?php
// File: /posts/handle_delete_post.php

require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //  Get data from the form
    $post_id = $_POST['post_id'];
    $current_user_id = $_SESSION['user_id'];

    //  Authorization Check: Fetch the post's author ID from the database
    $stmt = $conn->prepare("SELECT user_id FROM blogpost WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $post = $result->fetch_assoc();
        $author_id = $post['user_id'];
        
        //  Check if the current user is the author of the post
        if ($current_user_id == $author_id) {
            // 3. Authorization successful, proceed with deletion
            $delete_stmt = $conn->prepare("DELETE FROM blogpost WHERE id = ?");
            $delete_stmt->bind_param("i", $post_id);

            if ($delete_stmt->execute()) {
                // Deletion successful
                // Due to "ON DELETE CASCADE", entries in `post_hashtags` are auto-deleted.
                header('Location: ../index.php'); // Redirect to home page
                exit();
            } else {
                die("Error: Could not delete the post.");
            }
            $delete_stmt->close();
        } else {
            // Authorization failed
            die("Error: You are not authorized to delete this post.");
        }
    } else {
        die("Error: Post not found.");
    }
    $stmt->close();
    $conn->close();
} else {
    // Not a POST request
    header('Location: ../index.php');
    exit();
}
?>