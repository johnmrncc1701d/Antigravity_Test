<?php
/**
 * Cron job script to monitor and start the Python checkers game server.
 * 
 * Usage:
 * - Windows: Add to Task Scheduler to run `php cron.php` every N minutes.
 * - Linux/Unix: Add to crontab: `* * * * * php /path/to/cron.php`
 */

$host = '127.0.0.1';
$port = 80;
$server_script = __DIR__ . DIRECTORY_SEPARATOR . 'server.py';
$log_file = __DIR__ . DIRECTORY_SEPARATOR . 'server.log';
$mtime_file = __DIR__ . DIRECTORY_SEPARATOR . '.server_mtime';

// Verify server script exists
if (!file_exists($server_script)) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Server script not found: $server_script\n";
    exit(1);
}

// Check if server.py has been modified since last run
$current_mtime = filemtime($server_script);
$last_mtime = file_exists($mtime_file) ? (int)file_get_contents($mtime_file) : 0;
$server_updated = ($current_mtime > $last_mtime);

// Check if the game server is already responding on the required port
$connection = @fsockopen($host, $port, $errno, $errstr, 2);

if (is_resource($connection)) {
    fclose($connection);
    
    if ($server_updated) {
        echo "[" . date('Y-m-d H:i:s') . "] Server file updated. Stopping current server and restarting...\n";
        
        // Stop the currently running server
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($is_windows) {
            // Windows: Find and kill the Python process running server.py
            $find_cmd = "wmic process where \"commandline like '%server.py%' and name='python.exe'\" get processid 2>nul";
            $pids = shell_exec($find_cmd);
            
            if ($pids) {
                preg_match_all('/(\d+)/', $pids, $matches);
                foreach ($matches[1] as $pid) {
                    if ($pid > 0) {
                        exec("taskkill /F /PID $pid 2>nul");
                        echo "[" . date('Y-m-d H:i:s') . "] Killed process ID: $pid\n";
                    }
                }
            }
        } else {
            // Linux/Mac: Find and kill all Python processes running server.py
            $pids = shell_exec("pgrep -f 'python.*server.py'");
            if ($pids) {
                $pid_array = array_filter(array_map('trim', explode("\n", $pids)));
                foreach ($pid_array as $pid) {
                    if (is_numeric($pid) && $pid > 0) {
                        // Try graceful shutdown first (SIGTERM)
                        exec("kill -TERM $pid 2>/dev/null");
                        echo "[" . date('Y-m-d H:i:s') . "] Sent SIGTERM to process ID: $pid\n";
                    }
                }
                // Give processes a moment to shut down gracefully
                sleep(1);
                // Force kill any remaining processes
                foreach ($pid_array as $pid) {
                    if (is_numeric($pid) && $pid > 0) {
                        exec("kill -9 $pid 2>/dev/null");
                    }
                }
            }
        }
        
        // Wait for the port to be released (longer on Linux for graceful shutdown)
        sleep($is_windows ? 2 : 3);
        
        // Now start the new server (fall through to start logic below)
        $start_server = true;
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Server is already running on port $port.\n";
        $start_server = false;
    }
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Server is not running. Starting it now...\n";
    $start_server = true;
}
if ($start_server) {
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
        // Use absolute paths and ensure proper backgrounding
        $python_cmd = trim(shell_exec("which python3 2>/dev/null")) ?: 'python3';
        $command = "nohup $python_cmd \"$server_script\" >> \"$log_file\" 2>&1 &";
        shell_exec($command);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Command executed: $command\n";
    
    // Update the last modification time
    file_put_contents($mtime_file, $current_mtime);
    echo "[" . date('Y-m-d H:i:s') . "] Server modification time updated.\n";
}
?>
