<?php
/**
 * Cron job script to monitor and start the Python checkers game server.
 * 
 * Usage:
 * - Windows: Add to Task Scheduler to run `php cron.php` every N minutes.
 * - Linux/Unix: Add to crontab: `* * * * * php /path/to/cron.php`
 */

$host = '127.0.0.1';
$backend_port = (int)(getenv('CHECKERS_PORT') ?: 80);
$server_script = __DIR__ . DIRECTORY_SEPARATOR . 'server.py';
$log_file = __DIR__ . DIRECTORY_SEPARATOR . 'server.log';
$mtime_file = __DIR__ . DIRECTORY_SEPARATOR . '.server_mtime';
$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

// Verify server script exists
if (!file_exists($server_script)) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Server script not found: $server_script\n";
    exit(1);
}

// Check if server.py has been modified since last run
$current_mtime = filemtime($server_script);
$last_mtime = file_exists($mtime_file) ? (int)file_get_contents($mtime_file) : 0;
$server_updated = ($current_mtime > $last_mtime);

// Find running Python process(es) for this server script
$running_pids = [];
if ($is_windows) {
    $find_cmd = "wmic process where \"commandline like '%server.py%' and (name='python.exe' or name='python3.exe')\" get processid 2>nul";
    $pids = shell_exec($find_cmd);
    if ($pids) {
        preg_match_all('/(\d+)/', $pids, $matches);
        foreach ($matches[1] as $pid) {
            if ((int)$pid > 0) {
                $running_pids[] = (int)$pid;
            }
        }
    }
} else {
    $pids = shell_exec("pgrep -f 'python.*server.py'");
    if ($pids) {
        $pid_array = array_filter(array_map('trim', explode("\n", $pids)));
        foreach ($pid_array as $pid) {
            if (is_numeric($pid) && (int)$pid > 0) {
                $running_pids[] = (int)$pid;
            }
        }
    }
}

$server_running = count($running_pids) > 0;

if ($server_running && $server_updated) {
    echo "[" . date('Y-m-d H:i:s') . "] Server file updated. Restarting Python backend...\n";

    if ($is_windows) {
        foreach ($running_pids as $pid) {
            exec("taskkill /F /PID $pid 2>nul");
            echo "[" . date('Y-m-d H:i:s') . "] Killed process ID: $pid\n";
        }
    } else {
        foreach ($running_pids as $pid) {
            exec("kill -TERM $pid 2>/dev/null");
        }
        sleep(1);
        foreach ($running_pids as $pid) {
            exec("kill -9 $pid 2>/dev/null");
            echo "[" . date('Y-m-d H:i:s') . "] Killed process ID: $pid\n";
        }
    }

    sleep($is_windows ? 2 : 3);
    $start_server = true;
} elseif ($server_running) {
    echo "[" . date('Y-m-d H:i:s') . "] Python backend is already running.\n";
    $start_server = false;
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Python backend is not running. Starting it now...\n";
    $start_server = true;
}
if ($start_server) {
    // Change working directory to where the script is, to avoid path issues
    chdir(__DIR__);

    if ($is_windows) {
        // Windows: use 'start /B' to run in background without opening a new console window
        $command = "start /B set CHECKERS_HOST=$host && set CHECKERS_PORT=$backend_port && python \"$server_script\" >> \"$log_file\" 2>&1";
        pclose(popen($command, "r"));
    } else {
        // Linux/Mac: use 'nohup' and '&' to run in background
        $python_cmd = trim(shell_exec("which python3 2>/dev/null")) ?: 'python3';
        $command = "nohup env CHECKERS_HOST=$host CHECKERS_PORT=$backend_port $python_cmd \"$server_script\" >> \"$log_file\" 2>&1 &";
        shell_exec($command);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Command executed: $command\n";
    
    // Update the last modification time
    file_put_contents($mtime_file, $current_mtime);
    echo "[" . date('Y-m-d H:i:s') . "] Server modification time updated.\n";
}
?>
