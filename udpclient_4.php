
    private function processServerResponse($data)
    {
        if (strpos($data, 'FILE_DOWNLOAD:') === 0) {
            $this->handleFileDownload($data);
        } elseif (strpos($data, 'FILE_CONTENT:') === 0) {
            echo substr($data, 13) . "\n";
        } elseif (strpos($data, 'BROADCAST from') === 0) {
            echo "*** $data ***\n";
        } elseif ($data === "ADMIN_GRANTED") {
            $this->is_admin = true;
            echo "*** You now have ADMIN privileges ***\n";
        } else {
            echo "Server: $data\n";
        }
    }

    private function handleFileDownload($data)
    {
        $content = substr($data, 14);
        $file_data = base64_decode($content);

        if ($file_data === false) {
            echo "ERROR: Failed to decode file data\n";
            return;
        }

        $download_filename = 'downloaded_' . date('Y-m-d_H-i-s') . '.file';
        if (file_put_contents($download_filename, $file_data) !== false) {
            echo "SUCCESS: File downloaded as $download_filename\n";
        } else {
            echo "ERROR: Failed to save downloaded file\n";
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
    $client = new UDPClient('127.0.0.1', 12345);
    $client->run();
} else {
    echo "This script must be run from command line.\n";