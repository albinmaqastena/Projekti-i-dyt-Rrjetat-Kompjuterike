<?php
class UDPClient
{
    private $socket;
    private $server_host;
    private $server_port;
    private $is_admin = false;
    private $client_id;

    public function __construct($host = '127.0.0.1', $port = 12345)
    {
        $this->server_host = $host;
        $this->server_port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->client_id = uniqid('client_', true);

        if ($this->socket === false) {
            die("Failed to create socket: " . socket_strerror(socket_last_error()));
        }

        echo "UDP Client started (ID: {$this->client_id})\n";
        echo "Connecting to $host:$port\n\n";
    }

    public function run()
    {
        $this->showHelp();

        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, false);

        $currentInput = '';

        echo "Enter command or message (or 'quit' to exit): ";

        while (true) {
            $readSockets = [$this->socket];
            $write = null;
            $except = null;
            $numChanged = socket_select($readSockets, $write, $except, 0, 200000); // 200ms

            if ($numChanged > 0) {
                foreach ($readSockets as $sock) {
                    $data = '';
                    $from = '';
                    $port = 0;
                    @socket_recvfrom($sock, $data, 65536, 0, $from, $port);
                    if (!empty($data)) {
                        echo "\r" . str_repeat(' ', strlen("Enter command or message (or 'quit' to exit): ") + strlen($currentInput)) . "\r";
                        $this->processServerResponse($data);
                        echo "Enter command or message (or 'quit' to exit): $currentInput";
                    }
                }
            }

            $readStreams = [$stdin];
            $write = null;
            $except = null;
            $numChangedInput = stream_select($readStreams, $write, $except, 0, 200000);

            if ($numChangedInput > 0) {
                $currentInput = trim(fgets($stdin));
                if ($currentInput === 'quit') {
                    fclose($stdin);
                    echo "\nClient disconnected.\n";
                    break;
                }

                if (!empty($currentInput)) {
                    $this->receiveResponse();

                    $this->processCommand($currentInput);
                    $currentInput = '';
                }

                echo "Enter command or message (or 'quit' to exit): ";
            }

            $this->adjustResponseTime();
        }
    }
    private function showHelp()
    {
        echo "UDP File Server Client\n";
        echo "Available commands:\n";
        echo "/list [directory] - List files in directory\n";
        echo "/read <filename> - Read file content\n";
        echo "/upload <filename> - Upload file to server\n";
        echo "/download <filename> - Download file from server\n";
        echo "/delete <filename> - Delete file on server (admin only)\n";
        echo "/search <keyword> - Search for files\n";
        echo "/info <filename> - Get file information\n";
        echo "/messages - View message log (admin only)\n";
        echo "STATS - Show server statistics\n";
        echo "BROADCAST: <message> - Broadcast message to all clients (admin only)\n";
        echo "Any other text - Send as regular message to server\n";
        echo "quit - Exit client\n\n";
    }

    private function processCommand($command)
    {
        if (strpos($command, '/upload ') === 0) {
            $this->handleFileUpload($command);
        } else {
            $this->sendToServer($command);
        }
    }

    private function handleFileUpload($command)
    {
        $parts = explode(' ', $command, 2);
        if (count($parts) < 2) {
            echo "ERROR: Please specify filename for upload\n";
            return;
        }

        $filename = $parts[1];

        if (!file_exists($filename)) {
            echo "ERROR: Local file not found: $filename\n";
            return;
        }

        $this->sendToServer($command);
        $response = $this->waitForResponse();

        if (strpos($response, 'READY_FOR_UPLOAD:') === 0) {
            $this->uploadFile($filename);
        } else {
            echo "Server: $response\n";
        }
    }