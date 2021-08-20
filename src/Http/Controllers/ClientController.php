<?php

namespace JBtje\Passports\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JBtje\Passports\ClientRepository;
use JBtje\Passports\Http\Rules\RedirectRule;
use JBtje\Passports\Passport;

class ClientController
{
    /**
     * The client repository instance.
     *
     * @var \JBtje\Passports\ClientRepository
     */
    protected $clients;

    /**
     * The validation factory implementation.
     *
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validation;

    /**
     * The redirect validation rule.
     *
     * @var \JBtje\Passports\Http\Rules\RedirectRule
     */
    protected $redirectRule;

    /**
     * Create a client controller instance.
     *
     * @param  \JBtje\Passports\ClientRepository  $clients
     * @param  \Illuminate\Contracts\Validation\Factory  $validation
     * @param  \JBtje\Passports\Http\Rules\RedirectRule  $redirectRule
     * @return void
     */
    public function __construct(
        ClientRepository $clients,
        ValidationFactory $validation,
        RedirectRule $redirectRule
    ) {
        $this->clients = $clients;
        $this->validation = $validation;
        $this->redirectRule = $redirectRule;
    }

    /**
     * Get all of the clients for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function forUser(Request $request)
    {
        $userId = $request->user()->getAuthIdentifier();

        $clients = $this->clients->activeForUser($userId);

        if (Passport::$hashesClientSecrets) {
            return $clients;
        }

        return $clients->makeVisible('secret');
    }

    /**
     * Store a new client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \JBtje\Passports\Client|array
     */
    public function store(Request $request)
    {
        $this->validation->make($request->all(), [
            'name' => 'required|max:191',
            'redirect' => ['required', $this->redirectRule],
            'confidential' => 'boolean',
        ])->validate();

        $client = $this->clients->create(
            $request->user()->getAuthIdentifier(), $request->name, $request->redirect,
            null, false, false, (bool) $request->input('confidential', true)
        );

        if (Passport::$hashesClientSecrets) {
            return ['plainSecret' => $client->plainSecret] + $client->toArray();
        }

        return $client->makeVisible('secret');
    }

    /**
     * Update the given client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $clientId
     * @return \Illuminate\Http\Response|\JBtje\Passports\Client
     */
    public function update(Request $request, $clientId)
    {
        $client = $this->clients->findForUser($clientId, $request->user()->getAuthIdentifier());

        if (! $client) {
            return new Response('', 404);
        }

        $this->validation->make($request->all(), [
            'name' => 'required|max:191',
            'redirect' => ['required', $this->redirectRule],
        ])->validate();

        return $this->clients->update(
            $client, $request->name, $request->redirect
        );
    }

    /**
     * Delete the given client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $clientId
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $clientId)
    {
        $client = $this->clients->findForUser($clientId, $request->user()->getAuthIdentifier());

        if (! $client) {
            return new Response('', 404);
        }

        $this->clients->delete($client);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
