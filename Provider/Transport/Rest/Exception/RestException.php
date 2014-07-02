<?php

namespace OroCRM\Bundle\ZendeskBundle\Provider\Transport\Rest\Exception;

use Oro\Bundle\IntegrationBundle\Provider\Rest\Client\RestResponseInterface;
use Oro\Bundle\IntegrationBundle\Provider\Rest\Exception\RestException as BaseException;

use OroCRM\Bundle\ZendeskBundle\Exception\ZendeskException;

class RestException extends BaseException implements ZendeskException
{
    /**
     * @param RestResponseInterface $response
     * @param string|null $message
     * @param \Exception|null $previous
     * @return RestException
     */
    public static function createFromResponse(
        RestResponseInterface $response,
        $message = null,
        \Exception $previous = null
    ) {
        if ($response->isClientError()) {
            return InvalidRecordException::createFromResponse($response, $message, $previous);
        }

        return parent::createFromResponse($response, $message, $previous);
    }
}
