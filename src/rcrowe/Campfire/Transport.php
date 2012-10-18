<?php

namespace rcrowe\Campfire;

use Guzzle\Http\Client as Http;
use Guzzle\Plugin\CurlAuth\CurlAuthPlugin as HttpAuth;
use Guzzle\Http\Exception\BadResponseException as HttpException;

class Transport
{
    protected $config;
    protected $http;

    protected $headers = array(
        'Accept'       => 'application/json',
        'Content-type' => 'application/json',
        'User-Agent'   => 'rcrowe/Campfire',
    );

    public function __construct(Config $config, $http = NULL)
    {
        $this->config = $config;

        if ($http !== NULL)
        {
            // Make sure class is off correct type
            // Normally I would have just put this as a type hint with the param
            // Unfortunately we can't mock the Http client class as it makes use of `final`
            // or something like that, hopefully can come back and fix it
            if (!$http instanceof Http AND get_parent_class($http) !== 'Mockery\Mock')
            {
                throw new \InvalidArgumentException('Incorrect HTTP object type passed in');
            }
        }
        else
        {
            $http = new Http('https://{subdomain}.campfirenow.com/room/{room}', array(
                'subdomain' => $this->config->get('subdomain'),
                'room'      => $this->config->get('room'),
            ));

            $http->addSubscriber(new HttpAuth($this->config->get('key'), 'x'));
        }

        $this->http = $http;
    }

    protected function getRequest($msg)
    {
        return $this->http->post('speak.json', $this->headers, json_encode(array(
            'message' => array(
                'type' => 'TextMessage',
                'body' => $msg,
            )
        )));
    }

    public function send($msg)
    {
        try
        {
            $response = $this->getRequest($msg)->send();
        }
        catch(HttpException $ex)
        {
            $response = $ex->getResponse();

            switch ($response->getStatusCode())
            {
                case 401:
                    $exception = new Exceptions\Transport\UnauthorizedException('Unauthorised: API incorrect');
                    break;

                default:
                    $exception = new Exceptions\TransportException('Unknown error occurred');
            }

            $exception->setResponse($response);

            throw $exception;
        }

        if ($response->getStatusCode() !== 201)
        {
            // Something funky occurred
            $exception = new Exceptions\Transport\UnknownException('Unknown error occurred');
            $exception->setResponse($response);

            throw $exception;
        }

        return TRUE;
    }
}