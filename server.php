<?php


// Create a TCP Stream socket
$socket_name = "tcp://localhost:50000";
// Bind the socket to an address/port
$socket = stream_socket_server($socket_name, $errno, $errstr);

// If the socket failed to bind, display an error message and exit.
if ($socket === false) {
    echo "$errstr ($errno)";
    exit;
}

$wrapper = [];
while (true) {
    // Accept a connection on a socket
    $stream = @stream_socket_accept($socket, 3);
    printf("[%s] タイムアウト指定時間:3秒が経過...%s", date("Y-m-d H:i:s"), PHP_EOL);
    if ($stream !== false) {
        $client_name = stream_socket_get_name($stream, true);
        printf("%s", $client_name);
        $wrapper[$client_name] = $stream;
        print_r($wrapper);
    }

    if (empty($wrapper)) {
        printf("%s", "現在接続中のクライアントはいません。" . PHP_EOL);
        continue;
    }

    // streamの管理
    $read = $write = $error = $wrapper;
    $number = stream_select($read, $write, $error, 1);
    if ($number === 0) {
        printf("管理streamに変化無し...%s", PHP_EOL);
        continue;
    }
    foreach ($read as $key => $readable_stream) {
        // 変更があったストリームから読み込み可能なバッファを取得
        stream_set_blocking($readable_stream, false);
        $readable_messages = [];
        while (true) {
            $buffer = fread($readable_stream, 16);
            var_dump($buffer);
            $readable_messages[] = $buffer;
            if (strlen($buffer) < 16) {
                break;
            }
        }
        $message = implode("", $readable_messages);


        printf("クライアントから受信したペイロード: %s %s", $message, PHP_EOL);

        // 現在接続中の全クライアントへ上記の{$readable_messags}を送信
        foreach ($write as $client_name => $stream) {
            if ($client_name === $key) {
                continue;
            }
            // $streamが有効かどうかを検証
            if (is_resource($stream) === false) {
                printf("streamが無効です。%s", PHP_EOL);
                continue;
            }
            $bytes = fwrite($stream, $message, strlen($message));
            // 書き込みに失敗した場合,既に接続がきれているものとして扱う
            if ($bytes === false) {
                unset($wrapper[$client_name]);
            }
        }
    }
}
