<?php

namespace OroCRM\Bundle\ZendeskBundle\ImportExport\Strategy;

use Doctrine\ORM\EntityManager;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use OroCRM\Bundle\ZendeskBundle\ImportExport\Strategy\Provider\ZendeskProvider;

use Oro\Bundle\ImportExportBundle\Context\ContextAwareInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;

use Oro\Bundle\ImportExportBundle\Exception\InvalidArgumentException;
use Oro\Bundle\ImportExportBundle\Strategy\StrategyInterface;

abstract class AbstractSyncStrategy implements StrategyInterface, ContextAwareInterface
{
    /**
     * @var PropertyAccessor
     */
    static private $propertyAccessor;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ZendeskProvider
     */
    protected $zendeskProvider;

    /**
     * @var ContextInterface
     */
    private $context;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Validates availability of origin id field
     *
     * @param mixed $entity
     * @return bool
     */
    public function validateOriginId($entity)
    {
        if (!$entity->getOriginId()) {
            $message = $this->buildMessage(
                'Can\'t process record [id=null].',
                $this->getContext()->getReadCount(),
                'read_index'
            );

            $this->getContext()->addError($message);
            $this->getLogger()->error($message);

            $this->getContext()->incrementErrorEntriesCount();

            return false;
        }

        return true;
    }

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param ZendeskProvider $zendeskProvider
     */
    public function setZendeskProvider(ZendeskProvider $zendeskProvider)
    {
        $this->zendeskProvider = $zendeskProvider;
    }

    /**
     * @param LoggerInterface $logger
     */
    protected function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param mixed $entity
     * @param string $fieldName
     * @param string $dictionaryEntityAlias
     */
    protected function refreshDictionaryField($entity, $fieldName, $dictionaryEntityAlias = null)
    {
        $dictionaryEntityAlias = $dictionaryEntityAlias ? : $fieldName;
        $value = null;
        $entityGetter = 'get' . ucfirst($fieldName);
        $entitySetter = 'set' . ucfirst($fieldName);
        $providerGetter = 'get' . ucfirst($dictionaryEntityAlias);
        if ($entity->$entityGetter()) {
            $value = $this->zendeskProvider->$providerGetter($entity->$entityGetter());
            if (!$value) {
                $valueName = $entity->$entityGetter()->getName();
                $this->getLogger()->warning(
                    $this->buildMessage("Can't find $fieldName [name=$valueName].", $entity)
                );
            }
        } else {
            $this->getLogger()->warning($this->buildMessage(ucfirst($fieldName) . " is empty.", $entity));
        }
        $entity->$entitySetter($value);
    }

    /**
     * Sync properties of $target object with $source object
     *
     * @param mixed $target
     * @param mixed $source
     * @param array $excludeProperties
     * @throws InvalidArgumentException
     */
    protected function syncProperties($target, $source, array $excludeProperties = array())
    {
        if (!is_object($target)) {
            throw new InvalidArgumentException(
                'Expect argument $target has object type object but %s given.',
                gettype($target)
            );
        }

        if (!is_object($source)) {
            throw new InvalidArgumentException(
                'Expect argument $target has object type object but %s given.',
                gettype($source)
            );
        }

        if (get_class($target) !== get_class($source)) {
            throw new InvalidArgumentException(
                'Expect argument $source is instance of %s but %s given.',
                get_class($target),
                get_class($source)
            );
        }

        $reflectionClass = new \ReflectionClass($target);

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            if (in_array($propertyName, $excludeProperties)) {
                continue;
            }
            $this->getPropertyAccessor()->setValue(
                $target,
                $propertyName,
                $this->getPropertyAccessor()->getValue($source, $propertyName)
            );
        }
    }

    /**
     * Returns managed entity
     *
     * @param mixed $entity
     * @param string $identifierName
     * @return mixed|null
     */
    protected function findExistingEntity($entity, $identifierName = 'id')
    {
        $existingEntity = null;
        if ($entity) {
            $identifier = $this->getPropertyAccessor()->getValue($entity, $identifierName);
            $existingEntity = $this->entityManager
                ->getRepository(get_class($entity))
                ->findOneBy(array($identifierName => $identifier));
        }

        return $existingEntity;
    }

    /**
     * @return PropertyAccessor
     */
    protected function getPropertyAccessor()
    {
        if (!self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
        return self::$propertyAccessor;
    }

    /**
     * @param ContextInterface $context
     */
    public function setImportExportContext(ContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * @return ContextInterface
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (null === $this->logger) {
            $logger = $this->context->getOption('logger');
            if ($logger instanceof LoggerInterface) {
                $this->logger = $logger;
            } else {
                $this->logger = new NullLogger();
            }
        }

        return $this->logger;
    }

    /**
     * Build message for log
     *
     * @param string $message
     * @param mixed $record
     * @param string $property
     * @param string $displayProperty
     * @return string
     */
    protected function buildMessage($message, $record, $property = 'id', $displayProperty = null)
    {
        $displayProperty = $property ? : $displayProperty;
        if (is_object($record)) {
            $record = $record->{'get' . ucfirst($property)}();
        }

        return sprintf('Record [%s=%s]: %s', $displayProperty, $record, $message);
    }
}
