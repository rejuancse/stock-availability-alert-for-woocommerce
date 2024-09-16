<?php

namespace StockAlert;

/**
 * The admin class
 */
class Admin {

    /**
     * Initialize the class
     */
    function __construct() {
        new Admin\Initial_Setup();
        new Admin\Stock_Notifications_Menu();
        // new Admin\Banner_Image_Category_Banner();
    }
}
