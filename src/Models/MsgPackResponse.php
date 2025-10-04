<?php
// src/Models/MsgPackResponse.php

namespace App\Models;

use Psr\Http\Message\ResponseInterface as Response;

class MsgPackResponse {
    /**
     * Creates a PSR-7 Response with MessagePack content type.
     */
    public static function withMsgPack(Response $response, array $data): Response {
        if (!extension_loaded('msgpack')) {
            // Throwing an exception here is better than silently failing to JSON
            throw new \Exception("MsgPack extension not enabled.");
        }
        $response->getBody()->write(msgpack_pack($data));
        return $response->withHeader('Content-Type', 'application/x-msgpack');
    }
}
