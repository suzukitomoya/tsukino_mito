<?php

trait Response {
    public static function response(string $text): void
    {
        echo json_encode([
            'text' => $text,
        ]);
    }
}