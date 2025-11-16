private function uploadFile($filename)
    {
        $file_content = file_get_contents($filename);
        if ($file_content === false) {
            echo "ERROR: Failed to read local file\n";
            return;
        }

        $upload_data = 'FILE_DATA:' . $filename . ':' . base64_encode($file_content);
        $this->sendToServer($upload_data);
        $upload_response = $this->waitForResponse();
        echo "Server: $upload_response\n";
    }

    private function sendToServer($data)
    {
        socket_sendto($this->socket, $data, strlen($data), 0, $this->server_host, $this->server_port);
    }

    private function waitForResponse($timeout = 5)
    {
        $read = [$this->socket];
        $write = null;
        $except = null;

        $result = socket_select($read, $write, $except, $timeout);

        if ($result > 0) {
            $data = '';
            $from = '';
            $port = 0;
            socket_recvfrom($this->socket, $data, 65536, 0, $from, $port);
            return $data;
        }

        return "No response from server";
    }

    private function adjustResponseTime()
    {
        $delay = $this->is_admin ? 50000 : 100000;
        usleep($delay);
    }

    private function receiveResponse()
    {
        $read = [$this->socket];
        $write = null;
        $except = null;

        $result = socket_select($read, $write, $except, 0, 100000);

        if ($result > 0) {
            $data = '';
            $from = '';
            $port = 0;
            socket_recvfrom($this->socket, $data, 65536, 0, $from, $port);
            $this->processServerResponse($data);
        }
    }