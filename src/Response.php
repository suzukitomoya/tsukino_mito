<?php
namespace App;

trait Response {
    /**
     * @param string $text
     * @return void
     */
    public static function response(string $text): void
    {
        echo json_encode([
            'text' => $text,
        ]);
    }
}