<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erilshk\Sisp\Entity;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use PHPUnit\Framework\TestCase;

final class EntityTest extends TestCase
{
    public function testListPreservesNamesAndIntegerCodes(): void
    {
        $entities = Entity::all();
        self::assertCount(21, $entities);
        self::assertSame(2, $entities['RECHARGE_ALOU']);
        self::assertSame(47, $entities['SERVICE_AGUAS_SANTIAGO']);
        self::assertNotContains('CATEGORIES', array_keys($entities));
        foreach ($entities as $code) {
            self::assertIsInt($code);
        }
    }

    public function testCategoriesCanOverlapAndPreserveNames(): void
    {
        $water = Entity::all(' WATER ');
        $electricity = Entity::all('electricity');
        self::assertSame(92, $water['SERVICE_AGUAS_ENERGIA_BOA_VISTA']);
        self::assertSame(92, $electricity['SERVICE_AGUAS_ENERGIA_BOA_VISTA']);
        self::assertArrayNotHasKey('SERVICE_ALOU_MOBILE', $water);
        self::assertSame(88, Entity::all('recharge')['RECHARGE_ELECTRA_SOUTH']);
        self::assertSame(88, $electricity['RECHARGE_ELECTRA_SOUTH']);
        foreach (['insurance', 'internet', 'transport', 'telephone'] as $category) {
            self::assertNotEmpty(Entity::all($category));
        }
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $this->expectException(Vinti4Exception::class);
        Entity::all('unknown');
    }
}
