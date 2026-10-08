<?php

/**
 * api-main.php Global API definitions and the authentication endpoint.
 *
 * This file contains the OpenAPI global metadata (info, servers, security
 * scheme) and the handler for POST /api/v1/authenticate – the only endpoint
 * that is reachable WITHOUT a JWT.
 */

declare(strict_types=1);

use App\Database;
use App\JsonResponder;
use App\JwtService;
use App\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /api/v1/authenticate
 *
 * Expects a JSON body: {"username": "...", "password": "..."}
 * Returns 200 {"token": "<jwt>"} with valid credentials,
 * 400 for a malformed body and 401 for wrong credentials.
 *
 * @param Request  $request  the incoming request (JSON body already parsed by Slim)
 * @param Response $response empty response to fill
 */
function authenticate(Request $request, Response $response): Response
{
    $body = $request->getParsedBody();

    //body must be a JSON object (BodyParsingMiddleware delivers an array)
    if (!is_array($body)) {
        return JsonResponder::send($response, 400, ['error' => 'request body must be a JSON object']);
    }

    $username = isset($body['username']) && is_string($body['username']) ? trim($body['username']) : '';
    $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';

    // both field are required and must not be empty
    if ($username === '' || $password === '') {
        return JsonResponder::send($response, 400, ['error' => 'username and password are required']);
    }

    // look up the user in the database (prepared statement inside the repository)
    $repository = new UserRepository(Database::getConnection());
    $user = $repository->findByUsername($username);

    // Same generic message for unknown user and wrong password (no user enumeration)
    if ($user === null || !password_verify($password, (string) $user['password'])) {
        return JsonResponder::send($response, 401, ['error' => 'invalid credentials']);
    }

    // credentials are valid: issue a signed JWT for all protected endpoints
    $jwtService = new JwtService();
    $token = $jwtService->issueToken((int) $user['id_user'], (string) $user['username']);

    return JsonResponder::send($response, 200, ['token' => $token]);
}
