<?php
// Include the header. 
require_once 'templates/includes/header.php';
?>

<div class="form-container">
    <h2>Login</h2>
    
    <?php
    // Display any error messages from the session
    if (isset($_SESSION['error_message'])) {
        echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
        unset($_SESSION['error_message']); // Clear the message
    }

    // Display a success message after registration
    if (isset($_SESSION['success_message'])) {
        echo '<div class="success-message">' . $_SESSION['success_message'] . '</div>';
        unset($_SESSION['success_message']); // Clear the message
    }
    ?>

    <!--  the PHP script to process the login -->
    <form action="auth/handle_login.php" method="POST">
        <div class="form-group">
            
            <input type="text" id="username" name="username" placeholder="username" required>
        </div>
        <div class="form-group">
            
            <input type="password" id="password" name="password" placeholder="password" required>
        </div>
        <button type="submit" class="btn">Login</button>
    </form>
</div>

<?php
// Include the footer
require_once 'templates/includes/footer.php';
?>
