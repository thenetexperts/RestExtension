<?php

namespace Behat\RestExtension\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\RestExtension\HttpClient\HttpClient;
use Behat\RestExtension\Message\Request;
use Behat\RestExtension\Message\RequestParser;
use Behat\RestExtension\Message\Response;

class RestContext implements Context
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var RequestParser
     */
    private $requestParser;

    /**
     * @param string        $baseUrl
     * @param HttpClient    $httpClient
     * @param RequestParser $requestParser
     */
    public function __construct($baseUrl, HttpClient $httpClient, RequestParser $requestParser)
    {
        $this->baseUrl = $baseUrl;
        $this->httpClient = $httpClient;
        $this->requestParser = $requestParser;
    }

    /**
     * @When /^(?:|the )client requests (?P<method>GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS) "(?P<resource>[^"]*)"$/
     */
    public function theClientRequests($method, $resource)
    {
        $this->send(new Request($method, $resource));
    }

    /**
     * @When /^(?:|the )client requests (?P<method>POST|PUT|OPTIONS) "(?P<resource>[^"]*)" with:$/
     */
    public function theClientRequestsWith($method, $resource, PyStringNode $content = null)
    {
        $request = new Request($method, $resource);
        $this->requestParser->parse((string) $content, $request);

        $this->send($request);
    }

    /**
     * @Then /^(?:|the )response should be (?:|a )(?P<statusCode>[0-9]{3}) with json:$/
     */
    public function theResponseShouldBeJson($statusCode, PyStringNode $content)
    {
        $response = $this->httpClient->getLastResponse();

        if (null === $response) {
            throw new \LogicException('No request was made');
        }

        if ((int) $statusCode !== $response->getStatusCode()) {
            throw new \LogicException(sprintf('Expected %d status code but %d received', $statusCode, $response->getStatusCode()));
        }

        // @todo introduce a differ
        $receivedJson = json_decode($response->getContent());
        $expectedJson = json_decode($content);

        if ($receivedJson != $expectedJson) {
            $message = sprintf('Expected to get "%s" but received: "%s"', json_encode($expectedJson, JSON_PRETTY_PRINT), json_encode($receivedJson, JSON_PRETTY_PRINT));

            throw new \LogicException($message);
        }
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    private function send(Request $request)
    {
        $method = strtolower($request->getMethod());
        $resource = $this->baseUrl . $request->getResource();

        if (in_array($method, array('get', 'head'))) {
            return $this->httpClient->$method($resource);
        }

        return $this->httpClient->$method($resource, $request->getHeaders(), $request->getBody());
    }
}