<?php


$host = "tcp://localhost:50000";
$ssl_context = [
    "ssl" => [
        "cafile" => "C:/Users/a-sen/works/socket_server/server.crt",
        // "local_key" => "C:/Users/a-sen/works/socket_server/server.key",
    ],
];
$socket = stream_socket_client($host, $errno, $errstr, 30,
    STREAM_CLIENT_CONNECT, stream_context_create($ssl_context));

// encrypt the connected socket
stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
if ($socket === false) {
    echo "$errstr ($errno)";
    exit;
}
// // 接続済みソケットを暗号化する
// stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
printf("あなたの名前を入力してください:");
$your_name = readline("=> ");
$bytes = fwrite($socket, $your_name, strlen($your_name));
while (true) {
    $message = readline("=> ");

    if (strlen($message) > 0) {
        printf("%s: %s", $message, PHP_EOL);
    }

    // $message .= PHP_EOL;
    fwrite($socket, $message, strlen($message));


    stream_set_timeout($socket, 3,3000);
    $recieved_messages = [];
    // while (($buffer = fgets($socket, 64)) !== false) {
    //     $recieved_messages[] = $buffer;
    // }


    // doing non-blocking
    stream_set_blocking($socket, false);
    while(true) {
        $read = fread($socket, 1024);
        var_dump($read);
        $recieved_messages[] = $read;
        if (strlen($read) === 0) {
            break;
        }
    }
    printf("from server:%s %s", implode("", $recieved_messages), PHP_EOL);
}
