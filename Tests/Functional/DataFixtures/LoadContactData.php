<?php

namespace OroCRM\Bundle\ZendeskBundle\Tests\Functional\DataFixtures;

use OroCRM\Bundle\ContactBundle\Entity\Contact;

use Doctrine\Common\Persistence\ObjectManager;
use OroCRM\Bundle\ContactBundle\Entity\ContactEmail;

class LoadContactData extends AbstractZendeskFixture
{
    /**
     * @var array
     */
    protected $data = array(
        array(
            'reference' => 'contact:jim.smith@example.com',
            'firstName' => 'Jim',
            'lastName' => 'Smith',
            'email' => 'jim.smith@example.com',
        ),
        array(
            'reference' => 'contact:mike.johnson@example.com',
            'firstName' => 'Mike',
            'lastName' => 'Johnson',
            'email' => 'mike.johnson@example.com',
        ),
    );

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        foreach ($this->data as $data) {
            $entity = new Contact();

            $this->setEntityPropertyValues($entity, $data, array('email', 'reference'));

            if (isset($data['reference'])) {
                $this->setReference($data['reference'], $entity);
            }

            $entity->addEmail($this->createContactEmail($data['email'], true));

            $manager->persist($entity);
        }

        $manager->flush();
    }

    /**
     * @param string $email
     * @param bool $primary
     * @return ContactEmail
     */
    protected function createContactEmail($email, $primary = true)
    {
        $result = new ContactEmail();
        $result->setEmail($email);
        $result->setPrimary($primary);
        return $result;
    }
}
