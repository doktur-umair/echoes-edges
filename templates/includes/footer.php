</main>
<footer>
   <center>
   <?php if (isset($_SESSION['user_id'])): ?>
         <p>You are signed in as, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
   <?php endif; ?>
   </center>
   <center> <p>&copy; <?php echo date("Y"); ?> Echoes & Edges<br> created by Umair Hassan</p></center>
</footer>

<script src="public/js/script.js"></script>
</body>
</html>