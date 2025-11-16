private function getMessageLog($is_admin)
    {
        if (!$is_admin) {
            return "ERROR: Admin privileges required to view message log";
        }

        if (!file_exists($this->message_log)) {
            return "No messages logged yet";
        }

        $log_content = file_get_contents($this->message_log);
        return "MESSAGE LOG:\n" . $log_content;
    }

    private function listFiles($directory, $client_key)
    {
        if (!is_dir($directory)) return "ERROR: Directory not found";

        $files = scandir($directory);
        if ($files === false) return "ERROR: Cannot read directory";

        $result = "Files in $directory:\n";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $fullpath = $directory . '/' . $file;
                $basename = basename($file);

                if (!($this->clients[$client_key]['is_admin'] ?? false)) {
                    if (in_array($basename, $this->restricted_paths)) {
                        continue;
                    }
                }

                $type = is_dir($fullpath) ? '[DIR]' : '[FILE]';
                $result .= "$type $file\n";
            }
        }
        return $result;
    }


    private function readFile($filename, $client_key)
    {
        $basename = basename($filename);
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin && in_array($basename, $this->restricted_paths)) {
            return "ERROR: Access denied";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_readable($filename)) return "ERROR: Cannot read file";

        $content = file_get_contents($filename);
        return $content !== false ? "FILE_CONTENT:\n" . $content : "ERROR: Failed to read file";
    }

    private function downloadFile($filename, $client_key)
    {
        $basename = basename($filename);
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin && in_array($basename, $this->restricted_paths)) {
            return "ERROR: Access denied";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_readable($filename)) return "ERROR: Cannot read file";

        $content = file_get_contents($filename);
        return $content !== false ? "FILE_DOWNLOAD:" . base64_encode($content) : "ERROR: Failed to read file";
    }


    private function deleteFile($filename, $client_key)
    {
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin) {
            return "ERROR: Admin privileges required";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_writable($filename)) return "ERROR: Cannot delete file";

        return unlink($filename) ? "SUCCESS: File deleted" : "ERROR: Failed to delete file";
    }


    private function searchFiles($keyword)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $results = [];
        foreach ($iterator as $file) {
            if (strpos($file->getFilename(), $keyword) !== false) {
                $type = $file->isDir() ? '[DIR]' : '[FILE]';
                $results[] = $type . ' ' . $file->getPathname();
            }
        }

        return empty($results) ? "No files found containing '$keyword'" :
            "Search results for '$keyword':\n" . implode("\n", $results);
    }

    private function fileInfo($filename)
    {
        if (!file_exists($filename)) return "ERROR: File not found";

        $stat = stat($filename);
        return "File: $filename\nSize: {$stat['size']} bytes\nCreated: " .
            date('Y-m-d H:i:s', $stat['ctime']) . "\nModified: " .
            date('Y-m-d H:i:s', $stat['mtime']);
    }
    private function processMessage($data, $client_key, $ip, $port)
    {
        $data = trim($data);
        echo "Received from $client_key: $data\n";

        $this->logMessage($client_key, $data);

        if ($data === 'STATS') {
            $response = $this->getStats();
        } else {
            $response = $this->handleClientCommand($data, $client_key);
        }

        if (!empty($response)) {
            $this->sendToClient($response, $ip, $port, $client_key);
        }
    }

    private function logMessage($client_key, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $client_key: $message\n";
        file_put_contents($this->message_log, $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function handleClientCommand($data, $client_key)
    {
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (strpos($data, '/') !== 0) {
            return $this->handleTextMessage($data, $client_key);
        }

        $parts = explode(' ', $data, 2);
        $command = $parts[0];
        $parameter = $parts[1] ?? '';

        switch ($command) {
            case '/delete':
                if (!$is_admin) return "ERROR: Admin privileges required";
                return $this->deleteFile($parameter ?: '', $client_key);

            case '/upload':
                if (!$is_admin) return "ERROR: Admin privileges required";
                return "Use /upload <filename> or send FILE_DATA to upload";

            case '/messages':
                if (!$is_admin) return "ERROR: Admin privileges required";
                return $this->getMessageLog($is_admin);

            case '/info':
                if (!$is_admin) return "ERROR: Admin privileges required";
                return $parameter ? $this->fileInfo($parameter) : "ERROR: Please specify filename";

            case '/search':
                if (!$is_admin) return "ERROR: Admin privileges required";
                return $parameter ? $this->searchFiles($parameter) : "ERROR: Please specify search keyword";

            case '/list':
                return $this->listFiles($parameter ?: '.', $client_key);

            case '/read':
                return $parameter ? $this->readFile($parameter, $client_key) : "ERROR: Please specify filename";

            case '/download':
                return $parameter ? $this->downloadFile($parameter, $client_key) : "ERROR: Please specify filename";

            default:
                if (strpos($data, 'FILE_DATA:') === 0) {
                    if (!$is_admin) return "ERROR: Admin privileges required for upload";
                    return $this->handleFileUpload(substr($data, 10));
                }
                return "Unknown command. Available: /list, /read, /upload, /download, /delete, /search, /info, /messages";
        }
    }

    private function handleTextMessage($message, $client_key)
    {
        $response = "Message received: \"$message\"";

        if ($this->clients[$client_key]['is_admin'] ?? false) {
            $response .= " (Admin priority processing)";

            if (strpos($message, 'BROADCAST:') === 0) {
                $this->broadcastMessage($client_key, substr($message, 10));
                $response = "Broadcast message sent to all clients";
            }
        }

        return $response;
    }

    private function broadcastMessage($from_client, $message)
    {
        $broadcast_count = 0;
        foreach ($this->clients as $client_key => $client) {
            if ($client_key !== $from_client) {
                $broadcast_msg = "BROADCAST from $from_client: $message";
                $this->sendToClient($broadcast_msg, $client['ip'], $client['port'], $client_key);
                $broadcast_count++;
            }
        }
        echo "Broadcast sent to $broadcast_count clients\n";
    }
    private function getMessageLog($is_admin)
    {
        if (!$is_admin) {
            return "ERROR: Admin privileges required to view message log";
        }

        if (!file_exists($this->message_log)) {
            return "No messages logged yet";
        }

        $log_content = file_get_contents($this->message_log);
        return "MESSAGE LOG:\n" . $log_content;
    }

    private function listFiles($directory, $client_key)
    {
        if (!is_dir($directory)) return "ERROR: Directory not found";

        $files = scandir($directory);
        if ($files === false) return "ERROR: Cannot read directory";

        $result = "Files in $directory:\n";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $fullpath = $directory . '/' . $file;
                $basename = basename($file);

                if (!($this->clients[$client_key]['is_admin'] ?? false)) {
                    if (in_array($basename, $this->restricted_paths)) {
                        continue;
                    }
                }

                $type = is_dir($fullpath) ? '[DIR]' : '[FILE]';
                $result .= "$type $file\n";
            }
        }
        return $result;
    }


    private function readFile($filename, $client_key)
    {
        $basename = basename($filename);
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin && in_array($basename, $this->restricted_paths)) {
            return "ERROR: Access denied";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_readable($filename)) return "ERROR: Cannot read file";

        $content = file_get_contents($filename);
        return $content !== false ? "FILE_CONTENT:\n" . $content : "ERROR: Failed to read file";
    }

    private function downloadFile($filename, $client_key)
    {
        $basename = basename($filename);
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin && in_array($basename, $this->restricted_paths)) {
            return "ERROR: Access denied";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_readable($filename)) return "ERROR: Cannot read file";

        $content = file_get_contents($filename);
        return $content !== false ? "FILE_DOWNLOAD:" . base64_encode($content) : "ERROR: Failed to read file";
    }


    private function deleteFile($filename, $client_key)
    {
        $is_admin = $this->clients[$client_key]['is_admin'] ?? false;

        if (!$is_admin) {
            return "ERROR: Admin privileges required";
        }

        if (!file_exists($filename)) return "ERROR: File not found";
        if (!is_writable($filename)) return "ERROR: Cannot delete file";

        return unlink($filename) ? "SUCCESS: File deleted" : "ERROR: Failed to delete file";
    }


    private function searchFiles($keyword)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $results = [];
        foreach ($iterator as $file) {
            if (strpos($file->getFilename(), $keyword) !== false) {
                $type = $file->isDir() ? '[DIR]' : '[FILE]';
                $results[] = $type . ' ' . $file->getPathname();
            }
        }

        return empty($results) ? "No files found containing '$keyword'" :
            "Search results for '$keyword':\n" . implode("\n", $results);
    }

    private function fileInfo($filename)
    {
        if (!file_exists($filename)) return "ERROR: File not found";

        $stat = stat($filename);
        return "File: $filename\nSize: {$stat['size']} bytes\nCreated: " .
            date('Y-m-d H:i:s', $stat['ctime']) . "\nModified: " .
            date('Y-m-d H:i:s', $stat['mtime']);
    }
    
    private function handleFileUpload($data)
    {
        $parts = explode(':', $data, 2);
        if (count($parts) !== 2) return "ERROR: Invalid upload data format";

        $filename = $parts[0];
        $file_data = base64_decode($parts[1]);

        return file_put_contents($filename, $file_data) !== false ?
            "SUCCESS: File uploaded successfully" : "ERROR: Failed to upload file";
    }

    private function sendToClient($response, $ip, $port, $client_key = null)
    {
        $bytes_sent = socket_sendto($this->socket, $response, strlen($response), 0, $ip, $port);

        if ($bytes_sent > 0 && $client_key) {
            $this->stats['total_bytes_sent'] += $bytes_sent;
            $this->stats['client_traffic'][$client_key]['sent'] =
                ($this->stats['client_traffic'][$client_key]['sent'] ?? 0) + $bytes_sent;
        }
    }

    private function checkTimeouts()
    {
        $current_time = time();
        foreach ($this->clients as $client_key => $client) {
            if ($current_time - $client['last_activity'] > $this->timeout) {
                $this->removeClient($client_key);
            }
        }
    }

    private function removeClient($client_key)
    {
        echo "Client timeout: $client_key\n";
        $was_admin = $this->clients[$client_key]['is_admin'] ?? false;
        unset($this->clients[$client_key]);
        $this->stats['active_connections']--;

        if ($was_admin) {
            $this->assignNewAdmin();
        }
    }

    private function assignNewAdmin()
    {
        $this->admin_ip = '';
        foreach ($this->clients as $client_key => $client) {
            $this->clients[$client_key]['is_admin'] = true;
            $this->admin_ip = $client_key;
            echo "New admin assigned: $client_key\n";
            $this->sendToClient("ADMIN_GRANTED", $client['ip'], $client['port'], $client_key);
            break;
        }
    }

    private function getStats()
    {
        $stats = "=== SERVER STATISTICS ===\n";
        $stats .= "Total connections: {$this->stats['total_connections']}\n";
        $stats .= "Active connections: {$this->stats['active_connections']}\n";
        $stats .= "Total messages: {$this->stats['total_messages']}\n";
        $stats .= "Total bytes sent: {$this->stats['total_bytes_sent']}\n";
        $stats .= "Total bytes received: {$this->stats['total_bytes_received']}\n";
        $stats .= "Active clients:\n";

        foreach ($this->clients as $client_key => $client) {
            $role = $client['is_admin'] ? 'ADMIN' : 'USER';
            $messages = $this->stats['client_messages'][$client_key] ?? 0;
            $sent = $this->stats['client_traffic'][$client_key]['sent'] ?? 0;
            $received = $this->stats['client_traffic'][$client_key]['received'] ?? 0;
            $idle_time = time() - $client['last_activity'];

            $stats .= "  $client_key [$role] - Msgs: $messages, Traffic: {$sent}↑/{$received}↓ bytes, Idle: {$idle_time}s\n";
        }

        file_put_contents('server_stats.txt', $stats . "\n", FILE_APPEND | LOCK_EX);
        return $stats;
    }

    private function checkStatsCommand()
    {
        if (file_exists('show_stats.flag')) {
            echo $this->getStats();
            unlink('show_stats.flag');
        }
    }

    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
}

if (php_sapi_name() === 'cli') {
    $server = new UDPServer('0.0.0.0', 12345);
    $server->run();
} else {
    echo "This script must be run from command line.\n";
}