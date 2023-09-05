<?php


$host = "tcp://localhost:50000";
$socket = stream_socket_client($host, $errno, $errstr, 30);

if ($socket === false) {
    echo "$errstr ($errno)";
    exit;
}

printf("あなたの名前を入力してください:");
$stdin = fgets(STDIN);
var_dump(mb_convert_encoding($stdin, "UTF-8"));
var_dump($stdin);

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
