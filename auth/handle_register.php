<?php
// Include the database connection file
require_once '../config/database.php';

// Check if the form was submitted using the POST method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    //  Sanitize and retrieve user inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'user'; // Default role for new users

    //  Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Please fill in all fields.";
        header('Location: ../register.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format.";
        header('Location: ../register.php');
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header('Location: ../register.php');
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error_message'] = "Password must be at least 6 characters long.";
        header('Location: ../register.php');
        exit();
    }

    //  Check if username or email already exists in the database
    
    $stmt = $conn->prepare("SELECT id FROM user WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error_message'] = "Username or email already taken.";
        header('Location: ../register.php');
        exit();
    }
    $stmt->close();

    //  Hash the password for security
   
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    //  Insert the new user into the database
    $stmt = $conn->prepare("INSERT INTO user (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
        // Registration successful
        $_SESSION['success_message'] = "Registration successful! Please log in.";
        header('Location: ../login.php'); // Redirect to login page 
        exit();
    } else {
        // Handle database error
        $_SESSION['error_message'] = "An error occurred. Please try again.";
        header('Location: ../register.php');
        exit();
    }
    $stmt->close();
    $conn->close();

} else {
    // If someone tries to access this file directly without POST method
    header('Location: ../register.php');
    exit();
}
?>