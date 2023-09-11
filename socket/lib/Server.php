<?php


class Server
{

    /**
     * @var int
     */
    private $port = null;

    /**
     * @var string
     */
    private $ipAddress = null;

    /**
     * @var Socket
     */
    private $socket = null;


    /**
     * 接続中の全クライアントのソケットを格納する
     * @var array<string, Socket>
     */
    private $wrapper = [];

    /**
     * 現在,起動中サーバーに接続中のクライアントの端末名を格納する
     *
     * @var array
     */
    private $nameListOfConnectedClient = [];

    /**
     * @param string $ip_address
     * @param int $port
     */
    public function __construct(string $ip_address = "192.168.0.16", int $port = 51000)
    {
        $this->port = $port;
        $this->ipAddress = $ip_address;
    }

    /**
     * @param int $backlog
     * @return false|resource|Socket
     * @throws Exception
     */
    public function startServer(int $backlog = 256)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $is_bound = socket_bind($this->socket, $this->ipAddress, $this->port);
        if ($is_bound === false) {
            throw new Exception("socket_bind failed");
        }
        $is_listening = socket_listen($this->socket, $backlog);
        if ($is_listening === false) {
            throw new Exception("socket_listen failed");
        }
        return $this->socket;
    }

    /**
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function run(callable $callback)
    {
        $client_address = null;
        $client_port = null;
        while (true) {
            socket_set_nonblock($this->socket);
            $client = socket_accept($this->socket);
            if ($client !== false) {
                // Get the socket client name that connected to this server.
                $result = socket_getpeername($client, $client_address, $client_port);
                if ($result === false) {
                    throw new Exception("socket_getpeername failed");
                }
                // Create the client name.
                $client_name =sprintf("%s:%s", $client_address, $client_port);
                $this->wrapper[$client_name] = $client;
                if (isset($callback)) {
                    $callback($client_name);
                } else {
                    $initiaize_message = sprintf("クライアント名[%s]として接続しました。", $client_name);
                    socket_write($client, $initiaize_message, strlen($initiaize_message));
                }
            }

            // If there is no client connected to this server, continue to next loop.
            if (empty($this->wrapper)) {
                continue;
            }

            $read = $write = $error = $this->wrapper;
            $number = socket_select($read, $write, $error, 3);
            // If there is no change in the stream, continue to next loop.
            if ($number === false) {
                error_log(socket_last_error($this->socket));
            }
        }
    }

    /**
     * 現在接続中の全クライアントを返却する
     * @return Socket[]
     */
    public function connectedClientList ()
    {
        return $this->wrapper;
    }


    /**
     * 管理中のクライアントの疎通確認を行う
     * @return void
     */
    public function connectivityCheck () {
        foreach ($this->wrapper as $client_name => $client_socket) {
            $exploded_client_name = explode(":", $client_name);
            list($address, $port) = $exploded_client_name;
            // クライアントにnullバイトを送信する
            $connectivity_message = "\0";
            $connectivity_result = socket_sendto($client_socket,
                $connectivity_message,
                strlen($connectivity_message),
                0,
                $address,
                $port
            );
            if ($connectivity_result === false) {
                // クライアントとの疎通が確認できなかった場合は,クライアントを切断する
                socket_close($client_socket);
                unset($this->wrapper[$client_name]);
            }
        }
    }
}
