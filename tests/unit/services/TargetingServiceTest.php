<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\TargetingService;
use PHPUnit\Framework\TestCase;

class TargetingServiceTest extends TestCase
{
    private TargetingService $service;

    protected function setUp(): void
    {
        $this->service = new TargetingService();
    }

    public function testEmptyTargetingMatchesEverything(): void
    {
        $this->assertTrue($this->service->matches([], '/any/url'));
        $this->assertTrue($this->service->matches(['urlPatterns' => []], '/any/url'));
    }

    public function testIncludeGlobMatch(): void
    {
        $targeting = ['urlPatterns' => ['include' => ['/products/*']]];
        $this->assertTrue($this->service->matches($targeting, '/products/widget'));
        $this->assertTrue($this->service->matches($targeting, '/products/nested/path'));
        $this->assertFalse($this->service->matches($targeting, '/about'));
    }

    public function testIncludeExactMatch(): void
    {
        $targeting = ['urlPatterns' => ['include' => ['/sale']]];
        $this->assertTrue($this->service->matches($targeting, '/sale'));
        $this->assertFalse($this->service->matches($targeting, '/sale/extra'));
    }

    public function testExcludeOverridesInclude(): void
    {
        $targeting = [
            'urlPatterns' => [
                'include' => ['/products/*'],
                'exclude' => ['/products/secret-*'],
            ],
        ];
        $this->assertTrue($this->service->matches($targeting, '/products/widget'));
        $this->assertFalse($this->service->matches($targeting, '/products/secret-item'));
    }

    public function testExcludeWithoutIncludeMatchesAllExceptExcluded(): void
    {
        $targeting = ['urlPatterns' => ['exclude' => ['/admin/*']]];
        $this->assertTrue($this->service->matches($targeting, '/any/page'));
        $this->assertFalse($this->service->matches($targeting, '/admin/users'));
    }

    public function testQueryStringIsIgnored(): void
    {
        $targeting = ['urlPatterns' => ['include' => ['/products']]];
        $this->assertTrue($this->service->matches($targeting, '/products?id=123'));
    }

    public function testFragmentIsIgnored(): void
    {
        $targeting = ['urlPatterns' => ['include' => ['/products']]];
        $this->assertTrue($this->service->matches($targeting, '/products#section'));
        $this->assertTrue($this->service->matches($targeting, '/products?id=1#section'));
    }

    public function testLoggedInTrueRejectsAnonymous(): void
    {
        $targeting = ['loggedIn' => true];
        $this->assertFalse($this->service->matches($targeting, '/any', null));
    }

    public function testLoggedInTrueAcceptsAuthenticated(): void
    {
        $targeting = ['loggedIn' => true];
        $user = $this->makeUser(['authorGroup']);
        $this->assertTrue($this->service->matches($targeting, '/any', $user));
    }

    public function testLoggedInFalseRejectsAuthenticated(): void
    {
        $targeting = ['loggedIn' => false];
        $user = $this->makeUser(['authorGroup']);
        $this->assertFalse($this->service->matches($targeting, '/any', $user));
    }

    public function testLoggedInFalseAcceptsAnonymous(): void
    {
        $targeting = ['loggedIn' => false];
        $this->assertTrue($this->service->matches($targeting, '/any', null));
    }

    public function testUserGroupsRequireMembership(): void
    {
        $targeting = ['userGroups' => ['subscribers']];
        $this->assertTrue($this->service->matches($targeting, '/any', $this->makeUser(['subscribers'])));
        $this->assertFalse($this->service->matches($targeting, '/any', $this->makeUser(['authors'])));
    }

    public function testUserGroupsOrWithinList(): void
    {
        $targeting = ['userGroups' => ['a', 'b']];
        $this->assertTrue($this->service->matches($targeting, '/any', $this->makeUser(['a'])));
        $this->assertTrue($this->service->matches($targeting, '/any', $this->makeUser(['b'])));
        $this->assertTrue($this->service->matches($targeting, '/any', $this->makeUser(['a', 'c'])));
        $this->assertFalse($this->service->matches($targeting, '/any', $this->makeUser(['c'])));
    }

