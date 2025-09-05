<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontaktformular</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Kontaktieren Sie uns</h1>

        <?php
        if (isset($_GET['status'])) {
            if ($_GET['status'] == 'success') {
                echo '<p class="success">Ihre Nachricht wurde erfolgreich gesendet und gespeichert!</p>';
            } elseif ($_GET['status'] == 'error') {
                echo '<p class="error">Es gab ein Problem beim Senden oder Speichern Ihrer Nachricht. Bitte versuchen Sie es erneut.</p>';
            } elseif ($_GET['status'] == 'validation_error') {
                echo '<p class="error">Bitte füllen Sie alle erforderlichen Felder aus.</p>';
            }
        }
        ?>

        <form action="process.php" method="POST">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">E-Mail:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="subject">Betreff (optional):</label>
                <input type="text" id="subject" name="subject">
            </div>

            <div class="form-group">
                <label for="message">Nachricht:</label>
                <textarea id="message" name="message" rows="8" required></textarea>
            </div>

            <button type="submit">Nachricht senden</button>
        </form>
    </div>
</body>
</html>
