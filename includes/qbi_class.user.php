<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( !class_exists( "WPC_QBI_User" ) ) {

    class WPC_QBI_User extends WPC_QBI_Common {

        /**
         * constructor
         **/
        function __construct() {
            $this->qbi_common_construct();
        }


        //end class
    }

}

