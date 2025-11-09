<?php
// Include  header template
include 'templates/includes/header.php';
?>

<div class="form-container">
    <h2>Register</h2>
    
    <?php
    // Check for error messages from the session and display them
    if (isset($_SESSION['error_message'])) {
        echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
        // Unset the error message 
        unset($_SESSION['error_message']);
    }
    ?>

    <form action="auth/handle_register.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn">Register</button>
    </form>
</div>

<?php
// Include the footer 
include 'templates/includes/footer.php';
?>