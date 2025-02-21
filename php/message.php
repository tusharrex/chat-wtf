<?php
session_start();
header( "Content-type: text/event-stream" );
header( "Cache-Control: no-cache" );
ob_end_flush();

$settings = require( __DIR__ . "/settings.php" );
require( __DIR__ . "/chatgpt.php" );

// get chat history from session
$chat_id = htmlspecialchars( $_REQUEST['chat_id'] );
$context = $_SESSION['chats'][$chat_id]['messages'] ?? [];

$messages = [];

if( ! empty( $settings['system_message'] ) ) {
    $messages[] = [
        "role" => "system",
        "content" => $settings['system_message'],
    ];
}

foreach( $context as $msg ) {
    if( $msg["role"] === "system" ) {
        continue;
    }
    $messages[] = [
        "role" => $msg["role"],
        "content" => $msg["content"],
    ];
}

if( isset( $_POST['message'] ) ) {
    $messages[] = [
        "role" => "user",
        "content" => $_POST['message'],
    ];

    $_SESSION['chats'][$chat_id]['messages'] = $messages;

    die( $chat_id );
}

$error = null;

// create a new completion
try {
    $response_text = send_chatgpt_message(
        $messages,
        $settings['api_key'],
        $settings['model'] ?? "",
    );
} catch( CurlErrorException $e ) {
    $error = "Sorry, there was an error in the connection. Check the error logs.";
} catch( OpenAIErrorException $e ) {
    $error = "Sorry, there was an unknown error in the OpenAI request";
}

if( $error !== null ) {
    $response_text = $error;
    echo "data: " . json_encode( ["content" => $error] ) . "\n\n";
    flush();
}

$messages[] = [
    "role" => "assistant",
    "content" => $response_text,
];

if( ! isset( $_SESSION['chats'] ) ) {
    $_SESSION['chats'] = [];
}

if( ! isset( $_SESSION['chats'][$chat_id] ) ) {
    $_SESSION['chats'] = array_merge( [
        $chat_id => [
            "messages" => [],
            "title" => "Untitled",
        ]
    ], $_SESSION['chats'] );
}

$_SESSION['chats'][$chat_id]['messages'] = $messages;

echo "event: stop\n";
echo "data: stopped\n\n";
