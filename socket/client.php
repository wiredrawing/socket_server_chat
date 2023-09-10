<?php

// socketモジュールがインストールされているかどうかを確認
if (function_exists("socket_create") !== true) {
    echo "socket_create関数が見つかりません。";
    exit;
}

const READ_BUFFER_SIZE = 16;

$port = 51000;
$ip_address = "192.168.0.16";
$client_socket = null;
$is_connected = false;
printf("あなたの名前を入力してください:");
while (true) {
    $your_name = readline("=> ");
    if (strlen($your_name) > 0) {

        // 入力した文字列が正しく入力された場合のみソケットを作成する
        $client_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $is_connected = socket_connect($client_socket, $ip_address, $port);

        if ($is_connected === false) {
            echo "接続に失敗しました。";
            exit;
        }
        $bytes = socket_write($client_socket, $your_name, strlen($your_name));
        if ($bytes === false) {
            printf("メッセージの送信に失敗しました。%s", PHP_EOL);
            continue;
        }
        break;
    }
    printf("名前が入力されていません。%s", PHP_EOL);
}


while (true) {

    $message = readline("=>");

    $is_sent = socket_write($client_socket, $message, strlen($message));
    if ($is_sent === false) {
        printf("メッセージの送信に失敗しました。%s", PHP_EOL);
        continue;
    }

    $recieved_messages = [];
    socket_set_nonblock($client_socket);
    while (true) {
        $buffer = socket_read($client_socket, READ_BUFFER_SIZE);
        $recieved_messages[] = $buffer;
        if (strlen($buffer) < READ_BUFFER_SIZE) {
            printf("読み込み完了...%s", PHP_EOL);
            break;
        }
    }
    $message = implode("", $recieved_messages);
    printf("受信したメッセージ[%s] %s", $message, PHP_EOL);
}
