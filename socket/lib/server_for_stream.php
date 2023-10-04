<?php


interface Handler
{
    /**
     * @param string $data
     * @return string
     */
    public function handle(string $data): string;
}

class ServerForStream
{

    const BUFFER_SIZE = 16;
    /**
     * @var null | resource
     */
    private $server = null;

    /**
     * @var null | string
     */
    private $socketAddress = null;

    /**
     * @var array
     */
    private $acceptedSockets = [];

    /**
     * @var array
     */
    private $socketNameList = [];

    /**
     * @param string $address
     * @param int $port
     */
    public function __construct(string $address, int $port)
    {
        $this->socketAddress = sprintf("tcp://%s:%s", $address, $port);
    }

    /**
     * 1.create, 2.bind, 3.listenを同一のメソッドで一度に行う
     *
     * @return false|resource|null
     */
    public function startServer()
    {
        // condition when making socket server.
        $context = stream_context_create([
            "socket" => [
                "backlog" => 256,
            ],
        ]);
        // stream_socket_server()でソケットのbindとlistenが自動で行われる
        $this->server = stream_socket_server($this->socketAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($this->server === false) {
            return false;
        }
        return $this->server;
    }

    /**
     * 4.accept(ソケットの待受)を行う
     *
     * @param callable|null $f
     * @return mixed
     * @throws Exception
     */
    public function run(callable $f = null)
    {
        // If the member variable $server is null, throw an exception.
        if ($this->server === null) {
            throw new Exception("Server is not initialized.");
        }
        while (true) {
            $socket = @stream_socket_accept($this->server, 3);
            if ($socket !== false) {
                // 受付完了したソケットを配列にまとめる.
                // この処理は初回受付分のみ通過する
                $this->acceptedSockets[] = $socket;
            }

            if (empty($this->acceptedSockets)) {
                printf("[%s] 現在接続中のクライアントはいません。%s", date("Y-m-d H:i:s"), PHP_EOL);
                continue;
            }
            $timeout = 3;
            $read = $write = $except = $this->acceptedSockets;
            $number = stream_select($read, $write, $except, $timeout);

            if ($number === false) {
                printf("stream_select failed.%s", PHP_EOL);
                continue;
            }
            // 実際に読み取り完了したソケットを取得しループで一巡する
            foreach ($read as $read_key => $read_value) {

                // ------------------------------------------------------
                // 読み取り可能なソケットから読み取りを行う
                // そのため読み込み可能なソケットをnon-blockingとする
                // ------------------------------------------------------
                $data_from_socket = $this->read($read_value);
                if (isset($f)) {
                    $data_from_socket = $f($data_from_socket);
                }
                // Socketの名前を取得し,ユーザー名と紐付ける
                $socket_name = stream_socket_get_name($read_value, true);
                if (isset($this->socketNameList[$socket_name]) !== true) {
                    $this->socketNameList[$socket_name] = $data_from_socket;
                    $data_from_socket = sprintf("%sさんが入室しました。", $data_from_socket);
                }

                // $write変数には現時点で書き込み可能なソケットが格納されている
                foreach ($write as $write_key => $write_value) {
                    // 自身の投稿は読み取り可能なソケットに書き込まない
                    if ($write_key === $read_key) {
                        continue;
                    }
                    // 上記で取得された読み取りデータを書き込み可能な他全ソケットに書き込む
                    stream_socket_sendto($write_value, $data_from_socket);
                }
            }
        }
    }

    /**
     * @param $readable_socket
     * @return string
     */
    private function read($readable_socket): string
    {
        stream_set_blocking($readable_socket, false);
        $buffer = [];
        while (true) {
            /** @var string $temp */
            $temp = stream_socket_recvfrom($readable_socket, static::BUFFER_SIZE);
            // If any buffer data was read in socket, store it in $buffer.
            if (strlen($temp) > 0) {
                $buffer[] = $temp;
            } else {
                break;
            }
        }
        // The string data which was read in socket.
        return join("", $buffer);
    }
}

class MessageHandler implements Handler
{

    /**
     * @param string $data
     * @return string
     */
    public function handle(string $data): string
    {
        return sprintf("You said: %s", $data);
    }
}

try {
    $address = "192.168.0.16";
    $port = 51000;
    $server = new ServerForStream($address, $port);

    $server->startServer();
    // 読み取ったデータを加工するコールバック関数を指定する
    $server->run([
        new MessageHandler(),
        "handle",
    ]);

} catch (Exception $e) {
    printf("Error: %s", $e->getMessage());
}
