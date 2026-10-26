<?php
// Check various possible error log locations
$possible_logs = [
    'c:\\xampp\\php\\logs\\php_error_log.txt',
    'c:\\xampp\\logs\\php_error_log.txt',
    getcwd() . '\\php_error_log.txt',
    sys_get_temp_dir() . '\\php_errors.log',
];

echo "<h2>PHP Error Log Viewer</h2>";

// Try to find and read the error log
$error_log = ini_get('error_log');
echo "<p><strong>Configured error_log path:</strong> " . htmlspecialchars($error_log) . "</p>";

if ($error_log && file_exists($error_log)) {
    echo "<p>File exists! Showing last 50 lines:</p>";
    $lines = file($error_log);
    $lines = array_slice($lines, -50);
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 600px; overflow-y: auto;'>";
    foreach ($lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<p>Error log file not found at configured location.</p>";
    echo "<p>Checking other possible locations:</p>";
    foreach ($possible_logs as $log_path) {
        if (file_exists($log_path)) {
            echo "<p><strong>Found:</strong> " . $log_path . "</p>";
            $lines = file($log_path);
            $lines = array_slice($lines, -30);
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 400px; overflow-y: auto;'>";
            foreach ($lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
            break;
        }
    }
}
?>
