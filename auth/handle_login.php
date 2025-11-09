<?php
//  database connection 
require_once '../config/database.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    //  Sanitize and retrieve user inputs
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    //  Validate inputs
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "Please fill in all fields.";
        header('Location: ../login.php');
        exit();
    }

    //  Fetch the user from the database
    // Using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password, role FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result(); // Get the result of the query

    if ($result->num_rows === 1) {
        // User found, now verify the password
        $user = $result->fetch_assoc();

        //  Verify the hashed password
        
        if (password_verify($password, $user['password'])) {
            // Password is correct, so start a new session
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Store user data in the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect to a landing page 
            header('Location: ../dashboard.php');
            exit();
        } else {
            // Password is not valid
            $_SESSION['error_message'] = "Invalid username or password.";
            header('Location: ../login.php');
            exit();
        }
    } else {
        // No user found with that username
        $_SESSION['error_message'] = "Invalid username or password.";
        header('Location: ../login.php');
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    // Redirect if accessed directly
    header('Location: ../login.php');
    exit();
}
?>