<?php
include_once("../connection/localhost.php");

SecurityShell::redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    ErrorHandler::UnauthorizedAccess();

// Discount card posts on its own (section=discount)
if (($_POST['section'] ?? '') === 'discount')
{
    RestaurantWebsite::saveDiscount($_POST['discount_percent'] ?? 0, $_POST['discount_from'] ?? '', $_POST['discount_to'] ?? '', ($_POST['discount_on_deals'] ?? '') === 'Y',
                                     array_values((array)($_POST['discount_branches'] ?? [])));
    $Discount = RestaurantWebsite::getDiscount();
    $Branches = array_column(array_filter(RestaurantWebsite::getDiscountBranches(), fn($b) => $b['enabled']), 'name');
    SuccessHandler::setMessage("Website discount saved." . ($Discount['percent'] > 0 && $Branches
        ? " Customers of " . implode(', ', $Branches) . " get " . (float)$Discount['percent'] . "% off right now" . ($Discount['on_deals'] ? "." : " (deals excluded).")
        : " No discount is running today."));
    Utils::redirect("website_status.php");
}

RestaurantWebsite::save(($_POST['is_open'] ?? '') === 'Y', $_POST['open_time'] ?? '', $_POST['close_time'] ?? '', $_POST['closed_message'] ?? '',
                        array_values((array)($_POST['open_branches'] ?? [])),
                        (array)($_POST['branch_open_time'] ?? []), (array)($_POST['branch_close_time'] ?? []));

$Status = RestaurantWebsite::getStatus();
$ClosedBranches = array_column(array_filter(RestaurantWebsite::getWebsiteBranches(), fn($b) => !RestaurantWebsite::getBranchStatus($b)['open']), 'name');
SuccessHandler::setMessage("Website settings saved. The website is " . ($Status['open'] ? "open" : "closed") . " right now."
    . ($Status['open'] && $ClosedBranches ? " Closed now: " . implode(', ', $ClosedBranches) . "." : ""));

Utils::redirect("website_status.php");
