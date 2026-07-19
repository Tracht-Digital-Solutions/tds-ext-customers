<?php
declare(strict_types=1);

namespace Tds\Ext\Customers\Tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tds\Ext\Customers\CustomersModule;
use Tds\Panel\Contract\UserContext;

/** A configurable UserContext double (no live JWT needed). */
final class FakeUser implements UserContext
{
    /** @param string[] $perms */
    public function __construct(
        private bool $auth = true,
        private bool $admin = false,
        private array $perms = [],
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->auth;
    }

    public function userId(): ?int
    {
        return 1;
    }

    public function email(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /** @return string[] */
    public function permissions(): array
    {
        return $this->perms;
    }

    public function has(string $permission): bool
    {
        return $this->admin || in_array($permission, $this->perms, true);
    }

    public function activeCompanyId(): ?int
    {
        return null;
    }
}

/**
 * Route + RBAC coverage that needs no DB: auth + payload validation short-circuit
 * before any repository access. Data paths are covered when deployed against MySQL.
 */
final class CustomersModuleTest extends TestCase
{
    private function appWith(UserContext $user): App
    {
        $container = new Container();
        $container->set(UserContext::class, $user);
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        (new CustomersModule())->register($app);
        return $app;
    }

    private function get(App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
    }

    /** @param array<string,mixed> $body */
    private function post(App $app, string $path, array $body): \Psr\Http\Message\ResponseInterface
    {
        $req = (new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
        return $app->handle($req);
    }

    public function testMetadata(): void
    {
        $module = new CustomersModule();
        self::assertSame('customers', $module->id());
        $perms = array_map(static fn ($p): string => $p->id, $module->permissions());
        self::assertSame(['customers:read', 'customers:write'], $perms);
        self::assertDirectoryExists($module->migrations()[0]);
    }

    public function testUnauthenticatedGetsUnauthorized(): void
    {
        self::assertSame(401, $this->get($this->appWith(new FakeUser(auth: false)), '/customers')->getStatusCode());
    }

    public function testReadWithoutPermissionForbidden(): void
    {
        self::assertSame(403, $this->get($this->appWith(new FakeUser(perms: [])), '/customers')->getStatusCode());
    }

    public function testAdminListRequiresAdmin(): void
    {
        $res = $this->get($this->appWith(new FakeUser(perms: ['customers:read'])), '/admin/customers');
        self::assertSame(403, $res->getStatusCode());
    }

    public function testCreateRequiresWrite(): void
    {
        $res = $this->post($this->appWith(new FakeUser(perms: ['customers:read'])), '/customers', ['name' => 'ACME']);
        self::assertSame(403, $res->getStatusCode());
    }

    public function testCreateValidatesName(): void
    {
        $res = $this->post($this->appWith(new FakeUser(perms: ['customers:write'])), '/customers', ['name' => '']);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testCreateValidatesEmail(): void
    {
        $res = $this->post(
            $this->appWith(new FakeUser(perms: ['customers:write'])),
            '/customers',
            ['name' => 'ACME', 'email' => 'not-an-email'],
        );
        self::assertSame(422, $res->getStatusCode());
    }
}
