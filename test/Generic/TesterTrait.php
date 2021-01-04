<?php declare(strict_types=1);

namespace Generic;

trait TesterTrait
{
    /**
     * Provided by \Omeka\Test\AbstractHttpControllerTestCase
     *
     * @var \Laminas\Mvc\Application
     */
    protected $application;

    /**
     * @var \Laminas\ServiceManager\ServiceManager
     */
    protected $services;

    protected function getServiceLocator(): \Laminas\ServiceManager\ServiceManager
    {
        return $this->application->getServiceManager();
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->services->get('Omeka\ApiManager');
    }

    /** Mocked in Omeka\Test\TestCase
    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->services->get('Omeka\EntityManager');
    }
     */

    protected function createUserAndLogin(
        $email,
        $role = 'guest',
        $name = 'username',
        $password = 'password'
    ): \Omeka\Entity\User {
        $user = new \Omeka\Entity\User;
        $user->setEmail($email);
        $user->setName($name);
        $user->setRole($role);
        $user->setPassword($password);
        $user->setIsActive(true);
        $entityManager = $this->services->get('Omeka\EntityManager');
        $entityManager->persist($user);
        $entityManager->flush();

        $this->logout();
        $this->loginUser($email, $password);
        return $user;
    }

    protected function loginAdmin(): void
    {
        // See Omeka S tests.
        $this->loginUser('admin@example.com', 'root');
    }

    protected function loginUser($email, $password = 'password'): \Laminas\Authentication\Result
    {
        /** @var \Laminas\Authentication\AuthenticationServiceInterface $auth */
        $auth = $this->services->get('Omeka\AuthenticationService');
        $auth->getAdapter()
            ->setIdentity($email)
            ->setCredential($password);
        return $auth->authenticate();
    }

    protected function logout(): void
    {
        $this->services->get('Omeka\AuthenticationService')->clearIdentity();
    }

    public function rolesProvider()
    {
        return [
            'global_admin' => ['global_admin@example.org', 'global_admin', true],
            'site_admin' => ['site_admin@example.org', 'site_admin', true],
            'editor' => ['editor@example.org', 'editor', false],
            'reviewer' => ['reviewer@example.org', 'reviewer', false],
            'author' => ['author@example.org', 'author', false],
            'researcher' => ['researcher@example.org', 'researcher', false],
            'guest' => ['guest@example.org', 'guest', false],
        ];
    }
}
