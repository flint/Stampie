<?php

namespace Stampie;

use Stampie\Adapter\AdapterInterface;
use Stampie\Adapter\ResponseInterface;
use Stampie\Util\IdentityUtils;

/**
 * Minimal implementation of a MailerInterface
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
abstract class Mailer implements MailerInterface
{
    /**
     * @var AdapterInterface $adapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $serverToken;

    /**
     * @param AdapterInterface $adapter
     * @param string           $serverToken
     */
    public function __construct(AdapterInterface $adapter, $serverToken)
    {
        $this->setAdapter($adapter);
        $this->setServerToken($serverToken);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function setServerToken($serverToken)
    {
        if (empty($serverToken)) {
            throw new \InvalidArgumentException('ServerToken cannot be empty');
        }

        $this->serverToken = $serverToken;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerToken()
    {
        return $this->serverToken;
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message)
    {
        $response = $this->getAdapter()->send(
            $this->getEndpoint(),
            $this->format($message),
            $this->getHeaders()
        );

        // We are clear if status is HTTP 2xx OK and are able to verify success
        // in the response from the API
        if ($response->isSuccessful() && $this->verifySuccess($response)) {
            return true;
        }

        return $this->handle($response);
    }

    /**
     * Return a key -> value array of headers
     *
     * example:
     *     array('X-Header-Name' => 'value')
     *
     * @return array
     */
    protected function getHeaders()
    {
        return array();
    }

    /**
     * @return string
     */
    abstract protected function getEndpoint();

    /**
     * Return a a string formatted for the correct Mailer endpoint.
     * Postmark this is Json, SendGrid it is a urlencoded parameter list
     *
     * @param MessageInterface $message
     *
     * @return string
     */
    abstract protected function format(MessageInterface $message);

    /**
     * If a Response is not successful it will be passed to this method
     * each Mailer should then throw an HttpException with an optional
     * ApiException to help identify the problem.
     *
     * @param ResponseInterface $response
     *
     * @throws \Stampie\Exception\ApiException
     * @throws \Stampie\Exception\HttpException
     */
    abstract protected function handle(ResponseInterface $response);

    /**
     * If a Response is successful it will be passed to this method
     * each Mailer should parse the response to validate the success
     * or then throw an HttpException with an optional
     * ApiException to help identify the problem.
     *
     * @param ResponseInterface $response
     *
     * @throws \Stampie\Exception\ApiException
     * @throws \Stampie\Exception\HttpException
     * @return boolean
     */
    protected function verifySuccess(ResponseInterface $response)
    {
        // default implemetation for Mailers who skip this
        return true;
    }

    /**
     * @param IdentityInterface|string $identity
     *
     * @return IdentityInterface
     */
    protected function normalizeIdentity($identity)
    {
        return IdentityUtils::normalizeIdentity($identity);
    }

    /**
     * @param IdentityInterface[]|string $identities
     *
     * @return IdentityInterface[]
     */
    protected function normalizeIdentities($identities)
    {
        return IdentityUtils::normalizeIdentities($identities);
    }

    /**
     * @param IdentityInterface[]|IdentityInterface|string $identities
     *
     * @return string
     */
    protected function buildIdentityString($identities)
    {
        return IdentityUtils::buildIdentityString($identities);
    }
}
