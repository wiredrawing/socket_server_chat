<?php

ini_set("error_log", __DIR__ . "/error.log");

const READ_BUFFER_SIZE = 16;
// Specify the host/port to connect to
$port = 51000;
$ip_address = "127.0.0.1";
// Create a TCP Stream socket
$socket_name = sprintf("ssl://%s:%s", $ip_address, $port);
$ssl_context = [
    "ssl" => [
        "local_cert" => __DIR__ . "/server.crt",
        "local_pk" => __DIR__ . "/server.key",
        "verify_peer" => false,
        "verify_peer_name" => false,
        "allow_self_signed" => true,
        // "ssltransport" => "tlsv1.2",
    ],
];
// Bind the socket to an address/port
$socket = stream_socket_server($socket_name, $errno, $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create($ssl_context));

// encrypt the connected socket
// stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
var_dump($socket);
// encrypt the connected socket
// stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
// If the socket failed to bind, display an error message and exit.
if ($socket === false) {
    echo "$errstr ($errno)";
    exit;
}

$wrapper = [];
$client_name_list = [];
while (true) {
    // Accept a connection on a socket
    $stream = @stream_socket_accept($socket, 3);
    printf("[%s] タイムアウト指定時間:3秒が経過...%s", date("Y-m-d H:i:s"), PHP_EOL);
    if ($stream !== false) {
        $client_name = stream_socket_get_name($stream, true);
        printf("%s", $client_name);
        $wrapper[$client_name] = $stream;


        // クライアントに接続が正常に完了したことを通知
        $initiaize_message = sprintf("クライアント名[%s]として接続しました。%s", $client_name, PHP_EOL);
        fwrite($stream, $initiaize_message, strlen($initiaize_message));
        error_log(print_r($wrapper, true));
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
            $buffer = fread($readable_stream, READ_BUFFER_SIZE);
            var_dump($key, $buffer);
            $readable_messages[] = $buffer;
            if (strlen($buffer) < READ_BUFFER_SIZE) {
                printf("読み込み完了...%s", PHP_EOL);
                break;
            }
        }

        $message = implode("", $readable_messages);

        if (isset($client_name_list[$key]) !== true) {
            $client_name_list[$key] = $message;
            $message = sprintf("%sさんが入室しました。%s", $message, PHP_EOL);
        }
        error_log(print_r($client_name_list, true));
        error_log(sprintf("発言内容[%s] %s", $message, PHP_EOL));
        error_log(sprintf("発言者[%s]:メッセージ[%s] %s", $client_name_list[$key], $message, PHP_EOL));

        // 現在接続中の全クライアントへ上記の{$readable_messags}を送信
        foreach ($write as $client_name_key => $stream) {
            if ($client_name_key === $key) {
                continue;
            }
            // $streamが有効かどうかを検証
            if (is_resource($stream) === false) {
                printf("streamが無効です。%s", PHP_EOL);
                continue;
            }

            $fixed_message = sprintf("[%s]: %s %s", $client_name_list[$key], $message, PHP_EOL);
            var_dump($client_name_list[$key]);
            $bytes = fwrite($stream, $fixed_message, strlen($fixed_message));
            // 書き込みに失敗した場合,既に接続がきれているものとして扱う
            if ($bytes === false) {
                unset($wrapper[$client_name_key]);
            }
        }
    }
}
