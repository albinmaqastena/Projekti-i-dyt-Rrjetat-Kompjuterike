<?php
class UDPServer
{
    private $socket;
    private $clients = [];
    private $stats;
    private $max_connections = 3;
    private $timeout = 100;
    private $admin_ip = '';
    private $message_log = 'server_messages.log';
    private $restricted_paths = [
        'server_messages.log',
        'server_stats.txt',
        'udp_client.php',
        'udp_server.php',
        'server_files'
    ];


    public function __construct($host = '0.0.0.0', $port = 12345)
    {
        $this->initializeStats();
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($this->socket === false) {
            die("Failed to create socket: " . socket_strerror(socket_last_error()));
        }

        if (!socket_bind($this->socket, $host, $port)) {
            die("Failed to bind socket: " . socket_strerror(socket_last_error()));
        }

        socket_set_nonblock($this->socket);

        echo "UDP Server started on $host:$port\n";
        echo "Maximum connections: {$this->max_connections}\n";
        echo "Client timeout: {$this->timeout} seconds\n\n";

        file_put_contents($this->message_log, "=== Server Message Log Started at " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND | LOCK_EX);
    }

    private function initializeStats()
    {
        $this->stats = [
            'total_connections' => 0,
            'active_connections' => 0,
            'total_messages' => 0,
            'total_bytes_sent' => 0,
            'total_bytes_received' => 0,
            'client_messages' => [],
            'client_traffic' => []
        ];
    }

    public function run()
    {
        while (true) {
            $this->checkTimeouts();
            $this->handleIncomingData();
            $this->checkStatsCommand();
            usleep(100000);
        }
    }

    private function handleIncomingData()
    {
        $from = '';
        $port = 0;
        $data = '';

        $bytes = @socket_recvfrom($this->socket, $data, 65536, 0, $from, $port);

        if ($bytes > 0) {
            $client_key = $from . ':' . $port;

            if ($this->updateClientActivity($client_key, $from, $port)) {
                $this->updateStatistics($bytes, $client_key);
                $this->processMessage($data, $client_key, $from, $port);
            }
        }
    }

    private function updateClientActivity($client_key, $ip, $port)
    {
        $current_time = time();

        if (!isset($this->clients[$client_key])) {
            if ($this->stats['active_connections'] >= $this->max_connections) {
                $this->sendToClient("ERROR: Server is at maximum capacity. Please try again later.", $ip, $port);
                return false;
            }

            $this->addNewClient($client_key, $ip, $port, $current_time);
        } else {
            $this->clients[$client_key]['last_activity'] = $current_time;
        }

        return true;
    }

    private function addNewClient($client_key, $ip, $port, $timestamp)
    {
        $is_admin = empty($this->admin_ip);

        $this->clients[$client_key] = [
            'ip' => $ip,
            'port' => $port,
            'last_activity' => $timestamp,
            'is_admin' => $is_admin
        ];

        if ($is_admin) {
            $this->admin_ip = $client_key;
            echo "Admin client set: $client_key\n";
            $this->sendToClient("ADMIN_GRANTED", $ip, $port, $client_key);
        }

        $this->stats['total_connections']++;
        $this->stats['active_connections']++;

        echo "New client connected: $client_key\n";
        echo "Active connections: {$this->stats['active_connections']}/{$this->max_connections}\n";
    }

    private function updateStatistics($bytes, $client_key)
    {
        $this->stats['total_messages']++;
        $this->stats['total_bytes_received'] += $bytes;
        $this->stats['client_messages'][$client_key] =
            ($this->stats['client_messages'][$client_key] ?? 0) + 1;
        $this->stats['client_traffic'][$client_key]['received'] =
            ($this->stats['client_traffic'][$client_key]['received'] ?? 0) + $bytes;
    }
