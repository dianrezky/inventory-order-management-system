<?php

namespace App\Controller;

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

        if ($availability === null) {
            return $this->json(['error' => self::ERROR_NOT_FOUND, 'sku' => $sku], 404);
        }

        return $this->json($availability);
    }
}
