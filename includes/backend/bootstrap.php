<?php
/**
 * Bootstrap file for the backend service layer.
 *
 * @package FP_Prenotazioni_Ristorante_PRO\Backend
 */

use RBF\Backend\Kernel;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-rbf-backend-kernel.php';
require_once __DIR__ . '/booking/class-rbf-booking-context.php';
require_once __DIR__ . '/booking/class-rbf-booking-request-validator.php';
require_once __DIR__ . '/booking/class-rbf-availability-service.php';
require_once __DIR__ . '/booking/class-rbf-booking-repository.php';
require_once __DIR__ . '/booking/class-rbf-notification-service.php';
require_once __DIR__ . '/booking/class-rbf-tracking-builder.php';
require_once __DIR__ . '/booking/class-rbf-booking-pipeline.php';

if (!function_exists('rbf_backend')) {
    /**
     * Retrieve the backend kernel or a specific service.
     *
     * @param string|null $service Optional service identifier.
     * @return Kernel|object
     */
    function rbf_backend($service = null) {
        static $kernel;

        if (!$kernel) {
            $kernel = new Kernel();
        }

        if ($service === null) {
            return $kernel;
        }

        return $kernel->get($service);
    }
}
