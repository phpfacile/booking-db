<?php
namespace PHPFacile\Booking\Db\Service;

use PHPFacile\Booking\Service\BookingService as AbstractBookingService;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;

class BookingService extends AbstractBookingService
{
    /**
     * Database adapter
     *
     * @var AdapterInterface $adapter
     */
    protected $adapter;

    /**
     * Name of the table where booking data are stored
     *
     * @var string $tableName
     */
    protected $tableName = 'bookings';

    /**
     * Name of the table field where id of the pool is stored
     *
     * @var string $fieldPoolId
     */
    protected $fieldPoolId = 'pool_id';

    /**
     * Name of the table field where id of the set of booking is stored
     *
     * @var string $fieldBookingSetId
     */
    protected $fieldBookingSetId = 'booking_set_id';

    /**
     * Name of the table field where the status is stored
     *
     * @var string $fieldStatus
     */
    protected $fieldStatus = 'status';

    /**
     * Name of the table field where last status date time is stored
     *
     * @var string $fieldStatusLastUpdateDateTimeUTC
     */
    protected $fieldStatusLastUpdateDateTimeUTC = 'status_datetime_utc';

    /**
     * Constructor
     *
     * @param AdapterInterface $adapter           Database adapter
     * @param array            $bookingMappingCfg Configuration for custom database field names
     */
    public function __construct(AdapterInterface $adapter, $bookingMappingCfg = [])
    {
        parent::__construct();
        $this->adapter = $adapter;

        if (true === array_key_exists('bookings', $bookingMappingCfg)) {
            if (true === array_key_exists('resource', $bookingMappingCfg['bookings'])) {
                $this->tableName = $bookingMappingCfg['bookings']['resource'];
            }

            if (true === array_key_exists('fields', $bookingMappingCfg['bookings'])) {
                if (true === array_key_exists('pool_id', $bookingMappingCfg['bookings']['fields'])) {
                    $this->fieldPoolId = $bookingMappingCfg['bookings']['fields']['pool_id'];
                }
            }
        }
    }

    /**
     * Returns the database adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Returns the name of the table where are stored the booking data
     *
     * @return string
     */
    public function getBookingTableName()
    {
        return $this->tableName;
    }

    /**
     * Returns the nb of bookings (i.e. items booked) for a given pool (i.e. pool of bookable items) using the pool id
     *
     * @param integer|string $poolId Id of the pool
     * @param mixed          $filter Filter to count only items matching the filter rules
     *
     * @return integer
     */
    protected function getNbBookingsByPoolId($poolId, $filter = null)
    {
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select($this->tableName)
            ->columns(['c' => new Expression('COUNT(*)')])
            ->where([$this->fieldPoolId => $poolId]);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $rows  = $stmt->execute();
        $row   = $rows->current();
        return $row['c'];
    }

    /**
     * Books (add booking data)
     *
     * @param integer|string $poolId       Id of the pool (of items from which a booking is performed)
     * @param integer|string $userId       Id of the user
     * @param string         $status       Any of the BookingServiceInterface::BOOKING_STATUS_*
     * @param integer|string $bookingSetId Id of the booking set (useful when there are several bookings at the same time for the same user)
     *
     * @return integer|string $bookingId Id of the booking (reservation)
     */
    protected function addBookingForPoolIdByUserId($poolId, $userId, $status, $bookingSetId)
    {
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->insert($this->tableName)
            ->values(
                [
                    $this->fieldPoolId       => $poolId,
                    'user_id'                => $userId,
                    $this->fieldStatus       => $status,
                    $this->fieldBookingSetId => $bookingSetId,
                    // TODO Add datetimes
                ]
            );
        $stmt = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
        return $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Books (add booking data) but provide no status
     *
     * @param integer|string $poolId       Id of the pool (of items from which a booking is performed)
     * @param integer|string $userId       Id of the user
     * @param integer|string $bookingSetId Id of the booking set (useful when there are several bookings at the same time for the same user)
     *
     * @return integer|string $bookingId Id of the booking (reservation)
     */
    protected function addBookingForPoolIdByUserIdWithNoStatus($poolId, $userId, $bookingSetId)
    {
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->insert($this->tableName)
            ->values(
                [
                    $this->fieldPoolId       => $poolId,
                    'user_id'                => $userId,
                     // FIXME Should allow $bookingSetId to be the same as 'user_id' in case... it's not really the user_id
                    $this->fieldBookingSetId => $bookingSetId,
                    // TODO Add datetimes
                ]
            );
        $stmt = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
        return $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Update the status of a booking (ex: pre-reservation -> reservation)
     *
     * @param integer|string $bookingSetId Id of the booking set (useful when there are several bookings at the same time for the same user)
     * @param string         $status       Any of the BookingServiceInterface::BOOKING_STATUS_*
     *
     * @return void
     */
    protected function updateBookingStatusForBookingSetId($bookingSetId, $status)
    {
        // FIXME Only valid for Sqlite vendor
        $now = new Expression('strftime(\'%Y-%m-%d %H:%M:%S\',\'now\')');

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->update($this->tableName)
            ->set(
                [
                    $this->fieldStatus                      => $status,
                    $this->fieldStatusLastUpdateDateTimeUTC => $now,
                ]
            )
            ->where(
                [
                    $this->fieldBookingSetId => $bookingSetId,
                ]
            );
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
    }

}
