<?php

const READ_BUFFER_SIZE = 16;
$port = 51000;
$ip_address = "192.168.0.16";
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_bind($socket, $ip_address, $port);

socket_listen($socket, 256);

$wrapper = [];
$client_name_list = [];
while (true) {
    $client = null;
    socket_set_nonblock($socket);
    $client = socket_accept($socket);
    // 新規クライアントが接続してきた場合
    if ($client !== false) {
        // get the socket name
        $client_name = socket_getpeername($client, $address, $port);
        // アクセスしてきたclientの名前を作成
        $client_name = sprintf("%s:%s", $address, $port);
        $wrapper[$client_name] = $client;
        $initiaize_message = sprintf("クライアント名[%s]として接続しました。", $client_name);
        socket_write($client, $initiaize_message, strlen($initiaize_message));
    }


    if (empty($wrapper)) {
        // error_log(sprintf("[%s] %s", date("Y-m-d H:i:s"), "現在接続中のクライアントはいません。" . PHP_EOL)) ;
        continue;
    }
    $read = $write = $error = $wrapper;
    $number = socket_select($read, $write, $error, 3);

    if ($number === 0) {
        printf("管理streamに変化無し...%s", PHP_EOL);
        continue;
    }

    foreach ($read as $read_key => $read_value) {
        // var_dump($read_key);
        // print_r($read_value);
        socket_set_nonblock($read_value);
        $recieved_messages = [];
        while (true) {
            $buffer = socket_read($read_value, READ_BUFFER_SIZE);
            $recieved_messages[] = $buffer;
            if (strlen($buffer) < READ_BUFFER_SIZE) {
                printf("読み込み完了...%s", PHP_EOL);
                break;
            }
        }
        $message = implode("", $recieved_messages);
        if (isset($client_name_list[$read_key]) !== true) {
            $client_name_list[$read_key] = $message;
            $message = sprintf("%sさんが入室しました。%s", $message, PHP_EOL);
        }
        printf("受信したメッセージ[%s] %s", $message, PHP_EOL);

        foreach ($write as $write_key => $write_value) {
            if ($write_key === $read_key) {
                $owner_message = sprintf("あなたが送信したメッセージ[%s] %s", $message, PHP_EOL);
                $is_bytes = socket_write($write_value, $owner_message, strlen($owner_message));
                if ($is_bytes === false) {
                    printf("メッセージの送信に失敗しました。%s", PHP_EOL);
                    unset($wrapper[$write_key]);
                    continue;
                }
                continue;
            }
            socket_write($write_value, $message, strlen($message));
            printf("書き込み可能なソケットに書き込み");
        }
    }
}
