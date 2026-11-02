<?php declare(strict_types=1);

namespace AnnotateTest\Api\Adapter;

use AnnotateTest\AnnotateTestTrait;
use Omeka\Api\Request;
use Omeka\Stdlib\ErrorStore;
use Omeka\Test\AbstractHttpControllerTestCase;

class SpamGuardTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        if (!$this->getServiceLocator()->has('SpamGuard\SpamChecker')) {
            $this->markTestSkipped('Module SpamGuard not installed.');
        }
    }

    public function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    public function testAnonymousAnnotationWithProofIsAccepted(): void
    {
        $errorStore = $this->validateCreate('A fine view of the harbour.', $this->solveProof());
        $this->assertFalse($errorStore->hasErrors());
    }

    public function testAnonymousAnnotationWithoutProofIsRejected(): void
    {
        $errorStore = $this->validateCreate('A fine view of the harbour.');
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testAnonymousAnnotationWithInvalidNonceIsRejected(): void
    {
        $proof = $this->solveProof();
        $proof['nonce'] = 'invalid';
        $errorStore = $this->validateCreate('A fine view of the harbour.', $proof);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testAnonymousAnnotationWithForgedTokenIsRejected(): void
    {
        $proof = $this->solveProof();
        $proof['token'] .= 'x';
        $errorStore = $this->validateCreate('A fine view of the harbour.', $proof);
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testAnonymousSpamWithProofIsRejected(): void
    {
        $errorStore = $this->validateCreate(str_repeat('https://spam.example.com/ ', 5), $this->solveProof());
        $this->assertTrue($errorStore->hasErrors());
    }

    public function testIdentifiedUserIsNotChecked(): void
    {
        $this->loginAdmin();
        $errorStore = $this->validateCreate(str_repeat('https://spam.example.com/ ', 5));
        $this->assertFalse($errorStore->hasErrors());
    }

    protected function validateCreate(string $text, array $spamGuard = []): ErrorStore
    {
        $request = new Request(Request::CREATE, 'annotations');
        $request->setContent([
            'oa:motivatedBy' => [['@value' => 'commenting']],
            'oa:hasBody' => [['rdf:value' => [['@value' => $text]]]],
            'oa:hasTarget' => [['oa:hasSource' => [['value_resource_id' => 1]]]],
        ]);
        $request->setOption('spamguard', $spamGuard);
        $errorStore = new ErrorStore();
        $this->getServiceLocator()->get('Omeka\ApiAdapterManager')
            ->get('annotations')
            ->validateRequest($request, $errorStore);
        return $errorStore;
    }

    /**
     * Solve the proof-of-work like the browser, with a token issued earlier, so
     * the check "too fast" is not triggered.
     */
    protected function solveProof(): array
    {
        $services = $this->getServiceLocator();
        $salt = bin2hex(random_bytes(16));
        $ip = (string) (new \Laminas\Http\PhpEnvironment\RemoteAddress())->getIpAddress();
        $token = $services->get('SpamGuard\FormToken')->issue($salt, $ip, time() - 60);
        $prefix = str_repeat('0', (int) ($services->get('Omeka\Settings')->get('spamguard_pow_difficulty') ?: 4));
        for ($nonce = 0; strpos(hash('sha256', $salt . ':' . $nonce), $prefix) !== 0; ++$nonce);
        return ['token' => $token, 'nonce' => (string) $nonce];
    }
}
