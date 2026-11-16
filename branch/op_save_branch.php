<?php
include_once("../connection/localhost.php");

$oBranch = new Branch();

if ($oBranch->isValidFormSubmission())
{
    $oBranch->initForSave();

    if (!$oBranch->isDelete())
    {
        $oBranch->saveMainTableData([
            $_POST['branch_name'],
            $_POST['address'],
            $_POST['phone'],
            $_POST['fax'],
            $_POST['email'],
            $_POST['is_warehouse'],
            $_POST['is_active']
        ]);

        // Restaurant website row of the branch (rs_branches), shown on the website or not ("Show on website" switch)
        RestaurantBranch::syncFromBranches();
        RestaurantBranch::setOnWebsite($oBranch->getVoucherId(), ($_POST['show_on_website'] ?? '') === 'Y');
    }
    else
        $oBranch->deleteFormData();

    $oBranch->notifyStatusMessage();
    Utils::redirectToReferer();
}
else
    ErrorHandler::UnauthorizedAccess();