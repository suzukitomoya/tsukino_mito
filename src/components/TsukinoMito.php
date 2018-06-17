<?php
namespace App\Components;

use App\Response;

/**
 * 月ノ美兎、がんばれ。
 */
class TsukinoMito
{
    use Response;

    const MESSAGES = [
        'バーチャルライバーの月ノ美兎です！',
        '起立！気をつけ！こんにちは、月ノ美兎です！',
        '頭脳明晰、容姿端麗、ポケモンマスター',
        '僕はね、もう音楽なんかどうでも良くて、君のことが好きなんやけど、でも、あの、その、だから楽器を握るんじゃなくて、君の手を握りたいけど、だけれども、だけれども、僕はもう、こうやって音楽を奏でて、君に言葉を伝えるその術しか持ってないから、僕は君のために、歌うも、ぼ、僕のために歌いたいんです！',
    ];

    /**
     * @param array $request
     */
    public static function run(array $request)
    {
        self::response(self::MESSAGES[array_rand(self::MESSAGES)]);
        exit;
    }
}