<?php

declare(strict_types=1);

namespace Ragefill;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private string $adminPassword;

    public function __construct(string $adminPassword)
    {
        $this->adminPassword = $adminPassword;
    }

    public function process(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized();
        }

        $token = substr($authHeader, 7);

        if (!$this->validateToken($token)) {
            return $this->unauthorized();
        }

        return $handler->handle($request);
    }

    public function generateToken(string $password): ?string
    {
        if (!hash_equals($this->adminPassword, $password)) {
            return null;
        }

        $payload = [
            'exp' => time() + 86400, // 24 hours
            'iat' => time(),
        ];

        $data = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $data, $this->adminPassword);

        return "$data.$signature";
    }

    private function validateToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        [$data, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $data, $this->adminPassword);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $payload = json_decode(base64_decode($data), true);
        if (!is_array($payload) || !isset($payload['exp'])) {
            return false;
        }

        return $payload['exp'] > time();
    }

    private function unauthorized(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => 'Не авторизован']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
