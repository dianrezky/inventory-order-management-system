<?php

namespace App\Controller;

use App\Core\Result;

class ProductApiController extends BaseController
{
    // ================================================================
    // ERROR CONSTANTS
    // ================================================================
    public const ERROR_NOT_FOUND = 'not_found';
    public const ERROR_UNAUTHORIZED = 'unauthorized';

    public function getAvailabilityAction($sku)
    {
        $authError = $this->requireAuth();
        if ($authError !== null) {
            // Caller is Fetch (not a browser), so respond with JSON 401 instead of redirecting.
            return $this->json(['error' => self::ERROR_UNAUTHORIZED], 401);
        }

        $availability = $this->container->getProductService()->getAvailability($sku);

        if ($availability instanceof Result) {
            $availability = ['error' => 'internal_error'];
            $status = 500;
        } elseif ($availability === null) {
            $availability = ['error' => self::ERROR_NOT_FOUND, 'sku' => $sku];
            $status = 404;
        } else {
            $status = 200;
        }

        return $this->json($availability, $status);
    }
}
