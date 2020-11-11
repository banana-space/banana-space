<?php
// Redirect to keep old URLs working
header( 'Location: demos.php?' . http_build_query( $_GET ) );
