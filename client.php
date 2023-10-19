<?php


$host = "tcp://192.168.0.16:11180";
$ssl_context = stream_context_create([
    // 'ssl' => [
    //     "local_cert" => __DIR__ . "/practice/server_certificate.pem",
    //     "local_pk" => __DIR__ . "/practice/server_private.key",
    //     'verify_peer' => false,
    //     'verify_peer_name' => false,
    //     'allow_self_signed' => true,
    //     "passphrase" => "AAAaaa123",
    //     'verify_depth' => 0,
    // ],
]);
$socket = stream_socket_client($host, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ssl_context);

// encrypt the connected socket
// stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
if ($socket === false) {
    echo "$errstr ($errno)";
    exit;
}
// // 接続済みソケットを暗号化する
// stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
// printf("あなたの名前を入力してください:");
// while(true) {
//     $your_name = readline("=> ");
//     if (strlen($your_name) > 0 ) {
//         $bytes = fwrite($socket, $your_name, strlen($your_name));
//         break;
//     }
//     printf("名前が入力されていません。%s", PHP_EOL);
// }

while (true) {
    // printf("[%s] %s", $your_name, PHP_EOL);
    $message = readline("=> ");

    if (strlen($message) > 0) {
        printf("%s: %s", $message, PHP_EOL);
    }

    // $message .= PHP_EOL;
    fwrite($socket, $message, strlen($message));


    stream_set_timeout($socket, 3, 3000);
    $recieved_messages = [];

    // doing non-blocking
    stream_set_blocking($socket, false);
    while (true) {
        $read = fread($socket, 1024);
        $recieved_messages[] = $read;
        if (strlen($read) === 0) {
            break;
        }
    }
    printf("-----------------------------------------------------\n");
    printf("<:%s> %s", implode("", $recieved_messages), PHP_EOL);
    printf("-----------------------------------------------------\n");
}
