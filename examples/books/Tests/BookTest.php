<?php

namespace AuthorBooks\Tests;

use Magsql\Raw;
use Maghead\Testing\ModelTestCase;
use Maghead\Runtime\Result;
use AuthorBooks\Model\Book;
use AuthorBooks\Model\BookCollection;
use AuthorBooks\Model\BookSchema;
use AuthorBooks\Model\CategorySchema;
use AuthorBooks\Model\AuthorSchema;
use AuthorBooks\Model\AuthorBookSchema;
use DateTime;

/**
 * @group app
 */
class BookTest extends ModelTestCase
{
    public function models()
    {
        return [
            new AuthorSchema,
            new BookSchema,
            new AuthorBookSchema,
        ];
    }

    public function testImmutableColumn()
    {
        $b = Book::createAndLoad(array( 'isbn' => '123123123' ));
        $ret = $b->update(array('isbn'  => '456456' ));
        $this->assertResultFail($ret, 'Should not update immutable column');
        $this->assertDelete($b);
    }



    public function testFindBook()
    {
        $results = array();
        $book1 = Book::createAndLoad(array( 'title' => 'Book1' ));
        $this->assertNotFalse($book1);

        $book2 = Book::createAndLoad(array( 'title' => 'Book2' ));
        $this->assertNotFalse($book2);

        $book = Book::load($book1->id);
        $this->assertNotFalse($book);
        $this->assertInstanceOf('AuthorBooks\Model\Book', $book);
        $this->assertEquals($book1->id, $book->id);


        $book = Book::load($book2->id);
        $this->assertNotFalse($book);
        $this->assertInstanceOf('AuthorBooks\Model\Book', $book);
        $this->assertEquals($book2->id, $book->id);
    }

    /**
     * TODO: Should we validate the field ? think again.
     *
     * @expectedException PDOException
     */
    public function testUpdateUnknownColumn()
    {
        // Column not found: 1054 Unknown column 'name' in 'where clause'
        $book = Book::load([ 'name' => 'LoadOrCreateTest' ]);
    }


    public function testChildrenRecords()
    {

        // Book::
    }



    public function testFlagHelper()
    {
        $b = Book::createAndLoad([ 'title' => 'Test Book' ]);

        $schema = $b->getSchema();
        $cA = $schema->getColumn('is_hot');
        $cB = $schema->getColumn('is_selled');
        $this->assertInstanceOf('Maghead\\Schema\\RuntimeColumn',$cA);
        $this->assertInstanceOf('Maghead\\Schema\\RuntimeColumn',$cB);

        $ret = $b->update([ 'is_hot' => true ]);
        $this->assertResultSuccess($ret);

        $ret = $b->update([ 'is_selled' => true ]);
        $this->assertResultSuccess($ret);

        $ret = $b->delete();
        $this->assertResultSuccess($ret);
    }

    public function testTraitMethods()
    {
        $b = new Book ;
        $this->assertSame(['link1', 'link2'], $b->getLinks());
        $this->assertSame(['store1', 'store2'], $b->getStores());
    }

    public function testInterface()
    {
        $this->assertInstanceOf('TestApp\ModelInterface\EBookInterface', new Book);
    }

    public function testLoadOrCreate()
    {
        $results = [];
        $b = new Book;

        $ret = $b->create(array( 'title' => 'Should Not Load This' ));
        $this->assertResultSuccess($ret);
        $results[] = $ret;
        $b = Book::masterRepo()->load($ret->key);

        $ret = $b->create(array( 'title' => 'LoadOrCreateTest' ));
        $this->assertResultSuccess($ret);
        $results[] = $ret;
        $b = Book::masterRepo()->load($ret->key);

        $id = $b->id;
        $this->assertNotNull($id);

        $b = $b->loadOrCreate(array( 'title' => 'LoadOrCreateTest'  ), 'title');
        $this->assertEquals($id, $b->id, 'is the same ID');

        $b2 = new Book ;
        $b2 = $b2->loadOrCreate(array( 'title' => 'LoadOrCreateTest'  ), 'title');
        $this->assertEquals($id, $b2->id);

        $b2 = $b2->loadOrCreate(array( 'title' => 'LoadOrCreateTest2'  ), 'title');
        $this->assertNotEquals($id, $b2->id, 'we should create anther one');

        $b3 = new Book ;
        $b3 = $b3->loadOrCreate(array( 'title' => 'LoadOrCreateTest3'  ), 'title');
        $this->assertNotNull($id, $b3->id, 'we should create anther one');

        $b3 = Book::masterRepo()->load($b3->getKey());
        $b3->delete();
    }

