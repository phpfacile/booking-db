<?php
namespace PHPFacile\Booking\Db\Service;

use PHPFacile\Booking\Service\BookingExtraDataService as DefaultBookingExtraDataService;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;

class BookingExtraDataFieldValueService extends DefaultBookingExtraDataService
{
    /**
     * Database adapter
     *
     * @var AdapterInterface $adapter
     */
    protected $adapter;

    /**
     * Name of the table where extra data are stored
     *
     * @var string $tableName
     */
    protected $tableName = 'bookings';

    /**
     * Whether the extra data are stored in the main table or not
     *
     * @var boolean $isTablesMerged
     */
    protected $isTablesMerged;

    /**
     * Name of the table field where is stored the id of the booking set
     *
     * @var integer $columnBookingId
     */
    protected $columnBookingSetId;

    /**
     * Constructor
     *
     * @param AdapterInterface $adapter           Database adapter
     * @param array            $bookingMappingCfg Configuration for custom database field names
     */
    public function __construct(AdapterInterface $adapter, $bookingMappingCfg)
    {
        // parent::__construct();
        $this->adapter            = $adapter;
        $this->columnBookingSetId = 'booking_set_id';
        if (true === array_key_exists('bookings', $bookingMappingCfg)) {
            if (true === array_key_exists('resource', $bookingMappingCfg['bookings'])) {
                $this->tableName = $bookingMappingCfg['bookings']['resource'];
            }

            /*
                Comment
                if (array_key_exists('fields', $bookingMappingCfg['bookings'])) {
                    if (array_key_exists('pool_id', $bookingMappingCfg['bookings']['fields'])) {
                        $this->fieldPoolId = $bookingMappingCfg['bookings']['fields']['pool_id'];
                    }
                }
            */
        }

        $this->isTablesMerged = true;
        if (false === $this->isTablesMerged) {
            $this->tableName = 'booking_extra_datas';
        }
    }

    /**
     * Stores extra data along with the booking data
     *
     * @param array      $extraData    Additionnal data to store on booking as an associative array [table field => value]
     * @param int|string $bookingSetId Identifier of the booking
     *
     * @return void
     */
    public function insertExtraData($extraData, $bookingSetId)
    {
        // Do not raise any exception if $extraData is null.
        // This is an allowed value.
        if (null === $extraData) {
            return;
        }

        $sql = new Sql($this->adapter);
        if (true === $this->isTablesMerged) {
            $query = $sql
                ->update($this->tableName)
                ->set($extraData)
                ->where([$this->columnBookingSetId => $bookingSetId]);
        } else {
            $query = $sql
                ->insert($this->tableName)
                ->values(array_merge($extraData, [$this->columnBookingSetId => $bookingSetId]));
        }

        $stmt = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
    }

    /**
     * Updates extra data stored along with the booking data
     *
     * @param mixed      $extraData    Additionnal data to be updated for the booking as an associative array [table field => value]
     * @param int|string $bookingSetId Identifier of the booking
     *
     * @return void
     */
    public function updateExtraData($extraData, $bookingSetId)
    {
        // Do not raise any exception if $extraData is null.
        // This is an allowed value.
        if (null === $extraData) {
            return;
        }

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->update($this->tableName)
            ->set($extraData)
            ->where([$this->columnBookingSetId => $bookingSetId]);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
    }

    /**
     * Deletes extra data stored along with the booking data
     *
     * @param int|string $bookingSetId Identifier of the booking
     *
     * @return void
     */
    public function deleteExtraData($bookingSetId)
    {
        throw new \Exception('No.... don\'t delete the line in case of mergedTables');

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->delete($this->tableName)
            ->where([$this->columnBookingSetId => $bookingSetId]);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
    }
}
