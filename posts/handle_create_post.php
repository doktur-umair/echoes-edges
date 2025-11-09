<?php
require_once '../config/database.php';

// Only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    // Or redirect them to login page with an error
    die("Access denied. Please log in.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- 1. Get and Sanitize Post Data ---
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];
    $hashtags_string = trim($_POST['hashtags']);

    if (empty($title) || empty($content)) {
        $_SESSION['error_message'] = "Title and content cannot be empty.";
        header('Location: ../create_post.php');
        exit();
    }

    $conn->begin_transaction();

    try {
        // --- 3. Insert the Blog Post ---
        $stmt = $conn->prepare("INSERT INTO blogpost (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        $stmt->execute();

        // Get the ID of the newly inserted post
        $new_post_id = $conn->insert_id;
        $stmt->close();

        // --- 4. Process and Insert Hashtags ---
        if (!empty($hashtags_string)) {
            // Sanitize the input: remove the '#' symbol and split by comma
            $clean_hashtags_string = str_replace('#', '', $hashtags_string);
            $hashtags_array = array_map('trim', explode(',', $clean_hashtags_string));

            foreach ($hashtags_array as $tag_name) {
                if (empty($tag_name)) continue; // Skip empty tags

                $tag_name_lower = strtolower($tag_name);

                // Check if the hashtag already exists in the `hashtags` table
                $stmt = $conn->prepare("SELECT id FROM hashtags WHERE name = ?");
                $stmt->bind_param("s", $tag_name_lower);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Hashtag exists, get its ID
                    $row = $result->fetch_assoc();
                    $hashtag_id = $row['id'];
                } else {
                    // Hashtag does not exist, so insert it
                    $stmt_insert = $conn->prepare("INSERT INTO hashtags (name) VALUES (?)");
                    $stmt_insert->bind_param("s", $tag_name_lower);
                    $stmt_insert->execute();
                    $hashtag_id = $conn->insert_id;
                    $stmt_insert->close();
                }
                $stmt->close();
                
                // --- 5. Link the Post and Hashtag in the Junction Table ---
                $stmt = $conn->prepare("INSERT INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $new_post_id, $hashtag_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // If everything was successful, commit the transaction
        $conn->commit();
        header('Location: ../index.php'); // Redirect to home page after success
        exit();

    } catch (mysqli_sql_exception $exception) {
        // If any error occurred, roll back all the changes
        $conn->rollback();

        $_SESSION['error_message'] = "An error occurred while creating the post. " . $exception->getMessage();
        header('Location: ../create_post.php');
        exit();
    }
}
?>