    public function testUserGroupsWithAnonymousRejected(): void
    {
        $targeting = ['userGroups' => ['anything']];
        $this->assertFalse($this->service->matches($targeting, '/any', null));
    }

    public function testUserStateAndsWithUrlRules(): void
    {
        $targeting = [
            'urlPatterns' => ['include' => ['/products/*']],
            'loggedIn' => true,
        ];
        $user = $this->makeUser(['any']);
        $this->assertTrue($this->service->matches($targeting, '/products/x', $user));
        $this->assertFalse($this->service->matches($targeting, '/about', $user));
        $this->assertFalse($this->service->matches($targeting, '/products/x', null));
    }

    public function testMissingUserStateMatchesAll(): void
    {
        $targeting = ['urlPatterns' => ['include' => ['/*']]];
        $this->assertTrue($this->service->matches($targeting, '/x', null));
        $this->assertTrue($this->service->matches($targeting, '/x', $this->makeUser(['any'])));
    }

    /** Build an object with getGroups() returning objects that each have a public $handle. */
    private function makeUser(array $groupHandles): object
    {
        $groups = array_map(static function (string $h) {
            return new class ($h) {
                public function __construct(public string $handle) {}
            };
        }, $groupHandles);
        return new class ($groups) {
            public function __construct(private array $groups) {}
            public function getGroups(): array { return $this->groups; }
        };
    }

    public function testSectionsMatchWhenContextInList(): void
    {
        $targeting = ['sections' => ['news']];
        $element = $this->makeElement(42, 'news');
        $this->assertTrue($this->service->matches($targeting, '/any', null, $element));
    }

    public function testSectionsRejectWhenContextNotInList(): void
    {
        $targeting = ['sections' => ['news']];
        $element = $this->makeElement(42, 'about');
        $this->assertFalse($this->service->matches($targeting, '/any', null, $element));
    }

    public function testSectionsRejectWhenContextNull(): void
    {
        $targeting = ['sections' => ['news']];
        $this->assertFalse($this->service->matches($targeting, '/any', null, null));
    }

    public function testEntryIdsMatch(): void
    {
        $targeting = ['entryIds' => [42, 99]];
        $this->assertTrue($this->service->matches($targeting, '/any', null, $this->makeElement(42, 'news')));
        $this->assertTrue($this->service->matches($targeting, '/any', null, $this->makeElement(99, 'blog')));
        $this->assertFalse($this->service->matches($targeting, '/any', null, $this->makeElement(7, 'news')));
    }

    public function testEntryIdsRejectWhenContextNull(): void
    {
        $targeting = ['entryIds' => [42]];
        $this->assertFalse($this->service->matches($targeting, '/any', null, null));
    }

    public function testSectionsAndEntryIdsAnd(): void
    {
        $targeting = ['sections' => ['news'], 'entryIds' => [42]];
        $this->assertTrue($this->service->matches($targeting, '/any', null, $this->makeElement(42, 'news')));
        $this->assertFalse($this->service->matches($targeting, '/any', null, $this->makeElement(42, 'blog')));
        $this->assertFalse($this->service->matches($targeting, '/any', null, $this->makeElement(99, 'news')));
    }

    public function testFullTargetingCombination(): void
    {
        $targeting = [
            'urlPatterns' => ['include' => ['/products/*']],
            'sections' => ['products'],
            'loggedIn' => true,
        ];
        $user = $this->makeUser(['customers']);
        $element = $this->makeElement(42, 'products');
        $this->assertTrue($this->service->matches($targeting, '/products/1', $user, $element));
        $this->assertFalse($this->service->matches($targeting, '/about', $user, $element));
        $this->assertFalse($this->service->matches($targeting, '/products/1', null, $element));
        $this->assertFalse($this->service->matches($targeting, '/products/1', $user, $this->makeElement(42, 'news')));
    }

    private function makeElement(int $id, string $sectionHandle): object
    {
        $section = new class ($sectionHandle) {
            public function __construct(public string $handle) {}
        };
        return new class ($id, $section) extends \craft\base\Element {
            public function __construct(int $id, private object $section)
            {
                $this->id = $id;
            }
            public function getSection(): object { return $this->section; }
        };
    }
}
