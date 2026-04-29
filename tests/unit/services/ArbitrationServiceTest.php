<?php

namespace anvildev\socialproof\tests\unit\services;

use anvildev\socialproof\services\ArbitrationService;
use anvildev\socialproof\services\FatigueService;
use anvildev\socialproof\services\TargetingService;
use PHPUnit\Framework\TestCase;

class ArbitrationServiceTest extends TestCase
{
    private ArbitrationService $service;
    private InMemoryFatigueStore $fatigueStore;

    protected function setUp(): void
    {
        $this->fatigueStore = new InMemoryFatigueStore();
        $this->service = new ArbitrationService(
            new TargetingService(),
            new FatigueService($this->fatigueStore),
        );
    }

    public function testReturnsAllEligiblePopupsSortedByPriorityDesc(): void
    {
        $popups = [
            ['id' => 1, 'priority' => 10, 'targeting' => [], 'fatigueRules' => [], 'html' => 'a'],
            ['id' => 2, 'priority' => 90, 'targeting' => [], 'fatigueRules' => [], 'html' => 'b'],
            ['id' => 3, 'priority' => 50, 'targeting' => [], 'fatigueRules' => [], 'html' => 'c'],
        ];
        $result = $this->service->arbitrate($popups, '/', 'visitor-a');
        $this->assertSame([2, 3, 1], array_column($result, 'id'));
    }

    public function testTargetingMismatchDropsPopup(): void
    {
        $popups = [
            ['id' => 1, 'priority' => 50, 'targeting' => ['urlPatterns' => ['include' => ['/products/*']]], 'fatigueRules' => [], 'html' => 'a'],
            ['id' => 2, 'priority' => 50, 'targeting' => [], 'fatigueRules' => [], 'html' => 'b'],
        ];
        $result = $this->service->arbitrate($popups, '/about', 'visitor-a');
        $this->assertSame([2], array_column($result, 'id'));
    }

    public function testFatiguedPopupIsDropped(): void
    {
        $this->fatigueStore->set(1, 'visitor-a', ['shownCount' => 5]);
        $popups = [
            ['id' => 1, 'priority' => 50, 'targeting' => [], 'fatigueRules' => ['maxPerVisitor' => 3], 'html' => 'a'],
            ['id' => 2, 'priority' => 50, 'targeting' => [], 'fatigueRules' => ['maxPerVisitor' => 3], 'html' => 'b'],
        ];
        $result = $this->service->arbitrate($popups, '/', 'visitor-a');
        $this->assertSame([2], array_column($result, 'id'));
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->service->arbitrate([], '/', 'visitor-a'));
    }

    public function testUserPassedThroughToTargeting(): void
    {
        $popups = [
            ['id' => 1, 'priority' => 50, 'targeting' => ['loggedIn' => true], 'fatigueRules' => [], 'html' => 'a'],
            ['id' => 2, 'priority' => 50, 'targeting' => [], 'fatigueRules' => [], 'html' => 'b'],
        ];
        $anon = $this->service->arbitrate($popups, '/', 'v1', null);
        $this->assertSame([2], array_column($anon, 'id'));

        $authed = $this->service->arbitrate($popups, '/', 'v1', $this->makeUser(['any']));
        $this->assertSame([1, 2], array_column($authed, 'id'));
    }

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

    public function testContextElementPassedThroughToTargeting(): void
    {
        $popups = [
            ['id' => 1, 'priority' => 50, 'targeting' => ['sections' => ['news']], 'fatigueRules' => [], 'html' => 'a'],
            ['id' => 2, 'priority' => 50, 'targeting' => [], 'fatigueRules' => [], 'html' => 'b'],
        ];
        $result = $this->service->arbitrate($popups, '/', 'v1', null, $this->makeElement(42, 'news'));
        $this->assertSame([1, 2], array_column($result, 'id'));

        $result = $this->service->arbitrate($popups, '/', 'v1', null, $this->makeElement(42, 'other'));
        $this->assertSame([2], array_column($result, 'id'));
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
