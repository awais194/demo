<?php

/**
 * Delivery or pickup of a Website (WIV) / WhatsApp (WHIV) Invoice, its delivery area and the delivery charges the rider
 * keeps (sl_sales_order_info, one row per invoice). The charge comes from the area's setting (Restaurant > Delivery Areas).
 * Delivery charges are printed on the bill (pos/print_voucher.php) but are not part of the invoice: not posted to Sales
 * (shown in its own column in pos/report_sales.php, outside Voucher Total). Saved by RestaurantOrder::postToSales() (WIV) and pos/op_save_pos.php (WHIV).
 */
class SalesOrderInfo
{
    const TYPE_DELIVERY = 'delivery';
    const TYPE_PICKUP = 'pickup';

    /** Invoices that carry delivery / pickup */
    static function isOrderInfoVoucherType($VoucherTypeId)
    {
        return in_array((int)$VoucherTypeId, [VoucherType::WIV, VoucherType::WHIV]);
    }

    static function getTypes()
    {
        return [self::TYPE_DELIVERY => 'Delivery', self::TYPE_PICKUP => 'Pickup'];
    }

    static function save($SalesId, $OrderType, $DeliveryCharges, $AreaId = null, $AreaName = null)
    {
        if (empty($SalesId))
            return;

        $OrderType = $OrderType == self::TYPE_PICKUP ? self::TYPE_PICKUP : self::TYPE_DELIVERY;
        // Pickup has no rider and no area
        $isPickup = $OrderType == self::TYPE_PICKUP;
        $DeliveryCharges = $isPickup ? 0 : round(max(0, (float)$DeliveryCharges), 2);

        DBQM::query("REPLACE INTO sl_sales_order_info (sales_id, order_type, delivery_area_id, delivery_area, delivery_charges)
                     VALUES (" . escape($SalesId, 'int') . ", " . escape($OrderType) . ",
                             " . ($isPickup || empty($AreaId) ? 'NULL' : escape($AreaId, 'int')) . ",
                             " . ($isPickup || trim((string)$AreaName) === '' ? 'NULL' : escape(mb_substr(trim($AreaName), 0, 120))) . ",
                             " . escape($DeliveryCharges, 'double') . ")");
    }

    /** @return array ['order_type' => 'delivery'|'pickup'|'', 'delivery_area_id' => int|null, 'delivery_area' => string, 'delivery_charges' => float] */
    static function get($SalesId)
    {
        $Row = empty($SalesId) ? null : DBQM::fetch_assoc(DBQM::query("SELECT order_type, delivery_area_id, delivery_area, delivery_charges FROM sl_sales_order_info WHERE sales_id = " . escape($SalesId, 'int')));

        return $Row
            ? ['order_type' => $Row['order_type'], 'delivery_area_id' => $Row['delivery_area_id'] ? (int)$Row['delivery_area_id'] : null, 'delivery_area' => (string)$Row['delivery_area'], 'delivery_charges' => (float)$Row['delivery_charges']]
            : ['order_type' => '', 'delivery_area_id' => null, 'delivery_area' => '', 'delivery_charges' => 0.0];
    }

    /**
     * Active delivery areas of every branch (Restaurant > Delivery Areas), for the WhatsApp Invoice
     * @return array area id => ['id', 'branch_id', 'name', 'branch_name', 'charge', 'free_over' (null = never free)]
     */
    static function getAreas()
    {
        $Areas = [];
        if (DatabaseHelper::isRecordSetEmpty(DBQM::query("SHOW TABLES LIKE 'rs_delivery_areas'")))
            return $Areas;

        $Select = DBQM::query("SELECT a.id, a.branch_id, a.name, a.delivery_charge, a.free_over, b.name AS branch_name
                               FROM rs_delivery_areas a LEFT JOIN branch b ON b.id = a.branch_id
                               WHERE a.is_active = 1 ORDER BY b.name, a.sort_order, a.name");
        while ($Row = DBQM::fetch_assoc($Select))
            $Areas[(int)$Row['id']] = ['id' => (int)$Row['id'], 'branch_id' => (int)$Row['branch_id'], 'name' => $Row['name'], 'branch_name' => (string)$Row['branch_name'],
                                       'charge' => (float)$Row['delivery_charge'], 'free_over' => $Row['free_over'] === null ? null : (float)$Row['free_over']];

        return $Areas;
    }

    /** Area's charge for a bill of this amount: free from its "free over" amount (same rule as the website API) */
    static function chargeFor(array $Area, $Amount)
    {
        return ($Area['free_over'] !== null && $Amount >= $Area['free_over']) ? 0.0 : $Area['charge'];
    }

    /**
     * Rows of many invoices at once (reports)
     * @param array $SalesIds
     * @return array sales id => ['order_type', 'delivery_area', 'delivery_charges']
     */
    static function getMany(array $SalesIds)
    {
        $SalesIds = array_filter(array_map('intval', $SalesIds));
        if (!$SalesIds)
            return [];

        $Rows = [];
        $Select = DBQM::query("SELECT sales_id, order_type, delivery_area, delivery_charges FROM sl_sales_order_info WHERE sales_id IN (" . implode(', ', $SalesIds) . ")");
        while ($Row = DBQM::fetch_assoc($Select))
            $Rows[(int)$Row['sales_id']] = ['order_type' => $Row['order_type'], 'delivery_area' => (string)$Row['delivery_area'], 'delivery_charges' => (float)$Row['delivery_charges']];

        return $Rows;
    }

    static function delete($SalesId)
    {
        DBQM::query("DELETE FROM sl_sales_order_info WHERE sales_id = " . escape($SalesId, 'int'));
    }
}
