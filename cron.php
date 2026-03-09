<?php
/**
 * Cron job script to monitor and start the Python checkers game server.
 * 
 * Usage:
 * - Windows: Add to Task Scheduler to run `php cron.php` every N minutes.
 * - Linux/Unix: Add to crontab: `* * * * * php /path/to/cron.php`
 */

$host = '127.0.0.1';
$port = 8000;
$server_script = __DIR__ . DIRECTORY_SEPARATOR . 'server.py';
$log_file = __DIR__ . DIRECTORY_SEPARATOR . 'server.log';

// Check if the game server is already responding on the required port
$connection = @fsockopen($host, $port, $errno, $errstr, 2);

if (is_resource($connection)) {
    echo "[" . date('Y-m-d H:i:s') . "] Server is already running on port $port.\n";
    fclose($connection);
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Server is not running. Starting it now...\n";
    
    // Change working directory to where the script is, to avoid path issues
    chdir(__DIR__);
    
    // Check operating system to use the correct background command
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    if ($is_windows) {
        // Windows: use 'start /B' to run in background without opening a new console window
        $command = "start /B python \"$server_script\" >> \"$log_file\" 2>&1";
        pclose(popen($command, "r"));
    } else {
        // Linux/Mac: use 'nohup' and '&' to run in background
        $command = "nohup python3 \"$server_script\" >> \"$log_file\" 2>&1 &";
        exec($command);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Command executed: $command\n";
}
?>
