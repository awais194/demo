<?php
/**
 * Restaurant website On / Off (rs_website_settings row id 1), managed from restaurant/website_status.php.
 *
 * Off: closed until switched on again. On: open, only between the opening and closing time when they are set,
 * and only on the branches ticked as open (rs_branches.website_open; an unticked branch is closed on its own).
 * A branch can have its own opening and closing time (rs_branches.open_time / close_time, e.g. one branch 8 PM to 2 AM,
 * another 6 PM to 2 AM); a branch without them follows the website's times.
 * (closing before opening = open past midnight). While closed the website shows a popup with the message and
 * the API refuses new orders; restaurant_bridge/api/index.php works out the same state (websiteStatus()).
 *
 * Discount: percent off every website order between two dates (either may be empty = no limit), on the deals too or
 * only on the dishes (discount_on_deals), on the branches ticked for it (rs_branches.discount_enabled). The API works out
 * the same rule (websiteDiscount()) and saves the discount on the order; the Website Invoice takes it as invoice discount.
 */
class RestaurantWebsite
{
    static function getSettings()
    {
        $Row = DBQM::fetch_assoc(DBQM::query("SELECT * FROM rs_website_settings WHERE id = 1"));

        return $Row ?: ['is_open' => 1, 'open_time' => null, 'close_time' => null, 'closed_message' => null,
                        'discount_percent' => 0, 'discount_from' => null, 'discount_to' => null, 'discount_on_deals' => 1];
    }

    /**
     * Saves the website discount: percent (0 = none, max 100), its dates ("2026-11-30" from an <input type="date">, or empty)
     * and whether deals get it too (false = dishes only)
     */
    static function saveDiscount($Percent, $From, $To, bool $OnDeals = true, ?array $BranchIds = null)
    {
        // Branches that give the discount; the website's other branches don't
        if ($BranchIds !== null)
        {
            $BranchIds = array_map('intval', $BranchIds);
            foreach (array_keys(self::getDiscountBranches()) as $BranchId)
                DBQM::query("UPDATE rs_branches SET discount_enabled = " . (in_array($BranchId, $BranchIds) ? 1 : 0) . " WHERE branch_id = " . escape($BranchId, 'int'));
        }

        $Percent = round(min(100, max(0, (float)$Percent)), 2);
        DBQM::query("
            INSERT INTO rs_website_settings (id, discount_percent, discount_from, discount_to, discount_on_deals)
            VALUES (1, " . escape($Percent) . ", " . self::dateSql($From) . ", " . self::dateSql($To) . ", " . ($OnDeals ? 1 : 0) . ")
            ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), discount_from = VALUES(discount_from), discount_to = VALUES(discount_to),
                                    discount_on_deals = VALUES(discount_on_deals)
        ");
    }

