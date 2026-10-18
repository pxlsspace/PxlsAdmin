<?php

use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

use League\OAuth2\Client\Provider\AbstractProvider as Provider;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

class OidcProvider extends GenericProvider {
    private array $keys;
    private array $signingMethods;
    
    public function __construct(
        string $redirect,
        string $clientId,
        string $clientSecret,
        string $issuer,
        bool $verify_ssl
    ) {
        $client = new GuzzleHttp\Client(['verify' => $verify_ssl]);

        $discovery_request = $client->get($issuer . '/.well-known/openid-configuration');
        $discovery = $this->parseJson($discovery_request->getBody());

        if (empty($discovery['authorization_endpoint'])) {
            throw new UnexpectedValueException("Missing authorization_endpoint in discovery");
        }

        if (empty($discovery['token_endpoint'])) {
            throw new UnexpectedValueException("Missing token_endpoint in discovery");
        }
        
        if (empty($discovery['jwks_uri'])) {
            throw new UnexpectedValueException("Missing jwks_uri in discovery");
        }

        $jwks_request = $client->get($discovery['jwks_uri']);
        $this->keys = JWK::parseKeySet($this->parseJson($jwks_request->getBody()));
        $this->signingMethods = $discovery['id_token_signing_alg_values_supported'];
        
        parent::__construct([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirect,
            'urlAuthorize' => $discovery['authorization_endpoint'],
            'urlAccessToken' => $discovery['token_endpoint'],
            'urlResourceOwnerDetails' => $discovery['userinfo_endpoint'],
            'scopes' => 'openid'
        ], [
           'optionProvider' => new HttpBasicAuthOptionProvider(Provider::METHOD_POST, [
               'clientId' => $clientId,
               'clientSecret' => $clientSecret,
           ]),
        ]);
        
        $this->setHttpClient($client);
    }

    public function decodeId(AccessTokenInterface $token): stdClass {
        $key = array_find($this->keys, function($k) {
            $validAlg = array_find($this->signingMethods, function($s) use ($k) {
                return $k->getAlgorithm() == $s;
            });
            // TODO ([  ]): also check key.use = sig? doesn't seem to be a
            // parsed properly by jwk…
            return $validAlg;
        });
        return JWT::decode($token->getValues()['id_token'], $key);
    }
}
