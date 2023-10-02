<?php


/**
 * このServerクラスはStreamではなく
 * 拡張もモジュールのSocketを使用している
 */
class Server
{

    const MAX_BUFFER_SIZE = 16;
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
     *
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
     * @param callable $customieze
     * @param callable|null $handler
     * @return mixed
     * @throws Exception
     */
    public function run(callable $customieze, callable $handler = null)
    {
        while (true) {
            // 定期的に疎通確認を行い,切断されたクライアントを除外する
            $this->connectivityCheck();
            // 100秒後に接続中のクライアントを表示する
            if (time() % 100 === 0) {
                print_r($this->wrapper);
            }
            socket_set_nonblock($this->socket);
            $client = socket_accept($this->socket);
            if ($client !== false) {
                $client_name = $this->formatClientName($client);
                $this->wrapper[$client_name] = $client;
                if (isset($customieze)) {
                    $customieze($client_name);
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
            foreach ($read as $read_key => $read_value) {

                socket_set_nonblock($read_value);
                $message = $this->read($read_value, static::MAX_BUFFER_SIZE);
                if ($message === false) {
                    // クライアントが切断した場合は,クライアントを切断する
                    socket_close($read_value);
                    unset($this->wrapper[$read_key]);
                    continue;
                }
                $client_name = $this->formatClientName($read_value);
                // クライアントが初回接続した場合は,クライアント名を登録する
                if (isset($this->nameListOfConnectedClient[$client_name]) !== true) {
                    $this->nameListOfConnectedClient[$client_name] = $message;
                    $message = sprintf("[%s]<%s>さんが入室しました。\n", $this->nameListOfConnectedClient[$client_name], $message);
                    printf("%s", $message);
                } else {
                    // クライアントが既に入室済みの場合
                    $message = sprintf("[%s]:%s\n", $this->nameListOfConnectedClient[$client_name], $message);
                    printf("%s", $message);
                }

                foreach ($write as $write_key => $write_value) {
                    if ($write_key === $read_key) {
                        // 同一クライアントへは返信しない
                        continue;
                    }
                    if (isset($handler)) {
                        // メッセージ結果を返却するようにコールバックに指示する
                        $message = $handler($message);
                    }
                    // コールバックが指定されない場合はそのまま返却
                    // socket_writeのWarningを抑制するために@を付与
                    $is_success = @socket_write($write_value, $message, strlen($message));
                    // ----------------------------------------------------------------------
                    // クライアントへの書き込みに失敗した場合は,クライアントを切断する
                    // ----------------------------------------------------------------------
                    if ($is_success === false) {
                        socket_close($write_value);
                        unset($this->wrapper[$write_key]);
                    }
                }
            }
        }
    }

    /**
     * 管理中のクライアントの疎通確認を行う
     *
     * @return Socket[]
     */
    public function connectivityCheck(): array
    {
        foreach ($this->wrapper as $client_name => $client_socket) {
            $exploded_client_name = explode(":", $client_name);
            list($address, $port) = $exploded_client_name;
            // クライアントにnullバイトを送信する
            $connectivity_message = "\0";
            $connectivity_result = @socket_sendto($client_socket, $connectivity_message, strlen($connectivity_message), 0, $address, $port);
            if ($connectivity_result === false) {
                // クライアントとの疎通が確認できなかった場合は,クライアントを切断する
                socket_close($client_socket);
                unset($this->wrapper[$client_name]);
            }
        }
        return $this->wrapper;
    }

    /**
     * ソケットクライアントから接続元のIPおよびポートからクライアント名を作成する
     *
     * @param $client
     * @return string
     * @throws Exception
     */
    public function formatClientName($client): string
    {
        // Get the socket client name that connected to this server.
        $result = socket_getpeername($client, $client_address, $client_port);
        if ($result === false) {
            return (string)null;
        }
        // Create the client name.
        return sprintf("%s:%s", $client_address, $client_port);
    }

    /**
     * ソケットから読み込みを行うが,読み込んだバイト数が指定したバッファ未満になったら
     * 読み込みを終了する
     *
     * @param $socket
     * @param int $size
     * @return false|string
     */
    public function read($socket, int $size = self::MAX_BUFFER_SIZE)
    {
        $messages = [];
        while (true) {
            $buffer = @socket_read($socket, $size);
            if ($buffer === false) {
                // falseが帰ってきた場合は,呼び出し側で接続済みソケットリストから除外すること
                return false;
            }
            $messages[] = $buffer;
            if (strlen($buffer) < $size) {
                break;
            }
        }
        return implode("", $messages);
    }

    /**
     * 現在接続中の全クライアントを返却する
     *
     * @return Socket[]
     */
    public function connectedClientList()
    {
        return $this->wrapper;
    }
}


try {
    $server = new Server();
    $server->startServer();
    $server->run(function ($client_name) {
        error_log(sprintf("クライアント名[%s]として接続しました。", $client_name));
    });
} catch (Exception $e) {
    printf("%s", $e->getMessage());
    exit();
}
