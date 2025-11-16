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