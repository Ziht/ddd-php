<?php

namespace AlephTools\DDD\Tests\Common\Infrastructure;

use PHPUnit\Framework\TestCase;
use AlephTools\DDD\Common\Infrastructure\DomainEventPublisher;
use AlephTools\DDD\Common\Infrastructure\EventSourcedEntity;
use AlephTools\DDD\Common\Model\Events\EntityCreated;
use AlephTools\DDD\Common\Model\Events\EntityDeleted;
use AlephTools\DDD\Common\Model\Events\EntityUpdated;
use AlephTools\DDD\Common\Model\Identity\LocalId;

/**
 * @property mixed $prop1
 * @property mixed $prop2
 * @property mixed $prop3
 */
class EventSourcedEntityTestObject extends EventSourcedEntity
{
    private $prop1;
    private $prop2;
    private $prop3;

    public function assign(array $properties)
    {
        $this->applyChanges($properties);
    }

    public function delete()
    {
        $this->publishEntityDeletedEvent();
    }
}

class EventSourcedEntityTest extends TestCase
{
    use DomainEventPublisherAware;

    public function testCreationWithEvent(): void
    {
        $id = new class(1) extends LocalId {};
        $properties = [
            'id' => $id,
            'prop1' => 'a',
            'prop2' => true,
            'prop3' => 123
        ];
        new EventSourcedEntityTestObject($properties);

        $events = $this->publisher->getEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EntityCreated::class, $events[0]);
        $this->assertTrue($events[0]->id->equals($id));
        $this->assertSame(EventSourcedEntityTestObject::class, $events[0]->entity);
        $this->assertSame($properties, $events[0]->properties);
    }

    public function testCreationWithoutEvent(): void
    {
        new EventSourcedEntityTestObject([], true);

        $this->assertCount(0, $this->publisher->getEvents());
    }

    public function testDeleteEntity(): void
    {
        $id = new class(1) extends LocalId {};
        $entity = new EventSourcedEntityTestObject(['id' => $id], true);
        $entity->delete();

        $events = $this->publisher->getEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EntityDeleted::class, $events[0]);
        $this->assertTrue($events[0]->id->equals($id));
        $this->assertSame(EventSourcedEntityTestObject::class, $events[0]->entity);
    }

    public function testUpdateScalarProperties(): void
    {
        $id = new class(1) extends LocalId {};
        $properties = [
            'id' => $id,
            'prop1' => 'a',
            'prop2' => true,
            'prop3' => 123
        ];
        $entity = new EventSourcedEntityTestObject($properties, true);

        $entity->assign([
            'prop1' => 123,
            'prop2' => 'b',
            'prop3' => false
        ]);

        $events = $this->publisher->getEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EntityUpdated::class, $events[0]);
        $this->assertTrue($events[0]->id->equals($id));
        $this->assertSame(EventSourcedEntityTestObject::class, $events[0]->entity);
        $this->assertSame(['prop1' => 'a', 'prop2' => true, 'prop3' => 123], $events[0]->oldProperties);
        $this->assertSame(['prop1' => 123, 'prop2' => 'b', 'prop3' => false], $events[0]->newProperties);
    }

    public function testUpdateNestedProperties(): void
    {
        $properties = [
            'prop1' => 'a',
            'prop2' => true,
            'prop3' => 123
        ];
        $entity1 = new EventSourcedEntityTestObject($properties, true);

        $properties = [
            'prop1' => false,
            'prop2' => 321,
            'prop3' => 'b'
        ];
        $entity2 = new EventSourcedEntityTestObject($properties, true);

        $properties = [
            'prop1' => 'a',
            'prop2' => false,
            'prop3' => 'foo'
        ];
        $entity3 = new EventSourcedEntityTestObject($properties, true);

        $id = new class(10) extends LocalId {};
        $properties = [
            'id' => $id,
            'prop1' => $entity1,
            'prop2' => $entity2,
            'prop3' => 'test'
        ];
        $entity = new EventSourcedEntityTestObject($properties, true);

        $entity->assign([
            'prop1' => $entity3,
            'prop2' => $entity2,
            'prop3' => $entity1
        ]);

        $events = $this->publisher->getEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(EntityUpdated::class, $events[0]);
        $this->assertTrue($events[0]->id->equals($id));
        $this->assertSame(EventSourcedEntityTestObject::class, $events[0]->entity);

        $this->assertSame([
            'prop1' => [
                'prop2' => true,
                'prop3' => 123
            ],
            'prop3' => 'test'
        ], $events[0]->oldProperties);

        $this->assertSame([
            'prop1' => [
                'prop2' => false,
                'prop3' => 'foo'
            ],
            'prop3' => $entity1
        ], $events[0]->newProperties);
    }
}