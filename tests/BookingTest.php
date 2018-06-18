<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

use PHPFacile\Booking\Db\Service\BookingService;
use PHPFacile\Booking\Db\Service\BookingExtraDataFieldValueService;
use PHPFacile\Booking\Quota\Service\NoQuotaService;
use PHPFacile\Booking\Quota\Db\Service\QuotaService;
use PHPFacile\Booking\Service\BookingServiceInterface;

final class BookingTest extends TestCase
{
    use TestCaseTrait {
        TestCaseTrait::setUp as parentSetUp;
    }

    protected $adapter;
    protected $dbName;
    protected $connection;
    protected $bookingService;
    protected $bookingExtraDataService;

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__.'/db-init.xml');
    }

/*
    public function getConnection()
    {
        putenv('APP_ENV=development');
        // Inclure APRES avoir positionné la variable d'environnement
        $configArray = include __DIR__ . '/../../config/autoload/local.php';
        $params = $configArray['db']['adapters']['PHPFacile\Booking'];
        $pdo = new PDO('mysql:dbname='.$params['dbname'],
                       $params['username'],
                       $params['password'],
                       $params['driver_options']);
        return $this->createDefaultDBConnection($pdo, $params['dbname']);
    }

    protected function setUp()
    {
        //parent::setUp(); // Required so as to rebuild the database (thanks to getDataSet()) but doesn't work like this in case of use of Trait
        $this->parentSetUp(); // Replacement for parent::setUp() in case of use of Trait
        putenv('APP_ENV=development');
        // Inclure APRES avoir positionné la variable d'environnement
        $configArray = include __DIR__ . '/../../config/autoload/local.php';
        $this->adapter = new Zend\Db\Adapter\Adapter($configArray['db']['adapters']['PHPFacile\Booking']);
    }
*/

    public function getConnection()
    {
        /*if (null === $this->connection) {
            if (null === $this->adapter) {
                if (null === $this->dbName) {
                    $this->dbName = '/tmp/parser_storage_test_'.date('YmdHid').'.sqlite';
                    copy(__DIR__.'/ref_database.sqlite', $this->dbName);
                }
                $config = [
                    'driver' => 'Pdo_Sqlite',
                    'database' => $this->dbName,
                ];
                $this->adapter = new Zend\Db\Adapter\Adapter($config);
            }
            $this->connection = $this->adapter->getDriver()->getConnection();
        }
        return $this->connection;*/
        if (null === $this->dbName) {
            $this->dbName = '/tmp/booking_test_'.date('YmdHid').'.sqlite';
            copy(__DIR__.'/ref_database.sqlite', $this->dbName);
        }
        $pdo = new PDO('sqlite:'.$this->dbName);
        return $this->createDefaultDBConnection($pdo, $this->dbName);
    }

    protected function setUp()
    {
        //parent::setUp(); // Required so as to rebuild the database (thanks to getDataSet()) but doesn't work like this in case of use of Trait
        $this->parentSetUp(); // Replacement for parent::setUp() in case of use of Trait
        if (null === $this->dbName) {
            $this->dbName = '/tmp/booking_test_'.date('YmdHid').'.sqlite';
            copy(__DIR__.'/ref_database.sqlite', $this->dbName);
        }
        $config = [
            'driver' => 'Pdo_Sqlite',
            'database' => $this->dbName,
        ];
        $this->adapter = new Zend\Db\Adapter\Adapter($config);

        $dbConfig = [
            'bookings' => [
                'resource' => 'bookings',
                'fields' => [
                    'pool_id' => 'pool_id',
                ]
            ]
        ];

        $this->bookingService = new BookingService($this->adapter);

        $quotaService = new QuotaService($this->adapter, $dbConfig);
        $quotaService->setPoolBasicQuota(2, 3); // No more than 3 booking allowed for pool 2
        $this->bookingService->setQuotaService($quotaService);

        $this->bookingServiceWithExtraData = clone $this->bookingService;

        $bookingExtraDataService = new BookingExtraDataFieldValueService($this->adapter, $dbConfig);
        $this->bookingServiceWithExtraData->setBookingExtraDataService($bookingExtraDataService);
        $quotaService = new NoQuotaService();
        $this->bookingServiceWithExtraData->setQuotaService($quotaService);
        /*$filter = new Priority(Logger::DEBUG);
        $writer = new Stream('/tmp/booking-db_'.date('Ymd').'.log');
        $writer->addFilter($filter);
        $this->logger = new Logger();
        $this->logger->addWriter($writer);*/
    }

    /**
     * @testdox The library must be able to return the nb of booked "units" (whatever the status is: pre-reserved, payed, etc.) for a given "pool" id
     */
    public function testGetNbOfBookingsForAPoolId()
    {
        $poolId = 2;
        $nb = $this->bookingService->getNbBookings($poolId);
        $this->assertEquals(3, $nb);
    }

    /**
     * @testdox The library must allow to book an undefined "unit" (ex: inscription) in a given "pool" (ex: course) thanks to a booker id and a "pool" id
     */
    public function testBookAPoolIdByBookerId()
    {
        $bookerId = 2;
        $poolId = 3;
        $this->assertEquals(0, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\''));
        $this->bookingService->book($poolId, $bookerId);
        // Yes it seems to be booked
        $this->assertEquals(1, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\''));
        // Yes it's booked and the status is as expected
        $this->assertEquals(1, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\' AND status=\''.BookingServiceInterface::BOOKING_STATUS_BOOKED.'\''));
    }

    /**
     * @testdox The library must prevent to book an undefined "unit" in a given "pool" thanks to a booker id and a "pool" id if the quota for this pool is reached
     */
    public function testBookAFullPoolIdByBookerId()
    {
        $bookerId = 2;
        $poolId = 2;
        $this->expectException(\PHPFacile\Booking\Exception\NoMoreUnitAvailableException::class);
        $this->bookingService->book($poolId, $bookerId);
    }

    /**
     * @testdox The library must allow to book a specific "unit" (ex: seat n°123) of a "pool" (ex: a concert) thanks to a booker id, a  "pool" id and a "unit" id. Assuming the project or pool allows booking a given unit
     */
    public function testBookingAGivenUnitIdInAPoolIdByBookerIdMustBeAllowed()
    {
        $bookerId = 2;
        $bookingOrder = new \StdClass();
        $bookingOrder->type = 'unit';
        $bookingOrder->poolId = 3;
        $bookingOrder->unitId = 123;
        $this->bookingService->book($bookingOrder, $bookerId);
        $this->assertTrue(false);
    }

    /**
     * @testdox The library must allow to book a specific "unit" (ex: seat n°123) of a "pool" (ex: a concert) thanks to a booker id, a  "pool" id and a "unit" id. Assuming the project or pool allows booking a given unit
     */
    public function testBookingASetInAPoolIdByBookerIdMustBeAllowed()
    {
        $bookerId = 2;
        $bookingOrder = new \StdClass();
        // book 2 "units" in pool defined by Id
        $bookingOrder->type = 'set';
        $bookingOrder->poolId = 3;
        $bookingOrder->quantity = 2;
        $this->bookingService->book($bookingOrder, $bookerId);
        $this->assertTrue(false);
    }

    /**
    * @testdox The library must allow to book an undefined "unit" in a given "pool" thanks to a booker alternative id (not internal id) and a "pool" id
     */
    public function testBookAPoolIdByBookerAlternativeId()
    {
        $booker = new \StdClass();
        $booker->id = new \StdClass();
        $booker->id->value = 'foo';
        $booker->id->dataSource = 'facebook';
        $poolId = 3;
        $this->bookingService->book($poolId, $booker);
    }

    /**
     * @testdox The library must allow to book an undefined "unit" (ex: inscription) in a given "pool" (ex: course) with a given context data (ex: as begginner if the course allows both subscription of begginer and intermediaite) thanks to a booker id and a "pool" id
     */
    public function testBookingAPoolIdByBookerIdWithBookingExtraInfoMustBePossible()
    {
        $bookerId = 2;
        $poolId = 3;
        $extra = ['booking_type' => 2];
        $this->assertEquals(0, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\''));
        $this->assertEquals(0, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\' AND booking_type=\''.$extra['booking_type'].'\''));
        $this->bookingServiceWithExtraData->book($poolId, $bookerId, $extra);
        $this->assertEquals(1, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\''));
        // Yes it's booked and the extra data are successully stored
        $this->assertEquals(1, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\' AND booking_type=\''.$extra['booking_type'].'\''));
        // Yes it's booked and the status is as expected
        $this->assertEquals(1, $this->getConnection()->getRowCount('bookings', 'user_id=\''.$bookerId.'\' AND pool_id=\''.$poolId.'\' AND status=\''.BookingServiceInterface::BOOKING_STATUS_BOOKED.'\''));
    }

    public function testBookingAPoolIdByBookerIdWithNoBookingExtraInfoMustCallBookingExtraInfoServiceIfThereIsOneDefined()
    {
        $bookerId = 2;
        $poolId = 3;
        $extra = null;
        // FIXME To test this, we have to "plug" a bookingExtraDataService
        // that store something even if $extra = null
        $this->bookingServiceWithExtraData->book($poolId, $bookerId, $extra);
        // TODO Check DB content
        $this->markTestSkipped('Not yet implemented');
    }

    public function testBookingAPoolIdByBookerIdWithBookingExtraInfoMustRaiseAnExceptionIfThereIsNoBookingExtraInfoService()
    {
        $bookerId = 2;
        $poolId = 3;
        $extra = ['booking_type' => 2];
        $this->expectException(\Exception::class);
        $this->bookingService->book($poolId, $bookerId, $extra);
    }
}
