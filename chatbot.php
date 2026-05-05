<?php

function getBotResponse($input)
{
    $input = strtolower(trim($input));

    // YOUR "DATA" (edit this part)
    $responses = [
    "hello" => "Hi there 👋",
    "hi" => "Hello! How can I help you?",
    "how are you" => "I'm just PHP code, but I'm doing fine 😄",
    "name" => "I'm your simple PHP chatbot.",
    "bye" => "Goodbye! 👋",
    "aab" => "We offer many courses, there are computer science and law and many more"
];

    foreach ($responses as $keyword => $reply) {
        if (strpos($input, $keyword) !== false) {
            return $reply;
        }
    }

    return "Sorry, I don't understand that 🤔";
}