    public function testTypeConstraint()
    {
        $book = new Book ;
        $ret = Book::create(array(
            'title' => 'Programming Perl',
            'subtitle' => 'Way Way to Roman',
            'view' => '""',  /* cast this to null or empty */
            // 'publisher_id' => NULL,  /* cast this to null or empty */
        ));
        $this->assertResultSuccess($ret);
    }


    /**
     * @rebuild false
     */
    public function testRawSQL()
    {
        $book = Book::createAndLoad(array(
            'title' => 'book title',
            'view' => 0,
        ));
        $this->assertEquals(0, $book->view);

        $ret = $book->update([
            'view' => new Raw('view + 1')
        ]);
        $this->assertResultSuccess($ret);

        $book = Book::load($book->id);
        $this->assertEquals(1, $book->view);

        $book->update([
            'view' => new Raw('view + 3')
        ]);
        $book = Book::load($book->id);
        $this->assertEquals(4, $book->view);
    }


    public function testDateTimeValue()
    {
        $date = new DateTime;
        $book = Book::createAndLoad([ 'title' => 'Create With Time' , 'view' => 0, 'published_at' => $date ]);
        $this->assertInstanceOf('DateTime', $book->getPublishedAt());
        $this->assertEquals('00-00-00 00-00-00', $date->diff($book->getPublishedAt())->format('%Y-%M-%D %H-%I-%S'));
    }

    public function testUpdateOrCreateByPrimaryKey()
    {
        $args = [ 'title' => 'Create With Time' , 'view' => 0 ];
        $ret = Book::updateOrCreate($args);
        $this->assertResultSuccess($ret); // create

        /*
        $args[Book::PRIMARY_KEY] = $ret->key;

        $ret = Book::updateOrCreate($args);
        $this->assertResultSuccess($ret); // update
         */
    }


    public function testUpdateOrCreateOnTimestampColumn()
    {
        $date = new DateTime;

        $book = Book::createAndLoad([ 'title' => 'Create With Time' , 'view' => 0, 'published_at' => $date ]);
        $this->assertCount(1, new BookCollection);

        $id = $book->id;
        $this->assertNotNull($id);

        $book = Book::load([ 'published_at' => $date ]);
        $this->assertNotNull($book);

        $ret = Book::updateOrCreate([ 'title' => 'Update With Time' , 'view' => 0, 'published_at' => $date ], ['published_at']);
        $this->assertResultSuccess($ret);
        $this->assertCount(1, new BookCollection);

        // reload
        $book->reload();

        $this->assertEquals('Update With Time', $book->title);
        $this->assertEquals($id, $book->id);
    }

    public function testRecordRawCreateBook()
    {
        $ret = Book::rawCreate(array( 'title' => 'Go Programming' ));
        $this->assertResultSuccess($ret);
        $this->assertEquals(Result::TYPE_CREATE, $ret->type);

        $book = Book::load($ret->key);
        $this->assertNotNull($book->id);
    }

    public function testRecordRawUpdateBook()
    {
        $ret = Book::rawCreate([ 'title' => 'Go Programming without software validation' ]);
        $this->assertResultSuccess($ret);
        $this->assertNotNull($ret->key);


        $book = Book::load($ret->key);
        $ret = $book->rawUpdate([ 'title' => 'Perl Programming without filtering' ]);
        $this->assertResultSuccess($ret);
        $this->assertEquals(Result::TYPE_UPDATE, $ret->type);
    }

    /**
     * @rebuild false
     */
    public function testZeroInflator()
    {
        $book = Book::createAndLoad(array( 'title' => 'Create X' , 'view' => 0 ));
        $this->assertNotFalse($book);
        $this->assertNotNull($book->id);
        $this->assertEquals(0, $book->view);

        // Test incremental
        $ret = $book->update([ 'view'  => new Raw('view + 1') ]);
        $this->assertResultSuccess($ret);

        // verify update
        $book = Book::load($book->id);
        $this->assertEquals(1, $book->view);

        $ret = $book->update(array( 'view'  => new Raw('view + 1') ));
        $this->assertResultSuccess($ret);

        $book = Book::load($book->id);
        $this->assertEquals(2, $book->view);
    }

    /**
     * @expectedException PDOException
     */
    public function testTitleIsRequired()
    {
        $book = Book::load(array( 'name' => 'LoadOrCreateTest' ));
        $this->assertNotFalse($book);
        $this->assertNull($book->id);
    }
}
