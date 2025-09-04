<?php

// Define the base path to the root of your application
// This helps in including files relative to the project root,
// regardless of where index.php is executed from.
define('BASE_PATH', dirname(__DIR__));

// 1. Error Reporting (for development purposes)
// In production, you would typically disable display_errors and log errors instead.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Autoloader
// This simple autoloader will include class files based on their namespace and class name.
// It assumes classes are in the 'app/' directory and follow a simple file naming convention
// (e.g., 'App\Controllers\MyController' -> 'app/controllers/MyController.php').
spl_autoload_register(function ($class) {
    // Replace backslashes with directory separators
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    // Remove 'App\' prefix if used, assuming all application classes are in the 'App' namespace
    // Adjust this if you don't use namespaces or use a different root namespace.
    $class = preg_replace('/^App' . DIRECTORY_SEPARATOR . '/', '', $class);

    $file = BASE_PATH . '/app/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        // Optional: Log missing class files
        // error_log("Autoloader failed to load class: " . $class . " from file: " . $file);
    }
});

// 3. Load Application Configuration
// These files will return arrays which are then merged into a global config array or passed to a Config class.
$config = require_once BASE_PATH . '/app/config/app.php';
$databaseConfig = require_once BASE_PATH . '/app/config/database.php';

// Merge configurations, if desired, or pass them individually to relevant classes.
// For simplicity, we'll make them available globally here, but a dedicated Config class is better.
$appConfig = array_merge($config, ['database' => $databaseConfig]);

// 4. Start Session (using a dedicated SessionManager is recommended)
// If you don't have app/SessionManager.php yet, you can use session_start();
// session_start(); // Basic PHP session start

// Or, using a SessionManager class:
use App\SessionManager; // Assuming SessionManager is in the App namespace
SessionManager::startSession($appConfig['session_name'] ?? 'app_session');


// 5. Include the Core Application file
// This file will contain your Router and other core functionalities.
require_once BASE_PATH . '/app/Core.php';

// 6. Instantiate the Core/Router and handle the request
// The Core class (or a dedicated Router) will parse the URL, match it against defined routes,
// and then instantiate the correct controller and call its method.

// Use the App namespace for Core, as per our autoloader assumption
use App\Core;

// Pass the app configuration and define routes
$routes = require_once BASE_PATH . '/app/config/routes.php';

$app = new Core($appConfig, $routes);
$app->run();

?>