<?php

namespace StockAlert;

/**
 * Frontend handler class
 */
class Frontend {

    /**
     * Initialize the class
     */
    function __construct() {
        new Frontend\Add_Notify_Me_Button();
    }
}