    /**
     * Branches the website offers: open on the website or closed, and whether each gives the website discount
     * @return array branch id => ['name', 'open' => bool (ticked), 'discount' => bool, 'open_time', 'close_time' (own hours or null)]
     */
    static function getWebsiteBranches()
    {
        $Branches = [];
        $Select = DBQM::query("SELECT b.id, b.name, rb.website_open, rb.discount_enabled, rb.open_time, rb.close_time FROM rs_branches rb JOIN branch b ON b.id = rb.branch_id
                               WHERE rb.show_on_website = 1 AND b.company_id = " . escape(Company::getLoggedInCompanyId(), 'int') . "
                               ORDER BY rb.sort_order, b.name");
        while ($Row = DBQM::fetch_assoc($Select))
            $Branches[(int)$Row['id']] = ['name' => $Row['name'], 'open' => (bool)$Row['website_open'], 'discount' => (bool)$Row['discount_enabled'],
                                          'open_time' => $Row['open_time'], 'close_time' => $Row['close_time']];

        return $Branches;
    }

    /** @return array branch id => ['name', 'enabled' => bool (gives the website discount)] */
    static function getDiscountBranches()
    {
        return array_map(fn($b) => ['name' => $b['name'], 'enabled' => $b['discount']], self::getWebsiteBranches());
    }

    private static function dateSql($Date)
    {
        $Date = trim((string)$Date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $Date) && strtotime($Date) ? escape($Date) : 'null';
    }

    /**
     * The discount running today
     * @return array ['percent' => float (0 = none), 'until' => 'Y-m-d' or null, 'on_deals' => bool, 'state' => 'active' | 'scheduled' | 'ended' | 'off']
     */
    static function getDiscount()
    {
        $Settings = self::getSettings();
        $Percent = (float)($Settings['discount_percent'] ?? 0);
        $From = $Settings['discount_from'] ?? null;
        $To = $Settings['discount_to'] ?? null;
        $Today = date('Y-m-d');

        $State = 'active';
        if ($Percent <= 0)
            $State = 'off';
        elseif ($From && $Today < $From)
            $State = 'scheduled';
        elseif ($To && $Today > $To)
            $State = 'ended';

        return ['percent' => $State == 'active' ? $Percent : 0.0, 'until' => $To, 'on_deals' => (bool)($Settings['discount_on_deals'] ?? 1), 'state' => $State];
    }

    /**
     * @param array|null $OpenBranchIds branches open on the website (the others show the closed popup)
     * @param array $BranchOpenTimes [branch id => "20:00" or ""], $BranchCloseTimes the same: a branch's own hours
     */
    static function save(bool $isOpen, $OpenTime, $CloseTime, $ClosedMessage, ?array $OpenBranchIds = null, array $BranchOpenTimes = [], array $BranchCloseTimes = [])
    {
        if ($OpenBranchIds !== null)
        {
            $OpenBranchIds = array_map('intval', $OpenBranchIds);
            foreach (array_keys(self::getWebsiteBranches()) as $BranchId)
                DBQM::query("UPDATE rs_branches SET website_open = " . (in_array($BranchId, $OpenBranchIds) ? 1 : 0) . ",
                                                    open_time = " . self::timeSql($BranchOpenTimes[$BranchId] ?? '') . ",
                                                    close_time = " . self::timeSql($BranchCloseTimes[$BranchId] ?? '') . "
                             WHERE branch_id = " . escape($BranchId, 'int'));
        }

        DBQM::query("
            INSERT INTO rs_website_settings (id, is_open, open_time, close_time, closed_message)
            VALUES (1, " . ($isOpen ? 1 : 0) . ", " . self::timeSql($OpenTime) . ", " . self::timeSql($CloseTime) . ", " . escape(trim($ClosedMessage), 'text', true) . ")
            ON DUPLICATE KEY UPDATE is_open = VALUES(is_open), open_time = VALUES(open_time), close_time = VALUES(close_time), closed_message = VALUES(closed_message)
        ");
    }

    /** "18:00" from an <input type="time">, or null */
    private static function timeSql($Time)
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim((string)$Time)) ? escape(trim($Time) . ':00') : 'null';
    }

    /** "6:00 PM", or '' */
    static function formatTime($Time)
    {
        return empty($Time) ? '' : date('g:i A', strtotime($Time));
    }

    /**
     * What the website shows right now
     * @return array ['open' => bool, 'message' => string]
     */
    static function getStatus()
    {
        $Settings = self::getSettings();
        $isOpen = $Settings['is_open'] && self::isWithin($Settings['open_time'], $Settings['close_time']);

        return ['open' => (bool)$isOpen, 'message' => $isOpen ? '' : self::getClosedMessage($Settings)];
    }

    /** Now between these times? Empty = all day; closing before opening = past midnight. Same rule as the API. */
    static function isWithin($OpenTime, $CloseTime)
    {
        $Now = date('H:i:s');
        $Open = $OpenTime ?: '00:00:00';
        $Close = $CloseTime ?: '24:00:00';

        return $Open <= $Close ? ($Now >= $Open && $Now < $Close) : ($Now >= $Open || $Now < $Close);
    }

    /**
     * A branch right now: website switched off, branch unticked, or outside its hours (own times, else the website's)
     * @param array $Branch a row of getWebsiteBranches()
     * @return array ['open' => bool, 'hours' => "8:00 PM - 2:00 AM" or "All day", 'own_hours' => bool]
     */
    static function getBranchStatus(array $Branch)
    {
        $Settings = self::getSettings();
        $OwnHours = !empty($Branch['open_time']) || !empty($Branch['close_time']);
        $OpenTime = $OwnHours ? $Branch['open_time'] : $Settings['open_time'];
        $CloseTime = $OwnHours ? $Branch['close_time'] : $Settings['close_time'];

        return [
            'open' => $Settings['is_open'] && $Branch['open'] && self::isWithin($OpenTime, $CloseTime),
            'hours' => ($OpenTime || $CloseTime) ? (self::formatTime($OpenTime ?: '00:00:00') . ' - ' . self::formatTime($CloseTime ?: '23:59:59')) : 'All day',
            'own_hours' => $OwnHours,
        ];
    }

    static function getClosedMessage(array $Settings)
    {
        if (trim((string)$Settings['closed_message']) !== '')
            return trim($Settings['closed_message']);

        return $Settings['open_time']
            ? "We're closed right now. Kindly visit at " . self::formatTime($Settings['open_time']) . "."
            : "We're closed right now. Kindly visit again later.";
    }
}
