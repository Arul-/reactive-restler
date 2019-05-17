<?php namespace Luracast\Restler\Contracts;

use Luracast\Restler\Data\Param;

/**
 * Validation classes should implement this interface
 */
interface ValidationInterface
{

    /**
     * method used for validation.
     *
     * @param mixed $input
     *            data that needs to be validated
     * @param \Luracast\Restler\Data\Param $info
     *            information to be used for validation
     * @return boolean false in case of failure or fixed value in the expected
     *         type
     * @throws \Luracast\Restler\RestException 400 with information about the
     * failed
     * validation
     */
    public static function validate($input, Param $info);
}

