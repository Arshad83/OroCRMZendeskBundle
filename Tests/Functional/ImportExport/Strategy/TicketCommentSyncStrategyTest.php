<?php

namespace OroCRM\Bundle\ZendeskBundle\Tests\Functional\ImportExport\Strategy;

use Doctrine\ORM\EntityManager;

use OroCRM\Bundle\ZendeskBundle\Entity\TicketComment;
use OroCRM\Bundle\ZendeskBundle\ImportExport\Strategy\TicketCommentSyncStrategy;
use OroCRM\Bundle\ZendeskBundle\Entity\User as ZendeskUser;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @outputBuffering enabled
 * @dbIsolation
 * @dbReindex
 */
class TicketCommentSyncStrategyTest extends WebTestCase
{
    /**
     * @var int
     */
    protected static $ticketId;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $context;

    /**
     * @var TicketCommentSyncStrategy
     */
    protected $strategy;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures(['OroCRM\\Bundle\\ZendeskBundle\\Tests\\Functional\\DataFixtures\\LoadTicketData']);

        $this->entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->strategy = $this->getContainer()->get('orocrm_zendesk.importexport.strategy.ticket_comment_sync');
        $this->context = $this->getMock('Oro\\Bundle\\ImportExportBundle\\Context\\ContextInterface');
        $this->strategy->setImportExportContext($this->context);
    }

    protected function postFixtureLoad()
    {
        self::$ticketId = $this->getReference('zendesk_ticket_42')->getOriginId();
    }

    /**
     * @expectedException \Oro\Bundle\ImportExportBundle\Exception\InvalidArgumentException
     * @expectedExceptionMessage Imported entity must be instance of OroCRM\Bundle\ZendeskBundle\Entity\TicketComment,
     * stdClass given.
     */
    public function testProcessFailsWithInvalidArgument()
    {
        $this->strategy->process(new \stdClass());
    }

    /**
     * @expectedException \Oro\Bundle\ImportExportBundle\Exception\InvalidArgumentException
     * @expectedExceptionMessage Option "ticketId" must be set.
     */
    public function testProcessFailsWithInvalidContext()
    {
        $this->strategy->process($this->createZendeskTicketComment()->setOriginId(1));
    }

    public function testProcessNewZendeskTicketComment()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $ticketComment = $this->createZendeskTicketComment()->setOriginId(1);

        $this->assertEquals($ticketComment, $this->strategy->process($ticketComment));
        $this->assertFalse($this->entityManager->contains($ticketComment));
    }

    public function testProcessExistingZendeskTicketComment()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $ticketComment = $this->createZendeskTicketComment()
            ->setOriginId(1000)
            ->setBody('Updated body')
            ->setHtmlBody('Updated body html')
            ->setPublic(false)
            ->setCreatedAt(new \DateTime('2014-04-10T12:12:21Z'));

        $result = $this->strategy->process($ticketComment);

        $this->assertInstanceOf('OroCRM\\Bundle\\ZendeskBundle\\Entity\\TicketComment', $result);

        $this->assertNotSame($ticketComment, $result);
        $this->assertNotNull($result->getId());
        $this->assertEquals($ticketComment->getOriginId(), $result->getOriginId());
        $this->assertEquals($ticketComment->getBody(), $result->getBody());
        $this->assertEquals($ticketComment->getHtmlBody(), $result->getHtmlBody());
        $this->assertEquals($ticketComment->getPublic(), $result->getPublic());
        $this->assertEquals($ticketComment->getCreatedAt(), $result->getCreatedAt());

        $this->assertFalse($this->entityManager->contains($ticketComment));
        $this->assertTrue($this->entityManager->contains($result));
    }

    public function testProcessLinksAuthor()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $user = $this->getReference('zendesk_user:james.cook@example.com');
        $originId = $user->getOriginId();
        $ticketComment = $this->createZendeskTicketComment()
            ->setOriginId(1)
            ->setAuthor($this->createZendeskUser()->setOriginId($originId));

        $this->assertSame($ticketComment, $this->strategy->process($ticketComment));

        $this->assertInstanceOf('OroCRM\\Bundle\\ZendeskBundle\\Entity\\User', $ticketComment->getAuthor());
        $this->assertEquals($originId, $ticketComment->getAuthor()->getOriginId());
        $this->assertTrue($this->entityManager->contains($ticketComment->getAuthor()));
    }

    public function testProcessCreatesNewCaseComment()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $ticketComment = $this->createZendeskTicketComment()
            ->setOriginId(1)
            ->setBody('Comment body')
            ->setCreatedAt(new \DateTime('2014-04-10T12:12:21Z'))
            ->setPublic(true);

        $this->assertEquals($ticketComment, $this->strategy->process($ticketComment));

        $comment = $ticketComment->getRelatedComment();
        $this->assertInstanceOf('OroCRM\\Bundle\\CaseBundle\\Entity\\CaseComment', $comment);
        $this->assertFalse($this->entityManager->contains($comment));

        $this->assertEquals($ticketComment->getBody(), $comment->getMessage());
        $this->assertEquals($ticketComment->getCreatedAt(), $comment->getCreatedAt());
        $this->assertEquals($ticketComment->getPublic(), $comment->isPublic());
    }

    public function testProcessSyncsCaseCommentOwner()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $expectedOwner = $this->getReference('user:james.cook@example.com');
        $agentUser = $this->getReference('zendesk_user:james.cook@example.com');

        $ticketComment = $this->createZendeskTicketComment()
            ->setOriginId(1)
            ->setAuthor($agentUser);

        $this->assertSame($ticketComment, $this->strategy->process($ticketComment));

        $comment = $ticketComment->getRelatedComment();
        $this->assertInstanceOf('OroCRM\\Bundle\\CaseBundle\\Entity\\CaseComment', $comment);
        $this->assertInstanceOf('Oro\\Bundle\\UserBundle\\Entity\\User', $comment->getOwner());
        $this->assertTrue($this->entityManager->contains($comment->getOwner()));
        $this->assertEquals($expectedOwner->getId(), $comment->getOwner()->getId());
    }

    public function testProcessSyncsCaseCommentContact()
    {
        $this->setExpectedContextOptions(['ticketId' => self::$ticketId]);

        $expectedContact = $this->getReference('contact:jim.smith@example.com');
        $endUser = $this->getReference('zendesk_user:jim.smith@example.com');

        $ticketComment = $this->createZendeskTicketComment()
            ->setOriginId(1)
            ->setAuthor($endUser);

        $this->assertSame($ticketComment, $this->strategy->process($ticketComment));

        $comment = $ticketComment->getRelatedComment();
        $this->assertInstanceOf('OroCRM\\Bundle\\CaseBundle\\Entity\\CaseComment', $comment);
        $this->assertInstanceOf('OroCRM\\Bundle\\ContactBundle\\Entity\\Contact', $comment->getContact());
        $this->assertTrue($this->entityManager->contains($comment->getContact()));
        $this->assertEquals($expectedContact->getId(), $comment->getContact()->getId());
    }

    protected function createZendeskTicketComment()
    {
        return new TicketComment();
    }

    protected function createZendeskUser()
    {
        return new ZendeskUser();
    }

    /**
     * @param array $options
     */
    protected function setExpectedContextOptions(array $options)
    {
        $this->context->expects($this->any())
            ->method('getOption')
            ->will(
                $this->returnCallback(
                    function ($name) use ($options) {
                        return isset($options[$name]) ? $options[$name] : null;
                    }
                )
            );
    }
}
