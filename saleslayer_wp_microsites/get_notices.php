<?php

    header_remove();

	if ($_POST['id']) {

        session_start();

        define('SLYR__PLUGIN_DIR', dirname(__FILE__).'/');

        include_once(SLYR__PLUGIN_DIR.'../../../wp-config.php');

		if ($_POST['id'] == get_option(SLYR_connector_id)) {

            echo '1';

			include_once(SLYR__PLUGIN_DIR.'settings.php');
			include_once(SLYR__PLUGIN_DIR.'admin/SlPlugin.class.php');

            session_write_close(); ob_end_flush(); flush(); ignore_user_abort(true);

            $conn = new SlPlugin();

			$conn->sync($_POST['id'], get_option(SLYR_connector_key));

		} else { echo '0'; }

	} else { echo '0'; }
