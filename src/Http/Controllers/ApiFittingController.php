<?php

namespace CryptaTech\Seat\Fitting\Http\Controllers;

use Seat\Api\Http\Controllers\Api\v2\ApiController;

/**
 * Class ApiFittingController.
 */
class ApiFittingController extends ApiController
{
    public function getFittingList()
    {
        return app(FittingController::class)->getFittingList();
    }

    public function getFittingById($id)
    {
        return app(FittingController::class)->getFittingById($id);
    }

    public function getDoctrineList()
    {
        return app(FittingController::class)->getDoctrineList();
    }

    public function getDoctrineById($id)
    {
        return app(FittingController::class)->getDoctrineById($id);
    }
}
