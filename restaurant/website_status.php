<?
include_once("../connection/localhost.php");

$_GET['_page_title'] = 'Website On / Off';
$_GET['_page_title_text'] = 'When customers can visit and order on the restaurant website';

include_once("../includes/inc_header.php");

$ActiveTab = 'website';
include_once(__DIR__ . '/_tabs.php');

function h($Value) { return htmlspecialchars((string)$Value, ENT_QUOTES, 'UTF-8'); }

$Settings = RestaurantWebsite::getSettings();
$Status = RestaurantWebsite::getStatus();
$Discount = RestaurantWebsite::getDiscount();
$DiscountBranches = RestaurantWebsite::getDiscountBranches();
$WebsiteBranches = RestaurantWebsite::getWebsiteBranches();
$DiscountBranchNames = array_column(array_filter($DiscountBranches, fn($b) => $b['enabled']), 'name');
$DiscountBadges = [
    'active' => ['success', 'Running now'],
    'scheduled' => ['info', 'Starts ' . ($Settings['discount_from'] ? date('M j, Y', strtotime($Settings['discount_from'])) : '')],
    'ended' => ['secondary', 'Ended'],
    'off' => ['secondary', 'No discount'],
];
?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="d-flex align-items-center">
                <h3 class="card-title mb-0">Website On / Off</h3>
                <? if ($Status['open']) { ?>
                    <span class="badge badge-soft-success ml-auto"><i class="mdi mdi-circle-medium"></i> Open now</span>
                <? } else { ?>
                    <span class="badge badge-soft-danger ml-auto"><i class="mdi mdi-circle-medium"></i> Closed now</span>
                <? } ?>
            </div>
            <p class="text-muted mt-2">While the website is closed, customers see a popup with the message below and can't order. Set opening and closing times to open and close the website every day by itself.</p>
            <form method="post" action="op_save_website_status.php">
                <div class="form-group">
                    <div class="custom-control custom-switch" dir="ltr">
                        <input type="checkbox" class="custom-control-input" id="is_open" name="is_open" value="Y" <?=$Settings['is_open'] ? 'checked' : ''?>>
                        <label class="custom-control-label font-weight-bold" for="is_open">Website is On</label>
                    </div>
                    <small class="text-muted">Switch off to close the website until you switch it on again.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold d-block">Branches</label>
                    <? if (!$WebsiteBranches) { ?>
                        <small class="text-muted">No branch is on the website yet (Restaurant &gt; Branches).</small>
                    <? } ?>
                    <? if ($WebsiteBranches) { ?>
                        <table class="table table-sm mb-1">
                            <thead><tr><th>Open on website</th><th>Opening Time</th><th>Closing Time</th></tr></thead>
                            <tbody>
                            <? foreach ($WebsiteBranches as $BranchId => $Branch) { ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="open_branch_<?=$BranchId?>" name="open_branches[]" value="<?=$BranchId?>" <?=$Branch['open'] ? 'checked' : ''?>>
                                            <label class="custom-control-label" for="open_branch_<?=$BranchId?>"><?=h($Branch['name'])?></label>
                                        </div>
                                    </td>
                                    <td><input type="time" name="branch_open_time[<?=$BranchId?>]" value="<?=h(substr((string)$Branch['open_time'], 0, 5))?>"></td>
                                    <td><input type="time" name="branch_close_time[<?=$BranchId?>]" value="<?=h(substr((string)$Branch['close_time'], 0, 5))?>"></td>
                                </tr>
                            <? } ?>
                            </tbody>
                        </table>
                    <? } ?>
                    <small class="text-muted d-block">Only ticked branches take orders; customers of a closed branch see the closed popup and can pick another branch. A branch's own times (e.g. 8:00 PM to 2:00 AM) replace the website's times below for that branch; leave them empty to follow the website's times.</small>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="open_time">Opening Time</label>
                            <input type="time" name="open_time" id="open_time" value="<?=h(substr((string)$Settings['open_time'], 0, 5))?>">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="close_time">Closing Time</label>
                            <input type="time" name="close_time" id="close_time" value="<?=h(substr((string)$Settings['close_time'], 0, 5))?>">
                        </div>
                    </div>
                </div>
                <small class="text-muted d-block mb-3">Leave both empty to keep the website open all day. A closing time before the opening time (e.g. 6:00 PM to 2:00 AM) runs past midnight.</small>
                <div class="form-group">
                    <label for="closed_message">Popup Message</label>
                    <input type="text" name="closed_message" id="closed_message" maxlength="255" value="<?=h($Settings['closed_message'])?>"
                           placeholder="<?=h(RestaurantWebsite::getClosedMessage(['closed_message' => '', 'open_time' => $Settings['open_time'] ?: '18:00:00']))?>">
                    <small class="text-muted">Leave empty for: "We're closed right now. Kindly visit at (opening time)."</small>
                </div>
                <div class="text-right">
                    <button class="btn button color" type="submit"><i class="mdi mdi-content-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <h3 class="card-title">What customers see now</h3>
            <? if ($Status['open']) { ?>
                <p class="mb-0"><i class="mdi mdi-check-circle text-success"></i> The website is open for orders<?=$Settings['close_time'] ? ' until ' . h(RestaurantWebsite::formatTime($Settings['close_time'])) : ''?>.</p>
                <? foreach ($WebsiteBranches as $Branch) { $BranchStatus = RestaurantWebsite::getBranchStatus($Branch); ?>
                    <div class="mt-1">
                        <i class="mdi mdi-store-outline"></i> <?=h($Branch['name'])?>:
                        <? if ($BranchStatus['open']) { ?><span class="badge badge-soft-success">Open now</span><? } else { ?><span class="badge badge-soft-danger">Closed now</span><? } ?>
                        <small class="text-muted"><?=$Branch['open'] ? h($BranchStatus['hours']) . ($BranchStatus['own_hours'] ? '' : ' (website times)') : 'switched off'?></small>
                    </div>
                <? } ?>
            <? } else { ?>
                <div class="border rounded p-3 text-center">
                    <div style="font-size: 32px">🕕</div>
                    <b>We're closed</b>
                    <p class="text-muted mb-0 mt-1"><?=h($Status['message'])?></p>
                </div>
            <? } ?>
            <? if ($Discount['percent'] > 0 && $DiscountBranchNames) { ?>
                <p class="mb-0 mt-2"><i class="mdi mdi-sale text-success"></i> <b><?=h((float)$Discount['percent'])?>% off</b> at <?=h(implode(', ', $DiscountBranchNames))?>, every order<?=$Discount['on_deals'] ? '' : ' (deals excluded)'?><?=$Discount['until'] ? ' till ' . h(date('M j, Y', strtotime($Discount['until']))) : ''?>.</p>
            <? } ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="d-flex align-items-center">
                <h3 class="card-title mb-0">Website Discount</h3>
                <span class="badge badge-soft-<?=$DiscountBadges[$Discount['state']][0]?> ml-auto"><i class="mdi mdi-circle-medium"></i> <?=h($DiscountBadges[$Discount['state']][1])?></span>
            </div>
            <p class="text-muted mt-2">Percent off every website order (delivery charges are not discounted). Choose below whether deals get it too. The website shows the offer, and the Website Invoice gets it as invoice discount.</p>
            <form method="post" action="op_save_website_status.php">
                <input type="hidden" name="section" value="discount">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="discount_percent">Discount %</label>
                            <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" step="0.01" value="<?=h((float)($Settings['discount_percent'] ?? 0) ?: '')?>" placeholder="e.g. 10">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="discount_from">From Date</label>
                            <input type="date" name="discount_from" id="discount_from" value="<?=h($Settings['discount_from'] ?? '')?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label for="discount_to">Till Date</label>
                            <input type="date" name="discount_to" id="discount_to" value="<?=h($Settings['discount_to'] ?? '')?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-switch" dir="ltr">
                        <input type="checkbox" class="custom-control-input" id="discount_on_deals" name="discount_on_deals" value="Y" <?=($Settings['discount_on_deals'] ?? 1) ? 'checked' : ''?>>
                        <label class="custom-control-label font-weight-bold" for="discount_on_deals">Apply on deals too</label>
                    </div>
                    <small class="text-muted">Off: deals keep their own price and the discount is only on the other dishes.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold d-block">Branches</label>
                    <? if (!$DiscountBranches) { ?>
                        <small class="text-muted">No branch is on the website yet (Restaurant &gt; Branches).</small>
                    <? } ?>
                    <? foreach ($DiscountBranches as $BranchId => $Branch) { ?>
                        <div class="custom-control custom-checkbox custom-control-inline">
                            <input type="checkbox" class="custom-control-input" id="discount_branch_<?=$BranchId?>" name="discount_branches[]" value="<?=$BranchId?>" <?=$Branch['enabled'] ? 'checked' : ''?>>
                            <label class="custom-control-label" for="discount_branch_<?=$BranchId?>"><?=h($Branch['name'])?></label>
                        </div>
                    <? } ?>
                    <small class="text-muted d-block">Only ticked branches give the discount; customers of the other branches pay full price.</small>
                </div>
                <small class="text-muted d-block mb-3">Both dates count (the discount runs through the whole Till Date). Leave From empty to start today, Till empty to run until you change it. 0 or empty % = no discount.</small>
                <div class="text-right">
                    <button class="btn button color" type="submit"><i class="mdi mdi-content-save"></i> Save Discount</button>
                </div>
            </form>
        </div>
    </div>
</div>
<? include_once("../includes/inc_footer.php"); ?>
