<?php

namespace JBtje\Passports\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use JBtje\Passports\TokenRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

abstract class CheckCredentials
{
    /**
     * The Resource Server instance.
     *
     * @var \League\OAuth2\Server\ResourceServer
     */
    protected $server;

    /**
     * Token Repository.
     *
     * @var \JBtje\Passports\TokenRepository
     */
    protected $repository;

    /**
     * Create a new middleware instance.
     *
     * @param  \League\OAuth2\Server\ResourceServer  $server
     * @param  \JBtje\Passports\TokenRepository  $repository
     * @return void
     */
    public function __construct(ResourceServer $server, TokenRepository $repository)
    {
        $this->server = $server;
        $this->repository = $repository;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$scopes
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        $psr = (new PsrHttpFactory(
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException;
        }

        $this->validate($psr, $scopes);

        return $next($request);
    }

    /**
     * Validate the scopes and token on the incoming request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $psr
     * @param  array  $scopes
     * @return void
     *
     * @throws \JBtje\Passports\Exceptions\MissingScopeException|\Illuminate\Auth\AuthenticationException
     */
    protected function validate($psr, $scopes)
    {
        $token = $this->repository->find($psr->getAttribute('oauth_access_token_id'));

        $this->validateCredentials($token);

        $this->validateScopes($token, $scopes);
    }

    /**
     * Validate token credentials.
     *
     * @param  \JBtje\Passports\Token  $token
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    abstract protected function validateCredentials($token);

    /**
     * Validate token scopes.
     *
     * @param  \JBtje\Passports\Token  $token
     * @param  array  $scopes
     * @return void
     *
     * @throws \JBtje\Passports\Exceptions\MissingScopeException
     */
    abstract protected function validateScopes($token, $scopes);
}
