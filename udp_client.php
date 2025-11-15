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